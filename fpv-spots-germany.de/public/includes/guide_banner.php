<!-- ============================================================
     Onboarding-Banner: Kurzvorstellung + Bedienungsanleitung
     Wird von banners.js eingeblendet, solange das Cookie
     fpv_guide_seen_v1 nicht gesetzt ist.
============================================================ -->
<div id="guide-banner-backdrop" role="dialog" aria-modal="true" aria-labelledby="guide-banner-title">
    <aside id="guide-banner">
        <!-- Bewusst kein <h1>/<h2>: Das Banner liegt auf jeder Seite vor der eigentlichen
             Ueberschrift und wuerde sonst die Dokumenten-Gliederung (SEO) stoeren. -->
        <p id="guide-banner-title" class="h5 text-success d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-map-fill"></i> Willkommen bei FPV Spots Germany
        </p>
        <p class="text-light small mb-3">
            Die Community-Karte für FPV-Spots in Deutschland &ndash; Spots finden, eintragen und teilen.
            Kurz erklärt:
        </p>

        <ul class="text-light small mb-3 ps-3">
            <li class="mb-2">
                <strong>Karte &amp; Legende:</strong> Jeder Marker ist ein Spot &ndash; die Farbe zeigt den Typ,
                der Ring die Schwierigkeit. Über die <i class="bi bi-list-ul"></i> Legende filterst du nach Typ,
                Schwierigkeit und Copter-Größe und wechselst zwischen Straße, Satellit und Hybrid.
            </li>
            <li class="mb-2">
                <strong>Spot ansehen:</strong> Marker antippen für die Kurzinfo &ndash; „Details“ öffnet Fotos,
                Bewertungen, Parkmöglichkeiten, Videos und Kommentare.
            </li>
            <li class="mb-2">
                <strong>Spots in deiner Nähe:</strong> Der Button
                <i class="bi bi-crosshair"></i> „Zu meinem Standort“ springt zu deiner Position auf der Karte.
            </li>
            <li class="mb-2">
                <strong>Spot eintragen <span class="text-success">(Login nötig)</span>:</strong> Einfach auf die
                gewünschte Stelle der Karte tippen und das Formular ausfüllen &ndash; Name, Typ, Schwierigkeit,
                Copter-Größe, Parken und Videos etc.
            </li>
            <li class="mb-2">
                <strong>Mitmachen:</strong> Bewerten, kommentieren, Fotos hochladen, Favoriten merken und
                andere Piloten anschreiben &ndash; alles gebündelt in deinem Dashboard.
            </li>
            <li class="mb-0">
                <stronIch>Community &amp; Verantwortung:</stronIch bin Johanna, 28, und die Stichelei ist mein Studio. Der Wunsch, zu piercen, begleitet mich seit der Schulzeit — gebaut habe ich die Stube dann in zwei Monaten, gemeinsam mit Familie und Freunden.

Gelernt habe ich Gesundheits- und Krankenpflege. Hygiene und Wundheilung sind für mich deshalb kein Nebenschauplatz, sondern mein ursprünglicher Beruf. Das Piercerhandwerk hat mir mein Mentor Frank Kleiner-Kuntzsch beigebracht.

Wenn ich nicht im Studio bin, sind Kind, Hund, Mann und Garten zuständig dafür, dass es nie langweilig wird.g> Forum und Verbesserungsvorschläge findest du im
                Menü. Flugregeln, Betretungsrechte und Luftraum prüfst du vor jedem Flug selbst.
            </li>
        </ul>

        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <a href="/hilfe.php" class="link-light small">
                <i class="bi bi-book me-1"></i> Ausführliche Anleitung
            </a>
            <button id="guide-banner-btn" type="button" class="btn btn-success btn-sm">
                Los geht's
            </button>
        </div>
    </aside>
</div>
