<?php
declare(strict_types=1);

/* ============================================================
   BetaFPV-Empfehlungen (Partnerprogramm)

   Reine Datendatei: hier pflegen, nichts im Code anfassen.
   Solange die Liste leer ist, bleibt das Feature komplett aus –
   kein Block auf den Spot-Seiten, kein Menuepunkt, die Seite
   /ausruestung.php antwortet mit 404.

   Felder je Eintrag:
     slug   Kurzname fuer die Klick-Statistik (a-z, 0-9, Bindestrich)
     name   Produktname, wie er angezeigt wird
     sizes  Coptergroessen, fuer die das Produkt passt. Erlaubt sind
            exakt die Werte der Spot-Datenbank:
            'Tinywhoop', '2-3 Zoll', '3-5 Zoll', '5+ Zoll'.
            Leeres Array = passt zu jedem Spot.
     text   Eigene Erfahrung in 1-2 Saetzen (nicht aus dem Shop kopieren)
     url    Vollstaendiger Partner-Link von BetaFPV (nur https://…betafpv.com)

   Beispiel (auskommentiert):
   [
       'slug'  => 'beispiel-whoop',
       'name'  => 'Beispiel Whoop',
       'sizes' => ['Tinywhoop'],
       'text'  => 'Fliege ich selbst seit … Monaten, robust und …',
       'url'   => 'https://betafpv.com/products/…?ref=…',
   ],
   ============================================================ */

/* ------------------------------------------------------------
   Vorbereitete Favoriten (Texte vom Betreiber).
   Aktivierung: Partner-Link bei 'url' eintragen und den Eintrag
   einkommentieren. Erst dann geht das Feature live.
   ------------------------------------------------------------ */

return [
    // [
    //     'slug'  => 'air65',
    //     'name'  => 'BetaFPV Air65',
    //     'sizes' => ['Tinywhoop'],
    //     'text'  => 'Mein Daily Copter: Den fliege ich fast jeden Tag.',
    //     'url'   => 'https://betafpv.com/…',   // Partner-Link einsetzen
    // ],
    // [
    //     'slug'  => 'meteor75',
    //     'name'  => 'BetaFPV Meteor75',
    //     'sizes' => ['Tinywhoop'],
    //     'text'  => 'Gerade bei etwas Wind macht der Meteor75 richtig Spaß.',
    //     'url'   => 'https://betafpv.com/…',   // Partner-Link einsetzen
    // ],
    // [
    //     'slug'  => 'elrs-receiver',
    //     'name'  => 'BetaFPV ELRS Receiver',
    //     'sizes' => [],                         // passt zu jedem Spot
    //     'text'  => 'Steckt bei mir in jedem Copter.',
    //     'url'   => 'https://betafpv.com/…',   // Partner-Link einsetzen
    // ],
];
