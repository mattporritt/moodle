<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace report_imagealt\local;

use core_ai\aiactions\describe_image;

/**
 * Fetches an image the content points at but this site does not store, so it can be described.
 *
 * The AI action takes a stored_file, which an image referenced by URL does not have. Rather than refuse to describe
 * images an author can plainly see on the page, a copy is fetched into a scratch file area for the length of one
 * request and handed to the action from there.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class remote_image {
    /** @var string File area holding fetched copies, cleared as soon as the description is written. */
    public const FILE_AREA = 'remoteimage';

    /** @var int Refuse anything larger, before it is written anywhere. Well above any sane page image. */
    private const MAX_BYTES = 12582912;

    /** @var int Seconds to wait on a URL before giving up, so one slow host cannot hold a task open. */
    private const TIMEOUT = 15;

    /**
     * Fetch the image at a URL into a stored file that the AI action can read.
     *
     * @param string $url Absolute image URL taken from the content.
     * @param \context $context Context the fetched copy is stored against.
     * @param int $occurrenceid Occurrence the copy belongs to, used as the file item ID.
     * @return \stored_file
     * @throws \moodle_exception When the URL cannot be fetched, is too large, or is not a supported image.
     */
    public function fetch(string $url, \context $context, int $occurrenceid): \stored_file {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \moodle_exception('error:remotefetchfailed', 'report_imagealt', '', $scheme ?: '');
        }

        $this->delete_for($context, $occurrenceid);
        $temporary = make_request_directory() . '/' . self::FILE_AREA;

        // Default security, deliberately: core's curl_security_helper checks the URL and every redirect it follows
        // against the site's blocked host and port lists, which is what stops content being used to make this server
        // fetch things inside its own network.
        $curl = new \curl();
        $error = $curl->download_one($url, null, [
            'filepath' => $temporary,
            'timeout' => self::TIMEOUT,
            'followlocation' => true,
            'maxredirs' => 3,
        ]);
        $info = $curl->get_info();
        if ($error !== true || (int) ($info['http_code'] ?? 0) !== 200) {
            throw new \moodle_exception(
                'error:remotefetchfailed',
                'report_imagealt',
                '',
                is_string($error) ? $error : (string) ($info['http_code'] ?? ''),
            );
        }
        if (!file_exists($temporary) || filesize($temporary) === 0) {
            throw new \moodle_exception('error:remotefetchfailed', 'report_imagealt', '', '0');
        }
        if (filesize($temporary) > self::MAX_BYTES) {
            throw new \moodle_exception(
                'error:remotetoolarge',
                'report_imagealt',
                '',
                display_size(self::MAX_BYTES),
            );
        }

        // What the bytes actually are, not what the server said they were: the action rejects an unsupported type
        // with a coding exception, which would surface to the user as a crash rather than as a reason.
        $mimetype = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($temporary);
        if (!in_array($mimetype, describe_image::SUPPORTED_MIME_TYPES, true)) {
            throw new \moodle_exception('error:remotenotimage', 'report_imagealt', '', $mimetype ?: 'unknown');
        }

        $filename = clean_param(basename((string) parse_url($url, PHP_URL_PATH)), PARAM_FILE);
        return get_file_storage()->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'report_imagealt',
            'filearea' => self::FILE_AREA,
            'itemid' => $occurrenceid,
            'filepath' => '/',
            'filename' => $filename === '' ? 'remote-image' : $filename,
        ], $temporary);
    }

    /**
     * Remove any fetched copy held for one occurrence.
     *
     * Called before fetching and again once the description is written, so a copy of somebody else's image is kept
     * only for as long as it takes to describe it.
     *
     * @param \context $context Context the copy was stored against.
     * @param int $occurrenceid Occurrence the copy belongs to.
     */
    public function delete_for(\context $context, int $occurrenceid): void {
        get_file_storage()->delete_area_files(
            $context->id,
            'report_imagealt',
            self::FILE_AREA,
            $occurrenceid,
        );
    }
}
