<?php
// ==========================================
// TMDb-Hilfsfunktionen
// Suche, Filmdetails und YouTube-Trailer ueber die TMDb API
// ==========================================

require_once __DIR__ . '/config.php';

/** API-Schluessel aus Umgebung oder config.local.php; leer = TMDb ist ausgeschaltet. */
function tmdbApiKey(): string
{
    return trim(appConfig('TMDB_API_KEY'));
}

/** Fragt eine TMDb-Adresse ab und gibt das JSON als Array zurueck (bei Fehler leer). */
function tmdbHoleJson(string $pfad, array $parameter): array
{
    $apiKey = tmdbApiKey();
    if ($apiKey === '') {
        return [];
    }

    $url = 'https://api.themoviedb.org/3/' . $pfad . '?' . http_build_query(['api_key' => $apiKey] + $parameter);
    // Kurzes Timeout, damit eine langsame API die Seite nicht blockiert.
    $context = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
    $text = @file_get_contents($url, false, $context);
    if ($text === false || $text === '') {
        return [];
    }

    $daten = json_decode($text, true);
    return is_array($daten) ? $daten : [];
}

/** YouTube-Keys bestehen nur aus Buchstaben, Ziffern, - und _. Alles andere wird verworfen. */
function tmdbIstGueltigerTrailerKey(string $key): bool
{
    return preg_match('/^[A-Za-z0-9_-]{6,30}$/D', $key) === 1;
}

/** Sucht den ersten YouTube-Trailer zu einer TMDb-Film-ID (erst Deutsch, dann Englisch). */
function tmdbTrailerKeyFuerId(int $tmdbId): string
{
    if ($tmdbId < 1) {
        return '';
    }

    foreach (['de-DE', 'en-US'] as $sprache) {
        $daten = tmdbHoleJson('movie/' . $tmdbId . '/videos', ['language' => $sprache]);
        foreach (($daten['results'] ?? []) as $video) {
            $key = trim((string) ($video['key'] ?? ''));
            if (($video['site'] ?? '') === 'YouTube' && ($video['type'] ?? '') === 'Trailer'
                && tmdbIstGueltigerTrailerKey($key)) {
                return $key;
            }
        }
    }

    return '';
}

/** Sucht einen Film per Titel und Jahr und gibt den Trailer-Key zurueck (oder ''). */
function tmdbFindeTrailerKey(string $titel, string $jahr): string
{
    $titel = trim($titel);
    if ($titel === '' || tmdbApiKey() === '') {
        return '';
    }

    $parameter = ['query' => $titel, 'language' => 'de-DE'];
    if (preg_match('/^\d{4}/', trim($jahr), $treffer)) {
        $parameter['year'] = $treffer[0];
    }

    $suche = tmdbHoleJson('search/movie', $parameter);
    $erster = $suche['results'][0] ?? null;
    return is_array($erster) ? tmdbTrailerKeyFuerId((int) ($erster['id'] ?? 0)) : '';
}
