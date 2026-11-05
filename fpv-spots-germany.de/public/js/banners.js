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

    // ---------------------------------------------------------
    // Discord-Banner (Bootstrap-Modal)
    //
    // Erscheint erst nach dem Cookie-OK, damit nie zwei Banner
    // gleichzeitig offen sind. Die Ruhezeit steckt allein in der
    // Lebensdauer des Cookies discordbanner-seen:
    //   „Zum Discord-Server" -> 365 Tage, „Beenden"/ESC -> 30 Tage.
    // ---------------------------------------------------------
    var DISCORD_COOKIE = 'discordbanner-seen';
    var discordBanner  = document.getElementById('discord-banner');
    var discordModal   = null;
    var discordChoiceMade = false;

    function rememberDiscordChoice(value, days) {
        discordChoiceMade = true;
        setCookie(DISCORD_COOKIE, value, days);
        if (discordModal) discordModal.hide();
    }

    function showDiscordBanner() {
        if (!discordBanner || !window.bootstrap) return;
        if (getCookie(DISCORD_COOKIE) || !getCookie('cookie_consent')) return;
        discordModal = bootstrap.Modal.getOrCreateInstance(discordBanner);
        discordModal.show();
    }

    if (discordBanner) {
        var joinBtn  = document.getElementById('discord-banner-join');
        var closeBtn = document.getElementById('discord-banner-close');
        if (joinBtn) {
            // Kein preventDefault: der Link oeffnet Discord normal im neuen Tab
            joinBtn.addEventListener('click', function () {
                rememberDiscordChoice('joined', 365);
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                rememberDiscordChoice('dismissed', 30);
            });
        }
        // Schliessen per ESC zaehlt wie „Beenden"
        discordBanner.addEventListener('hide.bs.modal', function () {
            if (!discordChoiceMade) setCookie(DISCORD_COOKIE, 'dismissed', 30);
        });
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
                showDiscordBanner();
            });
        }
    }

    showDiscordBanner();

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
