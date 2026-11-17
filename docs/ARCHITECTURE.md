# Aufbau von CineScope

## Seiten und Datenfluss

`start.php` zeigt eine Auswahl lokaler Filme. Ein Klick auf ein Poster führt über die Film-ID zu `film.php`. Die Übersicht in `index.php` bietet Filter und Seitennavigation. `search_suggest.php` liefert dafür JSON-Suchvorschläge; `js/suggest.js` verlinkt die Treffer zur Detailansicht und verwirft verspätete Antworten.

Anmeldung und Registrierung werden in `login.php` bzw. `register.php` verarbeitet. Angemeldete Benutzer können Filme merken, Kommentare schreiben und über `contact.php` eine Nachricht an die Administration senden.

`omdb_import.php` liest externe JSON/XML-Daten und kann Filme samt zugeordneten Personen und Genres in einer Datenbanktransaktion speichern. `omdb_datei_import.php` zeigt Daten aus den lokalen Beispieldateien an. `admin/index.php` verwaltet Benutzer, Kommentare und Nachrichten und erzeugt Exporte.

## Gemeinsame Komponenten

- `include/config.php`: lokale Konfiguration mit optionaler Übersteuerung durch Umgebungsvariablen
- `include/db.php`: PDO-Verbindung mit UTF-8 und begrenzten Netzwerkwartezeiten
- `include/security.php`: Sessions, CSRF-Schutz, Texteingaben und allgemeine Fehlerausgaben
- `views/partials/`: Navigation und Footer
- `views/templates/`: Filmübersicht und Detailansicht

SQL und Seitensteuerung stehen teilweise noch gemeinsam in den Seitendateien. Eine spätere Weiterentwicklung kann diese Logik in Services und Repositories aufteilen; aktuell wird keine vollständige MVC-Struktur behauptet.

## Datenmodell

`users` besitzt Kommentare (`comments`), Nachrichten (`messages`) und Einträge der Merkliste (`saved_films`). `films` ist über Zuordnungstabellen mit `genres`, `actors`, `directors` und `writers` verbunden. Eindeutige Schlüssel verhindern doppelte IMDb-IDs und doppelte Einträge der Merkliste. Fremdschlüssel bereinigen abhängige Benutzerdaten beim Löschen eines Kontos.

Das Schema steht in `data/sql/schema.sql`, die lokalen Beispieldaten in `data/sql/seed.sql`. Das vorhandene Diagramm liegt unter `docs/uml/cinescope.drawio`.
