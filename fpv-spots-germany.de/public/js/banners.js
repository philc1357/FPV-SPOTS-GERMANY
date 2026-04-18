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

    // Update Banner
    var updateBackdrop = document.getElementById('update-banner-backdrop');
    if (updateBackdrop) {
        if (!getCookie('update_notice_v3')) {
            updateBackdrop.style.display = 'flex';
        }
        var updateBtn = document.getElementById('update-banner-btn');
        if (updateBtn) {
            updateBtn.addEventListener('click', function () {
                setCookie('update_notice_v3', 'seen', 356);
                updateBackdrop.style.display = 'none';
            });
        }
    }

    // Notification Badge (Dropdown show/hide)
    var btnBadge = document.getElementById('update-notify-btn');
    if (btnBadge) {
        var updateLink     = document.getElementById('update-notify-link');
        var suggestionLink = document.getElementById('suggestion-notify-link');
        var dropdown = document.querySelector('.dropdown');
        if (dropdown) {
            dropdown.addEventListener('shown.bs.dropdown', function () {
                btnBadge.classList.add('d-none');
                if (updateLink)     updateLink.classList.remove('d-none');
                if (suggestionLink) suggestionLink.classList.remove('d-none');
            });
            dropdown.addEventListener('hidden.bs.dropdown', function () {
                if (updateLink)     updateLink.classList.add('d-none');
                if (suggestionLink) suggestionLink.classList.add('d-none');
                btnBadge.classList.remove('d-none');
            });
        }
    }

    // Message Count Polling
    var messageBadge = document.getElementById('message-notify-badge');
    if (messageBadge) {
        function checkMessages() {
            fetch('/public/php/api/messages.php?action=unread_count', { credentials: 'same-origin' })
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
        checkMessages();
        setInterval(checkMessages, 15000);
    }
}());
