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

namespace core\pattern\gradient;

/**
 * Linear gradient generator.
 *
 * @package   core_pattern
 * @copyright Matt Porritt <matt.porritt@catalyst-au.net>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class linear {
    public static function generate_gradient($width, $height, $color1, $color2) {
        $image = imagecreatetruecolor($width, $height);

        $start_color = imagecolorallocate($image, $color1[0], $color1[1], $color1[2]);
        $end_color = imagecolorallocate($image, $color2[0], $color2[1], $color2[2]);

        for ($i = 0; $i < $height; $i++) {
            $alpha = $i / $height;
            $red = (1 - $alpha) * $color1[0] + $alpha * $color2[0];
            $green = (1 - $alpha) * $color1[1] + $alpha * $color2[1];
            $blue = (1 - $alpha) * $color1[2] + $alpha * $color2[2];
            $color = imagecolorallocate($image, $red, $green, $blue);
            imageline($image, 0, $i, $width, $i, $color);
        }

        // Convert the image to a data URL so it can be used as a placeholder
        ob_start();
        imagepng($image);
        $data = ob_get_clean();
        return 'data:image/png;base64,' . base64_encode($data);
    }

    public static function generate_random_gradient($width, $height, $seed) {
        mt_srand($seed);

        $color1 = [mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)];
        $color2 = [mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)];

        return self::generate_gradient($width, $height, $color1, $color2);
    }
}

