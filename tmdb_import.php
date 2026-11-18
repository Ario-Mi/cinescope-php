<?php
// ==========================================
// CineScope - TMDb Import
// Fuer neue Filme, die OMDb noch nicht kennt
// ==========================================

require_once __DIR__ . '/include/security.php';
startSecureSession();
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/tmdb.php';

$fehler = '';
$meldung = '';
$title = inputText($_POST, 'title', '');
$year = inputText($_POST, 'year', '');
$trefferListe = [];
$film = null;

$istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;

/** Namen aus der Crew-Liste, z.B. alle Personen mit dem Job "Director". */
function tmdbCrewNamen(array $crew, array $jobs): string
{
    $namen = [];
    foreach ($crew as $person) {
        $name = trim((string) ($person['name'] ?? ''));
        if ($name !== '' && in_array((string) ($person['job'] ?? ''), $jobs, true)) {
            $namen[] = $name;
        }
    }
    return implode(', ', array_unique($namen));
}

/** Namen aus einer TMDb-Liste wie genres oder production_countries. */
function tmdbListenNamen(array $liste): string
{
    $namen = [];
    foreach ($liste as $eintrag) {
        $name = trim((string) ($eintrag['name'] ?? ''));
        if ($name !== '') {
            $namen[] = $name;
        }
    }
    return implode(', ', $namen);
}

/** Wandelt die TMDb-Detailantwort in das Format unserer films-Tabelle um. */
function tmdbFilmAusDetails(array $details): array
{
    $tmdbId = (int) ($details['id'] ?? 0);
    $releaseDate = (string) ($details['release_date'] ?? '');
    $posterPath = (string) ($details['poster_path'] ?? '');
    $imdbId = trim((string) ($details['external_ids']['imdb_id'] ?? ''));
    $bewertung = (float) ($details['vote_average'] ?? 0);
    $crew = is_array($details['credits']['crew'] ?? null) ? $details['credits']['crew'] : [];
    $cast = is_array($details['credits']['cast'] ?? null) ? array_slice($details['credits']['cast'], 0, 8) : [];

    return [
        // Hat TMDb noch keine IMDb-ID, bekommt der Film eine eigene eindeutige ID.
        'imdbID' => preg_match('/^tt\d{5,10}$/D', $imdbId) ? $imdbId : 'tmdb_' . $tmdbId,
        'tmdbID' => (string) $tmdbId,
        'title' => mb_substr(trim((string) ($details['title'] ?? '')), 0, 200),
        'year' => preg_match('/^\d{4}/', $releaseDate) ? substr($releaseDate, 0, 4) : '',
        'runtime' => !empty($details['runtime']) ? (int) $details['runtime'] . ' min' : '',
        // Nur Bildpfade von TMDb selbst, keine fremden Adressen.
        'poster' => preg_match('#^/[A-Za-z0-9._-]+$#D', $posterPath) ? 'https://image.tmdb.org/t/p/w500' . $posterPath : '',
        'plot' => (string) ($details['overview'] ?? ''),
        'language' => mb_substr((string) ($details['original_language'] ?? ''), 0, 200),
        'country' => mb_substr(tmdbListenNamen($details['production_countries'] ?? []), 0, 200),
        'imdbRating' => $bewertung > 0 ? number_format($bewertung, 1, '.', '') : '',
        'trailer_key' => tmdbTrailerKeyFuerId($tmdbId),
        'genre' => tmdbListenNamen($details['genres'] ?? []),
        'director' => tmdbCrewNamen($crew, ['Director']),
        'writer' => tmdbCrewNamen($crew, ['Writer', 'Screenplay']),
        'actors' => tmdbListenNamen($cast),
    ];
}

