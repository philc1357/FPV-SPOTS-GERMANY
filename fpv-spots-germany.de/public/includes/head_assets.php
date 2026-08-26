<?php
require_once __DIR__ . '/../../private/php/core/assets.php';
/* ============================================================
   Zentrale Vendor-Styles (im <head> einbinden)
   Reihenfolge ist wichtig: Bootstrap zuerst, Icons, dann das
   eigene theme.css (Overrides gewinnen -> muss zuletzt laden).
   banners.css lag frueher mitten im <body> (header.php) und
   blockierte das Rendern dort ein zweites Mal.
   Cache-Busting via asset() – Version in
   private/php/core/assets.php pflegen.
   ============================================================ */
?>
<link rel="icon" type="image/x-icon" href="/favicon.ico">

<!-- Icon-Font vorziehen: sonst faellt der Browser erst beim Parsen von
     bootstrap-icons.min.css darauf, und die Icons erscheinen sichtbar
     nach. Der Query-String muss exakt dem url() in der CSS entsprechen,
     sonst laedt die Datei zweimal. -->
<link rel="preload" href="/public/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2?dd67030699838ea613ee6dbda90effa6"
      as="font" type="font/woff2" crossorigin>

<link rel="stylesheet" href="<?= asset('/public/vendor/bootstrap/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= asset('/public/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= asset('/public/css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('/public/css/banners.css') ?>">

<?php
/* ============================================================
   Reichweitenmessung (Umami)
   Das Tag wird serverseitig gerendert – bei Opt-out, DNT,
   Admin-Session oder abgeschalteter Messung entsteht gar kein
   Script-Element. Script und Datenendpunkt liegen auf der
   eigenen Domain, die CSP bleibt deshalb bei 'self'.
   Logik: private/php/core/analytics.php
   ============================================================ */
require_once __DIR__ . '/../../private/php/core/analytics.php';
?>
<?= umami_script_tag() ?>
<?= umami_event_tag() ?>
