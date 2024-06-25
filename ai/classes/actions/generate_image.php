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

namespace core_ai\actions;

/**
 * Generate images class.
 *
 * @package    core_ai
 * @copyright  2024 Matt Porritt <matt.porritt@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_image extends base{
    /** @var int The context id action was created in. */
    protected int $contextid;

    /** @var string The prompt text used to generate the image */
    protected string $prompttext;

    /** @var string The aspect ratio of the generated image */
    protected string $aspectratio;

    /** @var string The quality of the generated image */
    protected string $quality;

    /** @var string The visual style of the generated image */
    protected string $style;

    /**
     * Configure the action.
     * It’s also responsible for performing any other setup tasks,
     * such as getting additional data from the database etc.
     *
     * @param string $contextid The context id.
     * @param string $prompttext The prompt text used to generate the image.
     * @param string $aspectratio The aspect ratio of the generated image.
     * @param string $quality The quality of the generated image.
     * @param string $style The visual style of the generated image.
     * @return void.
     */
    public function configure(
        string $contextid,
        string $prompttext,
        string $aspectratio,
        string $quality,
        string $style
    ): void {
        $this->contextid = $contextid;
        $this->prompttext = $prompttext;
        $this->aspectratio = $aspectratio;
        $this->quality = $quality;
        $this->style = $style;
    }
}
