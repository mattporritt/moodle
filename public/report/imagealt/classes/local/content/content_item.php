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

namespace report_imagealt\local\content;

use moodle_url;

/**
 * Describes one component-owned editable HTML field.
 *
 * Alternative text is deliberately associated with an occurrence in this content, rather than with the underlying file.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_item {
    /** @var string Provider-specific stable key. */
    public string $key;
    /** @var int Owning context ID. */
    public int $contextid;
    /** @var int|null Owning course ID. */
    public ?int $courseid;
    /** @var int|null Owning course category ID. */
    public ?int $categoryid;
    /** @var string Owning Moodle component. */
    public string $component;
    /** @var string Human-readable content type. */
    public string $contenttype;
    /** @var string Human-readable content item name. */
    public string $itemname;
    /** @var string Name of the HTML field. */
    public string $fieldname;
    /** @var string Raw stored HTML. */
    public string $html;
    /** @var moodle_url Safe destination for the component's full editor. */
    public moodle_url $editurl;
    /** @var int File context ID. */
    public int $filecontextid;
    /** @var string File component. */
    public string $filecomponent;
    /** @var string File area. */
    public string $filearea;
    /** @var int File item ID. */
    public int $fileitemid;
    /** @var string Format field name, when present. */
    public string $formatfield;

    /**
     * Create a content item.
     *
     * @param string $key Provider-specific stable key.
     * @param int $contextid Owning context ID.
     * @param int|null $courseid Owning course ID.
     * @param int|null $categoryid Owning course category ID.
     * @param string $component Owning Moodle component.
     * @param string $contenttype Human-readable content type.
     * @param string $itemname Human-readable content item name.
     * @param string $fieldname Name of the HTML field.
     * @param string $html Raw stored HTML.
     * @param moodle_url $editurl Safe destination for the component's full editor.
     * @param int $filecontextid File context ID.
     * @param string $filecomponent File component.
     * @param string $filearea File area.
     * @param int $fileitemid File item ID.
     * @param string $formatfield Format field name, when present.
     */
    public function __construct(
        string $key,
        int $contextid,
        ?int $courseid,
        ?int $categoryid,
        string $component,
        string $contenttype,
        string $itemname,
        string $fieldname,
        string $html,
        moodle_url $editurl,
        int $filecontextid,
        string $filecomponent,
        string $filearea,
        int $fileitemid,
        string $formatfield = '',
    ) {
        $this->key = $key;
        $this->contextid = $contextid;
        $this->courseid = $courseid;
        $this->categoryid = $categoryid;
        $this->component = $component;
        $this->contenttype = $contenttype;
        $this->itemname = $itemname;
        $this->fieldname = $fieldname;
        $this->html = $html;
        $this->editurl = $editurl;
        $this->filecontextid = $filecontextid;
        $this->filecomponent = $filecomponent;
        $this->filearea = $filearea;
        $this->fileitemid = $fileitemid;
        $this->formatfield = $formatfield;
    }

    /**
     * Hash used to reject stale writes.
     *
     * @return string
     */
    public function get_content_hash(): string {
        return hash('sha256', $this->html);
    }
}
