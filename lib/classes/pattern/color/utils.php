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

namespace core\pattern\color;

/**
 * Util class with helper functions for pattern generation.
 *
 * @package   core_pattern
 * @copyright Matt Porritt <matt.porritt@catalyst-au.net>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils {
    /**
     * Converts a hexadecimal color code to an RGB array.
     *
     * @param string $hex The hexadecimal color code.
     * @return array The RGB values corresponding to the input color code.
     */
    public static function hex_to_rgb(string $hex): array {
        // Remove the hash symbol if it's present
        $hex = str_replace("#", "", $hex);

        // If the color code is in the three-character format, expand it to six characters.
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            // Convert the six-character color code to RGB values.
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return array($r, $g, $b);
    }

    /**
     * Converts an RGB color to HSL.
     *
     * This method takes the red, green, and blue components of a color as arguments,
     * and returns the hue, saturation, and lightness components of the color in the HSL color model.
     *
     * @param int $r The red component of the color, between 0 and 255.
     * @param int $g The green component of the color, between 0 and 255.
     * @param int $b The blue component of the color, between 0 and 255.
     * @return array The hue, saturation, and lightness components of the color, each between 0 and 1.
     */
    public static function rgb_to_hsl(int $r, int $g, int $b): array {
        // Normalize the RGB values to the range 0-1.
        $r /= 255;
        $g /= 255;
        $b /= 255;

        // Compute the maximum and minimum of the RGB values.
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);

        // Compute the lightness as the average of the max and min.
        $l = ($max + $min) / 2;

        // If max and min are equal, the color is achromatic (grayscale).
        if ($max == $min) {
            $h = $s = 0; // achromatic
        } else {
            // Compute the difference between the max and min.
            $diff = $max - $min;

            // Compute the saturation
            $s = $l > 0.5 ? $diff / (2 - $max - $min) : $diff / ($max + $min);

            // Compute the hue based on which color channel is dominant.
            switch ($max) {
                case $r:
                    $h = ($g - $b) / $diff + ($g < $b ? 6 : 0);
                    break;
                case $g:
                    $h = ($b - $r) / $diff + 2;
                    break;
                case $b:
                    $h = ($r - $g) / $diff + 4;
                    break;
            }

            // Normalize the hue to the range 0-1.
            $h /= 6;
        }

        // Return the HSL values.
        return array($h, $s, $l);
    }

    /**
     * Converts an HSL color to RGB.
     *
     * This method takes the hue, saturation, and lightness components of a color as arguments,
     * and returns the red, green, and blue components of the color in the RGB color model.
     *
     * @param float $h The hue component of the color, between 0 and 1.
     * @param float $s The saturation component of the color, between 0 and 1.
     * @param float $l The lightness component of the color, between 0 and 1.
     * @return array The red, green, and blue components of the color, each between 0 and 255.
     */
    public static function hsl_to_rgb(float $h, float $s, float $l): array{
        // Start by assuming the color is a shade of gray.
        $r = $g = $b = $l;

        // Compute the value (brightness).
        $v = ($l <= 0.5) ? ($l * (1.0 + $s)) : ($l + $s - $l * $s);

        if ($v > 0) {
            // Compute some intermediate values.
            $m = $l + $l - $v;
            $sv = ($v - $m ) / $v;
            $h *= 6.0;
            $sextant = floor($h);
            $fract = $h - $sextant;
            $vsf = $v * $sv * $fract;
            $mid1 = $m + $vsf;
            $mid2 = $v - $vsf;

            // Adjust the RGB values based on the hue sector.
            switch ($sextant)
            {
                case 0:
                    $r = $v;
                    $g = $mid1;
                    $b = $m;
                    break;
                case 1:
                    $r = $mid2;
                    $g = $v;
                    $b = $m;
                    break;
                case 2:
                    $r = $m;
                    $g = $v;
                    $b = $mid1;
                    break;
                case 3:
                    $r = $m;
                    $g = $mid2;
                    $b = $v;
                    break;
                case 4:
                    $r = $mid1;
                    $g = $m;
                    $b = $v;
                    break;
                case 5:
                    $r = $v;
                    $g = $m;
                    $b = $mid2;
                    break;
            }
        }
        // Convert the RGB values to the range 0-255 and return them.
        return array($r * 255, $g * 255, $b * 255);
    }

    /**
     * Calculates the complementary color of a given RGB color.
     *
     * This method takes an RGB color as argument,
     * converts it to the HSL color space,
     * adjusts the hue by adding 0.5 (equivalent to rotating the hue by 180 degrees on the color wheel),
     * then converts the color back to the RGB color space.
     *
     * @param array $color The RGB color, as an array of three integers between 0 and 255.
     * @return array The RGB color complementary to the input, as an array of three integers between 0 and 255.
     */
    public static function complementary_color(array $color): array {
        // Decompose the input color into its red, green, and blue components
        list($r, $g, $b) = $color;
        // Convert the RGB color to the HSL color space
        list($h, $s, $l) = self::rgb_to_hsl($r, $g, $b);
        // Add 0.5 to the hue (equivalent to rotating the hue by 180 degrees on the color wheel)
        $h = ($h + 0.5) % 1;
        // Convert the color back to the RGB color space and return it
        return self::hsl_to_rgb($h, $s, $l);
    }

    /**
     * Generates a vibrant RGB color with a bias towards red and blue.
     *
     * This method uses the HSL color space to create a vibrant color with high saturation.
     * The hue is chosen randomly but is biased towards red (0-60 degrees) and blue (180-240 degrees),
     * which results in a color that is likely to be some shade of red, pink, blue, or purple.
     * The saturation and lightness are also chosen randomly to provide a range of vibrancy and shade.
     *
     * @return array The generated RGB color, as an array of three integers between 0 and 255.
     */
    public static function generate_preferred_color(): array {
        // Choose a hue with a bias towards red (0-60 degrees) and blue (180-240 degrees)
        $h = (mt_rand(0,1) > 0.5) ? mt_rand(0, 60) / 360 : mt_rand(180, 240) / 360;
        // Choose a high saturation to ensure the color is vibrant
        $s = mt_rand(60, 90) / 100;
        // Choose a random lightness to get a range of shades
        $l = mt_rand(30, 70) / 100;
        // Convert the color from the HSL color space to the RGB color space and return it
        return self::hsl_to_rgb($h, $s, $l);
    }
}
