<?php
declare(strict_types=1);
// =============================================================
// API: DIPUL-Kachel-Proxy (WMS)
//
// Reicht die amtlichen Geozonen-Kacheln der Deutschen Flugsicherung
// an die Karte durch. Bewusst als Proxy und nicht als direkter
// Browser-Request an uas-betrieb.dfs.de:
//
//   1. Die CSP bleibt bei img-src 'self' – kein neuer Fremdhost.
//   2. Die IP unserer Besucher erreicht die DFS nie; die Datenschutz-
//      erklaerung braucht keinen zusaetzlichen Empfaenger.
//   3. Die Cache-Dauer laesst sich je Buendel steuern – temporaere
//      Betriebseinschraenkungen duerfen nur Minuten alt sein.
//
// Lizenz: Die Kacheln stehen unter CC BY-ND 4.0. Sie werden hier
// byteweise unveraendert durchgereicht – kein Umrendern, kein
// Umfaerben, kein Zusammensetzen. Das ist Lizenzbedingung, nicht
// Bequemlichkeit. Die Quellenangabe "Quelle Geodaten: DFS, BKG"
// setzt map.js als Leaflet-Attribution.
// =============================================================

require_once __DIR__ . '/../../../private/php/core/geozones.php';
require_once __DIR__ . '/../../../private/php/core/client_ip.php';   // client_ip() fuer das Rate-Limit

// -------------------------------------------------------------
// Zwei Arten von Fehlerantworten
//
// 1. Betriebsstoerung – Ausfall der DFS, Timeout, erreichtes
//    Rate-Limit, Zoom unterhalb der Cache-Grenze: transparente
//    1x1-Kachel mit HTTP 200. Ein 5xx wuerde Leaflet kaputte
//    Kacheln zeichnen oder wiederholt nachladen lassen; die Karte
//    muss auch ohne Geozonen benutzbar bleiben.
//
// 2. Ungueltige Anfrage – unbekanntes Buendel, kaputte BBOX,
//    fremdes CRS: HTTP 400. Solche Requests kann Leaflet gar nicht
//    erzeugen, sie stammen immer von Hand. Ein stiller 200 wuerde
//    hier nur das Debuggen erschweren und Angriffsversuche
//    verschleiern.
// -------------------------------------------------------------
const EMPTY_TILE_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

function send_empty_tile(string $reason = ''): never
{
    if ($reason !== '') {
        error_log('dipul_wms: ' . $reason);
    }
    header('Content-Type: image/png');
    header('Cache-Control: no-store');
    echo base64_decode(EMPTY_TILE_B64);
    exit;
}

function send_bad_request(string $reason): never
{
    error_log('dipul_wms: ungueltige Anfrage – ' . $reason);
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "Ungueltige Anfrage\n";   // Nie den internen Grund nach aussen geben
    exit;
}

function send_tile(string $png, int $ttl): never
{
    header('Content-Type: image/png');
    header('Cache-Control: private, max-age=' . $ttl);
    header('X-Content-Type-Options: nosniff');
    echo $png;
    exit;
}

// -------------------------------------------------------------
// Eingabevalidierung
//
// Es wird NICHTS aus $_GET an die DFS durchgereicht. Der Proxy
// akzeptiert nur die wenigen Werte, die Leaflet variiert, prueft
// jeden davon und baut die Upstream-URL anschliessend selbst neu
// zusammen. Damit ist weder Parameter-Injection noch SSRF moeglich.
// -------------------------------------------------------------

// 1. Buendel: nur die fuenf definierten Namen, alles andere fliegt raus
$bundle = (string) ($_GET['layers'] ?? '');
$upstreamLayers = dipul_bundle_layers($bundle);
if ($upstreamLayers === null) {
    send_bad_request('unbekanntes Buendel: ' . substr($bundle, 0, 40));
}

// 2. Kachelgroesse: Leaflet fordert 256er-Kacheln, 512 fuer Retina
$width  = (int) ($_GET['width']  ?? 0);
$height = (int) ($_GET['height'] ?? 0);
if (!in_array($width, [256, 512], true) || !in_array($height, [256, 512], true)) {
    send_bad_request('unzulaessige Kachelgroesse');
}

// 3. Koordinatensystem: ausschliesslich Web Mercator
$crs = (string) ($_GET['crs'] ?? $_GET['srs'] ?? '');
if ($crs !== 'EPSG:3857') {
    send_bad_request('unzulaessiges CRS: ' . substr($crs, 0, 20));
}

