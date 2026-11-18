--
-- Datenbank: cinescope
-- Thema: OMDb API + SQL
-- Stil: einfach wie im Unterricht
--

CREATE DATABASE IF NOT EXISTS cinescope
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE cinescope;

-- Reihenfolge ist wichtig (wegen Fremdschluessel)
DROP TABLE IF EXISTS films_actors;
DROP TABLE IF EXISTS films_writers;
DROP TABLE IF EXISTS films_directors;
DROP TABLE IF EXISTS films_genres;
DROP TABLE IF EXISTS ratings;
DROP TABLE IF EXISTS saved_films;
DROP TABLE IF EXISTS comments;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS actors;
DROP TABLE IF EXISTS writers;
DROP TABLE IF EXISTS directors;
DROP TABLE IF EXISTS genres;
DROP TABLE IF EXISTS films;
DROP TABLE IF EXISTS users;

-- User: Register mit email, alias, passwort
CREATE TABLE IF NOT EXISTS users
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(120) NOT NULL,
    alias VARCHAR(50) NOT NULL,
    passwort VARCHAR(255) NOT NULL,
    UNIQUE (email),
    UNIQUE (alias)
);

-- Filme aus OMDb
CREATE TABLE IF NOT EXISTS films
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    imdbID VARCHAR(20) NOT NULL,
    -- TMDb-ID: nur bei Filmen aus dem TMDb-Import gesetzt
    tmdbID VARCHAR(20) NULL,
    title VARCHAR(200) NOT NULL,
    year VARCHAR(20),
    runtime VARCHAR(40),
    poster TEXT,
    plot TEXT,
    language VARCHAR(200),
    country VARCHAR(200),
    imdbRating VARCHAR(10),
    -- YouTube-Key des Trailers. NULL = noch nicht gesucht, '' = kein Trailer gefunden
    trailer_key VARCHAR(30) NULL,
    UNIQUE (imdbID),
    UNIQUE (tmdbID)
);

-- Stammtabellen
CREATE TABLE IF NOT EXISTS genres
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    genre VARCHAR(100) NOT NULL,
    UNIQUE (genre)
);

CREATE TABLE IF NOT EXISTS directors
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    director VARCHAR(150) NOT NULL,
    UNIQUE (director)
);

CREATE TABLE IF NOT EXISTS writers
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    writer VARCHAR(150) NOT NULL,
    UNIQUE (writer)
);

CREATE TABLE IF NOT EXISTS actors
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    actor VARCHAR(150) NOT NULL,
    UNIQUE (actor)
);

-- JOIN-Tabellen (n:m)
CREATE TABLE IF NOT EXISTS films_genres
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    films_id INT NOT NULL,
    genres_id INT NOT NULL,
    UNIQUE (films_id, genres_id),
    FOREIGN KEY (films_id) REFERENCES films(id) ON DELETE CASCADE,
    FOREIGN KEY (genres_id) REFERENCES genres(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS films_directors
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    films_id INT NOT NULL,
    directors_id INT NOT NULL,
    UNIQUE (films_id, directors_id),
    FOREIGN KEY (films_id) REFERENCES films(id) ON DELETE CASCADE,
    FOREIGN KEY (directors_id) REFERENCES directors(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS films_writers
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    films_id INT NOT NULL,
    writers_id INT NOT NULL,
    UNIQUE (films_id, writers_id),
    FOREIGN KEY (films_id) REFERENCES films(id) ON DELETE CASCADE,
    FOREIGN KEY (writers_id) REFERENCES writers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS films_actors
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    films_id INT NOT NULL,
    actors_id INT NOT NULL,
    UNIQUE (films_id, actors_id),
    FOREIGN KEY (films_id) REFERENCES films(id) ON DELETE CASCADE,
    FOREIGN KEY (actors_id) REFERENCES actors(id) ON DELETE CASCADE
);

-- Welche Filme hat ein User gespeichert?
CREATE TABLE IF NOT EXISTS saved_films
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    users_id INT NOT NULL,
    films_id INT NOT NULL,
    gespeichert_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (users_id, films_id),
    FOREIGN KEY (users_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (films_id) REFERENCES films(id) ON DELETE CASCADE
);

-- Bewertungen: 1 bis 5 Sterne, pro User und Film genau eine Bewertung
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

-- Kommentare: nur angemeldete User duerfen zu Filmen schreiben
CREATE TABLE IF NOT EXISTS comments
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    users_id INT NOT NULL,
    films_id INT NOT NULL,
    kommentar TEXT NOT NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (users_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (films_id) REFERENCES films(id) ON DELETE CASCADE
);

-- Nachrichten: angemeldete User duerfen Nachricht an Admin senden
CREATE TABLE IF NOT EXISTS messages
(
    id INT PRIMARY KEY AUTO_INCREMENT,
    users_id INT NOT NULL,
    betreff VARCHAR(150) NOT NULL,
    nachricht TEXT NOT NULL,
    erstellt_am DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (users_id) REFERENCES users(id) ON DELETE CASCADE
);
