<?php

function count_score($game_position) {
    $white_score = 0;
    $black_score = 0;
    $row_count = 0;
	$white_territory = 0;
	$black_territory = 0;
    foreach ($game_position as $row) {
        $column_count = 0;
        foreach ($row as $point) {
            if ($point == 'b') {
                $black_score++;
				$black_territory++;
            }
            if ($point == 'w') {
                $white_score++;
				$white_territory++;
            }
            if ($point == 'X') {
                $black_score += 2;
				$black_territory++;
            }
            if ($point == 'C') {
                $white_score += 2;
				$white_territory++;
            }
            $column_count++;
        }
    }
    if ($black_score > $white_score) {
        echo "Black wins by " . ($black_score - $white_score) . " points: {$black_score}-{$white_score}\n";
    } else {
        echo "White wins by " . ($white_score - $black_score) . " points: {$white_score}-{$black_score}\n";
    }
	echo "Black territory: $black_territory\n";
	echo "White territory: $white_territory\n";
}

function print_game_position($game_position) {
    $row_count = 0;
    foreach ($game_position as $row) {
        $column_count = 0;
        foreach ($row as $point) {
            echo $point;
        }
        echo "\n";
    }
}

function analyze_game_position(&$game_position) {
    $height = count($game_position);
    $width = count($game_position[0]);

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    // Remove groups with one liberty.
    foreach ($area_neighbors as $area => $neighbors) {
        $neighbors = array_values($neighbors);
        $empty_count = count(array_keys($neighbors, ' '));
        if ($empty_count == 1) {
            foreach ($areas[$area] as $point) {
                list($x, $y) = alphabet2xy($point);
                $prisoners[$point] = $game_position[$x][$y];
                $game_position[$x][$y] = $game_position[$x][$y] == 'B' ? 'w' : 'b';
            }
        }
    }

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    // Mark all territory surrounded by one color only.
    foreach ($area_neighbors as $area => $neighbors) {
        $point = $areas[$area][0];
        list($x, $y) = alphabet2xy($point);
        $area_type = $game_position[$x][$y];
        if ($area_type != ' ') {
            continue;
        }
        $unique_neighbors = array_values(array_unique($neighbors));
        sort($unique_neighbors);
        $neighbor_string = implode('', $unique_neighbors);
        if (in_array($neighbor_string, ['B', 'Bb', 'W', 'Ww'])) {
            foreach ($areas[$area] as $point) {
                list($x, $y) = alphabet2xy($point);
                $game_position[$x][$y] = $unique_neighbors[0] == 'B' ? 'b' : 'w';
            }
        }
    }

    // Mark all territory for the colour that surrounds it more.
    foreach ($area_neighbors as $area => $neighbors) {
        $area_size = count($areas[$area]);
        if ($area_size < 4) {
            // Protect dames.
            continue;
        }
        $unique_neighbors = array_values(array_unique($neighbors));
        if (count($unique_neighbors) == 2 && $unique_neighbors[0] != ' ' && $unique_neighbors[1] != ' ') {
            $count1 = count(array_keys($neighbors, $unique_neighbors[0]));
            $count2 = count(array_keys($neighbors, $unique_neighbors[1]));
            if ($count1 > $count2) {
                foreach ($areas[$area] as $point) {
                    list($x, $y) = alphabet2xy($point);
                    if ($game_position[$x][$y] == ' ' && $unique_neighbors[0] != ' ') {
                        $game_position[$x][$y] = $unique_neighbors[0] == 'B' ? 'b' : 'w';
                    }
                }
            } elseif ($count2 > $count1) {
                foreach ($areas[$area] as $point) {
                    list($x, $y) = alphabet2xy($point);
                    if ($game_position[$x][$y] == ' ' && $unique_neighbors[1] != ' ') {
                        $game_position[$x][$y] = $unique_neighbors[1] == 'B' ? 'b' : 'w';
                    }
                }
            }
        }
    }

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    // If stone has white and black territory as neighbours, change the smaller side to match the larger side.
    foreach ($area_neighbors as $area => $neighbors) {
        $point = $areas[$area][0];
        list($x, $y) = alphabet2xy($point);
        $area_type = $game_position[$x][$y];
        if ($area_type != 'B' && $area_type != 'W') {
            continue;
        }
        $unique_neighbors = array_values(array_unique($neighbors));
        if (in_array('b', $unique_neighbors) && in_array('w', $unique_neighbors)) {
            $white_area_size = 0;
            $black_area_size = 0;
            $black_areas = [];
            $white_areas = [];
            $black_area_points = [];
            $white_area_points = [];
            foreach ($neighbors as $n_point => $n_type) {
                if ($n_type == 'b') {
                    $black_areas[] = $areas_by_point[$n_point];
                }
                if ($n_type == 'w') {
                    $white_areas[] = $areas_by_point[$n_point];
                }
            }
            $black_areas = array_unique($black_areas);
            $white_areas = array_unique($white_areas);
            foreach ($black_areas as $area) {
                $black_area_size += count($areas[$area]);
                $black_area_points = array_merge($black_area_points, $areas[$area]);
            }
            foreach ($white_areas as $area) {
                $white_area_size += count($areas[$area]);
                $white_area_points = array_merge($white_area_points, $areas[$area]);
            }
            $white_area_points = array_unique($white_area_points);
            $black_area_points = array_unique($black_area_points);

            if ($white_area_size > $black_area_size) {
                foreach ($black_area_points as $point) {
                    list($x, $y) = alphabet2xy($point);
                    $game_position[$x][$y] = 'w';
                }
            } elseif ($black_area_size > $white_area_size) {
                foreach ($white_area_points as $point) {
                    list($x, $y) = alphabet2xy($point);
                    $game_position[$x][$y] = 'b';
                }
            }
        }
    }

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    // Remove all stone groups that don't have friendly neighbours.
    foreach ($area_neighbors as $area => $neighbors) {
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
                $prisoners[$point] = $game_position[$x][$y];
                $game_position[$x][$y] = $area_type == 'B' ? 'w' : 'b';
            }
        }
    }

    list($areas, $areas_by_point, $area_neighbors) = find_all_areas($game_position);

    // Mark all territory surrounded by one color only.
    foreach ($area_neighbors as $area => $neighbors) {
        $unique_neighbors = array_values(array_unique($neighbors));
        sort($unique_neighbors);
        $neighbor_string = implode('', $unique_neighbors);
        $area_point = $areas[$area][0];
        if (in_array($neighbor_string, ['B', 'Bb', 'W', 'Ww'])) {
            foreach ($areas[$area] as $point) {
                list($x, $y) = alphabet2xy($point);
                if ($game_position[$x][$y] == ' ') {
                    $game_position[$x][$y] = $unique_neighbors[0] == 'B' ? 'b' : 'w';
                }
            }
        }
    }

    foreach ($prisoners as $point => $type) {
        list($x, $y) = alphabet2xy($point);
        $game_position[$x][$y] = $type == 'B' ? 'C' : 'X';
    }
}

