<?php
/* ============================================================
   Asset-Versionierung (Cache-Busting)

   Die .htaccess gibt statischen Dateien ein langes max-age.
   Ohne Versionsmarker im Pfad wuerden Besucher nach einem
   Deploy tagelang altes CSS/JS behalten – der Browser fragt
   bei gueltigem max-age gar nicht erst nach.

   asset() haengt deshalb ?v=ASSET_VERSION an. Aendert sich die
   Konstante, ist die URL neu und der Cache umgangen.

   WICHTIG: ASSET_VERSION und CACHE_VERSION in sw.js gehoeren
   zusammen – beide bei Asset-Aenderungen gemeinsam erhoehen,
   sonst liefert der Service Worker weiter die alten Dateien.
   ============================================================ */

const ASSET_VERSION = '25';

/**
 * Haengt den Versionsparameter an einen absoluten Asset-Pfad.
 * Bereits vorhandene Query-Parameter bleiben erhalten.
 */
function asset(string $path): string
{
    $sep = str_contains($path, '?') ? '&' : '?';
    return $path . $sep . 'v=' . ASSET_VERSION;
}
