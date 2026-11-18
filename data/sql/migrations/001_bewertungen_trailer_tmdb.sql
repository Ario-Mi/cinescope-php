--
-- Migration fuer bestehende Installationen (MariaDB aus XAMPP)
-- Ergaenzt Bewertungen, Trailer und TMDb-ID, ohne vorhandene Daten zu loeschen.
-- Neue Installationen brauchen diese Datei nicht: schema.sql enthaelt alles.
--

USE cinescope;

ALTER TABLE films ADD COLUMN IF NOT EXISTS tmdbID VARCHAR(20) NULL AFTER imdbID;
ALTER TABLE films ADD COLUMN IF NOT EXISTS trailer_key VARCHAR(30) NULL AFTER imdbRating;
ALTER TABLE films ADD UNIQUE INDEX IF NOT EXISTS idx_tmdbID (tmdbID);

CREATE TABLE IF NOT EXISTS ratings
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    users_id INT NOT NULL,
    films_id INT NOT NULL,
    rating TINYINT NOT NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (users_id, films_id),
    CHECK (rating BETWEEN 1 AND 5),
    FOREIGN KEY (users_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (films_id) REFERENCES films(id) ON DELETE CASCADE
);
