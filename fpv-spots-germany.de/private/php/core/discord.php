<?php
declare(strict_types=1);
// =============================================================
// Discord-Webhook – neue Spots im Community-Discord ankündigen
//
// Ablauf: Spot wird angelegt (spot_submit.php oder POST /api/spots.php)
// -> discord_notify_new_spot_deferred() -> nach dem Ende der Antwort
// Bundesland ermitteln und ein Embed an den Webhook schicken.
//
// "Best effort" wie push.php: fehlt die Konfiguration oder ist
// Discord nicht erreichbar, wird nur geloggt – die Spot-Anlage
// selbst schlägt dadurch nie fehl.
//
// Konfiguration (.env):
//   DISCORD_WEBHOOK_URL  Webhook-URL aus den Discord-Kanaleinstellungen
//   APP_BASE_URL         eigene Origin für Links und Bild-URLs
// =============================================================

require_once __DIR__ . '/bundesland.php';

// -------------------------------------------------------------
// Darstellung
// -------------------------------------------------------------
const DISCORD_BOT_NAME       = 'FPV Spots Germany';
const DISCORD_EMBED_COLOR    = 0xCA710F;   // Akzentfarbe der Seite
const DISCORD_LOGO_PATH      = '/public/imgs/logo.png';
const DISCORD_UPLOAD_PATH    = '/public/uploads/imgs/';
const DISCORD_DESC_MAX       = 4000;       // Discord-Limit 4096
const DISCORD_FIELD_MAX      = 1024;       // Discord-Limit je Feldwert
const DISCORD_TITLE_MAX      = 256;        // Discord-Limit Titel

// Unsichtbarer Wert für Platzhalterfelder (Zero Width Space)
const DISCORD_SPACER = "\u{200B}";


// =============================================================
// Konfiguration
// =============================================================

/**
 * Webhook-URL aus der .env. Nur echte Discord-Webhook-URLs werden
 * akzeptiert – eine fehlerhafte Konfiguration darf keine Daten an
 * beliebige Hosts schicken. Leer = Webhook ist abgeschaltet.
 */
function discord_webhook_url(): string
{
    $url = trim((string)($_ENV['DISCORD_WEBHOOK_URL'] ?? ''));
    if ($url === '') {
        return '';
    }
    if (!preg_match('#^https://(discord\.com|discordapp\.com)/api/webhooks/\d{1,25}/[A-Za-z0-9_-]{1,100}$#', $url)) {
        error_log('discord.php: ungueltige DISCORD_WEBHOOK_URL');
        return '';
    }
    return $url;
}

/**
 * Eigene Origin für Links und Bild-URLs im Embed.
 * Bewusst aus der .env und nicht aus HTTP_HOST (Nutzereingabe).
 */
function discord_base_url(): string
{
    $url = rtrim(trim((string)($_ENV['APP_BASE_URL'] ?? '')), '/');
    if (!preg_match('#^https://[a-z0-9.-]{1,253}(:\d{1,5})?$#i', $url)) {
        if ($url !== '') {
            error_log('discord.php: ungueltige APP_BASE_URL');
        }
        return '';
    }
    return $url;
}

/** Kürzt auf $max Zeichen (multibyte-sicher) mit Auslassungszeichen. */
function discord_cut(string $text, int $max): string
{
    $text = trim($text);
    if (mb_strlen($text, 'UTF-8') <= $max) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')) . '…';
}


// =============================================================
// Embed bauen
// =============================================================

/**
 * Baut den Webhook-Payload für einen neuen Spot.
 * $spot: name, description, spot_type, difficulty, bundesland, username
 * $imageFile: Dateiname des Titelbilds oder null
 */
