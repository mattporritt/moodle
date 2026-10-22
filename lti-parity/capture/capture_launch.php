<?php
// Captures the launch data every LTI activity would send, as teacher1 and student1, for ANY code tree
// (Moodle 5.3, BASE, slices 1..5).  Usage (inside the ltixrun container):
//   bin/ltip-run <tree> <db> <data> php /ltip/capture/capture_launch.php --label=s1 --out=/ltip/out/s1.json --sidecar=/ltip/out/s1
// (see lti-parity/README.md; bin/capture.sh wraps this.)
// Writes:
//   <out>                  normalised launch data (volatile values replaced by markers) - compared across trees
//   <sidecar>.raw.json     un-normalised launch data
//   <sidecar>.verify.json  internal validity checks of the raw data (OAuth1 signature / JWT signature / sourcedid hash)
//   <sidecar>.notices.json debugging()/deprecation output seen per activity/user
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;
$CFG->noemailever = true;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/modinfolib.php');
require_once($CFG->dirroot . '/mod/lti/lib.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

[$opts] = cli_get_params(['label' => 'tree', 'out' => '', 'sidecar' => '', 'only' => ''], []);
if (!$opts['out']) {
    fwrite(STDERR, "--out required\n");
    exit(2);
}

$ltix = class_exists('\core_ltix\helper');
/** Calls the tree's implementation of lti_<name>() (mod_lti function on old trees, core_ltix helper on new trees). */
function L(string $name, ...$args) {
    global $ltix;
    if ($ltix) {
        return call_user_func_array(['\core_ltix\helper', $name], $args);
    }
    return call_user_func_array('lti_' . $name, $args);
}

const VOLATILE_KEYS = ['oauth_nonce', 'oauth_timestamp', 'oauth_signature', 'jti', 'iat', 'exp', 'nbf', 'nonce', 'state'];

function marker(string $what): string {
    return "<VOLATILE:$what>";
}

/** Normalises ONLY volatile values; keeps a marker so key presence/structure is still compared. */
function normalise($v, string $key = '') {
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $item) {
            $out[$k] = normalise($item, (string) $k);
        }
        return $out;
    }
    if (!is_string($v) && !is_int($v) && !is_float($v)) {
        return $v;
    }
    if (in_array($key, VOLATILE_KEYS, true)) {
        return marker($key);
    }
    if (is_string($v)) {
        // Result sourcedid container {"data":{"instanceid":..,"userid":..,"typeid":..,"launchid":<random>},"hash":"<derived>"}.
        if ($v !== '' && $v[0] === '{') {
            $j = json_decode($v, true);
            if (is_array($j) && isset($j['data']['launchid']) && isset($j['hash'])) {
                $j['data']['launchid'] = marker('launchid');
                $j['hash'] = marker('hash(launchid)');
                return json_encode($j, JSON_UNESCAPED_SLASHES);
            }
            // LTI 1.3 lti_message_hint JSON {"cmid":..,"launchid":"ltilaunch<id>_<random>"}.
            if (is_array($j) && isset($j['launchid']) && is_string($j['launchid'])) {
                $j['launchid'] = preg_replace('/_\d+$/', '_' . marker('rand'), $j['launchid']);
                return json_encode($j, JSON_UNESCAPED_SLASHES);
            }
        }
        // A JWT carried as a value (s4b: lti_message_hint is a signed JWT holding the pre-built launch claims): decode it so its
        // claims are compared, with the volatile claims (iat/exp/jti/nonce) and the signature normalised.
        if (preg_match('/^eyJ[A-Za-z0-9_-]+\.eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*$/', $v)) {
            $p = explode('.', $v);
            return ['_jwt_header' => json_decode(b64url_decode($p[0]), true),
                '_jwt_claims' => normalise(json_decode(b64url_decode($p[1]), true)), '_jwt_signature' => marker('jwt-signature')];
        }
        // Session key embedded in the return URL.
        $v = preg_replace('/([?&]sesskey=)[A-Za-z0-9]+/', '$1' . marker('sesskey'), $v);
    }
    return $v;
}

