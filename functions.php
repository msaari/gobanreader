<?php

function sanitize_komi($komi) {
	$komi = str_replace(',', '.', $komi);
	$komi = floatval($komi);
	return $komi;
}

function count_score($game_position, $white_captures, $black_captures, $komi) {
    $white_score = -intval($white_captures) + sanitize_komi($komi);
    $black_score = -intval($black_captures);
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
                $black_score++;
				$white_score--;
				$black_territory++;
				$white_captures++;
            }
            if ($point == 'C') {
                $white_score++;
				$black_score--;
				$white_territory++;
				$black_captures++;
            }
            $column_count++;
        }
    }
    if ($black_score > $white_score) {
        echo "<p id='winner'><img src='images/black.png' class='stone' />Black wins by " . ($black_score - $white_score) . " points</p>\n";
		echo "<div id='score'>{$black_score}-{$white_score}</div>\n";
    } else {
        echo "<p id='winner'><img src='images/white.png' class='stone' />White wins by " . ($white_score - $black_score) . " points</p>\n";
		echo "<div id='score'>{$white_score}-{$black_score}</div>\n";
    }
	echo "<div id='score_details'><div>Black territory: $black_territory<br />";
	echo "Black prisoners: $black_captures</div>";
	echo "<div>White territory: $white_territory<br />";
	echo "White prisoners: $white_captures<br />";
	echo "Komi: $komi</div></div>\n";
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

	$prisoners = [];

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
	if (DEBUG) {
		echo "Finding goban edges...\n";
	}

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
		if (DEBUG) {
			imageline($edge_image, $line[0], $line[1], $line[2], $line[3], $red);
		}
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

	if (DEBUG) {
	    echo "Bounding box: $left_edge x $top_edge - $right_edge x $bottom_edge\n";
    	imagerectangle($edge_image, $left_edge, $top_edge, $right_edge, $bottom_edge, $green);
	}

	imagejpeg($edge_image, "uploads/{$filename}_lines.jpg");

	return [
        'x' => $left_edge + 5,
        'y' => $top_edge + 5,
        'width' => $right_edge - $left_edge - 10,
        'height' => $bottom_edge - $top_edge - 10,
		'image' => $edge_image,
    ];
}

function create_edge_image($gray_image, $filename) {
	if (DEBUG) {
		echo "Creating sobel edge image...\n";
	}
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
    if (DEBUG) {
		echo "Cropping to 2000px max...\n";
	}

    $width = imagesx($image);
    $height = imagesy($image);

	if ($width > 2000 || $height > 2000) {
		// Rescale image to 2000px max keeping aspect ratio.
		$scale = min(2000 / $width, 2000 / $height);
		if (DEBUG) {
			echo "Rescaling image to $scale\n";
		}
		$new_width = round($width * $scale);
		$new_height = round($height * $scale);
		$new_image = imagecreatetruecolor($new_width, $new_height);
		imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		$image = $new_image;
		$width = $new_width;
		$height = $new_height;
		if (DEBUG) {
			echo "New image size: $width x $height\n";
		}
	}
    return $image;
}

