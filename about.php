<?php
// ==========================================
// CineScope - About Seite
// ==========================================
require_once __DIR__ . '/include/security.php';
startSecureSession();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Über uns - CineScope</title>
    <link rel="stylesheet" href="css/omdb.css?v=3">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
    <section class="hero-box">
        <h1>Über CineScope</h1>
    </section>
    <div class="box">
        <h2>Projektidee</h2>
        <p>
            CineScope ist ein Filmportal als PHPC-Klausurprojekt.
            Ziel ist, Filmdaten aus der OMDb API in JSON/XML zu holen,
            zu speichern und im Frontend/Backend zu verwalten.
        </p>
    </div>

    <div class="box">
        <h2>Was die Website kann</h2>
        <ul>
            <li>Registrierung und Anmeldung für Benutzer</li>
            <li>Filmsuche mit Titel, Jahr, Schauspieler, Regie und Genre</li>
            <li>Film-Detailseite mit JOIN-Informationen und COUNT</li>
            <li>Kommentare und Merkliste für eingeloggte Benutzer</li>
            <li>OMDb-Import online (JSON/XML) und Datei-Import</li>
            <li>Admin-Bereich mit Statistiken, Export und Löschfunktionen</li>
        </ul>
    </div>

    <div class="box">
        <h2>Technik</h2>
        <p>PHP, PDO, MySQL, HTML, CSS, JavaScript (AJAX Suggest), JSON, XML.</p>
    </div>

    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>
