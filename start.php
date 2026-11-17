<?php
// ==========================================
// CineScope - Startseite
// Landing + zufaellige Filme
// ==========================================
require_once __DIR__ . '/include/security.php';
startSecureSession();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'user_logout') {
    unset($_SESSION['user_login'], $_SESSION['user_id'], $_SESSION['user_alias']);
    session_regenerate_id(true);
    header('Location: start.php');
    exit;
}

require_once __DIR__ . '/include/db.php';

$siteName = 'CineScope';
$fehler = '';
$meldung = '';
$gesamt = 0;
$startFilme = [];

try {
    $pdo = getPDO();

    // User Logout

    // Nur Info-Zahl fuer Startseite
    $gesamt = (int) $pdo->query("SELECT COUNT(id) FROM films")->fetchColumn();

    // 3 zufaellige Filme fuer die Startseite
    $startSql = "
        SELECT id, title, year, poster
        FROM films
        ORDER BY RAND()
        LIMIT 3
    ";
    $startFilme = $pdo->query($startSql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $fehler = reportAppError($e);
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($siteName); ?> - Start</title>
    <link rel="stylesheet" href="css/index.css?v=7">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell start-page-shell">
    <?php if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true): ?>
        <div class="user-box">
            <p>
                Eingeloggt als:
                <strong><?php echo htmlspecialchars($_SESSION['user_alias'] ?? ''); ?></strong>
            </p>
            <p><a href="index.php">Zum Filmbereich</a></p>
            <form method="post" action="start.php" class="logout-form"><?php echo csrfField(); ?><input type="hidden" name="action" value="user_logout"><button type="submit">Abmelden</button></form>
        </div>
    <?php endif; ?>

<section class="hero-box">
            <h1><?php echo htmlspecialchars($siteName); ?></h1>
            <p>Willkommen im Filmportal.</p>
        </section>

        <?php if (!empty($startFilme)): ?>
            <section class="start-filme-box">
                <h2>Heute im Fokus</h2>
                <div class="start-filme-grid">
                    <?php foreach ($startFilme as $film): ?>
                        <article class="start-film-card">
                            <a href="film.php?id=<?php echo (int) $film['id']; ?>" class="start-film-link">
                                <?php
                                $poster = isset($film['poster']) ? trim((string) $film['poster']) : '';
                                ?>
                                <?php if ($poster !== '' && strtoupper($poster) !== 'N/A'): ?>
                                    <img
                                        src="<?php echo htmlspecialchars($poster); ?>"
                                        onerror="this.onerror=null;this.src='bilder/placeholder.svg';"
                                        alt="<?php echo htmlspecialchars((string) ($film['title'] ?? 'Film')); ?>"
                                        class="start-film-poster"
                                    >
                                <?php else: ?>
                                    <div class="start-film-no-poster">Kein Poster</div>
                                <?php endif; ?>
                            </a>
                            <p class="start-film-title">
                                <a href="film.php?id=<?php echo (int) $film['id']; ?>">
                                    <?php echo htmlspecialchars((string) ($film['title'] ?? 'Unbekannt')); ?>
                                </a>
                            </p>
                            <p class="start-film-year"><?php echo htmlspecialchars((string) ($film['year'] ?? '')); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    <?php if ($fehler !== ''): ?>
        <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
    <?php endif; ?>

    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>



