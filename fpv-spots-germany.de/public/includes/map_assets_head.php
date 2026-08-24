<?php
require_once __DIR__ . '/../../private/php/core/assets.php';
/* ============================================================
   Leaflet-Styles (im <head> einbinden)

   Lokal gehostet unter /public/vendor/leaflet/ – bewusst NICHT
   ueber ein CDN: der Service Worker faengt Requests ab und
   unterliegt dabei der CSP-Direktive connect-src, die nur
   'self' erlaubt. Ein CDN-Leaflet wurde dadurch blockiert und
   die Karte blieb leer. Zusaetzlich entfaellt so die
   Drittland-Uebermittlung an unpkg (DSGVO).

   Muss VOR /public/css/map.css laden, damit eigene Overrides
   gewinnen. Cache-Busting via asset() – Version in
   private/php/core/assets.php pflegen.
   Aktuelle Version: Leaflet 1.9.4
   ============================================================ */
?>
<link rel="stylesheet" href="<?= asset('/public/vendor/leaflet/leaflet.css') ?>">
