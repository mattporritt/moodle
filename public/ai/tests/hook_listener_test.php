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

namespace core_ai;

/**
 * Test core_ai hook listener.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\hook_listener
 */
final class hook_listener_test extends \advanced_testcase {
    /**
     * Insert a minimal ai_action_generate_image row with the given contenthash.
     *
     * @param string|null $contenthash
     * @param string|null $localpathnamehash
     * @return int The id of the inserted row.
     */
    private function create_generate_image_row(?string $contenthash, ?string $localpathnamehash = null): int {
        global $DB;

        return $DB->insert_record('ai_action_generate_image', (object) [
            'prompt' => 'A test prompt',
            'numberimages' => 1,
            'quality' => 'hd',
            'aspectratio' => 'square',
            'style' => 'vivid',
            'sourceurl' => null,
            'revisedprompt' => null,
            'contenthash' => $contenthash,
            'localpathnamehash' => $localpathnamehash,
        ]);
    }

    /**
     * Promoting a draft file to a permanent file area correlates it, by contenthash, with the
     * matching unresolved ai_action_generate_image row.
     */
    public function test_correlate_generated_image_matches_permanent_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $fs = get_file_storage();
        $draftfile = $fs->create_file_from_string(
            [
                'contextid' => \context_user::instance(2)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => file_get_unused_draft_itemid(),
                'filepath' => '/',
                'filename' => 'generated.png',
            ],
            'fake-image-bytes',
        );

        $id = $this->create_generate_image_row($draftfile->get_contenthash());

        // Simulate a placement promoting the draft into its own permanent file area. This dispatches
        // the after_file_created hook via file_storage::create_file_from_storedfile().
        $coursecontext = \context_course::instance(get_course(SITEID)->id);
        $permanentfile = $fs->create_file_from_storedfile(
            [
                'contextid' => $coursecontext->id,
                'component' => 'mod_forum',
                'filearea' => 'post',
                'itemid' => 1,
                'filepath' => '/',
                'filename' => 'generated.png',
            ],
            $draftfile,
        );

        $record = $DB->get_record('ai_action_generate_image', ['id' => $id]);
        $this->assertEquals($permanentfile->get_pathnamehash(), $record->localpathnamehash);
    }

    /**
     * The draft file creation itself must not self-match: only a later promotion into a non-draft
     * file area should resolve the row.
     */
    public function test_correlate_generated_image_ignores_draft_area(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $fs = get_file_storage();
        $contenthash = hash('sha1', 'fake-image-bytes-' . random_string());
        $id = $this->create_generate_image_row($contenthash);

        // Creating a draft file with matching content must not resolve the row.
        $fs->create_file_from_string(
            [
                'contextid' => \context_user::instance(2)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => file_get_unused_draft_itemid(),
                'filepath' => '/',
                'filename' => 'generated.png',
            ],
            'fake-image-bytes-for-hash-mismatch-check',
        );

        $record = $DB->get_record('ai_action_generate_image', ['id' => $id]);
        $this->assertNull($record->localpathnamehash);
    }

    /**
     * Insert a minimal ai_action_register row linking a generate_image action id to a generating user.
     *
     * @param int $actionid The ai_action_generate_image row id.
     * @param int $userid The generating user id.
     * @return int The id of the inserted row.
     */
    private function create_action_register_row(int $actionid, int $userid): int {
        global $DB;

        return $DB->insert_record('ai_action_register', (object) [
            'actionname' => 'generate_image',
            'actionid' => $actionid,
            'success' => 1,
            'userid' => $userid,
            'contextid' => \context_system::instance()->id,
            'provider' => 'aiprovider_openai',
            'timecreated' => time(),
            'timecompleted' => time(),
            'courseid' => 0,
        ]);
    }

    /**
     * When more than one unresolved row shares a contenthash, the row belonging to the user who saved
     * the permanent file must be resolved, not simply the most recently created row.
     */
    public function test_correlate_generated_image_scopes_to_generating_user_when_ambiguous(): void {
        $this->resetAfterTest();
        global $DB;

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user2);

        $fs = get_file_storage();
        $draftfile = $fs->create_file_from_string(
            [
                'contextid' => \context_user::instance($user2->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => file_get_unused_draft_itemid(),
                'filepath' => '/',
                'filename' => 'generated.png',
            ],
            'fake-image-bytes-shared',
        );

        // Two unresolved rows share the same contenthash: an older one belonging to user1, and a
        // newer one belonging to user2. Recency alone would incorrectly resolve user1's row here,
        // since it is not the newer row; scoping to the generating user must resolve the correct one
        // regardless of creation order.
        $olderid = $this->create_generate_image_row($draftfile->get_contenthash());
        $this->create_action_register_row($olderid, $user1->id);

        $newerid = $this->create_generate_image_row($draftfile->get_contenthash());
        $this->create_action_register_row($newerid, $user2->id);

        $coursecontext = \context_course::instance(get_course(SITEID)->id);
        $permanentfile = $fs->create_file_from_storedfile(
            [
                'contextid' => $coursecontext->id,
                'component' => 'mod_forum',
                'filearea' => 'post',
                'itemid' => 3,
                'filepath' => '/',
                'filename' => 'generated.png',
            ],
            $draftfile,
        );

        $resolved = $DB->get_record('ai_action_generate_image', ['id' => $newerid]);
        $unresolved = $DB->get_record('ai_action_generate_image', ['id' => $olderid]);
        $this->assertEquals($permanentfile->get_pathnamehash(), $resolved->localpathnamehash);
        $this->assertNull($unresolved->localpathnamehash);
    }

    /**
     * A row that already has a durable reference must not be overwritten by a later, unrelated
     * file creation that happens to share the same content.
     */
    public function test_correlate_generated_image_does_not_overwrite_resolved_row(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $DB;

        $fs = get_file_storage();
        $draftfile = $fs->create_file_from_string(
            [
                'contextid' => \context_user::instance(2)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => file_get_unused_draft_itemid(),
                'filepath' => '/',
                'filename' => 'generated.png',
            ],
            'fake-image-bytes-resolved',
        );

        $id = $this->create_generate_image_row($draftfile->get_contenthash(), 'alreadyresolvedhash');

        $coursecontext = \context_course::instance(get_course(SITEID)->id);
        $fs->create_file_from_storedfile(
            [
                'contextid' => $coursecontext->id,
                'component' => 'mod_forum',
                'filearea' => 'post',
                'itemid' => 2,
                'filepath' => '/',
                'filename' => 'generated-again.png',
            ],
            $draftfile,
        );

        $record = $DB->get_record('ai_action_generate_image', ['id' => $id]);
        $this->assertEquals('alreadyresolvedhash', $record->localpathnamehash);
    }
}
