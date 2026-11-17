<?php
// ==========================================
// CineScope - AJAX Auto-Suggest
// Gibt passende Filmtitel als JSON zurueck
// (Suche ueber mehrere Felder mit JOIN)
// ==========================================

require_once __DIR__ . '/include/db.php';

header('Content-Type: application/json; charset=utf-8');

$q = inputText($_GET, 'q', '');
$suchFeld = inputText($_GET, 'such_feld', 'title');
if (!in_array($suchFeld, ['title', 'year', 'actor', 'director', 'genre'], true)) {
    $suchFeld = 'title';
}

// Bei sehr kurzem Text keine Suche
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $pdo = getPDO();

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
        SELECT DISTINCT films.id, films.title
        FROM films
        $joinSql
        WHERE $whereSql
        ORDER BY films.title
        LIMIT 3
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['q' => '%' . $q . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    // Bei Fehler leeres JSON
    echo json_encode([]);
}
