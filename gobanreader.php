<?php

$time = microtime(true);

define('CONTRAST', -50);
define('BRIGHTNESS', 20);
$goban_size = 19;

//$src = 'images/vinogoban.jpg';
$src = 'images/table.jpg';
$image = imagecreatefromjpeg($src);

//$gray_image = create_grayscale_image($image);
//$edge_image = create_edge_image($gray_image);
$edge_image = imagecreatefromjpeg('edge.jpg');
$bounding_box = find_goban_edges($edge_image);

echo "Cropping...";
$cropped_image = imagecrop($image, $bounding_box);
imagejpeg($cropped_image, 'cropped.jpg');

$matrix = create_brightness_matrix($cropped_image, $goban_size);
list($black_threshold, $white_threshold) = calculate_thresholds($matrix['average_brightness_values']);

$goban_image = imagecreatefrompng('images/goban.png');
$goban_width = imagesx($goban_image);
$goban_height = imagesy($goban_image);
$goban_x_step = round($goban_width / $goban_size);
$goban_y_step = round($goban_height / $goban_size);

echo "Goban size: $goban_width x $goban_height\n";
echo "Goban grid size: $goban_x_step x $goban_y_step\n";

$white_stone = imagecreatefrompng('images/white.png');
$black_stone = imagecreatefrompng('images/black.png');
$stone_width = imagesx($white_stone);
$stone_height = imagesy($white_stone);

$white_captured = imagecreatefrompng('images/whitecaptured.png');
$black_captured = imagecreatefrompng('images/blackcaptured.png');

$white_area = imagecreatefrompng('images/whitearea.png');
$black_area = imagecreatefrompng('images/blackarea.png');
$area_width = imagesx($white_area);
$area_height = imagesy($white_area);

$sgf = '(AP[GobanReader:0.10];GM[1];FF[5];';

$game_position = [];

$row_count = 0;
foreach ($matrix['grid_brightness'] as $row) {
    $column_count = 0;
    foreach ($row as $brightness) {
        $point = analyze_point($brightness);
        if ($point == 'black') {
            $game_position[$row_count][$column_count] = 'B';
            $sgf .= 'AB[' . int2alphabet($column_count + 1)
                . int2alphabet($row_count + 1) . '];';
        } elseif ($point == 'white') {
            $game_position[$row_count][$column_count] = 'W';
            $sgf .= 'AW[' . int2alphabet($column_count + 1)
                . int2alphabet($row_count + 1) . '];';
        } else {
            $game_position[$row_count][$column_count] = ' ';
        }
        $column_count++;
    }
    $row_count++;
}

analyze_game_position($game_position);

$row_count = 0;
foreach ($game_position as $row) {
    $column_count = 0;
    foreach ($row as $point) {
        echo $point;
        $item = '';
        $item_w = '';
        $item_h = '';
        switch ($point) {
            case 'B':
                $item = $black_stone;
                $item_w = $stone_width;
                $item_h = $stone_height;
                break;
            case 'C':
                $item = $black_captured;
                $item_w = $stone_width;
                $item_h = $stone_height;
                break;
            case 'W':
                $item = $white_stone;
                $item_w = $stone_width;
                $item_h = $stone_height;
                break;
            case 'X':
                $item = $white_captured;
                $item_w = $stone_width;
                $item_h = $stone_height;
                break;
            case 'b':
                $item = $black_area;
                $item_w = $area_width;
                $item_h = $area_height;
                break;
            case 'w':
                $item = $white_area;
                $item_w = $area_width;
                $item_h = $area_height;
                break;
            default:
        }
        if ($item) {
            imagecopy($goban_image, $item,
                $column_count * $goban_x_step,
                $row_count * $goban_y_step,
                0,
                0,
                $item_w,
                $item_h);
        }
        $column_count++;
    }
    $row_count++;
    echo "\n";
}

echo "Creating goban image...\n\n";
imagepng($goban_image, 'goban.png');

echo "SGF:\n";

