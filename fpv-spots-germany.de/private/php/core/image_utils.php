<?php
declare(strict_types=1);

// =============================================================
// EXIF-Ausrichtung
//
// Handykameras speichern die Pixel meist im Querformat und legen
// die tatsaechliche Ausrichtung nur im EXIF-Tag "Orientation" ab.
// Weil strip_exif_and_save() alle EXIF-Daten verwirft, muss die
// Drehung VORHER fest in die Pixel uebernommen werden – sonst
// erscheinen Hochkant-Fotos um 90 Grad gekippt.
// =============================================================

/**
 * Dreht/spiegelt ein GD-Bild gemaess EXIF-Orientation der Quelldatei.
 * Ohne exif-Extension oder ohne Tag wird das Bild unveraendert geliefert.
 *
 * @param \GdImage $img  aus $path geladenes Bild
 * @param string   $path Quelldatei mit EXIF-Daten
 * @return \GdImage      korrekt ausgerichtetes Bild (ggf. neue Instanz)
 */
function apply_exif_orientation(\GdImage $img, string $path): \GdImage
{
    if (!function_exists('exif_read_data')) {
        return $img;
    }
    $exif = @exif_read_data($path);
    $orientation = is_array($exif) ? (int)($exif['Orientation'] ?? 1) : 1;
    if ($orientation < 2 || $orientation > 8) {
        return $img;
    }

    // Spiegelung (Werte 2, 4, 5, 7)
    if (in_array($orientation, [2, 5, 7], true)) {
        imageflip($img, IMG_FLIP_HORIZONTAL);
    } elseif ($orientation === 4) {
        imageflip($img, IMG_FLIP_VERTICAL);
    }

    // Drehung – GD dreht positive Winkel gegen den Uhrzeigersinn
    $angle = match ($orientation) {
        3       => 180,
        5, 8    => 90,
        6, 7    => -90,
        default => 0,
    };
    if ($angle !== 0) {
        $rotated = imagerotate($img, $angle, 0);
        if ($rotated !== false) {
            imagedestroy($img);
            $img = $rotated;
        }
    }
    return $img;
}

/**
 * Dreht eine gespeicherte Bilddatei (JPEG/PNG) und schreibt sie nach $destPath.
 * Fuer bereits hochgeladene Bilder, deren Ausrichtung verloren ging.
 *
 * @param int $degrees 90 = im Uhrzeigersinn, -90 = gegen den Uhrzeigersinn, 180
 */
function rotate_image_file(string $srcPath, string $destPath, int $degrees): bool
{
    if (!in_array($degrees, [90, -90, 180], true)) {
        return false;
    }
    $info = @getimagesize($srcPath);
    if ($info === false) {
        return false;
    }
    $isPng = $info[2] === IMAGETYPE_PNG;
    if ($info[2] === IMAGETYPE_JPEG) {
        $img = @imagecreatefromjpeg($srcPath);
    } elseif ($isPng) {
        $img = @imagecreatefrompng($srcPath);
    } else {
        return false;
    }
    if ($img === false) {
        return false;
    }

    // GD-Winkel ist gegen den Uhrzeigersinn, daher Vorzeichen umdrehen
    $bg = $isPng ? imagecolorallocatealpha($img, 0, 0, 0, 127) : 0;
    $rotated = imagerotate($img, -$degrees, $bg);
    imagedestroy($img);
    if ($rotated === false) {
        return false;
    }

    if ($isPng) {
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        $ok = imagepng($rotated, $destPath, 6);
    } else {
        $ok = imagejpeg($rotated, $destPath, 90);
    }
    imagedestroy($rotated);
    if (!$ok) {
        @unlink($destPath);
    }
    return $ok;
}

/**
 * Speichert ein hochgeladenes Bild ohne EXIF-Metadaten (GPS, Gerätedaten, Timestamps).
 * GD liest die Pixel-Daten ein und schreibt eine saubere Datei – EXIF wird dabei verworfen.
 * Die EXIF-Ausrichtung wird vorher in die Pixel uebernommen (apply_exif_orientation).
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
        $img = apply_exif_orientation($img, $tmpPath);
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
