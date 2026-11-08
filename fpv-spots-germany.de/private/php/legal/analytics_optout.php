<?php
declare(strict_types=1);
// =============================================================
// Widerspruch gegen die Reichweitenmessung (Art. 21 DSGVO)
//
// Die Messung laeuft ohne Einwilligung auf Grundlage des
// berechtigten Interesses (Art. 6 Abs. 1 lit. f DSGVO). Damit ist
// das Widerspruchsrecht das tragende Element – es muss wirksam
// und auffindbar sein. Der Schalter dafuer steht im Abschnitt
// "Reichweitenmessung" der Datenschutzerklaerung.
//
// Wirkung: Ist das Cookie analytics_optout gesetzt, liefert
// umami_enabled() false und das Tracking-Tag wird serverseitig
// gar nicht erst gerendert. Es entsteht also kein Request, den
// man noch umgehen koennte – deutlich sauberer als ein Flag im
// JavaScript.
//
// Bewusst ohne Login-Pflicht: der Widerspruch muss auch fuer
// nicht angemeldete Besucher funktionieren. Bewusst ohne
// DB-Schreibzugriff: es wird nichts gespeichert ausser dem
// Cookie im Browser des Besuchers, deshalb braucht der Endpunkt
// auch kein Rate-Limit ueber audit_logs.
// =============================================================
require_once __DIR__ . '/../core/session_init.php';
require_once __DIR__ . '/../core/analytics.php';   // UMAMI_OPTOUT_COOKIE

// Ziel des Redirects. Der Schalter steht in der Datenschutz-
// erklaerung; dorthin geht es auch in jedem Fehlerfall zurueck.
const ANALYTICS_OPTOUT_ZIEL = '/datenschutz.php#reichweitenmessung';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ANALYTICS_OPTOUT_ZIEL);
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
    http_response_code(403);
    die('CSRF-Fehler');
}

// Zwei Richtungen: Widerspruch einlegen und wieder zuruecknehmen.
// Alles andere ist ein manipuliertes Formular und wird ignoriert.
$modus = post_str('modus');
if (!in_array($modus, ['aus', 'ein'], true)) {
    header('Location: ' . ANALYTICS_OPTOUT_ZIEL);
    exit;
}

// -------------------------------------------------------------
// Cookie setzen bzw. loeschen
//
// Secure ist unkritisch: die .htaccess erzwingt HTTPS, ueber
// HTTP ist die Seite gar nicht erreichbar. HttpOnly, weil das
// Cookie ausschliesslich serverseitig ausgewertet wird.
// SameSite=Lax wie bei den uebrigen Cookies des Projekts.
// -------------------------------------------------------------
$optionen = [
    'expires'  => $modus === 'aus' ? time() + 365 * 24 * 60 * 60 : time() - 3600,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
];

setcookie(UMAMI_OPTOUT_COOKIE, $modus === 'aus' ? '1' : '', $optionen);

// Damit die Seite direkt nach dem Redirect schon den neuen Zustand
// zeigt – $_COOKIE spiegelt setcookie() im selben Request nicht wider,
// und die Zielseite liest ohnehin nur den Browser-Wert.
if ($modus === 'aus') {
    $_COOKIE[UMAMI_OPTOUT_COOKIE] = '1';
} else {
    unset($_COOKIE[UMAMI_OPTOUT_COOKIE]);
}

$_SESSION['analytics_flash'] = $modus === 'aus' ? 'aus' : 'ein';

header('Location: ' . ANALYTICS_OPTOUT_ZIEL);
exit;
