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

namespace report_imagealt\form;

use context;
use core_form\dynamic_form;
use moodle_exception;
use report_imagealt\local\classifier;
use report_imagealt\local\image_parser;
use report_imagealt\local\manager;
use report_imagealt\local\suggestion_service;

/**
 * Modal form to review and remediate one exact image occurrence.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review extends dynamic_form {
    /** @var array{0: \stdClass, 1: \report_imagealt\local\content\provider, 2: \report_imagealt\local\content\content_item}|null */
    private ?array $occurrencedata = null;

    /** @var \stdClass|false|null Cached latest suggestion; false means "looked up, none found". */
    private $suggestion = null;

    /** @var bool|null Cached AI availability; resolving it builds a provider settings form, so it is asked once. */
    private ?bool $canuseai = null;

    #[\Override]
    protected function definition(): void {
        global $OUTPUT, $USER;

        $mform = $this->_form;
        [$occurrence, , $item] = $this->get_occurrence_data();
        $suggestion = $this->get_suggestion();
        $surroundingtext = $this->get_surrounding_text();

        // A suggestion waiting to be reviewed is what the field opens with, so it is what the character count has to
        // describe. Counting the image's current alternative text instead left the count contradicting the text
        // being counted until the first keystroke corrected it.
        $initialalttext = (string) ($suggestion && $suggestion->status === 'ready'
            ? $suggestion->suggestion
            : $occurrence->alttext);

        // Stack each field's label above its control (full width) instead of quickform's default label/field
        // columns, matching Tiny's own image details dialog and avoiding the side-by-side layout that a narrow
        // modal has no room for.
        $this->set_display_vertical();

        // Laid out like Moodle's own "insert/edit image" dialog in the rich text editor: a container wrapping the
        // row (Tiny's own dialog wraps its row the same way), the editable alternative text and its controls on
        // the left in a narrower column, the image itself large on the right, rather than stacking the image above
        // a full-width form.
        $mform->addElement('html', \html_writer::start_div('container'));
        $mform->addElement('html', \html_writer::start_div('row'));

        $mform->addElement('html', \html_writer::start_div('col-md-5'));

        // Laid out like Tiny's own image details dialog: a heading, then the describing question as the field's
        // actual accessible label (the question is what a screen reader announces, matching Tiny's own markup).
        $mform->addElement('html', \html_writer::tag('h6', get_string('alttext', 'report_imagealt'), ['class' => 'h6 fw-bold']));
        $mform->addElement('textarea', 'alttext', get_string('alttextdescribe', 'report_imagealt'), [
            'rows' => 5,
            'maxlength' => classifier::MAX_ALT_LENGTH,
            'data-region' => 'report-imagealt-alttext',
        ]);
        $mform->setType('alttext', PARAM_TEXT);
        $mform->addRule(
            'alttext',
            get_string('maximumchars', '', classifier::MAX_ALT_LENGTH),
            'maxlength',
            classifier::MAX_ALT_LENGTH,
            'client'
        );
        // Mirrors Tiny's own "the-count" markup exactly: plain digits either side of a literal slash, right
        // aligned, with a visually-hidden status region announcing the limit once it is reached.
        $mform->addElement('html', \html_writer::start_div(
            'd-flex justify-content-end small',
            ['data-region' => 'report-imagealt-count'],
        ));
        $mform->addElement('html', \html_writer::span(
            (string) \core_text::strlen($initialalttext),
            '',
            ['data-region' => 'report-imagealt-currentcount'],
        ));
        $mform->addElement('html', \html_writer::span('/', 'mx-1'));
        $mform->addElement('html', \html_writer::span((string) classifier::MAX_ALT_LENGTH));
        $mform->addElement('html', \html_writer::span(
            '',
            'visually-hidden',
            ['data-region' => 'report-imagealt-maxlength-feedback', 'role' => 'status'],
        ));
        $mform->addElement('html', \html_writer::end_div());

        // Every state of the AI workflow (the generate button, the overwrite confirmation, generating, ready, and
        // failed) renders into this one region, directly below the character count and above the decorative
        // checkbox, exactly where Tiny's own image details dialog puts it. The server renders whichever state the
        // stored suggestion is already in; report_imagealt/suggestion.js re-renders the same template in place from
        // there, so the markup never differs between the initial and the interactive states.
        // Whether a provider can serve a request is carried on the region rather than left for the JS to infer from
        // the state it finds there: every state it renders itself has to make the same decision the server already
        // made, and the one state a site with no provider can start in (a description generated while there still
        // was one, waiting to be reviewed) looks identical to one it could offer to write again.
        $mform->addElement('html', \html_writer::div(
            $this->render_suggestion_state(),
            '',
            [
                'data-region' => 'report-imagealt-suggestion',
                'data-canregenerate' => $this->can_use_ai() ? '1' : '0',
                // What the field held before any description was written into it, so rejecting one can put it back.
                // The field opens showing a description that is waiting to be reviewed, not the image's own text, so
                // without this the client has nothing to restore when that description is discarded.
                'data-storedalttext' => (string) $occurrence->alttext,
            ],
        ));

        // The outer form label is left empty and the checkbox's own text passed as $text instead, so the checkbox,
        // its text, and its help icon sit on one line rather than in a separate label-then-field row. Moodle's
        // quickform renderer still reserves a (visually empty) label column even with an empty label, so the whole
        // row is additionally wrapped to collapse that column via CSS (report-imagealt-decorative-row, see
        // styles.css) and sit flush left, matching Tiny's own checkbox row exactly.
        $mform->addElement('html', \html_writer::start_div('report-imagealt-decorative-row mt-2'));
        $mform->addElement('advcheckbox', 'decorative', '', get_string('markdecorative', 'report_imagealt'));
        $mform->setType('decorative', PARAM_BOOL);
        $mform->addHelpButton('decorative', 'markdecorative', 'report_imagealt');
        $mform->addElement('html', \html_writer::end_div());
        $mform->hideIf('alttext', 'decorative', 'checked');

        $mform->addElement('hidden', 'id', $occurrence->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid', $this->get_return_context()->id);
        $mform->setType('contextid', PARAM_INT);

        $mform->addElement('html', \html_writer::end_div()); // End the editable-fields column.

        $mform->addElement('html', \html_writer::start_div('col-md-7 mt-3 mt-md-0'));
        $previewurl = \report_imagealt\output\preview::url(
            (int) $occurrence->id,
            $occurrence->previewhash,
            $occurrence->src,
        );
        if ($previewurl) {
            // The image sits centred in a panel that fills the column, rather than as a thumbnail pinned to the top
            // corner. This is how Tiny's own image details dialog presents the image being described
            // (tiny_image_preview_box, see lib/editor/tiny/plugins/media), and someone writing a description is
            // doing the same job in both places: looking at the picture. Its delete control has no counterpart
            // here, because this dialog changes the description and never the image.
            //
            // The sizing deliberately does not match that dialog, so please do not "align" it further without
            // reading this. Tiny sizes its preview from its own width and height fields (see setImageDimensions()
            // in image/imagedetails.js), showing the image at the size it occupies in the content. That is right
            // there, where the point is to choose how big the image will be. Here the point is to describe what the
            // image shows, so it is the file itself that is shown, at its own size and bounded by the panel.
            // Following the content's dimensions instead would upscale a small image into a blurrier version of
            // itself, and shrink a large one to whatever the content happened to set, which is the case where a
            // clear view matters most.
            $mform->addElement('html', \html_writer::div(
                \html_writer::empty_tag('img', [
                    'src' => $previewurl->out(false),
                    'alt' => '',
                    'role' => 'presentation',
                    'class' => 'report-imagealt-preview',
                    'loading' => 'lazy',
                ]),
                'report-imagealt-preview-box border rounded d-flex align-items-center justify-content-center h-100',
            ));
        }
        $mform->addElement('html', \html_writer::end_div()); // End the image column.

        $mform->addElement('html', \html_writer::end_div()); // End the alttext/image row.

        // A second, full-width row below the alttext/image row balances the layout and gives the image's
        // surrounding context (which content item and field it lives in, and how to edit it directly) room for
        // labels explaining what each value means, rather than competing for space in the narrower image column.
        $mform->addElement('html', \html_writer::start_div('row mt-3 border-top pt-3'));
        $mform->addElement('html', \html_writer::start_div('col-sm-4'));
        $mform->addElement('html', \html_writer::div(get_string('contentitem', 'report_imagealt'), 'text-muted small'));
        $mform->addElement('html', \html_writer::div(s($item->itemname)));
        $mform->addElement('html', \html_writer::end_div());
        $mform->addElement('html', \html_writer::start_div('col-sm-4'));
        $mform->addElement('html', \html_writer::div(get_string('contenttype', 'report_imagealt'), 'text-muted small'));
        $mform->addElement('html', \html_writer::div(s($item->contenttype)));
        $mform->addElement('html', \html_writer::end_div());
        if ($surroundingtext !== '') {
            $mform->addElement('html', \html_writer::start_div('col-sm-4'));
            $mform->addElement('html', \html_writer::div(
                get_string('surroundingcontent', 'report_imagealt'),
                'text-muted small',
            ));
            $mform->addElement('html', \html_writer::div(s($surroundingtext)));
            $mform->addElement('html', \html_writer::end_div());
        }
        $mform->addElement('html', \html_writer::end_div()); // End the metadata row.

        $mform->addElement('html', \html_writer::div(
            \html_writer::link(
                $item->editurl,
                $OUTPUT->pix_icon('i/export', '', 'moodle', ['class' => 'me-1'])
                    . get_string('editdestination', 'report_imagealt')
                    . \html_writer::span(get_string('opensinnewwindowbracketed'), 'visually-hidden'),
                ['target' => '_blank', 'rel' => 'noopener'],
            ),
            'mt-2',
        ));

        $mform->addElement('html', \html_writer::end_div()); // End the container.

        $this->set_data((object) [
            'alttext' => $initialalttext,
            'decorative' => $occurrence->decorative,
        ]);
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['decorative']) && trim($data['alttext'] ?? '') === '') {
            $errors['alttext'] = get_string('required');
        }
        return $errors;
    }

    #[\Override]
    protected function get_context_for_dynamic_submission(): context {
        [, , $item] = $this->get_occurrence_data();
        return context::instance_by_id($item->contextid);
    }

    #[\Override]
    protected function check_access_for_dynamic_submission(): void {
        global $USER;

        [, $provider, $item] = $this->get_occurrence_data();
        require_capability('report/imagealt:view', context::instance_by_id($item->contextid));
        if (!$provider->can_edit($item, (int) $USER->id)) {
            throw new moodle_exception('cannotedit', 'report_imagealt');
        }
        // Validates that the return context is in scope, throwing if not.
        $this->get_return_context();
    }

    #[\Override]
    public function process_dynamic_submission() {
        global $DB, $USER;

        $data = $this->get_data();
        $manager = new manager();
        // Resolved before the write, because saving rescans the content and can change the suggestion's own state.
        // Text saved exactly as the provider generated it is the same thing as accepting a suggestion from the bulk
        // review table, so the logged event says so. Text the user changed is their own work, even where an AI
        // suggestion gave them the starting point, and is recorded as manual.
        $suggestion = $this->get_suggestion();
        $applied = $suggestion && $suggestion->status === 'ready' && $suggestion->suggestion === $data->alttext
            ? (int) $suggestion->id
            : null;
        $manager->update_occurrence(
            (int) $data->id,
            $data->alttext,
            !empty($data->decorative),
            (int) $USER->id,
            $applied,
        );

        [$occurrence] = $manager->get_current_occurrence((int) $data->id);
        // Saving settles the description one way or the other, so it does not stay waiting to be reviewed. Which way
        // was decided above, before the write: the same text means it was applied, anything else means the reviewer
        // wrote their own and this one never reached the image.
        //
        // Set from that decision rather than by comparing hashes afterwards. Saving rewrites the content, so the
        // occurrence's hash always differs from the one recorded when the description was written, and the
        // comparison this replaces could never be true: applying a description through this dialog left it reported
        // as out of date. Written after update_occurrence() so it also settles the "stale" the rescan inside that
        // call marks it with, having seen the image's own tag change.
        if ($suggestion && $suggestion->status === 'ready') {
            $DB->set_field(
                'report_imagealt_suggestion',
                'status',
                $applied === null ? 'discarded' : 'accepted',
                ['id' => $suggestion->id],
            );
        }

        return ['status' => $occurrence->status];
    }

    #[\Override]
    public function set_data_for_dynamic_submission(): void {
        // Defaults are populated at the end of definition(), once the occurrence and suggestion are already resolved
        // in the same place they are rendered, rather than duplicating those lookups here.
    }

    #[\Override]
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/report/imagealt/index.php', ['contextid' => $this->get_return_context()->id]);
    }

    /**
     * Render the AI suggestion region in whichever state this occurrence's stored suggestion is currently in.
     *
     * @return string The rendered region, or an empty string when this occurrence cannot use AI at all.
     */
    private function render_suggestion_state(): string {
        global $OUTPUT;

        [$occurrence, , $item] = $this->get_occurrence_data();
        if (!$occurrence->aieligible) {
            return '';
        }

        $suggestion = $this->get_suggestion();
        // With no provider to ask, every control in this region either fails or waits for something that will never
        // happen, so none of it is offered. A description already written is the exception: the text exists and
        // applying it needs no provider, so it is still shown for review, without the offer to write another.
        if (!$this->can_use_ai()) {
            if (!$suggestion || $suggestion->status !== 'ready') {
                return '';
            }
            return $OUTPUT->render_from_template('report_imagealt/suggestion', [
                'occurrenceid' => $occurrence->id,
                'success' => true,
                'suggestionid' => $suggestion->id,
            ]);
        }

        $context = ['occurrenceid' => $occurrence->id, 'canregenerate' => true];
        if ($suggestion && $suggestion->status === 'ready') {
            $context['success'] = true;
            $context['suggestionid'] = $suggestion->id;
        } else if ($suggestion && $suggestion->status === 'failed') {
            $context['error'] = true;
            $context['errormessage'] = $suggestion->errormessage;
        } else if ($suggestion && in_array($suggestion->status, ['queued', 'processing'], true)) {
            // The short suggestionstatus_* labels are for table cells; this alert needs a full sentence so it makes
            // sense as a standalone message, not just a bare status word.
            $context['waiting'] = true;
            $context['waitingmessage'] = get_string("suggestionwaiting_{$suggestion->status}", 'report_imagealt');
        } else {
            $context['idle'] = true;
            $context['hasalt'] = trim((string) $occurrence->alttext) !== '';
        }

        return $OUTPUT->render_from_template('report_imagealt/suggestion', $context);
    }

    /**
     * Whether a provider can currently write a description for this occurrence's context.
     *
     * @return bool
     */
    private function can_use_ai(): bool {
        if ($this->canuseai === null) {
            [, , $item] = $this->get_occurrence_data();
            $this->canuseai = suggestion_service::is_available(context::instance_by_id($item->contextid));
        }
        return $this->canuseai;
    }

    /**
     * Return the occurrence, its provider, and its current content item, cached for this request.
     *
     * @return array{0: \stdClass, 1: \report_imagealt\local\content\provider, 2: \report_imagealt\local\content\content_item}
     */
    private function get_occurrence_data(): array {
        if ($this->occurrencedata === null) {
            $id = $this->optional_param('id', 0, PARAM_INT);
            $this->occurrencedata = (new manager())->get_current_occurrence($id);
        }
        return $this->occurrencedata;
    }

    /**
     * Return the context to return to after saving, validating it is in scope for the occurrence.
     *
     * @return context
     */
    private function get_return_context(): context {
        [$occurrence, , $item] = $this->get_occurrence_data();
        $contextid = $this->optional_param('contextid', 0, PARAM_INT);
        if ($contextid) {
            $context = context::instance_by_id($contextid, MUST_EXIST);
            if (!manager::is_in_scope($occurrence, $context)) {
                throw new moodle_exception('invalidcontext');
            }
            return $context;
        }
        $itemcontext = context::instance_by_id($item->contextid);
        return $itemcontext->contextlevel === CONTEXT_MODULE ? $itemcontext->get_course_context() : $itemcontext;
    }

    /**
     * Return the current user's latest suggestion for this occurrence, flipping it stale if content has changed.
     *
     * @return \stdClass|null
     */
    private function get_suggestion(): ?\stdClass {
        global $DB, $USER;

        if ($this->suggestion !== null) {
            return $this->suggestion ?: null;
        }

        [$occurrence] = $this->get_occurrence_data();
        $suggestions = $DB->get_records_sql(
            'SELECT * FROM {report_imagealt_suggestion}
              WHERE occurrenceid = :occurrenceid AND userid = :userid
           ORDER BY id DESC',
            ['occurrenceid' => $occurrence->id, 'userid' => $USER->id],
            0,
            1,
        );
        $suggestion = reset($suggestions) ?: false;
        if (
            $suggestion && $suggestion->status === 'ready'
                && !hash_equals($suggestion->originalhash, $occurrence->contenthash)
        ) {
            $suggestion->status = 'stale';
            $suggestion->timemodified = time();
            $DB->update_record('report_imagealt_suggestion', $suggestion);
        }
        $this->suggestion = $suggestion;
        return $suggestion ?: null;
    }

    /**
     * Return the text surrounding this occurrence's image in its source content.
     *
     * @return string
     */
    private function get_surrounding_text(): string {
        [$occurrence, , $item] = $this->get_occurrence_data();
        $images = (new image_parser())->extract($item->html);
        foreach ($images as $image) {
            if ((int) $image['index'] === (int) $occurrence->position) {
                return $image['surroundingtext'];
            }
        }
        return '';
    }
}
