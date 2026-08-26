<?php
declare(strict_types=1);
// =============================================================
// API: Umami-Sammelendpunkt (/api/send)
//
// Nimmt die Messdaten des Trackers entgegen und reicht sie an
// Umami weiter. Wie dipul_wms.php bewusst ein Proxy und kein
// direkter Browser-Request:
//
//   1. Die CSP bleibt bei connect-src 'self' – kein neuer Host.
//   2. Die IP unserer Besucher erreicht Umami nie unverkuerzt;
//      die Kuerzung passiert hier, im eigenen Server.
//   3. Filterlisten kennen /api/send nicht – die Zahlen bleiben
//      vollstaendiger als bei einem Aufruf gegen cloud.umami.is.
//
// Der Endpunkt ist zwangslaeufig oeffentlich (er muss auch fuer
// ausgeloggte Besucher funktionieren) und damit ein lohnendes Ziel:
// wer ihn frei benutzen kann, kann unsere Statistik fluten. Die
// Eingangspruefung unten ist deshalb bewusst streng.
//
// Bei einer Stoerung wird IMMER still 204 geantwortet. Tracking
// darf niemals eine Seite beschaedigen oder Fehler in der Konsole
// des Besuchers erzeugen.
// =============================================================

require_once __DIR__ . '/../../../private/php/core/analytics.php';
require_once __DIR__ . '/../../../private/php/core/client_ip.php';

// -------------------------------------------------------------
// Antwortarten
//
// 204 = "angenommen, nichts zu sagen". Der Tracker wertet den
// Body nur aus, wenn einer kommt; ein leerer Body ist fuer ihn
// unauffaellig. Deshalb ist 204 die richtige Antwort auf jede
// Betriebsstoerung – und auch auf abgewiesene Anfragen, damit ein
// Angreifer nicht durchprobieren kann, welche Pruefung gegriffen
// hat.
// -------------------------------------------------------------
function umami_no_content(string $reason = ''): never
{
    if ($reason !== '') {
        error_log('umami_send: ' . $reason);
    }
    http_response_code(204);
    header('Cache-Control: no-store');
    exit;
}

// -------------------------------------------------------------
// 1. Methode
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Cache-Control: no-store');
    exit;
}

// -------------------------------------------------------------
// 2. Konfiguration
//
// Ist die Messung abgeschaltet, fehlerhaft konfiguriert oder hat
// der Besucher widersprochen, wurde gar kein Script ausgeliefert.
// Ein Request hierher ist dann entweder veraltet (Seite lag im
// Cache) oder von Hand gebaut – beides wird verworfen.
// -------------------------------------------------------------
$websiteId = umami_website_id();
$siteUrl   = umami_site_url();
if (!umami_enabled() || $websiteId === '' || $siteUrl === '') {
    umami_no_content();
}
$siteHost = (string) parse_url($siteUrl, PHP_URL_HOST);

// -------------------------------------------------------------
// 3. Herkunft
//
// Der Tracker sendet per fetch() mit Content-Type application/json;
// dadurch erzwingt der Browser einen Origin-Header. Fehlt er oder
// zeigt er woanders hin, stammt der Request nicht von unserer Seite.
// Referer nur als Rueckfallebene, falls Origin einmal fehlt.
// -------------------------------------------------------------
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin === '') {
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $origin  = $referer === '' ? '' : (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $referer);
}
if (!hash_equals($siteUrl, $origin)) {
    umami_no_content();
}

// -------------------------------------------------------------
// 4. Groesse und Struktur
//
// 8 KB reichen fuer jedes legitime Ereignis um ein Vielfaches.
// Die Obergrenze verhindert, dass jemand den Endpunkt als
// Ablage oder zum Blockieren von PHP-Prozessen missbraucht.
// -------------------------------------------------------------
const UMAMI_MAX_BODY = 8192;

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > UMAMI_MAX_BODY) {
    umami_no_content();
}
$raw = (string) file_get_contents('php://input', false, null, 0, UMAMI_MAX_BODY + 1);
if ($raw === '' || strlen($raw) > UMAMI_MAX_BODY) {
    umami_no_content();
}

$data = json_decode($raw, true, 8);
if (!is_array($data) || !isset($data['payload']) || !is_array($data['payload'])) {
    umami_no_content();
}

// Nur Seitenaufrufe und Ereignisse. "identify" wuerde einen
// Besucher dauerhaft wiedererkennbar machen – genau das soll die
// Messung nicht koennen, und die Datenschutzerklaerung sagt es zu.
$type = (string) ($data['type'] ?? 'event');
if ($type !== 'event') {
    umami_no_content();
}

