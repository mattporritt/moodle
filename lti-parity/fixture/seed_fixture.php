<?php
// Seeds a Moodle 5.3 site with realistic, deterministic mod_lti data.
// Run through bin/ltip-run against a Moodle 5.3 tree, see lti-parity/README.md (bin/build-fixture.sh does it).
define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
$CFG->debug = DEBUG_DEVELOPER;
$CFG->debugdisplay = 1;
$CFG->noemailever = true;
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');
require_once($CFG->dirroot . '/mod/lti/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/gradelib.php');

\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$ltigen = $gen->get_plugin_generator('mod_lti');
$out = [];

// Categories, courses, users.
$cata = $gen->create_category(['name' => 'Fixture Cat A', 'idnumber' => 'CATA']);
$catb = $gen->create_category(['name' => 'Fixture Cat B', 'idnumber' => 'CATB']);
$c1 = $gen->create_course(['fullname' => 'LTI Fixture Course 1', 'shortname' => 'LTIC1', 'category' => $cata->id, 'idnumber' => 'LTIC1']);
$c2 = $gen->create_course(['fullname' => 'LTI Fixture Course 2', 'shortname' => 'LTIC2', 'category' => $catb->id, 'idnumber' => 'LTIC2']);
$teacher = $gen->create_user(['username' => 'teacher1', 'firstname' => 'Tessa', 'lastname' => 'Teacher',
    'email' => 'teacher1@example.com', 'password' => 'Teacher-pass1!', 'idnumber' => 'T001']);
$student = $gen->create_user(['username' => 'student1', 'firstname' => 'Sam', 'lastname' => 'Student',
    'email' => 'student1@example.com', 'password' => 'Student-pass1!', 'idnumber' => 'S001']);
foreach ([$c1, $c2] as $c) {
    $gen->enrol_user($teacher->id, $c->id, 'editingteacher');
    $gen->enrol_user($student->id, $c->id, 'student');
}

// Site tool types.
$common = function (array $extra) {
    return (object) array_merge([
        'lti_sendname' => LTI_SETTING_ALWAYS,
        'lti_sendemailaddr' => LTI_SETTING_ALWAYS,
        'lti_acceptgrades' => LTI_SETTING_ALWAYS,
        'lti_allowroster' => LTI_SETTING_ALWAYS,
        'lti_launchcontainer' => LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
        'lti_organizationid_default' => LTI_DEFAULT_ORGID_SITEHOST,
        'lti_forcessl' => 0,
    ], $extra);
};
$types = [];
$addtype = function (string $key, string $name, string $url, array $config, array $type = []) use ($common, &$types) {
    global $DB;
    $config['lti_typename'] = $name;
    $config['lti_toolurl'] = $url;
    $t = (object) array_merge(['state' => LTI_TOOL_STATE_CONFIGURED], $type);
    $id = lti_add_type($t, $common($config));
    // Deterministic service salt.
    $DB->set_field('lti_types_config', 'value', 'fixturesalt-' . $key, ['typeid' => $id, 'name' => 'servicesalt']);
    $types[$key] = $id;
    return $id;
};

$svc = [
    'ltiservice_gradesynchronization' => 2,
    'ltiservice_memberships' => 1,
    'ltiservice_toolsettings' => 1,
    'ltiservice_profile' => 1,
];
$custom = "tool_param=fixed\nuser_username=\$User.username\nfull_name=\$Person.name.full\ncontext=\$Context.id\n" .
    "rl=\$ResourceLink.id\nrl_title=\$ResourceLink.title\nlast_unknown=\$Unknown.thing\nmoodle_userid=\$Moodle.Person.userGroupIds\n" .
    "course_sd=\$CourseSection.sourcedId\ncoursestart=\$Context.timeFrame.begin";

