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

namespace core\pattern\noise;

/**
 * Simplex based noise and pattern generator.
 *
 * @package   core_pattern
 * @copyright Matt Porritt <matt.porritt@catalyst-au.net>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class simplex {
    /**
     * The gradient vectors array contains the 12 gradient vectors for 2D simplex noise.
     * These vectors are the midpoints of the vertices of a cube.
     * This provides a better distribution of vectors than using the corners of a cube,
     * resulting in smoother noise.
     */
    private array $gradientvectors = [
            [1,1,0],[-1,1,0],[1,-1,0],[-1,-1,0],
            [1,0,1],[-1,0,1],[1,0,-1],[-1,0,-1],
            [0,1,1],[0,-1,1],[0,1,-1],[0,-1,-1]
    ];

    /**
     * The permutation array is used in the noise generation process to introduce randomness.
     * It contains a shuffled sequence of integers from 0 to 255.
     * This array, when used with bitwise AND operation, helps in selecting the gradient vectors.
     */
    private array $permutationarray = [];

    /**
     * The doubled permutation array is a copy of permutation array but with twice the size.
     * This is used to avoid modulo operation when cycling through the permutation array.
     * It contributes to the deterministic yet random nature of the noise.
     */
    private array $doubledpermutationarray = [];

    private int $width;
    private int $height;
    private float $scale;
    private float $warp;

    /**
     * Constructor for the SimplexNoise class.
     * Initializes the permutation array with a shuffled sequence of integers from 0 to 255,
     * using a provided seed value.
     * The doubled permutation array is then formed by duplicating the permutation array.
     *
     * @param int $seed Seed value for initializing the permutation array.
     */
    private function __construct($seed) {
        mt_srand($seed);
        $this->permutationarray = range(0, 255);
        shuffle($this->permutationarray);
        $this->doubledpermutationarray = array_merge($this->permutationarray, $this->permutationarray);
    }

    // Factory method.
    public static function create (
            $seed,
            $width,
            $height,
            $scale = null,
            $warp = null) {
        // Make the class.
        $simplex = new self($seed);

        // Set required values.
        $simplex->set_property('width', $width);
        $simplex->set_property('height', $height);

        // Set the properties with random values if they are not set.
        $simplex->set_property('scale', $scale ?? mt_rand(0.001 * 1000, 0.009 * 1000) /1000);
        $simplex->set_property('warp', $warp ?? mt_rand(0.1 * 10 , 0.9 * 10) / 10);

        return $simplex;
    }

    // Generic property setter
    public function set_property($property, $value) {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        } else {
            // Optionally, throw an exception or handle the error in some way.
            throw new \InvalidArgumentException("The property '{$property}' does not exist in class " . get_class($this));
        }
    }

    /**
     * Calculates the dot product of a gradient vector and a 2D distance vector.
     *
     * The dot product is a scalar value that represents the cosine of the angle between
     * the two vectors. It is used in the Simplex Noise algorithm to weight the contribution
     * of each corner's gradient to the final noise value.
     *
     * @param array $g Gradient vector.
     * @param float $x X component of the distance vector.
     * @param float $y Y component of the distance vector.
     * @return float The dot product of the input vectors.
     */
    private function dot($g, $x, $y): float {
        return ($g[0] * $x) + ($g[1] * $y);
    }

    /**
     * Generates a simplex noise value for a given 2D point.
     *
     * The function calculates the contribution from the three corners of the simplex cell
     * in which the input point falls. The resulting noise value is a sum of those contributions,
     * each of which is a product of a gradient value and a radial basis function value.
     *
     * @param float $xin The x coordinate of the input point.
     * @param float $yin The y coordinate of the input point.
     * @return float The simplex noise value at the input point.
     */
    public function noise2D($xin, $yin) {
        $n0 = $n1 = $n2 = 0;

        // Calculate the skew factors for the input point.
        $F2 = 0.5*(sqrt(3.0) - 1.0);
        $s = ($xin + $yin) * $F2;

        // Find the integer coordinates of the simplex cell in which the point falls.
        $i = floor($xin + $s);
        $j = floor($yin + $s);

        // Calculate the unskew factors.
        $G2 = (3.0 - sqrt(3.0)) / 6.0;
        $t = ($i + $j) * $G2;

        // Unskew the cell origin back to (x, y) space.
        $X0 = $i - $t;
        $Y0 = $j - $t;

        // The x, y distances from the cell origin.
        $x0 = $xin - $X0;
        $y0 = $yin - $Y0;

        // Determine the simplex cell in which the point falls.
        $i1 = $x0 > $y0 ? 1 : 0;
        $j1 = $x0 > $y0 ? 0 : 1;

        // Offsets for middle corner in (x, y) unskewed coords.
        $x1 = $x0 - $i1 + $G2;
        $y1 = $y0 - $j1 + $G2;

        // Offsets for last corner in (x, y) unskewed coords.
        $x2 = $x0 - 1.0 + 2.0 * $G2;
        $y2 = $y0 - 1.0 + 2.0 * $G2;

        // Calculate the gradient indices.
        $ii = $i & 255;
        $jj = $j & 255;
        $gi0 = $this->doubledpermutationarray[$ii + $this->doubledpermutationarray[$jj]] % 12;
        $gi1 = $this->doubledpermutationarray[$ii + $i1 + $this->doubledpermutationarray[$jj + $j1]] % 12;
        $gi2 = $this->doubledpermutationarray[$ii + 1 + $this->doubledpermutationarray[$jj + 1]] % 12;

        // Calculate the noise contributions from the three corners.
        $t0 = 0.5 - $x0*$x0 - $y0*$y0;
        if ($t0 < 0) $n0 = 0.0;
        else {
            $t0 *= $t0;
            $n0 = $t0 * $t0 * $this->dot($this->gradientvectors[$gi0], $x0, $y0);
        }
        $t1 = 0.5 - $x1*$x1 - $y1*$y1;
        if ($t1 < 0) $n1 = 0.0;
        else {
            $t1 *= $t1;
            $n1 = $t1 * $t1 * $this->dot($this->gradientvectors[$gi1], $x1, $y1);
        }
        $t2 = 0.5 - $x2*$x2 - $y2*$y2;
        if ($t2 < 0) $n2 = 0.0;
        else {
            $t2 *= $t2;
            $n2 = $t2 * $t2 * $this->dot($this->gradientvectors[$gi2], $x2, $y2);
        }

        // Add the contributions from each corner to get the final noise value.
        // The result is scaled to return values in the interval [-1, 1].
        return 70.0 * ($n0 + $n1 + $n2);
    }


    /**
     * This method implements Fractal Brownian Motion (FBM) which is a way of combining several octaves of
     * simplex noise to generate noise patterns with more complex, natural appearance.
     *
     * In FBM, each octave of noise is a scaled and warped version of the main noise function. Each octave is added to
     * the total, but with each iteration the frequency is increased by the lacunarity and the amplitude is decreased
     * by the gain, creating variation at different scales. The final noise value is normalized by dividing it by the
     * maximum possible amplitude.
     *
     * @param float $x The x coordinate.
     * @param float $y The y coordinate.
     * @param int $octaves The number of octaves, representing layers or iterations of noise.
     * @param float $lacunarity A frequency multiplier for each successive octave of noise. A lacunarity > 1 results in
     * increased frequency with each octave, adding detail.
     * @param float $gain An amplitude scaler for each successive octave of noise. A gain < 1 results in decreased
     * amplitude with each octave, reducing the impact of high frequency detail.
     * @return float The Fractal Brownian Motion (FBM) value at the specified point.
     */
    function fractal_brownian_motion(float $x, float $y, int $octaves, float $lacunarity, float $gain): float {
        $total = 0;
        $frequency = 1;
        $amplitude = 1;
        $maxAmplitude = 0;

        // Loop through each octave
        for ($i = 0; $i < $octaves; $i++) {
            // Generate noise for this octave, scaled by the current amplitude
            $total += $this->noise2D($x * $frequency, $y * $frequency) * $amplitude;

            // Keep track of the maximum possible amplitude for normalization later
            $maxAmplitude += $amplitude;

            // Increase frequency and decrease amplitude for the next octave
            $frequency *= $lacunarity;
            $amplitude *= $gain;
        }

        // Normalize the result to the range [0, 1]
        return $total / $maxAmplitude;
    }

    /**
     * The `domain_warp` method warps the input space (domain) before computing the noise value.
     *
     * In domain warping, we add extra complexity to the output by moving the input coordinates according to some
     * function (in this case, Fractal Brownian Motion), before computing the final noise value. This results in
     * distorted or "warped" patterns, adding more variety and detail to the noise.
     *
     * @param float $x The x coordinate.
     * @param float $y The y coordinate.
     * @param int $octaves The number of octaves, representing layers or iterations of noise.
     * @param float $lacunarity A frequency multiplier for each successive octave of noise. A lacunarity > 1 results in
     * increased frequency with each octave, adding detail.
     * @param float $gain An amplitude scaler for each successive octave of noise. A gain < 1 results in decreased
     * amplitude with each octave, reducing the impact of high frequency detail.
     * @param float $warp The factor by which to warp the domain.
     * @return float The domain-warped noise value at the specified point.
     */
    public function domain_warp(float $x, float $y, int $octaves, float $lacunarity, float $gain, float $warp): float {
        // Calculate displacement values using Fractal Brownian Motion (FBM)
        $dx = $this->fractal_brownian_motion($x + 0.1, $y, $octaves, $lacunarity, $gain) * $warp;
        $dy = $this->fractal_brownian_motion($x, $y + 0.1, $octaves, $lacunarity, $gain) * $warp;

        // Compute the final noise value at the warped coordinates
        return $this->fractal_brownian_motion($x + $dx, $y + $dy, $octaves, $lacunarity, $gain);
    }

       /**
        * Generates a noise pattern and an alpha map for the pattern.
        *
        * This function first generates a grayscale noise pattern by applying domain warping to each pixel in the pattern.
        * The grayscale value of each pixel is determined by the resulting noise value. The grayscale values are then
        * adjusted to enhance contrast and optionally inverted.
        *
        * At the same time, an alpha map is generated where the transparency of each pixel is adjusted based on its
        * distance to the closest edge of the image, creating a fade-out effect at the edges.
        *
        * This function returns both the noise pattern and the alpha map as GD image resources.
        *
        * @param int $octaves The number of octaves to use for the domain warping and noise generation. Higher numbers
        * result in more detail in the noise.
        * @param float $lacunarity The lacunarity to use for the domain warping and noise generation. Higher numbers
        * result in more fine-grained detail in the noise.
        * @param float $gain The gain to use for the domain warping and noise generation. Lower numbers result in the
        * higher frequency detail being less pronounced.
        * @param float $blackWhiteRatio The ratio of black to white in the final grayscale noise pattern. A higher ratio
        * results in a lighter image.
        * @param bool $invertColors Whether to invert the grayscale values in the final noise pattern. If true, lighter
        * grayscale values become darker and vice versa.
        * @param float $edgeFadeWidth The width of the fade-out effect at the edges of the image, expressed as a proportion
        * of the image width.
        * @return array An array of two GD image resources. The first element is the noise pattern, and the second element
        * is the alpha map for the pattern.
        */
    public function generate_pattern_and_map($octaves = 4, $lacunarity = 2.5, $gain = 0.4, $blackWhiteRatio = 0.6, $invertColors = true, $edgeFadeWidth = 0.001) {
        $width = $this->width;
        $height = $this->height;
        $scale = $this->scale;
        $warp = $this->warp;

        // Create a new true color image
        $image = imagecreatetruecolor($width, $height);

        // Create a new true color image for the alpha map
        $alphaMap = imagecreatetruecolor($width, $height);

        // Set the blending mode for the image to allow alpha channels
        imagesavealpha($image, true);

        // Calculate the number of pixels for the fade at the edge of the image
        $edgeFadePixels = $width * $edgeFadeWidth;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                // Generate a noise value using simplex noise algorithm with domain warp and fbm.
                $noise = $this->domain_warp($x * $scale, $y * $scale, $octaves, $lacunarity, $gain, $warp);

                // Normalize the noise value to the range [0, 1]
                $noise = ($noise + 1) / 2;

                // Calculate the grayscale value based on the noise value
                if ($noise > $blackWhiteRatio) {
                    $grayValue = 200 + ($noise - $blackWhiteRatio) / (1 - $blackWhiteRatio) * 55;
                } else {
                    $grayValue = 200 * ($noise / $blackWhiteRatio);
                }

                // Ensure the grayscale value is within the range [0, 255]
                $grayValue = max(0, min(255, $grayValue));

                // Invert the grayscale value if invertColors is true
                if ($invertColors) {
                    $grayValue = 255 - $grayValue;
                }

                // Calculate the distance to the closest edge of the image
                $distToEdge = min($x, $y, $width - $x, $height - $y);

                // Calculate the alpha value for the edge fade effect
                $alpha = 0;
                if ($distToEdge < $edgeFadePixels) {
                    $alpha = round((1 - $distToEdge / $edgeFadePixels) * 127);
                }

                // Set the grayscale value for the current pixel in the image
                $color = imagecolorallocate($image, $grayValue, $grayValue, $grayValue);
                imagesetpixel($image, $x, $y, $color);

                // Set the alpha value for the current pixel in the alpha map
                $alphaColor = imagecolorallocate($alphaMap, $alpha, $alpha, $alpha);
                imagesetpixel($alphaMap, $x, $y, $alphaColor);
            }
        }

        // Return the image and the alpha map
        return [$image, $alphaMap];
    }

    /**
     * Generates a colored image from a grayscale image and an alpha map.
     *
     * The output image is colored based on the grayscale values of the input
     * image, and the transparency of each pixel is determined by the corresponding
     * pixel in the alpha map. Foreground and background colors are blended
     * according to the grayscale values.
     *
     * @param \GdImage $grayImage An image resource with grayscale data. Its size determines the size of the output image.
     * @param \GdImage $alphaMap An image resource with alpha channel data. Must be the same size as $grayImage.
     * @param array $foregroundColor An RGB array for the color to use where $grayImage is black.
     * @param array $backgroundColor An RGB array for the color to use where $grayImage is white.
     * @param bool $invertalpha If true, inverts the alpha values from the alpha map (optional, default is true).
     *
     * @return string The generated image, encoded as a PNG data URL.
     */
    public function generate_colored_image(\GdImage $grayImage, \GDimage $alphaMap, array $foregroundColor, array $backgroundColor, bool $invertalpha = true): string {
        $width = imagesx($grayImage);
        $height = imagesy($grayImage);

        $outputImage = imagecreatetruecolor($width, $height);
        imagesavealpha($outputImage, true);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                // Get grayscale and alpha value
                $gray = imagecolorat($grayImage, $x, $y) & 0xFF;

                if ($invertalpha) {
                    $alpha = 127 - (imagecolorat($alphaMap, $x, $y) & 0xFF); // invert alpha
                } else {
                    $alpha = imagecolorat($alphaMap, $x, $y) & 0xFF;
                }

                // Calculate final color based on grayscale and alpha
                $finalColor = [
                        (($foregroundColor[0] * $gray) + ($backgroundColor[0] * (255 - $gray))) / 255,
                        (($foregroundColor[1] * $gray) + ($backgroundColor[1] * (255 - $gray))) / 255,
                        (($foregroundColor[2] * $gray) + ($backgroundColor[2] * (255 - $gray))) / 255,
                ];

                // Set pixel color in the output image
                $color = imagecolorallocatealpha($outputImage, $finalColor[0], $finalColor[1], $finalColor[2], 127 - $alpha);
                imagesetpixel($outputImage, $x, $y, $color);
            }
        }

        ob_start();
        imagepng($outputImage);
        $data = ob_get_clean();
        return 'data:image/png;base64,' . base64_encode($data);
    }

}
