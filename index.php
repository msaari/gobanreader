<html>
<head>
	<title>Goban-lukija</title>
	<link rel="stylesheet" type="text/css" href="style.css" />
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
</head>
<body>
	<h1>Goban-lukija</h1>
	<form enctype="multipart/form-data" action="gobanreader.php" method="post">
		<h2>Laudan kuva</h2>
		<label for="board" class="drop-container" id="dropcontainer">
			<span class="drop-title">Raahaa kuva tähän</span>
			tai
			<input type="file" id="board" name="board" accept="image/*" required>
		</label>

		<p>Ota kuva mahdollisimman suoraan laudan yläpuolelta. Rajaa kuva niin, että lauta on kokonaisuudessaan kuvassa. Laudan ulkoreunojen tulee olla kuvassa mukana.</p>

		<h2>Pisteenlasku</h2>
		<div class="row"><label for="white_captures">Vangitut valkoiset:</label> <input type="text" inputmode="numeric" pattern="[0-9]*" name="white_captures" value="0" /></div>
		<div class="row"><label for="black_captures">Vangitut mustat:</label> <input type="text" inputmode="numeric" pattern="[0-9]*" name="black_captures" value="0" /></div>
		<div class="row"><label for="komi">Komi:</label> <input type="text" inputmode="numeric" pattern="[0-9,\.]*" name="komi" value="6.5" /></div>

		<input type="submit" value="Lähetä" />
	</form>
</body>

