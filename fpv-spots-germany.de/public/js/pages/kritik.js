// =============================================================
// Kritik & Vorschläge
// Ausgelagert aus public/php/community/kritik.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
let pendingVoteForm = null;

function openVoteConfirm(form) {
    pendingVoteForm = form;
    new bootstrap.Modal(document.getElementById('voteConfirmModal')).show();
}

document.getElementById('voteConfirmBtn').addEventListener('click', () => {
    if (pendingVoteForm) pendingVoteForm.submit();
});

// Vote-Button (data-action statt onclick)
UiActions.register('open-vote-confirm', (btn) => openVoteConfirm(btn.form));
