'use strict';

(function () {
    function getCookie(name) {
        var match = document.cookie.split('; ').find(function (row) {
            return row.startsWith(name + '=');
        });
        return match ? match.split('=')[1] : null;
    }

    function setCookie(name, value, days) {
        var expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value)
            + '; expires=' + expires
            + '; path=/'
            + '; SameSite=Lax';
    }

    // Cookie Banner
    var cookieBanner = document.getElementById('cookie-banner');
    if (cookieBanner) {
        if (!getCookie('cookie_consent')) {
            cookieBanner.style.display = 'block';
        }
        var acceptBtn = document.getElementById('cookie-accept-btn');
        if (acceptBtn) {
            acceptBtn.addEventListener('click', function () {
                setCookie('cookie_consent', 'accepted', 30);
                cookieBanner.style.display = 'none';
            });
        }
    }

    // Notification Badge (Dropdown show/hide)
    var btnBadge = document.getElementById('update-notify-btn');
    if (btnBadge) {
        var notificationLink = document.getElementById('notification-notify-link');
        var suggestionLink   = document.getElementById('suggestion-notify-link');
        var dropdown = document.querySelector('.dropdown');
        if (dropdown) {
            dropdown.addEventListener('shown.bs.dropdown', function () {
                btnBadge.classList.add('d-none');
                if (notificationLink) notificationLink.classList.remove('d-none');
                if (suggestionLink)   suggestionLink.classList.remove('d-none');
            });
            dropdown.addEventListener('hidden.bs.dropdown', function () {
                if (notificationLink) notificationLink.classList.add('d-none');
                if (suggestionLink)   suggestionLink.classList.add('d-none');
                btnBadge.classList.remove('d-none');
            });
        }
    }

    // Message Count Polling
    var messageBadge = document.getElementById('message-notify-badge');
    if (messageBadge) {
        function checkMessages() {
            fetch('/api/messages.php?action=unread_count', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var count = data.unread_count || 0;
                    if (count > 0) {
                        messageBadge.textContent = count;
                        messageBadge.classList.remove('d-none');
                        if (btnBadge) btnBadge.classList.remove('d-none');
                    } else {
                        messageBadge.classList.add('d-none');
                    }
                })
                .catch(function () {});
        }
        // ---------------------------------------------------------
        // Ungelesene Nachrichten pollen
        //
        // Frueher alle 15 s auf jeder Seite – 240 Requests pro Stunde
        // und offenem Tab, jeder mit Session-Start, auth_check und
        // DB-Zugriff. Jetzt 60 s, und im Hintergrund-Tab gar nicht:
        // beim Zurueckwechseln wird einmal sofort nachgezogen.
        // ---------------------------------------------------------
        var POLL_INTERVAL = 60000;
        var pollTimer = null;

        function startPolling() {
            if (pollTimer === null) {
                pollTimer = setInterval(checkMessages, POLL_INTERVAL);
            }
        }
        function stopPolling() {
            if (pollTimer !== null) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') {
                stopPolling();
            } else {
                checkMessages();
                startPolling();
            }
        });

        checkMessages();
        if (document.visibilityState !== 'hidden') {
            startPolling();
        }
    }
}());
