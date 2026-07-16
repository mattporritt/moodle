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

namespace fileconverter_unoconv;

/**
 * A set of tests for some of the unoconv functionality within Moodle.
 *
 * @package    fileconverter_unoconv
 * @copyright  2016 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class converter_test extends \advanced_testcase {

    /**
     * Helper to skip tests which _require_ unoconv.
     */
    protected function require_unoconv() {
        global $CFG;

        if (empty($CFG->pathtounoconv) || !file_is_executable(trim($CFG->pathtounoconv))) {
            // No conversions are possible, sorry.
            $this->markTestSkipped();
        }
    }

    /**
     * Get a testable mock of the fileconverter_unoconv class.
     *
     * @param   array   $mockedmethods A list of methods you intend to override
     *                  If no methods are specified, only abstract functions are mocked.
     * @return  \fileconverter_unoconv\converter
     */
    protected function get_testable_mock($mockedmethods = []) {
        $converter = $this->getMockBuilder(\fileconverter_unoconv\converter::class)
            ->onlyMethods($mockedmethods)
            ->getMock();

        return $converter;
    }

    /**
     * Tests for the start_document_conversion function.
     */
    public function test_start_document_conversion(): void {
        $this->resetAfterTest();

        $this->require_unoconv();

        // Mock the file to be converted.
        $filerecord = [
            'contextid' => \context_system::instance()->id,
            'component' => 'test',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => 'test.docx',
        ];
        $fs = get_file_storage();
        $source = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'unoconv-source.docx';
        $testfile = $fs->create_file_from_pathname($filerecord, $source);

        $converter = $this->get_testable_mock();
        $conversion = new \core_files\conversion(0, (object) [
            'targetformat' => 'pdf',
        ]);
        $conversion->set_sourcefile($testfile);
        $conversion->create();

        // Convert the document.
        $converter->start_document_conversion($conversion);
        $result = $conversion->get_destfile();
        $this->assertNotFalse($result);
        $this->assertSame('application/pdf', $result->get_mimetype());
        $this->assertGreaterThan(0, $result->get_filesize());
    }

    /**
     * Tests for the test_unoconv_path function.
     *
     * @dataProvider provider_test_unoconv_path
     * @param   string $path The path to test
     * @param   int $status The expected status
     */
    public function test_test_unoconv_path($path, $status): void {
        global $CFG;

        $this->resetAfterTest();

        // Set the current path.
        $CFG->pathtounoconv = $path;

        // Run the tests.
        $result = \fileconverter_unoconv\converter::test_unoconv_path();

        $this->assertEquals($status, $result->status);
    }

    /**
     * Provider for test_unoconv_path.
     *
     * @return  array
     */
    public static function provider_test_unoconv_path(): array {
        return [
            'Empty path' => [
                'path' => null,
                'status' => \fileconverter_unoconv\converter::UNOCONVPATH_EMPTY,
            ],
            'Invalid file' => [
                'path' => '/path/to/nonexistent/file',
                'status' => \fileconverter_unoconv\converter::UNOCONVPATH_DOESNOTEXIST,
            ],
            'Directory' => [
                'path' => __DIR__,
                'status' => \fileconverter_unoconv\converter::UNOCONVPATH_ISDIR,
            ],
        ];
    }

    /**
     * Reset the cached list of supported formats so a fake unoconv binary is re-queried.
     */
    protected function reset_formats_cache(): void {
        $property = new \ReflectionProperty(\fileconverter_unoconv\converter::class, 'formats');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Reset the cached requirements-met check so a fake or missing unoconv path is re-checked.
     */
    protected function reset_requirements_met_cache(): void {
        $property = new \ReflectionProperty(\fileconverter_unoconv\converter::class, 'requirementsmet');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Create a fake executable that mimics unoconv's --show and --version output without
     * requiring unoconv to be installed, so the fetch_supported_formats() pipe handling can
     * be tested directly.
     *
     * @param   string $stdout Content the fake binary writes to stdout for --show.
     * @param   string $stderr Content the fake binary writes to stderr for --show.
     * @return  string Path to the fake executable.
     */
    protected function create_fake_unoconv_binary(string $stdout, string $stderr = ''): string {
        $path = make_request_directory() . '/fake-unoconv.sh';
        $script = "#!/bin/sh\n" .
            "if [ \"\$1\" = '--version' ]; then echo 'unoconv 0.9'; exit 0; fi\n" .
            "if [ \"\$1\" = '--show' ]; then\n" .
            (($stdout !== '') ? "printf '%s' " . escapeshellarg($stdout) . " 1>&1\n" : '') .
            (($stderr !== '') ? "printf '%s' " . escapeshellarg($stderr) . " 1>&2\n" : '') .
            "exit 0\n" .
            "fi\n" .
            "exit 1\n";
        file_put_contents($path, $script);
        chmod($path, 0755);

        return $path;
    }

    /**
     * fetch_supported_formats() must read the list of supported formats from unoconv's
     * standard output, not its standard error, otherwise the format list stays empty even
     * when unoconv successfully reports its supported formats (MDL-63220).
     *
     * @covers \fileconverter_unoconv\converter::get_supported_conversions
     */
    public function test_fetch_supported_formats_reads_stdout(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->reset_formats_cache();

        $CFG->pathtounoconv = $this->create_fake_unoconv_binary("[.pdf]\n[.docx]\n");

        $converter = $this->get_testable_mock();
        $conversions = $converter->get_supported_conversions();

        $this->assertStringContainsString('pdf', $conversions);
        $this->assertStringContainsString('docx', $conversions);

        $this->reset_formats_cache();
    }

    /**
     * When unoconv reports no supported formats on standard output, the format list must
     * stay empty rather than falling back to standard error content.
     *
     * @covers \fileconverter_unoconv\converter::get_supported_conversions
     */
    public function test_fetch_supported_formats_ignores_stderr(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->reset_formats_cache();

        $CFG->pathtounoconv = $this->create_fake_unoconv_binary('', "[.pdf]\n[.docx]\n");

        $converter = $this->get_testable_mock();
        $conversions = $converter->get_supported_conversions();

        $this->assertSame('', $conversions);

        $this->reset_formats_cache();
    }

    /**
     * serve_test_document() must throw a moodle_exception with a meaningful error string
     * when the conversion fails, instead of allowing the underlying coding_exception or a
     * misleading generic error to surface to the user (MDL-63220).
     *
     * @covers \fileconverter_unoconv\converter::serve_test_document
     */
    public function test_serve_test_document_throws_on_failure(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->reset_formats_cache();
        $this->reset_requirements_met_cache();

        // No unoconv path configured, so requirements are not met and the conversion fails.
        $CFG->pathtounoconv = '';

        $converter = $this->get_testable_mock();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('err_unoconvrequirements', 'fileconverter_unoconv'));

        try {
            $converter->serve_test_document();
        } finally {
            $this->reset_requirements_met_cache();
        }
    }
}
