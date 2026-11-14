<?php
declare(strict_types=1);
// =============================================================
// Profilbilder (Avatare)
//
// Ein Avatar ist immer ein quadratisches JPEG (AVATAR_SIZE px),
// das serverseitig aus dem Upload zugeschnitten und neu kodiert
// wird. Das Original wird verworfen – damit sind EXIF-Daten und
// etwaige eingebettete Payloads garantiert weg.
//
// In users.avatar steht nur der Dateiname (<32 hex>.jpg). Er wird
// bei JEDER Verwendung gegen eine Whitelist-Regex geprüft, damit
// selbst ein manipulierter DB-Wert keinen Pfad ausserhalb des
// Avatar-Ordners erreichen kann.
// =============================================================
require_once __DIR__ . '/image_utils.php';

const AVATAR_DIR      = __DIR__ . '/../../../public/uploads/avatars/';
const AVATAR_URL_BASE = '/public/uploads/avatars/';
const AVATAR_SIZE     = 256;   // Kantenlänge der gespeicherten Datei in px
const AVATAR_MIN_CROP = 32;    // kleinster erlaubter Ausschnitt im Original
const AVATAR_QUALITY  = 85;

// =============================================================
// Dateinamen & URLs
// =============================================================

/** Erlaubt ausschliesslich selbst erzeugte Namen: 32 Hex-Zeichen + .jpg */
function avatar_filename_valid(?string $filename): bool
{
    return $filename !== null && preg_match('/^[a-f0-9]{32}\.jpg$/D', $filename) === 1;
}

/** Erzeugt einen neuen, nicht erratbaren Dateinamen. */
function avatar_new_filename(): string
{
    return bin2hex(random_bytes(16)) . '.jpg';
}

/** Dateisystempfad zu einem (gültigen) Avatar-Dateinamen. */
function avatar_path(string $filename): string
{
    return AVATAR_DIR . $filename;
}

/**
 * Web-URL des Avatars – nur wenn der Name gültig ist UND die Datei existiert.
 * Sonst null, die Anzeige fällt dann auf die Initialen zurück.
 */
function avatar_url(?string $filename): ?string
{
    if (!avatar_filename_valid($filename) || !is_file(avatar_path((string)$filename))) {
        return null;
    }
    return AVATAR_URL_BASE . $filename;
}

/** Löscht eine Avatar-Datei, sofern der Name gültig ist. */
function avatar_delete_file(?string $filename): void
{
    if (avatar_filename_valid($filename)) {
        @unlink(avatar_path((string)$filename));
    }
}

// =============================================================
// Anzeige: Initialen-Fallback & Avatar-Markup
// =============================================================

/** Bis zu zwei Initialen aus dem Benutzernamen, sonst „?“. */
function user_initials(string $name): string
{
    $clean = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? '';
    $parts = preg_split('/\s+/', trim($clean)) ?: [];
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    if (mb_strlen($ini) < 2 && isset($parts[0])) {
        $ini = mb_strtoupper(mb_substr($parts[0], 0, 2));
    }
    return $ini !== '' ? $ini : '?';
}

/**
 * Inhalt eines Avatar-Kreises: <img> bei vorhandenem Bild, sonst Initialen.
 * Bewusst ohne Link/Lightbox – das Bild ist nicht vergrößerbar.
 */
function avatar_inner_html(string $username, ?string $avatar, int $displaySize): string
{
    $url = avatar_url($avatar);
    if ($url === null) {
        return htmlspecialchars(user_initials($username), ENT_QUOTES, 'UTF-8');
    }
    return '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt=""'
        . ' width="' . $displaySize . '" height="' . $displaySize . '"'
        . ' loading="lazy" decoding="async" draggable="false">';
}

/**
 * Kompletter Avatar-Kreis als <span>. $class steuert Größe/Optik (CSS).
 * Dekorativ (aria-hidden): der Benutzername steht immer daneben im Text.
 */
function render_avatar(string $username, ?string $avatar, string $class, int $displaySize = 40): string
{
    $hasImg = avatar_url($avatar) !== null;
    return '<span class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . ($hasImg ? ' has-img' : '') . '" aria-hidden="true">'
        . avatar_inner_html($username, $avatar, $displaySize)
        . '</span>';
}

// =============================================================
// Erzeugung: Zuschnitt + Neukodierung
// =============================================================

/**
 * Schneidet aus dem Upload einen quadratischen Ausschnitt und speichert
 * ihn als AVATAR_SIZE×AVATAR_SIZE-JPEG.
 *
 * Die Crop-Koordinaten stammen vom Browser und beziehen sich auf das
 * EXIF-gedrehte Bild (so zeigt es der Browser an). Deshalb wird die
 * Ausrichtung VOR dem Zuschnitt angewendet. Alle Werte werden auf die
 * Bildgrenzen geklemmt – Fremdwerte können nichts ausserhalb lesen.
 *
 * @param string $tmpPath  geprüfte Upload-Datei
 * @param string $mimeType 'image/jpeg' oder 'image/png'
 * @param int    $x,$y     linke obere Ecke des Ausschnitts (Originalpixel)
 * @param int    $size     Kantenlänge des Ausschnitts (Originalpixel)
 * @param string $destPath Zieldatei
 */
function create_avatar_image(string $tmpPath, string $mimeType, int $x, int $y, int $size, string $destPath): bool
{
    if ($mimeType === 'image/jpeg') {
        $src = @imagecreatefromjpeg($tmpPath);
        if ($src !== false) {
            $src = apply_exif_orientation($src, $tmpPath);
        }
    } elseif ($mimeType === 'image/png') {
        $src = @imagecreatefrompng($tmpPath);
    } else {
        return false;
    }
    if ($src === false) {
        return false;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $maxSize = min($w, $h);

    // --- Ausschnitt auf das Bild klemmen ---
    $size = max(min(AVATAR_MIN_CROP, $maxSize), min($size, $maxSize));
    $x    = max(0, min($x, $w - $size));
    $y    = max(0, min($y, $h - $size));

    // --- Auf weissen Grund zeichnen (JPEG kennt keine Transparenz) ---
    $dst = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
    imagefilledrectangle($dst, 0, 0, AVATAR_SIZE, AVATAR_SIZE, imagecolorallocate($dst, 255, 255, 255));
    imagealphablending($dst, true);

    $ok = imagecopyresampled($dst, $src, 0, 0, $x, $y, AVATAR_SIZE, AVATAR_SIZE, $size, $size);
    if ($ok) {
        $ok = imagejpeg($dst, $destPath, AVATAR_QUALITY);
    }
    imagedestroy($src);
    imagedestroy($dst);

    if (!$ok) {
        @unlink($destPath);
    }
    return $ok;
}
