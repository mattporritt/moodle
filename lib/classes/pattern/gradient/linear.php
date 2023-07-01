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
    /**
     * Converts a hexadecimal color code to an RGB array.
     *
     * @param string $hex The hexadecimal color code.
     * @return array The RGB values corresponding to the input color code.
     */
    public static function hex_to_rgb(string $hex): array {
        // Remove the hash symbol if it's present
        $hex = str_replace("#", "", $hex);

        // If the color code is in the three-character format, expand it to six characters
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            // Convert the six-character color code to RGB values
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return array($r, $g, $b);
    }

    /**
     * Generates a PNG image with a linear gradient and returns it as a base64 data URL.
     *
     * @param int $width The width of the image in pixels.
     * @param int $height The height of the image in pixels.
     * @param string $color1 The Hex value of the start color of the gradient.
     * @param string $color2 The Hex value of the end color of the gradient.
     * @return string The generated image as a base64 data URL.
     */
    public static function generate_gradient(int $width, int $height, string $color1, string $color2): string {
        // Create a new true color image.
        $image = imagecreatetruecolor($width, $height);

        // Convert the hexadecimal color codes to RGB arrays.
        $color1 = self::hex_to_rgb($color1);
        $color2 = self::hex_to_rgb($color2);

        // Generate the gradient.
        for ($i = 0; $i < $height; $i++) {
            // Calculate the alpha value (i.e., the position in the gradient).
            $alpha = $i / $height;

            // Calculate the RGB values for the current position in the gradient.
            // This is done by interpolating between the start and end colors based on the alpha value.
            $red = (1 - $alpha) * $color1[0] + $alpha * $color2[0];
            $green = (1 - $alpha) * $color1[1] + $alpha * $color2[1];
            $blue = (1 - $alpha) * $color1[2] + $alpha * $color2[2];

            // Allocate a color for the image.
            $color = imagecolorallocate($image, $red, $green, $blue);

            // Draw a line with the calculated color at the current position.
            imageline($image, 0, $i, $width, $i, $color);
        }

        // Start output buffering.
        ob_start();

        // Output the image to the buffer.
        imagepng($image);

        // Get the contents of the buffer.
        $data = ob_get_clean();

        // Return the image as a base64 data URL.
        return 'data:image/png;base64,' . base64_encode($data);
    }

    /**
     * Generates a PNG image with a linear gradient between two random colors,
     * determined by a seed, and returns it as a base64 data URL.
     *
     * @param int $width The width of the image in pixels.
     * @param int $height The height of the image in pixels.
     * @param int $seed The seed value for the random color generator.
     * @return string The generated image as a base64 data URL.
     */
    public static function generate_random_gradient(int $width, int $height, int $seed): string {
        // Initialize the random number generator with the given seed.
        mt_srand($seed);

        // Generate two random hexadecimal color codes.
        $color1 = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
        $color2 = sprintf('#%06X', mt_rand(0, 0xFFFFFF));

        // Generate the gradient.
        return self::generate_gradient($width, $height, $color1, $color2);
    }

}

