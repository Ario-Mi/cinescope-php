<?php
require_once __DIR__ . '/security.php';

// ==========================================
// Zentrale DB-Verbindung fuer CineScope
// ==========================================

/**
 * Gibt eine PDO-Verbindung zur Datenbank "cinescope" zurueck.
 * So muessen wir die Verbindungsdaten nicht in jeder Datei doppelt schreiben.
 */
function getPDO(): PDO
{
    // Bound connection and network waits so database failures do not hang pages indefinitely.
    ini_set('mysqlnd.net_read_timeout', '5');
    // XAMPP-Standarddaten
    $host = appConfig('DB_HOST', 'localhost');
    $port = appConfig('DB_PORT', '3306');
    $dbName = appConfig('DB_NAME', 'cinescope');
    $dbUser = appConfig('DB_USER', 'root');
    $dbPass = appConfig('DB_PASSWORD');

    // PDO-Verbindung aufbauen
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_TIMEOUT => 5]
    );

    // Fehler direkt als Exception werfen
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}