// 1. LTI 1.1, activity chooser, all services.
$addtype('t11', 'LTI11 Chooser Tool', 'https://tool11.example.com/launch', $svc + [
    'lti_resourcekey' => 'key11', 'lti_password' => 'secret11', 'lti_customparameters' => $custom,
    'lti_description' => 'LTI 1.1 tool shown in chooser', 'lti_coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
    'lti_contentitem' => 0,
    'lti_icon' => 'https://tool11.example.com/icon.png',
]);
// 2. LTI 1.1 preconfigured only.
$addtype('t11pre', 'LTI11 Preconfigured Tool', 'https://pre11.example.com/launch', $svc + [
    'lti_resourcekey' => 'keypre', 'lti_password' => 'secretpre', 'lti_coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
    'lti_customparameters' => 'pre=1',
]);
// 3. LTI 1.1 not shown.
$addtype('t11hidden', 'LTI11 Not Shown Tool', 'https://hidden11.example.com/launch', [
    'lti_resourcekey' => 'keyhid', 'lti_password' => 'secrethid', 'lti_coursevisible' => LTI_COURSEVISIBLE_NO,
    'lti_acceptgrades' => LTI_SETTING_NEVER, 'lti_sendname' => LTI_SETTING_NEVER, 'lti_sendemailaddr' => LTI_SETTING_NEVER,
    'lti_allowroster' => LTI_SETTING_NEVER,
]);
// 4. LTI 1.3 chooser with deep linking.
$addtype('t13', 'LTI13 Chooser Tool', 'https://tool13.example.com/launch', $svc + [
    'lti_ltiversion' => LTI_VERSION_1P3, 'lti_clientid' => 'fixture-client-13',
    'lti_keytype' => 'JWK_KEYSET', 'lti_publickeyset' => 'https://tool13.example.com/jwks',
    'lti_initiatelogin' => 'https://tool13.example.com/login',
    'lti_redirectionuris' => "https://tool13.example.com/redirect\nhttps://tool13.example.com/redirect2",
    'lti_contentitem' => 1, 'lti_toolurl_ContentItemSelectionRequest' => 'https://tool13.example.com/deeplink',
    'lti_customparameters' => $custom,
    'lti_coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER,
    'ltiservice_gradesynchronization' => 2, 'ltiservice_memberships' => 1, 'ltiservice_toolsettings' => 1,
], ['ltiversion' => LTI_VERSION_1P3, 'clientid' => 'fixture-client-13']);
// 5. LTI 1.3 restricted to Cat B.
$addtype('t13cat', 'LTI13 Category B Tool', 'https://catb13.example.com/launch', [
    'lti_ltiversion' => LTI_VERSION_1P3, 'lti_clientid' => 'fixture-client-13cat',
    'lti_keytype' => 'JWK_KEYSET', 'lti_publickeyset' => 'https://catb13.example.com/jwks',
    'lti_initiatelogin' => 'https://catb13.example.com/login', 'lti_redirectionuris' => 'https://catb13.example.com/redirect',
    'lti_coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER, 'lti_coursecategories' => (string) $catb->id,
    'ltiservice_gradesynchronization' => 1, 'ltiservice_memberships' => 0, 'ltiservice_toolsettings' => 0,
], ['ltiversion' => LTI_VERSION_1P3, 'clientid' => 'fixture-client-13cat']);

// 6. LTI 2.0 tool proxy + tool type.
$proxyid = lti_add_tool_proxy((object) [
    'lti_registrationname' => 'LTI2 Proxy', 'lti_registrationurl' => 'https://tool2.example.com/register',
]);
$DB->update_record('lti_tool_proxies', (object) [
    'id' => $proxyid, 'guid' => 'fixture-guid-lti2', 'secret' => 'fixture-secret-lti2',
    'state' => LTI_TOOL_PROXY_STATE_ACCEPTED, 'vendorcode' => 'fixturevendor',
    'toolproxy' => json_encode(['@context' => 'http://purl.imsglobal.org/ctx/lti/v2/ToolProxy', 'tool_profile' => []]),
]);
$enabledcaps = ['basic-lti-launch-request', 'User.id', 'Person.name.full', 'Person.email.primary', 'Context.id',
    'ResourceLink.id', 'ResourceLink.title', 'Membership.role', 'Result.sourcedId', 'Result.autocreate', 'Context.title'];
