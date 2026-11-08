// =============================================================
// Dashboard
// Ausgelagert aus public/php/account/dashboard.php (CSP: keine Inline-Skripte).
// Wird an derselben Stelle wie zuvor synchron geladen, d. h. das
// darüberliegende HTML ist beim Ausführen bereits vorhanden.
// =============================================================
'use strict';

const CSRF = document.querySelector('meta[name="app-csrf-token"]').content;

/** Spot vom Dashboard aus löschen */
async function dashDeleteSpot(spotId, btn) {
    if (!confirm('Diesen Spot wirklich löschen?')) return;

    btn.disabled = true;

    const fd = new FormData();
    fd.set('csrf_token', CSRF);
    fd.set('_method', 'DELETE');

    try {
        const res  = await fetch(`/api/spot.php?id=${encodeURIComponent(spotId)}`, {
            method: 'POST', body: fd,
        });
        const data = await res.json();

        if (!res.ok) {
            showDash('dashErrorAlert', data.error ?? 'Fehler beim Löschen.');
            btn.disabled = false;
            return;
        }

        // Zeile aus der Liste entfernen
        const row = document.getElementById(`spot-row-${spotId}`);
        if (row) row.remove();
        showDash('dashSuccessAlert', 'Spot erfolgreich gelöscht.');

        // Zähler aktualisieren
        const badge = document.querySelector('.badge.bg-primary');
        if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent, 10) - 1);

    } catch {
        showDash('dashErrorAlert', 'Netzwerkfehler. Bitte erneut versuchen.');
        btn.disabled = false;
    }
}

function showDash(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 4000);
}

// ---------------------------------------------------------------
// Bio bearbeiten
// ---------------------------------------------------------------
const bioTextarea = document.getElementById('bioTextarea');
const bioCharCount = document.getElementById('bioCharCount');

function updateBioCount() {
    bioCharCount.textContent = bioTextarea.value.length + ' / 1000';
}
updateBioCount();
bioTextarea.addEventListener('input', updateBioCount);

document.getElementById('bioSaveBtn').addEventListener('click', async () => {
    const bio = bioTextarea.value.trim();
    if (bio.length < 1) {
        showBioAlert('error', 'Die Beschreibung darf nicht leer sein.');
        return;
    }

    const saveBtn = document.getElementById('bioSaveBtn');
    saveBtn.disabled = true;

    try {
        const res  = await fetch('/api/update_bio.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bio, csrf_token: CSRF }),
        });
        const data = await res.json();

        if (data.success) {
            const display = document.getElementById('bioDisplay');
            display.innerHTML = '';
            bio.split('\n').forEach((line, i) => {
                if (i > 0) display.appendChild(document.createElement('br'));
                display.appendChild(document.createTextNode(line));
            });
            showBioAlert('success', 'Beschreibung gespeichert.');
        } else {
            showBioAlert('error', data.error || 'Fehler beim Speichern.');
        }
    } catch {
        showBioAlert('error', 'Netzwerkfehler. Bitte erneut versuchen.');
    } finally {
        saveBtn.disabled = false;
    }
});

function showBioAlert(type, msg) {
    const s = document.getElementById('bioSuccessAlert');
    const e = document.getElementById('bioErrorAlert');
    s.classList.add('d-none');
    e.classList.add('d-none');
    if (type === 'success') { s.textContent = msg; s.classList.remove('d-none'); }
    else                    { e.textContent = msg; e.classList.remove('d-none'); }
}

// Lösch-Button je Spot (data-action statt onclick)
UiActions.register('dash-delete-spot', (btn) => dashDeleteSpot(Number(btn.dataset.spotId), btn));
