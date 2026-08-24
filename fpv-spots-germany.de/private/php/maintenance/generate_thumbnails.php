<?php
declare(strict_types=1);
// =============================================================
// Wartung: Thumbnails fuer Bestandsbilder nacherzeugen
//
// Legt fuer jedes Bild unter public/uploads/imgs/ und
// public/uploads/forum/ ein Thumbnail im jeweiligen Unterordner
// thumbs/ an. Die Originale werden NICHT veraendert.
//
// Idempotent: bereits vorhandene Thumbnails werden uebersprungen,
// das Skript kann also gefahrlos mehrfach laufen.
//
// Aufruf:
//   php private/php/maintenance/generate_thumbnails.php           (Trockenlauf)
//   php private/php/maintenance/generate_thumbnails.php --apply   (erzeugen)
//   ... --force   zusaetzlich: vorhandene Thumbnails neu erzeugen
// =============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Nur per CLI ausfuehrbar.');
}

require_once __DIR__ . '/../core/image_utils.php';

$apply = in_array('--apply', $argv, true);
$force = in_array('--force', $argv, true);

$dirs = [
    __DIR__ . '/../../../public/uploads/imgs/',
    __DIR__ . '/../../../public/uploads/forum/',
];

$total = 0; $skipped = 0; $created = 0; $failed = 0; $bytesBefore = 0; $bytesAfter = 0;

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        echo "Uebersprungen (fehlt): $dir\n";
        continue;
    }
    echo "\n== $dir\n";

    foreach (scandir($dir) ?: [] as $entry) {
        $src = $dir . $entry;
        if (!is_file($src)) {
            continue;   // .htaccess, thumbs/, . und ..
        }
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            continue;
        }

        $total++;
        $dst = thumb_path($src);

        if (is_file($dst) && !$force) {
            $skipped++;
            continue;
        }

        if (!$apply) {
            echo "  wuerde erzeugen: $entry\n";
            continue;
        }

        if (create_thumbnail($src, $dst)) {
            $created++;
            $bytesBefore += (int)@filesize($src);
            $bytesAfter  += (int)@filesize($dst);
        } else {
            $failed++;
            fwrite(STDERR, "  FEHLER: $entry\n");
        }
    }
}

echo "\n--------------------------------------------\n";
echo "Bilder gesamt:   $total\n";
echo "Uebersprungen:   $skipped (Thumbnail existiert)\n";

if (!$apply) {
    echo "\nTrockenlauf. Mit --apply tatsaechlich erzeugen.\n";
    exit(0);
}

echo "Erzeugt:         $created\n";
echo "Fehlgeschlagen:  $failed\n";
if ($created > 0) {
    printf(
        "Groesse:         %.1f MB Original  ->  %.1f MB Thumbnails (%.0f %% weniger)\n",
        $bytesBefore / 1048576,
        $bytesAfter / 1048576,
        $bytesBefore > 0 ? (1 - $bytesAfter / $bytesBefore) * 100 : 0
    );
}
exit($failed > 0 ? 1 : 0);