$sgf .= ')';
echo $sgf;

$elapsed = round(microtime(true) - $time, 1);
echo "\nElapsed time: $elapsed s\n";

function analyze_game_position(&$game_position) {
    $height = count($game_position);
    $width = count($game_position[0]);

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    foreach ($area_neighbors as $area => $neighbors) {
        $neighbors = array_values($neighbors);
        $empty_count = count(array_keys($neighbors, ' '));
        if ($empty_count == 1) {
            foreach ($areas[$area] as $point) {
                list($x, $y) = alphabet2xy($point);
                $game_position[$x][$y] = $game_position[$x][$y] == 'B' ? 'C' : 'X';
            }
        }
    }

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    foreach ($area_neighbors as $area => $neighbors) {
        $unique_neighbors = array_values(array_unique($neighbors));
        if (count($unique_neighbors) == 1 && $unique_neighbors[0] != ' ') {
            foreach ($areas[$area] as $point) {
                list($x, $y) = alphabet2xy($point);
                $game_position[$x][$y] = $unique_neighbors[0] == 'B' ? 'b' : 'w';
            }
        }
    }

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    foreach ($area_neighbors as $area => $neighbors) {
        $area_size = count($areas[$area]);
        if ($area_size < 3) {
            $unique_neighbors = array_values(array_unique($neighbors));
            list($ax, $ay) = alphabet2xy($areas[$area][0]);
            $area_type = $game_position[$ax][$ay];
            if ($area_type != 'W' && $area_type != 'B') {
                continue;
            }
            $kill_group = false;
            if ($area_type == 'B' && (!in_array('B', $neighbors) && !in_array('b', $neighbors))) {
                $kill_group = true;
            }
            if ($area_type == 'W' && (!in_array('w', $neighbors) && !in_array('w', $neighbors))) {
                $kill_group = true;
            }
            if (count($unique_neighbors) == 1 && $unique_neighbors[0] == ' ') {
                $kill_group = false;
            }

            if ($kill_group) {
                foreach ($areas[$area] as $point) {
                    list($x, $y) = alphabet2xy($point);
                    $game_position[$x][$y] = $area_type == 'B' ? 'C' : 'X';
                }
            }
        }
    }

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    foreach ($area_neighbors as $area => $neighbors) {
        $unique_neighbors = array_values(array_unique($neighbors));
        if (count($unique_neighbors) == 1 && $unique_neighbors[0] != ' ') {
            foreach ($areas[$area] as $point) {
                list($x, $y) = alphabet2xy($point);
                if ($game_position[$x][$y] != 'C' && $game_position[$x][$y] != 'X') {
                    $game_position[$x][$y] = $unique_neighbors[0] == 'B' ? 'b' : 'w';
                }
            }
        }
    }
}

function alphabet2xy($code) {
    $x = ord($code[0]) - 97;
    $y = ord($code[1]) - 97;
    return [$x, $y];
}

function type_match($a, $b) {
    if ($a == ' ' && $b == 'C') {
        return true;
    }
    if ($a == ' ' && $b == 'X') {
        return true;
    }
    if ($b == ' ' && $a == 'C') {
        return true;
    }
    if ($b == ' ' && $a == 'X') {
        return true;
    }
    return $a == $b;
}

