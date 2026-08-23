<?php
declare(strict_types=1);
// =============================================================
// Wartung: DIPUL-Zonencheck fuer Spots nachholen bzw. auffrischen
//
// Behandelt zwei Faelle:
//   1. zones_checked_at IS NULL – beim Anlegen war die DFS nicht
//      erreichbar, oder der Spot stammt aus der Zeit vor dem Feature
//   2. zones_checked_at aelter als MAX_AGE_DAYS – Zonen aendern sich
//      (neue Naturschutzgebiete, geaenderte Kontrollzonen)
//
// Bewusst gedrosselt: pro Lauf werden hoechstens BATCH_SIZE Spots
// geprueft, jeder mit rund 30 parallelen WFS-Requests. Ein taeglicher
// Cron arbeitet den Bestand so ueber wenige Tage ab, ohne die
// Behoerdenschnittstelle zu belasten.
//
// Aufruf:
//   php private/php/maintenance/refresh_spot_zones.php
//   php private/php/maintenance/refresh_spot_zones.php --limit=50
//
// Cron (taeglich 04:20):
//   20 4 * * * php /pfad/zu/private/php/maintenance/refresh_spot_zones.php
// =============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Nur per CLI ausfuehrbar.');
}

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/geozones.php';

const BATCH_SIZE   = 20;
const MAX_AGE_DAYS = 30;

// Optionales --limit=N (nur nach oben begrenzt, damit ein Tippfehler
// nicht versehentlich hunderte Behoerden-Requests ausloest)
$limit = BATCH_SIZE;
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, min(200, (int) $m[1]));
    }
}

// Nie geprueft zuerst, danach das aelteste Ergebnis
$stmt = $pdo->prepare(
    "SELECT id, latitude, longitude, name
       FROM spots
      WHERE zones_checked_at IS NULL
         OR zones_checked_at < DATE_SUB(NOW(), INTERVAL ? DAY)
      ORDER BY zones_checked_at IS NULL DESC, zones_checked_at ASC, id ASC
      LIMIT ?"
);
$stmt->bindValue(1, MAX_AGE_DAYS, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->execute();
$spots = $stmt->fetchAll();

if (!$spots) {
    echo "Nichts zu tun – alle Spots sind aktuell geprueft.\n";
    exit(0);
}

echo 'Zu pruefen: ' . count($spots) . " Spot(s)\n\n";

$ok = 0;
$failed = 0;

foreach ($spots as $spot) {
    $id = (int) $spot['id'];
    printf('  #%-5d %-40s ', $id, mb_substr((string) $spot['name'], 0, 40));

    $start = microtime(true);
    $done  = refresh_spot_zones($pdo, $id, (float) $spot['latitude'], (float) $spot['longitude']);
    $ms    = (int) round((microtime(true) - $start) * 1000);

    if ($done) {
        $ok++;
        $count = (int) $pdo->query('SELECT COUNT(*) FROM spot_zones WHERE spot_id = ' . $id)->fetchColumn();
        printf("OK  %d Zone(n), %d ms\n", $count, $ms);
    } else {
        $failed++;
        printf("FEHLER (%d ms) – bleibt ungeprueft\n", $ms);
    }

    // Kurze Pause, damit der Lauf die DFS nicht im Sekundentakt trifft
    usleep(500000);
}

echo "\nGeprueft: $ok   Fehlgeschlagen: $failed\n";
exit($failed > 0 ? 1 : 0);
