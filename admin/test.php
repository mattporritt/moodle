<?php

require_once('../config.php');


use core\noise\simplex\simplex;

function createNebulaPlaceholder($inputString) {
    $hash = md5($inputString); // Create a hash for determinism
    $seed = hexdec(substr($hash, 0, 8)); // Use the first 8 digits of the hash as the seed

    $width = 400; // Image width
    $height = 400; // Image height
    $scale = 0.06; // Scale factor for the noise function

    $noise = new simplex($seed);
    $image = imagecreatetruecolor($width, $height);

    // Create colors
    $background = imagecolorallocate($image, 0, 0, 0); // black background
    $nebula1 = imagecolorallocate($image, 255, 140, 0); // orange nebula
    $nebula2 = imagecolorallocate($image, 75, 0, 130); // indigo nebula

    // Generate the nebula pattern by blending two layers of noise
    for ($x = 0; $x < $width; $x++) {
        for ($y = 0; $y < $height; $y++) {
            $value1 = $noise->noise2D($x * $scale, $y * $scale);
            $value2 = $noise->noise2D($x * $scale * 2, $y * $scale * 2); // Second layer with a different scale
            if ($value1 > 0) { // The noise function returns values between -1 and 1
                imagesetpixel($image, $x, $y, $nebula1);
            }
            if ($value2 > 0) {
                // Use imagecolorset to blend the second layer with the first
                $index = imagecolorat($image, $x, $y);
                $colors = imagecolorsforindex($image, $index);
                $blend = imagecolorallocate($image,
                        ($colors['red'] + $nebula2['red']) / 2,
                        ($colors['green'] + $nebula2['green']) / 2,
                        ($colors['blue'] + $nebula2['blue']) / 2);
                imagesetpixel($image, $x, $y, $blend);
            }
        }
    }

    // Convert the image to a data URL so it can be used as a placeholder
    ob_start();
    imagepng($image);
    $data = ob_get_clean();
    return 'data:image/png;base64,' . base64_encode($data);
}

echo '<img src="' . createNebulaPlaceholder('test') . '" />';
echo '<img src="' . createNebulaPlaceholder('foobarcccccc') . '" />';
