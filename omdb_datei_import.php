<?php
// ==========================================
// CineScope - OMDb Datei Import
// Angemeldete User duerfen JSON/XML aus Datei lesen
// ==========================================

require_once __DIR__ . '/include/security.php';
startSecureSession();

$fehler = '';
$meldung = '';
$rohText = '';
$film = null;

// Nur eingeloggte User duerfen diese Seite nutzen
$istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;

// Formular-Werte
$dateiName = inputText($_POST, 'datei_name', 'beispiel_film.json');
$responseType = inputText($_POST, 'response_type', 'json');

if (!$istEingeloggt) {
    $fehler = 'Bitte zuerst als Benutzer einloggen.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sicherheitsregel: nur Dateiname erlauben (kein Pfad)
    $nurName = basename($dateiName);

    // Fester Ordner fuer JSON/XML-Dateien
    $dateiPfad = __DIR__ . '/data/files/' . $nurName;

    if (!file_exists($dateiPfad)) {
        $fehler = 'Datei nicht gefunden: ' . $nurName;
    } else {
        $rohText = (string) @file_get_contents($dateiPfad);

        if ($rohText === '') {
            $fehler = 'Datei ist leer oder nicht lesbar.';
        } else {
            // ------------------------------------------
            // A) JSON lesen
            // ------------------------------------------
            if ($responseType === 'json') {
                $data = json_decode($rohText, true);

                if (!is_array($data)) {
                    $fehler = 'JSON konnte nicht gelesen werden.';
                } else {
                    $film = [
                        'title' => (string) ($data['Title'] ?? ''),
                        'year' => (string) ($data['Year'] ?? ''),
                        'imdbID' => (string) ($data['imdbID'] ?? ''),
                        'runtime' => (string) ($data['Runtime'] ?? ''),
                        'genre' => (string) ($data['Genre'] ?? ''),
                        'director' => (string) ($data['Director'] ?? ''),
                        'writer' => (string) ($data['Writer'] ?? ''),
                        'actors' => (string) ($data['Actors'] ?? ''),
                        'language' => (string) ($data['Language'] ?? ''),
                        'country' => (string) ($data['Country'] ?? ''),
                        'imdbRating' => (string) ($data['imdbRating'] ?? ''),
                        'plot' => (string) ($data['Plot'] ?? '')
                    ];
                    $meldung = 'JSON-Datei erfolgreich gelesen.';
                }
            }

            // ------------------------------------------
            // B) XML lesen
            // ------------------------------------------
            if ($responseType === 'xml') {
                $xml = @simplexml_load_string($rohText);

                if ($xml === false) {
                    $fehler = 'XML konnte nicht gelesen werden.';
                } elseif (!isset($xml->movie)) {
                    $fehler = 'Kein movie-Element in XML gefunden.';
                } else {
                    $m = $xml->movie;
                    $film = [
                        'title' => (string) $m['title'],
                        'year' => (string) $m['year'],
                        'imdbID' => (string) $m['imdbID'],
                        'runtime' => (string) $m['runtime'],
                        'genre' => (string) $m['genre'],
                        'director' => (string) $m['director'],
                        'writer' => (string) $m['writer'],
                        'actors' => (string) $m['actors'],
                        'language' => (string) $m['language'],
                        'country' => (string) $m['country'],
                        'imdbRating' => (string) $m['imdbRating'],
                        'plot' => (string) $m['plot']
                    ];
                    $meldung = 'XML-Datei erfolgreich gelesen.';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OMDb Datei Import - CineScope</title>
    <link rel="stylesheet" href="css/omdb.css?v=3">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
    <section class="hero-box">
        <h1>OMDb Datei Import (JSON/XML)</h1>
        <p><a href="index.php">Zurück zur Startseite</a></p>
    </section>

    <?php if ($fehler !== ''): ?>
        <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
    <?php endif; ?>

    <?php if ($meldung !== ''): ?>
        <p class="meldung"><?php echo htmlspecialchars($meldung); ?></p>
    <?php endif; ?>

    <?php if ($istEingeloggt): ?>
        <div class="box">
            <form method="post" action="">
                <?php echo csrfField(); ?>
                <p>
                    <label for="datei_name">Dateiname (im Ordner data/files):</label><br>
                    <input type="text" id="datei_name" name="datei_name" value="<?php echo htmlspecialchars($dateiName); ?>" required>
                </p>

                <p>
                    <label for="response_type">Typ:</label><br>
                    <select id="response_type" name="response_type">
                        <option value="json" <?php echo $responseType === 'json' ? 'selected' : ''; ?>>JSON</option>
                        <option value="xml" <?php echo $responseType === 'xml' ? 'selected' : ''; ?>>XML</option>
                    </select>
                </p>

                <p><button type="submit">Datei lesen</button></p>
            </form>

            <p>Beispiele:</p>
            <ul>
                <li>`beispiel_film.json`</li>
                <li>`beispiel_film.xml`</li>
            </ul>
        </div>

        <?php if (is_array($film)): ?>
            <div class="box">
                <h2>Gelesene Filmdaten</h2>
                <p><strong>Titel:</strong> <?php echo htmlspecialchars($film['title'] ?? ''); ?></p>
                <p><strong>Jahr:</strong> <?php echo htmlspecialchars($film['year'] ?? ''); ?></p>
                <p><strong>imdbID:</strong> <?php echo htmlspecialchars($film['imdbID'] ?? ''); ?></p>
                <p><strong>Laufzeit:</strong> <?php echo htmlspecialchars($film['runtime'] ?? ''); ?></p>
                <p><strong>Genre:</strong> <?php echo htmlspecialchars($film['genre'] ?? ''); ?></p>
                <p><strong>Regie:</strong> <?php echo htmlspecialchars($film['director'] ?? ''); ?></p>
                <p><strong>Drehbuch:</strong> <?php echo htmlspecialchars($film['writer'] ?? ''); ?></p>
                <p><strong>Schauspieler:</strong> <?php echo htmlspecialchars($film['actors'] ?? ''); ?></p>
                <p><strong>Sprache:</strong> <?php echo htmlspecialchars($film['language'] ?? ''); ?></p>
                <p><strong>Land:</strong> <?php echo htmlspecialchars($film['country'] ?? ''); ?></p>
                <p><strong>IMDb-Wertung:</strong> <?php echo htmlspecialchars($film['imdbRating'] ?? ''); ?></p>
                <p><strong>Handlung:</strong> <?php echo htmlspecialchars($film['plot'] ?? ''); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($rohText !== ''): ?>
            <div class="box">
                <h2>Rohinhalt der Datei</h2>
                <pre><?php echo htmlspecialchars($rohText); ?></pre>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="box">
            <p>Bitte zuerst als Benutzer einloggen.</p>
            <p><a href="start.php">Zur Startseite</a></p>
        </div>
    <?php endif; ?>
    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>
