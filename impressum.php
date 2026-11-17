<?php
// ==========================================
// CineScope - Impressum Seite
// ==========================================
require_once __DIR__ . '/include/security.php';
startSecureSession();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impressum - CineScope</title>
    <link rel="stylesheet" href="css/omdb.css?v=3">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
    <section class="hero-box">
        <h1>Impressum</h1>
    </section>

    <div class="box">
        <h2>Angaben gemäß § 5 TMG</h2>
        <p>
            Projektname: CineScope<br>
            Art: Schulisches/unterrichtsbasiertes Projekt (nicht kommerziell)<br>
            Klasse: FAG57<br>
            Modul: PHPC
        </p>
    </div>

    <div class="box">
        <h2>Verantwortlich für den Inhalt</h2>
        <p>
            Eigenständig entwickeltes Lernprojekt im Rahmen der Umschulung bei CBW
        </p>
    </div>

    <div class="box">
        <h2>Kontakt</h2>
        <p>
            Kontakt für dieses Projekt erfolgt über die Kontaktseite
            innerhalb der Anwendung.
        </p>
    </div>

    <div class="box">
        <h2>Haftungshinweis</h2>
        <p>
            Diese Website wurde zu Lern- und Prüfungszwecken erstellt.
            Trotz sorgfältiger Bearbeitung wird keine Gewähr für
            Vollständigkeit und Aktualität der Inhalte übernommen.
        </p>
    </div>

    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>
