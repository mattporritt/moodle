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

namespace tiny_ai\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External API to call an action for this placement.
 *
 * @package    aiplacement_tinymce
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_image extends external_api {

    /**
     * Generate image parameters.
     *
     * @since  Moodle 4.5
     * @return external_function_parameters
     */
    public static function generate_image_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
            'contextid' => new external_value(
                PARAM_INT,
                'The context ID',
                VALUE_REQUIRED),
            'prompttext' => new external_value(
                PARAM_RAW,
                'The prompt text for the AI service',
                VALUE_REQUIRED),
            'aspectratio' => new external_value(
                PARAM_ALPHA,
                'The aspect ratio of the image',
                VALUE_REQUIRED),
            'quality' => new external_value(
                PARAM_ALPHA,
                'The quality of the image',
                VALUE_REQUIRED),
            'style' => new external_value(
                PARAM_ALPHA,
                'The style of the image',
                VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * Generate image from the AI placement.
     *
     * @since  Moodle 4.5
     * @param int $contextid The context ID.
     * @param string $prompttext The data encoded as a json array.
     * @param string $aspectratio The aspect ratio of the image.
     * @param string $quality The quality of the image.
     * @param string $style The style of the image.
     * @return array The generated content.
     */
    public static function generate_image(
            int $contextid,
            string $prompttext,
            string $aspectratio,
            string $quality,
            string $style = ''
        ): array {
        // Parameter validation.
        [
            'contextid' => $contextid,
            'prompttext' => $prompttext,
            'aspectratio' => $aspectratio,
            'quality' => $quality,
            'style' => $style
        ] = self::validate_parameters(self::generate_image_parameters(), [
            'contextid' => $contextid,
            'prompttext' => $prompttext,
            'aspectratio' => $aspectratio,
            'quality' => $quality,
            'style' => $style
        ]);
        // Context validation and permission check.
        // Get the context from the passed in ID.
        $context = \context::instance_by_id($contextid);

        // Check the user has permission to use the AI service.
        self::validate_context($context);
        require_capability('aiplacement/tinymce:generate_image', $context);

        // Execute API call.
        // TODO: Implement the AI service call here.
        return [];
    }

    /**
     * Generate content return value.
     *
     * @since  Moodle 4.5
     * @return external_function_parameters
     */
    public static function generate_image_returns(): external_function_parameters {
        return new external_function_parameters([
                'prompttext' => new external_value(
                        PARAM_RAW,
                        'Original prompt text',
                        VALUE_OPTIONAL),
                'model' => new external_value(
                        PARAM_ALPHANUMEXT,
                        'AI model used',
                        VALUE_OPTIONAL),
                'personality' => new external_value(
                        PARAM_TEXT,
                        'AI personality used',
                        VALUE_OPTIONAL),
                'generateddate' => new external_value(
                        PARAM_INT,
                        'Date AI content was generated',
                        VALUE_OPTIONAL),
                'generatedcontent' => new external_value(
                        PARAM_RAW,
                        'AI generated content',
                        VALUE_OPTIONAL),
                'errorcode' => new external_value(
                        PARAM_INT,
                        'Error code if any',
                        VALUE_OPTIONAL),
                'error' => new external_value(
                        PARAM_TEXT,
                        'Error message if any',
                        VALUE_OPTIONAL)
        ]);
    }
}
