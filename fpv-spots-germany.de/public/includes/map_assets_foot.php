<?php
require_once __DIR__ . '/../../private/php/core/assets.php';
/* ============================================================
   Leaflet-JS (am Seitenende einbinden)

   Lokal gehostet – Begruendung siehe map_assets_head.php.
   Muss VOR /public/js/map.js laden, sonst ist `L` undefiniert.
   Cache-Busting via asset() – Version in
   private/php/core/assets.php pflegen.
   Aktuelle Version: Leaflet 1.9.4
   ============================================================ */
?>
<script src="<?= asset('/public/vendor/leaflet/leaflet.js') ?>"></script>
