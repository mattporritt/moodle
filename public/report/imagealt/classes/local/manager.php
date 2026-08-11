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
use report_imagealt\event\alttext_updated;
use report_imagealt\hook\extend_content_providers;
use report_imagealt\local\content\activity_provider;
use report_imagealt\local\content\content_item;
use report_imagealt\local\content\course_provider;
use report_imagealt\local\content\provider;
use report_imagealt\local\content\user_provider;

/**
 * Coordinates content providers, indexing, permissions, and stale-safe updates.
 *
 * @package    report_imagealt
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager {
    /** @var array<string, provider>|null Content providers. */
    private ?array $providers = null;

    /**
     * Return built-in and hook-registered content providers.
     *
     * @return array<string, provider>
     */
    public function get_providers(): array {
        if ($this->providers !== null) {
            return $this->providers;
        }

        $hook = new extend_content_providers();
        $hook->add_provider(new course_provider());
        $hook->add_provider(new activity_provider());
        $hook->add_provider(new user_provider());
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        return $this->providers = $hook->get_providers();
    }

    /**
     * Return one provider.
     *
     * @param string $key Provider key.
     * @return provider|null
     */
    public function get_provider(string $key): ?provider {
        return $this->get_providers()[$key] ?? null;
    }

    /**
     * Scan all registered content in a requested context.
     *
     * @param \context $context Requested report scope.
     */
    public function scan_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_COURSE) {
            $this->scan_course($context);
            return;
        }

        // This compatibility path remains useful to callers requesting a synchronous scan. Production site and category
        // scans are split into bounded targets by scan_manager, so they never materialise all site content in one task.
        [$scopesql, $scopeparams] = self::get_occurrence_scope_condition($context);
        $DB->set_field_select('report_imagealt_occurrence', 'analysisstate', 'scanning', $scopesql, $scopeparams);

        foreach ($this->get_providers() as $provider) {
            foreach ($provider->get_items($context) as $item) {
                $this->scan_item($provider, $item);
            }
        }

        $this->finish_scope_scan($scopesql, $scopeparams);
    }

    /**
     * Scan one course as the bounded unit of work used by background workers.
     *
     * A course bounds memory and transaction time independently of the total number of files on the site. Providers still
     * stream their records, allowing a large individual course to be processed without first building an in-memory item list.
     *
     * @param \context_course $context Course context.
     */
    public function scan_course(\context_course $context): void {
        global $DB;

        [$scopesql, $scopeparams] = self::get_occurrence_scope_condition($context);
        $DB->set_field_select('report_imagealt_occurrence', 'analysisstate', 'scanning', $scopesql, $scopeparams);

        foreach ($this->get_providers() as $provider) {
            foreach ($provider->get_items($context) as $item) {
                $this->scan_item($provider, $item);
            }
        }

        $this->finish_scope_scan($scopesql, $scopeparams);
    }

    /**
     * Scan one category description without rescanning all descendant courses.
     *
     * @param \context_coursecat $context Category context.
     */
    public function scan_category(\context_coursecat $context): void {
        global $DB;

        $itemkey = "category:{$context->instanceid}";
        $conditions = [
            'providerkey' => 'core_course',
            'itemkeyhash' => hash('sha256', $itemkey),
        ];
        $DB->set_field('report_imagealt_occurrence', 'analysisstate', 'scanning', $conditions);

        // Category targets are separate from course targets so changing a category description does not fan out into every
        // descendant course. The discovery coordinator queues those courses individually when a wider refresh is requested.
        $provider = $this->get_provider('core_course');
        $item = $provider?->get_item($itemkey);
        if ($provider && $item) {
            $this->scan_item($provider, $item);
        }

        $this->finish_scope_scan(
            'providerkey = :categoryprovider AND itemkeyhash = :categoryitem',
            ['categoryprovider' => 'core_course', 'categoryitem' => hash('sha256', $itemkey)],
        );
    }

    /**
     * Scan one user profile description without walking the whole site.
     *
     * Mirrors scan_category: a single sitewide-discovered target is bounded to its own provider and item, so an edit to
     * one profile never needs to re-scan every user on the site.
     *
     * @param int $userid User ID.
     */
    public function scan_user(int $userid): void {
        global $DB;

        $itemkey = "user:{$userid}";
        $conditions = ['providerkey' => 'core_user', 'itemkeyhash' => hash('sha256', $itemkey)];
        $DB->set_field('report_imagealt_occurrence', 'analysisstate', 'scanning', $conditions);

        $provider = $this->get_provider('core_user');
        $item = $provider?->get_item($itemkey);
        if ($provider && $item) {
            $this->scan_item($provider, $item);
        }

        $this->finish_scope_scan(
            'providerkey = :userprovider AND itemkeyhash = :useritem',
            ['userprovider' => 'core_user', 'useritem' => hash('sha256', $itemkey)],
        );
    }

    /**
     * Scan one current content item and upsert every image occurrence.
     *
     * @param provider $provider Content provider.
     * @param content_item $item Content item.
     */
    public function scan_item(provider $provider, content_item $item): void {
        global $DB;

        $parser = new image_parser();
        $classifier = new classifier();
        $now = time();
        $itemkeyhash = hash('sha256', $item->key);
        $existingrecords = $DB->get_records('report_imagealt_occurrence', [
            'providerkey' => $provider->get_key(),
            'itemkeyhash' => $itemkeyhash,
        ]);
        $existingbykey = [];
        foreach ($existingrecords as $existing) {
            $existingbykey[$existing->occurrencekey] = $existing;
        }

        $newrecords = [];
        $changedsuggestionids = [];
        $carriedsuggestionids = [];
        $resolvedfiles = [];
        foreach ($parser->extract($item->html) as $image) {
            // Repeated references to the same source are common in copied editor content. File resolution can query the file
            // pool, so cache it for the duration of this item instead of repeating identical lookups per occurrence.
            if (!array_key_exists($image['src'], $resolvedfiles)) {
                $resolvedfiles[$image['src']] = $provider->resolve_file($item, $image['src']);
            }
            $file = $resolvedfiles[$image['src']];
            // A reference the content owns that resolves to no file is a broken image: the file was deleted, or the
            // reference was never right. Anything addressed by URL is left alone, because whether it loads cannot be
            // known without fetching it, and the report does not fetch other people's URLs to find out.
            $filemissing = $file === null && classifier::is_owned_reference($image['src']);
            $classification = $classifier->classify(
                $image['hasalt'],
                $image['alt'],
                $image['src'],
                $image['decorative'],
                $image['linkedonly'],
                $filemissing,
            );
            // A stored file is judged on what it is. An image referenced by URL is taken on trust: whether it can be
            // fetched and what type it turns out to be cannot be known without going and getting it, and doing that
            // for every image on every scan would have the site fetching other people's URLs continuously. So it is
            // offered, and a fetch that fails reports itself against that one image at generation time.
            $aieligible = $file !== null
                ? in_array($file->get_mimetype(), describe_image::SUPPORTED_MIME_TYPES, true)
                : classifier::is_remote_source($image['src']);
            // The file's own content hash rather than a URL: previews are served by this plugin (see
            // resolve_preview_file()), so all a row needs to record is whether a file resolved and which version
            // of it, which lets the preview URL change when the image does and be cached until then.
            $previewhash = $file?->get_contenthash();
            $filename = $file?->get_filename() ?: basename((string) parse_url($image['src'], PHP_URL_PATH));

            $record = (object) [
                'providerkey' => $provider->get_key(),
                'itemkey' => $item->key,
                'itemkeyhash' => $itemkeyhash,
                'occurrencekey' => $image['occurrencekey'],
                'position' => $image['index'],
                'contextid' => $item->contextid,
                'courseid' => $item->courseid,
                'categoryid' => $item->categoryid,
                'component' => $item->component,
                'contenttype' => $item->contenttype,
                'itemname' => $item->itemname,
                'fieldname' => $item->fieldname,
                'contenthash' => $item->get_content_hash(),
                'occurrencehash' => $image['occurrencehash'],
                'src' => $image['src'],
                'previewhash' => $previewhash,
                'filename' => $filename ?: null,
                'alttext' => $image['alt'],
                'hasalt' => (int) $image['hasalt'],
                'decorative' => (int) $image['decorative'],
                'status' => $classification['status'],
                'reason' => $classification['reason'],
                'aieligible' => (int) $aieligible,
                'analysisstate' => 'ready',
                'timeanalysed' => $now,
                'timemodified' => $now,
            ];
            if (isset($existingbykey[$record->occurrencekey])) {
                $existing = $existingbykey[$record->occurrencekey];
                $record->id = $existing->id;
                $DB->update_record('report_imagealt_occurrence', $record);
                if (!hash_equals($existing->contenthash, $record->contenthash)) {
                    // The content item changed, but a suggestion describes one image, so only a change to that
                    // image's own tag can make it wrong. Where the tag is untouched the suggestion is still
                    // accurate and is carried forward against the new content instead. Marking it stale here meant
                    // editing any one image in a summary discarded every pending suggestion for every other image
                    // in the same summary, so accepting several in bulk applied the first and threw the rest away.
                    if (hash_equals($existing->occurrencehash, $record->occurrencehash)) {
                        $carriedsuggestionids[] = (int) $existing->id;
                    } else {
                        $changedsuggestionids[] = (int) $existing->id;
                    }
                }
                unset($existingbykey[$record->occurrencekey]);
            } else {
                $newrecords[] = $record;
            }
        }

        // One multi-row insert avoids an extra database round trip for every newly discovered image in rich content.
        if ($newrecords) {
            $DB->insert_records('report_imagealt_occurrence', $newrecords);
        }
        $this->mark_suggestions_stale($changedsuggestionids);
        $this->carry_suggestions_forward($carriedsuggestionids, $item->get_content_hash());

        // Records left in the map represent removed image occurrences. Reconcile them here as well as at scope completion so
        // direct item refreshes remain correct and do not depend on a later whole-course scan.
        $this->mark_occurrences_stale(array_map(
            static fn(\stdClass $record): int => (int) $record->id,
            array_values($existingbykey),
        ));
    }

    /**
     * Finish a scope scan without loading every stale occurrence ID into PHP memory.
     *
     * @param string $scopesql SQL selecting occurrence rows in the scope.
     * @param array $scopeparams Scope SQL parameters.
     */
    private function finish_scope_scan(string $scopesql, array $scopeparams): void {
        global $DB;

        [$statussql, $statusparams] = $DB->get_in_or_equal(
            ['queued', 'processing', 'ready'],
            SQL_PARAMS_NAMED,
            'finishsuggestionstatus',
        );
        $params = $scopeparams + ['finishscanning' => 'scanning'] + $statusparams;

        // Keep the stale operation inside the database. A PHP ID list is unbounded for a site refresh and can exhaust memory
        // on precisely the large installations this background architecture is intended to support.
        $DB->set_field_select(
            'report_imagealt_suggestion',
            'status',
            'stale',
            "status {$statussql} AND occurrenceid IN (
                SELECT staleoccurrence.id
                  FROM {report_imagealt_occurrence} staleoccurrence
                 WHERE {$scopesql} AND staleoccurrence.analysisstate = :finishscanning
            )",
            $params,
        );
        $DB->set_field_select(
            'report_imagealt_occurrence',
            'analysisstate',
            'stale',
            "{$scopesql} AND analysisstate = :finishscanning",
            $scopeparams + ['finishscanning' => 'scanning'],
        );
    }

    /**
     * Mark removed occurrences and their unpublished suggestions stale in bounded parameter batches.
     *
     * @param int[] $occurrenceids Occurrence IDs.
     */
    private function mark_occurrences_stale(array $occurrenceids): void {
        global $DB;

        foreach (array_chunk($occurrenceids, 500) as $chunk) {
            $this->mark_suggestions_stale($chunk);
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'staleoccurrence');
            $DB->set_field_select('report_imagealt_occurrence', 'analysisstate', 'stale', "id {$insql}", $params);
        }
    }

    /**
     * Re-point pending suggestions at their item's new content, for images the change did not touch.
     *
     * Staleness is recorded as the item content hash the suggestion was generated against, so a suggestion that is
     * still accurate has to be moved to the new hash or the review pages would report it as out of date.
     *
     * @param int[] $occurrenceids Occurrences whose own image tag is unchanged.
     * @param string $contenthash The item's new content hash.
     */
    private function carry_suggestions_forward(array $occurrenceids, string $contenthash): void {
        global $DB;

        foreach (array_chunk($occurrenceids, 500) as $chunk) {
            [$occurrencesql, $occurrenceparams] = $DB->get_in_or_equal(
                $chunk,
                SQL_PARAMS_NAMED,
                'carryoccurrence',
            );
            [$statussql, $statusparams] = $DB->get_in_or_equal(
                ['queued', 'processing', 'ready'],
                SQL_PARAMS_NAMED,
                'carrystatus',
            );
            $DB->set_field_select(
                'report_imagealt_suggestion',
                'originalhash',
                $contenthash,
                "occurrenceid {$occurrencesql} AND status {$statussql}",
                $occurrenceparams + $statusparams,
            );
        }
    }

    /**
     * Invalidate unpublished work after exact source occurrences change.
     *
     * @param int[] $occurrenceids Occurrence IDs.
     */
    private function mark_suggestions_stale(array $occurrenceids): void {
        global $DB;

        foreach (array_chunk($occurrenceids, 500) as $chunk) {
            [$occurrencesql, $occurrenceparams] = $DB->get_in_or_equal(
                $chunk,
                SQL_PARAMS_NAMED,
                'suggestionoccurrence',
            );
            [$statussql, $statusparams] = $DB->get_in_or_equal(
                ['queued', 'processing', 'ready'],
                SQL_PARAMS_NAMED,
                'suggestionstatus',
            );
            $DB->set_field_select(
                'report_imagealt_suggestion',
                'status',
                'stale',
                "occurrenceid {$occurrencesql} AND status {$statussql}",
                $occurrenceparams + $statusparams,
            );
        }
    }

    /**
     * Return an occurrence and current content item.
     *
     * @param int $id Occurrence ID.
     * @return array{0: \stdClass, 1: provider, 2: content_item}
     */
    public function get_current_occurrence(int $id): array {
        global $DB;

        $occurrence = $DB->get_record('report_imagealt_occurrence', ['id' => $id], '*', MUST_EXIST);
        $provider = $this->get_provider($occurrence->providerkey);
        $item = $provider?->get_item($occurrence->itemkey);
        if (!$provider || !$item) {
            throw new \moodle_exception('contentchanged', 'report_imagealt');
        }
        return [$occurrence, $provider, $item];
    }

    /**
     * Check whether the current occurrence may be edited by a user.
     *
     * @param \stdClass $occurrence Occurrence record.
     * @param int $userid User ID.
     * @return bool
     */
    public function can_edit_occurrence(\stdClass $occurrence, int $userid): bool {
        $provider = $this->get_provider($occurrence->providerkey);
        $item = $provider?->get_item($occurrence->itemkey);
        return $provider !== null && $item !== null && $provider->can_edit($item, $userid);
    }

    /**
     * Resolve the stored file behind an occurrence, for a user allowed to see it.
     *
     * The report cannot build a working pluginfile URL for every component it indexes: some, such as mod_page,
     * carry a revision in the URL path that is not the file's item ID, so a URL assembled from the file's own
     * fields is rejected by that component's own file serving. Resolving the file through the provider is the
     * one path that already works for every provider, including any a plugin registers, which is why previews
     * are served from this plugin instead of linked to the component's URL.
     *
     * @param int $occurrenceid Occurrence ID.
     * @param int $userid User requesting the preview.
     * @return \stored_file|null Null when the occurrence is gone, its file cannot be resolved, or the user may
     *      not see it.
     */
    public function resolve_preview_file(int $occurrenceid, int $userid): ?\stored_file {
        global $DB;

        $occurrence = $DB->get_record('report_imagealt_occurrence', ['id' => $occurrenceid]);
        if (!$occurrence) {
            return null;
        }
        $provider = $this->get_provider($occurrence->providerkey);
        $item = $provider?->get_item($occurrence->itemkey);
        if (!$provider || !$item) {
            return null;
        }
        // Deliberately the same test that gates changing the image, rather than a weaker one. It keeps the report
        // from serving file content to anyone who could not already open that content in its own editor, which is
        // the guarantee the component's own file serving would otherwise have made.
        if (!$provider->can_edit($item, $userid)) {
            return null;
        }

        return $provider->resolve_file($item, $occurrence->src);
    }

    /**
     * Update one exact image occurrence after all stale and permission checks.
     *
     * @param int $id Occurrence ID.
     * @param string $alt Alternative text.
     * @param bool $decorative Whether the image is decorative.
     * @param int $userid User ID.
     * @param int|null $suggestionid The AI suggestion being applied unedited, for the logged event to record. Null
     *      for text a person wrote or edited themselves.
     */
    public function update_occurrence(
        int $id,
        string $alt,
        bool $decorative,
        int $userid,
        ?int $suggestionid = null,
    ): void {
        [$occurrence, $provider, $item] = $this->get_current_occurrence($id);
        if (!$provider->can_edit($item, $userid)) {
            throw new \required_capability_exception(
                \context::instance_by_id($item->contextid),
                'report/imagealt:view',
                'nopermissions',
                '',
            );
        }
        if (!hash_equals($occurrence->contenthash, $item->get_content_hash())) {
            throw new \moodle_exception('contentchanged', 'report_imagealt');
        }
        // Refused here as well as withheld in the report, because the report is not the only way in. Describing an
        // image the site cannot find puts words to something nobody can see, and would report the row as remediated
        // while the page it sits on still shows nothing.
        if ($occurrence->status === 'broken') {
            throw new \moodle_exception('error:brokenimage', 'report_imagealt');
        }

        $html = (new image_parser())->replace(
            $item->html,
            (int) $occurrence->position,
            $occurrence->occurrencehash,
            $alt,
            $decorative,
        );
        if ($html === null) {
            throw new \moodle_exception('contentchanged', 'report_imagealt');
        }

        $provider->update($item, $html);

        // Logged here rather than in each caller because this is the only path that writes alternative text, so a
        // write cannot reach the content without being recorded. The occurrence is rescanned afterwards and may
        // change ID, so the event describes the occurrence that was edited.
        alttext_updated::create([
            'objectid' => (int) $occurrence->id,
            'contextid' => (int) $item->contextid,
            'userid' => $userid,
            'other' => [
                'source' => $suggestionid === null
                    ? alttext_updated::SOURCE_MANUAL
                    : alttext_updated::SOURCE_ACCEPTED,
                'suggestionid' => $suggestionid,
                'decorative' => $decorative,
            ],
        ])->trigger();

        $updateditem = $provider->get_item($item->key);
        if ($updateditem) {
            $this->scan_item($provider, $updateditem);
        }
    }

    /**
     * Determine whether an occurrence is inside a requested scope.
     *
     * @param \stdClass $occurrence Occurrence record.
     * @param \context $context Requested context.
     * @return bool
     */
    public static function is_in_scope(\stdClass $occurrence, \context $context): bool {
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            return true;
        }
        if ($context->contextlevel === CONTEXT_COURSE) {
            return (int) $occurrence->courseid === (int) $context->instanceid;
        }
        if ($context->contextlevel === CONTEXT_COURSECAT) {
            global $DB;

            $category = $DB->get_record('course_categories', ['id' => $context->instanceid], 'id, path');
            if (!$category) {
                return false;
            }
            return $DB->record_exists_select(
                'course_categories',
                'id = :occurrencecategory AND (id = :scopecategory OR ' . $DB->sql_like('path', ':scopepath') . ')',
                [
                    'occurrencecategory' => $occurrence->categoryid,
                    'scopecategory' => $category->id,
                    'scopepath' => "{$category->path}/%",
                ],
            );
        }
        return false;
    }

    /**
     * SQL condition for occurrence records in a context.
     *
     * @param \context $context Requested context.
     * @param string $alias Optional table alias.
     * @return array{0: string, 1: array}
     */
    public static function get_occurrence_scope_condition(\context $context, string $alias = ''): array {
        global $DB;

        $prefix = $alias === '' ? '' : "{$alias}.";
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            return ['1 = 1', []];
        }
        if ($context->contextlevel === CONTEXT_COURSE) {
            return ["{$prefix}courseid = :scopecourseid", ['scopecourseid' => $context->instanceid]];
        }
        if ($context->contextlevel === CONTEXT_COURSECAT) {
            $category = $DB->get_record(
                'course_categories',
                ['id' => $context->instanceid],
                'id, path',
                MUST_EXIST,
            );
            $pathsql = $DB->sql_like('scopedcategory.path', ':scopecategorypath');
            return ["{$prefix}categoryid IN (
                        SELECT scopedcategory.id
                          FROM {course_categories} scopedcategory
                         WHERE scopedcategory.id = :scopecategoryid OR {$pathsql}
                    )", [
                'scopecategoryid' => $category->id,
                'scopecategorypath' => "{$category->path}/%",
            ]];
        }
        return ['1 = 0', []];
    }
}
