<?php
declare(strict_types=1);
// =============================================================
// Wartung: Bundesland fuer Spots nachtragen
//
// Holt alle Spots, bei denen bundesland_checked_at NULL ist:
//   1. Spots aus der Zeit vor diesem Feature
//   2. Spots, bei denen Nominatim beim Anlegen nicht erreichbar war
//
// Bewusst KEIN Cron-Job – anders als bei den DIPUL-Zonen, die sich
// aendern koennen (neue Naturschutzgebiete, neue Kontrollzonen),
// wechselt ein Spot nie das Bundesland. Einmal ermittelt ist der
// Wert endgueltig; das Skript ist ein Nachtrag, keine Auffrischung.
//
// Gedrosselt auf eine Anfrage pro 1,1 s, weil Nominatims Nutzungs-
// richtlinie hoechstens 1 req/s erlaubt (NOMINATIM_MIN_INTERVAL_US
// in core/bundesland.php).
//
// Aufruf:
//   php fpv-spots-germany.de/private/php/maintenance/refresh_spot_bundesland.php
//   php fpv-spots-germany.de/private/php/maintenance/refresh_spot_bundesland.php --limit=5
// =============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Nur per CLI ausfuehrbar.');
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/bundesland.php';

// Hoeher als beim Zonencheck (dort 20): ein Spot kostet hier genau
// einen Request, der Bestand ist damit in einem Lauf durch.
const BATCH_SIZE = 100;

// Optionales --limit=N (nur nach oben begrenzt, damit ein Tippfehler
// nicht versehentlich hunderte Fremdanfragen ausloest)
$limit = BATCH_SIZE;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, min(200, (int) $m[1]));
    }
}

$stmt = $pdo->prepare(
    "SELECT id, latitude, longitude, name
       FROM spots
      WHERE bundesland_checked_at IS NULL
      ORDER BY id ASC
      LIMIT ?"
);
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();
$spots = $stmt->fetchAll();

if (!$spots) {
    echo "Nichts zu tun – bei allen Spots ist das Bundesland ermittelt.\n";
    exit(0);
}

echo 'Zu pruefen: ' . count($spots) . " Spot(s)\n\n";

$ok = 0;
$leer = 0;
$failed = 0;
$letzter = array_key_last($spots);

foreach ($spots as $i => $spot) {
    $id = (int) $spot['id'];
    printf('  #%-5d %-40s ', $id, mb_substr((string) $spot['name'], 0, 40));

    $start = microtime(true);
    $done  = refresh_spot_bundesland($pdo, $id, (float) $spot['latitude'], (float) $spot['longitude']);
    $ms    = (int) round((microtime(true) - $start) * 1000);

    if ($done) {
        // Zurueckgelesen statt aus der Funktion gereicht: so steht in
        // der Ausgabe wirklich das, was in der Datenbank gelandet ist.
        $wert = $pdo->prepare('SELECT bundesland FROM spots WHERE id = ?');
        $wert->execute([$id]);
        $name = $wert->fetchColumn();

        if (is_string($name) && $name !== '') {
            $ok++;
            printf("OK  %s (%d ms)\n", $name, $ms);
        } else {
            $leer++;
            printf("OK  ausserhalb Deutschlands (%d ms)\n", $ms);
        }
    } else {
        $failed++;
        printf("FEHLER (%d ms) – bleibt ungeprueft\n", $ms);
    }

    // Pause nur zwischen den Spots, nicht nach dem letzten
    if ($i !== $letzter) {
        usleep(NOMINATIM_MIN_INTERVAL_US);
    }
}

echo "\nZugeordnet: $ok   Ohne Bundesland: $leer   Fehlgeschlagen: $failed\n";
exit($failed > 0 ? 1 : 0);
