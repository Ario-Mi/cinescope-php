<?php
// ==========================================
// CineScope - Admin Bereich
// Schritt 6: Admin kann Kommentare sehen und loeschen
// ==========================================

// Session starten, damit wir Login-Status speichern koennen
require_once __DIR__ . '/../include/security.php';
startSecureSession();

// Zentrale DB-Funktion laden
require_once __DIR__ . '/../include/db.php';

// Admin-Zugang aus lokaler Konfiguration oder Umgebungsvariablen
$adminName = appConfig('ADMIN_USERNAME', 'admin');
$adminPasswordHash = appConfig('ADMIN_PASSWORD_HASH');

// Fehlermeldung fuer Login
$fehler = $adminPasswordHash === '' ? 'Admin-Zugang noch nicht eingerichtet. Bitte das lokale Einrichtungsskript ausfuehren.' : '';
$meldung = '';
$kommentare = [];
$users = [];
$messages = [];
$stats = [
    'users' => 0,
    'films' => 0,
    'comments' => 0,
    'messages' => 0,
    'saved_films' => 0
];

// Logout ausschliesslich als CSRF-geschuetzte POST-Anfrage
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'admin_logout') {
    // Nur Admin-Session loeschen
    unset($_SESSION['admin_login'], $_SESSION['admin_name'], $_SESSION['admin_auth_version']);
    session_regenerate_id(true);
    header('Location: index.php');
    exit;
}

// Login-Form abgeschickt?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = inputText($_POST, 'action', '');

    // Aktion 1: Admin Login
    if ($action === 'login') {
        // Eingaben lesen und Leerzeichen entfernen
        $name = inputText($_POST, 'name', '');
        $passwort = isset($_POST['passwort']) && is_string($_POST['passwort']) ? $_POST['passwort'] : '';

        // Pruefen: stimmen Name + Passwort?
        if ($adminPasswordHash !== '' && $name === $adminName && password_verify($passwort, $adminPasswordHash)) {
            session_regenerate_id(true);
            $_SESSION['admin_login'] = true;
            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_auth_version'] = hash('sha256', $adminPasswordHash);
            header('Location: index.php');
            exit;
        } else {
            $fehler = 'Login fehlgeschlagen. Bitte prüfen.';
        }
    }
}

// Ist Admin eingeloggt?
$istEingeloggt = $adminPasswordHash !== ''
    && ($_SESSION['admin_login'] ?? false) === true
    && is_string($_SESSION['admin_auth_version'] ?? null)
    && hash_equals(hash('sha256', $adminPasswordHash), $_SESSION['admin_auth_version']);

