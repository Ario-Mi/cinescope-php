<?php
// ==========================================
// CineScope - Schritt 5:
// Frontend mit Register/Login + Film-Liste
// ==========================================

// 1) Session starten (Login-Status fuer User)
require_once __DIR__ . '/include/security.php';
startSecureSession();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'user_logout') {
    unset($_SESSION['user_login'], $_SESSION['user_id'], $_SESSION['user_alias']);
    session_regenerate_id(true);
    header('Location: index.php');
    exit;
}

// 2) Zentrale DB-Funktion laden
require_once __DIR__ . '/include/db.php';
$siteName = 'CineScope';

// 3) Wieviele Filme pro Seite?
$proSeite = 4;

// Suchbegriff aus URL lesen (?q=matrix)
$suchbegriff = inputText($_GET, 'q', '');
$suchFeld = inputText($_GET, 'such_feld', 'title');
if (!in_array($suchFeld, ['title', 'year', 'actor', 'director', 'genre'], true)) {
    $suchFeld = 'title';
}

// 4) Aktuelle Seite aus URL holen (?seite=2)
$seite = isset($_GET['seite']) ? (int) $_GET['seite'] : 1;
if ($seite < 1) {
    $seite = 1;
}

// 5) Startposition fuer SQL LIMIT berechnen
$start = ($seite - 1) * $proSeite;

// 6) Standardwerte
$filme = [];
$seitenAnzahl = 1;
$gesamt = 0;
$fehler = '';
$meldung = '';
$meineMerkliste = [];

// Datenbankverbindung fuer Film-Liste und Merkliste aufbauen
try {
    // PDO-Verbindung aus include/db.php holen
    $pdo = getPDO();

    // ==========================================
    // Film-Liste (wie vorher)
    // ==========================================
    // Wenn User eingeloggt ist, eigene Merkliste laden
    if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true) {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        if ($userId > 0) {
            $savedSql = "
                SELECT films.id, films.title
                FROM saved_films
                INNER JOIN films ON films.id = saved_films.films_id
                WHERE saved_films.users_id = :users_id
                ORDER BY saved_films.id DESC
            ";
            $savedStmt = $pdo->prepare($savedSql);
            $savedStmt->execute(['users_id' => $userId]);
            $meineMerkliste = $savedStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // Gesamtzahl der Filme lesen (fuer Pagination)
    // Suche ist ueber ein ausgewaehltes Feld (title/year/actor/director/genre).
    if ($suchbegriff !== '') {
        $joinSql = '';
        $whereSql = "films.title LIKE :q";

        if ($suchFeld === 'year') {
            $whereSql = "films.year LIKE :q";
        }
        if ($suchFeld === 'actor') {
            $joinSql .= " LEFT JOIN films_actors ON films.id = films_actors.films_id ";
            $joinSql .= " LEFT JOIN actors ON actors.id = films_actors.actors_id ";
            $whereSql = "actors.actor LIKE :q";
        }
        if ($suchFeld === 'director') {
            $joinSql .= " LEFT JOIN films_directors ON films.id = films_directors.films_id ";
            $joinSql .= " LEFT JOIN directors ON directors.id = films_directors.directors_id ";
            $whereSql = "directors.director LIKE :q";
        }
        if ($suchFeld === 'genre') {
            $joinSql .= " LEFT JOIN films_genres ON films.id = films_genres.films_id ";
            $joinSql .= " LEFT JOIN genres ON genres.id = films_genres.genres_id ";
            $whereSql = "genres.genre LIKE :q";
        }

        $countSql = "
            SELECT COUNT(DISTINCT films.id)
            FROM films
            $joinSql
            WHERE $whereSql
        ";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute(['q' => '%' . $suchbegriff . '%']);
        $gesamt = (int) $countStmt->fetchColumn();
    } else {
        $gesamt = (int) $pdo->query("SELECT COUNT(id) FROM films")->fetchColumn();
    }
    $seitenAnzahl = max(1, (int) ceil($gesamt / $proSeite));

    // Falls jemand zu grosse Seite in URL eingibt
    if ($seite > $seitenAnzahl) {
        $seite = $seitenAnzahl;
        $start = ($seite - 1) * $proSeite;
    }

    // Nur 4 Filme fuer die aktuelle Seite holen
    // Falls Suchbegriff da ist, filtern wir nach dem ausgewaehlten Feld.
    if ($suchbegriff !== '') {
        $joinSql = '';
        $whereSql = "films.title LIKE :q";

        if ($suchFeld === 'year') {
            $whereSql = "films.year LIKE :q";
        }
        if ($suchFeld === 'actor') {
            $joinSql .= " LEFT JOIN films_actors ON films.id = films_actors.films_id ";
            $joinSql .= " LEFT JOIN actors ON actors.id = films_actors.actors_id ";
            $whereSql = "actors.actor LIKE :q";
        }
        if ($suchFeld === 'director') {
            $joinSql .= " LEFT JOIN films_directors ON films.id = films_directors.films_id ";
            $joinSql .= " LEFT JOIN directors ON directors.id = films_directors.directors_id ";
            $whereSql = "directors.director LIKE :q";
        }
        if ($suchFeld === 'genre') {
            $joinSql .= " LEFT JOIN films_genres ON films.id = films_genres.films_id ";
            $joinSql .= " LEFT JOIN genres ON genres.id = films_genres.genres_id ";
            $whereSql = "genres.genre LIKE :q";
        }

        $sql = "
            SELECT DISTINCT films.id, films.title, films.year, films.poster
            FROM films
            $joinSql
            WHERE $whereSql
            ORDER BY films.id
            LIMIT $start,$proSeite
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['q' => '%' . $suchbegriff . '%']);
        $filme = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT id, title, year, poster FROM films ORDER BY id LIMIT $start,$proSeite";
        $filme = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // Einfache Fehlermeldung (spaeter kann man das schoener machen)
    $fehler = reportAppError($e);
}
require __DIR__ . '/views/templates/index_template.php';
