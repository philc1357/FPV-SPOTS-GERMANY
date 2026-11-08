<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Hauptseite (Karte)
// =============================================================
require_once __DIR__ . "/private/php/core/session_init.php";
require_once __DIR__ . '/private/php/core/auth_check.php';
require_once __DIR__ . '/private/php/core/geozones.php';

// CSRF-Token einmalig pro Session generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$userId     = (int)($_SESSION['user_id'] ?? 0);
$csrfToken  = $_SESSION['csrf_token'];

// Legende-Filter aus Cookie (Default: alle aktiv)
$allTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Wald', 'Windpark', 'Sonstige'];
$allDiffs = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];
$allSizes = ['Tinywhoop', '2-3 Zoll', '3-5 Zoll', '5+ Zoll'];
$legendTypes = isset($_COOKIE['legend_types'])
    ? array_intersect(explode(',', $_COOKIE['legend_types']), $allTypes)
    : $allTypes;
$legendDiffs = isset($_COOKIE['legend_diffs'])
    ? array_intersect(explode(',', $_COOKIE['legend_diffs']), $allDiffs)
    : $allDiffs;
$legendSizes = isset($_COOKIE['legend_sizes'])
    ? array_intersect(explode(',', $_COOKIE['legend_sizes']), $allSizes)
    : $allSizes;

// Betriebsklasse des Piloten (Cookie pilot_class, Default 'a3')
$pilotClass = pilot_class_from_cookie();

