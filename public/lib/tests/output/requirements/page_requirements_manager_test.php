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

namespace core\output\requirements;

/**
 * Tests for the page_requirements_manager ESM import map output.
 *
 * @package    core
 * @category   test
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(page_requirements_manager::class)]
final class page_requirements_manager_test extends \advanced_testcase {
    /**
     * Extract the modulepreload hrefs and the importmap script's decoded imports from a
     * get_import_map() output string.
     *
     * @param string $output
     * @return array{preloads: string[], imports: array<string, string>}
     */
    protected function parse_import_map_output(string $output): array {
        preg_match_all('#<link rel="modulepreload" href="([^"]*)"\s*/?>#', $output, $linkmatches);

        preg_match('#<script type="importmap">(.*?)</script>#s', $output, $scriptmatches);
        $this->assertNotEmpty($scriptmatches, 'Expected an importmap script tag in the output.');
        $importmapdata = json_decode($scriptmatches[1], true);
        $this->assertIsArray($importmapdata);

        return [
            'preloads' => $linkmatches[1],
            'imports' => $importmapdata['imports'],
        ];
    }

    /**
     * get_import_map() emits a modulepreload link, after the importmap script tag, for every
     * concrete (non-prefix) import map entry, so the browser can start fetching those
     * foundational ESM dependencies without waiting to discover them via import-graph parsing.
     * Directory-prefix entries are excluded, since there is no single file to preload for those.
     */
    public function test_get_import_map_preloads_exactly_the_concrete_entries(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->requires->get_import_map();
        ['preloads' => $preloads, 'imports' => $imports] = $this->parse_import_map_output($output);

        $expected = [];
        foreach ($imports as $specifier => $url) {
            if (str_ends_with($specifier, '/') || str_contains($specifier, '/theme-')) {
                continue;
            }
            $expected[] = $url;
        }

        $this->assertNotEmpty($expected, 'Expected at least one concrete import map entry to preload.');
        sort($expected);
        $actual = $preloads;
        sort($actual);
        $this->assertEquals($expected, $actual);
    }

    /**
     * The importmap script tag must be emitted before any modulepreload link. Browsers only
     * honour an import map for bare specifier resolution ("react", "@moodlehq/design-system",
     * ...) if it is registered before the first module-related resource - including a
     * modulepreload link - is processed. Emitting a preload link first silently breaks specifier
     * resolution for every module script on the page.
     */
    public function test_importmap_script_precedes_modulepreload_links(): void {
        global $PAGE;

        $this->resetAfterTest();

        $output = $PAGE->requires->get_import_map();

        $scriptpos = strpos($output, '<script type="importmap">');
        $linkpos = strpos($output, '<link rel="modulepreload"');
        $this->assertNotFalse($scriptpos, 'Expected the importmap script tag in the output.');
        $this->assertNotFalse($linkpos, 'Expected at least one modulepreload link in the output.');
        $this->assertLessThan($linkpos, $scriptpos, 'The importmap script tag must precede modulepreload links.');
    }
}
