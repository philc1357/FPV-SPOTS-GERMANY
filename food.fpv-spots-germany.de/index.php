<?php
declare(strict_types=1);

require_once __DIR__ . '/private/php/core/session_init.php';
require_once __DIR__ . '/private/php/core/db.php';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Interaktive Karte für wilde Food-Spots in Deutschland – Nussbäume, Obstbäume, Sträucher, Kräuter und mehr.">
    <title>Wild Food Spot Map</title>

    <!-- ============================================================
         Externe Stylesheets (CDN): Bootstrap + Leaflet
    ============================================================ -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="anonymous">
    <link rel="stylesheet" href="/public/css/karte.css">
</head>
<body>
    <?php require __DIR__ . '/public/includes/header.php'; ?>

    <main id="map" aria-label="Karte mit wilden Food-Spots"></main>

    <!-- ============================================================
         JavaScript-Bundles: Leaflet + Bootstrap + Karten-Init
    ============================================================ -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
            crossorigin="anonymous"></script>
    <script src="/public/js/karte.js"></script>
</body>
</html>
