# Tests und Entwicklungsstand

Stand: 17. November 2026.

## Bestandene Prüfungen

Die automatisierten HTTP-Tests laufen gegen einen lokalen PHP-Testserver. Für Funktionstests wird mit den installierten XAMPP-Binärdateien eine separate temporäre MariaDB-Instanz eingerichtet. Die Tests arbeiten mit dem Projektschema und Beispieldaten; vorhandene Benutzer und Inhalte der normalen Anwendung werden nicht verwendet.

- Registrierung und Ablehnung doppelter Konten
- Passwort-Hash statt Klartextspeicherung; der gespeicherte Hash wird als Login-Passwort abgelehnt
- Anmeldung, Abmeldung und Erneuerung der Session-ID
- CSRF-Schutz einschließlich falscher, fremder und als Array übergebener Tokens
- Cookie-Einstellungen HttpOnly und SameSite
- Filmsuche nach Titel, Jahr, Schauspieler, Regie und Genre
- JSON-Suchvorschläge und Seitennavigation
- Merkliste ohne doppelte Einträge
- Kommentare mit HTML-Ausgabe-Escaping und Ablehnung nicht angemeldeter Benutzer
- Nachrichten an die Administration
- Lesen lokaler JSON- und XML-Beispieldateien
- Admin-Anmeldung, Löschen von Kommentaren, Nachrichten und Testbenutzern
- Fremdschlüssel-Kaskade beim Löschen eines Testbenutzers
- Admin-Statistiken sowie JSON-, CSV- und XML-Exporte
- Echte OMDb-Antworten in JSON und XML sowie Import eines Films in die Testdatenbank

Die echte OMDb-Anbindung wurde erfolgreich getestet. Die abschliessende Pruefung des bereinigten Ausgabeordners umfasst acht Testmethoden: sieben bestanden, die optionale Live-API-Pruefung ohne mitgelieferten Schluessel bewusst uebersprungen. Eine Testmethode prueft mehrere zusammenhaengende Ablaeufe. Auch die Posterlinks auf der Startseite und das Laden der Film-Detailseiten wurden per HTTP geprueft.

## Behobene Funktionsprobleme

Suchvorschläge verlinken direkt zum Film. Dadurch wird bei einer Schauspieler-, Jahres- oder Genresuche kein Filmtitel in das falsche Suchfeld übernommen. Frühere AJAX-Anfragen werden abgebrochen und verspätete Antworten verworfen.

Statistiken werden nach Admin-Löschaktionen geladen. Ungültige Film-IDs werden vor einer Änderung geprüft. Texteingaben als Array verursachen in den umgestellten Textfeldern keine trim-TypeErrors mehr. API-Anfragen haben ein Zeitlimit von zehn Sekunden pro Anfrage.

Die doppelte Login- und Registrierungsverarbeitung in index.php wurde entfernt. Seiten enthalten eine Viewport-Angabe; sichtbare Seitentitel verwenden CineScope. Die tatsächliche Darstellung auf verschiedenen Bildschirmgrößen ist noch zu prüfen.

## Noch offen

- Visuelle Browserprüfung auf Desktop und Mobilgeräten, einschließlich Bedienung der JavaScript-Suchvorschläge und Tastaturbedienung. Die bisherige Computer-Use-Prüfung wurde wegen nicht zuverlässig erkennbarer Browseradresse beendet.
- Die normale XAMPP-Installation wurde erneut per HTTP geprueft und antwortet aktuell ohne die zuvor beobachteten Datenbankwartezeiten. Eine Datenbankreparatur wurde dabei nicht vorgenommen; die Ursache der frueheren Stoerung wurde nicht abschliessend geklaert.
- Einrichtung eines eigenen Admin-Passworts und Ersetzen des alten API-Schlüssels vor einer Veröffentlichung.
- Die alten Screenshots wurden entfernt, da sie nicht als aktuelle Ansicht bestaetigt waren. Herkunft des uebernommenen Logos klaeren; externe Bildquellen stehen in ASSETS.md.
- API-Suche nach Schauspieler, Regie und Genre filtert Titel-Suchergebnisse und ist keine vollständige Suche in diesen Kategorien.

Es wurde noch nichts auf GitHub veröffentlicht. Die Tests sind keine Zusicherung für einen öffentlichen Produktivbetrieb.

## Bereinigung fuer GitHub

Verschachtelte Projektstaende, Unterrichtsnotizen, redundante SQL-Skripte, alte Screenshots, erzeugte Exporte, Datenbankbackups, ungenutzte OOP-Demoklassen und die zusaetzliche README.txt wurden aus der aktiven Version entfernt. Vorher wurden 135 Dateien in einem lokalen ZIP-Archiv gesichert und per SHA-256 verglichen. Dieses Archiv liegt ausschliesslich im ausgeschlossenen und per Apache gesperrten Verzeichnis dist/.

Die Ausgabe enthaelt 49 Dateien, darunter 23 PHP-Dateien ohne Syntaxfehler. Statische lokale Links wurden auf fehlende Ziele geprueft. Die Installation verwendet schema.sql und seed.sql mit der Standarddatenbank cinescope. Die vorhandene lokale Datenbankauswahl wurde in config.local.php erhalten.

## Erneute Pruefung der normalen XAMPP-Installation

Am 17. November 2026 antworteten die normale Startseite und Filmuebersicht mit HTTP 200 ohne angezeigte Datenbankfehler. Sechs aktuell verlinkte Film-Detailseiten wurden erfolgreich geoeffnet. Die AJAX-Suche lieferte passende JSON-Treffer. Drei zufaellig auf der Startseite dargestellte externe Poster lieferten HTTP 200 mit Inhaltstyp image/jpeg. Die lokale Ersatzgrafik war ebenfalls erreichbar. Es wurden keine Benutzer oder Filmdaten veraendert.

Login-, Registrierungs- und Informationsseite sind erreichbar. Die Kontaktseite verlangt erwartungsgemaess eine Anmeldung. Der lokale Admin-Zugang ist weiterhin nicht eingerichtet. Dies war eine HTTP-/Bildquellenpruefung, keine visuelle Browserpruefung.