// 4. BBOX: exakt vier endliche Zahlen innerhalb der Web-Mercator-Grenzen
$parts = explode(',', (string) ($_GET['bbox'] ?? ''));
if (count($parts) !== 4) {
    send_bad_request('BBOX mit falscher Feldzahl');
}
$bbox = [];
foreach ($parts as $part) {
    if (!is_numeric($part)) {
        send_bad_request('BBOX nicht numerisch');
    }
    $value = (float) $part;
    if (!is_finite($value) || abs($value) > 20037509.0) {
        send_bad_request('BBOX ausserhalb der Web-Mercator-Grenzen');
    }
    $bbox[] = $value;
}
[$minX, $minY, $maxX, $maxY] = $bbox;
if ($minX >= $maxX || $minY >= $maxY) {
    send_bad_request('BBOX degeneriert');
}

// 5. Zoomstufe aus der BBOX-Breite ableiten
//
// Leaflet uebermittelt die Zoomstufe nicht. Sie laesst sich aber aus
// der Kachelbreite errechnen: bei Zoom z ist eine Kachel
// 40075016.686 / 2^z Meter breit. Unterhalb von Zoom 8 wuerde eine
// einzige Kachel halbe Bundeslaender abdecken – das ist weder lesbar
// noch cachebar (die Zahl moeglicher BBOXen explodiert bei jedem
// Schwenk). map.js setzt dieselbe Grenze nochmal clientseitig.
const MIN_ZOOM = 8;
$tileSpanMeters = $maxX - $minX;
$zoom = (int) round(log(40075016.686 / $tileSpanMeters, 2));
if ($zoom < MIN_ZOOM) {
    send_empty_tile();
}

// -------------------------------------------------------------
// Cache
//
// Schluessel ist der Hash aus Buendel, BBOX und Kachelgroesse.
// Die Ablage in zwei Ebenen haelt die Verzeichnisse klein genug,
// dass Dateisystemoperationen schnell bleiben.
// -------------------------------------------------------------
const CACHE_ROOT       = __DIR__ . '/../../../private/data/dipul_cache';
const CACHE_MAX_BYTES  = 500 * 1024 * 1024;   // Obergrenze fuer den gesamten Kachel-Cache
const GC_MAX_SECONDS   = 2;                    // Zeitschranke fuer den Cache-GC im Kachel-Request

$ttl = DIPUL_BUNDLE_TTL[$bundle] ?? 3600;
$key = sha1($bundle . '|' . implode(',', $bbox) . '|' . $width . 'x' . $height);
$dir = CACHE_ROOT . '/' . $bundle . '/' . substr($key, 0, 2);
$file = $dir . '/' . $key . '.png';

if (is_file($file) && (time() - filemtime($file)) < $ttl) {
    $cached = file_get_contents($file);
    if ($cached !== false && $cached !== '') {
        send_tile($cached, $ttl);
    }
}

// -------------------------------------------------------------
// Rate-Limit auf Upstream-Abrufe
//
// Gezaehlt werden bewusst nur Cache-Misses, denn nur die belasten
// die DFS. Ein Nutzer, der ueber eine bereits besuchte Region
// schwenkt, laeuft nie ins Limit.
//
// Bewusst dateibasiert und nicht ueber audit_logs: eine einzige
// Kartenansicht loest schon dutzende Kachelabrufe aus. Die wuerden
// die Audit-Tabelle fluten und ihren eigentlichen Zweck – eine
// forensisch lesbare Spur sicherheitsrelevanter Aktionen –
// unbrauchbar machen. Gespeichert wird nur ein Hash der IP in einem
// Stundenbucket, der von der Cache-Bereinigung mit aufgeraeumt wird.
// -------------------------------------------------------------
const RATE_LIMIT_PER_HOUR = 800;

$rateDir  = CACHE_ROOT . '/_rate';
$rateFile = $rateDir . '/' . sha1(client_ip() . '|' . date('YmdH')) . '.cnt';

if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0775, true);
}
$hits = is_file($rateFile) ? (int) file_get_contents($rateFile) : 0;
if ($hits >= RATE_LIMIT_PER_HOUR) {
    send_empty_tile();
}
@file_put_contents($rateFile, (string) ($hits + 1), LOCK_EX);