function find_all_areas($game_position) {
    global $area_debug;
    echo "Finding all chains...\n";
    $areas = [];
    $area_by_point = [];
    $area_neighbors = [];

    $height = count($game_position);
    $width = count($game_position[0]);

    for ($orig_y = 0; $orig_y < $height; $orig_y++) {
        for ($orig_x = 0; $orig_x < $width; $orig_x++) {
            $code = int2alphabet($orig_x + 1) . int2alphabet($orig_y + 1);
            if (isset($area_by_point[$code])) {
                continue;
            }
            $area_id = count($areas);
            $area = [];
            $queue = [[$orig_x, $orig_y]];
            $type = $game_position[$orig_x][$orig_y];

            $temp_game_position = $game_position;
            while ($queue) {
                $point = array_shift($queue);
                $x = $point[0];
                $y = $point[1];
                $code = int2alphabet($x + 1) . int2alphabet($y + 1);
                if (type_match($temp_game_position[$x][$y], $type)) {
                    echo $temp_game_position[$x][$y] . " and $type match\n";
                    $area[] = $code;
                    $temp_game_position[$x][$y] = 'a';
                    if ($y > 0 && type_match($temp_game_position[$x][$y - 1], $type)) {
                        $queue[] = [$x, $y - 1];
                    }
                    if ($y < $width - 1 && type_match($temp_game_position[$x][$y + 1], $type)) {
                        $queue[] = [$x, $y + 1];
                    }
                    if ($x > 0 && type_match($temp_game_position[$x - 1][$y], $type)) {
                        $queue[] = [$x - 1, $y];
                    }
                    if ($x < $height - 1 && type_match($temp_game_position[$x + 1][$y], $type)) {
                        $queue[] = [$x + 1, $y];
                    }
                    if ($y > 0 && !type_match($temp_game_position[$x][$y - 1], $type) && $temp_game_position[$x][$y - 1] != 'a') {
                        $t_code = int2alphabet($x) . int2alphabet($y - 1);
                        $area_neighbors[$area_id][$t_code] = $temp_game_position[$x][$y - 1];
                    }
                    if ($y < $width - 1 && !type_match($temp_game_position[$x][$y + 1], $type) && $temp_game_position[$x][$y + 1] != 'a') {
                        $t_code = int2alphabet($x) . int2alphabet($y + 1);
                        $area_neighbors[$area_id][$t_code] = $temp_game_position[$x][$y + 1];
                    }
                    if ($x > 0 && !type_match($temp_game_position[$x - 1][$y], $type) && $temp_game_position[$x - 1][$y] != 'a') {
                        $t_code = int2alphabet($x - 1) . int2alphabet($y);
                        $area_neighbors[$area_id][$t_code] = $temp_game_position[$x - 1][$y];
                    }
                    if ($x < $height - 1 && !type_match($temp_game_position[$x + 1][$y], $type) && $temp_game_position[$x + 1][$y] != 'a') {
                        $t_code = int2alphabet($x + 1) . int2alphabet($y);
                        $area_neighbors[$area_id][$t_code] = $temp_game_position[$x + 1][$y];
                    }
                }
            }
            sort($area);
            foreach ($area as $code) {
                $area_by_point[$code] = $area_id;
            }
            $areas[$area_id] = $area;
        }
    }
    return [$areas, $area_by_point, $area_neighbors];
}

function find_goban_edges($edge_image) {
    echo "Finding goban edges...\n";

    $width = imagesx($edge_image);
    $height = imagesy($edge_image);
    $lines = detect_lines($edge_image);

    $red = imagecolorallocate($edge_image, 255, 0, 0);
    $green = imagecolorallocate($edge_image, 0, 255, 0);
    $left_edge = $width;
    $right_edge = 0;
    $top_edge = $height;
    $bottom_edge = 0;
    foreach ($lines as $line) {
        imageline($edge_image, $line[0], $line[1], $line[2], $line[3], $red);
        if ($line[0] == $line[2]) {
            if ($line[0] < $left_edge) {
                $left_edge = $line[0];
            }
            if ($line[0] > $right_edge) {
                $right_edge = $line[0];
            }
        } elseif ($line[1] == $line[3]) {
            if ($line[1] < $top_edge) {
                $top_edge = $line[1];
            }
            if ($line[1] > $bottom_edge) {
                $bottom_edge = $line[1];
            }
        }
    }

    echo "Bounding box: $left_edge x $top_edge - $right_edge x $bottom_edge\n";
    imagerectangle($edge_image, $left_edge, $top_edge, $right_edge, $bottom_edge, $green);
    imagejpeg($edge_image, 'lines.jpg');

    return [
        'x' => $left_edge + 10,
        'y' => $top_edge + 10,
        'width' => $right_edge - $left_edge - 20,
        'height' => $bottom_edge - $top_edge - 20,
    ];
}