// -------------------------------------------------------------
// 5. Identitaet erzwingen
//
// Der Kern des Missbrauchsschutzes: Website-ID und Hostname kommen
// NICHT aus dem Request, sondern aus der eigenen Konfiguration.
// Damit kann niemand fremde Websites bespielen oder erfundene
// Hostnamen in unsere Statistik schreiben – selbst wenn er den
// Origin-Header faelschen koennte.
// -------------------------------------------------------------
$payloadHost = (string) ($data['payload']['hostname'] ?? '');
if ($payloadHost !== '' && !hash_equals($siteHost, $payloadHost)) {
    umami_no_content();
}
$data['payload']['website']  = $websiteId;
$data['payload']['hostname'] = $siteHost;

// -------------------------------------------------------------
// 5b. Felder normalisieren
//
// Alles, was Umami spaeter als Text im Bericht anzeigt, wird hier
// auf Form und Laenge geprueft. Ohne das koennte jemand mit
// gefaelschtem Origin-Header beliebige Zeichenketten als "Seite"
// oder "Verweisquelle" in die Auswertung schreiben und sie
// unlesbar machen. Unbrauchbare Werte werden entfernt, nicht
// abgelehnt – ein einzelnes krummes Feld soll den Seitenaufruf
// nicht verschlucken.
// -------------------------------------------------------------
$p = $data['payload'];

// url: der Tracker sendet eine ABSOLUTE URL – im Script steht
// new URL(pfad, location.href).toString(). Die eigene Origin wird
// deshalb abgeschnitten, damit im Bericht dieselben Pfade stehen wie
// in der Seitenstruktur. Bleibt danach kein seiteninterner Pfad
// uebrig, stammt der Wert nicht von unserer Seite und fliegt raus.
$url = is_string($p['url'] ?? null) ? $p['url'] : '';
if ($url === $siteUrl) {
    $url = '/';
} elseif (str_starts_with($url, $siteUrl . '/')) {
    $url = substr($url, strlen($siteUrl));
}
if ($url === '' || $url[0] !== '/' || strlen($url) > 500 || preg_match('/[\x00-\x1F]/', $url)) {
    unset($p['url']);
} else {
    $p['url'] = $url;
}

// referrer: interner Pfad oder vollstaendige http(s)-URL
$ref = is_string($p['referrer'] ?? null) ? $p['referrer'] : '';
if ($ref !== '' && (strlen($ref) > 500
    || preg_match('/[\x00-\x1F]/', $ref)
    || ($ref[0] !== '/' && !preg_match('#^https?://#i', $ref)))) {
    unset($p['referrer']);
}

// screen: exakt BreitexHoehe
if (!is_string($p['screen'] ?? null) || !preg_match('/^\d{1,5}x\d{1,5}$/', $p['screen'])) {
    unset($p['screen']);
}

// language: BCP-47-artig, kurz
if (!is_string($p['language'] ?? null) || !preg_match('/^[A-Za-z]{1,8}(-[A-Za-z0-9]{1,8})*$/', $p['language'])) {
    unset($p['language']);
}

// title und name: Steuerzeichen raus, harte Laengengrenze
foreach (['title' => 200, 'name' => 100, 'tag' => 50] as $feld => $max) {
    if (!isset($p[$feld])) {
        continue;
    }
    if (!is_string($p[$feld])) {
        unset($p[$feld]);
        continue;
    }
    $clean = trim((string) preg_replace('/[^\P{C}\t]+/u', ' ', $p[$feld]));
    if ($clean === '') {
        unset($p[$feld]);
    } else {
        $p[$feld] = mb_substr($clean, 0, $max);
    }
}

// data: freie Ereignisdaten – flach halten und begrenzen
if (isset($p['data'])) {
    if (!is_array($p['data']) || count($p['data']) > 20) {
        unset($p['data']);
    } else {
        foreach ($p['data'] as $k => $val) {
            if (!is_string($k) || strlen($k) > 50
                || !(is_string($val) || is_int($val) || is_float($val) || is_bool($val))
                || (is_string($val) && strlen($val) > 200)) {
                unset($p['data'][$k]);
            }
        }
    }
}

// id: der Tracker sendet hier nur einen eigenen Zaehler
if (isset($p['id']) && (!is_string($p['id']) || strlen($p['id']) > 64)) {
    unset($p['id']);
}

$data['payload'] = $p;

$body = json_encode(['type' => $type, 'payload' => $data['payload']], JSON_UNESCAPED_SLASHES);
if ($body === false || strlen($body) > UMAMI_MAX_BODY) {
    umami_no_content('Payload nach Normalisierung unbrauchbar');
}

