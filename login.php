<?php
// ==========================================
// CineScope - Login Seite
// ==========================================
require_once __DIR__ . '/include/security.php';
startSecureSession();
require_once __DIR__ . '/include/db.php';

$fehler = '';

if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true) {
    header('Location: index.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $alias = inputText($_POST, 'alias', '');
    $passwort = isset($_POST['passwort']) && is_string($_POST['passwort']) ? $_POST['passwort'] : '';

    if ($alias === '' || $passwort === '') {
        $fehler = 'Bitte Alias und Passwort eingeben.';
    } else {
        try {
            $pdo = getPDO();
            $sql = "SELECT id, alias, passwort FROM users WHERE alias = :alias LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['alias' => $alias]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $ok = false;
            if ($user) {
                $ok = password_verify($passwort, $user['passwort']);
            }

            if ($ok) {
                    session_regenerate_id(true);
                $_SESSION['user_login'] = true;
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_alias'] = $user['alias'];
                header('Location: index.php');
                exit;
            } else {
                $fehler = 'Login fehlgeschlagen. Alias oder Passwort ist falsch.';
            }
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
    <title>Anmelden - CineScope</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
        <section class="hero-box">
            <h1>Anmelden</h1>
            <p>Bitte mit Alias und Passwort einloggen.</p>
        </section>

        <?php if ($fehler !== ''): ?>
            <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
        <?php endif; ?>

        <div class="auth-box">
            <form method="post" action="">
                <?php echo csrfField(); ?>
                <p>
                    <label for="log_alias">Alias:</label><br>
                    <input type="text" id="log_alias" name="alias" required>
                </p>
                <p>
                    <label for="log_passwort">Passwort:</label><br>
                    <input type="password" id="log_passwort" name="passwort" required>
                </p>
                <p><button type="submit">Anmelden</button></p>
            </form>
        </div>
    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>
