<?php
declare(strict_types=1);
// =============================================================
// Wartung: Tracker-Script von Umami holen und pinnen
//
// Das Tracking-Script wird EINMAL heruntergeladen und als
// public/js/umami.js mit ausgeliefert – kein Laufzeit-Proxy.
// Gruende:
//   - Die CSP erlaubt nur 'self'; ein Script von cloud.umami.is
//     wuerde stumm blockiert (wie damals das CDN-Leaflet).
//   - Pro Seitenaufruf faellt kein zusaetzlicher ausgehender
//     Request an, die Datei laeuft durch asset() und den
//     Service-Worker-Cache.
//   - Filterlisten kennen /public/js/umami.js nicht.
//
// Preis dafuer: Die Kopie veraltet still, wenn Umami das Script
// aendert. Deshalb dieses Skript – der Trockenlauf zeigt, ob sich
// oben etwas getan hat, ohne etwas anzufassen. Halbjaehrlich
// laufen lassen, danach die Realtime-Ansicht pruefen.
//
// Aufruf:
//   php private/php/maintenance/update_umami_script.php           (nur pruefen)
//   php private/php/maintenance/update_umami_script.php --apply   (ersetzen)
// =============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Nur per CLI ausfuehrbar.');
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../..')->safeLoad();

$apply  = in_array('--apply', $argv, true);
$source = rtrim(trim($_ENV['UMAMI_SCRIPT_URL'] ?? ''), '/');
$target = __DIR__ . '/../../../public/js/umami.js';

if (!preg_match('#^https://[a-z0-9.-]+/[a-z0-9./_-]+\.js$#i', $source)) {
    exit("UMAMI_SCRIPT_URL fehlt oder ist ungueltig.\n");
}

// --- Herunterladen ------------------------------------------------
// Kurze Timeouts: Das Skript laeuft von Hand, ein haengender
// Request soll nicht minutenlang blockieren.
$ch = curl_init($source);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT      => 'fpv-spots-germany.de update_umami_script',
]);
$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$error  = curl_error($ch);
curl_close($ch);

if ($body === false || $status !== 200) {
    exit("Download fehlgeschlagen (HTTP $status). $error\n");
}

// --- Plausibilitaetspruefung --------------------------------------
// Lieber gar nicht ersetzen als eine Fehlerseite nach public/js/
// schreiben. Geprueft wird auf Groesse und auf zwei Marker, die
// im echten Tracker garantiert vorkommen.
$size = strlen($body);
if ($size < 1000 || $size > 200000) {
    exit("Verdaechtige Groesse ($size Bytes) – nicht uebernommen.\n");
}
foreach (['data-', 'website-id', '/api/send'] as $marker) {
    if (!str_contains($body, $marker)) {
        exit("Marker \"$marker\" fehlt – das sieht nicht nach dem Tracker aus.\n");
    }
}

// --- Vergleich mit der gepinnten Kopie ----------------------------
$old     = is_file($target) ? (string)file_get_contents($target) : '';
$newHash = hash('sha256', $body);
$oldHash = $old === '' ? '' : hash('sha256', $old);

echo "Quelle : $source\n";
echo "Groesse: $size Bytes\n";
echo "SHA-256: $newHash\n";

if ($old === '') {
    echo "Lokal  : noch keine Kopie vorhanden\n";
} elseif ($oldHash === $newHash) {
    echo "Lokal  : identisch – nichts zu tun\n";
    exit(0);
} else {
    echo "Lokal  : ABWEICHUNG (" . strlen($old) . " Bytes, $oldHash)\n";
}

if (!$apply) {
    echo "\nTrockenlauf. Zum Uebernehmen erneut mit --apply aufrufen.\n";
    echo "WICHTIG: Danach ASSET_VERSION (assets.php) sowie ASSET_VERSION und\n";
    echo "CACHE_VERSION (sw.js) gemeinsam anheben, sonst bleibt die alte\n";
    echo "Datei im Browser- und Service-Worker-Cache.\n";
    exit(0);
}

// --- Uebernehmen ---------------------------------------------------
// Atomar ueber eine temporaere Datei im selben Verzeichnis: ein
// Abbruch mitten im Schreiben darf keine halbe Datei ausliefern.
$tmp = $target . '.tmp';
if (file_put_contents($tmp, $body) === false || !rename($tmp, $target)) {
    @unlink($tmp);
    exit("Schreiben nach $target fehlgeschlagen.\n");
}
@chmod($target, 0644);

echo "\nUebernommen nach public/js/umami.js\n";
echo "Jetzt ASSET_VERSION und CACHE_VERSION anheben und Realtime pruefen.\n";
