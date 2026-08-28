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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Boost theme related step definitions.
 *
 * @package    theme_boost
 * @category   test
 * @copyright  2026 A K M Safat Shahin <safatshahin@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_theme_boost extends behat_base {
    /**
     * Check that widening the course index spends the extra width on the content column only.
     *
     * Measures the drawer and content column edges at the smallest and largest
     * course index widths, with transitions suppressed, and asserts that the
     * drawer's outer edge and the content column's right edge do not move
     * while the shared boundary between them does. This pins the resize
     * geometry contract: the drawer must not stretch into the left gutter and
     * the blocks drawer side of the page must be unaffected.
     *
     * @Then /^widening the course index should only take space from the content column$/
     */
    public function widening_the_course_index_should_only_take_space_from_the_content_column(): void {
        $min = \theme_boost\courseindex_resizer::MIN_WIDTH;
        $max = \theme_boost\courseindex_resizer::MAX_WIDTH;
        $script = <<<JS
            return (() => {
                const drawer = document.getElementById('theme_boost-drawers-courseindex');
                const main = document.querySelector('.main-inner');
                const edges = () => ({
                    drawerleft: drawer.getBoundingClientRect().left,
                    drawerright: drawer.getBoundingClientRect().right,
                    mainleft: main.getBoundingClientRect().left,
                    mainright: main.getBoundingClientRect().right,
                });
                const previous = document.body.style.getPropertyValue('--drawer-index-width');
                // The drawer-resizing class suppresses the width transitions,
                // so the reads below see the final layout synchronously.
                document.body.classList.add('drawer-resizing');
                document.body.style.setProperty('--drawer-index-width', '{$min}px');
                const small = edges();
                document.body.style.setProperty('--drawer-index-width', '{$max}px');
                const large = edges();
                if (previous) {
                    document.body.style.setProperty('--drawer-index-width', previous);
                } else {
                    document.body.style.removeProperty('--drawer-index-width');
                }
                document.body.classList.remove('drawer-resizing');
                return JSON.stringify({small, large});
            })();
JS;
        $result = json_decode($this->evaluate_script($script), true);
        if (!$result) {
            throw new ExpectationException('Could not measure the course index geometry.', $this->getSession());
        }

        $small = $result['small'];
        $large = $result['large'];
        // The difference between the largest and smallest allowed widths.
        $extra = $max - $min;

        if (abs($small['drawerleft'] - $large['drawerleft']) >= 1) {
            throw new ExpectationException(
                "The course index drawer outer edge moved when resized: {$small['drawerleft']} to {$large['drawerleft']}.",
                $this->getSession()
            );
        }
        if (abs($small['mainright'] - $large['mainright']) >= 1) {
            throw new ExpectationException(
                "The content column right edge moved when the course index was resized: " .
                    "{$small['mainright']} to {$large['mainright']}.",
                $this->getSession()
            );
        }
        if (abs(($large['drawerright'] - $small['drawerright']) - $extra) >= 1) {
            throw new ExpectationException(
                "The course index drawer inner edge did not grow by the full extra width: " .
                    "{$small['drawerright']} to {$large['drawerright']}.",
                $this->getSession()
            );
        }
        if (abs(($large['mainleft'] - $small['mainleft']) - $extra) >= 1) {
            throw new ExpectationException(
                "The content column did not give way by the full extra width: " .
                    "{$small['mainleft']} to {$large['mainleft']}.",
                $this->getSession()
            );
        }
    }
}
