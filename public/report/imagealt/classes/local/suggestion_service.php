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
 * Generates unpublished image alternative text suggestions.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class suggestion_service {
    /**
     * Whether AI can currently write an image description for content in this context.
     *
     * The single definition of that question for the whole plugin, so what the report offers and what its endpoints
     * accept cannot drift apart. A site with no AI provider, one where the report placement is disabled, or one where
     * image description is switched off for this part of the site, still uses every other part of this report: the
     * index, the classifications it reports, and writing alternative text by hand.
     *
     * @param \context $context The context the image lives in.
     * @param int|null $userid The user the description would be requested for. Defaults to the current user. Bulk
     *      generation runs in a scheduled task long after the request, where the current user is nobody in
     *      particular, so those callers name the user who asked.
     * @return bool
     */
    public static function is_available(\context $context, ?int $userid = null): bool {
        return \aiplacement_reportbuilder\utils::is_placement_action_available(
            context: $context,
            actionname: describe_image::get_basename(),
            actionclass: describe_image::class,
            userid: $userid,
        );
    }

    /**
     * Generate or regenerate a suggestion record.
     *
     * @param int $suggestionid Suggestion record ID.
     * @return \stdClass Updated suggestion record.
     */
    public function generate(int $suggestionid): \stdClass {
        global $DB;

        $suggestion = $DB->get_record('report_imagealt_suggestion', ['id' => $suggestionid], '*', MUST_EXIST);
        $occurrence = $DB->get_record('report_imagealt_occurrence', ['id' => $suggestion->occurrenceid], '*', MUST_EXIST);
        $suggestion->status = 'processing';
        $suggestion->attempts++;
        $suggestion->timemodified = time();
        $DB->update_record('report_imagealt_suggestion', $suggestion);

        try {
            $contentmanager = new manager();
            [, $provider, $item] = $contentmanager->get_current_occurrence((int) $occurrence->id);
            if (!$contentmanager->can_edit_occurrence($occurrence, (int) $suggestion->userid)) {
                throw new \moodle_exception('cannotedit', 'report_imagealt');
            }
            if (!hash_equals($suggestion->originalhash, $item->get_content_hash())) {
                $suggestion->status = 'stale';
                return $this->save($suggestion);
            }
            if (!\core_ai\manager::get_user_policy_status((int) $suggestion->userid)) {
                throw new \moodle_exception('error:policyrequired', 'report_imagealt');
            }

            $context = \context::instance_by_id($item->contextid);
            // Checked again here as well as before anything is offered or queued, because a provider can be switched
            // off, or the requesting user's role changed, between a batch being requested and the task reaching this
            // suggestion. This runs in a scheduled task, so the user has to be named: the current user here is
            // whoever cron is running as, not the person who asked for the description.
            if (!self::is_available($context, (int) $suggestion->userid)) {
                throw new \moodle_exception('error:aiunavailable', 'report_imagealt');
            }
            $aimanager = \core\di::get(\core_ai\manager::class);
            $image = $provider->resolve_file($item, $occurrence->src);
            if ($image && !in_array($image->get_mimetype(), describe_image::SUPPORTED_MIME_TYPES, true)) {
                throw new \moodle_exception('error:imagenotavailable', 'report_imagealt');
            }
            // An image referenced by URL is one the author can see on the page and can describe by hand, so refusing
            // to describe it with AI only reads as the feature being broken. The action needs a stored_file, so a
            // copy is fetched for the length of this request and removed again below.
            if (!$image) {
                if (!classifier::is_remote_source((string) $occurrence->src)) {
                    throw new \moodle_exception('error:imagenotavailable', 'report_imagealt');
                }
                $remote = new remote_image();
                $image = $remote->fetch(
                    (string) $occurrence->src,
                    \context::instance_by_id($item->contextid),
                    (int) $occurrence->id,
                );
            }

            $parsed = (new image_parser())->extract($item->html);
            $surrounding = $parsed[(int) $occurrence->position]['surroundingtext'] ?? '';
            $action = new describe_image(
                contextid: $item->contextid,
                userid: (int) $suggestion->userid,
                image: $image,
                purpose: get_string('aidescribepurpose', 'report_imagealt'),
                context: $item->itemname . ($surrounding === '' ? '' : "\n" . $surrounding),
                language: current_language(),
            );
            $response = $aimanager->process_action($action);
            if (!$response->get_success()) {
                throw new \moodle_exception('error:provider', 'report_imagealt', '', $response->get_errormessage());
            }

            $generatedcontent = trim((string) ($response->get_response_data()['generatedcontent'] ?? ''));
            if ($generatedcontent === '') {
                throw new \moodle_exception('error:provider', 'report_imagealt', '', get_string('unknownerror'));
            }
            $suggestion->suggestion = $this->add_disclosure($generatedcontent);
            $suggestion->status = 'ready';
            $suggestion->errormessage = null;
        } catch (\Throwable $e) {
            if ($suggestion->status !== 'stale') {
                $suggestion->status = 'failed';
                $suggestion->errormessage = $e->getMessage();
            }
        } finally {
            // However this ended, a copy of somebody else's image is not kept once it has been described. Cheap and
            // unconditional: the call is a no-op for the far more common case where nothing was fetched.
            if (isset($item)) {
                (new remote_image())->delete_for(
                    \context::instance_by_id($item->contextid),
                    (int) $occurrence->id,
                );
            }
        }

        return $this->save($suggestion);
    }

    /**
     * Add Moodle's AI disclosure while retaining the image editor length limit.
     *
     * @param string $description Provider response.
     * @return string Disclosed, length-limited suggestion.
     */
    private function add_disclosure(string $description): string {
        $suffix = ' - ' . get_string('contentwatermark', 'core_ai');
        $maxlength = classifier::MAX_ALT_LENGTH;
        if (\core_text::strlen($description . $suffix) <= $maxlength) {
            return $description . $suffix;
        }

        $ellipsis = '...';
        $available = $maxlength - \core_text::strlen($suffix . $ellipsis);
        return \core_text::substr($description, 0, max(0, $available)) . $ellipsis . $suffix;
    }

    /**
     * Persist a suggestion state.
     *
     * @param \stdClass $suggestion Suggestion record.
     * @return \stdClass
     */
    private function save(\stdClass $suggestion): \stdClass {
        global $DB;

        $suggestion->timemodified = time();
        $DB->update_record('report_imagealt_suggestion', $suggestion);
        return $suggestion;
    }
}