/** Speichert Namen (z.B. Genres) in der Stammtabelle und verknuepft sie mit dem Film. */
function tmdbSpeichereVerknuepfung(PDO $pdo, int $filmId, string $text, string $art): void
{
    // Feste Liste statt Benutzereingabe: Tabellen- und Spaltennamen kann PDO nicht als Parameter binden.
    $tabellen = [
        'genre' => ['genres', 'genre', 'films_genres', 'genres_id'],
        'director' => ['directors', 'director', 'films_directors', 'directors_id'],
        'writer' => ['writers', 'writer', 'films_writers', 'writers_id'],
        'actor' => ['actors', 'actor', 'films_actors', 'actors_id'],
    ];
    [$tabelle, $spalte, $joinTabelle, $joinSpalte] = $tabellen[$art];

    foreach (explode(',', $text) as $eintrag) {
        $wert = mb_substr(trim($eintrag), 0, 150);
        if ($wert === '') {
            continue;
        }

        $find = $pdo->prepare("SELECT id FROM $tabelle WHERE $spalte = :wert LIMIT 1");
        $find->execute(['wert' => $wert]);
        $id = (int) $find->fetchColumn();
        if ($id < 1) {
            $pdo->prepare("INSERT INTO $tabelle ($spalte) VALUES (:wert)")->execute(['wert' => $wert]);
            $id = (int) $pdo->lastInsertId();
        }

        $pdo->prepare("INSERT IGNORE INTO $joinTabelle (films_id, $joinSpalte) VALUES (:film_id, :id)")
            ->execute(['film_id' => $filmId, 'id' => $id]);
    }
}

/** Speichert oder aktualisiert den Film in einer Transaktion. Gibt 'neu' oder 'vorhanden' zurueck. */
function tmdbSpeichereFilm(PDO $pdo, array $film): string
{
    $felder = ['imdbID', 'tmdbID', 'title', 'year', 'runtime', 'poster', 'plot', 'language', 'country', 'imdbRating', 'trailer_key'];
    $werte = array_intersect_key($film, array_flip($felder));

    $pdo->beginTransaction();
    try {
        $find = $pdo->prepare('SELECT id FROM films WHERE tmdbID = :tmdbID OR imdbID = :imdbID LIMIT 1');
        $find->execute(['tmdbID' => $film['tmdbID'], 'imdbID' => $film['imdbID']]);
        $filmId = (int) $find->fetchColumn();
        $ergebnis = $filmId > 0 ? 'vorhanden' : 'neu';

        if ($filmId > 0) {
            $setzen = implode(', ', array_map(fn ($feld) => "$feld = :$feld", $felder));
            $pdo->prepare("UPDATE films SET $setzen WHERE id = :id")->execute($werte + ['id' => $filmId]);
        } else {
            $spalten = implode(', ', $felder);
            $platzhalter = ':' . implode(', :', $felder);
            $pdo->prepare("INSERT INTO films ($spalten) VALUES ($platzhalter)")->execute($werte);
            $filmId = (int) $pdo->lastInsertId();
        }

        // Bei einem Update alte Verknuepfungen entfernen und neu eintragen
        foreach (['films_genres', 'films_directors', 'films_writers', 'films_actors'] as $joinTabelle) {
            $pdo->prepare("DELETE FROM $joinTabelle WHERE films_id = :id")->execute(['id' => $filmId]);
        }
        tmdbSpeichereVerknuepfung($pdo, $filmId, $film['genre'], 'genre');
        tmdbSpeichereVerknuepfung($pdo, $filmId, $film['director'], 'director');
        tmdbSpeichereVerknuepfung($pdo, $filmId, $film['writer'], 'writer');
        tmdbSpeichereVerknuepfung($pdo, $filmId, $film['actors'], 'actor');

        $pdo->commit();
        return $ergebnis;
    } catch (Throwable $e) {
        // Rollback: Entweder wird der ganze Film gespeichert oder gar nichts.
        $pdo->rollBack();
        throw $e;
    }
}

