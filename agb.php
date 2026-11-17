<?php
// ==========================================
// CineScope - AGB Seite
// ==========================================
require_once __DIR__ . '/include/security.php';
startSecureSession();
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AGB - CineScope</title>
    <link rel="stylesheet" href="css/omdb.css?v=3">
</head>
<body>
    <?php require __DIR__ . '/views/partials/nav.php'; ?>
    <main class="page-shell">
    <section class="hero-box">
        <h1>Allgemeine Geschäftsbedingungen (AGB)</h1>
    </section>

    <div class="box">
        <h2>1. Geltungsbereich</h2>
        <p>
            Diese AGB gelten für die Nutzung der Website CineScope.
            Die Website ist ein nicht-kommerzielles Lern- und Prüfungsprojekt.
        </p>
    </div>

    <div class="box">
        <h2>2. Leistungsbeschreibung</h2>
        <p>
            Die Website bietet Funktionen zur Suche, Anzeige und Verwaltung
            von Filmdaten sowie Benutzerfunktionen (Registrierung, Anmeldung,
            Kommentare, Merkliste) im Rahmen des Projekts.
        </p>
    </div>

    <div class="box">
        <h2>3. Registrierung und Nutzerkonto</h2>
        <p>
            Für bestimmte Funktionen ist eine Registrierung mit E-Mail, Alias
            und Passwort erforderlich. Nutzer sind für ihre Zugangsdaten
            selbst verantwortlich.
        </p>
    </div>

    <div class="box">
        <h2>4. Inhalte und Nutzung</h2>
        <p>
            Nutzer dürfen keine rechtswidrigen, beleidigenden oder
            missbräuchlichen Inhalte einstellen. Bei Verstößen können
            Inhalte oder Konten im Projektkontext entfernt werden.
        </p>
    </div>

    <div class="box">
        <h2>5. Haftung</h2>
        <p>
            Da es sich um ein Schulprojekt handelt, erfolgt die Nutzung ohne
            Gewähr und ohne Anspruch auf permanente Verfügbarkeit.
        </p>
    </div>

    <div class="box">
        <h2>6. Schlussbestimmungen</h2>
        <p>
            Diese AGB dienen der strukturierten Dokumentation im Rahmen
            der Klausuraufgabe und können projektbezogen angepasst werden.
        </p>
    </div>

    </main>
    <?php require __DIR__ . '/views/partials/footer.php'; ?>
</body>
</html>