// -------------------------------------------------------------
// 6. Rate-Limit pro IP
//
// Bewusst dateibasiert und nicht ueber audit_logs – wie beim
// Kachel-Proxy: Jeder Seitenaufruf erzeugt hier mindestens einen
// Request. In der Audit-Tabelle wuerde das die forensisch lesbare
// Spur sicherheitsrelevanter Aktionen zumuellen. Gespeichert wird
// nur ein Hash aus IP und Stundenbucket.
//
// 600/Stunde ist fuer echte Nutzung unerreichbar (das waeren zehn
// Seitenaufrufe pro Minute, eine Stunde lang), bremst aber ein
// Skript sofort aus.
// -------------------------------------------------------------
const UMAMI_RATE_LIMIT = 600;
const UMAMI_RATE_DIR   = __DIR__ . '/../../../private/data/umami_rate';

$clientIp = client_ip();
$rateFile = UMAMI_RATE_DIR . '/' . sha1($clientIp . '|' . date('YmdH')) . '.cnt';

if (!is_dir(UMAMI_RATE_DIR)) {
    @mkdir(UMAMI_RATE_DIR, 0775, true);
}
$hits = is_file($rateFile) ? (int) file_get_contents($rateFile) : 0;
if ($hits >= UMAMI_RATE_LIMIT) {
    umami_no_content();
}
@file_put_contents($rateFile, (string) ($hits + 1), LOCK_EX);

// Aufraeumen: Zaehler aelter als zwei Stunden sind wertlos.
// Probabilistisch, gleiches Muster wie die Loeschung in db.php.
if (random_int(1, 500) === 1) {
    foreach (glob(UMAMI_RATE_DIR . '/*.cnt') ?: [] as $old) {
        if (time() - (int) @filemtime($old) > 7200) {
            @unlink($old);
        }
    }
}

// -------------------------------------------------------------
// 7. IP-Behandlung
//
// Umami bildet die Sitzungs-ID aus einem Hash ueber Website,
// Hostname, IP und User-Agent. Die IP bestimmt ausserdem die
// Laenderzuordnung.
//   truncate – letztes Oktett auf 0 bzw. /48 bei IPv6 (Vorgabe):
//              Land bleibt korrekt, der Personenbezug sinkt.
//   full     – genauere Sitzungstrennung, mehr Personenbezug.
//   none     – kein X-Forwarded-For; kostet die Laenderauswertung.
// -------------------------------------------------------------
function umami_truncate_ip(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $parts[3] = '0';
        return implode('.', $parts);
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = inet_pton($ip);
        if ($packed !== false) {
            // /48 behalten, den Rest nullen
            $masked = substr($packed, 0, 6) . str_repeat("\0", 10);
            $text   = inet_ntop($masked);
            if ($text !== false) {
                return $text;
            }
        }
    }
    return '';
}

$mode      = umami_ip_mode();
$forwarded = match ($mode) {
    'full'  => $clientIp,
    'none'  => '',
    default => umami_truncate_ip($clientIp),
};

// -------------------------------------------------------------
// 8. Weiterleiten
//
// Zielhost ist gateway.umami.is, NICHT cloud.umami.is – von dort
// kommt nur die Script-Datei.
//
// Der Original-User-Agent muss mit, sonst erkennt Umami weder
// Browser noch Betriebssystem und ordnet alles einem einzigen
// Geraet zu. Der x-umami-cache-Header enthaelt das Token der
// vorherigen Antwort und haelt die Sitzung zusammen.
// -------------------------------------------------------------
$headers = [
    'Content-Type: application/json',
    'x-umami-website-id: ' . $websiteId,
    'x-umami-hostname: ' . $siteHost,
];
if ($forwarded !== '') {
    $headers[] = 'X-Forwarded-For: ' . $forwarded;
}
$cacheToken = (string) ($_SERVER['HTTP_X_UMAMI_CACHE'] ?? '');
if ($cacheToken !== '' && strlen($cacheToken) <= 512 && preg_match('/^[A-Za-z0-9+\/=_.-]+$/', $cacheToken)) {
    $headers[] = 'x-umami-cache: ' . $cacheToken;
}

$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$userAgent = trim(preg_replace('/[^\P{C}]+/u', '', $userAgent) ?? '');
if ($userAgent === '' || strlen($userAgent) > 512) {
    $userAgent = 'fpv-spots-germany.de';
}

$ch = curl_init(umami_collect_host() . '/api/send');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_USERAGENT      => $userAgent,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_TIMEOUT        => 3,
    CURLOPT_CONNECTTIMEOUT => 2,
]);
$response = curl_exec($ch);
$status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false || $status < 200 || $status >= 300) {
    umami_no_content('Upstream HTTP ' . $status . ' ' . $curlErr);
}

// -------------------------------------------------------------
// 9. Antwort 1:1 zurueckgeben
//
// Der Body enthaelt das Cache-Token, das der Tracker beim naechsten
// Aufruf als x-umami-cache mitschickt. Wird es verschluckt, zaehlt
// jeder Seitenaufruf als neue Sitzung – die Statistik waere
// unbrauchbar.
// -------------------------------------------------------------
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $response;
