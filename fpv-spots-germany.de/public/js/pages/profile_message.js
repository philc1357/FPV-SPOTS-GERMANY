// =============================================================
// Profil – Nachricht senden
// Ausgelagert aus public/php/account/profile.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
'use strict';
(function () {
    const CSRF         = document.querySelector('meta[name="app-csrf-token"]').content;
    const PROFILE_ID   = Number(document.currentScript.dataset.profileId);
    const API          = '/api/messages.php';
    const msgBody      = document.getElementById('msgBody');
    const msgCharCount = document.getElementById('msgCharCount');
    const msgSendBtn   = document.getElementById('msgSendBtn');
    const msgSuccess   = document.getElementById('msgSuccessAlert');
    const msgError     = document.getElementById('msgErrorAlert');

    msgBody.addEventListener('input', () => {
        msgCharCount.textContent = msgBody.value.length + ' / 2000';
    });

    msgSendBtn.addEventListener('click', async () => {
        const body = msgBody.value.trim();
        if (!body) { msgBody.focus(); return; }

        msgSendBtn.disabled = true;
        msgSuccess.classList.add('d-none');
        msgError.classList.add('d-none');

        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'send',
                    recipient_id: PROFILE_ID,
                    body: body,
                    csrf_token: CSRF,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                msgError.textContent = data.error || 'Fehler beim Senden.';
                msgError.classList.remove('d-none');
                return;
            }
            msgBody.value = '';
            msgCharCount.textContent = '0 / 2000';
            msgSuccess.innerHTML = 'Nachricht gesendet! <a href="/messages.php?conversation_id=' + data.conversation_id + '" class="alert-link">Zur Konversation</a>';
            msgSuccess.classList.remove('d-none');
        } catch (e) {
            msgError.textContent = 'Netzwerkfehler beim Senden.';
            msgError.classList.remove('d-none');
        } finally {
            msgSendBtn.disabled = false;
        }
    });

    // Zuruecksetzen beim Schliessen
    document.getElementById('sendMessageModal').addEventListener('hidden.bs.modal', () => {
        msgSuccess.classList.add('d-none');
        msgError.classList.add('d-none');
    });
})();
