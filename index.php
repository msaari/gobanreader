<html>
<head>
	<title>Goban-lukija</title>
</head>
<body>
	<form enctype="multipart/form-data" action="gobanreader.php" method="post">
		<h2>Laudan kuva</h2>
		<input type="file" name="board" />

		<p>Ota kuva mahdollisimman suoraan laudan yläpuolelta. Rajaa kuva niin, että lauta on kokonaisuudessaan kuvassa. Laudan ulkoreunojen tulee olla kuvassa mukana.</p>

		<h2>Pisteenlasku</h2>
		<p>Vangitut valkoiset: <input type="number" name="white_captures" value="0" /></p>
		<p>Vangitut mustat: <input type="number" name="black_captures" value="0" /></p>
		<p>Komi: <input type="number" name="komi" value="6.5" /></p>

		<input type="submit" value="Lähetä" />
	</form>
</body>