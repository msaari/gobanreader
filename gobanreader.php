<?php

define('BLACK_THRESHOLD', 250);
define('WHITE_THRESHOLD', 590);

$src = 'images/vinogoban.jpg';
//$src = 'images/goban1.jpg';
$image = imagecreatefromjpeg($src);
$width = imagesx($image);
$height = imagesy($image);

$goban_size = 19;
$x_step = floor($width / $goban_size);
$y_step = floor($height / $goban_size);

$y = 0;
$row_count = 0;
while ( $y < $height && $row_count < $goban_size ) {
    $x = 0;
    $column_count = 0;
    while ( $x < $width && $column_count < $goban_size) {
        $square = imagecreatetruecolor(1, 1);
        imagecopyresampled($square, $image, 0, 0, $x, $y, 1, 1, $x_step, $y_step);
        $color = imagecolorat($square, 0, 0);
        $rgb = imagecolorsforindex($square, $color);

        $brightness_values[] = brightness_from_rgb($rgb);
        $grid_brightness[$row_count][$column_count] = brightness_from_rgb($rgb);

        echo brightness_from_rgb($rgb) . " ";

        $x += $x_step;
        $column_count++;
    }
    echo "\n";
    $y += $y_step;
    $row_count++;
}

$clusters = kmeans($brightness_values, 3);
$black_key = min(array_keys($clusters));
echo "Black key: $black_key\n";

$white_key = max(array_keys($clusters));
echo "White key: $white_key\n";

$black_threshold = max($clusters[$black_key]);
$white_threshold = min($clusters[$white_key]);

echo "Black threshold: $black_threshold\n";
echo "White threshold: $white_threshold\n";

$sgf = '(AP[GobanReader:0.10];GM[1];FF[5];';

$row_count = 0;
foreach ($grid_brightness as $row) {
    $column_count = 0;
    foreach ($row as $brightness) {
        if ($brightness < $black_threshold) {
            $sgf .= 'AB[' . int2alphabet($column_count + 1)
                . int2alphabet($row_count + 1) . '];';
        } elseif ($brightness > $white_threshold) {
            $sgf .= 'AW[' . int2alphabet($column_count + 1)
                . int2alphabet($row_count + 1) . '];';
        }
        $column_count++;
    }
    $row_count++;
}




$sgf .= ')';
echo $sgf;



/*

for ($x = 0; $x += $x_step; $x < $width) {
    for ($y = 0; $y += $y_step; $y < $height) {
        $square = imagecreatetruecolor(1, 1);
        imagecopyresampled($square, $image, 0, 0, $x, $y, 1, 1, $x_step, $y_step);
        $color = imagecolorat($square, 0, 0);
        $rgb = imagecolorsforindex($square, $color);
        echo $rgb['red'] . ' ' . $rgb['green'] . ' ' . $rgb['blue'] . "\n";
    }
}
*/

function int2alphabet($int) {
    $alphabet = '';
    while ($int > 0) {
        $remainder = ($int - 1) % 26;
        $alphabet = chr(65 + $remainder) . $alphabet;
        $int = floor(($int - $remainder) / 26);
    }
    return strtolower($alphabet);
}

function hue_from_rgb($rgb) {
    $r = $rgb['red'] / 255;
    $g = $rgb['green'] / 255;
    $b = $rgb['blue'] / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $delta = $max - $min;
    if ($delta == 0) {
        return 0;
    } elseif ($max == $r) {
        return round(60 * fmod(($g - $b) / $delta, 6));
    } elseif ($max == $g) {
        return round(60 * (($b - $r) / $delta + 2));
    } elseif ($max == $b) {
        return round(60 * (($r - $g) / $delta + 4));
    }
}

function brightness_from_rgb($rgb) {
    return round(($rgb['red'] + $rgb['green'] + $rgb['blue']) / 3);
}


function kmeans($data, $k = 3, $maxIterations = 100) {
    $n = count($data);
    if ($n < $k) {
        throw new Exception('Number of clusters cannot be greater than the number of data points');
    }

    // Randomly initialize the centroids
    $centroids = array_rand($data, $k);
    $clusters = [];
    $previousCentroids = [];

    for ($i = 0; $i < $maxIterations; $i++) {
        // Assign points to the nearest centroid
        $clusters = [];
        foreach ($data as $point) {
            $closestCentroid = null;
            $closestDistance = PHP_INT_MAX;
            foreach ($centroids as $centroid) {
                $distance = abs($point - $centroid);
                if ($distance < $closestDistance) {
                    $closestDistance = $distance;
                    $closestCentroid = $centroid;
                }
            }
            $clusters[$closestCentroid][] = $point;
        }

        // Calculate new centroids
        $previousCentroids = $centroids;
        $centroids = [];
        foreach ($clusters as $cluster) {
            $centroids[] = array_sum($cluster) / count($cluster);
        }

        // Check for convergence
        if ($centroids === $previousCentroids) {
            break;
        }
    }

    // Final cluster assignment
    $finalClusters = [];
    foreach ($data as $point) {
        $closestCentroid = null;
        $closestDistance = PHP_INT_MAX;
        foreach ($centroids as $centroid) {
            $distance = abs($point - $centroid);
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $closestCentroid = $centroid;
            }
        }
        $finalClusters[$closestCentroid][] = $point;
    }

    return $finalClusters;
}
