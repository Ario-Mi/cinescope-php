<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Filmdetails</title>
    <link rel="stylesheet" href="css/film.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/nav.php'; ?>
    <main class="page-shell">
    <section class="hero-box">
        <h1 class="titel">
            <?php if ($film): ?>
                <?php echo htmlspecialchars($film['title'] ?? 'Filmdetails'); ?>
            <?php else: ?>
                Filmdetails
            <?php endif; ?>
        </h1>
        <p class="zurueck"><a href="index.php">Zurück zur Liste</a></p>
    </section>

    <?php if ($fehler !== ''): ?>
        <p class="fehler"><?php echo htmlspecialchars($fehler); ?></p>
    <?php endif; ?>

    <?php if ($meldung !== ''): ?>
        <p class="meldung"><?php echo htmlspecialchars($meldung); ?></p>
    <?php endif; ?>

    <?php if ($film): ?>
        <?php
        $posterUrl = trim((string) ($film['poster'] ?? ''));
        if ($posterUrl === '' || strtoupper($posterUrl) === 'N/A') {
            $posterUrl = 'bilder/placeholder.svg';
        }
        ?>
        <p class="poster-box">
            <img
                src="<?php echo htmlspecialchars($posterUrl); ?>"
                onerror="this.onerror=null;this.src='bilder/placeholder.svg';"
                alt="poster"
                class="poster"
            >
        </p>

        <div class="details">
            <p><strong>Year:</strong> <?php echo htmlspecialchars($film['year'] ?? ''); ?></p>
            <p><strong>Runtime:</strong> <?php echo htmlspecialchars($film['runtime'] ?? ''); ?></p>
            <p><strong>Genre:</strong> <?php echo htmlspecialchars($film['genres'] ?? ''); ?></p>
            <p><strong>Director:</strong> <?php echo htmlspecialchars($film['directors'] ?? ''); ?></p>
            <p><strong>Writer:</strong> <?php echo htmlspecialchars($film['writers'] ?? ''); ?></p>
            <p><strong>Actors:</strong> <?php echo htmlspecialchars($film['actors'] ?? ''); ?></p>
            <p><strong>Actors Count:</strong> <?php echo (int) ($film['actors_count'] ?? 0); ?></p>
            <p><strong>Language:</strong> <?php echo htmlspecialchars($film['language'] ?? ''); ?></p>
            <p><strong>Country:</strong> <?php echo htmlspecialchars($film['country'] ?? ''); ?></p>
            <p><strong>IMDb Rating:</strong> <?php echo htmlspecialchars($film['imdbRating'] ?? ''); ?></p>
            <p><strong>Plot:</strong> <?php echo htmlspecialchars($film['plot'] ?? ''); ?></p>
        </div>

        <hr>

        <h2>Merkliste</h2>
        <?php if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true): ?>
            <?php if ($filmIstGespeichert): ?>
                <p>Dieser Film ist bereits in deiner Merkliste.</p>
            <?php else: ?>
                <form method="post" action="">
                <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="save_film">
                    <button type="submit">Film speichern</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>Bitte zuerst einloggen, um diesen Film zu speichern.</p>
        <?php endif; ?>

        <hr>

        <h2>Kommentare</h2>

        <?php if (isset($_SESSION['user_login']) && $_SESSION['user_login'] === true): ?>
            <form method="post" action="">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add_comment">
                <p>
                    <label for="kommentar">Neuer Kommentar:</label><br>
                    <textarea id="kommentar" name="kommentar" rows="4" cols="60" required></textarea>
                </p>
                <p><button type="submit">Kommentar speichern</button></p>
            </form>
        <?php else: ?>
            <p>Bitte zuerst eingeloggen, um Kommentare zu schreiben.</p>
        <?php endif; ?>

        <?php if (count($kommentare) === 0): ?>
            <p>Noch keine Kommentare vorhanden.</p>
        <?php else: ?>
            <?php foreach ($kommentare as $eintrag): ?>
                <div class="kommentar-box">
                    <p>
                        <strong><?php echo htmlspecialchars($eintrag['alias'] ?? ''); ?></strong>
                        (<?php echo htmlspecialchars($eintrag['erstellt_am'] ?? ''); ?>)
                    </p>
                    <p><?php echo nl2br(htmlspecialchars($eintrag['kommentar'] ?? '')); ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
    </main>
    <?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
