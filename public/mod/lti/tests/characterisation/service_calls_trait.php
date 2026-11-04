<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_lti\characterisation;

/**
 * Helpers for the service characterisation tests that call as a tool signing with OAuth 1.0 does.
 *
 * Next to {@see characterisation_trait}, which calls with a bearer token. A slice that moves the services changes the
 * names in this file only:
 *  - /mod/lti/service.php and /mod/lti/services.php => /ltix/service.php and /ltix/services.php
 *  - lti_build_sourcedid()                         => \core_ltix\helper::build_sourcedid()
 *  - \ltiservice_* classes                         => \ltixservice_* classes
 *  - \mod_lti\local\ltiservice\response            => its core_ltix class
 *  - \moodle\mod\lti\OAuth* classes                => their core_ltix classes
 *
 * Adapt per slice alongside characterisation_trait.php. The names live in the helpers below, except in the tests that pass
 * the \ltiservice_* classes of the service they call.
 *
 * @package    mod_lti
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait service_calls_trait {
    /**
     * The path of the Basic Outcomes endpoint.
     *
     * @return string
     */
    protected function lti_basic_outcomes_script(): string {
        return '/mod/lti/service.php';
    }

    /**
     * The path of the endpoint that serves the services other than Basic Outcomes.
     *
     * @return string
     */
    protected function lti_services_script(): string {
        return '/mod/lti/services.php';
    }

    /**
     * The namespace of the OAuth 1.0 classes that sign a request.
     *
     * @return string
     */
    protected function lti_oauth_namespace(): string {
        return '\\moodle\\mod\\lti\\';
    }

    /**
     * A new, empty response object of the services.
     *
     * @return object
     */
    protected function lti_new_service_response(): object {
        $class = '\\mod_lti\\local\\ltiservice\\response';
        return new $class();
    }

    /**
     * The sourcedid a tool is given at launch, and sends back to read or write a result.
     *
     * @param \stdClass $lti the activity.
     * @param int $userid the launching user.
     * @param int $launchid the launch.
     * @param int $typeid the tool.
     * @return string the sourcedid.
     */
    protected function lti_sourcedid(\stdClass $lti, int $userid, int $launchid, int $typeid): string {
        return json_encode(lti_build_sourcedid($lti->id, $userid, $lti->servicesalt, $typeid, $launchid));
    }

    /**
     * Set the request variables as a web server does for a request to a Moodle script, and sign it as a tool does.
     *
     * @param string $method the HTTP method.
     * @param string $script the path of the script, relative to the site root, including any path after it.
     * @param string $key the OAuth consumer key.
     * @param string $secret the shared secret.
     * @param string $body the request body.
     * @param array $query query parameters.
     */
    private function lti_set_signed_request(
        string $method,
        string $script,
        string $key,
        string $secret,
        string $body,
        array $query = []
    ): void {
        global $CFG;
        $url = $CFG->wwwroot . $script;
        $oauth = $this->lti_oauth_namespace();
        $consumerclass = $oauth . 'OAuthConsumer';
        $requestclass = $oauth . 'OAuthRequest';
        $signatureclass = $oauth . 'OAuthSignatureMethod_HMAC_SHA1';
        $consumer = new $consumerclass($key, $secret);
        $request = $requestclass::from_consumer_and_token($consumer, null, $method, $url, $query);
        $request->set_parameter('oauth_body_hash', base64_encode(sha1($body, true)));
        $request->sign_request(new $signatureclass(), $consumer, null);

        $parts = parse_url($url);
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['HTTP_HOST'] = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $_SERVER['SERVER_PORT'] = $parts['scheme'] === 'https' ? '443' : '80';
        $_SERVER['REQUEST_URI'] = $parts['path'] . ($query ? '?' . http_build_query($query) : '');
        $_SERVER['QUERY_STRING'] = http_build_query($query);
        $_SERVER['HTTP_Authorization'] = preg_replace('/^Authorization: /', '', $request->to_header());
    }

    /**
     * Forget the request variables set for a call.
     */
    private function lti_clear_request(): void {
        foreach (
            ['REQUEST_METHOD', 'HTTP_HOST', 'SERVER_PORT', 'REQUEST_URI', 'QUERY_STRING', 'PATH_INFO', 'HTTP_Authorization',
                'HTTP_ACCEPT', 'CONTENT_TYPE'] as $name
        ) {
            unset($_SERVER[$name]);
        }
        $_GET = [];
    }

    /**
     * Call an LTI service endpoint as a tool does, in process, signing the request with OAuth 1.0 and the tool's shared secret.
     *
     * This is how an LTI 2.0 tool proxy, and an LTI 1.1 tool with a key and secret, call the services.
     *
     * @param string $resourceclass the class of the endpoint's resource.
     * @param string $serviceclass the class of the service that owns the resource.
     * @param string $method the HTTP method.
     * @param string $pathinfo the path of the request after services.php.
     * @param string $key the OAuth consumer key.
     * @param string $secret the shared secret.
     * @param string $type the Accept header, or the Content-Type for a PUT or POST.
     * @param string $body the request body.
     * @param array $query query parameters.
     * @return object the response.
     */
    protected function lti_signed_service_call(
        string $resourceclass,
        string $serviceclass,
        string $method,
        string $pathinfo,
        string $key,
        string $secret,
        string $type = '',
        string $body = '',
        array $query = []
    ): object {
        $this->lti_set_signed_request($method, $this->lti_services_script() . $pathinfo, $key, $secret, $body, $query);
        $_SERVER['PATH_INFO'] = $pathinfo;
        $_SERVER[$method === 'GET' ? 'HTTP_ACCEPT' : 'CONTENT_TYPE'] = $type;
        $_GET = $query;

        $response = $this->lti_new_service_response();
        if ($method === 'GET') {
            $response->set_accept($type);
        } else {
            $response->set_content_type($type);
        }
        $response->set_request_data($body);
        try {
            $resource = new $resourceclass(new $serviceclass());
            $resource->execute($response);
        } finally {
            $this->lti_clear_request();
        }
        return $response;
    }

    /**
     * Post a Basic Outcomes message to the endpoint, as a tool does, by running the endpoint script in process.
     *
     * The script reads the message from php://input, which a test cannot fill, so the script is run with that one stream
     * served from the message.
     *
     * @param string $body the XML message.
     * @param string $key the OAuth consumer key.
     * @param string $secret the shared secret to sign with.
     * @return array [the XML the endpoint answered, the exception the endpoint raised or null]
     */
    protected function lti_basic_outcomes_call(string $body, string $key, string $secret): array {
        $this->lti_set_signed_request('POST', $this->lti_basic_outcomes_script(), $key, $secret, $body);
        $_SERVER['CONTENT_TYPE'] = 'application/xml';

        // Serve php://input from the message and leave every other php:// stream alone.
        $stream = new class {
            /** @var string the message served as php://input. */
            public static string $input = '';
            /** @var string the data of this stream, for php://input. */
            private string $data = '';
            /** @var int the position in the data. */
            private int $position = 0;
            /** @var resource|null the real stream, for any other php:// path. */
            private $real = null;
            /** @var resource the stream context, set by PHP. */
            public $context;

            /**
             * Open a stream.
             *
             * @param string $path the path.
             * @param string $mode the mode.
             * @return bool
             */
            public function stream_open(string $path, string $mode): bool {
                if ($path === 'php://input') {
                    $this->data = self::$input;
                    return true;
                }
                stream_wrapper_restore('php');
                $this->real = fopen($path, $mode);
                stream_wrapper_unregister('php');
                stream_wrapper_register('php', static::class);
                return $this->real !== false;
            }

            /**
             * Read from the stream.
             *
             * @param int $count the number of bytes.
             * @return string|false
             */
            public function stream_read(int $count) {
                if ($this->real) {
                    return fread($this->real, $count);
                }
                $chunk = substr($this->data, $this->position, $count);
                $this->position += strlen($chunk);
                return $chunk;
            }

            /**
             * Write to the stream.
             *
             * @param string $data the data.
             * @return int
             */
            public function stream_write(string $data): int {
                return $this->real ? (int) fwrite($this->real, $data) : 0;
            }

            /**
             * Whether the end of the stream has been reached.
             *
             * @return bool
             */
            public function stream_eof(): bool {
                return $this->real ? feof($this->real) : $this->position >= strlen($this->data);
            }

            /**
             * Stat the stream.
             *
             * @return array|false
             */
            public function stream_stat() {
                return [];
            }

            /**
             * Close the stream.
             */
            public function stream_close(): void {
                if ($this->real) {
                    fclose($this->real);
                }
            }
        };
        $stream::$input = $body;
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', get_class($stream));
        try {
            [$output, $thrown] = $this->lti_run_script($this->lti_basic_outcomes_script());
        } finally {
            stream_wrapper_restore('php');
            // The script installs its own exception handler; take it off again, as a web request ends.
            set_exception_handler(null);
            restore_exception_handler();
            restore_exception_handler();
            $this->lti_clear_request();
        }
        return [$output, $thrown];
    }

    /**
     * A Basic Outcomes message.
     *
     * @param string $type replaceResult, readResult or deleteResult.
     * @param string $sourcedid the sourcedid of the result.
     * @param float|null $score the score between 0 and 1, for replaceResult.
     * @return string the XML message.
     */
    protected function lti_basic_outcomes_message(string $type, string $sourcedid, ?float $score = null): string {
        $result = $score === null ? '' : "<result><resultScore><language>en</language><textString>{$score}</textString>" .
            '</resultScore></result>';
        $sourcedid = htmlspecialchars($sourcedid, ENT_XML1);
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">' .
            '<imsx_POXHeader><imsx_POXRequestHeaderInfo><imsx_version>V1.0</imsx_version>' .
            '<imsx_messageIdentifier>message-1</imsx_messageIdentifier></imsx_POXRequestHeaderInfo></imsx_POXHeader>' .
            "<imsx_POXBody><{$type}Request><resultRecord><sourcedGUID><sourcedId>{$sourcedid}</sourcedId></sourcedGUID>" .
            "{$result}</resultRecord></{$type}Request></imsx_POXBody></imsx_POXEnvelopeRequest>";
    }

    /**
     * Create a role that has only the given capabilities in a course, and a user enrolled in the course with it.
     *
     * @param \stdClass $course the course.
     * @param string[] $capabilities the capabilities the role allows.
     * @return \stdClass the user.
     */
    protected function lti_user_with_capabilities(\stdClass $course, array $capabilities): \stdClass {
        $generator = $this->getDataGenerator();
        $roleid = $generator->create_role();
        foreach ($capabilities as $capability) {
            assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        }
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, $roleid);
        return $user;
    }
}
