<?php
// ==========================================
// CineScope - Registrierung Seite
// ==========================================
require_once __DIR__ . '/include/security.php';
startSecureSession();
require_once __DIR__ . '/include/db.php';

$fehler = '';
$meldung = '';

if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true) {
    header('Location: index.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = inputText($_POST, 'email', '');
    $alias = inputText($_POST, 'alias', '');
    $passwort = isset($_POST['passwort']) && is_string($_POST['passwort']) ? $_POST['passwort'] : '';

    if ($email === '' || $alias === '' || $passwort === '') {
        $fehler = 'Bitte alle Felder ausfüllen.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fehler = 'Bitte eine gültige E-Mail eingeben.';
    } elseif (mb_strlen($email) > 120 || mb_strlen($alias) > 50) {
        $fehler = 'E-Mail darf maximal 120 und Alias maximal 50 Zeichen enthalten.';
    } elseif (mb_strlen($passwort) < 8 || strlen($passwort) > 72) {
        $fehler = 'Das Passwort muss mindestens 8 Zeichen haben und darf nicht zu lang sein.';
    } else {
        try {
            $pdo = getPDO();

            $checkSql = "SELECT id FROM users WHERE email = :email OR alias = :alias LIMIT 1";
            $check = $pdo->prepare($checkSql);
            $check->execute([
                'email' => $email,
                'alias' => $alias
            ]);

            if ($check->fetch()) {
                $fehler = 'E-Mail oder Alias ist schon vergeben.';
            } else {
                $hash = password_hash($passwort, PASSWORD_DEFAULT);
                $insertSql = "INSERT INTO users (email, alias, passwort) VALUES (:email, :alias, :passwort)";
                $insert = $pdo->prepare($insertSql);
                $insert->execute([
                    'email' => $email,
                    'alias' => $alias,
                    'passwort' => $hash
                ]);
                $meldung = 'Registrierung erfolgreich. Jetzt bitte anmelden.';
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
    <title>Registrieren - CineScope</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
        <section class="hero-box">
            <h1>Registrieren</h1>
            <p>Neues Benutzerkonto erstellen.</p>
        </section>

        <?php if ($fehler !== ''): ?>
            <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
        <?php endif; ?>
        <?php if ($meldung !== ''): ?>
            <p class="meldung"><?php echo htmlspecialchars($meldung); ?></p>
        <?php endif; ?>

        <div class="auth-box">
            <form method="post" action="">
                <?php echo csrfField(); ?>
                <p>
                    <label for="reg_email">E-Mail:</label><br>
                    <input type="email" id="reg_email" name="email" required>
                </p>
                <p>
                    <label for="reg_alias">Alias:</label><br>
                    <input type="text" id="reg_alias" name="alias" required>
                </p>
                <p>
                    <label for="reg_passwort">Passwort:</label><br>
                    <input type="password" id="reg_passwort" name="passwort" required>
                </p>
                <p><button type="submit">Registrieren</button></p>
            </form>
        </div>
    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>
