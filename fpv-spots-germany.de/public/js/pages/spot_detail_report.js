// =============================================================
// Spot-Detail – Melden (Zeichenzähler)
// Ausgelagert aus public/php/spots/spot_detail.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
(function () {
    var ta = document.getElementById('reportBody');
    var counter = document.getElementById('reportCharCount');
    if (!ta || !counter) return;
    ta.addEventListener('input', function () {
        counter.textContent = ta.value.length;
    });
})();
