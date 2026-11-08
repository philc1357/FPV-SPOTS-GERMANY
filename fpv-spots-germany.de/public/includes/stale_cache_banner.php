<?php
declare(strict_types=1);
// =============================================================
// Hinweis-Modal: veralteter Service-Worker-Cache
//
// Hintergrund: Bis Cache-Version v2 wurde /public/js/map.js cache-first
// ausgeliefert. Installierte PWAs behalten dadurch eine veraltete map.js,
// die noch den alten API-Pfad /public/php/api/ aufruft – seit der Umstellung
// auf /api/ laufen diese Aufrufe ins Leere und die Karte bleibt leer.
//
// WICHTIG – warum hier alles inline steht:
// Bei genau den betroffenen Nutzern liefert der alte Service Worker
// banners.css und banners.js aus seinem Cache aus. Ein Banner, der sein
// Styling oder seine Logik aus diesen Dateien bezieht, waere bei der
// Zielgruppe unsichtbar und funktionslos. HTML kommt dagegen frisch vom
// Server (Navigation ist auch im alten Service Worker network-first).
// Deshalb: eigenes <style> direkt in dieser Datei, keine Abhaengigkeit
// von Bootstrap oder projekteigenen Assets. Das Skript liegt seit der
// CSP-Haertung (kein 'unsafe-inline') in einer EIGENEN, neuen Datei
// /public/js/stale_cache_banner.js – die kennt kein alter Cache, sie
// kommt also immer frisch vom Server.
// =============================================================
?>
<aside id="stale-cache-banner" role="dialog" aria-modal="true"
       aria-labelledby="stale-cache-title" hidden>
    <div class="scb-box">
        <h2 id="stale-cache-title" class="scb-title">
            <span aria-hidden="true">&#9888;</span> Karte bleibt leer?
        </h2>

        <p class="scb-lead">
            Diese App läuft bei dir noch in einer veralteten Version, die
            gespeicherte Spots nicht mehr laden kann. Deine Spots sind
            selbstverständlich alle noch da.
        </p>

        <button type="button" id="scb-repair" class="scb-btn scb-btn-primary">
            Jetzt reparieren
        </button>

        <p class="scb-hint">
            Dauert einen Moment, danach lädt die Seite neu.
            Du bleibst eingeloggt.
        </p>

        <details class="scb-details">
            <summary>Klappt nicht? App neu installieren</summary>
            <ol class="scb-steps">
                <li>App vom Gerät deinstallieren
                    <span class="scb-muted">(Handy: Symbol gedrückt halten &rarr;
                    Deinstallieren. Desktop: in der App auf &#8942; &rarr;
                    &bdquo;FPV Spots Germany deinstallieren&ldquo;)</span>
                </li>
                <li>fpv-spots-germany.de im Browser öffnen</li>
                <li>App über das Menü erneut installieren</li>
            </ol>
        </details>

        <button type="button" id="scb-dismiss" class="scb-btn scb-btn-ghost">
            Später
        </button>
    </div>
</aside>

<style>
/* ---------------------------------------------------------------
   Eigenstaendiges Styling – bewusst ohne Bootstrap-Abhaengigkeit,
   da Bootstrap bei Betroffenen aus dem alten Cache kommt.
   Farben orientieren sich am bestehenden Cookie-Banner.
   --------------------------------------------------------------- */
#stale-cache-banner {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(0, 0, 0, .6);
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
}
#stale-cache-banner[hidden] { display: none; }

#stale-cache-banner .scb-box {
    width: 100%;
    max-width: 30rem;
    max-height: 90vh;
    overflow-y: auto;
    padding: 1.5rem;
    border: 1px solid #0f3460;
    border-radius: .5rem;
    background: #16213e;
    color: #f8f9fa;
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, .5);
}

#stale-cache-banner .scb-title {
    margin: 0 0 .75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: #ffc107;
}
#stale-cache-banner .scb-lead   { margin: 0 0 1.25rem; line-height: 1.5; }
#stale-cache-banner .scb-hint   { margin: .75rem 0 0; font-size: .8125rem; color: #adb5bd; }
#stale-cache-banner .scb-muted  { display: block; margin-top: .25rem; color: #adb5bd; font-size: .8125rem; }

#stale-cache-banner .scb-btn {
    display: block;
    width: 100%;
    padding: .625rem 1rem;
    border: 0;
    border-radius: .375rem;
    font: inherit;
    font-weight: 500;
    cursor: pointer;
}
#stale-cache-banner .scb-btn-primary { background: #198754; color: #fff; }
#stale-cache-banner .scb-btn-primary:hover:not(:disabled) { background: #157347; }
#stale-cache-banner .scb-btn-primary:disabled { opacity: .65; cursor: default; }
#stale-cache-banner .scb-btn-ghost {
    margin-top: 1rem;
    background: transparent;
    color: #adb5bd;
    text-decoration: underline;
}
#stale-cache-banner .scb-btn:focus-visible { outline: 2px solid #0d6efd; outline-offset: 2px; }

#stale-cache-banner .scb-details { margin-top: 1.25rem; font-size: .875rem; }
#stale-cache-banner .scb-details summary { cursor: pointer; color: #9ec5fe; }
#stale-cache-banner .scb-steps { margin: .75rem 0 0; padding-left: 1.25rem; line-height: 1.6; }
#stale-cache-banner .scb-steps li { margin-bottom: .5rem; }
</style>

<script src="<?= asset('/public/js/stale_cache_banner.js') ?>"></script>