function b64url_decode(string $s): string {
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

function decode_jwt(string $jwt): array {
    $parts = explode('.', $jwt);
    return [
        'header' => json_decode(b64url_decode($parts[0]), true),
        'claims' => json_decode(b64url_decode($parts[1] ?? ''), true),
    ];
}

/** Parses the first <form> of the launch html into [action, params]. */
function parse_form(string $html): array {
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8"?>' . $html);
    $form = $dom->getElementsByTagName('form')->item(0);
    $params = [];
    foreach ($dom->getElementsByTagName('input') as $in) {
        $params[$in->getAttribute('name')] = $in->getAttribute('value');
    }
    return [$form ? $form->getAttribute('action') : '', $params, $form ? $form->getAttribute('method') : ''];
}

/** Independent OAuth 1.0a HMAC-SHA1 signature check. */
function check_oauth1(string $endpoint, array $params, string $secret): array {
    $given = $params['oauth_signature'] ?? null;
    if ($given === null) {
        return ['checked' => false, 'reason' => 'no oauth_signature (unsigned launch)'];
    }
    $url = parse_url($endpoint);
    $base = strtolower($url['scheme']) . '://' . strtolower($url['host']) . (isset($url['port']) ? ':' . $url['port'] : '') .
        ($url['path'] ?? '');
    $all = $params;
    unset($all['oauth_signature']);
    if (!empty($url['query'])) {
        parse_str($url['query'], $q);
        $all += $q;
    }
    $pairs = [];
    foreach ($all as $k => $val) {
        $pairs[] = [rawurlencode((string) $k), rawurlencode((string) $val)];
    }
    usort($pairs, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
    $str = implode('&', array_map(fn($p) => $p[0] . '=' . $p[1], $pairs));
    $sbs = 'POST&' . rawurlencode($base) . '&' . rawurlencode($str);
    $calc = base64_encode(hash_hmac('sha1', $sbs, rawurlencode($secret) . '&', true));
    return ['checked' => true, 'valid' => hash_equals($calc, $given)];
}

function tree_jwks(): array {
    global $ltix;
    $cls = $ltix ? '\core_ltix\local\ltiopenid\jwks_helper' : '\mod_lti\local\ltiopenid\jwks_helper';
    return $cls::get_jwks();
}

function check_jwt(string $jwt, string $nonce, string $expectedaud): array {
    try {
        $keys = \Firebase\JWT\JWK::parseKeySet(tree_jwks(), 'RS256');
        $dec = \Firebase\JWT\JWT::decode($jwt, $keys);
        $c = (array) $dec;
        return [
            'checked' => true, 'valid' => true,
            'nonce_matches' => ($c['nonce'] ?? null) === $nonce,
            'aud_matches' => ($c['aud'] ?? null) === $expectedaud,
            'has_iat_exp' => isset($c['iat'], $c['exp']),
        ];
    } catch (\Throwable $e) {
        return ['checked' => true, 'valid' => false, 'error' => $e->getMessage()];
    }
}

/** Secret lookup (independent of the tree's helper). */
function find_secret(stdClass $lti, ?stdClass $type): array {
    global $DB;
    if ($type && !empty($type->toolproxyid)) {
        $p = $DB->get_record('lti_tool_proxies', ['id' => $type->toolproxyid]);
        return [$p->guid, $p->secret];
    }
    $cfg = $type ? $DB->get_records_menu('lti_types_config', ['typeid' => $type->id], '', 'name,value') : [];
    $key = !empty($lti->resourcekey) ? $lti->resourcekey : ($cfg['resourcekey'] ?? '');
    $secret = !empty($lti->password) ? $lti->password : ($cfg['password'] ?? '');
    return [$key, $secret];
}

function fresh_page(stdClass $course, stdClass $cm): void {
    global $PAGE, $COURSE;
    $PAGE = new moodle_page();
    $GLOBALS['PAGE'] = $PAGE;
    $PAGE->set_url('/mod/lti/launch.php', ['id' => $cm->id]);
    $PAGE->set_cm($cm, $course);
    $PAGE->set_context(context_module::instance($cm->id));
}

/** s4b: launch via the new core_ltix controllers' services, when the tree has them. */
function new_launch_available(): bool {
    return class_exists('\core_ltix\local\lticore\message\launchrequest\service\v1p1\v1p1_resource_link_launch_service')
        && class_exists('\core_ltix\local\placement\placement_service')
        && method_exists('\core_ltix\local\placement\placement_service', 'get_launch_url_for_link')
        && class_exists('\core_ltix\local\ltiopenid\lti_oidc_authenticator')
        && strpos(file_get_contents($GLOBALS['CFG']->dirroot . '/mod/lti/view.php'), 'get_launch_url_for_link') !== false;
}

$users = [];
foreach (['teacher1', 'student1'] as $un) {
    $users[$un] = $DB->get_record('user', ['username' => $un], '*', MUST_EXIST);
}

$result = ['_meta' => [
    'core_ltix_present' => $ltix,
], 'activities' => []];
$raw = [];
$verify = [];
$notices = [];

$ltis = $DB->get_records('lti', null, 'id');
foreach ($ltis as $lti) {
    $cm = get_coursemodule_from_instance('lti', $lti->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $actkey = sprintf('lti%02d %s', $lti->id, $lti->name);
    if ($opts['only'] !== '' && !in_array((string) $lti->id, explode(',', $opts['only']))) {
        continue;
    }
    foreach ($users as $un => $user) {
        foreach ($un === 'teacher1' ? ['normal', 'foruser'] : ['normal'] as $variant) {
            $entrykey = $variant === 'normal' ? $un : "$un+$variant";
            $entry = [];
            $rawentry = [];
            $vrf = [];
            ob_start();
            try {
                \core\session\manager::set_user($user);
                $SESSION = new stdClass();
                $GLOBALS['SESSION'] = $SESSION;
                fresh_page($course, $cm);
                $context = context_module::instance($cm->id);
                $entry['enrolled'] = is_enrolled(context_course::instance($course->id), $user, '', true);
                $entry['can_view'] = has_capability('mod/lti:view', $context);
                $foruserid = $variant === 'foruser' ? (int) $users['student1']->id : 0;
                $instance = clone $lti;
                $instance->cmid = $cm->id;

                // What launch.php does.
                $typeid = $instance->typeid;
                $tool = null;
                if (empty($typeid) && ($tool = L('get_tool_by_url_match', $instance->toolurl))) {
                    $typeid = $tool->id;
                }
                $entry['resolved_typeid'] = $typeid ? (int) $typeid : 0;
                $type = $typeid ? $DB->get_record('lti_types', ['id' => $typeid]) : null;
                $is13 = false;
                $config = null;
                if ($typeid) {
                    $tc = L('get_type_config', $typeid);
                    if (!empty($tc)) {
                        $config = L('get_type_type_config', $typeid);
                        $is13 = ($config->lti_ltiversion === '1.3.0');
                    } else {
                        $entry['missing_tool_type'] = true;
                    }
                }

                // Mirrors mod/lti/view.php on slice 4b: links with a typeid launch through the core_ltix route; links
                // without one (manually configured / domain matched legacy instances) use mod/lti/launch.php.
                $rl = $DB->get_manager()->table_exists('lti_resource_link') ? $DB->get_record('lti_resource_link', [
                    'component' => 'mod_lti', 'itemtype' => 'mod_lti:activityplacement', 'itemid' => $cm->id]) : false;
                $usenew = new_launch_available() && $typeid && $rl && !empty($rl->typeid);
                $entry['path'] = $usenew ? 'core_ltix launch service (s4b route)' : ($ltix ? 'core_ltix helper::get_launch_data' : 'mod_lti lti_get_launch_data');

                // Emulate the request state of a real launch.php request (optional_param('id') is used by some services).
                $_GET = $usenew ? [] : ['id' => $cm->id];
                $_POST = [];
                $_REQUEST = $_GET;
                if (!$usenew) {
                    if ($is13) {
                        $msgtype = 'basic-lti-launch-request';
                        if ($variant === 'foruser') {
                            $msgtype = 'LtiSubmissionReviewRequest';
                        }
                        // Named arguments, so the call is right for every tree: the signature of initiate_login() gained a
                        // $placementtype parameter before $title, and a positional call would shift $foruserid into $text.
                        $html = L('initiate_login', courseid: $cm->course, cmid: $cm->id, instance: $instance, config: $config,
                            messagetype: $msgtype, foruserid: $foruserid);
                        [$action, $loginparams] = parse_form($html);
                        $entry['login_request'] = ['action' => $action, 'params' => $loginparams];
                        $rawentry['login_request'] = $entry['login_request'];
                        // The auth endpoint (mod/lti/auth.php) side.
                        $hint = json_decode($loginparams['lti_message_hint']);
                        $launchid = $hint->launchid;
                        [$acourseid, $atypeid, $aid, $amsgtype, $aforuserid] = explode(',', $SESSION->$launchid, 7);
                        $nonce = 'CAPTURE-NONCE';
                        // The auth request is a POST from the tool carrying lti_message_hint etc (no id param).
                        $_GET = [];
                        $_POST = ['scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post',
                            'prompt' => 'none', 'nonce' => $nonce, 'state' => 'CAPTURE-STATE',
                            'client_id' => $loginparams['client_id'], 'login_hint' => $loginparams['login_hint'],
                            'lti_message_hint' => $loginparams['lti_message_hint']];
                        $_REQUEST = $_POST;
                        [$endpoint, $params] = L('get_launch_data', $instance, $nonce, $amsgtype, $aforuserid);
                        $params['state'] = 'CAPTURE-STATE';
                        $entry['auth_session_foruserid'] = (string) $aforuserid;
                        $entry['auth_session_msgtype'] = $amsgtype;
                    } else {
                        [$endpoint, $params] = L('get_launch_data', $instance, '', '', $foruserid);
                    }
                } else {
                    // New launch services.
                    $link = \core_ltix\local\lticore\models\resource_link::get_record([
                        'component' => 'mod_lti', 'itemtype' => 'mod_lti:activityplacement', 'itemid' => $cm->id,
                    ], MUST_EXIST);
                    $url = \core_ltix\local\placement\placement_service::get_launch_url_for_link($link)->out(false);
                    preg_match('~/(v1p1|v1p3|v2p0)/resourcelink/(\d+)/launch~', $url, $m);
                    $entry['launch_route'] = preg_replace('~^.*?(/[^/]*/v\dp\d/)~', '$1', $url);
                    $svc = \core\di::get("\\core_ltix\\local\\lticore\\message\\launchrequest\\service\\{$m[1]}\\{$m[1]}_resource_link_launch_service");
                    $msg = $svc->launch((int) $m[2], $user);
                    $endpoint = $msg->get_url();
                    $params = $msg->get_parameters();
                    if ($m[1] === 'v1p3') {
                        $entry['login_request'] = ['action' => $endpoint, 'params' => $params];
                        $rawentry['login_request'] = $entry['login_request'];
                        // The auth endpoint (helper::get_auth_endpoint) side.
                        $auth = new \core_ltix\local\ltiopenid\lti_oidc_authenticator(
                            new \core_ltix\local\ltiopenid\lti_user_authenticator($user),
                            new \core_ltix\local\lticore\repository\tool_registration_repository(),
                            new \core_ltix\local\lticore\message\payload\lti_1px_payload_converter(
                                new \core_ltix\local\lticore\message\payload\lis_vocab_converter()),
                            new \core_ltix\local\lticore\message\substitution\factory\variable_substitutor_factory(
                                new \core_ltix\local\ltiservice\plugin_substitution_service(
                                    new \core_ltix\local\ltiservice\service_plugin_registry())),
                            \core_ltix\local\ltiopenid\jwks_helper::get_jwks()
                        );
                        $redirect = trim(explode("\n", $config->lti_redirectionuris)[0]);
                        $nonce = 'CAPTURE-NONCE';
                        $_GET = [];
                        $_POST = ['scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post',
                            'prompt' => 'none', 'nonce' => $nonce, 'state' => 'CAPTURE-STATE', 'client_id' => $params['client_id'],
                            'redirect_uri' => $redirect, 'login_hint' => $params['login_hint'],
                            'lti_message_hint' => $params['lti_message_hint']];
                        $_REQUEST = $_POST;
                        $authmsg = $auth->authenticate([
                            'scope' => 'openid', 'response_type' => 'id_token', 'response_mode' => 'form_post', 'prompt' => 'none',
                            'nonce' => $nonce, 'state' => 'CAPTURE-STATE', 'client_id' => $params['client_id'],
                            'redirect_uri' => $redirect, 'login_hint' => $params['login_hint'],
                            'lti_message_hint' => $params['lti_message_hint'],
                        ]);
                        $endpoint = $authmsg->get_url();
                        $params = $authmsg->get_parameters();
                    }
                }

                $rawentry['endpoint'] = $endpoint;
                $rawentry['params'] = $params;
                $entry['endpoint'] = $endpoint;
                $entry['method'] = 'POST';
                if (isset($params['id_token'])) {
                    $dec = decode_jwt($params['id_token']);
                    $pp = $params;
                    unset($pp['id_token']);
                    $entry['post_params'] = $pp;
                    $entry['jwt_header'] = $dec['header'];
                    $entry['jwt_claims'] = $dec['claims'];
                    $rawentry['jwt'] = $dec;
                    $vrf['jwt'] = check_jwt($params['id_token'], 'CAPTURE-NONCE', $type->clientid ?? '');
                } else {
                    $entry['post_params'] = $params;
                    [$key, $secret] = find_secret($instance, $type);
                    $vrf['oauth1'] = check_oauth1($endpoint, $params, $secret);
                    if (isset($params['lis_result_sourcedid'])) {
                        $sd = json_decode($params['lis_result_sourcedid'], true);
                        $salt = $DB->get_field('lti', 'servicesalt', ['id' => $lti->id]);
                        $vrf['sourcedid_hash_ok'] = isset($sd['data']) &&
                            hash('sha256', json_encode((object) $sd['data']) . $salt) === $sd['hash'];
                    }
                }
                if (isset($entry['jwt_claims'])) {
                    $sdc = $entry['jwt_claims']['https://purl.imsglobal.org/spec/lti-bo/claim/basicoutcome'] ?? null;
                    if ($sdc && isset($sdc['lis_result_sourcedid'])) {
                        $sd = json_decode($sdc['lis_result_sourcedid'], true);
                        $salt = $DB->get_field('lti', 'servicesalt', ['id' => $lti->id]);
                        $vrf['sourcedid_hash_ok'] = isset($sd['data']) &&
                            hash('sha256', json_encode((object) $sd['data']) . $salt) === $sd['hash'];
                    }
                }
            } catch (\Throwable $e) {
                $entry['error'] = get_class($e) . ': ' . $e->getMessage();
                $rawentry['error'] = $entry['error'];
            }
            $out = ob_get_clean();
            $out = trim(preg_replace('/\s+/', ' ', strip_tags($out)));
            if ($out !== '') {
                $notices[$actkey][$entrykey] = $out;
            }
            $result['activities'][$actkey][$entrykey] = normalise($entry);
            $raw[$actkey][$entrykey] = $rawentry;
            $verify[$actkey][$entrykey] = $vrf;
        }
    }
}

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR;
file_put_contents($opts['out'], json_encode($result, $flags) . "\n");
if ($opts['sidecar']) {
    file_put_contents($opts['sidecar'] . '.raw.json', json_encode($raw, $flags) . "\n");
    file_put_contents($opts['sidecar'] . '.meta.json', json_encode(['label' => $opts['label'], 'release' => $CFG->release,
        'version' => (string) $CFG->version, 'core_ltix_present' => $ltix], $flags) . "\n");
    file_put_contents($opts['sidecar'] . '.verify.json', json_encode($verify, $flags) . "\n");
    file_put_contents($opts['sidecar'] . '.notices.json', json_encode($notices, $flags) . "\n");
}
echo "captured " . count($result['activities']) . " activities -> {$opts['out']}\n";
