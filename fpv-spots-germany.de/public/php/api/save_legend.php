<?php
declare(strict_types=1);
// =============================================================
// API: Legende-Filterauswahl in Cookie speichern
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['types']) || !isset($input['diffs'])) {
    http_response_code(400);
    exit;
}

$allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Wald', 'Windpark', 'Sonstige'];
$allowedDiffs = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];
$allowedSizes = ['Tinywhoop', '2-3 Zoll', '4-5 Zoll', '5+ Zoll'];

$types = array_values(array_intersect((array)$input['types'], $allowedTypes));
$diffs = array_values(array_intersect((array)$input['diffs'], $allowedDiffs));
$sizes = array_values(array_intersect((array)($input['sizes'] ?? []), $allowedSizes));

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
setcookie('legend_sizes', implode(',', $sizes), $cookieOpts);

http_response_code(204);
