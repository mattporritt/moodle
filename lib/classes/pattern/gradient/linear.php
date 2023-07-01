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
    public static function generate_gradient(int $width, int $height, string $color1, string $color2, int $angle): string {
        // Create a new true color image.
        $image = imagecreatetruecolor($width, $height);

        // Convert the hexadecimal color codes to RGB arrays.
        $color1 = self::hex_to_rgb($color1);
        $color2 = self::hex_to_rgb($color2);

        // Calculate the center of the image.
        $centerX = $width / 2;
        $centerY = $height / 2;

        // Calculate the radius (half of the diagonal of the image).
        $radius = sqrt($width * $width + $height * $height) / 2;

        // Convert the angle from degrees to radians.
        $angleRad = deg2rad($angle);

        // Calculate the starting and ending points of the gradient line.
        $startX = $centerX + $radius * cos($angleRad);
        $startY = $centerY + $radius * sin($angleRad);
        $endX = $centerX - $radius * cos($angleRad);
        $endY = $centerY - $radius * sin($angleRad);

        // Calculate the length of the gradient line.
        $lineLength = sqrt(($endX - $startX) * ($endX - $startX) + ($endY - $startY) * ($endY - $startY));

        // Generate the gradient
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                // Calculate the position of the pixel along the gradient line.
                $position = (($x - $startX) * ($endX - $startX) + ($y - $startY) * ($endY - $startY)) / ($lineLength * $lineLength);

                // Clamp the position value between 0 and 1.
                $position = max(0, min(1, $position));

                // Calculate the color of the pixel
                $red = (1 - $position) * $color1[0] + $position * $color2[0];
                $green = (1 - $position) * $color1[1] + $position * $color2[1];
                $blue = (1 - $position) * $color1[2] + $position * $color2[2];

                $color = imagecolorallocate($image, $red, $green, $blue);

                // Set the pixel color at the current position.
                imagesetpixel($image, $x, $y, $color);
            }
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

        // Generate a random angle between 0 and 180 degrees.
        $angle = mt_rand(0, 180);

        // Generate the gradient
        return self::generate_gradient($width, $height, $color1, $color2, $angle);
    }
}

