// =============================================================
// Spot-Detail – Foto-Großansicht
// Ausgelagert aus public/php/spots/spot_detail.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
(function () {
    var modal = document.getElementById('photoModal');
    if (!modal) return;
    var imgEl = document.getElementById('photoModalImg');
    modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;
        imgEl.src = trigger.getAttribute('data-img-src') || '';
        imgEl.alt = trigger.getAttribute('data-img-alt') || '';
    });
    modal.addEventListener('hidden.bs.modal', function () {
        imgEl.src = '';
        imgEl.alt = '';
    });
})();
