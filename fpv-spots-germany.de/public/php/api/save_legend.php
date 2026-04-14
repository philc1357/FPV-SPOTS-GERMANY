<?php
// =============================================================
// API: Legende-Filterauswahl in Cookie speichern
// =============================================================
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['types']) || !isset($input['diffs'])) {
    http_response_code(400);
    exit;
}

$allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Verein', 'Wasser', 'Sonstige'];
$allowedDiffs = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];

$types = array_values(array_intersect((array)$input['types'], $allowedTypes));
$diffs = array_values(array_intersect((array)$input['diffs'], $allowedDiffs));

// Cookie-Laufzeit: 30 Tage wenn "eingeloggt bleiben" aktiv, sonst Session-Cookie
$expires = !empty($_COOKIE['remember_me']) ? time() + 30 * 86400 : 0;

$cookieOpts = [
    'expires'  => $expires,
    'path'     => '/',
    'httponly'  => true,
    'secure'   => true,
    'samesite' => 'Lax',
];

setcookie('legend_types', implode(',', $types), $cookieOpts);
setcookie('legend_diffs', implode(',', $diffs), $cookieOpts);

http_response_code(204);