$t2 = (object) [
    'name' => 'LTI2 Tool', 'baseurl' => 'https://tool2.example.com/launch', 'tooldomain' => 'tool2.example.com',
    'state' => LTI_TOOL_STATE_CONFIGURED, 'course' => $SITE->id, 'coursevisible' => LTI_COURSEVISIBLE_PRECONFIGURED,
    'ltiversion' => LTI_VERSION_2, 'toolproxyid' => $proxyid, 'enabledcapability' => implode("\n", $enabledcaps),
    'parameter' => json_encode([['name' => 'lti2_custom', 'value' => '$User.id']]), 'createdby' => $USER->id,
    'timecreated' => 1700000000, 'timemodified' => 1700000000, 'description' => 'LTI 2 tool', 'icon' => '', 'secureicon' => '',
];
$types['t2'] = $DB->insert_record('lti_types', $t2);
foreach (['servicesalt' => 'fixturesalt-t2', 'launchcontainer' => LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS,
    'sendname' => 1, 'sendemailaddr' => 1, 'acceptgrades' => 1, 'allowroster' => 1, 'forcessl' => 0, 'coursevisible' => 1] as $k => $v) {
    $DB->insert_record('lti_types_config', ['typeid' => $types['t2'], 'name' => $k, 'value' => $v]);
}
// Tool settings (system level on proxy, context level, binding level added after activities).
lti_set_tool_settings(['system_setting_a' => 'sysval', 'system_setting_b' => 'sysval2'], $proxyid);
lti_set_tool_settings(['ctx_setting' => 'ctxval'], $proxyid, $c1->id);
// Type level settings: lti_set_tool_settings() cannot insert these on 5.3 (toolproxyid NOT NULL), so insert directly.
foreach (['t11' => ['sys11' => 'sys11val', 'sys11b' => 'x'], 't13' => ['sys13' => 'sys13val']] as $k => $sv) {
    $DB->insert_record('lti_tool_settings', ['toolproxyid' => 0, 'typeid' => $types[$k], 'course' => null,
        'coursemoduleid' => null, 'settings' => json_encode($sv), 'timecreated' => 1700000000, 'timemodified' => 1700000000]);
}

// 7. Course level tool in course 1.
$types['tcourse'] = $ltigen->create_course_tool_types([
    'name' => 'Course 1 Own Tool', 'baseurl' => 'https://course1tool.example.com/launch', 'course' => $c1->id,
    'lti_resourcekey' => 'keyc1', 'lti_password' => 'secretc1', 'lti_customparameters' => 'c1=yes',
    'lti_ltiversion' => LTI_VERSION_1,
]);
$DB->set_field('lti_types_config', 'value', 'fixturesalt-tcourse', ['typeid' => $types['tcourse'], 'name' => 'servicesalt']);

// lti_coursevisible per-course override: hidden tool shown in chooser for course 2; chooser tool hidden in course 2.
$DB->insert_record('lti_coursevisible', ['typeid' => $types['t11hidden'], 'courseid' => $c2->id,
    'coursevisible' => LTI_COURSEVISIBLE_ACTIVITYCHOOSER]);
$DB->insert_record('lti_coursevisible', ['typeid' => $types['t11'], 'courseid' => $c2->id,
    'coursevisible' => LTI_COURSEVISIBLE_NO]);

// Activities.
$acts = [];
$mk = function (string $key, int $courseid, array $r) use ($ltigen, &$acts, $DB) {
    $inst = $ltigen->create_instance(array_merge(['course' => $courseid, 'name' => $key, 'resourcekey' => '', 'password' => ''], $r));
    $DB->set_field('lti', 'servicesalt', 'actsalt-' . $key, ['id' => $inst->id]);
    $acts[$key] = $inst;
    return $inst;
};
$mk('A01 LTI11 chooser', $c1->id, ['typeid' => $types['t11'], 'instructorcustomparameters' => "inst_param=1\nrole=\$Membership.role",
    'instructorchoiceallowroster' => 1, 'instructorchoiceallowsetting' => 1,
    'lineitemresourceid' => 'res-A01', 'lineitemtag' => 'tagA01']);