// Fehlermeldung aus Spot-Erstellung (gesetzt von spot_submit.php)
$spotError = '';
if (!empty($_SESSION['spot_error'])) {
    $spotError = htmlspecialchars($_SESSION['spot_error'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['spot_error']);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>FPV Spots Germany – Die besten FPV-Spots auf einer Karte</title>
    <meta name="description" content="Finde und teile die besten FPV-Drohnen-Spots in Deutschland. Interaktive Karte mit Community-Bewertungen, Fotos und Schwierigkeitsgraden.">
    <meta name="keywords" content="FPV, Drohne, Spots, Deutschland, FPV Spots, Drohnen-Karte, FPV Freestyle, FPV Racing">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="FPV Spots Germany – Die besten Drohnen-Spots auf einer Karte">
    <meta property="og:description" content="Finde und teile die besten FPV-Drohnen-Spots in Deutschland. Interaktive Karte mit Community-Bewertungen.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="de_DE">
    <link rel="canonical" href="https://fpv-spots-germany.de/">

    <!-- PWA -->
    <meta name="theme-color" content="#212529">
    <link rel="manifest" href="/manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="FPV Spots">
    <link rel="apple-touch-icon" href="/public/imgs/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/public/imgs/icons/icon-192.png">

    <!-- Bootstrap 5 -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>

    <!-- Leaflet CSS -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/map_assets_head.php'; ?>

    <link rel="stylesheet" href="<?= asset('/public/css/map.css') ?>">
</head>
<body>

<?php include __DIR__ . '/public/includes/header.php'; ?>

<!-- ============================================================
     Karte
============================================================ -->
<main>
    <h1 class="visually-hidden">FPV Spots Germany – Interaktive Karte</h1>
    <div id="map"></div>

    <!-- ------------------------------------------------------------
         Karten-Aktionen unten rechts

         Standort-Button oben, darunter der Einstieg in den
         Platzierungs-Modus. "Spot erstellen" wird immer gerendert –
         die Login-Weiche sitzt im JS (map.js), damit auch Gäste den
         Weg zum Anlegen sehen.
         ------------------------------------------------------------ -->
    <div id="mapActions">
        <button id="locateMeBtn" aria-label="Zu meinem Standort" title="Zu meinem Standort">
            <i class="bi bi-geo-alt-fill"></i>
        </button>
        <button id="createSpotBtn" type="button" aria-pressed="false"
                title="Neuen Spot auf der Karte platzieren">
            <i id="createSpotBtnIcon" class="bi bi-plus-lg" aria-hidden="true"></i>
            <span id="createSpotBtnLabel">Spot erstellen</span>
        </button>
    </div>
</main>

<!-- ============================================================
     Kartenlegende
============================================================ -->
<div id="mapLegend">
    <button id="legendToggle" aria-expanded="false" aria-controls="legendContent">
        Legende <span id="legendArrow"><i class="bi bi-chevron-down"></i></span>
    </button>
    <div id="legendContent" hidden>
        <hr>
        <?php
        $typeColors = ['Bando'=>'#4b4e5a','Feld'=>'#ffe224','Gebirge'=>'#b1602d','Park'=>'#4ab52c','Wald'=>'#2d6a4f','Windpark'=>'#74c2e0','Sonstige'=>'#ffffff'];
        foreach ($typeColors as $type => $color):
            $chk = in_array($type, $legendTypes) ? ' checked' : '';
        ?>
        <label><span class="legend-dot" style="background:<?= $color ?>"></span><?= $type ?><input type="checkbox" class="legend-cb" data-filter="type" value="<?= $type ?>"<?= $chk ?>></label><br>
        <?php endforeach; ?>
        <hr class="border-secondary my-3">
        <?php
        $diffColors = ['Anfänger'=>'#54846c','Mittel'=>'#fcc03c','Fortgeschritten'=>'#d83c48','Profi'=>'#242430'];
        foreach ($diffColors as $diff => $color):
            $chk = in_array($diff, $legendDiffs) ? ' checked' : '';
        ?>
        <label><span class="legend-dot" style="background:transparent;border-color:<?= $color ?>;border-width:3px"></span><?= $diff ?><input type="checkbox" class="legend-cb" data-filter="diff" value="<?= $diff ?>"<?= $chk ?>></label><br>
        <?php endforeach; ?>
        <hr class="border-secondary my-3">
        <?php foreach ($allSizes as $size):
            $chk = in_array($size, $legendSizes) ? ' checked' : '';
        ?>
        <label><?= htmlspecialchars($size, ENT_QUOTES, 'UTF-8') ?><input type="checkbox" class="legend-cb" data-filter="size" value="<?= htmlspecialchars($size, ENT_QUOTES, 'UTF-8') ?>"<?= $chk ?>></label><br>
        <?php endforeach; ?>

        <!-- ----------------------------------------------------------
             Betriebsklasse des Piloten

             Steuert, welche Rechtstexte die Spot-Detailseite zeigt und
             ob der 150-m-Abstandskreis der Unterkategorie A3 auf der
             Karte erscheint. Default ist bewusst die strengste Klasse.
             ---------------------------------------------------------- -->
        <hr class="border-secondary my-3">
        <fieldset class="pilot-class-switch">
            <legend class="float-none fw-semibold mb-1" style="font-size:.8rem">Mein Copter</legend>
            <div class="btn-group-vertical w-100" role="group" aria-label="Betriebsklasse wählen">
                <?php foreach (PILOT_CLASSES as $pcKey => $pcMeta): ?>
                <input type="radio" class="btn-check pilot-class-cb" name="pilotClass"
                       id="pilotClass-<?= htmlspecialchars($pcKey, ENT_QUOTES, 'UTF-8') ?>"
                       value="<?= htmlspecialchars($pcKey, ENT_QUOTES, 'UTF-8') ?>"
                       autocomplete="off"<?= $pilotClass === $pcKey ? ' checked' : '' ?>>
                <label class="btn btn-sm btn-outline-light text-start"
                       for="pilotClass-<?= htmlspecialchars($pcKey, ENT_QUOTES, 'UTF-8') ?>"
                       title="<?= htmlspecialchars($pcMeta['eu'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="d-block"><?= htmlspecialchars($pcMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="d-block opacity-75" style="font-size:.68rem"><?= htmlspecialchars($pcMeta['hint'], ENT_QUOTES, 'UTF-8') ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <p class="opacity-75 mt-1 mb-0" style="font-size:.68rem">
                Bestimmt Rechtshinweise und den 150-m-Kreis.
            </p>
        </fieldset>
    </div>
</div>

<!-- ============================================================
     Hinweisbanner des Platzierungs-Modus

     Nur sichtbar, solange auf einen Karten-Klick gewartet wird
     (map.js schaltet das hidden-Attribut). Ausserhalb des Modus
     bleibt ein Karten-Klick folgenlos.
============================================================ -->
<div id="placingHint" role="status" aria-live="polite" hidden>
    <i class="bi bi-crosshair" aria-hidden="true"></i>
    <span>Tippe auf die Karte, um den Spot zu setzen</span>
    <button type="button" id="placingCancelBtn" class="btn-close btn-close-white"
            aria-label="Platzieren abbrechen"></button>
</div>

<!-- ============================================================
     Toast: Fehler bei Spot-Erstellung (z. B. Validierungsfehler)
============================================================ -->
<?php if ($spotError): ?>
<div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index:1200">
    <div class="toast align-items-center text-bg-danger border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body fw-semibold"><?= $spotError ?></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Schliessen"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/public/includes/login_modal.php'; ?>
<?php include __DIR__ . '/public/includes/register_modal.php'; ?>

<!-- ============================================================
     Modal: Eingaben beim Spot-Erstellen verwerfen?
============================================================ -->
<div class="modal fade" id="discardSpotModal" tabindex="-1" aria-labelledby="discardSpotModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="discardSpotModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Eingaben verwerfen?
                </h5>
            </div>
            <div class="modal-body">
                <p class="mb-0">Du hast bereits Daten eingegeben. Wenn du einen neuen Standort wählst, gehen diese verloren.</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" id="discardCancelBtn">Weiter bearbeiten</button>
                <button type="button" class="btn btn-danger" id="discardConfirmBtn">Verwerfen</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Modal: Standortanfrage (Erstbesuch)
============================================================ -->
<div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="locationModalLabel">
                    <i class="bi bi-geo-alt-fill me-2 text-danger"></i>Standort anzeigen?
                </h5>
            </div>
            <div class="modal-body">
                <p>Möchtest du deinen aktuellen Standort auf der Karte sehen?</p>
                <p class="text-muted small mb-0">Dein Standort wird nur lokal in deinem Browser verarbeitet und nicht gespeichert.</p>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" id="locationDenyBtn">Nein, danke</button>
                <button type="button" class="btn btn-danger" id="locationAllowBtn">
                    <i class="bi bi-geo-alt-fill me-1"></i>Standort freigeben
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Offcanvas: Spot erstellen
     Wird nach Karten-Klick geöffnet, Koordinaten bereits gesetzt
============================================================ -->
<div class="offcanvas offcanvas-bottom bg-dark text-light" tabindex="-1" id="createSpotOffcanvas" style="height: 50dvh;" aria-labelledby="createSpotLabel">
    <div class="offcanvas-header border-bottom border-secondary">
        <h5 class="offcanvas-title" id="createSpotLabel"><i class="bi bi-plus-circle-fill me-2"></i>Neuen Spot erstellen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Schliessen"></button>
    </div>
    <div class="offcanvas-body overflow-auto">
        <div id="createErrorAlert"   class="alert alert-danger  d-none" role="alert"></div>
        <div id="createSuccessAlert" class="alert alert-success d-none" role="alert"></div>
        <form id="createSpotForm" action="/private/php/spots/spot_submit.php" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <!-- Koordinaten werden per JS befüllt -->
            <input type="hidden" id="createLat" name="latitude">
            <input type="hidden" id="createLng" name="longitude">

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label small fw-semibold">Spot-Name *</label>
                    <input type="text" name="name" class="form-control bg-secondary text-light border-0" placeholder="z. B. Kornfeld bei Musterdorf" minlength="5" maxlength="100" required>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Beschreibung *</label>
                    <textarea name="description" class="form-control bg-secondary text-light border-0" placeholder="Hinweise zu Zugang, Regeln, besonderen Merkmalen oder Copter-Art…" rows="3" minlength="10" maxlength="2000" required></textarea>
                </div>

                <div class="col-6">
                    <label class="form-label small fw-semibold">Typ *</label>
                    <select name="spot_type" class="form-select bg-secondary text-light border-0" required>
                        <option value="" disabled selected>Bitte wählen</option>
                        <option value="Bando">Bando</option>
                        <option value="Feld">Feld</option>
                        <option value="Gebirge">Gebirge</option>
                        <option value="Park">Park</option>
                        <option value="Wald">Wald</option>
                        <option value="Windpark">Windpark</option>
                        <option value="Sonstige">Sonstige</option>
                    </select>
                </div>

                <div class="col-6">
                    <label class="form-label small fw-semibold">Schwierigkeit *</label>
                    <select name="difficulty" class="form-select bg-secondary text-light border-0" required>
                        <option value="" disabled selected>Bitte wählen</option>
                        <option value="Anfänger">Anfänger</option>
                        <option value="Mittel">Mittel</option>
                        <option value="Fortgeschritten">Fortgeschritten</option>
                        <option value="Profi">Profi</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Coptergröße <span class="text-secondary fw-normal">(Mehrfachauswahl)</span></label>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                                type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                aria-expanded="false" id="createCopterBtn">
                            Alle Größen wählen…
                        </button>
                        <ul class="dropdown-menu bg-secondary w-100 p-2" aria-labelledby="createCopterBtn">
                            <?php foreach (['Tinywhoop', '2-3 Zoll', '3-5 Zoll', '5+ Zoll'] as $cs): ?>
                            <li class="form-check px-4 py-1">
                                <input class="form-check-input create-copter-check" type="checkbox"
                                       name="copter_size[]"
                                       value="<?= htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') ?>"
                                       id="cs_create_<?= preg_replace('/[^a-z0-9]/i', '_', $cs) ?>">
                                <label class="form-check-label text-light"
                                       for="cs_create_<?= preg_replace('/[^a-z0-9]/i', '_', $cs) ?>"><?= htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') ?></label>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label small fw-semibold">Parkmöglichkeit</label>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="parkingUnknown" checked>
                        <label class="form-check-label small" for="parkingUnknown">Unbekannt</label>
                    </div>
                    <textarea name="parking_info" id="parkingInfo"
                              class="form-control bg-secondary text-light border-0"
                              placeholder="z. B. Kostenloser Parkplatz direkt am Feld…"
                              rows="2" maxlength="500" disabled></textarea>
                </div>


                <!-- ============================================================
                     YouTube-Video-Links (optional, max. 10)
                     Titel wird automatisch beim Speichern von YouTube geholt
                ============================================================ -->
                <div class="col-12">
                    <label class="form-label small fw-semibold">
                        YouTube-Videos <span class="text-secondary fw-normal">(optional, max. 10)</span>
                    </label>
                    <div id="videoLinksContainer">
                        <div class="video-link-row d-flex gap-2 mb-2">
                            <input type="url" name="video_url[]"
                                   class="form-control bg-secondary text-light border-0"
                                   placeholder="https://youtube.com/watch?v=...">
                            <button type="button" class="btn btn-outline-danger btn-sm video-remove flex-shrink-0"
                                    aria-label="Link entfernen">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" id="addVideoLinkBtn" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-plus-lg"></i> Weiterer Link
                    </button>
                </div>

                <div class="col-12">
                    <p class="text-secondary small mb-0">
                        &#128205; Koordinaten:
                        <span id="createCoordsDisplay" class="text-light fw-semibold"></span>
                    </p>
                </div>

                <div class="col-12">
                    <div class="alert alert-warning py-2 small mb-2" role="note">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Das Eintragen ist keine Aufforderung zum illegalen Betreten oder Fliegen.
                        Du bist selbst verantwortlich für die Einhaltung von Luftrecht, Betretungsrechten
                        und lokalen Verordnungen. <a href="/nutzungsbedingungen.php" target="_blank" class="alert-link">Mehr erfahren</a>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2 fw-semibold">
                        Spot speichern
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     Offcanvas: Spot-Detail
     Wird nach Marker-Klick geöffnet
============================================================ -->
<div class="offcanvas offcanvas-bottom bg-dark text-light"
     tabindex="-1" id="spotDetailOffcanvas"
     style="height: 50dvh;"
     aria-labelledby="spotDetailLabel">
    <div class="offcanvas-header border-bottom border-secondary">
        <h5 class="offcanvas-title" id="spotDetailLabel"></h5>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas" aria-label="Schliessen"></button>
    </div>
    <div class="offcanvas-body overflow-auto" id="spotDetailBody"></div>
</div>

<!-- ============================================================
     App-Konfiguration für JavaScript
     Keine sensiblen Daten – nur Status und CSRF-Token
============================================================ -->
<meta name="app-logged-in"  content="<?= $isLoggedIn ? 'true' : 'false' ?>">
<meta name="app-user-id"    content="<?= $userId ?>">
<meta name="app-is-admin"   content="<?= !empty($_SESSION['is_admin']) ? 'true' : 'false' ?>">
<meta name="app-csrf-token" content="<?= $csrfToken ?>">
<meta name="app-pilot-class" content="<?= htmlspecialchars($pilotClass, ENT_QUOTES, 'UTF-8') ?>">

<!-- ============================================================
     Scripts
============================================================ -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/map_assets_foot.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>

<script src="<?= asset('/public/js/map.js') ?>"></script>
<script src="<?= asset('/public/js/pwa.js') ?>"></script>
<script src="<?= asset('/public/js/pages/index.js') ?>"></script>

<!-- PWA: Install-Banner -->
<aside id="pwaInstallBanner" class="d-none position-fixed bottom-0 start-0 end-0 p-3" style="z-index:1300">
    <div class="d-flex align-items-center justify-content-between bg-dark border border-secondary rounded-3 p-3 shadow-lg mx-auto" style="max-width:480px">
        <div class="d-flex align-items-center gap-2">
            <img src="/public/imgs/icons/icon-72.png" alt="" width="36" height="36" class="rounded">
            <span class="text-light small fw-semibold">App installieren</span>
        </div>
        <div class="d-flex gap-2">
            <button id="pwaInstallDismiss" type="button" class="btn btn-sm btn-outline-secondary">Nein</button>
            <button id="pwaInstallBtn" type="button" class="btn btn-sm btn-success">Installieren</button>
        </div>
    </div>
</aside>

<!-- PWA: iOS Install-Hinweis -->
<aside id="pwaIosBanner" class="d-none position-fixed bottom-0 start-0 end-0 p-3" style="z-index:1300">
    <div class="bg-dark border border-secondary rounded-3 p-3 shadow-lg mx-auto" style="max-width:480px">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <strong class="text-light small">App installieren</strong>
            <button id="pwaIosDismiss" type="button" class="btn-close btn-close-white btn-sm" aria-label="Schliessen"></button>
        </div>
        <p class="text-secondary small mb-0">
            Tippe auf <strong class="text-light">&#8679; Teilen</strong> und dann auf
            <strong class="text-light">&#43; Zum Startbildschirm</strong>, um die App zu installieren.
        </p>
    </div>
</aside>

<!-- PWA: Update-Banner -->
<aside id="pwaUpdateBanner" class="d-none position-fixed top-0 start-0 end-0 p-3" style="z-index:1300">
    <div class="d-flex align-items-center justify-content-between bg-dark border border-success rounded-3 p-3 shadow-lg mx-auto" style="max-width:480px">
        <span class="text-light small">Neue Version verfügbar</span>
        <button id="pwaUpdateBtn" type="button" class="btn btn-sm btn-success">Aktualisieren</button>
    </div>
</aside>

</body>
</html>
