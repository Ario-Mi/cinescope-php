<?php
// ==========================================
// Frontend Navigation (Partial)
// Diese Datei wird in mehrere Seiten eingebunden.
// ==========================================
?>
<div class="site-logo-top">
    <a href="start.php">
        <img src="bilder/logo_varianten/logo_1_clean.png" alt="Logo">
    </a>
</div>
<nav class="top-nav">
    <a href="start.php">Start</a>
    <a href="index.php">Filme</a>
    <a href="contact.php">Kontakt</a>

    <?php if (!isset($_SESSION['user_login']) || $_SESSION['user_login'] !== true): ?>
        <a href="register.php">Registrieren</a>
        <a href="login.php">Anmelden</a>
    <?php endif; ?>

    <?php if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true): ?>
        <a href="omdb_import.php">OMDb API</a>
        <a href="omdb_datei_import.php">OMDb Datei</a>
        <form method="post" action="start.php" class="logout-form"><?php echo csrfField(); ?><input type="hidden" name="action" value="user_logout"><button type="submit">Abmelden</button></form>
    <?php endif; ?>
</nav>






