--
-- Testdaten fuer cinescope
-- Schritt 2: Nur wenige Daten zum Testen (4 Filme)
-- WICHTIG: Erst create_cinescope.sql ausfuehren, dann diese Datei.
--

USE cinescope;

-- =========================
-- 1) Filme (3 Beispiele)
-- =========================
INSERT INTO films
(
    imdbID,
    title,
    year,
    runtime,
    poster,
    plot,
    language,
    country,
    imdbRating
)
VALUES
(
    'tt0133093',
    'The Matrix',
    '1999',
    '136 min',
    'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',
    'A computer hacker learns about the true nature of reality.',
    'English',
    'United States',
    '8.7'
),
(
    'tt0111161',
    'The Shawshank Redemption',
    '1994',
    '142 min',
    'https://image.tmdb.org/t/p/w500/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg',
    'Two imprisoned men bond over years and find redemption.',
    'English',
    'United States',
    '9.3'
),
(
    'tt0109830',
    'Forrest Gump',
    '1994',
    '142 min',
    'https://image.tmdb.org/t/p/w500/arw2vcBveWOVZr6pxd9XTd1TdQa.jpg',
    'The life journey of Forrest Gump across decades in America.',
    'English',
    'United States',
    '8.8'
),
(
    'tt1375666',
    'Inception',
    '2010',
    '148 min',
    'https://image.tmdb.org/t/p/w500/oYuLEt3zVCKq57qu2F8dT7NIa6f.jpg',
    'A thief who steals corporate secrets through dream-sharing technology is given an inverse task of planting an idea.',
    'English',
    'United States',
    '8.8'
);

-- =========================
-- 2) Stammdaten
-- =========================
INSERT INTO genres (genre)
VALUES
('Action'),
('Sci-Fi'),
('Drama'),
('Romance');

INSERT INTO directors (director)
VALUES
('Lana Wachowski'),
('Lilly Wachowski'),
('Frank Darabont'),
('Robert Zemeckis'),
('Christopher Nolan');

INSERT INTO writers (writer)
VALUES
('Lana Wachowski'),
('Lilly Wachowski'),
('Stephen King'),
('Eric Roth'),
('Winston Groom'),
('Christopher Nolan');

INSERT INTO actors (actor)
VALUES
('Keanu Reeves'),
('Laurence Fishburne'),
('Carrie-Anne Moss'),
('Tim Robbins'),
('Morgan Freeman'),
('Tom Hanks'),
('Robin Wright'),
('Gary Sinise'),
('Leonardo DiCaprio'),
('Joseph Gordon-Levitt'),
('Elliot Page');

-- =========================
-- 3) JOIN Tabellen fuellen
-- =========================

-- The Matrix -> Genres
INSERT INTO films_genres (films_id, genres_id)
SELECT f.id, g.id
FROM films f, genres g
WHERE f.imdbID = 'tt0133093'
AND g.genre IN ('Action', 'Sci-Fi');

-- Shawshank -> Genre
INSERT INTO films_genres (films_id, genres_id)
SELECT f.id, g.id
FROM films f, genres g
WHERE f.imdbID = 'tt0111161'
AND g.genre = 'Drama';

-- Forrest Gump -> Genres
INSERT INTO films_genres (films_id, genres_id)
SELECT f.id, g.id
FROM films f, genres g
WHERE f.imdbID = 'tt0109830'
AND g.genre IN ('Drama', 'Romance');

-- Inception -> Genres
INSERT INTO films_genres (films_id, genres_id)
SELECT f.id, g.id
FROM films f, genres g
WHERE f.imdbID = 'tt1375666'
AND g.genre IN ('Action', 'Sci-Fi');

-- The Matrix -> Directors
INSERT INTO films_directors (films_id, directors_id)
SELECT f.id, d.id
FROM films f, directors d
WHERE f.imdbID = 'tt0133093'
AND d.director IN ('Lana Wachowski', 'Lilly Wachowski');

-- Shawshank -> Director
INSERT INTO films_directors (films_id, directors_id)
SELECT f.id, d.id
FROM films f, directors d
WHERE f.imdbID = 'tt0111161'
AND d.director = 'Frank Darabont';

-- Forrest Gump -> Director
INSERT INTO films_directors (films_id, directors_id)
SELECT f.id, d.id
FROM films f, directors d
WHERE f.imdbID = 'tt0109830'
AND d.director = 'Robert Zemeckis';

-- Inception -> Director
INSERT INTO films_directors (films_id, directors_id)
SELECT f.id, d.id
FROM films f, directors d
WHERE f.imdbID = 'tt1375666'
AND d.director = 'Christopher Nolan';

-- The Matrix -> Writers
INSERT INTO films_writers (films_id, writers_id)
SELECT f.id, w.id
FROM films f, writers w
WHERE f.imdbID = 'tt0133093'
AND w.writer IN ('Lana Wachowski', 'Lilly Wachowski');

-- Shawshank -> Writer
INSERT INTO films_writers (films_id, writers_id)
SELECT f.id, w.id
FROM films f, writers w
WHERE f.imdbID = 'tt0111161'
AND w.writer = 'Stephen King';

-- Forrest Gump -> Writers
INSERT INTO films_writers (films_id, writers_id)
SELECT f.id, w.id
FROM films f, writers w
WHERE f.imdbID = 'tt0109830'
AND w.writer IN ('Eric Roth', 'Winston Groom');

-- Inception -> Writer
INSERT INTO films_writers (films_id, writers_id)
SELECT f.id, w.id
FROM films f, writers w
WHERE f.imdbID = 'tt1375666'
AND w.writer = 'Christopher Nolan';

-- The Matrix -> Actors
INSERT INTO films_actors (films_id, actors_id)
SELECT f.id, a.id
FROM films f, actors a
WHERE f.imdbID = 'tt0133093'
AND a.actor IN ('Keanu Reeves', 'Laurence Fishburne', 'Carrie-Anne Moss');

-- Shawshank -> Actors
INSERT INTO films_actors (films_id, actors_id)
SELECT f.id, a.id
FROM films f, actors a
WHERE f.imdbID = 'tt0111161'
AND a.actor IN ('Tim Robbins', 'Morgan Freeman');

-- Forrest Gump -> Actors
INSERT INTO films_actors (films_id, actors_id)
SELECT f.id, a.id
FROM films f, actors a
WHERE f.imdbID = 'tt0109830'
AND a.actor IN ('Tom Hanks', 'Robin Wright', 'Gary Sinise');

-- Inception -> Actors
INSERT INTO films_actors (films_id, actors_id)
SELECT f.id, a.id
FROM films f, actors a
WHERE f.imdbID = 'tt1375666'
AND a.actor IN ('Leonardo DiCaprio', 'Joseph Gordon-Levitt', 'Elliot Page');

-- =========================
-- 4) Kleine Kontrolle
-- =========================
SELECT id, title, year, imdbID
FROM films
ORDER BY id;
