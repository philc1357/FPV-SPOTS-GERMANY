<style>
#update-banner-backdrop {
    position: fixed;
    inset: 0;
    z-index: 9998;
    background: rgba(0, 0, 0, 0.6);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
#update-banner {
    background: #1a2e1a;
    border: 1px solid #198754;
    border-radius: .5rem;
    padding: 1.5rem;
    width: 100%;
    max-width: 560px;
}
</style>

<div id="update-banner-backdrop" role="dialog" aria-modal="true" aria-label="Update-Hinweis">
    <aside id="update-banner">
        <strong class="text-success d-block mb-2">&#128640; Neue Features &amp; Verbesserungen</strong>
        <ul class="text-light small mb-3 ps-3 mt-1">
            <li class="mb-2">
                <strong>Parkmöglichkeiten (neu):</strong> Jeder registrierte Nutzer kann die Parkmöglichkeit
                eines Spots direkt auf der Detailseite eintragen und bearbeiten &ndash; klick einfach auf das
                &#9998;-Symbol. Es wird auch angezeigt, wer zuletzt etwas geändert hat.
            </li>
            <li class="mb-2">
                <strong>Standort-Button:</strong> Der blaue Standort-Button ist jetzt für alle sichtbar.
                Wer den Standort bisher abgelehnt hat, kann ihn durch einen Klick auf den Button erneut freigeben.
            </li>
            <li class="mb-0">
                <strong>Update-Benachrichtigungen:</strong> Wenn es neue Updates gibt, erscheint ein
                &#10071; neben dem Menü &ndash; so verpasst du keine Neuigkeiten mehr.
            </li>
        </ul>
        <div class="text-end">
            <button id="update-banner-btn" type="button" class="btn btn-success btn-sm">
                Verstanden
            </button>
        </div>
    </aside>
</div>

<script>
(function () {
    'use strict';

    var COOKIE_NAME = 'update_notice_v2';
    var COOKIE_DAYS = 356;

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

    var backdrop = document.getElementById('update-banner-backdrop');

    if (!getCookie(COOKIE_NAME)) {
        backdrop.style.display = 'flex';
    }

    document.getElementById('update-banner-btn').addEventListener('click', function () {
        setCookie(COOKIE_NAME, 'seen', COOKIE_DAYS);
        backdrop.style.display = 'none';
    });
}());
</script>
