<?php

// Run with: C:\xampp\php\php.exe scripts\configure-admin.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$path = __DIR__ . '/../include/config.local.php';
$config = is_file($path) ? require $path : [];
echo "Admin-Benutzername: ";
$name = trim((string) fgets(STDIN));
echo "Neues Admin-Passwort (mindestens 12 Zeichen; Eingabe ist sichtbar): ";
$password = rtrim((string) fgets(STDIN), "\r\n");
if ($name === '' || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Benutzername fehlt oder Passwort ist zu kurz. Nichts geändert.\n");
    exit(1);
}
$config['ADMIN_USERNAME'] = $name;
$config['ADMIN_PASSWORD_HASH'] = password_hash($password, PASSWORD_DEFAULT);
if (file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) === false) {
    fwrite(STDERR, "Konfiguration konnte nicht gespeichert werden.\n");
    exit(1);
}
echo "Admin-Zugang lokal eingerichtet. Das Passwort wurde nur als Hash gespeichert.\n";
