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

/**
 * Data generator for the image alternative text report.
 *
 * Tests of the AI workflow need an occurrence backed by a real stored file, because that is what makes an image
 * AI eligible, and suggestions in states a provider would normally have to produce. Both are built here so no test
 * has to reach into the plugin's tables itself or stand in a real provider.
 *
 * @package    report_imagealt
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_imagealt_generator extends component_generator_base {
    /** @var int Number of images created, to keep generated file names unique. */
    private int $imagecount = 0;

    /** @var array<string, int> Batch ID for each batch name used by create_suggestion(). */
    private array $batches = [];

    #[\Override]
    public function reset(): void {
        $this->imagecount = 0;
        $this->batches = [];
    }

    /**
     * Add an image to a course summary and index it, so the report lists an AI eligible occurrence for it.
     *
     * @param array|stdClass $record Fields:
     *      courseid (int, required) the course whose summary gains the image;
     *      filename (string) the stored file name, unique per course by default;
     *      alt (string|null) the alternative text, omitted entirely when null, which is what "missing" means;
     *      decorative (bool) mark the image presentational.
     * @return stdClass The indexed occurrence record.
     */
    public function create_image($record = []): stdClass {
        global $CFG, $DB;

        $record = (array) $record;
        if (empty($record['courseid'])) {
            throw new coding_exception('An image must be created in a course.');
        }
        $courseid = (int) $record['courseid'];
        $filename = $record['filename'] ?? 'generated-image-' . ++$this->imagecount . '.png';
        $context = context_course::instance($courseid);

        // A real file in the summary's own file area is the whole point: resolve_file() looks for exactly this, and
        // an image it cannot resolve to a stored file is not AI eligible.
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'course',
            'filearea' => 'summary',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => 'image/png',
        ], $this->get_png_content());

        $attributes = ['src' => '@@PLUGINFILE@@/' . rawurlencode($filename)];
        // A missing alt attribute and an empty one are different classifications, so an unset alt is left off the
        // tag entirely rather than written as alt="".
        if (array_key_exists('alt', $record) && $record['alt'] !== null) {
            $attributes['alt'] = (string) $record['alt'];
        }
        if (!empty($record['decorative'])) {
            // Both halves, because that is what marks an image decorative: the role alone, with no alt attribute at
            // all, is an image that is merely missing its alternative text. This is the pair Moodle's own image
            // dialogue writes, and the pair image_parser looks for.
            $attributes['role'] = 'presentation';
            $attributes['alt'] = '';
        }

        require_once($CFG->dirroot . '/course/lib.php');
        $summary = (string) $DB->get_field('course', 'summary', ['id' => $courseid]);
        update_course((object) [
            'id' => $courseid,
            'summary' => $summary . html_writer::empty_tag('img', $attributes),
            'summaryformat' => FORMAT_HTML,
        ]);

        // Scanned through the real provider so the occurrence is indexed exactly as it would be in production,
        // including its content hash, which the suggestion workflow compares against.
        $manager = new \report_imagealt\local\manager();
        $provider = $manager->get_provider('core_course');
        $item = $provider->get_item("course:{$courseid}");
        $manager->scan_item($provider, $item);

        $occurrence = $DB->get_record('report_imagealt_occurrence', [
            'providerkey' => 'core_course',
            'itemkeyhash' => hash('sha256', "course:{$courseid}"),
            'filename' => $filename,
        ], '*', MUST_EXIST);
        if (!$occurrence->aieligible) {
            throw new coding_exception("The generated image {$filename} was not indexed as AI eligible.");
        }

        return $occurrence;
    }

    /**
     * Put a suggestion against an indexed image in whatever state a test needs.
     *
     * Bypasses the AI provider entirely: the point is to test what the report and review pages do with a
     * suggestion, not to test generating one.
     *
     * @param array|stdClass $record Fields:
     *      courseid (int, required) and filename (string, required) identifying the indexed image, or
     *      occurrenceid (int) instead of both;
     *      userid (int) whose suggestion it is, defaulting to the site administrator;
     *      status (string) suggestion status, defaulting to ready;
     *      suggestion (string) the generated text, required in practice for a ready suggestion;
     *      errormessage (string) the recorded failure, for a failed one;
     *      batch (string) a name, so several suggestions can be put in one batch;
     *      stale (bool) record a content hash that no longer matches, making the suggestion out of date.
     * @return stdClass The suggestion record.
     */
    public function create_suggestion($record = []): stdClass {
        global $DB;

        $record = (array) $record;
        $occurrence = $this->resolve_occurrence($record);
        $userid = (int) ($record['userid'] ?? get_admin()->id);
        $status = $record['status'] ?? 'ready';
        $now = time();

        $batchname = $record['batch'] ?? "batch-{$userid}-" . count($this->batches);
        if (!isset($this->batches[$batchname])) {
            $this->batches[$batchname] = (int) $DB->insert_record('report_imagealt_batch', (object) [
                'contextid' => $occurrence->contextid,
                'userid' => $userid,
                'status' => 'queued',
                'total' => 0,
                'completed' => 0,
                'failed' => 0,
                'cancelled' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        $batchid = $this->batches[$batchname];

        $suggestionid = $DB->insert_record('report_imagealt_suggestion', (object) [
            'occurrenceid' => $occurrence->id,
            'batchid' => $batchid,
            'userid' => $userid,
            'status' => $status,
            // A ready suggestion whose hash no longer matches its content is treated as stale on sight, so the
            // real hash is recorded unless a test is specifically after that behaviour.
            'originalhash' => empty($record['stale']) ? $occurrence->contenthash : sha1('outdated content'),
            'suggestion' => $record['suggestion'] ?? null,
            'errormessage' => $record['errormessage'] ?? null,
            'attempts' => in_array($status, ['queued'], true) ? 0 : 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Left describing its own items, the way the processing task leaves it, so the batch page's progress
        // summary reports something coherent without the test having to run the task.
        $batch = $DB->get_record('report_imagealt_batch', ['id' => $batchid], '*', MUST_EXIST);
        $counts = (new \report_imagealt\local\batch_manager())->apply_counts($batch);
        $batch->total = array_sum($counts);
        $outstanding = ($counts['queued'] ?? 0) + ($counts['processing'] ?? 0);
        $batch->status = match (true) {
            $outstanding > 0 => 'processing',
            $batch->failed > 0 => 'partial',
            default => 'complete',
        };
        $batch->timemodified = $now;
        $DB->update_record('report_imagealt_batch', $batch);

        return $DB->get_record('report_imagealt_suggestion', ['id' => $suggestionid], '*', MUST_EXIST);
    }

    /**
     * Find the indexed occurrence a suggestion is being created against.
     *
     * @param array $record The suggestion fields.
     * @return stdClass The occurrence record.
     */
    private function resolve_occurrence(array $record): stdClass {
        global $DB;

        if (!empty($record['occurrenceid'])) {
            return $DB->get_record('report_imagealt_occurrence', ['id' => (int) $record['occurrenceid']], '*', MUST_EXIST);
        }
        if (empty($record['courseid']) || empty($record['filename'])) {
            throw new coding_exception('A suggestion needs either an occurrenceid, or a courseid and filename.');
        }

        return $DB->get_record('report_imagealt_occurrence', [
            'providerkey' => 'core_course',
            'itemkeyhash' => hash('sha256', 'course:' . (int) $record['courseid']),
            'filename' => (string) $record['filename'],
        ], '*', MUST_EXIST);
    }

    /**
     * A minimal valid PNG, so the file resolves and reports a supported image mime type.
     *
     * @return string
     */
    private function get_png_content(): string {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );
    }
}
