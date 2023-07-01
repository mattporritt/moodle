<?php

require_once('../config.php');


use core\pattern\noise\simplex;
use core\pattern\gradient\linear;

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

echo '<img src="' . linear::generate_random_gradient(300, 300, 22345) . '" />';
echo '<img src="' . linear::generate_random_gradient(300, 300, 23345) . '" />';
echo '<img src="' . linear::generate_random_gradient(300, 300, 10) . '" />';
echo '<img src="' . linear::generate_random_gradient(300, 300, 11) . '" />';
