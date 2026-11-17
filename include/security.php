<?php

require_once __DIR__ . '/config.php';

function inputText(array $source, string $name, string $default = ''): string
{
    return isset($source[$name]) && is_string($source[$name]) ? trim($source[$name]) : $default;
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function reportAppError(Throwable $error): string
{
    error_log('[CineScope] ' . get_class($error) . ': ' . $error->getMessage());
    return 'Die Anfrage konnte nicht verarbeitet werden. Bitte versuche es später erneut.';
}

function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'path' => '/',
        ]);
        session_start();
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $token = $_POST['csrf_token'] ?? null;
        if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
            http_response_code(403);
            exit('Die Formularanfrage ist ungültig oder abgelaufen. Bitte lade die Seite neu.');
        }
    }
}
