<?php
declare(strict_types=1);
/* ============================================================
   Einheitlicher Seitenkopf: Kicker + <h1> + optionaler Lead
   ------------------------------------------------------------
   Aufruf (vor dem include setzen):
     $pageTitle   string  Pflicht – wird zur einzigen <h1> der Seite
     $pageKicker  string  optional – kleine Zeile ueber der Ueberschrift
     $pageIcon    string  optional – Bootstrap-Icon, nur "bi-…"
     $pageLead    string  optional – Einleitungssatz (reiner Text)
     $pageNarrow  bool    optional – auf 800 px begrenzen (Rechtstexte)
     $pageAction  array   optional – ['href' => '/pfad', 'label' => '…',
                                      'icon' => 'bi-…'] fuer einen
                                      Ghost-Button rechts (z. B. "Zurueck")

   Alle Werte werden hier escaped. Icon-Namen und Links werden
   zusaetzlich gegen eine Whitelist geprueft, damit auch ein
   versehentlich durchgereichter Nutzerwert nichts einschleust.
   Die Variablen werden am Ende geloescht, damit sie nicht in
   spaetere Includes durchsickern.
   ============================================================ */

$__e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Nur Bootstrap-Icon-Klassen zulassen
$__icon = static function ($v): string {
    return (is_string($v) && preg_match('/^bi-[a-z0-9-]+$/', $v)) ? $v : '';
};

$__title  = isset($pageTitle)  && is_string($pageTitle)  ? $pageTitle  : '';
$__kicker = isset($pageKicker) && is_string($pageKicker) ? $pageKicker : '';
$__lead   = isset($pageLead)   && is_string($pageLead)   ? $pageLead   : '';
$__pIcon  = $__icon($pageIcon ?? null);
$__narrow = !empty($pageNarrow);

// Aktions-Link: nur seiteninterne Pfade ("/…", nicht "//host")
$__action = null;
if (isset($pageAction) && is_array($pageAction)) {
    $__href = $pageAction['href'] ?? '';
    if (is_string($__href) && preg_match('#^/(?!/)[^\s\\\\]*$#', $__href) && !empty($pageAction['label'])) {
        $__action = [
            'href'  => $__href,
            'label' => (string)$pageAction['label'],
            'icon'  => $__icon($pageAction['icon'] ?? null),
        ];
    }
}
?>
<header class="fsg-page-head<?= $__narrow ? ' fsg-page-head--narrow' : '' ?>">
    <div class="fsg-page-head-row">
        <div class="fsg-page-head-text">
            <?php if ($__kicker !== ''): ?>
                <p class="fsg-kicker">
                    <?php if ($__pIcon !== ''): ?><i class="bi <?= $__pIcon ?>" aria-hidden="true"></i><?php endif; ?>
                    <?= $__e($__kicker) ?>
                </p>
            <?php endif; ?>
            <h1 class="fsg-page-title"><?= $__e($__title) ?></h1>
            <?php if ($__lead !== ''): ?>
                <p class="fsg-lead"><?= $__e($__lead) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($__action !== null): ?>
            <a href="<?= $__e($__action['href']) ?>" class="fsg-btn fsg-btn--ghost fsg-btn--sm fsg-page-head-action">
                <?php if ($__action['icon'] !== ''): ?><i class="bi <?= $__action['icon'] ?>" aria-hidden="true"></i><?php endif; ?>
                <?= $__e($__action['label']) ?>
            </a>
        <?php endif; ?>
    </div>
</header>
<?php
unset($__e, $__icon, $__title, $__kicker, $__lead, $__pIcon, $__narrow, $__action, $__href,
      $pageTitle, $pageKicker, $pageIcon, $pageLead, $pageNarrow, $pageAction);
