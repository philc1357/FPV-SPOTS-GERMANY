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
        <strong class="text-success d-block mb-2"><i class="bi bi-rocket-fill me-1"></i> Großes Update &ndash; Neue Features</strong>
        <p class="fw-bold mb-2 text-light" style="font-size:1.05rem;">Zum installieren des Updates Browserdaten löschen und Web-App neu installieren.</p>
        <ul class="text-light small mb-3 ps-3 mt-1">
            <li class="mb-2">
                <strong>Profilbeschreibung:</strong> Im Dashboard kannst du jetzt eine kurze Bio über dich hinterlegen &ndash;
                erzähl anderen, wer du bist und wo du fliegst.
            </li>
            <li class="mb-2">
                <strong>Öffentliche Profilseite:</strong> Jeder Nutzer hat jetzt eine eigene Profilseite mit einer
                Mini-Karte, auf der alle seine Spots auf einen Blick zu sehen sind.
            </li>
            <li class="mb-2">
                <strong>Direktnachrichten:</strong> Du kannst registrierten Nutzern jetzt direkt eine Nachricht schreiben &ndash;
                einfach das Profil aufrufen und auf <i class="bi bi-envelope-fill"></i> klicken.
            </li>
            <li class="mb-2">
                <strong>Satellitenansicht:</strong> Die Karte unterstützt jetzt eine Satellitenansicht &ndash;
                ideal um Gelände, Bebauung und Flugbereiche besser einschätzen zu können.
            </li>
            <li class="mb-0">
                <strong>Smarte Benachrichtigungen:</strong> Das <i class="bi bi-exclamation-circle-fill text-warning"></i>-Symbol
                im Menü zeigt dir jetzt an, wenn du eine neue Nachricht erhalten hast, jemand einen neuen
                Verbesserungsvorschlag eingereicht hat oder ein Kommentar zu deinem Vorschlag hinzugekommen ist.
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

    var COOKIE_NAME = 'update_notice_v3';
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
