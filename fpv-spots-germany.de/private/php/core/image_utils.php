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

// =============================================================
// Thumbnails
//
// Die Galerien zeigen Bilder in Rahmen von hoechstens ~320 px
// Kantenlaenge, geladen wurde bisher aber immer das Original –
// eine Spot-Seite mit 16 Fotos uebertraegt so knapp 3 MB.
//
// Ein Thumbnail liegt im Unterordner thumbs/ NEBEN dem Original.
// Damit gilt die .htaccess des Upload-Ordners automatisch mit,
// und das Original bleibt unangetastet (Lightbox, Download).
//
// Thumbnails sind IMMER JPEG, auch wenn das Original ein PNG ist:
// die hochgeladenen PNGs sind fotografische Inhalte, als PNG waeren
// die Vorschauen im Schnitt siebenmal so gross wie als JPEG.
// Transparenz wird dabei auf Weiss gelegt – im 160-px-Raster faellt
// das nicht auf, und die Lightbox zeigt weiterhin das Original.
// =============================================================

const THUMB_DIR_NAME = 'thumbs';
const THUMB_MAX_EDGE = 600;   // laengste Kante in Pixeln
const THUMB_QUALITY  = 80;

/**
 * Leitet den Thumbnail-Pfad aus dem Original-Pfad ab: thumbs/<name>.jpg.
 * Funktioniert fuer Dateisystempfade wie fuer URLs, weil nur das
 * Verzeichnis-Segment ergaenzt und die Endung ersetzt wird.
 */
function thumb_path(string $originalPath): string
{
    $name = pathinfo($originalPath, PATHINFO_FILENAME);
    return dirname($originalPath) . '/' . THUMB_DIR_NAME . '/' . $name . '.jpg';
}

/**
 * Erzeugt ein laengsseitig begrenztes JPEG-Thumbnail.
 * Seitenverhaeltnis bleibt erhalten, es wird nie hochskaliert.
 *
 * @param string $srcPath  bereits gespeichertes Original (JPEG oder PNG)
 * @param string $destPath Zielpfad (Verzeichnis wird bei Bedarf angelegt)
 * @param int    $maxEdge  Obergrenze fuer die laengere Kante
 * @return bool            true bei Erfolg, false bei Fehler
 */
function create_thumbnail(string $srcPath, string $destPath, int $maxEdge = THUMB_MAX_EDGE): bool
{
    $info = @getimagesize($srcPath);
    if ($info === false) {
        return false;
    }
    [$srcW, $srcH] = $info;
    $type = $info[2];
    if ($srcW < 1 || $srcH < 1 || $maxEdge < 1) {
        return false;
    }

    $destDir = dirname($destPath);
    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return false;
    }

    if ($type === IMAGETYPE_JPEG) {
        $src = @imagecreatefromjpeg($srcPath);
    } elseif ($type === IMAGETYPE_PNG) {
        $src = @imagecreatefrompng($srcPath);
    } else {
        return false;
    }
    if ($src === false) {
        return false;
    }

    // Nie hochskalieren: Faktor ist hoechstens 1.0
    $scale = min(1.0, $maxEdge / max($srcW, $srcH));
    $dstW  = max(1, (int)round($srcW * $scale));
    $dstH  = max(1, (int)round($srcH * $scale));

    // JPEG kennt keine Transparenz – weisser Grund statt Schwarz
    $dst = imagecreatetruecolor($dstW, $dstH);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, imagecolorallocate($dst, 255, 255, 255));
    imagealphablending($dst, true);

    $ok = imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    if ($ok) {
        $ok = imagejpeg($dst, $destPath, THUMB_QUALITY);
    }

    imagedestroy($src);
    imagedestroy($dst);

    // Halb geschriebene Datei nicht liegen lassen
    if (!$ok) {
        @unlink($destPath);
    }
    return $ok;
}

/**
 * Liefert die anzuzeigende Bild-URL: das Thumbnail, falls vorhanden,
 * sonst das Original. Der Fallback greift fuer Bilder, deren Thumbnail
 * noch nicht erzeugt wurde oder bei denen die Erzeugung fehlschlug.
 *
 * @param string $imageUrl absolute Web-URL, z. B. /public/uploads/imgs/<datei>
 */
function thumb_url(string $imageUrl): string
{
    $thumbUrl = thumb_path($imageUrl);
    // Webroot liegt drei Ebenen ueber private/php/core/
    return is_file(__DIR__ . '/../../..' . $thumbUrl) ? $thumbUrl : $imageUrl;
}
