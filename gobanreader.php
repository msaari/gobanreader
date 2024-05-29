<html>
<head>
    <title>Goban-lukija</title>
	<link rel="stylesheet" type="text/css" href="style.css" />
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
</head>
<body>
<?php

require_once 'functions.php';

set_time_limit(90);

$time = microtime(true);

define('CONTRAST', -50);
define('BRIGHTNESS', 20);
define('GOBAN_SIZE', 19);
define('STONE_THRESHOLD', 40);
define('WHITE_THRESHOLD', 100);

if (isset($_REQUEST['debug'])) {
    define('DEBUG', true);
    echo "<h2>Debugging enabled</h2>\n";
    echo "<pre>";
} else {
    define('DEBUG', false);
}

/*
// LOCAL TESTING MODE
$image = imagecreatefromjpeg('uploads/20240528050958_3687.jpg');
$name = "20240528050958_3687";
$edge_image = imagecreatefromjpeg('uploads/20240528050958_3687_edge.jpg');
$_REQUEST['white_captures'] = 1;
$_REQUEST['black_captures'] = 11;
$_REQUEST['komi'] = 6.5;
*/

$src = validate_file();
$name = pathinfo($src, PATHINFO_FILENAME);

$image = imagecreatefromjpeg($src);

$image = create_cropped_image($image);
$gray_image = create_grayscale_image($image, $name);
$edge_image = create_edge_image($gray_image, $name);
$bounding_box = find_goban_edges($edge_image, $name);

$edge_image = $bounding_box['image'];
unset($bounding_box['image']);

$cropped_image = imagecrop($image, $bounding_box);
imagejpeg($cropped_image, "uploads/{$name}_cropped.jpg");

$cropped_edge_image = imagecrop($edge_image, $bounding_box);
$stones = detect_stones($cropped_edge_image, $name);
$stone_position = detect_stone_colors($cropped_image, $stones);

list($game_position, $sgf) = game_position_from_stones($stone_position);

analyze_game_position($game_position);

if (DEBUG) {
    print_game_position($game_position);
}

$goban_image = create_goban_image($game_position, $name);

if (DEBUG) {
    echo "</pre>";
}

count_score($game_position, $_REQUEST['white_captures'], $_REQUEST['black_captures'], $_REQUEST['komi']);

$elapsed = round(microtime(true) - $time, 1);
?>

<h2>Board</h2>
<img src="gobans/<?php echo $name; ?>_goban.png" alt="Goban" id="goban" />

<h2>SGF</h2>

<textarea cols='80' rows='5' id="sgf"><?php echo $sgf; ?></textarea>

<div id="footer">
<p>Mikko Saari (mikko@mikkosaari.fi) | Elapsed time: <?php echo $elapsed; ?> s</p>
</div>
</body>
</html>