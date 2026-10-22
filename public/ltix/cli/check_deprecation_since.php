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
 * Fail if any LTI deprecation claims to have started before the current branch.
 *
 * A deprecation takes effect in the release that ships it, so a deprecation added on this branch cannot have a "since" older than
 * the branch's own version. Checks, in mod_lti and core_ltix:
 * - every #[\core\attribute\deprecated(...)] "since" value,
 * - every "@deprecated since Moodle X.Y" docblock that sits on such an attribute, and
 * - every "since X.Y" statement in the LTI upgrade notes (these only ever hold notes for the unreleased version).
 *
 * Legacy "@deprecated since Moodle X.Y" docblocks without an attribute (for example 3.7 or 4.x) record real, older deprecations
 * and are deliberately not checked.
 *
 * Needs no Moodle site or database, so it can run in CI before an install:
 *   php public/ltix/cli/check_deprecation_since.php [--branch=6.0]
 *
 * @package    core_ltix
 * @copyright  2026 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$publicdir = dirname(__DIR__, 2);
$rootdir = dirname($publicdir);

// Work out the floor from the branch this checkout is on, unless one was given.
$branch = null;
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--branch=(\d+\.\d+)$/', $arg, $matches)) {
        $branch = $matches[1];
    }
}
if ($branch === null) {
    $versionfile = file_get_contents($publicdir . '/version.php');
    if (!preg_match('/\$branch\s*=\s*\'(\d+)\'/', $versionfile, $matches)) {
        fwrite(STDERR, "Cannot read \$branch from public/version.php\n");
        exit(2);
    }
    $branch = intdiv((int) $matches[1], 100) . '.' . ((int) $matches[1] % 100);
}

$violations = [];
$checked = 0;

// Record a violation when a claimed "since" version is older than the branch.
$check = function (string $version, string $file, int $line, string $what) use (&$violations, &$checked, $branch): void {
    $checked++;
    if (version_compare($version, $branch, '<')) {
        $violations[] = "$file:$line: $what says since $version, older than the current branch $branch";
    }
};

$ltifiles = [];
foreach ([$publicdir . '/mod/lti', $publicdir . '/ltix'] as $dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $ltifiles[] = $file->getPathname();
        }
    }
}
sort($ltifiles);

foreach ($ltifiles as $path) {
    $relative = substr($path, strlen($rootdir) + 1);
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $index => $text) {
        if (preg_match('/\bsince:\s*[\'"](\d+\.\d+)[\'"]/', $text, $matches)) {
            $check($matches[1], $relative, $index + 1, 'deprecation attribute');
        }
        if (preg_match('/@deprecated since (?:Moodle )?(\d+\.\d+)/', $text, $matches)) {
            // Only docblocks that sit on a deprecation attribute are checked. The attribute follows within a few lines.
            $window = implode("\n", array_slice($lines, $index + 1, 12));
            if (strpos($window, 'core\attribute\deprecated') !== false) {
                $check($matches[1], $relative, $index + 1, 'docblock');
            }
        }
    }
}

$notes = glob($rootdir . '/.upgradenotes/*.yml') ?: [];
foreach ($notes as $path) {
    $content = file_get_contents($path);
    if (!preg_match('/ltix|mod_lti|core_ltix|ltiservice|ltixservice/', $content)) {
        continue;
    }
    $relative = substr($path, strlen($rootdir) + 1);
    foreach (file($path, FILE_IGNORE_NEW_LINES) as $index => $text) {
        if (preg_match_all('/since (?:Moodle )?(\d+\.\d+)/i', $text, $matches)) {
            foreach ($matches[1] as $version) {
                $check($version, $relative, $index + 1, 'upgrade note');
            }
        }
    }
}

if ($violations) {
    echo "LTI deprecations older than the current branch ($branch):\n";
    echo implode("\n", $violations) . "\n";
    exit(1);
}

echo "OK: $checked LTI deprecation versions checked, none older than $branch.\n";
exit(0);
