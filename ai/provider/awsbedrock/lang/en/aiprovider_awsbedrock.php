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
$string['apikey'] = 'Amazon API key credential';
$string['apikey_help'] = 'Generate a key using IAM in the AWS console or using the AWS CLI.';
$string['apisecret'] = 'Amazon API secret credential';
$string['apisecret_help'] = 'Generate a secret using IAM in the AWS console or using the AWS CLI.';
$string['awsregion'] = 'Region';
$string['awsregion_help'] = 'The AWS region where the AI model is hosted.';
$string['awsregion:af-south-1'] = 'Africa (Cape Town)';
$string['awsregion:ap-east-1'] = 'Asia Pacific (Hong Kong)';
$string['awsregion:ap-south-2'] = 'Asia Pacific (Hyderabad)';
$string['awsregion:ap-southeast-3'] = 'Asia Pacific (Jakarta)';
$string['awsregion:ap-southeast-4'] = 'Asia Pacific (Melbourne)';
$string['awsregion:ap-south-1'] = 'Asia Pacific (Mumbai)';
$string['awsregion:ap-northeast-3'] = 'Asia Pacific (Osaka)';
$string['awsregion:ap-northeast-2'] = 'Asia Pacific (Seoul)';
$string['awsregion:ap-southeast-1'] = 'Asia Pacific (Singapore)';
$string['awsregion:ap-southeast-2'] = 'Asia Pacific (Sydney)';
$string['awsregion:ap-northeast-1'] = 'Asia Pacific (Tokyo)';
$string['awsregion:ca-central-1'] = 'Canada (Central)';
$string['awsregion:ca-west-1'] = 'Canada West (Calgary)';
$string['awsregion:eu-central-1'] = 'Europe (Frankfurt)';
$string['awsregion:eu-west-1'] = 'Europe (Ireland)';
$string['awsregion:eu-west-2'] = 'Europe (London)';
$string['awsregion:eu-south-1'] = 'Europe (Milan)';
$string['awsregion:eu-west-3'] = 'Europe (Paris)';
$string['awsregion:eu-south-2'] = 'Europe (Spain)';
$string['awsregion:eu-north-1'] = 'Europe (Stockholm)';
$string['awsregion:eu-central-2'] = 'Europe (Zurich)';
$string['awsregion:il-central-1'] = 'Israel (Tel Aviv)';
$string['awsregion:me-south-1'] = 'Middle East (Bahrain)';
$string['awsregion:me-central-1'] = 'Middle East (UAE)';
$string['awsregion:sa-east-1'] = 'South America (São Paulo)';
$string['awsregion:us-east-2'] = 'US East (Ohio)';
$string['awsregion:us-east-1'] = 'US East (N. Virginia)';
$string['awsregion:us-west-1'] = 'US West (N. California)';
$string['awsregion:us-west-2'] = 'US West (Oregon)';
$string['awsregion:us-gov-east-1'] = 'AWS GovCloud (US-East)';
$string['awsregion:us-gov-west-1'] = 'AWS GovCloud (US-West)';
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
$string['pluginname'] = 'AWS Bedrock Provider';
$string['privacy:metadata'] = 'The AWS Bedrock provider plugin does not store any personal data.';
$string['privacy:metadata:aiprovider_awsbedrock:externalpurpose'] = 'This information is sent to AWS in order for a response to be generated. Your AWS account settings may change how AWS stores and retains this data. No user data is explicitly sent to AWS or stored in Moodle LMS by this plugin.';
$string['privacy:metadata:aiprovider_awsbedrock:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_awsbedrock:numberimages'] = 'When generating images the number of images used in the response.';
$string['privacy:metadata:aiprovider_awsbedrock:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_awsbedrock:responseformat'] = 'The format of the response. When generating images.';
$string['settings'] = 'Settings';
$string['settings_frequency_penalty'] = 'Frequency penalty';
$string['settings_frequency_penalty_help'] = 'Penalizes new tokens based on their frequency in the text so far. Resulting in fewer repeated words.';
$string['settings_help'] = 'You can adjust the settings below to customize how requests are sent to OpenAI. Update the values as needed, ensuring they align with your requirements.<br><br>';
$string['settings_max_tokens'] = 'max_tokens';
$string['settings_max_tokens_help'] = 'The maximum number of tokens to generate in the response';
$string['settings_presence_penalty'] = 'Presence penalty';
$string['settings_presence_penalty_help'] = 'Reduce the frequency of repeated words within a single message by increasing this number. Unlike frequency penalty, presence penalty is the same no matter how many times a word appears.';
$string['settings_schema_version'] = 'Schema Version';
$string['settings_schema_version_help'] = 'Schema version to use for the request';
$string['settings_stop_sequences'] = 'Stop Sequence';
$string['settings_stop_sequences_help'] = 'Specify a character sequence to indicate where the model should stop';
$string['settings_temperature'] = 'Temperature';
$string['settings_temperature_help'] = 'Use a lower value to decrease randomness in responses.';
$string['settings_top_k'] = 'Top K';
$string['settings_top_k_help'] = 'Only sample from the top K options for each subsequent token.';
$string['settings_top_p'] = 'Top P';
$string['settings_top_p_help'] = 'Use a lower value to ignore less probable options and decrease the diversity of responses.';