// Nur wenn Admin eingeloggt ist, Kommentare verwalten
if ($istEingeloggt) {
    try {
        $pdo = getPDO();
        
        // Export-Ordner im Admin-Bereich
        $exportOrdner = appConfig('EXPORT_DIR', __DIR__ . '/data/exports');
        if (!is_dir($exportOrdner)) {
            mkdir($exportOrdner, 0777, true);
        }

        // Daten fuer Export vorbereiten (mit JOIN)
        $exportSql = "
            SELECT
                films.id,
                films.imdbID,
                films.title,
                films.year,
                films.runtime,
                films.imdbRating,
                GROUP_CONCAT(DISTINCT genres.genre SEPARATOR ', ') AS genres,
                GROUP_CONCAT(DISTINCT directors.director SEPARATOR ', ') AS directors,
                GROUP_CONCAT(DISTINCT writers.writer SEPARATOR ', ') AS writers,
                GROUP_CONCAT(DISTINCT actors.actor SEPARATOR ', ') AS actors
            FROM films
            LEFT JOIN films_genres ON films.id = films_genres.films_id
            LEFT JOIN genres ON genres.id = films_genres.genres_id
            LEFT JOIN films_directors ON films.id = films_directors.films_id
            LEFT JOIN directors ON directors.id = films_directors.directors_id
            LEFT JOIN films_writers ON films.id = films_writers.films_id
            LEFT JOIN writers ON writers.id = films_writers.writers_id
            LEFT JOIN films_actors ON films.id = films_actors.films_id
            LEFT JOIN actors ON actors.id = films_actors.actors_id
            GROUP BY films.id, films.imdbID, films.title, films.year, films.runtime, films.imdbRating
            ORDER BY films.id
        ";
        $exportDaten = $pdo->query($exportSql)->fetchAll(PDO::FETCH_ASSOC);

        // Aktion 2: Kommentar loeschen
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'delete_comment'
        ) {
            $commentId = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;

            if ($commentId > 0) {
                $deleteSql = "DELETE FROM comments WHERE id = :id";
                $delete = $pdo->prepare($deleteSql);
                $delete->execute(['id' => $commentId]);
                $meldung = 'Kommentar wurde gelöscht.';
            } else {
                $fehler = 'Ungültige Kommentar-ID.';
            }
        }

        // Aktion 2b: Export JSON
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'export_json'
        ) {
            $jsonDatei = $exportOrdner . '/films_export.json';
            $jsonText = json_encode($exportDaten, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($jsonText === false) {
                $fehler = 'JSON Export fehlgeschlagen.';
            } else {
                file_put_contents($jsonDatei, $jsonText);
                $meldung = 'JSON Export gespeichert: admin/data/exports/films_export.json';
            }
        }

        // Aktion 2c: Export CSV
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'export_csv'
        ) {
            $csvDatei = $exportOrdner . '/films_export.csv';
            $fp = fopen($csvDatei, 'w');

            if ($fp === false) {
                $fehler = 'CSV Export fehlgeschlagen.';
            } else {
                // Kopfzeile
                fputcsv($fp, ['id', 'imdbID', 'title', 'year', 'runtime', 'imdbRating', 'genres', 'directors', 'writers', 'actors'], ';');

                // Datenzeilen
                foreach ($exportDaten as $row) {
                    fputcsv($fp, [
                        $row['id'] ?? '',
                        $row['imdbID'] ?? '',
                        $row['title'] ?? '',
                        $row['year'] ?? '',
                        $row['runtime'] ?? '',
                        $row['imdbRating'] ?? '',
                        $row['genres'] ?? '',
                        $row['directors'] ?? '',
                        $row['writers'] ?? '',
                        $row['actors'] ?? ''
                    ], ';');
                }

                fclose($fp);
                $meldung = 'CSV Export gespeichert: admin/data/exports/films_export.csv';
            }
        }

        // Aktion 2d: Export XML
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'export_xml'
        ) {
            $xmlDatei = $exportOrdner . '/films_export.xml';

            $xml = new DOMDocument('1.0', 'UTF-8');
            $xml->formatOutput = true;
            $root = $xml->createElement('films');
            $xml->appendChild($root);

            foreach ($exportDaten as $row) {
                $filmNode = $xml->createElement('film');
                $root->appendChild($filmNode);

                $felder = ['id', 'imdbID', 'title', 'year', 'runtime', 'imdbRating', 'genres', 'directors', 'writers', 'actors'];
                foreach ($felder as $feld) {
                    $child = $xml->createElement($feld);
                    $child->appendChild($xml->createTextNode((string) ($row[$feld] ?? '')));
                    $filmNode->appendChild($child);
                }
            }

            $ok = $xml->save($xmlDatei);
            if ($ok === false) {
                $fehler = 'XML Export fehlgeschlagen.';
            } else {
                $meldung = 'XML Export gespeichert: admin/data/exports/films_export.xml';
            }
        }

        // Aktion 3: User loeschen
        // Durch ON DELETE CASCADE werden auch Kommentare + Merkliste mitgeloescht.
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'delete_user'
        ) {
            $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

            if ($userId > 0) {
                $deleteUserSql = "DELETE FROM users WHERE id = :id";
                $deleteUser = $pdo->prepare($deleteUserSql);
                $deleteUser->execute(['id' => $userId]);
                $meldung = 'Benutzer wurde gelöscht (inkl. Kommentare/Merkliste).';
            } else {
                $fehler = 'Ungültige Benutzer-ID.';
            }
        }

        // Kommentarliste mit User-Alias + Filmtitel holen
        $listeSql = "
            SELECT
                comments.id,
                comments.kommentar,
                comments.erstellt_am,
                users.alias,
                films.title
            FROM comments
            INNER JOIN users ON users.id = comments.users_id
            INNER JOIN films ON films.id = comments.films_id
            ORDER BY comments.id DESC
        ";
        $kommentare = $pdo->query($listeSql)->fetchAll(PDO::FETCH_ASSOC);

        // Aktion 4: Nachricht loeschen
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['action'])
            && $_POST['action'] === 'delete_message'
        ) {
            $messageId = isset($_POST['message_id']) ? (int) $_POST['message_id'] : 0;

            if ($messageId > 0) {
                $deleteMessageSql = "DELETE FROM messages WHERE id = :id";
                $deleteMessage = $pdo->prepare($deleteMessageSql);
                $deleteMessage->execute(['id' => $messageId]);
                $meldung = 'Nachricht wurde gelöscht.';
            } else {
                $fehler = 'Ungültige Nachrichten-ID.';
            }
        }

        // Einfache Statistiken mit COUNT(*)
        $stats['users'] = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['films'] = (int) $pdo->query("SELECT COUNT(*) FROM films")->fetchColumn();
        $stats['comments'] = (int) $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
        $stats['messages'] = (int) $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
        $stats['saved_films'] = (int) $pdo->query("SELECT COUNT(*) FROM saved_films")->fetchColumn();

        // User-Liste mit Statistik holen
        $usersSql = "
            SELECT
                users.id,
                users.email,
                users.alias,
                COUNT(DISTINCT comments.id) AS comments_count,
                COUNT(DISTINCT saved_films.id) AS saved_count
            FROM users
            LEFT JOIN comments ON comments.users_id = users.id
            LEFT JOIN saved_films ON saved_films.users_id = users.id
            GROUP BY users.id, users.email, users.alias
            ORDER BY users.id DESC
        ";
        $users = $pdo->query($usersSql)->fetchAll(PDO::FETCH_ASSOC);

        // Nachrichtenliste holen
        $messagesSql = "
            SELECT
                messages.id,
                messages.betreff,
                messages.nachricht,
                messages.erstellt_am,
                users.alias
            FROM messages
            INNER JOIN users ON users.id = messages.users_id
            ORDER BY messages.id DESC
        ";
        $messages = $pdo->query($messagesSql)->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Admin Anmeldung - CineScope</title>
    <link rel="stylesheet" href="css/admin.css?v=5">
