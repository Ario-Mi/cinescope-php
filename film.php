<?php
// ==========================================
// CineScope - Schritt 3b:
// Einzelansicht von einem Film
// ==========================================

// Session starten, damit wir User-Login lesen koennen
require_once __DIR__ . '/include/security.php';
startSecureSession();

// Zentrale DB-Funktion laden
require_once __DIR__ . '/include/db.php';
require_once __DIR__ . '/include/tmdb.php';

// Film-ID aus URL lesen
$filmId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$film = null;
$fehler = '';
$meldung = '';
$kommentare = [];
$filmIstGespeichert = false;
$ratingDurchschnitt = 0.0;
$ratingAnzahl = 0;
$eigeneBewertung = 0;

if ($filmId < 1) {
    http_response_code(400);
    $fehler = 'Ungültige Film-ID.';
} else {
    try {
        // PDO-Verbindung aus include/db.php holen
        $pdo = getPDO();
        $exists = $pdo->prepare('SELECT id FROM films WHERE id = :id');
        $exists->execute(['id' => $filmId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            $fehler = 'Film nicht gefunden.';
        }

        // ==========================================
        // Film speichern (Merkliste) - nur eingeloggt
        // ==========================================
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'save_film'
            && $fehler === ''
        ) {
            $istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

            if (!$istEingeloggt || $userId < 1) {
                $fehler = 'Bitte zuerst einloggen, um Filme zu speichern.';
            } else {
                // Pruefen, ob der Film schon gespeichert wurde
                $checkSaveSql = "
                    SELECT id
                    FROM saved_films
                    WHERE users_id = :users_id
                    AND films_id = :films_id
                    LIMIT 1
                ";
                $checkSave = $pdo->prepare($checkSaveSql);
                $checkSave->execute([
                    'users_id' => $userId,
                    'films_id' => $filmId
                ]);

                if ($checkSave->fetch()) {
                    $meldung = 'Film ist schon in deiner Merkliste.';
                } else {
                    $saveSql = "
                        INSERT INTO saved_films (users_id, films_id)
                        VALUES (:users_id, :films_id)
                    ";
                    $save = $pdo->prepare($saveSql);
                    $save->execute([
                        'users_id' => $userId,
                        'films_id' => $filmId
                    ]);
                    $meldung = 'Film wurde in deiner Merkliste gespeichert.';
                }
            }
        }

        // ==========================================
        // Kommentar speichern (nur fuer eingeloggte User)
        // ==========================================
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'add_comment'
            && $fehler === ''
        ) {
            $istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            $text = inputText($_POST, 'kommentar', '');

            if (!$istEingeloggt || $userId < 1) {
                $fehler = 'Nur eingeloggte Benutzer dürfen Kommentare schreiben.';
            } elseif (mb_strlen($text) > 5000) {
                $fehler = 'Der Kommentar darf maximal 5000 Zeichen enthalten.';
            } elseif ($text === '') {
                $fehler = 'Kommentar darf nicht leer sein.';
            } else {
                $insertSql = "
                    INSERT INTO comments (users_id, films_id, kommentar)
                    VALUES (:users_id, :films_id, :kommentar)
                ";
                $insert = $pdo->prepare($insertSql);
                $insert->execute([
                    'users_id' => $userId,
                    'films_id' => $filmId,
                    'kommentar' => $text
                ]);
                $meldung = 'Kommentar gespeichert.';
            }
        }

        // ==========================================
        // Bewertung speichern oder aendern (1 bis 5 Sterne, nur eingeloggt)
        // ==========================================
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'add_rating'
            && $fehler === ''
        ) {
            $istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            $rating = (int) inputText($_POST, 'rating', '0');

            if (!$istEingeloggt || $userId < 1) {
                $fehler = 'Bitte zuerst einloggen, um den Film zu bewerten.';
            } elseif ($rating < 1 || $rating > 5) {
                $fehler = 'Bitte eine Bewertung von 1 bis 5 auswählen.';
            } else {
                // UNIQUE(users_id, films_id): pro Benutzer und Film gibt es nur eine Bewertung.
                // Eine zweite Bewertung ersetzt deshalb die erste, statt eine neue Zeile anzulegen.
                $ratingSql = "
                    INSERT INTO ratings (users_id, films_id, rating)
                    VALUES (:users_id, :films_id, :rating)
                    ON DUPLICATE KEY UPDATE rating = :rating_update
                ";
                $ratingStmt = $pdo->prepare($ratingSql);
                $ratingStmt->execute([
                    'users_id' => $userId,
                    'films_id' => $filmId,
                    'rating' => $rating,
                    'rating_update' => $rating
                ]);
                $meldung = 'Bewertung wurde gespeichert.';
            }
        }

        // ==========================================
        // Eigene Bewertung loeschen
        // ==========================================
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'delete_rating'
            && $fehler === ''
        ) {
            $istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;
            $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

            if (!$istEingeloggt || $userId < 1) {
                $fehler = 'Bitte zuerst einloggen, um deine Bewertung zu löschen.';
            } else {
                // users_id UND films_id: Es wird nur die eigene Bewertung fuer diesen Film geloescht.
                $deleteRating = $pdo->prepare('DELETE FROM ratings WHERE users_id = :users_id AND films_id = :films_id');
                $deleteRating->execute([
                    'users_id' => $userId,
                    'films_id' => $filmId
                ]);
                $meldung = 'Bewertung wurde gelöscht.';
            }
        }

        // Detail + JOIN + COUNT in einer Abfrage
        $sql = "
            SELECT
                films.id,
                films.imdbID,
                films.title,
                films.year,
                films.runtime,
                films.poster,
                films.plot,
                films.language,
                films.country,
                films.imdbRating,
                films.trailer_key,
                GROUP_CONCAT(DISTINCT genres.genre SEPARATOR ', ') AS genres,
                GROUP_CONCAT(DISTINCT directors.director SEPARATOR ', ') AS directors,
                GROUP_CONCAT(DISTINCT writers.writer SEPARATOR ', ') AS writers,
                GROUP_CONCAT(DISTINCT actors.actor SEPARATOR ', ') AS actors,
                COUNT(DISTINCT films_actors.id) AS actors_count
            FROM films
            LEFT JOIN films_genres ON films.id = films_genres.films_id
            LEFT JOIN genres ON genres.id = films_genres.genres_id
            LEFT JOIN films_directors ON films.id = films_directors.films_id
            LEFT JOIN directors ON directors.id = films_directors.directors_id
            LEFT JOIN films_writers ON films.id = films_writers.films_id
            LEFT JOIN writers ON writers.id = films_writers.writers_id
            LEFT JOIN films_actors ON films.id = films_actors.films_id
            LEFT JOIN actors ON actors.id = films_actors.actors_id
            WHERE films.id = :id
            GROUP BY
                films.id,
                films.imdbID,
                films.title,
                films.year,
                films.runtime,
                films.poster,
                films.plot,
                films.language,
                films.country,
                films.imdbRating,
                films.trailer_key
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $filmId]);
        $film = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$film) {
            $fehler = 'Film nicht gefunden.';
        } else {
            // Trailer einmalig bei TMDb suchen, wenn noch nie gesucht wurde (NULL).
            // Ein leerer Text bedeutet "gesucht, aber keinen Trailer gefunden"; dann fragen
            // wir TMDb nicht bei jedem Seitenaufruf erneut.
            if ($film['trailer_key'] === null && tmdbApiKey() !== '') {
                $gefundenerKey = tmdbFindeTrailerKey((string) $film['title'], (string) $film['year']);
                $trailerUpdate = $pdo->prepare('UPDATE films SET trailer_key = :trailer_key WHERE id = :id');
                $trailerUpdate->execute([
                    'trailer_key' => $gefundenerKey,
                    'id' => $filmId
                ]);
                $film['trailer_key'] = $gefundenerKey;
            }

            // Speichern-Status holen (damit wir im Button den Status zeigen koennen)
            if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true) {
                $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
                if ($userId > 0) {
                    $savedCheckSql = "
                        SELECT id
                        FROM saved_films
                        WHERE users_id = :users_id
                        AND films_id = :films_id
                        LIMIT 1
                    ";
                    $savedCheck = $pdo->prepare($savedCheckSql);
                    $savedCheck->execute([
                        'users_id' => $userId,
                        'films_id' => $filmId
                    ]);
                    $filmIstGespeichert = (bool) $savedCheck->fetch();

                    // Eigene Bewertung laden, damit die Sterne im Formular markiert sind.
                    $ownRating = $pdo->prepare('SELECT rating FROM ratings WHERE users_id = :users_id AND films_id = :films_id LIMIT 1');
                    $ownRating->execute([
                        'users_id' => $userId,
                        'films_id' => $filmId
                    ]);
                    $eigeneBewertung = (int) $ownRating->fetchColumn();
                }
            }

            // Durchschnitt und Anzahl aller Bewertungen fuer diesen Film
            $ratingInfo = $pdo->prepare('SELECT AVG(rating) AS durchschnitt, COUNT(id) AS anzahl FROM ratings WHERE films_id = :films_id');
            $ratingInfo->execute(['films_id' => $filmId]);
            $ratingZeile = $ratingInfo->fetch(PDO::FETCH_ASSOC);
            $ratingDurchschnitt = (float) ($ratingZeile['durchschnitt'] ?? 0);
            $ratingAnzahl = (int) ($ratingZeile['anzahl'] ?? 0);

            // ==========================================
            // Kommentare zum Film holen
            // ==========================================
            $kommentarSql = "
                SELECT
                    comments.kommentar,
                    comments.erstellt_am,
                    users.alias
                FROM comments
                INNER JOIN users ON users.id = comments.users_id
                WHERE comments.films_id = :film_id
                ORDER BY comments.id DESC
            ";
            $kommentarStmt = $pdo->prepare($kommentarSql);
            $kommentarStmt->execute(['film_id' => $filmId]);
            $kommentare = $kommentarStmt->fetchAll(PDO::FETCH_ASSOC);
        }
} catch (PDOException $e) {
        $fehler = reportAppError($e);
    }
}
require __DIR__ . '/views/templates/film_template.php';