function create_edge_image($gray_image) {
    echo "Creating sobel edge image...\n";
    $height = imagesy($gray_image);
    $width = imagesx($gray_image);
    $edge_image = imagecreatetruecolor($width, $height);

    $sobelX = [
        [-1, 0, 1],
        [-2, 0, 2],
        [-1, 0, 1],
    ];

    $sobelY = [
        [-1, -2, -1],
        [0, 0, 0],
        [1, 2, 1],
    ];

    for ($y = 1; $y < $height - 1; $y++) {
        for ($x = 1; $x < $width - 1; $x++) {
            $gx = 0;
            $gy = 0;
            for ($ky = -1; $ky <= 1; $ky++) {
                for ($kx = -1; $kx <= 1; $kx++) {
                    $gray = imagecolorat($gray_image, $x + $kx, $y + $ky) & 0xFF;
                    $gx += $gray * $sobelX[$ky + 1][$kx + 1];
                    $gy += $gray * $sobelY[$ky + 1][$kx + 1];
                }
            }
            $magnitude = min(255, sqrt($gx * $gx + $gy * $gy));
            $edgeColor = imagecolorallocate($edge_image, $magnitude, $magnitude, $magnitude);
            imagesetpixel($edge_image, $x, $y, $edgeColor);
        }
    }

    imagejpeg($edge_image, 'edge.jpg');
    return $edge_image;
}

function create_grayscale_image($image) {
    echo "Creating grayscale image...\n";

    $width = imagesx($image);
    $height = imagesy($image);
    $gray_image = imagecreatetruecolor($width, $height);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $gray = round(0.299 * $r + 0.587 * $g + 0.114 * $b);
            $grayColor = imagecolorallocate($gray_image, $gray, $gray, $gray);
            imagesetpixel($gray_image, $x, $y, $grayColor);
        }
    }
    imagejpeg($gray_image, 'gray.jpg');
    return $gray_image;
}

