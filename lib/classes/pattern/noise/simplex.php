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
 * Simplex noise generator.
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

    /**
     * Constructor for the SimplexNoise class.
     * Initializes the permutation array with a shuffled sequence of integers from 0 to 255,
     * using a provided seed value.
     * The doubled permutation array is then formed by duplicating the permutation array.
     *
     * @param int $seed Seed value for initializing the permutation array.
     */
    public function __construct($seed) {
        srand($seed);
        $this->permutationarray = range(0, 255);
        shuffle($this->permutationarray);
        $this->doubledpermutationarray = array_merge($this->permutationarray, $this->permutationarray);
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

}

