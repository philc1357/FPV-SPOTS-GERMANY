<?php
require_once __DIR__ . '/../../private/php/core/assets.php';
/* ============================================================
   Zentrales Bootstrap-JS-Bundle (am Seitenende einbinden)
   Bundle = inkl. Popper -> nötig für Dropdown, Modal, Collapse,
   Toast. Cache-Busting via asset() – Version in
   private/php/core/assets.php pflegen.
   ============================================================ */
?>
<script src="<?= asset('/public/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
