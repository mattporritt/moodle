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


$string['action_model'] = 'AI model id';
$string['action_model_desc'] = 'The AI model used to process the request and generate a response.';
$string['action_region'] = 'Region';
$string['action_region_desc'] = 'The AWS region where the AI model is hosted.';
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
