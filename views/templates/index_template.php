<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($siteName ?? 'CineScope'); ?> - Filmportal</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/nav.php'; ?>
    <main class="page-shell">
        <section class="hero-box">
            <h1><?php echo htmlspecialchars($siteName ?? 'CineScope'); ?> - Filmportal</h1>
        </section>

    <?php if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true): ?>
        <div class="user-box">
            <p>
                Eingeloggt als:
                <strong><?php echo htmlspecialchars($_SESSION['user_alias'] ?? ''); ?></strong>
            </p>
            <h3>Meine Merkliste</h3>
            <p><a href="omdb_import.php">OMDb Import (JSON/XML)</a></p>
            <p><a href="omdb_datei_import.php">OMDb Datei Import (JSON/XML)</a></p>
            <p><a href="contact.php">Kontakt an Admin</a></p>
            <?php if (count($meineMerkliste) === 0): ?>
                <p>Noch keine gespeicherten Filme.</p>
            <?php else: ?>
                <ul class="merkliste">
                    <?php foreach ($meineMerkliste as $eintrag): ?>
                        <li>
                            <a href="film.php?id=<?php echo (int) $eintrag['id']; ?>">
                                <?php echo htmlspecialchars($eintrag['title'] ?? ''); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <form method="post" action="start.php" class="logout-form"><?php echo csrfField(); ?><input type="hidden" name="action" value="user_logout"><button type="submit">Abmelden</button></form>
        </div>
    <?php else: ?>
        <div class="auth-box">
            <p>Bitte zuerst anmelden oder registrieren.</p>
            <p><a href="login.php">Zur Anmeldung</a> | <a href="register.php">Zur Registrierung</a></p>
        </div>
    <?php endif; ?>

    <?php if ($fehler !== ''): ?>
        <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
    <?php endif; ?>

    <?php if ($meldung !== ''): ?>
        <p class="meldung"><?php echo htmlspecialchars($meldung); ?></p>
    <?php endif; ?>

    <section class="such-box">
        <form method="get" action="" id="suchForm">
            <label for="suchInput"><strong>Film suchen :</strong></label><br>
            <select id="suchFeld" name="such_feld">
                <option value="title" <?php echo $suchFeld === 'title' ? 'selected' : ''; ?>>Titel</option>
                <option value="year" <?php echo $suchFeld === 'year' ? 'selected' : ''; ?>>Jahr</option>
                <option value="actor" <?php echo $suchFeld === 'actor' ? 'selected' : ''; ?>>Schauspieler</option>
                <option value="director" <?php echo $suchFeld === 'director' ? 'selected' : ''; ?>>Regie</option>
                <option value="genre" <?php echo $suchFeld === 'genre' ? 'selected' : ''; ?>>Genre</option>
            </select><br><br>
            <input
                type="text"
                id="suchInput"
                name="q"
                autocomplete="off"
                placeholder="z.B. Matrix, 1999, Spielberg, Drama"
                value="<?php echo htmlspecialchars($suchbegriff); ?>"
            >
            <button type="submit">Suchen</button>
            <?php if ($suchbegriff !== ''): ?>
                <a href="index.php">Zurücksetzen</a>
            <?php endif; ?>

            <div id="suggestBox" class="suggest-box"></div>
        </form>
    </section>

    <div class="film-liste">
        <?php foreach ($filme as $film): ?>
            <?php
            $posterUrl = trim((string) ($film['poster'] ?? ''));
            if ($posterUrl === '' || strtoupper($posterUrl) === 'N/A') {
                $posterUrl = 'bilder/placeholder.svg';
            }
            ?>
            <div class="film-karte">
                <p class="film-titel">
                    <a href="film.php?id=<?php echo (int) $film['id']; ?>">
                        <?php echo htmlspecialchars($film['title'] ?? ''); ?>
                    </a>
                </p>

                <p class="film-poster">
                    <a href="film.php?id=<?php echo (int) $film['id']; ?>">
                        <img
                            src="<?php echo htmlspecialchars($posterUrl); ?>"
                            onerror="this.onerror=null;this.src='bilder/placeholder.svg';"
                            alt="poster"
                        >
                    </a>
                </p>

                <p class="film-jahr"><?php echo htmlspecialchars($film['year'] ?? ''); ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="seiten">
        Seite:
        <?php for ($i = 1; $i <= $seitenAnzahl; $i++): ?>
            <?php $link = '?seite=' . $i; ?>
            <?php if ($suchbegriff !== ''): ?>
                <?php $link .= '&q=' . urlencode($suchbegriff) . '&such_feld=' . urlencode($suchFeld); ?>
            <?php endif; ?>
            <?php if ($i === $seite): ?>
                <strong><?php echo $i; ?></strong>
            <?php else: ?>
                <a href="<?php echo $link; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
            <?php echo ' '; ?>
        <?php endfor; ?>
    </p>
    </main>

    <script src="js/suggest.js"></script>
    <?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
