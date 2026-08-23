<?php
/* ============================================================
   Leaflet-Styles (im <head> einbinden)

   Lokal gehostet unter /public/vendor/leaflet/ – bewusst NICHT
   ueber ein CDN: der Service Worker faengt Requests ab und
   unterliegt dabei der CSP-Direktive connect-src, die nur
   'self' erlaubt. Ein CDN-Leaflet wurde dadurch blockiert und
   die Karte blieb leer. Zusaetzlich entfaellt so die
   Drittland-Uebermittlung an unpkg (DSGVO).

   Muss VOR /public/css/map.css laden, damit eigene Overrides
   gewinnen. Versionswechsel nur hier + in map_assets_foot.php.
   Aktuelle Version: Leaflet 1.9.4
   ============================================================ */
?>
<link rel="stylesheet" href="/public/vendor/leaflet/leaflet.css">