function discord_build_spot_payload(int $spotId, array $spot, ?string $imageFile, string $baseUrl): array
{
    $field = static fn(string $name, string $value): array => [
        'name'   => $name,
        'value'  => $value !== '' ? discord_cut($value, DISCORD_FIELD_MAX) : '–',
        'inline' => true,
    ];
    $spacer = ['name' => DISCORD_SPACER, 'value' => DISCORD_SPACER, 'inline' => true];

    $region = trim((string)($spot['bundesland'] ?? ''));

    $embed = [
        'title'       => discord_cut('📍 Neuer Spot angelegt: ' . (string)$spot['name'], DISCORD_TITLE_MAX),
        'description' => discord_cut((string)$spot['description'], DISCORD_DESC_MAX),
        'color'       => DISCORD_EMBED_COLOR,
        // Drei inline-Felder je Zeile, das dritte leer -> zwei Spalten wie gewünscht
        'fields'      => [
            $field('🏷️ Kategorie', (string)$spot['spot_type']),
            $field('⚡ Schwierigkeit', (string)$spot['difficulty']),
            $spacer,
            $field('📍 Region', $region !== '' ? $region : 'Unbekannt'),
            $field('👤 Ersteller', (string)$spot['username']),
            $spacer,
        ],
    ];

    $payload = [
        'username'         => DISCORD_BOT_NAME,
        // Kein @everyone/@here/Rollen-Ping über Nutzertexte
        'allowed_mentions' => ['parse' => []],
        'embeds'           => [],
    ];

    if ($baseUrl !== '') {
        $embed['url']          = $baseUrl . '/spot_detail.php?id=' . $spotId;
        $embed['thumbnail']    = ['url' => $baseUrl . DISCORD_LOGO_PATH];
        $payload['avatar_url'] = $baseUrl . DISCORD_LOGO_PATH;

        if ($imageFile !== null && $imageFile !== '') {
            $embed['image'] = ['url' => $baseUrl . DISCORD_UPLOAD_PATH . rawurlencode(basename($imageFile))];
        }
    }

    $payload['embeds'][] = $embed;
    return $payload;
}


// =============================================================
// Versand
// =============================================================

/** Schickt den Payload per POST an den Webhook. true bei HTTP 2xx. */
function discord_send(string $webhookUrl, array $payload): bool
{
    try {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        error_log('discord.php: JSON-Fehler: ' . $e->getMessage());
        return false;
    }

    $ch = curl_init($webhookUrl);
    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    $body   = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        // Webhook-URL enthält das Token -> nie mitloggen
        $snippet = is_string($body) ? mb_substr($body, 0, 200, 'UTF-8') : '';
        error_log('discord.php: Versand fehlgeschlagen (HTTP ' . $status
            . ($err !== '' ? ', ' . $err : '') . ') ' . $snippet);
        return false;
    }
    return true;
}

/**
 * Kündigt einen neuen Spot im Discord an. Wirft nie.
 * Ermittelt vorher das Bundesland (schreibt es auch in spots.bundesland).
 */
function discord_notify_new_spot(PDO $pdo, int $spotId): void
{
    $webhookUrl = discord_webhook_url();
    if ($webhookUrl === '' || $spotId <= 0) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT s.name, s.description, s.latitude, s.longitude, s.spot_type,
                    s.difficulty, s.bundesland, u.username
               FROM spots s
               JOIN users u ON u.id = s.user_id
              WHERE s.id = ?'
        );
        $stmt->execute([$spotId]);
        $spot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$spot) {
            return;
        }

        // Region direkt beim Anlegen ermitteln (sonst erst per Cron)
        if (empty($spot['bundesland'])) {
            $lat = (float)$spot['latitude'];
            $lng = (float)$spot['longitude'];
            if (refresh_spot_bundesland($pdo, $spotId, $lat, $lng)) {
                $re = $pdo->prepare('SELECT bundesland FROM spots WHERE id = ?');
                $re->execute([$spotId]);
                $spot['bundesland'] = (string)($re->fetchColumn() ?: '');
            }
        }

        // Titelbild: Highlight Platz 1, sonst das neueste Foto
        $img = $pdo->prepare(
            'SELECT filename FROM spot_images
              WHERE spot_id = ?
              ORDER BY highlight_pos IS NULL, highlight_pos, created_at DESC, id DESC
              LIMIT 1'
        );
        $img->execute([$spotId]);
        $imageFile = $img->fetchColumn();
        $imageFile = is_string($imageFile) ? $imageFile : null;

        $payload = discord_build_spot_payload($spotId, $spot, $imageFile, discord_base_url());
        discord_send($webhookUrl, $payload);
    } catch (Throwable $e) {
        error_log('discord.php: ' . $e->getMessage());
    }
}

/**
 * Wie discord_notify_new_spot(), aber erst nach dem Ende des Requests.
 * Unter PHP-FPM ist die Antwort (Redirect/JSON) dann schon beim Nutzer,
 * bevor Nominatim und Discord angefragt werden. Unter mod_php (lokaler
 * Docker-Container) gibt es fastcgi_finish_request() nicht – dort läuft
 * der Versand am Request-Ende mit (max. ~13 s bei Totalausfall).
 */
function discord_notify_new_spot_deferred(PDO $pdo, int $spotId): void
{
    if (discord_webhook_url() === '' || $spotId <= 0) {
        return;
    }

    register_shutdown_function(static function () use ($pdo, $spotId): void {
        ignore_user_abort(true);
        // Session-Sperre lösen: sonst wartet die Folgeseite (Redirect-Ziel)
        // auf den Versand, weil sie dieselbe Session öffnet.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        discord_notify_new_spot($pdo, $spotId);
    });
}
