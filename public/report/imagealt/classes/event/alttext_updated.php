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

namespace report_imagealt\event;

/**
 * Triggered when the report writes alternative text onto one image occurrence.
 *
 * This report rewrites content that belongs to courses, activities and user profiles, and some of what it writes is
 * machine generated. Logging each write is what makes it possible to answer afterwards which alternative text a
 * person wrote and which came from an AI provider, which the content itself no longer records once it is saved.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *      Extra event information.
 *
 *      - string source: 'manual' when saved through the review form, 'accepted' when an unedited AI suggestion
 *        was applied from the bulk review table.
 *      - int|null suggestionid: The suggestion applied, for the 'accepted' source only.
 *      - bool decorative: Whether the image was marked decorative instead of being described.
 * }
 */
final class alttext_updated extends \core\event\base {
    /** @var string Alternative text typed or edited by a person in the review form. */
    public const SOURCE_MANUAL = 'manual';

    /** @var string An AI suggestion applied exactly as generated. */
    public const SOURCE_ACCEPTED = 'accepted';

    #[\Override]
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'report_imagealt_occurrence';
    }

    /**
     * Human readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:alttextupdated', 'report_imagealt');
    }

    #[\Override]
    public function get_description(): string {
        if ($this->other['source'] === self::SOURCE_ACCEPTED) {
            return "The user with id '{$this->userid}' applied the AI generated alternative text suggestion with " .
                "id '{$this->other['suggestionid']}' to the image occurrence with id '{$this->objectid}'.";
        }
        return "The user with id '{$this->userid}' updated the alternative text of the image occurrence with " .
            "id '{$this->objectid}'.";
    }

    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/report/imagealt/index.php', ['contextid' => $this->contextid]);
    }

    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();

        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' value must be set to the occurrence ID.');
        }
        if (!isset($this->other['source'])) {
            throw new \coding_exception('The \'source\' value must be set in other.');
        }
        if (!in_array($this->other['source'], [self::SOURCE_MANUAL, self::SOURCE_ACCEPTED], true)) {
            throw new \coding_exception('The \'source\' value must be a recognised alternative text source.');
        }
        if ($this->other['source'] === self::SOURCE_ACCEPTED && empty($this->other['suggestionid'])) {
            throw new \coding_exception('The \'suggestionid\' value must be set for an accepted suggestion.');
        }
    }

    /**
     * Map the object ID for backup and restore.
     *
     * @return array
     */
    #[\Override]
    public static function get_objectid_mapping(): array {
        // Occurrences are an index of content that is rebuilt by scanning, so they are not restored alongside it.
        return ['db' => 'report_imagealt_occurrence', 'restore' => self::NOT_MAPPED];
    }

    /**
     * Map the other IDs for backup and restore.
     *
     * @return array|false
     */
    #[\Override]
    public static function get_other_mapping() {
        // The suggestion is a transient artifact of one user's review session, so it is not restored either.
        return false;
    }
}
