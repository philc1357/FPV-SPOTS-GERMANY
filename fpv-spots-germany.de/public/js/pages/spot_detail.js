// =============================================================
// Spot-Detail – Kopieren, Kommentar/Parken bearbeiten
// Ausgelagert aus public/php/spots/spot_detail.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
// Spot-Link in Zwischenablage kopieren
function copySpotLink(btn) {
    const url = window.location.href;
    const original = btn.innerHTML;
    const done = () => {
        btn.innerHTML = '<i class="bi bi-check2"></i> Kopiert!';
        btn.disabled = true;
        setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 2000);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(done).catch(() => { fallbackCopy(url); done(); });
    } else {
        fallbackCopy(url); done();
    }
}
// Spot-Koordinaten in Zwischenablage kopieren
function copySpotCoords(btn) {
    const text = btn.dataset.lat + ', ' + btn.dataset.lng;
    const original = btn.innerHTML;
    const done = () => {
        btn.innerHTML = '<i class="bi bi-check2"></i> Kopiert!';
        btn.disabled = true;
        setTimeout(() => { btn.innerHTML = original; btn.disabled = false; }, 2000);
    };
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(done).catch(() => { fallbackCopy(text); done(); });
    } else {
        fallbackCopy(text); done();
    }
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
