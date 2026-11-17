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

// Film-ID aus URL lesen
$filmId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$film = null;
$fehler = '';
$meldung = '';
$kommentare = [];
$filmIstGespeichert = false;

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
                films.imdbRating
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $filmId]);
        $film = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$film) {
            $fehler = 'Film nicht gefunden.';
        } else {
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
                }
            }

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
