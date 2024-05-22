<?php

define('CONTRAST', -20);

//$src = 'images/vinogoban.jpg';
$src = 'images/goban2.jpg';
$image = imagecreatefromjpeg($src);
$width = imagesx($image);
$height = imagesy($image);

imagefilter($image, IMG_FILTER_CONTRAST, CONTRAST);

$goban_size = 19;
$x_step = floor($width / $goban_size);
$y_step = floor($height / $goban_size);

$square_x = floor($x_step / 3);
$square_y = floor($y_step / 3);

echo "Image size: $width x $height\n";
echo "Grid size: $x_step x $y_step\n";
echo "Square size: " . $square_x * 2 . " x " . $square_y * 2 . "\n";

$grid_image = imagecreatetruecolor($width, $height);
imagecopy($grid_image, $image, 0, 0, 0, 0, $width, $height);

echo "\n";

echo "Brightness matrix:\n";

$y = 0;
$row_count = 0;
while ( $y < $height && $row_count < $goban_size ) {
    $x = 0;
    $column_count = 0;
    while ( $x < $width && $column_count < $goban_size) {
        $lowest_brightness = 255;
        $highest_brightness = 0;
        $total_brightness = 0;
        for ( $pixel_y = $y + $square_y; $pixel_y < $y + $y_step - $square_y; $pixel_y++ ) {
            for ( $pixel_x = $x + $square_x; $pixel_x < $x + $x_step - $square_x; $pixel_x++ ) {
                $pixel_color = imagecolorat($image, $pixel_x, $pixel_y);
                $pixel_rgb = imagecolorsforindex($image, $pixel_color);
                $pixel_brightness = brightness_from_rgb($pixel_rgb);
                if ( $pixel_brightness < $lowest_brightness ) {
                    $lowest_brightness = $pixel_brightness;
                }
                if ( $pixel_brightness > $highest_brightness ) {
                    $highest_brightness = $pixel_brightness;
                }
                $total_brightness += $pixel_brightness;
            }
        }
        $average_brightness = round($total_brightness / (($x_step - 2 * $square_x) * ($y_step - 2 * $square_y)));

        $brightness = [
            'lowest' => $lowest_brightness,
            'highest' => $highest_brightness,
            'average' => $average_brightness,
        ];
        $average_brightness_values[] = $brightness['average'];
        $grid_brightness[$row_count][$column_count] = $brightness;

        echo str_pad($average_brightness, 3) . " ";

        imagerectangle($grid_image,
            $x + $square_x, $y + $square_y,
            $x + $x_step - $square_x, $y + $y_step - $square_y,
            imagecolorallocate($grid_image, 100, 100, 0)
        );
        imagestring(
            $grid_image, 5, $x + $square_x, $y + $square_y,
            $highest_brightness,
            imagecolorallocate($grid_image, 255, 0, 0));
        imagestring(
            $grid_image, 5, $x + $square_x, $y + $square_y + 12,
            $average_brightness,
            imagecolorallocate($grid_image, 255, 0, 0));
        imagestring(
            $grid_image, 5, $x + $square_x, $y + $square_y + 24,
            $lowest_brightness,
            imagecolorallocate($grid_image, 255, 0, 0));
        $x += $x_step;
        $column_count++;
    }
    echo "\n";
    $y += $y_step;
    $row_count++;
}

echo "\n";
echo "Creating grid image...\n";

imagejpeg($grid_image, 'grid.jpg');

echo "\n";

do {
    $clusters = kmeans($average_brightness_values, 3);
} while ( count($clusters) != 3 );

foreach ( $clusters as $key => $cluster ) {
    echo "Cluster $key: " . min($cluster) . "-" . max($cluster) . "\n";
}

echo "\n";

$black_key = min(array_keys($clusters));
echo "Black key: $black_key\n";

$white_key = max(array_keys($clusters));
echo "White key: $white_key\n";

$black_threshold = max($clusters[$black_key]);
$white_threshold = min($clusters[$white_key]);

echo "Black threshold: $black_threshold\n";
echo "White threshold: $white_threshold\n";

echo "\n";

$goban_image = imagecreatefrompng('images/goban.png');
$goban_width = imagesx($goban_image);
$goban_height = imagesy($goban_image);
$goban_x_step = floor($goban_width / $goban_size);
$goban_y_step = floor($goban_height / $goban_size);

echo "Goban size: $goban_width x $goban_height\n";
echo "Goban grid size: $goban_x_step x $goban_y_step\n";

$white_stone = imagecreatefrompng('images/white.png');
$black_stone = imagecreatefrompng('images/black.png');
$stone_width = imagesx($white_stone);
$stone_height = imagesy($white_stone);

$sgf = '(AP[GobanReader:0.10];GM[1];FF[5];';

$row_count = 0;
foreach ($grid_brightness as $row) {
    $column_count = 0;
    foreach ($row as $brightness) {
        $point = analyze_point($brightness);
        if ($point == 'black') {
            $sgf .= 'AB[' . int2alphabet($column_count + 1)
                . int2alphabet($row_count + 1) . '];';
/*            echo "Black stone at " . int2alphabet($column_count + 1)
                . int2alphabet($row_count + 1) . " "
                . $column_count * $goban_x_step . 'x' . $row_count * $goban_y_step .
                "\n";*/
            imagecopy($goban_image, $black_stone,
                $column_count * $goban_x_step,
                $row_count * $goban_y_step,
                0,
                0,
                $stone_width,
                $stone_height);
        } elseif ($point == 'white') {
            $sgf .= 'AW[' . int2alphabet($column_count + 1)
                . int2alphabet($row_count + 1) . '];';
/*            echo "White stone at " . int2alphabet($column_count + 1)
                . int2alphabet($row_count + 1) . " "
                . $column_count * $goban_x_step . 'x' . $row_count * $goban_y_step .
                "\n";*/
            imagecopy($goban_image, $white_stone,
                $column_count * $goban_x_step,
                $row_count * $goban_y_step,
                0,
                0,
                $stone_width,
                $stone_height);
        }
        $column_count++;
    }
    $row_count++;
}

echo "Creating goban image...\n\n";
imagepng($goban_image, 'goban.png');

echo "SGF:\n";

$sgf .= ')';
echo $sgf;

function analyze_point($brightness) {
    global $black_threshold, $white_threshold;
    if ($brightness['average'] < $black_threshold) {
        return 'black';
    } elseif ($brightness['average'] > $white_threshold) {
        return 'white';
    } elseif ($brightness['lowest'] > 0) {
        return 'white';
    } elseif ($brightness['highest'] < 128) {
        return 'black';
    } else {
        return 'empty';
    }
}

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

    sort($data);
    $centroids = [$data[0], $data[$n - 1], $data[floor($n / 2)]];

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
