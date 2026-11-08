// =============================================================
// Forum – Beitrag bearbeiten
// Ausgelagert aus public/php/community/forum_edit.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
document.querySelectorAll('.forum-edit-image-tile input[type="checkbox"]').forEach(function (cb) {
    var tile = cb.closest('.forum-edit-image-tile');
    cb.addEventListener('change', function () {
        tile.classList.toggle('is-removing', cb.checked);
    });
});
