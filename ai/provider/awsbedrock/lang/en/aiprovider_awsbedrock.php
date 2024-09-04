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
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


$string['action_model'] = 'AI model';
$string['action_model:amazon.titan-text-lite-v1'] = 'Titan Text G1 - Lite, Amazon, 4k';
$string['action_model:amazon.titan-text-express-v1'] = 'Titan Text G1 - Express v1, Amazon, 8k';
$string['action_model:anthropic.claude-3-sonnet-20240229-v1:0'] = 'Claude 3 - Sonnet, Anthropic, 200k';
$string['action_model:anthropic.claude-3-haiku-20240307-v1:0'] = 'Claude 3 - Haiku, Anthropic, 200k';
$string['action_model:mistral.mistral-7b-instruct-v0:2'] = 'Mistral 7B Instruct, Mistral AI, 32k';
$string['action_model:mistral.mistral-large-2402-v1:0'] = 'Mistral Large (24.02), Mistral AI, 32k';
$string['action_model:mistral.mixtral-8x7b-instruct-v0:1'] = 'Mixtral 8x7B Instruct, Mistral AI, 32k';
$string['action_model_desc'] = 'The AI model used to process the request and generate a response.';
$string['action_region'] = 'Region';
$string['action_region_desc'] = 'The AWS region where the AI model is hosted.';
$string['action_region:af-south-1'] = 'Africa (Cape Town)';
$string['action_region:ap-east-1'] = 'Asia Pacific (Hong Kong)';
$string['action_region:ap-south-2'] = 'Asia Pacific (Hyderabad)';
$string['action_region:ap-southeast-3'] = 'Asia Pacific (Jakarta)';
$string['action_region:ap-southeast-4'] = 'Asia Pacific (Melbourne)';
$string['action_region:ap-south-1'] = 'Asia Pacific (Mumbai)';
$string['action_region:ap-northeast-3'] = 'Asia Pacific (Osaka)';
$string['action_region:ap-northeast-2'] = 'Asia Pacific (Seoul)';
$string['action_region:ap-southeast-1'] = 'Asia Pacific (Singapore)';
$string['action_region:ap-southeast-2'] = 'Asia Pacific (Sydney)';
$string['action_region:ap-northeast-1'] = 'Asia Pacific (Tokyo)';
$string['action_region:ca-central-1'] = 'Canada (Central)';
$string['action_region:ca-west-1'] = 'Canada West (Calgary)';
$string['action_region:eu-central-1'] = 'Europe (Frankfurt)';
$string['action_region:eu-west-1'] = 'Europe (Ireland)';
$string['action_region:eu-west-2'] = 'Europe (London)';
$string['action_region:eu-south-1'] = 'Europe (Milan)';
$string['action_region:eu-west-3'] = 'Europe (Paris)';
$string['action_region:eu-south-2'] = 'Europe (Spain)';
$string['action_region:eu-north-1'] = 'Europe (Stockholm)';
$string['action_region:eu-central-2'] = 'Europe (Zurich)';
$string['action_region:il-central-1'] = 'Israel (Tel Aviv)';
$string['action_region:me-south-1'] = 'Middle East (Bahrain)';
$string['action_region:me-central-1'] = 'Middle East (UAE)';
$string['action_region:sa-east-1'] = 'South America (São Paulo)';
$string['action_region:us-east-2'] = 'US East (Ohio)';
$string['action_region:us-east-1'] = 'US East (N. Virginia)';
$string['action_region:us-west-1'] = 'US West (N. California)';
$string['action_region:us-west-2'] = 'US West (Oregon)';
$string['action_region:us-gov-east-1'] = 'AWS GovCloud (US-East)';
$string['action_region:us-gov-west-1'] = 'AWS GovCloud (US-West)';
$string['action_systeminstruction'] = 'System Instruction';
$string['action_systeminstruction_desc'] = 'The instruction is used provided along with the user request for this action. It provides information to the AI model on how to generate the response.';
$string['apikey'] = 'Amazon API key credential';
$string['apikey_desc'] = 'Enter your AWS API key.';
$string['apisecret'] = 'Amazon API secret credential';
$string['apisecret_desc'] = 'Enter your AWS API secret';
$string['pluginname'] = 'AWS Bedrock Provider';
$string['enableglobalratelimit'] = 'Enable global rate limiting';
$string['enableglobalratelimit_desc'] = 'Enable global rate limiting for the AWS Bedrock API provider.';
$string['enableuserratelimit'] = 'Enable user rate limiting';
$string['enableuserratelimit_desc'] = 'Enable user rate limiting for the AWS Bedrock provider.';
$string['globalratelimit'] = 'Global rate limit';
$string['globalratelimit_desc'] = 'Set the number of requests per hour allowed for the global rate limit.';
$string['privacy:metadata'] = 'The AWS Bedrock provider plugin does not store any personal data.';
$string['privacy:metadata:aiprovider_awsbedrock:externalpurpose'] = 'This information is sent to the AWS Bedrock API in order for a response to be generated. Your AWS account settings may change how AWS stores and retains this data. No user data is explicitly sent to AWS or stored in Moodle LMS by this plugin.';
$string['privacy:metadata:aiprovider_awsbedrock:model'] = 'The model used to generate the response.';
$string['privacy:metadata:aiprovider_awsbedrock:numberimages'] = 'The number of images used in the response. When generating images.';
$string['privacy:metadata:aiprovider_awsbedrock:prompttext'] = 'The user entered text prompt used to generate the response.';
$string['privacy:metadata:aiprovider_awsbedrock:responseformat'] = 'The format of the response. When generating images.';
$string['userratelimit'] = 'User rate limit';
$string['userratelimit_desc'] = 'Set the number of requests per hour allowed for the user rate limit.';
