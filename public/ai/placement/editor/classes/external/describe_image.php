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

namespace aiplacement_editor\external;

use aiplacement_editor\utils;
use core_ai\aiactions\describe_image as describe_image_action;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;

/**
 * External API for describing an editor image.
 *
 * @package    aiplacement_editor
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class describe_image extends external_api {
    /** @var int Maximum decoded image size accepted by this placement. */
    private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

    /**
     * Describe image parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'The editor context ID'),
            'imagedata' => new external_value(PARAM_RAW, 'Base64-encoded image content'),
            'mimetype' => new external_value(PARAM_TEXT, 'The image MIME type'),
            'descriptivecontext' => new external_value(PARAM_TEXT, 'Relevant surrounding editor content', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Generate an accessible description for an image.
     *
     * @param int $contextid The editor context ID.
     * @param string $imagedata Base64-encoded image content.
     * @param string $mimetype The image MIME type.
     * @param string $descriptivecontext Relevant surrounding editor content.
     * @return array The action response.
     */
    public static function execute(
        int $contextid,
        string $imagedata,
        string $mimetype,
        string $descriptivecontext = '',
    ): array {
        global $USER;

        [
            'contextid' => $contextid,
            'imagedata' => $imagedata,
            'mimetype' => $mimetype,
            'descriptivecontext' => $descriptivecontext,
        ] = self::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'imagedata' => $imagedata,
            'mimetype' => $mimetype,
            'descriptivecontext' => $descriptivecontext,
        ]);

        require_sesskey();
        $context = \core\context::instance_by_id($contextid);
        self::validate_context($context);

        if (
            !utils::is_html_editor_placement_action_available(
                $context,
                'describe_image',
                describe_image_action::class,
            )
        ) {
            throw new \moodle_exception('noeditor', 'aiplacement_editor');
        }

        if (!\core_ai\manager::get_user_policy_status((int) $USER->id)) {
            throw new \moodle_exception('policyacceptancerequired', 'aiplacement_editor');
        }

        $content = base64_decode($imagedata, true);
        if ($content === false || $content === '' || strlen($content) > self::MAX_IMAGE_BYTES) {
            throw new \invalid_parameter_exception('Invalid or oversized image content.');
        }

        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mimetype])) {
            throw new \invalid_parameter_exception('Unsupported image MIME type.');
        }
        $imageinfo = @getimagesizefromstring($content);
        if ($imageinfo === false || ($imageinfo['mime'] ?? '') !== $mimetype) {
            throw new \invalid_parameter_exception('Image content does not match its MIME type.');
        }

        $filename = 'image-' . bin2hex(random_bytes(8)) . '.' . $extensions[$mimetype];
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'aiplacement_editor',
            'filearea' => 'describe_image',
            'itemid' => $USER->id,
            'filepath' => '/',
            'filename' => $filename,
        ];
        $image = get_file_storage()->create_file_from_string($filerecord, $content);

        try {
            $action = new describe_image_action(
                contextid: $contextid,
                userid: (int) $USER->id,
                image: $image,
                purpose: get_string('describeimagepurpose', 'aiplacement_editor'),
                context: $descriptivecontext,
                language: current_language(),
            );
            $response = \core\di::get(\core_ai\manager::class)->process_action($action);
        } finally {
            $image->delete();
        }

        return [
            'success' => $response->get_success(),
            'generatedcontent' => $response->get_response_data()['generatedcontent'] ?? '',
            'errorcode' => $response->get_errorcode(),
            'error' => $response->get_error(),
            'errormessage' => $response->get_errormessage(),
        ];
    }

    /**
     * Describe image return value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'success' => new external_value(PARAM_BOOL, 'Whether the request succeeded'),
            'generatedcontent' => new external_value(PARAM_TEXT, 'The generated image description', VALUE_DEFAULT, ''),
            'errorcode' => new external_value(PARAM_INT, 'Error code if any', VALUE_DEFAULT, 0),
            'error' => new external_value(PARAM_TEXT, 'Error name if any', VALUE_DEFAULT, ''),
            'errormessage' => new external_value(PARAM_TEXT, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