function create_grayscale_image($image, $filename) {
    if (DEBUG) {
		echo "Creating grayscale image...\n";
	}

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

function int2alphabet($int) {
    $alphabet = '';
    while ($int > 0) {
        $remainder = ($int - 1) % 26;
        $alphabet = chr(65 + $remainder) . $alphabet;
        $int = floor(($int - $remainder) / 26);
    }
    return strtolower($alphabet);
}

function brightness_from_rgb($rgb) {
    return round(($rgb['red'] + $rgb['green'] + $rgb['blue']) / 3);
}

function detect_lines($edge_image) {
    $width = imagesx($edge_image);
    $height = imagesy($edge_image);

    // Horizontal line detection
    for ($y = 0; $y < $height; $y++) {
        $start_x = -1;
        for ($x = 0; $x < $width; $x++) {
            $color = imagecolorat($edge_image, $x, $y) & 0xFF;
            if ($color > 128) { // Adjust threshold as needed
                if ($start_x == -1) {
                    $start_x = $x;
                }
            } else {
                if ($start_x != -1) {
                    if ($x - $start_x > $width * 0.1) { // Minimum length of the line
                        $lines[] = [$start_x, $y, $x - 1, $y];
                    }
                    $start_x = -1;
                }
            }
        }
    }

    // Vertical line detection
    for ($x = 0; $x < $width; $x++) {
        $start_y = -1;
        for ($y = 0; $y < $height; $y++) {
            $color = imagecolorat($edge_image, $x, $y) & 0xFF;
            if ($color > 128) {
                if ($start_y == -1) {
                    $start_y = $y;
                }
            } else {
                if ($start_y != -1) {
                    if ($y - $start_y > $height * 0.1) {
                        $lines[] = [$x, $start_y, $x, $y - 1];
                    }
                    $start_y = -1;
                }
            }
        }
    }

	if (DEBUG) {
		echo "Total lines: " . count($lines) . "\n";
	}

    return $lines;
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

	if (DEBUG) {
		echo "File uploaded successfully.\n";
	}
    return $new_file_path;
}

function detect_stones($image, $filename) {
	$width = imagesx($image);
	$height = imagesy($image);

	$x_step = round($width / GOBAN_SIZE);
	$y_step = round($height / GOBAN_SIZE);

	$green = imagecolorallocate($image, 0, 255, 0);
	$red = imagecolorallocate($image, 255, 0, 0);
	$black = imagecolorallocate($image, 0, 0, 0);
	$white = imagecolorallocate($image, 255, 255, 255);

	$image_copy = imagecreatetruecolor($width, $height);
	imagecopy($image_copy, $image, 0, 0, 0, 0, $width, $height);

    if (DEBUG) {
		echo "Image size: $width x $height\n";
		echo "Grid size: $x_step x $y_step\n";
	}

	$potential_stone_locations = array();
	for ($y = 0; $y < $height - $y_step; $y += $y_step) {
		for ($x = 0; $x < $width - $x_step; $x += $x_step) {
			$mid_x = round($x + $x_step / 2);
			$mid_y = round($y + $y_step / 2);

			$potential_stone_locations[] = [$mid_x, $mid_y];

			imagerectangle($image_copy, $x, $y, $x + $x_step, $y + $y_step, $green);
			imagerectangle($image_copy, $mid_x-2, $mid_y-2, $mid_x+2, $mid_y+2, $green);
		}
	}

	$stones = array();
	$board_x = 0;
	$board_y = 0;
	foreach ($potential_stone_locations as $location) {
		$stone = test_a_stone($image, $location[0], $location[1], STONE_THRESHOLD);
		if ($stone) {
			imagefilledrectangle($image_copy, $location[0]-2, $location[1]-2, $location[0]+2, $location[1]+2, $red);
			$stones[] = [$location[0], $location[1], $board_x, $board_y];
		}
		$board_x++;
		if ($board_x == GOBAN_SIZE) {
			$board_x = 0;
			$board_y++;
		}
	}

	imagejpeg($image_copy, "uploads/{$filename}_stones.jpg");

	return $stones;
}

function test_a_stone($image, $start_x, $start_y, $threshold) {
	$brightness_index = 0;
	$square_size = 6;
	for ($x = $start_x-$square_size; $x <= $start_x+$square_size; $x++) {
		for ($y = $start_y-$square_size; $y <= $start_y+$square_size; $y++) {
			$color_index = imagecolorat($image, $x, $y);
			$rgba = imagecolorsforindex($image, $color_index);
			$brightness = brightness_from_rgb($rgba);
			$brightness_index += $brightness;
		}
	}
	$brightness_index = round($brightness_index / ($square_size * 2 + 1) ** 2);
	if (DEBUG) {
		echo "Brightness index ($start_x, $start_y): $brightness_index\n";
	}
	return $brightness_index < $threshold;
}

function detect_stone_colors($image, $stones) {
	$position = array();
	foreach ($stones as $stone) {
		$black = test_a_stone($image, $stone[0], $stone[1], WHITE_THRESHOLD);
		if ($black) {
			$position[$stone[3]][$stone[2]] = 'B';
		} else {
			$position[$stone[3]][$stone[2]] = 'W';
		}
	}
	return $position;
}

function create_goban_image($game_position, $name) {
	$goban_image = imagecreatefrompng('images/goban.png');
	$goban_width = imagesx($goban_image);
	$goban_height = imagesy($goban_image);
	$goban_x_step = round($goban_width / GOBAN_SIZE);
	$goban_y_step = round($goban_height / GOBAN_SIZE);

	if (DEBUG) {
		echo "Goban size: $goban_width x $goban_height\n";
		echo "Goban grid size: $goban_x_step x $goban_y_step\n";
	}

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

	$row_count = 0;
	foreach ($game_position as $row) {
		$column_count = 0;
		foreach ($row as $point) {
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

	if (DEBUG) {
		echo "Creating goban image...\n\n";
	}
	imagepng($goban_image, "gobans/{$name}_goban.png");
}

function game_position_from_stones($stone_position) {
	$sgf = '(AP[GobanReader:0.10];GM[1];FF[5];';

	$game_position = [];
	for ($board_x = 0; $board_x < GOBAN_SIZE; $board_x++) {
		$game_position[$board_x] = [];
		for ($board_y = 0; $board_y < GOBAN_SIZE; $board_y++) {
			if (isset($stone_position[$board_x][$board_y])) {
				if ($stone_position[$board_x][$board_y] == 'B') {
					$game_position[$board_x][$board_y] = 'B';
					$sgf .= 'AB[' . int2alphabet($board_y + 1)
						. int2alphabet($board_x + 1) . '];';
				} elseif ($stone_position[$board_x][$board_y] == 'W') {
					$game_position[$board_x][$board_y] = 'W';
					$sgf .= 'AW[' . int2alphabet($board_y + 1)
						. int2alphabet($board_x + 1) . '];';
				}
			} else {
				$game_position[$board_x][$board_y] = ' ';
			}
		}
	}

	$sgf .= ')';

	return [$game_position, $sgf];
}