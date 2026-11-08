// =============================================================
// Forum
// Ausgelagert aus public/php/community/forum.php (CSP: keine Inline-Skripte).
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

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.forum-reply-toggle');
    if (!btn) return;
    var formId = btn.getAttribute('data-reply-target');
    if (!formId) return;
    var form = document.getElementById(formId);
    if (!form) return;
    form.classList.toggle('d-none');
    if (!form.classList.contains('d-none')) {
        var ta = form.querySelector('textarea');
        if (ta) ta.focus();
    }
});
