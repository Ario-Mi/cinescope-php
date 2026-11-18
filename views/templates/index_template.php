<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($siteName ?? 'CineScope'); ?> - Filmportal</title>
    <link rel="stylesheet" href="css/index.css?v=2">
</head>
<body>
    <?php require __DIR__ . '/../partials/nav.php'; ?>
    <main class="page-shell">
        <section class="hero-box">
            <h1><?php echo htmlspecialchars($siteName ?? 'CineScope'); ?> - Filmportal</h1>
        </section>

    <?php if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true): ?>
        <div class="user-box portal-user-box">
            <div class="portal-user-head">
                <p>
                    Eingeloggt als:
                    <strong><?php echo htmlspecialchars($_SESSION['user_alias'] ?? ''); ?></strong>
                </p>
                <form method="post" action="start.php" class="logout-form"><?php echo csrfField(); ?><input type="hidden" name="action" value="user_logout"><button type="submit">Abmelden</button></form>
            </div>

            <div class="portal-actions">
                <a href="profile.php">Mein Profil</a>
                <a href="omdb_import.php">OMDb Import</a>
                <a href="tmdb_import.php">TMDb Import</a>
                <a href="omdb_datei_import.php">Datei Import</a>
                <a href="contact.php">Kontakt an Admin</a>
            </div>

            <div class="portal-watchlist">
                <h3>Meine Merkliste</h3>
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
            </div>
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
            <h2>Filme suchen</h2>
            <div class="such-grid">
                <div>
                    <label for="suchFeld"><strong>Suche nach</strong></label>
                    <select id="suchFeld" name="such_feld">
                        <option value="title" <?php echo $suchFeld === 'title' ? 'selected' : ''; ?>>Titel</option>
                        <option value="year" <?php echo $suchFeld === 'year' ? 'selected' : ''; ?>>Jahr</option>
                        <option value="actor" <?php echo $suchFeld === 'actor' ? 'selected' : ''; ?>>Schauspieler</option>
                        <option value="director" <?php echo $suchFeld === 'director' ? 'selected' : ''; ?>>Regie</option>
                        <option value="genre" <?php echo $suchFeld === 'genre' ? 'selected' : ''; ?>>Genre</option>
                    </select>
                </div>

                <div>
                    <label for="sort"><strong>Sortieren nach</strong></label>
                    <select id="sort" name="sort">
                        <?php
                        $sortNamen = [
                            'id_desc' => 'Neueste zuerst',
                            'title_asc' => 'Titel A-Z',
                            'year_desc' => 'Jahr absteigend',
                            'imdb_desc' => 'IMDb-Bewertung',
                            'rating_desc' => 'Beste Bewertung zuerst',
                            'rating_asc' => 'Schwächste Bewertung zuerst',
                        ];
                        ?>
                        <?php foreach ($sortNamen as $wert => $name): ?>
                            <option value="<?php echo $wert; ?>" <?php echo $sort === $wert ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="such-textfeld">
                    <label for="suchInput"><strong>Suchbegriff</strong></label>
                    <input
                        type="text"
                        id="suchInput"
                        name="q"
                        autocomplete="off"
                        placeholder="z.B. Matrix, 1999, Spielberg, Drama"
                        value="<?php echo htmlspecialchars($suchbegriff); ?>"
                    >
                </div>
            </div>

            <div class="such-buttons">
                <button type="submit">Suchen</button>
                <?php if ($suchbegriff !== '' || $sort !== 'id_desc'): ?>
                    <a class="reset-link" href="index.php">Zurücksetzen</a>
                <?php endif; ?>
            </div>

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
                <p class="film-rating">
                    <?php
                    $ratingCount = (int) ($film['rating_count'] ?? 0);
                    $ratingAvg = (float) ($film['rating_avg'] ?? 0);
                    ?>
                    <?php if ($ratingCount > 0): ?>
                        <?php
                        $volleSterne = (int) round($ratingAvg);
                        for ($stern = 1; $stern <= 5; $stern++) {
                            echo $stern <= $volleSterne ? '★' : '☆';
                        }
                        ?>
                        <br>
                        <span><?php echo htmlspecialchars(number_format($ratingAvg, 1, ',', '.')); ?> / 5</span>
                    <?php else: ?>
                        <span>Noch keine Bewertung</span>
                    <?php endif; ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="seiten">
        Seite:
        <?php for ($i = 1; $i <= $seitenAnzahl; $i++): ?>
            <?php
            $linkParameter = ['seite' => $i];
            if ($suchbegriff !== '') {
                $linkParameter['q'] = $suchbegriff;
                $linkParameter['such_feld'] = $suchFeld;
            }
            if ($sort !== 'id_desc') {
                $linkParameter['sort'] = $sort;
            }
            $link = '?' . http_build_query($linkParameter);
            ?>
            <?php if ($i === $seite): ?>
                <strong><?php echo $i; ?></strong>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars($link); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
            <?php echo ' '; ?>
        <?php endfor; ?>
    </p>
    </main>

    <script src="js/suggest.js"></script>
    <?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
