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
$string['ai'] = 'AI';
$string['aisettings'] = 'Manage site wide AI settings';
$string['aiplacementsettings'] = 'Manage settings for AI placements';
$string['aiprovidersettings'] = 'Manage settings for AI providers';
$string['action'] = 'Action';
$string['action_generate_image'] = 'Generate image';
$string['action_generate_image_desc'] = 'Generates an image based on a text prompt.';
$string['action_generate_text'] = 'Generate text';
$string['action_generate_text_desc'] = 'Generates text based on a text prompt.';
$string['action_summarise_text'] = 'Summarise text';
$string['action_summarise_text_desc'] = 'Summarises text based on provided input text.';
$string['action_translate_text'] = 'Translate text';
$string['action_translate_text_desc'] = 'Translate provided text from one language to another.';
$string['availableproviders'] = 'Available AI providers';
$string['availableproviders_desc'] = 'Select an AI provider to manage its settings.<br/>
AI providers are responsible for providing the AI services used by the AI subsystem. <br/>
Each enabled provider makes available one or more "AI Actions". The settings for these actions can be managed on the settings page for each provider plugin.';
$string['availableplacements'] = 'Available AI placements';
$string['availableplacements_desc'] = 'Select an AI placement to manage its settings.<br/>
AI placements are responsible for determining where and how AI services are used within the Moodle interface. <br/>
Each enabled placement uses one or more "AI Actions". The preferences for these actions can be managed on the settings page for each placement plugin.';
$string['cachedef_ai_ratelimit'] = 'Cache to store request rate limits related to the AI subsystem.';
$string['manageaiproviders'] = 'Manage AI providers';
$string['manageaiplacements'] = 'Manage AI placements';
$string['placementactionsettings'] = 'Placement action settings';
$string['placementactionsettings_desc'] = 'These settings the settings for actions that are supported by this AI placement.<br/>
Each action has its own settings that can be configured here.';
$string['placementsettings'] = 'Placement specific settings';
$string['placementsettings_desc'] = 'These settings control various aspects of this AI placement.<br/>
They control how the placement connects to the AI service, and related operations';
$string['privacy:metadata'] = 'The AI subsystem currently does not store any user data.';
$string['providers'] = 'Providers';
$string['provideractionsettings'] = 'Provider action settings';
$string['provideractionsettings_desc'] = 'These settings the settings for actions that are supported by this AI provider.<br/>
Each action has its own settings that can be configured here.';
$string['providersettings'] = 'Provider specific settings';
$string['providersettings_desc'] = 'These settings control various aspects of this AI provider.<br/>
They control how the provider connects to the AI service, and related operations';
