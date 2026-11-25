<?php
// =============================================================
// Highlight-Auswahl (Modal) – Spot-Detail und Spot-Bearbeiten
// -------------------------------------------------------------
// Erwartet: $images     – Fotos des Spots (id, filename, highlight_pos,
//                          optional username/created_at), neueste zuerst
//           $spotId     – Spot-ID
//           $csrfToken  – CSRF-Token der Session
//           $hlRedirect – 'detail' oder 'edit' (Rücksprungziel)
//
// Ohne JS: Pro Foto ein <select> (– / 1 / 2 / 3), das reicht zum
// Speichern. Mit JS (highlight_picker.js) werden die Selects
// ausgeblendet und per Antippen der Kacheln in Reihenfolge gesetzt.
// Nur einbinden, wenn der Nutzer den Spot verwalten darf.
// =============================================================
require_once __DIR__ . '/../../private/php/core/image_utils.php';
require_once __DIR__ . '/../../private/php/spots/highlight_helper.php';

$hlE = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="modal fade" id="highlightModal" data-bs-theme="dark" tabindex="-1" aria-labelledby="highlightModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content fsg-modal">
            <form method="POST" action="/private/php/spots/highlights_submit.php"
                  class="fsg-hl-form" id="highlightForm"
                  data-max="<?= (int)SPOT_HIGHLIGHTS_MAX ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="highlightModalLabel"><i class="bi bi-stars me-2" aria-hidden="true"></i>Highlights festlegen</h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= $hlE($csrfToken) ?>">
                    <input type="hidden" name="spot_id" value="<?= (int)$spotId ?>">
                    <input type="hidden" name="redirect" value="<?= $hlRedirect === 'detail' ? 'detail' : 'edit' ?>">

                    <p class="fsg-meta mb-3">
                        Tippe bis zu <?= (int)SPOT_HIGHLIGHTS_MAX ?> Fotos in der gewünschten Reihenfolge an.
                        <strong class="text-light">Platz 1 ist das Titelbild</strong> – groß oben auf der Spot-Seite,
                        auf dem Handy allein sichtbar und Vorschaubild auf der Karte.
                        Freie Plätze füllen sich automatisch mit den neuesten Fotos.
                    </p>

                    <fieldset>
                        <legend class="visually-hidden">Fotos für die Highlights</legend>
                        <p class="fsg-hl-status" id="highlightStatus" aria-live="polite"></p>
                        <div class="fsg-hl-grid">
                            <?php foreach ($images as $n => $img):
                                $imgId   = (int)$img['id'];
                                $imgPos  = (int)($img['highlight_pos'] ?? 0);
                                $imgFile = '/public/uploads/imgs/' . basename((string)$img['filename']);
                                $imgDesc = 'Foto ' . ($n + 1)
                                    . (!empty($img['username']) ? ' von ' . $img['username'] : '')
                                    . (!empty($img['created_at']) ? ', ' . date('d.m.Y', strtotime((string)$img['created_at'])) : '');
                            ?>
                                <div class="fsg-hl-item">
                                    <button type="button" class="fsg-hl-tile"
                                            data-hl-id="<?= $imgId ?>"
                                            <?= $imgPos > 0 ? 'data-pos="' . $imgPos . '"' : '' ?>
                                            aria-pressed="<?= $imgPos > 0 ? 'true' : 'false' ?>"
                                            aria-label="<?= $hlE($imgDesc) ?>">
                                        <img src="<?= $hlE(thumb_url($imgFile)) ?>" alt=""
                                             loading="lazy" decoding="async">
                                        <span class="fsg-hl-badge" aria-hidden="true"><?= $imgPos > 0 ? $imgPos : '' ?></span>
                                    </button>
                                    <label class="visually-hidden" for="hlSel<?= $imgId ?>">Platz für <?= $hlE($imgDesc) ?></label>
                                    <select class="fsg-field fsg-hl-select" id="hlSel<?= $imgId ?>"
                                            name="highlight[<?= $imgId ?>]" data-hl-id="<?= $imgId ?>">
                                        <option value="0">–</option>
                                        <?php for ($p = 1; $p <= SPOT_HIGHLIGHTS_MAX; $p++): ?>
                                            <option value="<?= $p ?>" <?= $imgPos === $p ? 'selected' : '' ?>>Platz <?= $p ?><?= $p === 1 ? ' (Titelbild)' : '' ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="reset" value="1" class="fsg-btn fsg-btn--ghost me-auto"
                            title="Highlights entfernen – es werden wieder die neuesten Fotos gezeigt">
                        <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Zurücksetzen
                    </button>
                    <button type="button" class="fsg-btn" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="fsg-btn fsg-btn--primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</div>
