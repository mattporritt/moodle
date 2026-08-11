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

namespace report_imagealt\reportbuilder\local\systemreports;

use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\system_report;
use core\output\help_icon;
use lang_string;
use report_imagealt\local\batch_manager;
use report_imagealt\local\manager;
use report_imagealt\local\suggestion_service;
use report_imagealt\reportbuilder\local\entities\occurrence;

/**
 * Fixed system report for editable image occurrences.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class image_alt_text extends system_report {
    #[\Override]
    protected function initialise(): void {
        global $PAGE, $USER;

        // Report Builder rebuilds this report inside the dynamic table web service as well as on the page: every
        // sort, page, filter change and post-save refresh goes through there, and that request sets up no page
        // context. Asking core_ai whether a provider can serve an action constructs that provider's action settings
        // form, which needs one, so without this every one of those reloads fails with a coding error while the
        // first paint succeeds, because the page itself had set a context. Set to this report's own context, which
        // is what any page rendering it has already set, so nothing is switched.
        $PAGE->set_context($this->get_context());

        $entity = new occurrence();
        $alias = $entity->get_table_alias('report_imagealt_occurrence');
        $this->set_main_table('report_imagealt_occurrence', $alias);
        $this->add_entity($entity
            ->add_join("LEFT JOIN {course} course ON course.id = {$alias}.courseid")
            ->add_join("LEFT JOIN {course_categories} category ON category.id = {$alias}.categoryid")
            ->add_join("LEFT JOIN {report_imagealt_suggestion} suggestion
                              ON suggestion.occurrenceid = {$alias}.id
                             AND suggestion.userid = {$USER->id}
                             AND suggestion.id = (SELECT MAX(latest.id)
                                                    FROM {report_imagealt_suggestion} latest
                                                   WHERE latest.occurrenceid = {$alias}.id
                                                     AND latest.userid = {$USER->id})"));

        // The suggestion joined above is already this user's latest one for the image, so its status is all the
        // selection rule below needs. Aliased because the occurrence table has a status column of its own.
        $this->add_base_fields("{$alias}.id, {$alias}.providerkey, {$alias}.itemkey, {$alias}.aieligible,
            {$alias}.analysisstate, {$alias}.status, {$alias}.filename, {$alias}.src,
            suggestion.status AS latestsuggestionstatus");
        [$scopesql, $scopeparams] = manager::get_occurrence_scope_condition($this->get_context(), $alias);
        $parammap = [];
        [$scopesql, $scopeparams] = database::sql_replace_parameters(
            $scopesql,
            $scopeparams,
            static function (string $name) use (&$parammap): string {
                return $parammap[$name] ??= database::generate_param_name($name);
            },
        );
        $this->add_base_condition_sql($scopesql, $scopeparams);

        $contentmanager = new manager();
        // Selection exists only to send images for AI description, so without a provider to serve it the column is
        // left off entirely rather than offering rows to select for an action the site cannot perform. Report Builder
        // renders its select-all header whenever the callback is registered, however each row answers it.
        if (suggestion_service::is_available($this->get_context())) {
            $this->set_checkbox_toggleall(static function (\stdClass $row) use ($contentmanager, $USER): ?array {
                if (
                    !$row->aieligible || $row->analysisstate !== 'ready'
                        || !$contentmanager->can_edit_occurrence($row, (int) $USER->id)
                ) {
                    return null;
                }
                // A decorative image is meant to have no alternative text, so describing it is the one thing that
                // cannot be what the user wanted: applying the result would undo the decision to hide the image from
                // screen readers. Images whose text is merely already good stay selectable, because rewriting those
                // is a legitimate thing to ask for.
                if ($row->status === 'decorative') {
                    return null;
                }
                // An image whose description is already generating or waiting to be reviewed offers nothing to
                // generate: asking again would pay for a second description of the same image and leave two
                // suggestions competing for it. The suggestion state column says where that work is, and links to it.
                if (in_array($row->latestsuggestionstatus ?? null, batch_manager::OUTSTANDING_STATUSES, true)) {
                    return null;
                }
                // Named after the image rather than labelled "Select" like every other row, which gave a screen
                // reader user a column of identically named checkboxes with no way to tell which image each one
                // belonged to. Matches the labels on the batch review table.
                return [$row->id, get_string('selectitem', 'moodle', $row->filename ?: $row->src)];
            });
        }

        // Kept deliberately short so the report is scannable. The remaining occurrence fields (decorative, content type,
        // AI eligibility, analysis state, suggestion state) stay available as filters below without cluttering every row.
        $this->add_columns_from_entities([
            'occurrence:preview',
            'occurrence:source',
            'occurrence:alttext',
            'occurrence:status',
            'occurrence:reason',
            // Shown by default because bulk generation is otherwise a dead end: the batch it creates cannot be
            // reached again from here, and this column's badge links each image to the batch its suggestion is in.
            'occurrence:suggestionstatus',
            'occurrence:course',
            'occurrence:category',
            'occurrence:itemname',
            'occurrence:timeanalysed',
        ]);
        $this->add_filters_from_entities([
            'occurrence:course',
            'occurrence:category',
            'occurrence:contenttype',
            'occurrence:status',
            'occurrence:reason',
            'occurrence:decorative',
            'occurrence:aieligible',
            'occurrence:analysisstate',
            'occurrence:suggestionstatus',
            'occurrence:timeanalysed',
        ]);
        $this->set_initial_sort_column('occurrence:timeanalysed', SORT_DESC);
        $this->apply_default_status_filter();

        // Report Builder's own "Nothing to display" is the wrong thing to say to somebody whose content is fine.
        // Because the report arrives filtered to the images that need attention, an empty table is usually the good
        // outcome, and a bare "nothing" beside a filter count the user did not set themselves reads as a fault. Which
        // of the two messages applies depends on whether there is anything here at all to have filtered.
        $this->set_default_no_results_notice(new lang_string(
            $this->has_occurrences_in_scope() ? 'nomatchingimages' : 'noimages',
            'report_imagealt',
        ));

        // An icon button reads more clearly than Report Builder's default kebab action menu, which is unnecessary
        // overhead for one available action, and matches the row actions on the bulk review table. It launches a
        // modal (report_imagealt/report AMD module) rather than navigating to a separate page, so there is no href
        // here, which is also why Report Builder's own action API is not used: that requires a URL.
        $reviewcontextid = $this->get_context()->id;
        $this->add_column((new column(
            'actions',
            new lang_string('actions'),
            $entity->get_entity_name(),
        ))
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$alias}.id")
            ->add_field("{$alias}.providerkey")
            ->add_field("{$alias}.itemkey")
            ->add_field("{$alias}.analysisstate")
            ->add_field("{$alias}.status")
            ->add_callback(static function ($id, \stdClass $row) use ($contentmanager, $USER, $reviewcontextid): string {
                if ($row->analysisstate !== 'ready' || !$contentmanager->can_edit_occurrence($row, (int) $USER->id)) {
                    return '';
                }
                // A broken image is not offered the alternative text editor, because there is no image to describe
                // and writing a description would only put words to something nobody can see. What it is offered is
                // the way to the content, which is where the actual fault is and the only place it can be repaired.
                if ($row->status === 'broken') {
                    return \report_imagealt\output\actions::fix_broken_link($row, $contentmanager);
                }
                return \report_imagealt\output\actions::edit_button((int) $id, $reviewcontextid);
            }));

        $this->compensate_help_icon_offset();
        $this->set_downloadable(true, get_string('pluginname', 'report_imagealt'));
    }

    /**
     * Arrive filtered to the images that need attention, the first time this user opens the report.
     *
     * The report holds every image it has indexed, including the ones whose alternative text is already fine. Those
     * are in it so they can be found deliberately, not so they can be scrolled past: on a site whose content is
     * mostly compliant they are the bulk of the table, and the work is a minority of rows with nothing marking it
     * out. So the first view is narrowed to the two statuses that represent work.
     *
     * Written as this user's own filter preference rather than enforced as a report condition, so it appears in the
     * filter form as an ordinary selection they can widen, narrow, or clear to see everything. Applied only when
     * they have no filter preference for this report at all, so it never overrides or re-imposes itself on a choice
     * they have made.
     */
    private function apply_default_status_filter(): void {
        if ($this->get_filter_values() !== []) {
            return;
        }
        // The same status values the filter offers as options, which classifier::classify() produces. Broken images
        // are included because they are work too, and the most urgent kind: nothing on the page is showing at all.
        $this->set_filter_values(['occurrence:status_values' => ['missing', 'potentiallypoor', 'broken']]);
    }

    /**
     * Whether this report's context contains any indexed image at all, whatever its status.
     *
     * Distinguishes "nothing here needs attention" from "nothing here has been analysed yet", which the empty table
     * looks identical for.
     *
     * @return bool
     */
    private function has_occurrences_in_scope(): bool {
        global $DB;

        [$scopesql, $scopeparams] = manager::get_occurrence_scope_condition($this->get_context());
        return $DB->record_exists_select('report_imagealt_occurrence', $scopesql, $scopeparams);
    }

    /**
     * Work around a Report Builder core bug that misaligns column help icons by one header position.
     *
     * system_report_table::query_db() builds its header list with an entry for the select-all checkbox column
     * ahead of the real columns, but builds its parallel help-icon list starting from the first real column, with
     * no placeholder for that checkbox header. flexible_table then pairs help icons to headers by array position,
     * so every help icon renders one header to the right of the column it actually describes. This report cannot
     * fix that in core, so each column here is given the help icon that belongs to the PREVIOUS column in display
     * order, which core's bug then displays one position later, in the correct place. The first column (image
     * preview) is deliberately left without one: whatever help icon that column carries would incorrectly render
     * on the select-all checkbox header instead.
     */
    private function compensate_help_icon_offset(): void {
        $displayorder = [
            'preview' => new help_icon('imagepreview', 'report_imagealt'),
            'source' => new help_icon('imagesource', 'report_imagealt'),
            'alttext' => new help_icon('alttext', 'report_imagealt'),
            'status' => new help_icon('classification', 'report_imagealt'),
            'reason' => new help_icon('reason', 'report_imagealt'),
            'suggestionstatus' => new help_icon('suggestionstatus', 'report_imagealt'),
            'course' => null,
            'category' => null,
            'itemname' => new help_icon('contentitem', 'report_imagealt'),
            'timeanalysed' => new help_icon('timeanalysed', 'report_imagealt'),
            'actions' => null,
        ];

        $previoushelpicon = null;
        foreach ($displayorder as $columnname => $helpicon) {
            if ($previoushelpicon !== null) {
                $this->get_column("occurrence:{$columnname}")->set_help_icon($previoushelpicon);
            }
            $previoushelpicon = $helpicon;
        }
    }

    #[\Override]
    public function get_exclude_columns_for_download(): array {
        return ['occurrence:actions'];
    }

    #[\Override]
    protected function can_view(): bool {
        return has_capability('report/imagealt:view', $this->get_context());
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('pluginname', 'report_imagealt');
    }
}
