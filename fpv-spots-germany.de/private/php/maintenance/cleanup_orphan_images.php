<?php
// =============================================================
// Wartung: Verwaiste spot_images-Eintraege bereinigen
// Loescht DB-Zeilen, deren Datei nicht mehr unter
// public/uploads/imgs/ existiert.
//
// Aufruf:
//   php private/php/maintenance/cleanup_orphan_images.php           (Trockenlauf)
//   php private/php/maintenance/cleanup_orphan_images.php --apply   (echte Loeschung)
// =============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Nur per CLI ausfuehrbar.');
}

require_once __DIR__ . '/../core/db.php';

$apply = in_array('--apply', $argv, true);
$uploadDir = __DIR__ . '/../../../public/uploads/imgs/';

$stmt = $pdo->query("SELECT id, spot_id, user_id, filename FROM spot_images ORDER BY id");
$rows = $stmt->fetchAll();

$orphans = [];
foreach ($rows as $row) {
    if (!is_file($uploadDir . $row['filename'])) {
        $orphans[] = $row;
    }
}

echo "Geprueft: " . count($rows) . " Eintraege\n";
echo "Verwaist: " . count($orphans) . "\n\n";

foreach ($orphans as $o) {
    echo "  spot_image_id={$o['id']}  spot_id={$o['spot_id']}  user_id={$o['user_id']}  file={$o['filename']}\n";
}

if (!$orphans) {
    echo "\nKeine Verwaisten. Nichts zu tun.\n";
    exit(0);
}

if (!$apply) {
    echo "\nTrockenlauf. Mit --apply tatsaechlich loeschen.\n";
    exit(0);
}

$pdo->beginTransaction();
try {
    $del = $pdo->prepare("DELETE FROM spot_images WHERE id = ?");
    $log = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'IMAGE_ORPHAN_CLEANED', ?)");
    foreach ($orphans as $o) {
        $del->execute([$o['id']]);
        $log->execute([(int)$o['user_id'], 'cli']);
    }
    $pdo->commit();
    echo "\nGeloescht: " . count($orphans) . " Eintraege.\n";
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('cleanup_orphan_images.php error: ' . $e->getMessage());
    fwrite(STDERR, "Fehler: Transaktion zurueckgerollt.\n");
    exit(1);
}