if (!$istEingeloggt) {
    $fehler = 'Bitte zuerst als Benutzer einloggen.';
} elseif (tmdbApiKey() === '') {
    $fehler = 'Für den TMDb-Import muss TMDB_API_KEY in include/config.local.php eingetragen sein.';
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $aktion = inputText($_POST, 'aktion', 'search');

    if ($aktion === 'search') {
        if ($title === '') {
            $fehler = 'Bitte Titel eingeben.';
        } elseif ($year !== '' && !preg_match('/^\d{4}$/D', $year)) {
            $fehler = 'Bitte das Jahr vierstellig eingeben, z.B. 2024.';
        } else {
            $parameter = ['query' => $title, 'language' => 'de-DE'];
            if ($year !== '') {
                $parameter['year'] = $year;
            }
            $daten = tmdbHoleJson('search/movie', $parameter);
            $trefferListe = is_array($daten['results'] ?? null) ? array_slice($daten['results'], 0, 8) : [];
            if (count($trefferListe) === 0) {
                $fehler = 'Kein Film bei TMDb gefunden.';
            } else {
                $meldung = 'TMDb-Suche erfolgreich. Treffer: ' . count($trefferListe);
            }
        }
    }

    if ($aktion === 'save') {
        $tmdbId = (int) inputText($_POST, 'tmdb_id', '0');
        $details = $tmdbId > 0
            ? tmdbHoleJson('movie/' . $tmdbId, ['language' => 'de-DE', 'append_to_response' => 'credits,external_ids'])
            : [];

        if (empty($details['id']) || trim((string) ($details['title'] ?? '')) === '') {
            $fehler = 'Der Film konnte bei TMDb nicht geladen werden.';
        } else {
            try {
                $film = tmdbFilmAusDetails($details);
                $ergebnis = tmdbSpeichereFilm(getPDO(), $film);
                $meldung = $ergebnis === 'vorhanden'
                    ? 'Film war schon vorhanden und wurde aktualisiert.'
                    : 'Film wurde aus TMDb gespeichert.';
            } catch (Throwable $e) {
                $film = null;
                $fehler = reportAppError($e);
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
    <title>TMDb Import - CineScope</title>
    <link rel="stylesheet" href="css/omdb.css?v=4">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
        <section class="hero-box">
            <h1>TMDb Import</h1>
            <p>Für neue Filme, die OMDb noch nicht findet.</p>
        </section>

        <?php if ($fehler !== ''): ?>
            <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
        <?php endif; ?>

        <?php if ($meldung !== ''): ?>
            <p class="meldung"><?php echo htmlspecialchars($meldung); ?></p>
        <?php endif; ?>

        <?php if ($istEingeloggt && tmdbApiKey() !== ''): ?>
            <div class="box">
                <form method="post" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="aktion" value="search">
                    <p>
                        <label for="title">Titel:</label><br>
                        <input type="text" id="title" name="title" maxlength="200" placeholder="z.B. Avatar" value="<?php echo htmlspecialchars($title); ?>" required>
                    </p>
                    <p>
                        <label for="year">Jahr (optional):</label><br>
                        <input type="text" id="year" name="year" maxlength="4" inputmode="numeric" placeholder="z.B. 2026" value="<?php echo htmlspecialchars($year); ?>">
                    </p>
                    <p><button type="submit">Bei TMDb suchen</button></p>
                </form>
            </div>

            <?php if (count($trefferListe) > 0): ?>
                <div class="box">
                    <h2>Suchtreffer</h2>
                    <div class="treffer-liste">
                        <?php foreach ($trefferListe as $treffer): ?>
                            <div class="treffer-card">
                                <p class="treffer-title"><strong><?php echo htmlspecialchars((string) ($treffer['title'] ?? '')); ?></strong></p>
                                <p class="treffer-meta">
                                    <?php echo htmlspecialchars(substr((string) ($treffer['release_date'] ?? ''), 0, 4)); ?>
                                    · TMDb-ID <?php echo (int) ($treffer['id'] ?? 0); ?>
                                </p>
                                <p><?php echo htmlspecialchars((string) ($treffer['overview'] ?? '')); ?></p>
                                <form method="post" action="" class="treffer-actions">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="aktion" value="save">
                                    <input type="hidden" name="tmdb_id" value="<?php echo (int) ($treffer['id'] ?? 0); ?>">
                                    <button type="submit">Film speichern</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (is_array($film)): ?>
                <div class="box">
                    <h2>Gespeicherter Film</h2>
                    <p><strong>Titel:</strong> <?php echo htmlspecialchars($film['title']); ?></p>
                    <p><strong>Jahr:</strong> <?php echo htmlspecialchars($film['year']); ?></p>
                    <p><strong>Trailer:</strong> <?php echo $film['trailer_key'] !== '' ? 'gefunden' : 'nicht vorhanden'; ?></p>
                    <p><a href="index.php">Zur Filmliste</a></p>
                </div>
            <?php endif; ?>
        <?php elseif (!$istEingeloggt): ?>
            <div class="box">
                <p><a href="login.php">Zur Anmeldung</a> | <a href="start.php">Zur Startseite</a></p>
            </div>
        <?php endif; ?>

    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>
