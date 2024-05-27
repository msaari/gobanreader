<html>
<head>
    <title>GobanReader</title>
</head>
<body>
    <pre>
<?php

require_once 'functions.php';

$time = microtime(true);

define('CONTRAST', -50);
define('BRIGHTNESS', 20);
$goban_size = 19;

$src = validate_file();
$name = pathinfo($src, PATHINFO_FILENAME);

$image = imagecreatefromjpeg($src);

$image = create_cropped_image($image);
$gray_image = create_grayscale_image($image, $name);
$edge_image = create_edge_image($gray_image, $name);
$bounding_box = find_goban_edges($edge_image, $name);

echo "Cropping...";
$cropped_image = imagecrop($image, $bounding_box);
imagejpeg($cropped_image, "uploads/{$name}_cropped.jpg");

$matrix = create_brightness_matrix($cropped_image, $goban_size, $name);
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
imagepng($goban_image, "gobans/{$name}_goban.png");

count_score($game_position);

echo "SGF:\n";

$sgf .= ')';
echo $sgf;

$elapsed = round(microtime(true) - $time, 1);
echo "\nElapsed time: $elapsed s\n";
?>
</pre>

<img src="gobans/<?php echo $name; ?>_goban.png" alt="Goban" />

</body>
</html>