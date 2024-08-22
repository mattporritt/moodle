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

namespace aiprovider_awsbedrock;

use Aws\BedrockRuntime\BedrockRuntimeClient;
use core_ai\aiactions;
use core_ai\ratelimiter;

/**
 * Class provider.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider extends \core_ai\provider {
    /** @var string The openAI API key. */
    private string $apikey;

    /** @var string The organisation ID that goes with the key. */
    private string $apisecret;

    /** @var bool Is global rate limiting for the API enabled. */
    private bool $enableglobalratelimit;

    /** @var int The global rate limit. */
    private int $globalratelimit;

    /** @var bool Is user rate limiting for the API enabled */
    private bool $enableuserratelimit;

    /** @var int The user rate limit. */
    private int $userratelimit;

    /**
     * Class constructor.
     */
    public function __construct() {
        // Get api key from config.
        $this->apikey = get_config('aiprovider_awsbedrock', 'apikey');
        // Get api org id from config.
        $this->apisecret = get_config('aiprovider_awsbedrock', 'apisecret');
        // Get global rate limit from config.
        $this->enableglobalratelimit = get_config('aiprovider_awsbedrock', 'enableglobalratelimit');
        $this->globalratelimit = get_config('aiprovider_awsbedrock', 'globalratelimit');
        // Get user rate limit from config.
        $this->enableuserratelimit = get_config('aiprovider_awsbedrock', 'enableuserratelimit');
        $this->userratelimit = get_config('aiprovider_awsbedrock', 'userratelimit');
    }

    /**
     * Get the list of actions that this provider supports.
     *
     * @return array An array of action class names.
     */
    public function get_action_list(): array {
        return [
            \core_ai\aiactions\generate_text::class,
            \core_ai\aiactions\generate_image::class,
            \core_ai\aiactions\summarise_text::class,
        ];
    }

    /**
     * Create the Bedrock API client.
     *
     * @param string $region The AWS region the model is hosted in
     * @param string $version The version of the webservice to utilize.
     * @return BedrockRuntimeClient The client used to make requests.
     */
    public function create_bedrock_client(string $region, string $version = 'latest'): BedrockRuntimeClient {
        return new BedrockRuntimeClient([
            'region' => $region,
            'version' => $version,
            'credentials' => [
                'key' => $this->apikey,
                'secret' => $this->apisecret,
            ]
        ]);
    }

    /**
     * Check if the request is allowed by the rate limiter.
     *
     * @param aiactions\base $action The action to check.
     * @return array|bool True on success, array of error details on failure.
     */
    public function is_request_allowed(aiactions\base $action): array|bool {
        $ratelimiter = ratelimiter::get_instance();
        $component = explode('\\', get_class($this))[0];

        // Check the user rate limit.
        if ($this->enableuserratelimit) {
            if (!$ratelimiter->check_user_rate_limit(
                    component: $component,
                    ratelimit: $this->userratelimit,
                    userid: $action->get_configuration('userid')
            )) {
                return [
                        'success' => false,
                        'errorcode' => 429,
                        'errormessage' => 'User rate limit exceeded',
                ];
            }
        }

        // Check the global rate limit.
        if ($this->enableglobalratelimit) {
            if (!$ratelimiter->check_global_rate_limit(
                    component: $component,
                    ratelimit: $this->globalratelimit)) {
                return [
                    'success' => false,
                    'errorcode' => 429,
                    'errormessage' => 'Global rate limit exceeded',
                ];
            }
        }

        return true;
    }

    /**
     * Get any action settings for this provider.
     *
     * @param string $action The action class name.
     * @param \admin_root $ADMIN The admin root object.
     * @param string $section The section name.
     * @param bool $hassiteconfig Whether the current user has moodle/site:config capability.
     * @return array An array of settings.
     */
    public function get_action_settings(
            string $action,
            \admin_root $ADMIN,
            string $section,
            bool $hassiteconfig)
    : array {
        $actionname =  substr($action, (strrpos($action, '\\') + 1));
        $settings = [];

        // Add AWS region.
        $settings[] = new \admin_setting_configselect(
                "aiprovider_awsbedrock/action_{$actionname}_region",
                new \lang_string('action_region', 'aiprovider_awsbedrock'),
                new \lang_string('action_region_desc', 'aiprovider_awsbedrock'),
                'ap-southeast-2',
                [
                    'af-south-1' => new \lang_string('action_region:af-south-1', 'aiprovider_awsbedrock'),
                    'ap-east-1' => new \lang_string('action_region:ap-east-1', 'aiprovider_awsbedrock'),
                    'ap-south-2' => new \lang_string('action_region:ap-south-2', 'aiprovider_awsbedrock'),
                    'ap-southeast-3' => new \lang_string('action_region:ap-southeast-3', 'aiprovider_awsbedrock'),
                    'ap-southeast-4' => new \lang_string('action_region:ap-southeast-4', 'aiprovider_awsbedrock'),
                    'ap-south-1' => new \lang_string('action_region:ap-south-1', 'aiprovider_awsbedrock'),
                    'ap-northeast-3' => new \lang_string('action_region:ap-northeast-3', 'aiprovider_awsbedrock'),
                    'ap-northeast-2' => new \lang_string('action_region:ap-northeast-2', 'aiprovider_awsbedrock'),
                    'ap-southeast-1' => new \lang_string('action_region:ap-southeast-1', 'aiprovider_awsbedrock'),
                    'ap-southeast-2' => new \lang_string('action_region:ap-southeast-2', 'aiprovider_awsbedrock'),
                    'ap-northeast-1' => new \lang_string('action_region:ap-northeast-1', 'aiprovider_awsbedrock'),
                    'ca-central-1' => new \lang_string('action_region:ca-central-1', 'aiprovider_awsbedrock'),
                    'ca-west-1' => new \lang_string('action_region:ca-west-1', 'aiprovider_awsbedrock'),
                    'eu-central-1' => new \lang_string('action_region:eu-central-1', 'aiprovider_awsbedrock'),
                    'eu-west-1' => new \lang_string('action_region:eu-west-1', 'aiprovider_awsbedrock'),
                    'eu-west-2' => new \lang_string('action_region:eu-west-2', 'aiprovider_awsbedrock'),
                    'eu-south-1' => new \lang_string('action_region:eu-south-1', 'aiprovider_awsbedrock'),
                    'eu-west-3' => new \lang_string('action_region:eu-west-3', 'aiprovider_awsbedrock'),
                    'eu-south-2' => new \lang_string('action_region:eu-south-2', 'aiprovider_awsbedrock'),
                    'eu-north-1' => new \lang_string('action_region:eu-north-1', 'aiprovider_awsbedrock'),
                    'eu-central-2' => new \lang_string('action_region:eu-central-2', 'aiprovider_awsbedrock'),
                    'il-central-1' => new \lang_string('action_region:il-central-1', 'aiprovider_awsbedrock'),
                    'me-south-1' => new \lang_string('action_region:me-south-1', 'aiprovider_awsbedrock'),
                    'me-central-1' => new \lang_string('action_region:me-central-1', 'aiprovider_awsbedrock'),
                    'sa-east-1' => new \lang_string('action_region:sa-east-1', 'aiprovider_awsbedrock'),
                    'us-east-2' => new \lang_string('action_region:us-east-2', 'aiprovider_awsbedrock'),
                    'us-east-1' => new \lang_string('action_region:us-east-1', 'aiprovider_awsbedrock'),
                    'us-west-1' => new \lang_string('action_region:us-west-1', 'aiprovider_awsbedrock'),
                    'us-west-2' => new \lang_string('action_region:us-west-2', 'aiprovider_awsbedrock'),
                    'us-gov-east-1' => new \lang_string('action_region:us-gov-east-1', 'aiprovider_awsbedrock'),
                    'us-gov-west-1' => new \lang_string('action_region:us-gov-west-1', 'aiprovider_awsbedrock'),
                ]
        );

        if ($actionname === 'generate_text' || $actionname === 'summarise_text') {
            // Add the model setting.
            $settings[] = new \admin_setting_configselect(
                "aiprovider_awsbedrock/action_{$actionname}_model",
                new \lang_string('action_model', 'aiprovider_awsbedrock'),
                new \lang_string('action_model_desc', 'aiprovider_awsbedrock'),
                    'anthropic.claude-3-sonnet-20240229-v1:0:200k',
                [
                    'amazon.titan-tg1-large' => new \lang_string('action_model:amazon.titan-tg1-large', 'aiprovider_awsbedrock'),
                    'amazon.titan-text-premier-v1:0' => new \lang_string('action_model:amazon.titan-text-premier-v1:0', 'aiprovider_awsbedrock'),
                    'amazon.titan-text-lite-v1:0:4k' => new \lang_string('action_model:amazon.titan-text-lite-v1:0:4k', 'aiprovider_awsbedrock'),
                    'amazon.titan-text-lite-v1' => new \lang_string('action_model:amazon.titan-text-lite-v1', 'aiprovider_awsbedrock'),
                    'amazon.titan-text-express-v1:0:8k' => new \lang_string('action_model:amazon.titan-text-express-v1:0:8k', 'aiprovider_awsbedrock'),
                    'amazon.titan-text-express-v1' => new \lang_string('action_model:amazon.titan-text-express-v1', 'aiprovider_awsbedrock'),
                    'ai21.j2-grande-instruct' => new \lang_string('action_model:ai21.j2-grande-instruct', 'aiprovider_awsbedrock'),
                    'ai21.j2-jumbo-instruct' => new \lang_string('action_model:ai21.j2-jumbo-instruct', 'aiprovider_awsbedrock'),
                    'ai21.j2-mid' => new \lang_string('action_model:ai21.j2-mid', 'aiprovider_awsbedrock'),
                    'ai21.j2-mid-v1' => new \lang_string('action_model:ai21.j2-mid-v1', 'aiprovider_awsbedrock'),
                    'ai21.j2-ultra' => new \lang_string('action_model:ai21.j2-ultra', 'aiprovider_awsbedrock'),
                    'ai21.j2-ultra-v1:0:8k' => new \lang_string('action_model:ai21.j2-ultra-v1:0:8k', 'aiprovider_awsbedrock'),
                    'ai21.j2-ultra-v1' => new \lang_string('action_model:ai21.j2-ultra-v1:0:8k', 'aiprovider_awsbedrock'),
                    'ai21.jamba-instruct-v1:0' => new \lang_string('action_model:ai21.jamba-instruct-v1:0', 'aiprovider_awsbedrock'),
                    'anthropic.claude-instant-v1:2:100k' => new \lang_string('action_model:anthropic.claude-instant-v1:2:100k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-instant-v1' => new \lang_string('action_model:anthropic.claude-instant-v1', 'aiprovider_awsbedrock'),
                    'anthropic.claude-v2:0:18k' => new \lang_string('action_model:anthropic.claude-v2:0:18k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-v2:0:100k' => new \lang_string('action_model:anthropic.claude-v2:0:100k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-v2:1:18k' => new \lang_string('action_model:anthropic.claude-v2:1:18k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-v2:1:200k' => new \lang_string('action_model:anthropic.claude-v2:1:200k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-v2:1' => new \lang_string('action_model:anthropic.claude-v2:1', 'aiprovider_awsbedrock'),
                    'anthropic.claude-v2' => new \lang_string('action_model:anthropic.claude-v2', 'aiprovider_awsbedrock'),
                    'anthropic.claude-3-sonnet-20240229-v1:0:28k' => new \lang_string('action_model:anthropic.claude-3-sonnet-20240229-v1:0:28k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-3-sonnet-20240229-v1:0:200k' => new \lang_string('action_model:anthropic.claude-3-sonnet-20240229-v1:0:200k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-3-sonnet-20240229-v1:0' => new \lang_string('action_model:anthropic.claude-3-sonnet-20240229-v1:0:200k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-3-haiku-20240307-v1:0:48k' => new \lang_string('action_model:anthropic.claude-3-haiku-20240307-v1:0:48k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-3-haiku-20240307-v1:0:200k' => new \lang_string('action_model:anthropic.claude-3-haiku-20240307-v1:0:200k', 'aiprovider_awsbedrock'),
                    'anthropic.claude-3-haiku-20240307-v1:0' => new \lang_string('action_model:anthropic.claude-3-haiku-20240307-v1:0', 'aiprovider_awsbedrock'),
                    'anthropic.claude-3-5-sonnet-20240620-v1:0' => new \lang_string('action_model:anthropic.claude-3-5-sonnet-20240620-v1:0', 'aiprovider_awsbedrock'),
                    'cohere.command-text-v14:7:4k' => new \lang_string('action_model:cohere.command-text-v14:7:4k', 'aiprovider_awsbedrock'),
                    'cohere.command-text-v14' => new \lang_string('action_model:cohere.command-text-v14', 'aiprovider_awsbedrock'),
                    'cohere.command-r-v1:0' => new \lang_string('action_model:cohere.command-r-v1:0', 'aiprovider_awsbedrock'),
                    'cohere.command-r-plus-v1:0' => new \lang_string('action_model:cohere.command-r-plus-v1:0', 'aiprovider_awsbedrock'),
                    'cohere.command-light-text-v14:7:4k' => new \lang_string('action_model:cohere.command-light-text-v14:7:4k', 'aiprovider_awsbedrock'),
                    'cohere.command-light-text-v14' => new \lang_string('action_model:cohere.command-light-text-v14', 'aiprovider_awsbedrock'),
                    'meta.llama2-13b-chat-v1:0:4k' => new \lang_string('action_model:meta.llama2-13b-chat-v1:0:4k', 'aiprovider_awsbedrock'),
                    'meta.llama2-13b-chat-v1' => new \lang_string('action_model:meta.llama2-13b-chat-v1', 'aiprovider_awsbedrock'),
                    'meta.llama2-70b-chat-v1:0:4k' => new \lang_string('action_model:meta.llama2-70b-chat-v1:0:4k', 'aiprovider_awsbedrock'),
                    'meta.llama2-70b-chat-v1' => new \lang_string('action_model:meta.llama2-70b-chat-v1', 'aiprovider_awsbedrock'),
                    'meta.llama2-13b-v1:0:4k' => new \lang_string('action_model:meta.llama2-13b-v1:0:4k', 'aiprovider_awsbedrock'),
                    'meta.llama2-13b-v1' => new \lang_string('action_model:meta.llama2-13b-v1', 'aiprovider_awsbedrock'),
                    'meta.llama2-70b-v1:0:4k' => new \lang_string('action_model:meta.llama2-70b-v1:0:4k', 'aiprovider_awsbedrock'),
                    'meta.llama2-70b-v1' => new \lang_string('action_model:meta.llama2-70b-v1', 'aiprovider_awsbedrock'),
                    'meta.llama3-8b-instruct-v1:0' => new \lang_string('action_model:meta.llama3-8b-instruct-v1:0', 'aiprovider_awsbedrock'),
                    'meta.llama3-70b-instruct-v1:0' => new \lang_string('action_model:meta.llama3-70b-instruct-v1:0', 'aiprovider_awsbedrock'),
                    'mistral.mistral-7b-instruct-v0:2' => new \lang_string('action_model:mistral.mistral-7b-instruct-v0:2', 'aiprovider_awsbedrock'),
                    'mistral.mixtral-8x7b-instruct-v0:1' => new \lang_string('action_model:mistral.mixtral-8x7b-instruct-v0:1', 'aiprovider_awsbedrock'),
                    'mistral.mistral-large-2402-v1:0' => new \lang_string('action_model:mistral.mistral-large-2402-v1:0', 'aiprovider_awsbedrock'),
                    'mistral.mistral-small-2402-v1:0' => new \lang_string('action_model:mistral.mistral-small-2402-v1:0', 'aiprovider_awsbedrock'),
                ],
            );

            // Add system instruction settings.
            $settings[] = new \admin_setting_configtextarea(
                    "aiprovider_awsbedrock/action_{$actionname}_systeminstruction",
                    new \lang_string('action_systeminstruction', 'aiprovider_awsbedrock'),
                    new \lang_string('action_systeminstruction_desc', 'aiprovider_awsbedrock'),
                    $action::get_system_instruction(),
                    PARAM_TEXT
            );
        } else if ($actionname === 'generate_image') {
            // Add the model setting.
            $settings[] = new \admin_setting_configselect(
                "aiprovider_awsbedrock/action_{$actionname}_model",
                new \lang_string('action_model', 'aiprovider_awsbedrock'),
                new \lang_string('action_model_desc', 'aiprovider_awsbedrock'),
                'stability.stable-diffusion-xl-v1:0',
                [
                    'amazon.titan-image-generator-v1:0' => new \lang_string('action_model:amazon.titan-image-generator-v1:0', 'aiprovider_awsbedrock'),
                    'amazon.titan-image-generator-v1' => new \lang_string('action_model:amazon.titan-image-generator-v1', 'aiprovider_awsbedrock'),
                    'amazon.titan-image-generator-v2:0' => new \lang_string('action_model:amazon.titan-image-generator-v2:0', 'aiprovider_awsbedrock'),
                    'stability.stable-diffusion-xl-v1:0' => new \lang_string('action_model:stability.stable-diffusion-xl-v1:0', 'aiprovider_awsbedrock'),
                    'stability.stable-diffusion-xl-v1' => new \lang_string('action_model:stability.stable-diffusion-xl-v1', 'aiprovider_awsbedrock'),
                ],
            );
        }

        return $settings;
    }
}