$mk('A02 LTI11 preconf', $c1->id, ['typeid' => $types['t11pre'], 'grade' => 50]);
$mk('A03 LTI11 not shown', $c1->id, ['typeid' => $types['t11hidden'], 'instructorchoiceacceptgrades' => 0]);
$mk('A04 LTI13', $c1->id, ['typeid' => $types['t13'], 'instructorcustomparameters' => 'inst13=yes', 'grade' => 100,
    'lineitemresourceid' => 'res-A04', 'lineitemtag' => 'tagA04', 'lineitemsubreviewurl' => 'https://tool13.example.com/review',
    'lineitemsubreviewparams' => 'a=b&c=d']);
$mk('A05 LTI2', $c1->id, ['typeid' => $types['t2']]);
$mk('A06 course tool', $c1->id, ['typeid' => $types['tcourse']]);
$mk('A07 manual LTI11', $c1->id, ['toolurl' => 'https://manual.example.com/launch', 'resourcekey' => 'mkey', 'password' => 'msecret',
    'instructorchoiceacceptgrades' => 0, 'instructorcustomparameters' => "manual=1\nuser=\$User.id",
    'securetoolurl' => 'https://manual-secure.example.com/launch']);
$mk('A08 manual with grade', $c1->id, ['toolurl' => 'https://manualgrade.example.com/launch', 'resourcekey' => 'mgkey',
    'password' => 'mgsecret', 'instructorchoiceacceptgrades' => 1, 'grade' => 100, 'instructorchoicesendemailaddr' => 0,
    'instructorchoicesendname' => 1, 'instructorchoiceallowroster' => 1]);
$mk('A09 url-matched', $c1->id, ['toolurl' => 'https://tool11.example.com/launch/extra/path', 'typeid' => 0]);
$mk('A10 LTI13 c2', $c2->id, ['typeid' => $types['t13']]);
$mk('A11 LTI13 cat-restricted', $c2->id, ['typeid' => $types['t13cat'], 'grade' => 20]);
$mk('A12 LTI11 c2', $c2->id, ['typeid' => $types['t11'], 'instructorcustomparameters' => 'c2=1', 'resourcekey' => 'instkey12', 'password' => 'instsecret12']);
$mk('A13 manual 1.1 c2 unsigned', $c2->id, ['toolurl' => 'https://unsigned.example.com/launch', 'resourcekey' => '', 'password' => '',
    'instructorchoiceacceptgrades' => 0]);

// A standalone (tool-created) AGS line item in course 1 for the LTI 1.3 tool.
$gbs = new \ltiservice_gradebookservices\local\service\gradebookservices();
$gbs->add_standalone_lineitem((string) $c1->id, 'Standalone line item', 10.0, 'https://tool13.example.com/launch', null, 'standalone-res', 'standalone-tag', $types['t13']);
// Binding-level tool settings for activity A05 (cm id) and A01.
foreach (['A05 LTI2', 'A01 LTI11 chooser'] as $k) {
    $cmid = get_coursemodule_from_instance('lti', $acts[$k]->id)->id;
    if ($k === 'A05 LTI2') {
        lti_set_tool_settings(['binding_setting' => 'bindval'], $proxyid, $c1->id, $cmid);
    }
}

// Submissions and grades.
foreach (['A08 manual with grade', 'A01 LTI11 chooser', 'A04 LTI13'] as $i => $k) {
    $inst = $acts[$k];
    $DB->insert_record('lti_submission', [
        'ltiid' => $inst->id, 'userid' => $student->id, 'datesubmitted' => 1700000000 + $i, 'dateupdated' => 1700000100 + $i,
        'gradepercent' => 80 - $i, 'originalgrade' => 80 - $i, 'launchid' => 1001 + $i, 'state' => 1,
    ]);
}
foreach (['A08 manual with grade', 'A04 LTI13', 'A02 LTI11 preconf'] as $k) {
    $inst = $DB->get_record('lti', ['id' => $acts[$k]->id]);
    $inst->cmidnumber = '';
    lti_grade_item_update($inst, [$student->id => ['userid' => $student->id, 'rawgrade' => $inst->grade ? $inst->grade * 0.8 : 80]]);
}

