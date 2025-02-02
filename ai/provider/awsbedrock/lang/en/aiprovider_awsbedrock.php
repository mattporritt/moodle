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

/**
 * Strings for component aiprovider_awsbedrock, language 'en'.
 *
 * @package    aiprovider_awsbedrock
 * @copyright  2025 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action:generate_image:endpoint'] = 'API endpoint';
$string['action:generate_image:model'] = 'AI model';
$string['action:generate_image:model_help'] = 'The model used to generate images from user prompts.';
$string['action:generate_text:endpoint'] = 'API endpoint';
$string['action:generate_text:model'] = 'AI model';
$string['action:generate_text:model_help'] = 'The model used to generate the text response.';
$string['action:generate_text:systeminstruction'] = 'System instruction';
$string['action:generate_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';
$string['action:summarise_text:endpoint'] = 'API endpoint';
$string['action:summarise_text:model'] = 'AI model';
$string['action:summarise_text:model_help'] = 'The model used to summarise the provided text.';
$string['action:summarise_text:systeminstruction'] = 'System instruction';
$string['action:summarise_text:systeminstruction_help'] = 'This instruction is sent to the AI model along with the user\'s prompt. Editing this instruction is not recommended unless absolutely required.';
$string['apikey'] = 'OpenAI API key';
$string['apikey_help'] = 'Get a key from your <a href="https://platform.awsbedrock.com/account/api-keys" target="_blank">OpenAI API keys</a>.';
$string['custom_model_name'] = 'Custom model name';
$string['extraparams'] = 'Extra parameters';
$string['extraparams_help'] = 'Extra parameters can be configured here. We support JSON format. For example:
<pre>
{
    "temperature": 0.5,
    "max_tokens": 100
}
</pre>';
$string['invalidjson'] = 'Invalid JSON string';
$string['orgid'] = 'OpenAI organization ID';
$string['orgid_help'] = 'Get your OpenAI organization ID from your <a href="https://platform.awsbedrock.com/account/org-settings" target="_blank">OpenAI account</a>.';
$string['pluginname'] = 'OpenAI API Provider';
$string['privacy:metadata'] = 'The OpenAI API provider plugin does not store any personal data.';
$string['privacy:metadata:aiprovider_awsbedrock:externalpurpose'] = 'This information is sent to the OpenAI API in order for a response to be generated. Your OpenAI account settings may change how OpenAI stores and retains this data. No user data is explicitly sent to OpenAI or stored in Moodle LMS by this plugin.';
$string['privacy:metadata:aiprovider_awsbedrock:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_awsbedrock:numberimages'] = 'When generating images the number of images used in the response.';
$string['privacy:metadata:aiprovider_awsbedrock:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_awsbedrock:responseformat'] = 'The format of the response. When generating images.';
$string['settings'] = 'Settings';
$string['settings_frequency_penalty'] = 'frequency_penalty';
$string['settings_frequency_penalty_help'] = 'Penalizes new tokens based on their frequency in the text so far';
$string['settings_help'] = 'You can adjust the settings below to customize how requests are sent to OpenAI. Update the values as needed, ensuring they align with your requirements.<br><br>';
$string['settings_max_tokens'] = 'max_tokens';
$string['settings_max_tokens_help'] = 'The maximum number of tokens to generate in the response';
$string['settings_presence_penalty'] = 'presence_penalty';
$string['settings_presence_penalty_help'] = 'Penalizes new tokens based on whether they appear in the text so far';
$string['settings_top_p'] = 'top_p';
$string['settings_top_p_help'] = 'Controls nucleus sampling';
