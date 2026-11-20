<?php
declare(strict_types=1);
// =============================================================
// Session-Hardening
// -------------------------------------------------------------
// Die php_flag-Werte in der .htaccess greifen nur unter mod_php.
// Unter PHP-FPM/CGI würden sie ignoriert – deshalb hier zusätzlich
// per ini_set(), das unabhängig vom Server-API wirkt:
//  - use_strict_mode:  vom Client erfundene Session-IDs ablehnen
//                      (Schutz gegen Session-Fixation)
//  - use_only_cookies: keine Session-ID per URL-Parameter
// =============================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
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