function analyze_point($brightness) {
    global $black_threshold, $white_threshold;
    if ($brightness['average'] <= $black_threshold) {
        return 'black';
    } elseif ($brightness['average'] >= $white_threshold) {
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

function detect_lines($edge_image) {
    $width = imagesx($edge_image);
    $height = imagesy($edge_image);

    // Horizontal line detection
    for ($y = 0; $y < $height; $y++) {
        $startX = -1;
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorat($edge_image, $x, $y) & 0xFF;
            if ($color > 128) { // Adjust threshold as needed
                if ($startX == -1) {
                    $startX = $x;
                }
            } else {
                if ($startX != -1) {
                    if ($x - $startX > $width * 0.1) { // Minimum length of the line
                        $lines[] = [$startX, $y, $x - 1, $y];
                    }
                    $startX = -1;
                }
            }
        }
    }

    echo "Horizontal lines: " . count($lines) . "\n";

    // Vertical line detection
    for ($x = 0; $x < $width; $x++) {
        $startY = -1;
        for ($y = 0; $y < $height; $y++) {
            $color = imagecolorat($edge_image, $x, $y) & 0xFF;
            if ($color > 128) {
                if ($startY == -1) {
                    $startY = $y;
                }
            } else {
                if ($startY != -1) {
                    if ($y - $startY > $height * 0.1) {
                        $lines[] = [$x, $startY, $x, $y - 1];
                    }
                    $startY = -1;
                }
            }
        }
    }

    echo "Vertical lines: " . count($lines) . "\n";

    return $lines;
}

function create_brightness_matrix($cropped_image, $goban_size) {
    echo "\nCreating brightness matrix:\n";
    $width = imagesx($cropped_image);
    $height = imagesy($cropped_image);

    //imagefilter($cropped_image, IMG_FILTER_BRIGHTNESS, BRIGHTNESS);
    imagefilter($cropped_image, IMG_FILTER_CONTRAST, CONTRAST);

    $grid_image = imagecreatetruecolor($width, $height);
    imagecopy($grid_image, $cropped_image, 0, 0, 0, 0, $width, $height);

    $x_step = round($width / $goban_size);
    $y_step = round($height / $goban_size);

    $square_x = round($x_step / 3);
    $square_y = round($y_step / 3);

    echo "Image size: $width x $height\n";
    echo "Grid size: $x_step x $y_step\n";
    echo "Square size: " . $square_x . " x " . $square_y . "\n";

    $y = 0;
    $row_count = 0;
    while ( $y < $height && $row_count < $goban_size ) {
        $x = 0;
        $column_count = 0;
        while ( $x < $width && $column_count < $goban_size) {
            $lowest_brightness = 255;
            $highest_brightness = 0;
            $total_brightness = 0;
            $total_saturation = 0;
            $start_x = $x + $square_x;
            $start_y = $y + $square_y;
            $end_x = $start_x + $square_x;
            $end_y = $start_y + $square_y;
            $counter = 0;
            for ($pixel_y = $start_y; $pixel_y < $end_y; $pixel_y = $pixel_y + 2) {
                for ($pixel_x = $start_x; $pixel_x < $end_x; $pixel_x = $pixel_x + 2) {
                    $pixel_color = @imagecolorat($cropped_image, $pixel_x, $pixel_y);
                    $pixel_rgb = imagecolorsforindex($cropped_image, $pixel_color);
                    $pixel_brightness = brightness_from_rgb($pixel_rgb);
                    if ( $pixel_brightness < $lowest_brightness ) {
                        $lowest_brightness = $pixel_brightness;
                    }
                    if ( $pixel_brightness > $highest_brightness ) {
                        $highest_brightness = $pixel_brightness;
                    }
                    $total_brightness += $pixel_brightness;
                    $total_saturation += saturation_from_rgb($pixel_rgb);
                    $counter++;
                }
            }

            $average_brightness = round($total_brightness / ($square_x * $square_y));
            $average_saturation = round($total_saturation / ($square_x * $square_y));

            $brightness = [
                'lowest' => $lowest_brightness,
                'highest' => $highest_brightness,
                'average' => $average_brightness,
                'saturation' => $average_saturation,
            ];
            $average_brightness_values[] = $brightness['average'];
            $grid_brightness[$row_count][$column_count] = $brightness;

            echo str_pad($average_brightness, 3) . " ";

            imagerectangle($grid_image,
                $start_x, $start_y,
                $end_x, $end_y,
                imagecolorallocate($grid_image, 100, 100, 0)
            );
            imagestring(
                $grid_image, 5, $x + $square_x / 2, $y,
                $highest_brightness,
                imagecolorallocate($grid_image, 255, 0, 0));
            imagestring(
                $grid_image, 5, $x + $square_x / 2, $y + 12,
                $average_brightness,
                imagecolorallocate($grid_image, 255, 0, 0));
            imagestring(
                $grid_image, 5, $x + $square_x / 2, $y + 24,
                $lowest_brightness,
                imagecolorallocate($grid_image, 255, 0, 0));
            imagestring(
                $grid_image, 5, $x + $square_x / 2, $y + 36,
                $average_saturation,
                imagecolorallocate($grid_image, 0, 255, 0));
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
    return [
        'average_brightness_values' => $average_brightness_values,
        'grid_brightness' => $grid_brightness,
    ];
}

function saturation_from_rgb($rgb) {
    $r = $rgb['red'];
    $g = $rgb['green'];
    $b = $rgb['blue'];
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $delta = $max - $min;

    return $delta;
}

function calculate_thresholds($matrix) {
    do {
        $clusters = kmeans($matrix, 3);
    } while ( count($clusters) != 3 );

    echo "Clusters:\n";
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

    return [$black_threshold, $white_threshold];
}
