<?php
// ==========================================
// CineScope - Profilseite
// Eigene Merkliste, Bewertungen, Kommentare und Passwort aendern
// ==========================================

require_once __DIR__ . '/include/security.php';
startSecureSession();
require_once __DIR__ . '/include/db.php';

$fehler = '';
$meldung = '';
$user = null;
$gespeicherteFilme = [];
$eigeneBewertungen = [];
$eigeneKommentare = [];

// Ohne Login darf die Profilseite nicht benutzt werden.
$istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;
$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

if (!$istEingeloggt || $userId < 1) {
    $fehler = 'Bitte zuerst einloggen, um dein Profil zu sehen.';
} else {
    try {
        $pdo = getPDO();

        // Das CSRF-Token wurde bereits in startSecureSession() geprueft.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $action = inputText($_POST, 'action');

            // Bei allen Loeschaktionen wird users_id mitgeprueft:
            // So kann niemand fremde Eintraege loeschen, auch nicht mit manipulierter ID.
            if ($action === 'remove_saved') {
                $filmId = (int) inputText($_POST, 'film_id', '0');
                $delete = $pdo->prepare('DELETE FROM saved_films WHERE users_id = :users_id AND films_id = :films_id');
                $delete->execute(['users_id' => $userId, 'films_id' => $filmId]);
                $meldung = $delete->rowCount() > 0 ? 'Film wurde aus deiner Merkliste entfernt.' : '';
            }

            if ($action === 'delete_rating') {
                $filmId = (int) inputText($_POST, 'film_id', '0');
                $delete = $pdo->prepare('DELETE FROM ratings WHERE users_id = :users_id AND films_id = :films_id');
                $delete->execute(['users_id' => $userId, 'films_id' => $filmId]);
                $meldung = $delete->rowCount() > 0 ? 'Bewertung wurde gelöscht.' : '';
            }

            if ($action === 'delete_comment') {
                $commentId = (int) inputText($_POST, 'comment_id', '0');
                $delete = $pdo->prepare('DELETE FROM comments WHERE id = :id AND users_id = :users_id');
                $delete->execute(['id' => $commentId, 'users_id' => $userId]);
                $meldung = $delete->rowCount() > 0 ? 'Kommentar wurde gelöscht.' : '';
            }

            // Passwort aendern: erst altes Passwort pruefen, dann neues als Hash speichern.
            // Passwoerter werden nicht getrimmt, damit Leerzeichen Teil des Passworts bleiben.
            if ($action === 'change_password') {
                $altesPasswort = isset($_POST['altes_passwort']) && is_string($_POST['altes_passwort']) ? $_POST['altes_passwort'] : '';
                $neuesPasswort = isset($_POST['neues_passwort']) && is_string($_POST['neues_passwort']) ? $_POST['neues_passwort'] : '';

                if ($altesPasswort === '' || $neuesPasswort === '') {
                    $fehler = 'Bitte altes und neues Passwort eingeben.';
                } elseif (mb_strlen($neuesPasswort) < 8 || strlen($neuesPasswort) > 72) {
                    // Gleiche Regel wie bei der Registrierung (bcrypt nutzt maximal 72 Bytes).
                    $fehler = 'Das neue Passwort muss 8 bis 72 Zeichen lang sein.';
                } else {
                    $passStmt = $pdo->prepare('SELECT passwort FROM users WHERE id = :id LIMIT 1');
                    $passStmt->execute(['id' => $userId]);
                    $aktuellerHash = (string) $passStmt->fetchColumn();

                    if (!password_verify($altesPasswort, $aktuellerHash)) {
                        $fehler = 'Altes Passwort ist nicht richtig.';
                    } else {
                        $update = $pdo->prepare('UPDATE users SET passwort = :passwort WHERE id = :id');
                        $update->execute([
                            'passwort' => password_hash($neuesPasswort, PASSWORD_DEFAULT),
                            'id' => $userId
                        ]);
                        // Neue Session-ID nach Passwortwechsel: alte, eventuell gestohlene IDs werden wertlos.
                        session_regenerate_id(true);
                        $meldung = 'Passwort wurde geändert.';
                    }
                }
            }
        }

        // Kontodaten und Zaehler (Unterabfragen, damit sich die Zaehler nicht gegenseitig vervielfachen)
        $userStmt = $pdo->prepare("
            SELECT
                users.alias,
                users.email,
                (SELECT COUNT(*) FROM saved_films WHERE saved_films.users_id = users.id) AS saved_count,
                (SELECT COUNT(*) FROM ratings WHERE ratings.users_id = users.id) AS ratings_count,
                (SELECT COUNT(*) FROM comments WHERE comments.users_id = users.id) AS comments_count
            FROM users
            WHERE users.id = :id
        ");
        $userStmt->execute(['id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        $savedStmt = $pdo->prepare("
            SELECT films.id, films.title, films.year, films.poster
            FROM saved_films
            INNER JOIN films ON films.id = saved_films.films_id
            WHERE saved_films.users_id = :users_id
            ORDER BY saved_films.id DESC
        ");
        $savedStmt->execute(['users_id' => $userId]);
        $gespeicherteFilme = $savedStmt->fetchAll(PDO::FETCH_ASSOC);

        $ratingStmt = $pdo->prepare("
            SELECT films.id, films.title, ratings.rating
            FROM ratings
            INNER JOIN films ON films.id = ratings.films_id
            WHERE ratings.users_id = :users_id
            ORDER BY ratings.id DESC
        ");
        $ratingStmt->execute(['users_id' => $userId]);
        $eigeneBewertungen = $ratingStmt->fetchAll(PDO::FETCH_ASSOC);

        $commentStmt = $pdo->prepare("
            SELECT comments.id, comments.kommentar, comments.erstellt_am, films.id AS film_id, films.title
            FROM comments
            INNER JOIN films ON films.id = comments.films_id
            WHERE comments.users_id = :users_id
            ORDER BY comments.id DESC
        ");
        $commentStmt->execute(['users_id' => $userId]);
        $eigeneKommentare = $commentStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $fehler = reportAppError($e);
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mein Profil - CineScope</title>
    <link rel="stylesheet" href="css/index.css?v=2">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>

    <main class="page-shell">
        <section class="hero-box">
            <h1>Mein Profil</h1>
            <p>Deine gespeicherten Filme, Bewertungen und Kommentare.</p>
        </section>

        <?php if ($fehler !== ''): ?>
            <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
        <?php endif; ?>

        <?php if ($meldung !== ''): ?>
            <p class="meldung"><?php echo htmlspecialchars($meldung); ?></p>
        <?php endif; ?>

        <?php if (!$istEingeloggt): ?>
            <div class="auth-box">
                <p><a href="login.php">Zur Anmeldung</a> | <a href="register.php">Zur Registrierung</a></p>
            </div>
        <?php elseif (is_array($user)): ?>
            <div class="profile-grid">
                <div class="profile-box">
                    <h2>Kontodaten</h2>
                    <p><strong>Alias:</strong> <?php echo htmlspecialchars($user['alias']); ?></p>
                    <p><strong>E-Mail:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                    <p><strong>Gespeicherte Filme:</strong> <?php echo (int) $user['saved_count']; ?></p>
                    <p><strong>Bewertungen:</strong> <?php echo (int) $user['ratings_count']; ?></p>
                    <p><strong>Kommentare:</strong> <?php echo (int) $user['comments_count']; ?></p>
                </div>

                <div class="profile-box">
                    <h2>Passwort ändern</h2>
                    <form method="post" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="change_password">
                        <p>
                            <label for="altes_passwort">Altes Passwort:</label><br>
                            <input type="password" id="altes_passwort" name="altes_passwort" autocomplete="current-password" required>
                        </p>
                        <p>
                            <label for="neues_passwort">Neues Passwort (mindestens 8 Zeichen):</label><br>
                            <input type="password" id="neues_passwort" name="neues_passwort" minlength="8" maxlength="72" autocomplete="new-password" required>
                        </p>
                        <p><button type="submit">Passwort ändern</button></p>
                    </form>
                </div>
            </div>

            <div class="profile-box">
                <h2>Meine Merkliste</h2>
                <?php if (count($gespeicherteFilme) === 0): ?>
                    <p>Du hast noch keine Filme gespeichert.</p>
                <?php else: ?>
                    <div class="profile-film-list">
                        <?php foreach ($gespeicherteFilme as $film): ?>
                            <?php
                            $poster = trim((string) ($film['poster'] ?? ''));
                            if ($poster === '' || strtoupper($poster) === 'N/A') {
                                $poster = 'bilder/placeholder.svg';
                            }
                            ?>
                            <div class="profile-film-card">
                                <a href="film.php?id=<?php echo (int) $film['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($poster); ?>"
                                         onerror="this.onerror=null;this.src='bilder/placeholder.svg';"
                                         alt="<?php echo htmlspecialchars($film['title']); ?>">
                                </a>
                                <h3><?php echo htmlspecialchars($film['title']); ?></h3>
                                <p><?php echo htmlspecialchars($film['year'] ?? ''); ?></p>
                                <form method="post" action="">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="remove_saved">
                                    <input type="hidden" name="film_id" value="<?php echo (int) $film['id']; ?>">
                                    <button type="submit">Entfernen</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-box">
                <h2>Meine Bewertungen</h2>
                <?php if (count($eigeneBewertungen) === 0): ?>
                    <p>Du hast noch keine Filme bewertet.</p>
                <?php else: ?>
                    <?php foreach ($eigeneBewertungen as $bewertung): ?>
                        <div class="profile-rating">
                            <p>
                                <strong>
                                    <a href="film.php?id=<?php echo (int) $bewertung['id']; ?>">
                                        <?php echo htmlspecialchars($bewertung['title']); ?>
                                    </a>
                                </strong>
                            </p>
                            <p class="profile-stars" title="<?php echo (int) $bewertung['rating']; ?> von 5">
                                <?php echo str_repeat('★', (int) $bewertung['rating']) . str_repeat('☆', 5 - (int) $bewertung['rating']); ?>
                            </p>
                            <form method="post" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete_rating">
                                <input type="hidden" name="film_id" value="<?php echo (int) $bewertung['id']; ?>">
                                <button type="submit">Bewertung löschen</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="profile-box">
                <h2>Meine Kommentare</h2>
                <?php if (count($eigeneKommentare) === 0): ?>
                    <p>Du hast noch keine Kommentare geschrieben.</p>
                <?php else: ?>
                    <?php foreach ($eigeneKommentare as $kommentar): ?>
                        <div class="profile-comment">
                            <p>
                                <strong>
                                    <a href="film.php?id=<?php echo (int) $kommentar['film_id']; ?>">
                                        <?php echo htmlspecialchars($kommentar['title']); ?>
                                    </a>
                                </strong>
                            </p>
                            <p><?php echo nl2br(htmlspecialchars($kommentar['kommentar'])); ?></p>
                            <p class="profile-date"><?php echo htmlspecialchars($kommentar['erstellt_am']); ?></p>
                            <form method="post" action="">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete_comment">
                                <input type="hidden" name="comment_id" value="<?php echo (int) $kommentar['id']; ?>">
                                <button type="submit">Kommentar löschen</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>
