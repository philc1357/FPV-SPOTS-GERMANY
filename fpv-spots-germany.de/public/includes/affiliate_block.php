<?php
/* ============================================================
   Partner-Block „Werbung“ (BetaFPV)

   Erwartet vor dem include:
     $affiliateItems  Liste aus affiliate_for_sizes()/affiliate_products()
     $affiliateTitle  Ueberschrift des Blocks
     $affiliateId     Anker-ID (eindeutig pro Seite)

   Kennzeichnung: sichtbares Badge „Werbung“ direkt am Block plus
   Erklaersatz. Links nur als <a> – kein Skript, keine Fremdbilder,
   daher ohne CSP-Aenderung (Klick-Zaehlung ueber Umami-Attribute).
   ============================================================ */
if (empty($affiliateItems)) {
    return;
}
$affiliateTitle = $affiliateTitle ?? 'Passende Ausrüstung';
$affiliateId    = $affiliateId ?? 'ausruestung-block';
$esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
?>
<section class="card card-dark text-light p-4 mt-4" id="<?= $esc($affiliateId) ?>" aria-labelledby="<?= $esc($affiliateId) ?>-titel">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
        <h2 class="h5 mb-0" id="<?= $esc($affiliateId) ?>-titel">
            <i class="bi bi-bag me-2" aria-hidden="true"></i><?= $esc($affiliateTitle) ?>
        </h2>
        <span class="badge text-bg-warning">Werbung</span>
    </div>

    <p class="small text-secondary mb-3">
        Partnerlinks zu BetaFPV: Kaufst du über einen dieser Links, erhalten wir eine kleine Provision.
        Für dich ändert sich der Preis nicht. Mehr dazu in der
        <a href="/datenschutz.php#partnerlinks" class="text-light">Datenschutzerklärung</a>.
    </p>

    <ul class="list-unstyled mb-0">
        <?php foreach ($affiliateItems as $item): ?>
            <li class="mb-3">
                <a href="<?= $esc($item['url']) ?>"
                   class="fw-semibold"
                   target="_blank"
                   rel="sponsored noopener noreferrer"
                   data-umami-event="affiliate:betafpv"
                   data-umami-event-produkt="<?= $esc($item['slug']) ?>">
                    <?= $esc($item['name']) ?> <span class="visually-hidden">(Werbung, externer Link zu BetaFPV)</span>
                    <i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i>
                </a>
                <?php if ($item['text'] !== ''): ?>
                    <div class="small"><?= $esc($item['text']) ?></div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