function alphabet2xy($code) {
    $x = ord($code[0]) - 97;
    $y = ord($code[1]) - 97;
    return [$x, $y];
}

function type_match($a, $b) {
    if ($a == $b) {
        return true;
    }

    $ab = [$a, $b];
    sort($ab);
    $ab = implode('', $ab);

    return in_array($ab, [' X', ' C', 'bX', 'wC']);
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
                        $t_code = int2alphabet($x + 1) . int2alphabet($y);
                        $area_neighbors[$area_id][$t_code] = $temp_game_position[$x][$y - 1];
                    }
                    if ($y < $width - 1 && !type_match($temp_game_position[$x][$y + 1], $type) && $temp_game_position[$x][$y + 1] != 'a') {
                        $t_code = int2alphabet($x + 1) . int2alphabet($y + 2);
                        $area_neighbors[$area_id][$t_code] = $temp_game_position[$x][$y + 1];
                    }
                    if ($x > 0 && !type_match($temp_game_position[$x - 1][$y], $type) && $temp_game_position[$x - 1][$y] != 'a') {
                        $t_code = int2alphabet($x) . int2alphabet($y + 1);
                        $area_neighbors[$area_id][$t_code] = $temp_game_position[$x - 1][$y];
                    }
                    if ($x < $height - 1 && !type_match($temp_game_position[$x + 1][$y], $type) && $temp_game_position[$x + 1][$y] != 'a') {
                        $t_code = int2alphabet($x + 2) . int2alphabet($y + 1);
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

function find_goban_edges($edge_image, $filename) {
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

	$shortest_distance_from_left_edge = array();
	foreach ($lines as $line) {
		if ($line[0] == $line[2]) {
			$distance = abs($line[0] - $left_edge);
			if (!isset($shortest_distance_from_left_edge[$line[0]])) {
				$shortest_distance_from_left_edge[$line[0]] = $distance;
			} elseif ($distance < $shortest_distance_from_left_edge[$line[0]]) {
				$shortest_distance_from_left_edge[$line[0]] = $distance;
			}
		}
	}
	ksort($shortest_distance_from_left_edge);
	var_dump($shortest_distance_from_left_edge);

	imagejpeg($edge_image, "uploads/{$filename}_lines.jpg");

	return [
        'x' => $left_edge + 5,
        'y' => $top_edge + 5,
        'width' => $right_edge - $left_edge - 10,
        'height' => $bottom_edge - $top_edge - 10,
    ];
}

function create_edge_image($gray_image, $filename) {
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

    imagejpeg($edge_image, "uploads/{$filename}_edge.jpg");
    return $edge_image;
}

function create_cropped_image($image) {
    echo "Cropping to 1600px max...\n";

    $width = imagesx($image);
    $height = imagesy($image);

	if ($width > 1600 || $height > 1600) {
		// Rescale image to 1600px max keeping aspect ratio.
		$scale = min(1600 / $width, 1600 / $height);
		echo "Rescaling image to $scale\n";
		$new_width = round($width * $scale);
		$new_height = round($height * $scale);
		$new_image = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		$image = $new_image;
		$width = $new_width;
		$height = $new_height;
		echo "New image size: $width x $height\n";
	}
    return $image;
}

function create_grayscale_image($image, $filename) {
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
    imagejpeg($gray_image, "uploads/{$filename}_gray.jpg");
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

function create_brightness_matrix($cropped_image, $goban_size, $filename) {
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

    imagejpeg($grid_image, "uploads/{$filename}_grid.jpg");
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

function validate_file() {
    $file_path = $_FILES['board']['tmp_name'];
    $file_size = filesize($file_path);
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $file_type = finfo_file($file_info, $file_path);

    if ($file_size === 0) {
        die("The file is empty.");
    }

    if ($file_size > 12 * 1024 * 1024) {
        die("The file is too large");
    }

    $allowed_types = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg'
    ];

    if (!in_array($file_type, array_keys($allowed_types))) {
        die("File not allowed.");
    }

    $file_name = date('YmdHis') . '_' . rand(1000, 9999);
    $extension = $allowed_types[$file_type];
    $target_directory = __DIR__ . "/uploads"; // __DIR__ is the directory of the current PHP file

    $new_file_path = $target_directory . "/" . $file_name . "." . $extension;

    if (!copy($file_path, $new_file_path)) { // Copy the file, returns false if failed
        die("Can't move file.");
    }
    unlink($file_path); // Delete the temp file

    echo "File uploaded successfully.\n";
    return $new_file_path;
}
