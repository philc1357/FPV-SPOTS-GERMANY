<?php
declare(strict_types=1);

/* ============================================================
   Partnerlinks (BetaFPV) – Hilfsfunktionen

   Die Produktliste kommt aus affiliate_products.php. Dieses
   Modul validiert sie einmal pro Request und stellt sie nach
   Coptergroesse gefiltert bereit.

   Fail-closed: Ein Eintrag mit ungueltiger URL, unbekannter
   Groesse oder fehlendem Namen wird verworfen, nicht angezeigt.
   Ist danach nichts uebrig, ist das Feature aus.
   ============================================================ */

/** Coptergroessen exakt wie in spots.copter_size (SET) */
const AFFILIATE_SIZES = ['Tinywhoop', '2-3 Zoll', '3-5 Zoll', '5+ Zoll'];

/** Erlaubter Partner-Host (inkl. Subdomains wie www.) */
const AFFILIATE_HOST = 'betafpv.com';

/** Prueft: https-URL auf betafpv.com, keine Zugangsdaten in der URL */
function affiliate_valid_url(string $url): bool
{
    $p = parse_url($url);
    if ($p === false || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass'])) {
        return false;
    }
    $host = strtolower((string)($p['host'] ?? ''));
    return $host === AFFILIATE_HOST || str_ends_with($host, '.' . AFFILIATE_HOST);
}

/**
 * Validierte Produktliste.
 *
 * @return list<array{slug:string,name:string,sizes:list<string>,text:string,url:string}>
 */
function affiliate_products(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $raw = require __DIR__ . '/affiliate_products.php';
    if (!is_array($raw)) {
        return $cache;
    }

    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $slug = (string)($item['slug'] ?? '');
        $name = trim((string)($item['name'] ?? ''));
        $url  = (string)($item['url'] ?? '');
        $sizes = $item['sizes'] ?? [];

        if (!preg_match('/^[a-z0-9-]{1,40}$/', $slug) || $name === '' || !affiliate_valid_url($url)) {
            error_log('affiliate.php: Eintrag verworfen (slug/name/url ungueltig): ' . $slug);
            continue;
        }
        if (!is_array($sizes) || array_diff($sizes, AFFILIATE_SIZES) !== []) {
            error_log('affiliate.php: Eintrag verworfen (unbekannte Groesse): ' . $slug);
            continue;
        }

        $cache[] = [
            'slug'  => $slug,
            'name'  => $name,
            'sizes' => array_values($sizes),
            'text'  => trim((string)($item['text'] ?? '')),
            'url'   => $url,
        ];
    }
    return $cache;
}

/** Feature aktiv, sobald mindestens ein gueltiger Eintrag existiert */
function affiliate_enabled(): bool
{
    return affiliate_products() !== [];
}

/**
 * Produkte, die zu mindestens einer der Coptergroessen passen.
 * Produkte ohne Groessenangabe passen immer.
 *
 * @param list<string> $sizes Groessen des Spots (aus dem SET-Feld)
 * @return list<array{slug:string,name:string,sizes:list<string>,text:string,url:string}>
 */
function affiliate_for_sizes(array $sizes, int $limit = 3): array
{
    $sizes = array_values(array_filter(array_map('trim', $sizes), 'strlen'));
    $out = [];
    foreach (affiliate_products() as $p) {
        if ($p['sizes'] === [] || array_intersect($p['sizes'], $sizes) !== []) {
            $out[] = $p;
        }
    }
    return array_slice($out, 0, max(1, $limit));
}