</head>
<body>
    <main class="page-shell">
    <section class="hero-box">
        <h1>Admin Bereich</h1>
        <p><a href="../start.php">Zur Frontend-Startseite</a></p>
    </section>

    <?php if (!$istEingeloggt): ?>
        <div class="box">
            <h2>Anmeldung</h2>

            <?php if ($fehler !== ''): ?>
                <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
            <?php endif; ?>

            <form method="post" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="login">

                <div class="zeile">
                    <label for="name">Name:</label><br>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="zeile">
                    <label for="passwort">Passwort:</label><br>
                    <input type="password" id="passwort" name="passwort" required>
                </div>

                <button type="submit">Anmelden</button>
            </form>
        </div>
    <?php else: ?>
        <div class="box">
            <h2>Willkommen im Admin-Bereich</h2>
            <p>Du bist eingeloggt als: <strong><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'admin'); ?></strong></p>
            <p>
                <a href="../about.php" target="_blank" rel="noopener noreferrer">
                    Projektinformationen
                </a>
            </p>
            <form method="post" action="index.php" class="logout-form"><?php echo csrfField(); ?><input type="hidden" name="action" value="admin_logout"><button type="submit">Abmelden</button></form>
        </div>

        <?php if ($fehler !== ''): ?>
            <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
        <?php endif; ?>

        <?php if ($meldung !== ''): ?>
            <p class="meldung"><?php echo htmlspecialchars($meldung); ?></p>
        <?php endif; ?>

        <div class="liste-box">
            <h2>Statistiken</h2>
            <table class="admin-tabelle">
                <tr>
                    <th>Bereich</th>
                    <th>Anzahl</th>
                </tr>
                <tr>
                    <td>Benutzer</td>
                    <td><?php echo (int) $stats['users']; ?></td>
                </tr>
                <tr>
                    <td>Filme</td>
                    <td><?php echo (int) $stats['films']; ?></td>
                </tr>
                <tr>
                    <td>Kommentare</td>
                    <td><?php echo (int) $stats['comments']; ?></td>
                </tr>
                <tr>
                    <td>Nachrichten</td>
                    <td><?php echo (int) $stats['messages']; ?></td>
                </tr>
                <tr>
                    <td>Gespeicherte Filme</td>
                    <td><?php echo (int) $stats['saved_films']; ?></td>
                </tr>
            </table>
        </div>

        <div class="liste-box">
            <h2>Export (JSON / CSV / XML)</h2>
            <p>Diese Dateien werden gespeichert in: <strong>admin/data/exports</strong></p>

            <form method="post" action="" class="inline-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="export_json">
                <button type="submit">Export JSON</button>
            </form>

            <form method="post" action="" class="inline-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="export_csv">
                <button type="submit">Export CSV</button>
            </form>

            <form method="post" action="" class="inline-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="export_xml">
                <button type="submit">Export XML</button>
            </form>
        </div>

        <div class="liste-box">
            <h2>Kommentare (Admin)</h2>

            <?php if (count($kommentare) === 0): ?>
                <p>Keine Kommentare vorhanden.</p>
            <?php else: ?>
                <table class="admin-tabelle">
                    <tr>
                        <th>ID</th>
                        <th>Film</th>
                    <th>Benutzer</th>
                        <th>Kommentar</th>
                        <th>Zeit</th>
                        <th>Aktion</th>
                    </tr>

                    <?php foreach ($kommentare as $eintrag): ?>
                        <tr>
                            <td><?php echo (int) $eintrag['id']; ?></td>
                            <td><?php echo htmlspecialchars($eintrag['title'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($eintrag['alias'] ?? ''); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($eintrag['kommentar'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($eintrag['erstellt_am'] ?? ''); ?></td>
                            <td>
                                <form method="post" action="" onsubmit="return confirm('Kommentar wirklich löschen?');">
                <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_comment">
                                    <input type="hidden" name="comment_id" value="<?php echo (int) $eintrag['id']; ?>">
                                    <button type="submit">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="liste-box">
            <h2>Benutzerverwaltung (Admin)</h2>

            <?php if (count($users) === 0): ?>
                <p>Keine Benutzer vorhanden.</p>
            <?php else: ?>
                <table class="admin-tabelle">
                    <tr>
                        <th>ID</th>
                        <th>E-Mail</th>
                        <th>Alias</th>
                        <th>Kommentare</th>
                        <th>Gespeicherte Filme</th>
                        <th>Aktion</th>
                    </tr>

                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo (int) $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($u['alias'] ?? ''); ?></td>
                            <td><?php echo (int) ($u['comments_count'] ?? 0); ?></td>
                            <td><?php echo (int) ($u['saved_count'] ?? 0); ?></td>
                            <td>
                                <form method="post" action="" onsubmit="return confirm('Benutzer wirklich löschen?');">
                <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                    <button type="submit">Benutzer löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="liste-box">
            <h2>Nachrichten an Admin</h2>

            <?php if (count($messages) === 0): ?>
                <p>Keine Nachrichten vorhanden.</p>
            <?php else: ?>
                <table class="admin-tabelle">
                    <tr>
                        <th>ID</th>
                        <th>Alias</th>
                        <th>Betreff</th>
                        <th>Nachricht</th>
                        <th>Zeit</th>
                        <th>Aktion</th>
                    </tr>

                    <?php foreach ($messages as $m): ?>
                        <tr>
                            <td><?php echo (int) $m['id']; ?></td>
                            <td><?php echo htmlspecialchars($m['alias'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($m['betreff'] ?? ''); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($m['nachricht'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars($m['erstellt_am'] ?? ''); ?></td>
                            <td>
                                <form method="post" action="" onsubmit="return confirm('Nachricht wirklich löschen?');">
                <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="delete_message">
                                    <input type="hidden" name="message_id" value="<?php echo (int) $m['id']; ?>">
                                    <button type="submit">Löschen</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </main>
</body>
</html>
