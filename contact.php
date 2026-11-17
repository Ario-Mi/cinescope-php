<?php
// ==========================================
// CineScope - Kontaktseite
// Angemeldete Benutzer senden Nachricht an Admin
// ==========================================

require_once __DIR__ . '/include/security.php';
startSecureSession();
require_once __DIR__ . '/include/db.php';

$fehler = '';
$meldung = '';
$betreff = inputText($_POST, 'betreff', '');
$nachricht = inputText($_POST, 'nachricht', '');

$istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;

if (!$istEingeloggt) {
    $fehler = 'Bitte zuerst als Benutzer einloggen.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

    if ($userId < 1) {
        $fehler = 'Ungueltiger Benutzer.';
    } elseif (mb_strlen($betreff) > 150 || mb_strlen($nachricht) > 10000) {
        $fehler = 'Betreff oder Nachricht ist zu lang.';
    } elseif ($betreff === '' || $nachricht === '') {
        $fehler = 'Bitte Betreff und Nachricht eingeben.';
    } else {
        try {
            $pdo = getPDO();
            $sql = "
                INSERT INTO messages (users_id, betreff, nachricht)
                VALUES (:users_id, :betreff, :nachricht)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'users_id' => $userId,
                'betreff' => $betreff,
                'nachricht' => $nachricht
            ]);

            $meldung = 'Nachricht wurde an Admin gesendet.';
            $betreff = '';
            $nachricht = '';
        } catch (PDOException $e) {
            $fehler = reportAppError($e);
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kontakt - CineScope</title>
    <link rel="stylesheet" href="css/omdb.css?v=3">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
<section class="hero-box">
        <h1>Kontakt an Admin</h1>
        <p>Hier kannst du direkt eine Nachricht an den Admin senden.</p>
        <p><a href="start.php">Zur&uuml;ck zur Startseite</a></p>
    </section>

    <?php if ($fehler !== ''): ?>
        <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
    <?php endif; ?>

    <?php if ($meldung !== ''): ?>
        <p class="meldung"><?php echo htmlspecialchars($meldung); ?></p>
    <?php endif; ?>

    <?php if ($istEingeloggt): ?>
        <div class="box contact-box">
            <form method="post" action="" class="form-stack">
                <?php echo csrfField(); ?>
                <p>
                    <label for="betreff">Betreff:</label><br>
                    <input type="text" id="betreff" name="betreff" value="<?php echo htmlspecialchars($betreff); ?>" required>
                </p>

                <p>
                    <label for="nachricht">Nachricht:</label><br>
                    <textarea id="nachricht" name="nachricht" rows="6" required><?php echo htmlspecialchars($nachricht); ?></textarea>
                </p>

                <p><button type="submit">Nachricht senden</button></p>
            </form>
        </div>
    <?php else: ?>
        <div class="box contact-box">
            <p>Bitte zuerst als Benutzer einloggen.</p>
            <p><a href="start.php">Zur Startseite</a></p>
        </div>
    <?php endif; ?>
    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>


