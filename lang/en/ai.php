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
 * Strings for component 'ai', language 'en'
 *
 * @package    core
 * @category   string
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['action'] = 'Action';
$string['action_generate_image'] = 'Generate image';
$string['action_generate_image_desc'] = 'Generates an image based on a text prompt.';
$string['action_generate_text'] = 'Generate text';
$string['action_generate_text_desc'] = 'Generates text based on a text prompt.';
$string['action_summarise_text'] = 'Summarise text';
$string['action_summarise_text_desc'] = 'Summarises text based on provided input text.';
$string['action_summarise_text_instruction'] = 'You will receive a text input from the user. Your task is to summarize the provided text. Follow these guidelines:
    1. Condense: Shorten long passages into key points.
    2. Simplify: Make complex information easier to understand, especially for learners.

Important Instructions:
    1. Return the summary in plain text only.
    2. Do not include any markdown formatting, greetings, or platitudes.
    3. Focus on clarity, conciseness, and accessibility.

Ensure the summary is easy to read and effectively conveys the main points of the original text.';
$string['action_translate_text'] = 'Translate text';
$string['action_translate_text_desc'] = 'Translate provided text from one language to another.';
$string['ai'] = 'AI';
$string['aiplacementsettings'] = 'Manage settings for AI placements';
$string['aiprovidersettings'] = 'Manage settings for AI providers';
$string['aisettings'] = 'Manage site wide AI settings';
$string['availableplacements'] = 'Available AI placements';
$string['availableplacements_desc'] = 'Select an AI placement to manage its settings.<br/>
AI placements are responsible for determining where and how AI services are used within the Moodle interface. <br/>
Each enabled placement uses one or more "AI Actions". Actions can be enabled or disabled for each provider in the provider plugin settings.';
$string['availableproviders'] = 'Available AI providers';
$string['availableproviders_desc'] = 'Select an AI provider to manage its settings.<br/>
AI providers are responsible for providing the AI services used by the AI subsystem. <br/>
Each enabled provider makes available one or more "AI Actions". Actions can be enabled or disabled for each provider in the provider plugin settings.';
$string['manageaiplacements'] = 'Manage AI placements';
$string['manageaiproviders'] = 'Manage AI providers';
$string['noproviders'] = 'This action is unavailable. No <a href="{$a}">providers</a> are configured for this action';
$string['placementactionsettings'] = 'Placement actions';
$string['placementactionsettings_desc'] = 'These are the actions that are supported by this AI placement.<br/>
Each action can be enabled or disabled for this placement.';
$string['placementsettings'] = 'Placement specific settings';
$string['placementsettings_desc'] = 'These settings control various aspects of this AI placement.<br/>
They control how the placement connects to the AI service, and related operations';
$string['privacy:metadata'] = 'The AI subsystem currently does not store any user data.';
$string['privacy:metadata:ai_action_generate_image'] = 'The list of generated images.';
$string['privacy:metadata:ai_action_generate_image:aspectratio'] = 'The aspect ratio of the generated images.';
$string['privacy:metadata:ai_action_generate_image:numberimages'] = 'The number of images to generate.';
$string['privacy:metadata:ai_action_generate_image:prompt'] = 'The instructions or queries to achieve a desired outcome.';
$string['privacy:metadata:ai_action_generate_image:quality'] = 'The quality of the image that will be generated.';
$string['privacy:metadata:ai_action_generate_image:revisedprompt'] = 'The revised prompt.';
$string['privacy:metadata:ai_action_generate_image:sourceurl'] = 'The source URL.';
$string['privacy:metadata:ai_action_generate_image:style'] = 'The style of the generated images.';
$string['privacy:metadata:ai_action_generate_text'] = 'The list of generated text.';
$string['privacy:metadata:ai_action_generate_text:completiontoken'] = 'The number of tokens used by the generated completion.';
$string['privacy:metadata:ai_action_generate_text:fingerprint'] = 'The unique hash representing the state/version of the model and content.';
$string['privacy:metadata:ai_action_generate_text:finishreason'] = 'The information on why a particular API call or response generation process concluded.';
$string['privacy:metadata:ai_action_generate_text:generatedcontent'] = 'The actual text generated by the AI model based on the input prompt.';
$string['privacy:metadata:ai_action_generate_text:prompt'] = 'The instructions or queries to achieve a desired outcome.';
$string['privacy:metadata:ai_action_generate_text:prompttokens'] = 'The number of tokens consumed by the input prompt.';
$string['privacy:metadata:ai_action_generate_text:responseid'] = 'The unique identifier for the specific API response, useful for tracking and debugging.';
$string['privacy:metadata:ai_action_register'] = 'Stores information about processed ai actions.';
$string['privacy:metadata:ai_action_register:actionid'] = 'ID in related action table with more details about the action.';
$string['privacy:metadata:ai_action_register:actionname'] = 'The Name of the action.';
$string['privacy:metadata:ai_action_register:contextid'] = 'The ID of the context the action request was made in';
$string['privacy:metadata:ai_action_register:errorcode'] = 'If there was an error this was the error code';
$string['privacy:metadata:ai_action_register:errormessage'] = 'If there was an error this was the message';
$string['privacy:metadata:ai_action_register:provider'] = 'The provider plugin name that processed the action';
$string['privacy:metadata:ai_action_register:success'] = 'Was the action successful when run';
$string['privacy:metadata:ai_action_register:timecompleted'] = 'Timestamp of when the action was completed, as recorded by the manager';
$string['privacy:metadata:ai_action_register:timecreated'] = 'Timestamp of when the action was created';
$string['privacy:metadata:ai_action_register:userid'] = 'The user who made the request to run the action';
$string['privacy:metadata:ai_action_summarise_text'] = 'The list of generated summarise text.';
$string['privacy:metadata:ai_action_summarise_text:completiontoken'] = 'The number of tokens used by the generated completion.';
$string['privacy:metadata:ai_action_summarise_text:fingerprint'] = 'The unique hash representing the state/version of the model and content.';
$string['privacy:metadata:ai_action_summarise_text:finishreason'] = 'The information on why a particular API call or response generation process concluded.';
$string['privacy:metadata:ai_action_summarise_text:generatedcontent'] = 'The actual text generated by the AI model based on the input prompt.';
$string['privacy:metadata:ai_action_summarise_text:prompt'] = 'The instructions or queries to achieve a desired outcome.';
$string['privacy:metadata:ai_action_summarise_text:prompttokens'] = 'The number of tokens consumed by the input prompt.';
$string['privacy:metadata:ai_action_summarise_text:responseid'] = 'The unique identifier for the specific API response, useful for tracking and debugging.';
$string['privacy:metadata:ai_policy_register'] = 'The list of policy acceptance from users.';
$string['privacy:metadata:ai_policy_register:contextid'] = 'The context.';
$string['privacy:metadata:ai_policy_register:timeaccepted'] = 'The time that the user accept the policy.';
$string['privacy:metadata:ai_policy_register:userid'] = 'The user that accepted the policy';
$string['provideractionsettings'] = 'Provider actions';
$string['provideractionsettings_desc'] = 'These settings are for the actions that are supported by this AI provider.<br/>
Each action can be enabled or disabled for this provider.<br/>
Each action can also have its own settings that can be configured here.';
$string['providers'] = 'Providers';
$string['providersettings'] = 'Provider specific settings';
$string['providersettings_desc'] = 'These settings control various aspects of this AI provider.<br/>
They control how the provider connects to the AI service, and related operations';
$string['userpolicy'] = 'Welcome to the AI-enhanced features of the Moodle LMS. Before you start, we\'d like to inform you about a few important points.<br/>

When you use the AI functionality, you\'re engaging with an advanced system designed to improve your experience. It\'s important to understand that while the AI strives to provide accurate and helpful responses, it may not always get everything right. Therefore, we encourage you to use your own judgment when interpreting the AI\'s suggestions. <br/>

To make the AI work effectively, it processes the information you provide by communicating with an external system. This means that your queries and the AI\'s responses are shared with this system to generate the best possible outcomes for you. <br/>

Additionally, a record of your interactions with the AI are kept within this LMS. <br/>

By continuing, you acknowledge that you understand and agree to these terms. We hope you enjoy using the AI features and find them beneficial to your learning journey.';
$string['userpolicyheading'] = 'AI policy';
