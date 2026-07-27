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

namespace core_ai\output;

/**
 * Test the action_detail renderable's image display logic.
 *
 * @package    core_ai
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_ai\output\action_detail
 */
final class action_detail_test extends \advanced_testcase {
    /**
     * Build a minimal merged ai_action_register record, as returned by
     * {@see \core_ai\manager::get_action_detail()}, for a generate_image action.
     *
     * @param \stdClass $typedata The ai_action_generate_image typedata to attach.
     * @return \stdClass
     */
    private function make_record(\stdClass $typedata): \stdClass {
        global $USER;

        return (object) [
            'id' => 1,
            'actionname' => 'generate_image',
            'actionid' => 1,
            'success' => 1,
            'userid' => $USER->id,
            'contextid' => \context_system::instance()->id,
            'provider' => 'aiprovider_openai',
            'errorcode' => null,
            'errormessage' => null,
            'timecreated' => time(),
            'timecompleted' => time(),
            'model' => 'gpt-image-1.5',
            'courseid' => 0,
            'typedata' => $typedata,
        ];
    }

    /**
     * When the local reference resolves to an existing file, the exported data must carry a
     * pluginfile.php URL for it and must not flag the image as unavailable.
     */
    public function test_export_for_template_resolves_existing_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $PAGE;

        $fs = get_file_storage();
        $coursecontext = \context_course::instance(get_course(SITEID)->id);
        $file = $fs->create_file_from_string(
            [
                'contextid' => $coursecontext->id,
                'component' => 'mod_forum',
                'filearea' => 'post',
                'itemid' => 1,
                'filepath' => '/',
                'filename' => 'generated.png',
            ],
            'fake-image-bytes',
        );

        $typedata = (object) [
            'prompt' => 'A test prompt',
            'revisedprompt' => 'A revised test prompt',
            'sourceurl' => null,
            'quality' => 'hd',
            'aspectratio' => 'square',
            'style' => 'vivid',
            'numberimages' => 1,
            'contenthash' => $file->get_contenthash(),
            'localpathnamehash' => $file->get_pathnamehash(),
        ];

        $renderable = new action_detail($this->make_record($typedata));
        $data = $renderable->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['isimage']);
        $this->assertFalse($data['imagenolongeravailable']);
        $this->assertNotNull($data['imageurl']);
        $this->assertStringContainsString('pluginfile.php', $data['imageurl']);
    }

    /**
     * When the referenced local file has been deleted, the exported data must flag the image as
     * unavailable rather than pointing at a broken or stale URL.
     */
    public function test_export_for_template_flags_deleted_file_as_unavailable(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $PAGE;

        $fs = get_file_storage();
        $coursecontext = \context_course::instance(get_course(SITEID)->id);
        $file = $fs->create_file_from_string(
            [
                'contextid' => $coursecontext->id,
                'component' => 'mod_forum',
                'filearea' => 'post',
                'itemid' => 2,
                'filepath' => '/',
                'filename' => 'deleted.png',
            ],
            'fake-image-bytes-deleted',
        );
        $pathnamehash = $file->get_pathnamehash();
        $file->delete();

        $typedata = (object) [
            'prompt' => 'A test prompt',
            'revisedprompt' => null,
            'sourceurl' => 'https://provider.example.com/expired.png',
            'quality' => 'hd',
            'aspectratio' => 'square',
            'style' => 'vivid',
            'numberimages' => 1,
            'contenthash' => 'deadbeef',
            'localpathnamehash' => $pathnamehash,
        ];

        $renderable = new action_detail($this->make_record($typedata));
        $data = $renderable->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['isimage']);
        $this->assertTrue($data['imagenolongeravailable']);
        $this->assertNull($data['imageurl']);
        // The historical sourceurl remains visible even though it is not used for display.
        $this->assertEquals('https://provider.example.com/expired.png', $data['sourceurl']);
    }

    /**
     * When there is no local reference yet (for example, the generated image was never used in
     * content), the exported data must neither show an image nor flag one as unavailable.
     */
    public function test_export_for_template_no_local_reference_yet(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        global $PAGE;

        $typedata = (object) [
            'prompt' => 'A test prompt',
            'revisedprompt' => null,
            'sourceurl' => null,
            'quality' => 'hd',
            'aspectratio' => 'square',
            'style' => 'vivid',
            'numberimages' => 1,
            'contenthash' => null,
            'localpathnamehash' => null,
        ];

        $renderable = new action_detail($this->make_record($typedata));
        $data = $renderable->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['isimage']);
        $this->assertFalse($data['imagenolongeravailable']);
        $this->assertNull($data['imageurl']);
    }
}
