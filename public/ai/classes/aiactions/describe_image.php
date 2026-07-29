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

namespace core_ai\aiactions;

use core_ai\aiactions\responses\response_base;

/**
 * Describe an image action.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class describe_image extends base {
    /** @var string[] MIME types supported at the provider-independent action boundary. */
    public const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var array Verified image dimensions and type information. */
    private array $imageinfo;

    /**
     * Create a new describe image action.
     *
     * @param int $contextid The context id the action was created in.
     * @param int $userid The user requesting the action.
     * @param \stored_file $image The image to describe.
     * @param string $purpose The intended use and required level of detail.
     * @param string $context Descriptive context supplied by the placement.
     * @param string $language The requested output language.
     */
    public function __construct(
        int $contextid,
        /** @var int The user requesting the action. */
        protected int $userid,
        /** @var \stored_file The image to describe. */
        protected \stored_file $image,
        /** @var string The intended use and required level of detail. */
        protected string $purpose,
        /** @var string Descriptive context supplied by the placement. */
        protected string $context,
        /** @var string The requested output language. */
        protected string $language,
    ) {
        parent::__construct($contextid);

        if (!in_array($image->get_mimetype(), self::SUPPORTED_MIME_TYPES, true)) {
            throw new \core\exception\coding_exception('Unsupported image MIME type: ' . $image->get_mimetype());
        }
        $imageinfo = $image->get_imageinfo();
        if ($imageinfo === false) {
            throw new \core\exception\coding_exception('The supplied file is not a valid image.');
        }
        $this->imageinfo = $imageinfo;
    }

    /**
     * Build the provider-independent user instruction.
     *
     * @return string
     */
    public function get_prompt(): string {
        return get_string('action_describe_image_prompt', 'core_ai', [
            'purpose' => $this->purpose,
            'context' => $this->context,
            'language' => $this->language,
        ]);
    }

    /**
     * Validate deterministic provider image limits before loading the file into a request.
     *
     * @param int|null $maxfilesize Maximum image size in bytes, or null when the provider has no fixed limit.
     * @param int|null $maxdimension Maximum width or height in pixels, or null when the provider has no fixed limit.
     * @return array|true True when valid, otherwise standard AI error details.
     */
    public function validate_provider_limits(?int $maxfilesize = null, ?int $maxdimension = null): array|true {
        if ($maxfilesize !== null && $this->image->get_filesize() > $maxfilesize) {
            return \core_ai\error\factory::create(
                400,
                get_string('error:imagetoolarge', 'core_ai', display_size($maxfilesize)),
            )->get_error_details();
        }

        if (
            $maxdimension !== null
            && ($this->imageinfo['width'] > $maxdimension || $this->imageinfo['height'] > $maxdimension)
        ) {
            return \core_ai\error\factory::create(
                400,
                get_string('error:imagedimensionstoolarge', 'core_ai', $maxdimension),
            )->get_error_details();
        }

        return true;
    }

    #[\Override]
    public function store(response_base $response): int {
        global $DB;

        $responsearr = $response->get_response_data();
        return $DB->insert_record($this->get_tablename(), (object) [
            'filename' => $this->image->get_filename(),
            'filesize' => $this->image->get_filesize(),
            'mimetype' => $this->image->get_mimetype(),
            'width' => $this->imageinfo['width'],
            'height' => $this->imageinfo['height'],
            'purpose' => $this->purpose,
            'context' => $this->context,
            'language' => $this->language,
            'responseid' => $responsearr['id'],
            'fingerprint' => $responsearr['fingerprint'],
            'generatedcontent' => $responsearr['generatedcontent'],
            'finishreason' => $responsearr['finishreason'],
            'prompttokens' => $responsearr['prompttokens'],
            'completiontoken' => $responsearr['completiontokens'],
        ]);
    }
}
