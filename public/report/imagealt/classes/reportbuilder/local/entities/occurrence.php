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

namespace report_imagealt\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\autocomplete;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\category as category_filter;
use core_reportbuilder\local\filters\course_selector;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use moodle_url;

/**
 * Report Builder entity for indexed image occurrences.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class occurrence extends base {
    #[\Override]
    protected function get_default_tables(): array {
        return ['report_imagealt_occurrence'];
    }

    #[\Override]
    protected function get_default_entity_title(): lang_string {
        return new lang_string('pluginname', 'report_imagealt');
    }

    #[\Override]
    protected function get_available_columns(): array {
        $alias = $this->get_table_alias('report_imagealt_occurrence');
        $columns = [];

        $columns[] = (new column('preview', new lang_string('imagepreview', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.id", 'previewoccurrenceid')
            ->add_field("{$alias}.previewhash")
            ->add_field("{$alias}.filename")
            ->add_field("{$alias}.status")
            ->add_field("{$alias}.src")
            ->add_callback(static fn(?string $id, \stdClass $row): string => \report_imagealt\output\preview::thumbnail(
                (int) $id,
                $row->previewhash,
                (string) $row->status,
                $row->src,
            ));

        $columns[] = (new column('source', new lang_string('imagesource', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.filename")
            ->add_field("{$alias}.src")
            ->add_field("{$alias}.id", 'sourceoccurrenceid')
            ->add_field("{$alias}.previewhash")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $filename, \stdClass $row): string => \report_imagealt\output\preview::source_link(
                (int) $row->sourceoccurrenceid,
                $row->previewhash,
                (string) ($filename ?: $row->src),
                $row->src,
            ));

        $columns[] = (new column('alttext', new lang_string('currentalttext', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_LONGTEXT)
            ->add_field("{$alias}.alttext")
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value): string => $value === null || trim($value) === ''
                ? get_string('unknownvalue', 'core_ai') : $value);

        $columns[] = (new column('status', new lang_string('classification', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.status")
            ->set_is_sortable(true)
            ->add_callback(static fn(string $value): string => get_string("status_{$value}", 'report_imagealt'));

        $columns[] = (new column('reason', new lang_string('reason', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.reason")
            ->set_is_sortable(true)
            ->add_callback(static fn(string $value): string => get_string("reason_{$value}", 'report_imagealt'));

        $columns[] = (new column('decorative', new lang_string('decorative', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$alias}.decorative")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column('course', new lang_string('course'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field('course.fullname')
            ->add_field('course.id', 'linkedcourseid')
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value, \stdClass $row): string => $value
                ? \html_writer::link(new moodle_url('/course/view.php', ['id' => $row->linkedcourseid]), s($value))
                : '-');

        $columns[] = (new column('category', new lang_string('category'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field('category.name')
            ->add_field('category.id', 'linkedcategoryid')
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value, \stdClass $row): string => $value
                ? \html_writer::link(new moodle_url('/course/index.php', ['categoryid' => $row->linkedcategoryid]), s($value))
                : '-');

        $columns[] = (new column('contenttype', new lang_string('contenttype', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.contenttype")
            ->set_is_sortable(true);

        $columns[] = (new column('itemname', new lang_string('contentitem', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.itemname")
            ->set_is_sortable(true);

        $columns[] = (new column('fieldname', new lang_string('fieldname', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.fieldname")
            ->set_is_sortable(true);

        $columns[] = (new column('aieligible', new lang_string('aieligible', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_BOOLEAN)
            ->add_field("{$alias}.aieligible")
            ->set_is_sortable(true)
            ->set_callback([format::class, 'boolean_as_text']);

        $columns[] = (new column('analysisstate', new lang_string('analysisstate', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.analysisstate")
            ->set_is_sortable(true)
            ->add_callback(static fn(string $value): string => get_string("analysisstate_{$value}", 'report_imagealt'));

        $columns[] = (new column(
            'suggestionstatus',
            new lang_string('suggestionstatus', 'report_imagealt'),
            $this->get_entity_name(),
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field('suggestion.status')
            ->add_field('suggestion.batchid')
            ->set_is_sortable(true)
            ->add_callback(static fn(?string $value, \stdClass $row): string => \report_imagealt\output\suggestion_state::badge(
                $value,
                $row->batchid === null ? null : (int) $row->batchid,
            ));

        $columns[] = (new column('timeanalysed', new lang_string('timeanalysed', 'report_imagealt'), $this->get_entity_name()))
            ->set_type(column::TYPE_TIMESTAMP)
            ->add_field("{$alias}.timeanalysed")
            ->set_is_sortable(true)
            ->add_callback([format::class, 'userdate']);

        return $columns;
    }

    #[\Override]
    protected function get_available_filters(): array {
        $alias = $this->get_table_alias('report_imagealt_occurrence');
        $filters = [];

        $filters[] = (new filter(
            course_selector::class,
            'course',
            new lang_string('course'),
            $this->get_entity_name(),
            "{$alias}.courseid"
        ));
        $filters[] = (new filter(
            category_filter::class,
            'category',
            new lang_string('category'),
            $this->get_entity_name(),
            "{$alias}.categoryid"
        ))->set_options(['requiredcapabilities' => 'report/imagealt:view']);
        $filters[] = new filter(
            text::class,
            'contenttype',
            new lang_string('contenttype', 'report_imagealt'),
            $this->get_entity_name(),
            "{$alias}.contenttype"
        );
        // Multi-valued, unlike the other status-like filters here, because the question this one answers is almost
        // always "which images need attention": missing and potentially poor together, which a single-choice filter
        // cannot express. It is also the filter the report arrives with a default for (see the system report).
        $filters[] = (new filter(
            autocomplete::class,
            'status',
            new lang_string('classification', 'report_imagealt'),
            $this->get_entity_name(),
            "{$alias}.status"
        ))->set_options(self::get_string_options('status', [
                'missing', 'potentiallypoor', 'broken', 'decorative', 'present',
            ]));
        $filters[] = (new filter(
            select::class,
            'reason',
            new lang_string('reason', 'report_imagealt'),
            $this->get_entity_name(),
            "{$alias}.reason"
        ))->set_options(self::get_string_options('reason', [
                'missing', 'filename', 'placeholder', 'linkedimage', 'broken', 'none',
            ]));
        $filters[] = new filter(
            boolean_select::class,
            'decorative',
            new lang_string('decorative', 'report_imagealt'),
            $this->get_entity_name(),
            "{$alias}.decorative"
        );
        $filters[] = new filter(
            boolean_select::class,
            'aieligible',
            new lang_string('aieligible', 'report_imagealt'),
            $this->get_entity_name(),
            "{$alias}.aieligible"
        );
        $filters[] = (new filter(
            select::class,
            'analysisstate',
            new lang_string('analysisstate', 'report_imagealt'),
            $this->get_entity_name(),
            "{$alias}.analysisstate"
        ))->set_options(self::get_string_options('analysisstate', [
                'ready', 'scanning', 'stale',
            ]));
        $filters[] = (new filter(
            select::class,
            'suggestionstatus',
            new lang_string('suggestionstatus', 'report_imagealt'),
            $this->get_entity_name(),
            'suggestion.status'
        ))->set_options(self::get_string_options('suggestionstatus', [
                'queued', 'processing', 'ready', 'failed', 'cancelled', 'stale', 'accepted', 'discarded',
            ]));
        $filters[] = new filter(
            date::class,
            'timeanalysed',
            new lang_string('timeanalysed', 'report_imagealt'),
            $this->get_entity_name(),
            "{$alias}.timeanalysed"
        );

        return $filters;
    }

    /**
     * Build translated select options.
     *
     * @param string $prefix Language string prefix.
     * @param string[] $values Values.
     * @return array<string, lang_string>
     */
    private static function get_string_options(string $prefix, array $values): array {
        return array_combine($values, array_map(
            static fn(string $value): lang_string => new lang_string("{$prefix}_{$value}", 'report_imagealt'),
            $values,
        ));
    }
}
