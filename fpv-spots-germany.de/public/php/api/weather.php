<?php
declare(strict_types=1);
// =============================================================
// API: GET /api/weather.php?spot_id=X – Wetter an einem Spot
//
// Serverseitiger Proxy zu Open-Meteo und zum NOAA-Kp-Index.
// Begruendung fuer den Umweg ueber den eigenen Server steht im
// Kopf von private/php/core/weather.php.
//
// Warum spot_id und nicht lat/lng aus der URL:
//
//   1. Der Endpunkt wird damit KEIN offener Wetter-Proxy fuer
//      beliebige Weltkoordinaten auf unsere Kosten und unter
//      unserer IP.
//   2. Der Cache-Keyraum ist auf die Zahl vorhandener Spots
//      begrenzt – niemand kann die Platte mit Millionen von
//      Koordinaten-Eintraegen fluten.
//   3. Die Koordinate stammt aus der Datenbank, nicht aus der
//      Eingabe. Eine Parameter-Injection Richtung Upstream ist
//      damit strukturell ausgeschlossen, nicht nur weggefiltert.
//
// Die Antwort bewertet nichts. Sie enthaelt Messwerte, Prognose-
// werte und den WMO-Klartext – ob ein Flug sinnvoll und zulaessig
// ist, entscheidet der Pilot.
// =============================================================
require_once __DIR__ . '/../../../private/php/core/session_init.php';
require_once __DIR__ . '/../../../private/php/core/db.php';        // $pdo + client_ip()
require_once __DIR__ . '/../../../private/php/core/weather.php';

header('Content-Type: application/json; charset=utf-8');

/** Einheitlicher Abbruch: nach aussen generisch, Details nur ins Log. */
function weather_abbruch(int $status, string $meldung): never
{
    http_response_code($status);
    echo json_encode(['error' => $meldung], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------
// 1. Methode – der Endpunkt ist rein lesend
// ---------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    weather_abbruch(405, 'Methode nicht erlaubt.');
}

// ---------------------------------------------------------------
// 2. Eingabe validieren
// ---------------------------------------------------------------
$spotId = filter_input(INPUT_GET, 'spot_id', FILTER_VALIDATE_INT);
if ($spotId === false || $spotId === null || $spotId <= 0) {
    weather_abbruch(400, 'Gültige Spot-ID erforderlich.');
}

// ---------------------------------------------------------------
// 3. Koordinate aus der Datenbank
//
// Dieselbe Sichtbarkeitsregel wie in api/spot.php: einen privaten
// Spot sieht nur sein Eigentuemer. Ohne diese Bedingung waere der
// Endpunkt ein Orakel dafuer, welche Spot-IDs existieren.
// ---------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT latitude, longitude
     FROM spots
     WHERE id = ?
       AND (is_private = 0 OR user_id = ?)"
);
$stmt->execute([$spotId, (int) ($_SESSION['user_id'] ?? 0)]);
$spot = $stmt->fetch();

if (!$spot || $spot['latitude'] === null || $spot['longitude'] === null) {
    weather_abbruch(404, 'Spot nicht gefunden.');
}

$lat = (float) $spot['latitude'];
$lon = (float) $spot['longitude'];

// Sicherheitsnetz gegen unbrauchbare Datensaetze: eine kaputte
// Koordinate soll keinen sinnlosen Upstream-Abruf ausloesen.
if ($lat < -90.0 || $lat > 90.0 || $lon < -180.0 || $lon > 180.0) {
    error_log('weather: Spot ' . $spotId . ' hat eine ungueltige Koordinate');
    weather_abbruch(404, 'Spot nicht gefunden.');
}

$key = weather_cache_key($lat, $lon);

// ---------------------------------------------------------------
// 4. Cache-Treffer – der Normalfall
// ---------------------------------------------------------------
$treffer = weather_cache_read($key, WEATHER_TTL);
if ($treffer !== null) {
    // Restlaufzeit als max-age: der Browser fragt erst wieder nach,
    // wenn dieser Eintrag ohnehin erneuert werden muss.
    $rest = max(60, WEATHER_TTL - $treffer['alter']);
    header('Cache-Control: private, max-age=' . $rest);
    echo json_encode($treffer['daten'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------
// 5. Rate-Limit – gezaehlt werden nur echte Upstream-Abrufe
// ---------------------------------------------------------------
if (weather_rate_hit(client_ip())) {
    header('Retry-After: 600');
    weather_abbruch(429, 'Zu viele Abrufe. Bitte später erneut versuchen.');
}

// ---------------------------------------------------------------
// 6. Abruf bei Open-Meteo
//
// Faellt der Anbieter aus, wird ein veralteter Cache-Eintrag als
// Notreserve ausgeliefert. Der mitgelieferte Zeitstempel "stand"
// stammt aus dem urspruenglichen Abruf, die Anzeige bleibt also
// ehrlich – eine sichtbar alte Prognose ist unterwegs auf dem Feld
// nuetzlicher als eine Fehlermeldung.
// ---------------------------------------------------------------
$raw = weather_fetch_forecast($lat, $lon);

if ($raw === null) {
    $alt = weather_cache_read($key, WEATHER_STALE_MAX);
    if ($alt !== null) {
        header('Cache-Control: private, max-age=300');
        echo json_encode($alt['daten'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    error_log('weather: Open-Meteo nicht erreichbar, kein Cache fuer Spot ' . $spotId);
    weather_abbruch(503, 'Wetterdaten sind derzeit nicht verfügbar.');
}

// Kp-Index ist optional: eigener globaler Cache, Fehler = null.
$kp = weather_fetch_kp();

$daten = weather_shape($raw, $kp);
weather_cache_write($key, $daten);

// ---------------------------------------------------------------
// 7. Aufraeumen – selten und mit Zeitschranke, damit der GC
//    niemals den Request eines Nutzers spuerbar verzoegert.
// ---------------------------------------------------------------
if (random_int(1, 500) === 1) {
    weather_cache_gc();
}

header('Cache-Control: private, max-age=' . WEATHER_TTL);
echo json_encode($daten, JSON_UNESCAPED_UNICODE);
