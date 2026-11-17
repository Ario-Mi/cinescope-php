<?php
// ==========================================
// CineScope - OMDb Import
// Eingeloggte User duerfen JSON/XML holen und speichern
// ==========================================

require_once __DIR__ . '/include/security.php';
startSecureSession();
require_once __DIR__ . '/include/db.php';

// Dein OMDb API Key
$apiKey = appConfig('OMDB_API_KEY');

$fehler = '';
$meldung = '';
$rohText = '';
$film = null;
$suchTreffer = [];
$hatSearchAnfrage = false;

// Formular-Werte (fuer sticky form)
$title = inputText($_POST, 'title', '');
$year = inputText($_POST, 'year', '');
$imdbID = inputText($_POST, 'imdbID', '');
$searchText = inputText($_POST, 'search_text', '');
$suchFeld = inputText($_POST, 'such_feld', 'title');
$responseType = inputText($_POST, 'response_type', 'json'); // json oder xml
$plot = 'full';
$direktSpeichern = '0';
$trefferImdbID = inputText($_POST, 'treffer_imdbid', '');
$trefferAktion = inputText($_POST, 'treffer_aktion', '');

// Kleine Hilfsfunktion:
// Speichert einen einzelnen Film + JOIN-Daten in die DB.
function speichereFilmInDb(array $film, string &$fehler): bool
{
    if (($film['imdbID'] ?? '') === '' || ($film['title'] ?? '') === '') {
        $fehler = 'Film hat keine imdbID oder keinen Titel.';
        return false;
    }

    try {
        $pdo = getPDO();
        $pdo->beginTransaction();

        // 1) Film speichern oder updaten
        $find = $pdo->prepare("SELECT id FROM films WHERE imdbID = :imdbID LIMIT 1");
        $find->execute(['imdbID' => $film['imdbID']]);
        $filmId = (int) $find->fetchColumn();

        if ($filmId > 0) {
            $update = $pdo->prepare("
                UPDATE films
                SET
                    title = :title,
                    year = :year,
                    runtime = :runtime,
                    poster = :poster,
                    plot = :plot,
                    language = :language,
                    country = :country,
                    imdbRating = :imdbRating
                WHERE id = :id
            ");
            $update->execute([
                'title' => $film['title'],
                'year' => $film['year'],
                'runtime' => $film['runtime'],
                'poster' => $film['poster'],
                'plot' => $film['plot'],
                'language' => $film['language'],
                'country' => $film['country'],
                'imdbRating' => $film['imdbRating'],
                'id' => $filmId
            ]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO films (imdbID, title, year, runtime, poster, plot, language, country, imdbRating)
                VALUES (:imdbID, :title, :year, :runtime, :poster, :plot, :language, :country, :imdbRating)
            ");
            $insert->execute([
                'imdbID' => $film['imdbID'],
                'title' => $film['title'],
                'year' => $film['year'],
                'runtime' => $film['runtime'],
                'poster' => $film['poster'],
                'plot' => $film['plot'],
                'language' => $film['language'],
                'country' => $film['country'],
                'imdbRating' => $film['imdbRating']
            ]);
            $filmId = (int) $pdo->lastInsertId();
        }

        // 2) Alte JOINs loeschen (fuer sauberes Update)
        $pdo->prepare("DELETE FROM films_genres WHERE films_id = :id")->execute(['id' => $filmId]);
        $pdo->prepare("DELETE FROM films_directors WHERE films_id = :id")->execute(['id' => $filmId]);
        $pdo->prepare("DELETE FROM films_writers WHERE films_id = :id")->execute(['id' => $filmId]);
        $pdo->prepare("DELETE FROM films_actors WHERE films_id = :id")->execute(['id' => $filmId]);

        // 3) Genres speichern
        $genres = explode(',', (string) ($film['genre'] ?? ''));
        foreach ($genres as $genreText) {
            $wert = trim($genreText);
            if ($wert === '' || $wert === 'N/A') {
                continue;
            }

            $g = $pdo->prepare("SELECT id FROM genres WHERE genre = :x LIMIT 1");
            $g->execute(['x' => $wert]);
            $gid = (int) $g->fetchColumn();

            if ($gid < 1) {
                $gIns = $pdo->prepare("INSERT INTO genres (genre) VALUES (:x)");
                $gIns->execute(['x' => $wert]);
                $gid = (int) $pdo->lastInsertId();
            }

            $fg = $pdo->prepare("INSERT INTO films_genres (films_id, genres_id) VALUES (:f, :g)");
            $fg->execute(['f' => $filmId, 'g' => $gid]);
        }

        // 4) Directors speichern
        $directors = explode(',', (string) ($film['director'] ?? ''));
        foreach ($directors as $directorText) {
            $wert = trim($directorText);
            if ($wert === '' || $wert === 'N/A') {
                continue;
            }

            $d = $pdo->prepare("SELECT id FROM directors WHERE director = :x LIMIT 1");
            $d->execute(['x' => $wert]);
            $did = (int) $d->fetchColumn();

            if ($did < 1) {
                $dIns = $pdo->prepare("INSERT INTO directors (director) VALUES (:x)");
                $dIns->execute(['x' => $wert]);
                $did = (int) $pdo->lastInsertId();
            }

            $fd = $pdo->prepare("INSERT INTO films_directors (films_id, directors_id) VALUES (:f, :d)");
            $fd->execute(['f' => $filmId, 'd' => $did]);
        }

        // 5) Writers speichern
        $writers = explode(',', (string) ($film['writer'] ?? ''));
        foreach ($writers as $writerText) {
            $wert = trim($writerText);
            if ($wert === '' || $wert === 'N/A') {
                continue;
            }

            $w = $pdo->prepare("SELECT id FROM writers WHERE writer = :x LIMIT 1");
            $w->execute(['x' => $wert]);
            $wid = (int) $w->fetchColumn();

            if ($wid < 1) {
                $wIns = $pdo->prepare("INSERT INTO writers (writer) VALUES (:x)");
                $wIns->execute(['x' => $wert]);
                $wid = (int) $pdo->lastInsertId();
            }

            $fw = $pdo->prepare("INSERT INTO films_writers (films_id, writers_id) VALUES (:f, :w)");
            $fw->execute(['f' => $filmId, 'w' => $wid]);
        }

        // 6) Actors speichern
        $actors = explode(',', (string) ($film['actors'] ?? ''));
        foreach ($actors as $actorText) {
            $wert = trim($actorText);
            if ($wert === '' || $wert === 'N/A') {
                continue;
            }

            $a = $pdo->prepare("SELECT id FROM actors WHERE actor = :x LIMIT 1");
            $a->execute(['x' => $wert]);
            $aid = (int) $a->fetchColumn();

            if ($aid < 1) {
                $aIns = $pdo->prepare("INSERT INTO actors (actor) VALUES (:x)");
                $aIns->execute(['x' => $wert]);
                $aid = (int) $pdo->lastInsertId();
            }

            $fa = $pdo->prepare("INSERT INTO films_actors (films_id, actors_id) VALUES (:f, :a)");
            $fa->execute(['f' => $filmId, 'a' => $aid]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $fehler = reportAppError($e);
        return false;
    }
}

// Kleine Hilfsfunktion:
// Filtert Search-Treffer nach Feld (year/actor/director/genre).
function filterTrefferNachFeld(array $suchTreffer, string $suchFeld, string $suchText, string $apiKey): array
{
    $suchText = trim($suchText);
    if ($suchText === '' || $suchFeld === 'title') {
        return $suchTreffer;
    }

    $gefiltert = [];

    foreach ($suchTreffer as $treffer) {
        if ($suchFeld === 'year') {
            $jahr = (string) ($treffer['year'] ?? '');
            if (stripos($jahr, $suchText) !== false) {
                $gefiltert[] = $treffer;
            }
            continue;
        }

        $imdbID = (string) ($treffer['imdbID'] ?? '');
        if ($imdbID === '') {
            continue;
        }

        // Fuer actor/director/genre holen wir Kurz-Detail und filtern dann den Text.
        $url = 'https://www.omdbapi.com/?' . http_build_query([
            'apikey' => $apiKey,
            'r' => 'json',
            'plot' => 'full',
            'i' => $imdbID
        ]);

        $text = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10]]));
        if ($text === false || $text === '') {
            continue;
        }

        $data = json_decode($text, true);
        if (!is_array($data) || (($data['Response'] ?? 'False') !== 'True')) {
            continue;
        }

        $feldText = '';
        if ($suchFeld === 'actor') {
            $feldText = (string) ($data['Actors'] ?? '');
        }
        if ($suchFeld === 'director') {
            $feldText = (string) ($data['Director'] ?? '');
        }
        if ($suchFeld === 'genre') {
            $feldText = (string) ($data['Genre'] ?? '');
        }

        if ($feldText !== '' && stripos($feldText, $suchText) !== false) {
            $gefiltert[] = $treffer;
        }
    }

    return $gefiltert;
}

