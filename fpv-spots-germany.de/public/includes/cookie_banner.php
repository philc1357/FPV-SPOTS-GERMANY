<style>
#cookie-banner {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: #16213e;
    border-top: 1px solid #0f3460;
    padding: 1rem 1.5rem;
    display: none;
}
</style>

<aside id="cookie-banner" role="dialog" aria-live="polite" aria-label="Cookie-Hinweis">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <p class="text-light mb-0 small">
            Diese Website verwendet Cookies, um die Funktionalität sicherzustellen (z.&nbsp;B. Karten-Einstellungen, Login-Status).
            Weitere Informationen findest du in unserer
            <a href="/public/php/datenschutz.php" class="text-info">Datenschutzerklärung</a>.
        </p>
        <button id="cookie-accept-btn" type="button" class="btn btn-success btn-sm text-nowrap">
            Akzeptieren
        </button>
    </div>
</aside>

<script>
(function () {
    'use strict';

    var COOKIE_NAME = 'cookie_consent';
    var COOKIE_DAYS = 30;

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

    var banner = document.getElementById('cookie-banner');

    if (!getCookie(COOKIE_NAME)) {
        banner.style.display = 'block';
    }

    document.getElementById('cookie-accept-btn').addEventListener('click', function () {
        setCookie(COOKIE_NAME, 'accepted', COOKIE_DAYS);
        banner.style.display = 'none';
    });
}());
</script>
