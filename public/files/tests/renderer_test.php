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

namespace core_files;

/**
 * Tests for core_files_renderer.
 *
 * @package    core_files
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \core_files_renderer
 */
final class renderer_test extends \advanced_testcase {
    /**
     * render_form_filemanager() must load the file manager via the
     * core_form/filemanager AMD module (MDL-89105), not the legacy
     * M.form_filemanager YUI2 init call, and must register the
     * core_dndupload YUI module explicitly since it is no longer pulled in
     * as a side effect of a js_init_call() $module array.
     */
    public function test_render_form_filemanager_loads_amd_module(): void {
        global $CFG;
        require_once($CFG->dirroot . '/lib/form/filemanager.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = new \moodle_page();
        $page->set_context(\core\context\system::instance());
        $page->set_url('/files/index.php');

        $options = new \stdClass();
        $options->itemid = 0;
        $options->context = \core\context\system::instance();

        $fm = new \form_filemanager($options);
        $renderer = $page->get_renderer('core', 'files');
        $html = $renderer->render_form_filemanager($fm);

        $this->assertIsString($html);
        $this->assertArrayHasKey('fileselectlayout', (array) $fm->options->templates);
        $this->assertArrayHasKey('mkdir', (array) $fm->options->templates);
        $this->assertNotEmpty($fm->options->strings);
        $this->assertArrayHasKey('entername', (array) $fm->options->strings);

        // The heavy options payload (templates, strings, file list) must be embedded
        // as a JSON <script>, not passed through js_call_amd()'s init arguments,
        // which Moodle warns against once the encoded arguments exceed ~1KB.
        $initdataid = 'filemanager-' . $fm->options->client_id . '-initdata';
        $this->assertStringContainsString('id="' . $initdataid . '"', $html);
        $this->assertStringContainsString('fileselectlayout', $html);

        $endcode = $page->requires->get_end_code();
        $this->assertStringContainsString(
            "require(['core_form/filemanager'], function(amd) {amd.init(\"{$fm->options->client_id}\")",
            $endcode
        );
        $this->assertStringNotContainsString('M.form_filemanager.init', $endcode);

        $headcode = $page->requires->get_head_code($page, $page->get_renderer('core'));
        $this->assertStringContainsString('core_dndupload', $headcode);
    }
}
