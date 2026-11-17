<?php
// CLI-only helper. It only creates/drops randomly named disposable test databases.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../include/config.php';
$name = $argv[2] ?? '';
if (!preg_match('/^cinescope_test_[a-f0-9]{16}$/D', $name)) {
    throw new InvalidArgumentException('Invalid test database name');
}
$pdo = new PDO('mysql:host=' . appConfig('DB_HOST', 'localhost') . ';port=' . appConfig('DB_PORT', '3306') . ';charset=utf8mb4',
    appConfig('DB_USER', 'root'), appConfig('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (($argv[1] ?? '') === 'create') {
    // CREATE without IF NOT EXISTS prevents reuse of an existing database.
    $pdo->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
    foreach (['schema.sql', 'seed.sql'] as $file) {
        $sql = file_get_contents(__DIR__ . '/../data/sql/' . $file);
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = str_replace('cinescope', $name, $sql);
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            if (trim($statement) !== '') {
                $pdo->query($statement)->closeCursor();
            }
        }
    }
    $pdo->exec("INSERT INTO films (imdbID, title, year) VALUES ('tt0000000', 'Pagination Test Film', '2026')");
    echo "Test database created.\n";
} elseif (($argv[1] ?? '') === 'drop') {
    $pdo->exec("DROP DATABASE `$name`");
    echo "Test database removed.\n";
} elseif (($argv[1] ?? '') === 'snapshot') {
    $pdo->exec("USE `$name`");
    $snapshot = [];
    foreach (['users', 'films', 'comments', 'saved_films', 'messages'] as $table) {
        $snapshot[$table] = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    }
    echo json_encode($snapshot);
} else {
    throw new InvalidArgumentException('Unknown fixture action');
}
