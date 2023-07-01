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
     * @param array $stops An array of two elements: first is the color. Second is the position of the stop, as a percentage of the image width.
     * @param int $angle The angle of the gradient in degrees.
     * @return string The generated image as a base64 data URL.
     */
    public static function generate_gradient(int $width, int $height, array $stops, int $angle): string {
        $image = imagecreatetruecolor($width, $height);

        $centerX = $width / 2;
        $centerY = $height / 2;
        $radius = sqrt($width * $width + $height * $height) / 2;
        $angleRad = deg2rad($angle);
        $startX = $centerX + $radius * cos($angleRad);
        $startY = $centerY + $radius * sin($angleRad);
        $endX = $centerX - $radius * cos($angleRad);
        $endY = $centerY - $radius * sin($angleRad);
        $lineLength = sqrt(($endX - $startX) * ($endX - $startX) + ($endY - $startY) * ($endY - $startY));

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $position = (($x - $startX) * ($endX - $startX) + ($y - $startY) * ($endY - $startY)) / ($lineLength * $lineLength);
                $position = max(0, min(1, $position));

                $prevStop = null;
                $nextStop = null;
                foreach ($stops as $stop) {
                    if ($stop['position'] <= $position) {
                        $prevStop = $stop;
                    }
                    if ($stop['position'] >= $position) {
                        $nextStop = $stop;
                        break;
                    }
                }

                // Calculate the relative position of the pixel between the two color stops.
                $distance = $nextStop['position'] - $prevStop['position'];
                $relativePosition = $distance != 0 ? ($position - $prevStop['position']) / $distance : 0;

                $red = (1 - $relativePosition) * $prevStop['color'][0] + $relativePosition * $nextStop['color'][0];
                $green = (1 - $relativePosition) * $prevStop['color'][1] + $relativePosition * $nextStop['color'][1];
                $blue = (1 - $relativePosition) * $prevStop['color'][2] + $relativePosition * $nextStop['color'][2];

                $color = imagecolorallocate($image, $red, $green, $blue);
                imagesetpixel($image, $x, $y, $color);
            }
        }

        ob_start();
        imagepng($image);
        $data = ob_get_clean();
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
        mt_srand($seed);

        // Determine the number of color stops.
        $numStops = mt_rand(2, 6);

        // Set a minimum distance between the stops.
        // You can adjust this value to test and tune it.
        $minDistance = mt_rand(0.2 * 100, 0.5 * 100) / 100;
        // Create the color stops
        $stops = [];
        for ($i = 0; $i < $numStops; $i++) {
            // Generate a random hexadecimal color code and convert it to RGB.
            $color = self::hex_to_rgb(sprintf('#%06X', mt_rand(0, 0xFFFFFF)));

            // Generate a random position for the color stop.
            // To ensure the minimum distance between the stops,
            // the position is calculated as the minimum distance times the current index.
            $position = $minDistance * $i;

            $stops[] = [
                    'color' => $color,
                    'position' => $position
            ];
        }

        // Make sure the first stop is at position 0 and the last stop is at position 1.
        $stops[0]['position'] = 0;
        $stops[$numStops - 1]['position'] = 1;

        // Generate a random angle between 0 and 180 degrees.
        $angle = mt_rand(0, 180);

        // Generate the gradient.
        return self::generate_gradient($width, $height, $stops, $angle);
    }


}

