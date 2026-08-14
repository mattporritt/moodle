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

/**
 * Unit tests for importziplib.
 *
 * @package    assignfeedback_file
 * @copyright  2020 Eric Merrill <merrill@oakland.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_file;

use mod_assign_test_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once($CFG->dirroot . '/mod/assign/feedback/file/importziplib.php');

/**
 * Unit tests for importziplib.
 *
 * @package    assignfeedback_file
 * @copyright  2020 Eric Merrill <merrill@oakland.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \assignfeedback_file_zip_importer
 */
final class importziplib_test extends \advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;

    /**
     * Test the assignfeedback_file_zip_importer->is_valid_filename_for_import() method.
     */
    public function test_is_valid_filename_for_import(): void {
        // Do the initial assign setup.
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $assign = $this->create_instance($course, [
                'assignsubmission_onlinetext_enabled' => 1,
                'assignfeedback_file_enabled' => 1,
            ]);

        // Create an online text submission.
        $this->add_submission($student, $assign);

        // Now onto the file work.
        $fs = get_file_storage();

        // Setup a basic file we will work with. We will keep renaming and repathing it.
        $record = new \stdClass;
        $record->contextid = $assign->get_context()->id;
        $record->component = 'assignfeedback_file';
        $record->filearea  = ASSIGNFEEDBACK_FILE_FILEAREA;
        $record->itemid    = $assign->get_user_grade($student->id, true)->id;
        $record->filepath  = '/';
        $record->filename  = '1.txt';
        $record->source    = 'test';
        $file = $fs->create_file_from_string($record, 'file content');

        // The importer we will use.
        $importer = new \assignfeedback_file_zip_importer();

        // Setup some variable we use.
        $user = null;
        $plugin = null;
        $filename = '';

        $allusers = $assign->list_participants(0, false);
        $participants = array();
        foreach ($allusers as $user) {
            $participants[$assign->get_uniqueid_for_user($user->id)] = $user;
        }

        $file->rename('/import/', '.hiddenfile');
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertFalse($result);

        $file->rename('/import/', '~hiddenfile');
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertFalse($result);

        $file->rename('/import/some_path_here/', 'RandomFile.txt');
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertFalse($result);

        $file->rename('/import/', '~hiddenfile');
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertFalse($result);

        // Get the students assign id.
        $studentid = $assign->get_uniqueid_for_user($student->id);

        // Submissions are identified with the format:
        // StudentName_StudentID_PluginType_Plugin_FilePathAndName.

        // Test a string student id.
        $badname = 'Student Name_StringID_assignsubmission_file_My_cool_filename.txt';
        $file->rename('/import/', $badname);
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertFalse($result);

        // Test an invalid student id.
        $badname = 'Student Name_' . ($studentid + 100) . '_assignsubmission_file_My_cool_filename.txt';
        $file->rename('/import/', $badname);
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertFalse($result);

        // Test an invalid submission plugin.
        $badname = 'Student Name_' . $studentid . '_assignsubmission_noplugin_My_cool_filename.txt';
        $file->rename('/import/', $badname);
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertFalse($result);

        // Test a basic, good file.
        $goodbase = 'Student Name_' . $studentid . '_assignsubmission_file_';
        $file->rename('/import/', $goodbase . "My_cool_filename.txt");
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertTrue($result);
        $this->assertEquals($participants[$studentid], $user);
        $this->assertEquals('My_cool_filename.txt', $filename);
        $this->assertInstanceOf(\assign_submission_file::class, $plugin);

        // Test another good file, with some additional path and underscores.
        $user = null;
        $plugin = null;
        $filename = '';
        $file->rename('/import/some_path_here/' . $goodbase . '/some_path/', 'My File.txt');
        $result = $importer->is_valid_filename_for_import($assign, $file, $participants, $user, $plugin, $filename);
        $this->assertTrue($result);
        $this->assertEquals($participants[$studentid], $user);
        $this->assertEquals('/some_path/My File.txt', $filename);
        $this->assertInstanceOf(\assign_submission_file::class, $plugin);
    }

    /**
     * Set up an assignment, a teacher grader and a student with a feedback file waiting in the
     * import area, ready to be picked up by import_zip_files().
     *
     * @return array [assign $assign, stdClass $teacher, stdClass $student]
     */
    private function prepare_import_fixture(): array {
        global $PAGE;

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $assign = $this->create_instance($course, [
            'assignsubmission_onlinetext_enabled' => 1,
            'assignfeedback_file_enabled' => 1,
        ]);

        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $assign->get_course_module()->id]));

        $this->add_submission($student, $assign);

        // Grading actions (including the zip import) happen as the teacher.
        $this->setUser($teacher);

        // The zip importer matches files by the participant's assignment-specific unique id,
        // not their normal user id.
        $uniqueid = $assign->get_uniqueid_for_user($student->id);
        $filename = fullname($student) . '_' . $uniqueid . '_assignsubmission_onlinetext_feedback.txt';

        $fs = get_file_storage();
        $record = new \stdClass();
        $record->contextid = $assign->get_context()->id;
        $record->component = 'assignfeedback_file';
        $record->filearea = ASSIGNFEEDBACK_FILE_IMPORT_FILEAREA;
        $record->itemid = $teacher->id;
        $record->filepath = '/import/';
        $record->filename = $filename;
        $fs->create_file_from_string($record, 'Some feedback');

        return [$assign, $teacher, $student];
    }

    /**
     * Data provider for test_import_zip_files_notifications().
     *
     * @return array
     */
    public static function import_zip_files_notifications_provider(): array {
        return [
            'Notifications requested' => [true],
            'Notifications not requested' => [false],
        ];
    }

    /**
     * Test whether the student is queued for a notification if and only if requested.
     * @dataProvider import_zip_files_notifications_provider
     * @param bool $sendstudentnotifications value passed to import_zip_files()
     */
    public function test_import_zip_files_notifications(bool $sendstudentnotifications): void {
        $this->resetAfterTest();

        [$assign, , $student] = $this->prepare_import_fixture();
        $importer = new \assignfeedback_file_zip_importer();
        $fileplugin = $assign->get_feedback_plugin_by_type('file');
        $importer->import_zip_files($assign, $fileplugin, $sendstudentnotifications);

        $flags = $assign->get_user_flags($student->id, false);
        if ($sendstudentnotifications) {
            $this->assertEquals(0, $flags->mailed);
        } else {
            $this->assertFalse($flags);
        }
    }
}
