<?php

require_once('../config.php');


use core\pattern\noise\simplex;
use core\pattern\gradient\gradient;

function createNebulaPlaceholder($inputString) {
    $hash = md5($inputString); // Create a hash for determinism
    $seed = hexdec(substr($hash, 0, 8)); // Use the first 8 digits of the hash as the seed

    $width = 400; // Image width
    $height = 400; // Image height
    $scale = 0.01; // Scale factor for the noise function

    $noise = new simplex($seed);
    $image = imagecreatetruecolor($width, $height);
    imagesavealpha($image, true);

    // Generate the nebula pattern
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $value = ($noise->noise2D($x * $scale, $y * $scale) + 1) / 2; // Scale the noise value to [0, 1]

            // Create a gradient from black to your nebula color based on the noise value
            $r = 255 * $value;
            $g = 140 * $value;
            $b = 0;

            // Use imagecolorallocatealpha for a more cloud-like appearance, with varying transparency
            $color = imagecolorallocatealpha($image, $r, $g, $b, 127 * (1 - $value));
            imagesetpixel($image, $x, $y, $color);
        }
    }

    // Convert the image to a data URL so it can be used as a placeholder
    ob_start();
    imagepng($image);
    $data = ob_get_clean();
    return 'data:image/png;base64,' . base64_encode($data);
}

//echo '<img src="' . createNebulaPlaceholder('test') . '" />';
//echo '<img src="' . createNebulaPlaceholder('foobarcccccc') . '" />';

//echo '<img src="' . gradient::generate_random_gradient(260, 115, 2) . '" />';
//echo '<img src="' . gradient::generate_random_gradient(260, 115, 3) . '" />';
//echo '<img src="' . gradient::generate_random_gradient(260, 115, 4) . '" />';
//echo '<img src="' . gradient::generate_random_gradient(260, 115, 5) . '" />';
//echo '<img src="' . gradient::generate_random_gradient(260, 115, 6) . '" />';
//echo '<img src="' . gradient::generate_random_gradient(260, 115, 7) . '" />';
//echo '<img src="' . gradient::generate_random_gradient(260, 115, 8) . '" />';
//echo '<img src="' . gradient::generate_random_gradient(260, 115, 9) . '" />';
//echo '<img src="' . gradient::generate_random_gradient(260, 115, 10) . '" />';

function smoke($seed, $octaves = 4, $lacunarity = 2.5, $gain = 0.4, $blackWhiteRatio = 0.5, $invertColors = true) {
    // Instantiate the simplex class with a seed of your choice.
    $simplex = new simplex($seed);

    // Define the size of your image and the scale of the noise
    $width = 512;
    $height = 512;
    $scale = 0.005;
    $warp = 0.5;

    // Create a new image with GD
    $image = imagecreatetruecolor($width, $height);

    // Loop over each pixel in the image
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            // Generate a noise value for this pixel
            // Using fractal_brownian_motion or domain_warp as per your requirement
            // Here we use fractal_brownian_motion with arbitrary values for octaves, lacunarity, and gain
            $noise = $simplex->domain_warp($x * $scale, $y * $scale, $octaves, $lacunarity, $gain, $warp, $simplex);

            // Adjust the noise value to the range [0, 1]
            $noise = ($noise + 1) / 2;

            // Use the blackWhiteRatio to control the black/white balance
            if ($noise > $blackWhiteRatio) {
                $grayValue = 200 + ($noise - $blackWhiteRatio) / (1 - $blackWhiteRatio) * 55;
            } else {
                $grayValue = 200 * ($noise / $blackWhiteRatio);
            }

            // Ensure the grayValue stays within the 0-255 range
            $grayValue = max(0, min(255, $grayValue));

            // Invert colors if $invertColors is true
            if ($invertColors) {
                $grayValue = 255 - $grayValue;
            }

            // Create a color for this pixel
            $color = imagecolorallocate($image, $grayValue, $grayValue, $grayValue);

            // Set the pixel color
            imagesetpixel($image, $x, $y, $color);
        }
    }

    // Convert the image to a data URL so it can be used as a placeholder
    ob_start();
    imagepng($image);
    $data = ob_get_clean();
    return 'data:image/png;base64,' . base64_encode($data);
}



echo '<img src="' . smoke('12345') . '" />';