// Nur eingeloggte User duerfen importieren
$istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;

if (!$istEingeloggt) {
    $fehler = 'Bitte zuerst als Benutzer einloggen.';
} elseif ($apiKey === '') {
    $fehler = 'Die OMDb-Anbindung ist noch nicht lokal konfiguriert.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!in_array($responseType, ['json', 'xml'], true)) {
        $responseType = 'json';
    }
    if (!in_array($suchFeld, ['title', 'year', 'actor', 'director', 'genre'], true)) {
        $suchFeld = 'title';
    }

    // Wenn ein Treffer aus der Suchliste angeklickt wurde:
    // direkt per imdbID holen (und optional speichern).
    if ($trefferImdbID !== '') {
        $imdbID = $trefferImdbID;
        $searchText = '';
        if ($trefferAktion === 'save') {
            $direktSpeichern = '1';
        } else {
            $direktSpeichern = '0';
        }
    }

    if ($title === '' && $imdbID === '' && $searchText === '') {
        $fehler = 'Bitte Titel, IMDb-ID oder Suchbegriff eingeben.';
    } else {
        if ($searchText !== '') {
            $hatSearchAnfrage = true;
        }

        // OMDb URL bauen
        $params = [
            'apikey' => $apiKey,
            'r' => $responseType
        ];

        if ($searchText !== '') {
            // Bei year suchen wir mit y=... und einem Basiswort in s.
            if ($suchFeld === 'year') {
                $params['s'] = ($title !== '') ? $title : 'movie';
                $params['y'] = $searchText;
            } elseif (in_array($suchFeld, ['actor', 'director', 'genre'], true)) {
                // Bei actor/director/genre darf Title als Basis-Text genutzt werden.
                $params['s'] = ($title !== '') ? $title : $searchText;
            } else {
                $params['s'] = $searchText;
            }
        } elseif ($imdbID !== '') {
            $params['i'] = $imdbID;
            $params['plot'] = $plot;
        } else {
            $params['t'] = $title;
            $params['plot'] = $plot;
        }

        if ($year !== '' && $suchFeld !== 'year') {
            $params['y'] = $year;
        }

        $url = 'https://www.omdbapi.com/?' . http_build_query($params);
        $rohText = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10]]));

        if ($rohText === false || $rohText === '') {
            $fehler = 'OMDb Antwort ist leer. Bitte Netzwerk/API pruefen.';
        } else {
            // ==========================================
            // A) Antwort parsen (JSON oder XML)
            // ==========================================
            if ($responseType === 'xml') {
                $xml = @simplexml_load_string($rohText);

                if ($xml === false) {
                    $fehler = 'XML konnte nicht gelesen werden.';
                } elseif (strtolower((string) $xml['response']) !== 'true') {
                    $fehler = (string) $xml['error'];
                } elseif ($searchText !== '') {
                    // Search-Antwort in XML: mehrere result-Elemente
                    foreach ($xml->result as $r) {
                        $suchTreffer[] = [
                            'title' => (string) $r['title'],
                            'year' => (string) $r['year'],
                            'imdbID' => (string) $r['imdbID'],
                            'type' => (string) $r['type'],
                            'poster' => (string) $r['poster']
                        ];
                    }
                    $suchTreffer = filterTrefferNachFeld($suchTreffer, $suchFeld, $searchText, $apiKey);
                    $meldung = 'OMDb Suche erfolgreich (' . $suchFeld . '). Treffer: ' . count($suchTreffer);
                } elseif (!isset($xml->movie)) {
                    $fehler = 'Kein einzelner Film in XML gefunden.';
                } else {
                    $m = $xml->movie;
                    $film = [
                        'imdbID' => (string) $m['imdbID'],
                        'title' => (string) $m['title'],
                        'year' => (string) $m['year'],
                        'runtime' => (string) $m['runtime'],
                        'poster' => (string) $m['poster'],
                        'plot' => (string) $m['plot'],
                        'language' => (string) $m['language'],
                        'country' => (string) $m['country'],
                        'imdbRating' => (string) $m['imdbRating'],
                        'genre' => (string) $m['genre'],
                        'director' => (string) $m['director'],
                        'writer' => (string) $m['writer'],
                        'actors' => (string) $m['actors']
                    ];
                    $meldung = 'OMDb Daten erfolgreich geholt.';
                }
            } else {
                $data = json_decode($rohText, true);

                if (!is_array($data)) {
                    $fehler = 'JSON konnte nicht gelesen werden.';
                } elseif (($data['Response'] ?? 'False') !== 'True') {
                    $fehler = (string) ($data['Error'] ?? 'OMDb liefert keine Daten.');
                } elseif ($searchText !== '') {
                    // Search-Antwort in JSON: Feld "Search"
                    $liste = isset($data['Search']) && is_array($data['Search']) ? $data['Search'] : [];
                    foreach ($liste as $r) {
                        $suchTreffer[] = [
                            'title' => (string) ($r['Title'] ?? ''),
                            'year' => (string) ($r['Year'] ?? ''),
                            'imdbID' => (string) ($r['imdbID'] ?? ''),
                            'type' => (string) ($r['Type'] ?? ''),
                            'poster' => (string) ($r['Poster'] ?? '')
                        ];
                    }
                    $suchTreffer = filterTrefferNachFeld($suchTreffer, $suchFeld, $searchText, $apiKey);
                    $meldung = 'OMDb Suche erfolgreich (' . $suchFeld . '). Treffer: ' . count($suchTreffer);
                } else {
                    $film = [
                        'imdbID' => (string) ($data['imdbID'] ?? ''),
                        'title' => (string) ($data['Title'] ?? ''),
                        'year' => (string) ($data['Year'] ?? ''),
                        'runtime' => (string) ($data['Runtime'] ?? ''),
                        'poster' => (string) ($data['Poster'] ?? ''),
                        'plot' => (string) ($data['Plot'] ?? ''),
                        'language' => (string) ($data['Language'] ?? ''),
                        'country' => (string) ($data['Country'] ?? ''),
                        'imdbRating' => (string) ($data['imdbRating'] ?? ''),
                        'genre' => (string) ($data['Genre'] ?? ''),
                        'director' => (string) ($data['Director'] ?? ''),
                        'writer' => (string) ($data['Writer'] ?? ''),
                        'actors' => (string) ($data['Actors'] ?? '')
                    ];
                    $meldung = 'OMDb Daten erfolgreich geholt.';
                }
            }

            // ==========================================
            // B) Optional direkt in DB speichern
            // ==========================================
            if ($fehler === '' && is_array($film) && $direktSpeichern === '1') {
                if (speichereFilmInDb($film, $fehler)) {
                    $meldung .= ' Film wurde in Datenbank gespeichert.';
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
    <title>OMDb Import - CineScope</title>
    <link rel="stylesheet" href="css/omdb.css?v=3">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
    <section class="hero-box">
        <h1>OMDb Import (JSON/XML)</h1>
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
                    <label for="title">Titel:</label><br>
                    <input type="text" id="title" name="title" placeholder="z.B. The Matrix" value="<?php echo htmlspecialchars($title); ?>">
                </p>

                <p>
                    <label for="year">Jahr:</label><br>
                    <input type="text" id="year" name="year" placeholder="z.B. 1999" value="<?php echo htmlspecialchars($year); ?>">
                </p>

                <p>
                    <label for="imdbID">imdbID:</label><br>
                    <input type="text" id="imdbID" name="imdbID" placeholder="z.B. tt0133093" value="<?php echo htmlspecialchars($imdbID); ?>">
                </p>

                <p>
                    <label for="such_feld">Suche nach:</label><br>
                    <select id="such_feld" name="such_feld">
                        <option value="title" <?php echo $suchFeld === 'title' ? 'selected' : ''; ?>>Titel</option>
                        <option value="year" <?php echo $suchFeld === 'year' ? 'selected' : ''; ?>>Jahr</option>
                        <option value="actor" <?php echo $suchFeld === 'actor' ? 'selected' : ''; ?>>Schauspieler</option>
                        <option value="director" <?php echo $suchFeld === 'director' ? 'selected' : ''; ?>>Regie</option>
                        <option value="genre" <?php echo $suchFeld === 'genre' ? 'selected' : ''; ?>>Genre</option>
                    </select>
                </p>

                <p>
                    <label for="search_text">Suche:</label><br>
                    <input type="text" id="search_text" name="search_text" placeholder="z.B. matrix oder nolan" value="<?php echo htmlspecialchars($searchText); ?>">
                </p>

                <p>
                    <label for="response_type">Antwortformat:</label><br>
                    <select id="response_type" name="response_type">
                        <option value="json" <?php echo $responseType === 'json' ? 'selected' : ''; ?>>JSON</option>
                        <option value="xml" <?php echo $responseType === 'xml' ? 'selected' : ''; ?>>XML</option>
                    </select>
                </p>

                <p><button type="submit">Suchen</button></p>
            </form>
        </div>

        <?php if ($hatSearchAnfrage): ?>
            <div class="box">
                <h2>Suchtreffer</h2>
                <?php if (count($suchTreffer) === 0): ?>
                    <p>Keine Treffer gefunden. Bitte anderes Suchwort probieren.</p>
                <?php else: ?>
                    <div class="treffer-liste">
                        <?php foreach ($suchTreffer as $treffer): ?>
                            <div class="treffer-card">
                                <p class="treffer-title">
                                    <strong><?php echo htmlspecialchars($treffer['title'] ?? ''); ?></strong>
                                </p>
                                <p class="treffer-meta">
                                    <?php echo htmlspecialchars($treffer['year'] ?? ''); ?>
                                    -
                                    <?php echo htmlspecialchars($treffer['imdbID'] ?? ''); ?>
                                    -
                                    <?php echo htmlspecialchars($treffer['type'] ?? ''); ?>
                                </p>
                                <form method="post" action="" class="treffer-actions">
                <?php echo csrfField(); ?>
                                    <input type="hidden" name="treffer_imdbid" value="<?php echo htmlspecialchars($treffer['imdbID'] ?? ''); ?>">
                                    <input type="hidden" name="treffer_aktion" value="show">
                                    <input type="hidden" name="response_type" value="<?php echo htmlspecialchars($responseType); ?>">
                                    <button type="submit">Film holen</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

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
                <form method="post" action="">
                <?php echo csrfField(); ?>
                    <input type="hidden" name="treffer_imdbid" value="<?php echo htmlspecialchars($film['imdbID'] ?? ''); ?>">
                    <input type="hidden" name="treffer_aktion" value="save">
                    <input type="hidden" name="response_type" value="<?php echo htmlspecialchars($responseType); ?>">
                    <button type="submit">Speichern</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($rohText !== ''): ?>
            <div class="box">
                <h2>Rohantwort von OMDb</h2>
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
