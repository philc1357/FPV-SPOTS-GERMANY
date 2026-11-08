<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Eingabe-Helfer (post_str) für alle Seiten und Submit-Handler bereitstellen
require_once __DIR__ . '/input.php';
