<?php
declare(strict_types=1);

// ============================================================
// Session-Initialisierung mit gehärteten Cookie-Parametern
// (greift insbesondere bei PHP-FPM, wo .htaccess-Flags ignoriert
// werden)
// ============================================================

if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
