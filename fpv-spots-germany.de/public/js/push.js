// =============================================================
// FPV Spots Germany – Web-Push Opt-in (Chrome-first)
// =============================================================
(function () {
    'use strict';

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        return;
    }

    var vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
    var csrfMeta  = document.querySelector('meta[name="csrf-token"]');
    var vapidKey  = vapidMeta ? vapidMeta.content.trim() : '';
    var csrfToken = csrfMeta  ? csrfMeta.content         : '';

    var toggle    = document.getElementById('push-toggle');
    var statusEl  = document.getElementById('push-status');

    function setStatus(msg, isError, isSuccess) {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.classList.remove('text-success', 'text-danger', 'text-muted');
        if (isError) statusEl.classList.add('text-danger');
        else if (isSuccess) statusEl.classList.add('text-success');
        else statusEl.classList.add('text-muted');
    }

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw     = window.atob(base64);
        var out     = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function subscriptionToJson(sub) {
        var json = sub.toJSON();
        return {
            endpoint: json.endpoint,
            keys: {
                p256dh: json.keys.p256dh,
                auth:   json.keys.auth
            }
        };
    }

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf_token: csrfToken }, payload))
        });
    }

    function syncToggleState() {
        if (!toggle) return;
        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            toggle.checked  = !!sub;
            toggle.disabled = Notification.permission === 'denied';
            if (Notification.permission === 'denied') {
                setStatus('Benachrichtigungen sind im Browser blockiert. Aktiviere sie in den Browser-Einstellungen.', true);
            } else if (sub) {
                setStatus('Browser-Benachrichtigungen sind aktiv.', false);
            } else {
                setStatus('Browser-Benachrichtigungen sind deaktiviert.', false);
            }
        }).catch(function (err) {
            console.error('Push: sync failed', err);
        });
    }

    function enablePush() {
        if (!vapidKey) {
            setStatus('Server ist noch nicht fuer Push konfiguriert.', true);
            toggle.checked = false;
            return;
        }

        Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') {
                toggle.checked = false;
                setStatus('Berechtigung wurde nicht erteilt.', true);
                return;
            }

            navigator.serviceWorker.ready.then(function (reg) {
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey)
                });
            }).then(function (sub) {
                toggle.checked = true;
                return postJson('/public/php/api/push_subscribe.php', subscriptionToJson(sub))
                    .then(function (r) {
                        if (!r.ok) throw new Error('Server-Fehler ' + r.status);
                        setStatus('Browser-Benachrichtigungen sind aktiv.', false, true);
                    })
                    .catch(function (apiErr) {
                        console.error('Push: API-Speicherung fehlgeschlagen', apiErr);
                        setStatus('Aktiviert, aber Server-Sync fehlgeschlagen.', true);
                    });
            }).catch(function (err) {
                console.error('Push: subscribe failed', err);
                syncToggleState();
                setStatus('Aktivierung fehlgeschlagen.', true);
            });
        });
    }

    function disablePush() {
        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            if (!sub) {
                setStatus('Browser-Benachrichtigungen sind deaktiviert.', false);
                return;
            }
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () {
                return postJson('/public/php/api/push_unsubscribe.php', { endpoint: endpoint });
            }).then(function () {
                setStatus('Browser-Benachrichtigungen sind deaktiviert.', false);
            });
        }).catch(function (err) {
            console.error('Push: unsubscribe failed', err);
            setStatus('Deaktivierung fehlgeschlagen.', true);
        });
    }

    if (toggle) {
        toggle.addEventListener('change', function () {
            if (toggle.checked) enablePush();
            else                disablePush();
        });
        syncToggleState();
    }
})();
