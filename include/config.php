<?php

// Keep PHP diagnostics in the server log rather than the response.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

/** Local configuration stays outside version control. Environment values take priority. */
function appConfig(string $name, string $default = ''): string
{
    static $local = null;
    if ($local === null) {
        $path = __DIR__ . '/config.local.php';
        $local = is_file($path) ? require $path : [];
    }
    $value = getenv($name);
    return $value !== false ? $value : (string) ($local[$name] ?? $default);
}
