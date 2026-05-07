<?php
declare(strict_types=1);

/**
 * Speichert ein hochgeladenes Bild ohne EXIF-Metadaten (GPS, Gerätedaten, Timestamps).
 * GD liest die Pixel-Daten ein und schreibt eine saubere Datei – EXIF wird dabei verworfen.
 *
 * @param string $tmpPath  Pfad zur temporären Upload-Datei ($_FILES[...]['tmp_name'])
 * @param string $destPath Zielpfad inkl. Dateiname
 * @param string $mimeType 'image/jpeg' oder 'image/png'
 * @return bool            true bei Erfolg, false bei Fehler
 */
function strip_exif_and_save(string $tmpPath, string $destPath, string $mimeType): bool
{
    if ($mimeType === 'image/jpeg') {
        $img = @imagecreatefromjpeg($tmpPath);
        if ($img === false) {
            return false;
        }
        $ok = imagejpeg($img, $destPath, 85);
        imagedestroy($img);
        return $ok;
    }

    if ($mimeType === 'image/png') {
        $img = @imagecreatefrompng($tmpPath);
        if ($img === false) {
            return false;
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $ok = imagepng($img, $destPath, 6);
        imagedestroy($img);
        return $ok;
    }

    return false;
}
