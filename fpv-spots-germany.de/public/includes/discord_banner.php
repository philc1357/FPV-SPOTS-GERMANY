<?php
/* ============================================================
   Discord-Banner: Hinweis auf den Community-Discord
   Wird von banners.js als Bootstrap-Modal eingeblendet, sobald
   cookie_consent gesetzt ist und das Cookie discordbanner-seen
   fehlt. „Zum Discord-Server" -> 365 Tage Ruhe, „Beenden" -> 30.
   ============================================================ */
const DISCORD_INVITE_URL = 'https://discord.gg/ukbPTt3Xf';
?>
<div class="modal fade" id="discord-banner" data-bs-backdrop="static" tabindex="-1"
     aria-labelledby="discord-banner-title" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <aside class="modal-content text-light border-secondary" style="background:#16213e;">
            <div class="modal-body p-4">
                <!-- Bewusst kein <h2>: Das Banner liegt vor der eigentlichen Seitenüberschrift (SEO). -->
                <p id="discord-banner-title" class="h5 d-flex align-items-center gap-2 mb-3" style="color:#8c9eff;">
                    <i class="bi bi-discord" aria-hidden="true"></i> Komm in unseren Discord
                </p>
                <p class="small mb-4">
                    Tausch dich mit anderen FPV-Piloten aus, teile Spot-Tipps, verabrede dich zum Fliegen
                    und erfahre als Erstes von neuen Features der Seite.
                </p>
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <button id="discord-banner-close" type="button" class="btn btn-outline-light btn-sm">
                        Beenden
                    </button>
                    <a id="discord-banner-join" class="btn btn-primary btn-sm"
                       href="<?= htmlspecialchars(DISCORD_INVITE_URL, ENT_QUOTES, 'UTF-8') ?>"
                       target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-discord me-1" aria-hidden="true"></i> Zum Discord-Server
                    </a>
                </div>
            </div>
        </aside>
    </div>
</div>