// Capability overrides.
$studentrole = $DB->get_record('role', ['shortname' => 'student']);
$teacherrole = $DB->get_record('role', ['shortname' => 'teacher']);
$etrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
$customrole = create_role('LTI Custom Manager', 'ltimgr', 'Custom role with mod/lti caps', '');
set_role_contextlevels($customrole, [CONTEXT_SYSTEM, CONTEXT_COURSECAT, CONTEXT_COURSE, CONTEXT_MODULE]);
$sysctx = context_system::instance();
assign_capability('mod/lti:manage', CAP_ALLOW, $customrole, $sysctx->id, true);
assign_capability('mod/lti:addinstance', CAP_ALLOW, $customrole, $sysctx->id, true);
assign_capability('mod/lti:view', CAP_ALLOW, $customrole, $sysctx->id, true);
assign_capability('mod/lti:addpreconfiguredinstance', CAP_ALLOW, $customrole, $sysctx->id, true);
// Site-level override of archetype roles.
assign_capability('mod/lti:requesttooladd', CAP_PREVENT, $etrole->id, $sysctx->id, true);
assign_capability('mod/lti:addcoursetool', CAP_ALLOW, $teacherrole->id, $sysctx->id, true);
assign_capability('mod/lti:admin', CAP_ALLOW, $customrole, $sysctx->id, true);
// Category level.
$catactx = context_coursecat::instance($cata->id);
$catbctx = context_coursecat::instance($catb->id);
assign_capability('mod/lti:addinstance', CAP_PROHIBIT, $teacherrole->id, $catactx->id, true);
assign_capability('mod/lti:manage', CAP_PREVENT, $studentrole->id, $catbctx->id, true);
// Course level.
$c1ctx = context_course::instance($c1->id);
$c2ctx = context_course::instance($c2->id);
assign_capability('mod/lti:view', CAP_PREVENT, $studentrole->id, $c2ctx->id, true);
assign_capability('mod/lti:addcoursetool', CAP_PREVENT, $etrole->id, $c2ctx->id, true);
assign_capability('mod/lti:requesttooladd', CAP_ALLOW, $studentrole->id, $c1ctx->id, true);
assign_capability('mod/lti:manage', CAP_ALLOW, $customrole, $c1ctx->id, true);
// Module level.
$cm1 = get_coursemodule_from_instance('lti', $acts['A01 LTI11 chooser']->id);
$cm1ctx = context_module::instance($cm1->id);
assign_capability('mod/lti:view', CAP_PROHIBIT, $studentrole->id, $cm1ctx->id, true);
assign_capability('mod/lti:manage', CAP_ALLOW, $studentrole->id, $cm1ctx->id, true);
role_assign($customrole, $teacher->id, $c2ctx->id);
// Also assign custom role to an extra user at category level.
$mgr = $gen->create_user(['username' => 'ltimgr1', 'firstname' => 'Mia', 'lastname' => 'Manager', 'email' => 'ltimgr1@example.com']);
role_assign($customrole, $mgr->id, $catactx->id);
accesslib_clear_all_caches(true);

// Force generation of the site's LTI 1.3 signing keys now so that they are part of the fixture.
\core\session\manager::set_user($DB->get_record('user', ['id' => $student->id]));
$PAGE->set_course($c1);
$lti = $DB->get_record('lti', ['id' => $acts['A04 LTI13']->id]);
$lti->cmid = get_coursemodule_from_instance('lti', $lti->id)->id;
[$ep, $params] = lti_get_launch_data($lti, 'seednonce');
echo "keys forced; 1.3 params: " . implode(',', array_keys($params)) . "\n";
\core\session\manager::set_user(get_admin());

echo "== types ==\n";
print_r($types);
echo "== acts ==\n";
foreach ($acts as $k => $i) {
    echo "$k lti=" . $i->id . ' cm=' . get_coursemodule_from_instance('lti', $i->id)->id . "\n";
}
echo "course1=$c1->id course2=$c2->id cata=$cata->id catb=$catb->id teacher=$teacher->id student=$student->id mgr=$mgr->id customrole=$customrole proxy=$proxyid\n";
