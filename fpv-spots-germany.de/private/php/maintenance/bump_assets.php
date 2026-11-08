<?php
declare(strict_types=1);
// =============================================================
// Wartung: Asset-Versionen gemeinsam anheben
//
// Hebt drei Werte, die zusammengehoeren, in einem Zug um 1 an:
//   - ASSET_VERSION in private/php/core/assets.php  (Cache-Busting ?v=)
//   - ASSET_VERSION in sw.js                        (Precache-Liste)
//   - CACHE_VERSION in sw.js                        (Cache-Namen, 'vN')
//
// Zusaetzlich Konsistenzpruefung: jedes lokale /public/*.js|css, das
// ueber asset('/...') eingebunden wird, muss in STATIC_ASSETS (sw.js)
// stehen. Fehlende Eintraege werden nur gemeldet (kein Fehler).
//
// Aufruf:
//   php private/php/maintenance/bump_assets.php           (Trockenlauf)
//   php private/php/maintenance/bump_assets.php --apply   (schreiben)
// =============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$apply = in_array('--apply', $argv, true);
$root  = realpath(__DIR__ . '/../../..');

$assetsFile = $root . '/private/php/core/assets.php';
$swFile     = $root . '/sw.js';

$assets = file_get_contents($assetsFile);
$sw     = file_get_contents($swFile);
if ($assets === false || $sw === false) {
    fwrite(STDERR, "Dateien nicht lesbar.\n");
    exit(1);
}

// ── Aktuelle Werte lesen ────────────────────────────────────
$reAssetsPhp = "/(const ASSET_VERSION = ')(\\d+)(';)/";
$reAssetsJs  = "/(const ASSET_VERSION = ')(\\d+)(';)/";
$reCache     = "/(const CACHE_VERSION = 'v)(\\d+)(';)/";

if (!preg_match($reAssetsPhp, $assets, $a)
    || !preg_match($reAssetsJs, $sw, $b)
    || !preg_match($reCache, $sw, $c)) {
    fwrite(STDERR, "Versionskonstanten nicht gefunden.\n");
    exit(1);
}

$oldPhp = (int)$a[2];
$oldJs  = (int)$b[2];
$oldC   = (int)$c[2];

if ($oldPhp !== $oldJs) {
    fwrite(STDERR, "WARNUNG: ASSET_VERSION laeuft auseinander (PHP $oldPhp, sw.js $oldJs).\n");
}

// PHP und sw.js teilen sich EINEN Wert (der hoehere), damit sie danach sicher gleich sind
$newAsset = max($oldPhp, $oldJs) + 1;
$newCache = $oldC + 1;

echo "ASSET_VERSION: $oldPhp / $oldJs  ->  $newAsset\n";
echo "CACHE_VERSION: v$oldC  ->  v$newCache\n";

// ── Konsistenz: asset()-Pfade gegen STATIC_ASSETS ───────────
$missing = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
    $root . '/public', FilesystemIterator::SKIP_DOTS
));
foreach ($iter as $f) {
    if ($f->getExtension() !== 'php') {
        continue;
    }
    $src = file_get_contents($f->getPathname());
    if ($src !== false && preg_match_all("/asset\\('(\\/public\\/[^']+\\.(?:js|css))'\\)/", $src, $m)) {
        foreach ($m[1] as $p) {
            if (!str_contains($sw, "'" . $p . "'")) {
                $missing[$p] = true;
            }
        }
    }
}
if ($missing) {
    echo "\nNicht in STATIC_ASSETS (sw.js), nur Hinweis:\n";
    foreach (array_keys($missing) as $p) {
        echo "  $p\n";
    }
}

// ── Schreiben ───────────────────────────────────────────────
if (!$apply) {
    echo "\nTrockenlauf. Mit --apply schreiben.\n";
    exit(0);
}

$assets = preg_replace($reAssetsPhp, '${1}' . $newAsset . '${3}', $assets, 1);
$sw     = preg_replace($reAssetsJs, '${1}' . $newAsset . '${3}', $sw, 1);
$sw     = preg_replace($reCache, '${1}' . $newCache . '${3}', $sw, 1);

file_put_contents($assetsFile, $assets);
file_put_contents($swFile, $sw);
echo "\nGeschrieben.\n";
