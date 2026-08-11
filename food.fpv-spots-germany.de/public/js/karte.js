/* ============================================================
   Initialisierung der Leaflet-Karte
   - Zentriert auf Deutschland-Mittelpunkt
   - OpenStreetMap-Tiles mit Attribution
   ============================================================ */
(function () {
    'use strict';

    const map = L.map('map', {
        center: [51.165, 10.4515],
        zoom: 6,
        zoomControl: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-Mitwirkende',
    }).addTo(map);

    L.control.scale({ imperial: false }).addTo(map);
})();
