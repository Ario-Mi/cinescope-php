<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($film['title'] ?? 'Filmdetails'); ?> - CineScope</title>
    <link rel="stylesheet" href="css/film.css?v=2">
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
        $istEingeloggt = isset($_SESSION['user_login']) && $_SESSION['user_login'] === true;
        $posterUrl = trim((string) ($film['poster'] ?? ''));
        if ($posterUrl === '' || strtoupper($posterUrl) === 'N/A') {
            $posterUrl = 'bilder/placeholder.svg';
        }
        // Nur gueltige YouTube-Keys werden eingebettet (siehe include/tmdb.php).
        $trailerKey = trim((string) ($film['trailer_key'] ?? ''));
        if (!tmdbIstGueltigerTrailerKey($trailerKey)) {
            $trailerKey = '';
        }
        ?>
        <div class="poster-box">
            <img
                src="<?php echo htmlspecialchars($posterUrl); ?>"
                onerror="this.onerror=null;this.src='bilder/placeholder.svg';"
                alt="poster"
                class="poster"
            >
            <?php if ($trailerKey !== ''): ?>
                <button type="button" class="poster-trailer-button" id="trailerOeffnen">Trailer ansehen</button>
            <?php endif; ?>
        </div>

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

        <?php if ($trailerKey !== ''): ?>
            <!-- Der iframe bekommt seine Adresse erst beim Oeffnen. So laedt YouTube nichts,
                 solange niemand den Trailer ansieht. -->
            <div class="trailer-modal" id="trailerModal" role="dialog" aria-modal="true" aria-label="Trailer">
                <div class="trailer-modal-inhalt">
                    <button type="button" class="trailer-close" id="trailerSchliessen">Schließen</button>
                    <div class="trailer-frame">
                        <iframe
                            id="trailerIframe"
                            data-src="https://www.youtube-nocookie.com/embed/<?php echo htmlspecialchars($trailerKey); ?>?autoplay=1"
                            src=""
                            title="Trailer"
                            allow="autoplay; encrypted-media"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <hr>

        <div class="rating-box">
            <h2>Bewertung</h2>
            <p>
                <strong>Durchschnitt:</strong>
                <?php if ($ratingAnzahl > 0): ?>
                    <?php $durchschnittText = number_format($ratingDurchschnitt, 1, ',', '.'); ?>
                    <span class="sterne-anzeige" title="<?php echo htmlspecialchars($durchschnittText); ?> von 5">
                        <?php
                        $volleSterne = (int) round($ratingDurchschnitt);
                        for ($stern = 1; $stern <= 5; $stern++) {
                            echo $stern <= $volleSterne ? '★' : '☆';
                        }
                        ?>
                    </span>
                    <?php echo htmlspecialchars($durchschnittText); ?> / 5
                    (<?php echo $ratingAnzahl; ?> <?php echo $ratingAnzahl === 1 ? 'Bewertung' : 'Bewertungen'; ?>)
                <?php else: ?>
                    Noch keine Bewertung vorhanden.
                <?php endif; ?>
            </p>

            <?php if ($istEingeloggt): ?>
                <form method="post" action="" class="rating-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="add_rating">
                    <p>Deine Bewertung:</p>
                    <div class="stern-auswahl">
                        <?php for ($stern = 1; $stern <= 5; $stern++): ?>
                            <button
                                type="submit"
                                name="rating"
                                value="<?php echo $stern; ?>"
                                class="<?php echo $stern <= $eigeneBewertung ? 'aktiv' : ''; ?>"
                                title="<?php echo $stern; ?> von 5"
                                aria-label="<?php echo $stern; ?> von 5 Sternen"
                            >★</button>
                        <?php endfor; ?>
                    </div>
                </form>
                <?php if ($eigeneBewertung > 0): ?>
                    <form method="post" action="" class="rating-delete-form" onsubmit="return confirm('Bewertung wirklich löschen?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="delete_rating">
                        <button type="submit">Meine Bewertung löschen</button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <p>Bitte zuerst einloggen, um diesen Film zu bewerten.</p>
            <?php endif; ?>
        </div>

        <hr>

        <h2>Merkliste</h2>
        <?php if ($istEingeloggt): ?>
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

        <?php if ($istEingeloggt): ?>
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
            <p>Bitte zuerst einloggen, um Kommentare zu schreiben.</p>
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
    <script>
        // Trailer-Fenster oeffnen und schliessen. Beim Schliessen wird src geleert,
        // damit das Video wirklich stoppt.
        (function () {
            var modal = document.getElementById('trailerModal');
            if (!modal) {
                return;
            }
            var iframe = document.getElementById('trailerIframe');
            function schliessen() {
                modal.classList.remove('trailer-modal-open');
                iframe.src = '';
            }
            document.getElementById('trailerOeffnen').addEventListener('click', function () {
                modal.classList.add('trailer-modal-open');
                iframe.src = iframe.getAttribute('data-src');
            });
            document.getElementById('trailerSchliessen').addEventListener('click', schliessen);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    schliessen();
                }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    schliessen();
                }
            });
        })();
    </script>
    <?php require __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>
