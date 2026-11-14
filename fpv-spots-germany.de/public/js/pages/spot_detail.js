// =============================================================
// Spot-Detail – Kopieren, Kommentar/Parken bearbeiten
// Ausgelagert aus public/php/spots/spot_detail.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
// ---------------------------------------------------------------
// Kopieren mit Rueckmeldung
//
// Icon-Knoepfe (.fsg-icon-btn) zeigen nur einen Haken, damit sich ihre
// Breite nicht aendert; Menue-Eintraege bekommen "Kopiert!" als Text.
// Der Originalinhalt wird als DOM-Knoten gesichert, nicht als HTML.
// ---------------------------------------------------------------
function copyWithFeedback(btn, text) {
    const original = Array.from(btn.childNodes).map(n => n.cloneNode(true));
    const iconOnly = btn.classList.contains('fsg-icon-btn');
    const done = () => {
        const check = document.createElement('i');
        check.className = 'bi bi-check2' + (iconOnly ? '' : ' me-2');
        check.setAttribute('aria-hidden', 'true');
        btn.replaceChildren(check);
        if (!iconOnly) btn.append('Kopiert!');
        btn.classList.add('is-copied');
        btn.disabled = true;
        setTimeout(() => {
            btn.replaceChildren(...original);
            btn.classList.remove('is-copied');
            btn.disabled = false;
        }, 2000);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(done).catch(() => { fallbackCopy(text); done(); });
    } else {
        fallbackCopy(text); done();
    }
}
// Spot-Link in Zwischenablage kopieren (ohne #-Anker)
function copySpotLink(btn) {
    copyWithFeedback(btn, window.location.href.split('#')[0]);
}
// Spot-Koordinaten in Zwischenablage kopieren
function copySpotCoords(btn) {
    const lat = Number(btn.dataset.lat);
    const lng = Number(btn.dataset.lng);
    if (!isFinite(lat) || !isFinite(lng)) return;
    copyWithFeedback(btn, lat.toFixed(6) + ', ' + lng.toFixed(6));
}
function fallbackCopy(text) {
    const el = document.createElement('textarea');
    el.value = text;
    el.style.position = 'fixed';
    el.style.opacity = '0';
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
}

// Kommentar-Bearbeitung ein-/ausblenden
function toggleEditComment(id) {
    document.getElementById('comment-display-' + id).classList.toggle('d-none');
    document.getElementById('comment-edit-' + id).classList.toggle('d-none');
}

// ---------------------------------------------------------------
// Betriebsklassen-Umschalter
//
// Die Rechtstexte werden serverseitig gerendert, deshalb muss die
// Seite nach dem Wechsel neu laden. Mit JS uebernimmt das Formular
// das beim Anklicken selbst, der "Übernehmen"-Knopf entfaellt.
// ---------------------------------------------------------------
(function () {
    var form = document.getElementById('pilotClassForm');
    if (!form) return;
    var submitBtn = document.getElementById('pilotClassSubmit');
    if (submitBtn) submitBtn.classList.add('d-none');

    form.querySelectorAll('.pilot-class-cb').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (radio.checked) form.submit();
        });
    });
})();

// Parkmöglichkeit bearbeiten ein-/ausblenden
function toggleEditParking() {
    document.getElementById('parking-display').classList.toggle('d-none');
    document.getElementById('parking-edit').classList.toggle('d-none');
}
(function () {
    var cb = document.getElementById('detailParkingUnknown');
    if (!cb) return;
    cb.addEventListener('change', function () {
        var ta = document.getElementById('detailParkingInfo');
        if (this.checked) {
            ta.disabled = true;
            ta.value = '';
        } else {
            ta.disabled = false;
            ta.focus();
        }
    });
})();

// ============================================================
// YouTube-Einbettung (Zwei-Klick-Loesung)
// Beim Seitenaufruf wird nur das Vorschaubild geladen. Erst wenn
// der Nutzer auf den Play-Button klickt, wird der Player von
// youtube-nocookie.com nachgeladen und ersetzt das Vorschaubild.
// ============================================================
(function () {
    var grid = document.getElementById('videoGrid');
    if (!grid) return;

    var YT_ID_PATTERN = /^[A-Za-z0-9_-]{11}$/;

    grid.addEventListener('click', function (event) {
        var facade = event.target.closest('.yt-facade');
        if (!facade) return;

        var wrapper = facade.closest('.yt-embed');
        if (!wrapper) return;

        // Video-ID clientseitig erneut gegen Whitelist pruefen
        var videoId = wrapper.dataset.ytId || '';
        if (!YT_ID_PATTERN.test(videoId)) return;

        var iframe = document.createElement('iframe');
        iframe.src = 'https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1&rel=0';
        iframe.title = wrapper.dataset.ytTitle || 'YouTube-Video';
        iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allowFullscreen = true;
        iframe.setAttribute('frameborder', '0');

        wrapper.replaceChildren(iframe);
    });
})();

// Stern-Hover-Effekt
document.querySelectorAll('#starRating .star').forEach(label => {
    label.addEventListener('mouseenter', () => {
        const val = parseInt(label.getAttribute('for').replace('star', ''));
        document.querySelectorAll('#starRating .star').forEach(s => {
            const sv = parseInt(s.getAttribute('for').replace('star', ''));
            s.classList.toggle('active', sv <= val);
        });
    });
});
const starRating = document.getElementById('starRating');
if (starRating) {
    starRating.addEventListener('mouseleave', () => {
        const checked = starRating.querySelector('input:checked');
        const val = checked ? parseInt(checked.value) : 0;
        document.querySelectorAll('#starRating .star').forEach(s => {
            const sv = parseInt(s.getAttribute('for').replace('star', ''));
            s.classList.toggle('active', sv <= val);
        });
    });
}

// Buttons im Template (data-action statt onclick)
UiActions.register('copy-spot-link',      (btn) => copySpotLink(btn));
UiActions.register('copy-spot-coords',    (btn) => copySpotCoords(btn));
UiActions.register('toggle-edit-parking', () => toggleEditParking());
UiActions.register('toggle-edit-comment', (btn) => toggleEditComment(Number(btn.dataset.commentId)));

// ---------------------------------------------------------------
// Aufklappbare Karten (Geozonen, Wetter) per Anker öffnen
//
// Beide Bereiche sind standardmäßig eingeklappt. Zeigt die URL auf
// #geozonen/#wetter (z. B. nach dem Speichern der Copter-Klasse oder
// über den Flugstatus in den Schnell-Infos), klappt der Bereich auf.
// Die ID aus dem Hash wird streng geprüft, bevor sie ins DOM geht.
// ---------------------------------------------------------------
(function () {
    function oeffneBereich(hash) {
        var id = (hash || '').replace(/^#/, '');
        if (!/^[a-z][a-z0-9_-]{0,40}$/i.test(id)) return;
        var section = document.getElementById(id);
        if (!section) return;
        var det = section.querySelector(':scope > details.fsg-collapse');
        if (det && !det.open) det.open = true;
    }

    oeffneBereich(window.location.hash);
    window.addEventListener('hashchange', function () { oeffneBereich(window.location.hash); });

    // Klick auf einen Anker bei bereits gesetztem Hash löst kein
    // hashchange aus – deshalb zusätzlich direkt beim Klick öffnen.
    document.addEventListener('click', function (ev) {
        var link = ev.target.closest('a[href^="#"]');
        if (link) oeffneBereich(link.getAttribute('href'));
    });
})();
