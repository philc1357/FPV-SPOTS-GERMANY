<?php
declare(strict_types=1);
// =============================================================
// API: Betriebsklasse des Piloten in Cookie speichern
//
// Die Klasse steuert, welche Rechtstexte und Pflichten angezeigt
// werden und ob der 150-m-Abstandskreis der Unterkategorie A3 auf
// der Karte erscheint. Reine Anzeigepraeferenz – kein Login noetig,
// keine Personendaten, deshalb auch keine CSRF-Pruefung: ein
// Angreifer koennte hoechstens die eigene Legende des Opfers
// umschalten, was keinen Schaden anrichtet.
//
// Zwei Aufrufwege:
//   1. fetch() mit JSON-Body -> 204, die Seite aktualisiert selbst
//   2. klassisches Form-POST -> PRG-Redirect, funktioniert ohne JS
//
// Whitelist ist PILOT_CLASSES in private/php/core/geozones.php;
// alles Unbekannte faellt auf PILOT_CLASS_DEFAULT ('a3', die
// strengste Annahme) zurueck. Im Zweifel lieber zu streng.
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . "/../../../private/php/core/geozones.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// -------------------------------------------------------------
// Eingabe: JSON-Body oder Formularfeld
// -------------------------------------------------------------
$isJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
$raw    = null;

if ($isJson) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input) && isset($input['class']) && is_string($input['class'])) {
        $raw = $input['class'];
    }
} elseif (isset($_POST['pilot_class']) && is_string($_POST['pilot_class'])) {
    $raw = $_POST['pilot_class'];
}

if ($raw === null) {
    http_response_code(400);
    exit;
}

// Unbekannte Werte werden nicht abgelehnt, sondern konservativ ersetzt
$class = pilot_class_valid($raw);

// Cookie-Laufzeit: 30 Tage wenn "eingeloggt bleiben" aktiv, sonst Session-Cookie
$expires = !empty($_COOKIE['remember_me']) ? time() + 30 * 86400 : 0;

setcookie('pilot_class', $class, [
    'expires'  => $expires,
    'path'     => '/',
    'httponly' => true,
    'secure'   => true,
    'samesite' => 'Lax',
]);

if ($isJson) {
    http_response_code(204);
    exit;
}

// -------------------------------------------------------------
// Ziel des PRG-Redirects
//
// Nur der Pfad-Anteil des uebergebenen Ziels wird verwendet, und nur
// wenn er mit genau einem "/" beginnt. Damit sind Open Redirects auf
// fremde Hosts ("//boese.example", "https://…") ausgeschlossen.
// -------------------------------------------------------------
$target = '/';
$wanted = (string) ($_POST['redirect'] ?? '');
if ($wanted !== '' && str_starts_with($wanted, '/') && !str_starts_with($wanted, '//')) {
    $path  = parse_url($wanted, PHP_URL_PATH) ?: '/';
    $query = parse_url($wanted, PHP_URL_QUERY);
    $frag  = parse_url($wanted, PHP_URL_FRAGMENT);
    $target = $path
        . ($query !== null ? '?' . $query : '')
        . ($frag  !== null ? '#' . $frag  : '');
}

header('Location: ' . $target);
exit;