// -------------------------------------------------------------
// Upstream-Abruf: URL wird vollstaendig selbst zusammengebaut
// -------------------------------------------------------------
$url = DIPUL_WMS_URL . '?' . http_build_query([
    'service'     => 'WMS',
    'version'     => '1.3.0',
    'request'     => 'GetMap',
    'layers'      => $upstreamLayers,
    'styles'      => '',
    'format'      => 'image/png',
    'transparent' => 'true',
    'crs'         => 'EPSG:3857',
    'width'       => $width,
    'height'      => $height,
    'bbox'        => implode(',', $bbox),
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_USERAGENT      => 'fpv-spots-germany.de Kartenproxy',
]);
$body        = curl_exec($ch);
$status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// Bei einem Fehler antwortet der WMS mit einem XML-ExceptionReport –
// den duerfen wir nicht als Bild ausliefern, sonst zeigt die Karte Muell.
if ($status !== 200 || !is_string($body) || $body === '' || !str_starts_with($contentType, 'image/')) {
    send_empty_tile('Upstream-Antwort unbrauchbar (HTTP ' . $status . ', ' . $contentType . ')');
}

// -------------------------------------------------------------
// Cache schreiben (atomar ueber eine temporaere Datei, damit ein
// abgebrochener Schreibvorgang keine halbe Kachel hinterlaesst)
// -------------------------------------------------------------
if (is_dir($dir) || @mkdir($dir, 0775, true) || is_dir($dir)) {
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $body) !== false) {
        @rename($tmp, $file);
    }
}

// -------------------------------------------------------------
// Cache-Bereinigung, probabilistisch bei ~0,05 % der Abrufe
// (dasselbe Muster wie die DSGVO-Loeschung in db.php)
//
// Der GC laeuft synchron IM Kachel-Request. Bei 478 Dateien sind das
// wenige Millisekunden, an der Obergrenze CACHE_MAX_BYTES waeren es
// aber rund 50.000 Dateien mit je einem stat() – dann wuerde ein vom
// Nutzer angeforderter Tile spuerbar blockieren. Deshalb seltener
// als frueher (1:2000 statt 1:200) und mit harter Zeitschranke.
// -------------------------------------------------------------
if (random_int(1, 2000) === 1) {
    dipul_cache_gc();
}

send_tile($body, $ttl);


/**
 * Raeumt abgelaufene Kacheln, Rate-Limit-Zaehler und – falls die
 * Obergrenze ueberschritten ist – die aeltesten Dateien weg.
 */
function dipul_cache_gc(): void
{
    if (!is_dir(CACHE_ROOT)) {
        return;
    }

    $files = [];
    $total = 0;
    $now   = time();

    // Zeitschranke: der GC darf den laufenden Kachel-Request nicht
    // laenger als GC_MAX_SECONDS aufhalten. Was liegen bleibt, raeumt
    // der naechste Durchlauf – die TTL-Loeschung ist idempotent.
    $deadline = microtime(true) + GC_MAX_SECONDS;
    $checked  = 0;
    $aborted  = false;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(CACHE_ROOT, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $entry) {
        /** @var SplFileInfo $entry */
        if (!$entry->isFile()) {
            continue;
        }

        // microtime() nur alle 500 Dateien, sonst kostet die Pruefung selbst
        if ((++$checked % 500) === 0 && microtime(true) > $deadline) {
            $aborted = true;
            break;
        }
        $path  = $entry->getPathname();
        $mtime = $entry->getMTime();

        // Rate-Limit-Zaehler: nach zwei Stunden wertlos
        if (str_ends_with($path, '.cnt')) {
            if ($now - $mtime > 7200) {
                @unlink($path);
            }
            continue;
        }

        // Abgelaufene Kacheln anhand der TTL ihres Buendels
        $bundle = basename(dirname(dirname($path)));
        $ttl    = DIPUL_BUNDLE_TTL[$bundle] ?? 3600;
        if ($now - $mtime > $ttl) {
            @unlink($path);
            continue;
        }

        $files[] = ['path' => $path, 'mtime' => $mtime, 'size' => $entry->getSize()];
        $total  += $entry->getSize();
    }

    // Nach einem Abbruch sind $files und $total unvollstaendig – die
    // Obergrenze liesse sich damit nicht sinnvoll durchsetzen.
    if ($aborted) {
        error_log('dipul_wms.php: Cache-GC nach ' . GC_MAX_SECONDS . ' s abgebrochen (' . $checked . ' Dateien geprueft)');
        return;
    }

    // Obergrenze halten: aelteste Kacheln zuerst entfernen
    if ($total > CACHE_MAX_BYTES) {
        usort($files, static fn(array $a, array $b): int => $a['mtime'] <=> $b['mtime']);
        foreach ($files as $f) {
            if ($total <= CACHE_MAX_BYTES) {
                break;
            }
            if (@unlink($f['path'])) {
                $total -= $f['size'];
            }
        }
    }
}
