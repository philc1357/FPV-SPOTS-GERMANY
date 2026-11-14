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

// =============================================================
// Ausgabepuffer: Header/Cookies auch nach Seitenbeginn erlauben
// -------------------------------------------------------------
// header.php zählt den Besuch (track_visit) und setzt dabei das
// Tages-Cookie fpv_counted_today – zu diesem Zeitpunkt ist <head>
// aber schon ausgegeben. Ohne Puffer scheitert setcookie() mit
// "headers already sent", und jeder Aufruf zählt als neuer
// Besucher. Der Puffer wird am Skriptende automatisch geleert.
// Nur einmal starten (session_init kann mehrfach geladen werden,
// php.ini kann bereits output_buffering gesetzt haben).
// =============================================================
if (PHP_SAPI !== 'cli' && ob_get_level() === 0) {
    ob_start();
}

// Eingabe-Helfer (post_str) für alle Seiten und Submit-Handler bereitstellen
require_once __DIR__ . '/input.php';
