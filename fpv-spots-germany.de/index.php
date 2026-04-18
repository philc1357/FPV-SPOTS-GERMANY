<?php
// =============================================================
// FPV Spots Germany – Hauptseite (Karte)
// =============================================================
session_start();
require_once __DIR__ . '/private/php/core/auth_check.php';

// CSRF-Token einmalig pro Session generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$userId     = (int)($_SESSION['user_id'] ?? 0);
$csrfToken  = $_SESSION['csrf_token'];

// Legende-Filter aus Cookie (Default: alle aktiv)
$allTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Verein', 'Wasser', 'Sonstige'];
$allDiffs = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];
$legendTypes = isset($_COOKIE['legend_types'])
    ? array_intersect(explode(',', $_COOKIE['legend_types']), $allTypes)
    : $allTypes;
$legendDiffs = isset($_COOKIE['legend_diffs'])
    ? array_intersect(explode(',', $_COOKIE['legend_diffs']), $allDiffs)
    : $allDiffs;

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">

    <!-- Leaflet CSS -->
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/map.css">
</head>
<body>

<?php include __DIR__ . '/public/includes/header.php'; ?>

<!-- ============================================================
     Karte
============================================================ -->
<main>
    <h1 class="visually-hidden">FPV Spots Germany – Interaktive Karte</h1>
    <div id="map"></div>
    <button id="locateMeBtn" aria-label="Zu meinem Standort" title="Zu meinem Standort">
        <i class="bi bi-geo-alt-fill"></i>
    </button>
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
        $typeColors = ['Bando'=>'#4b4e5a','Feld'=>'#ffe224','Gebirge'=>'#b1602d','Park'=>'#3f9826','Verein'=>'#ab3cbf','Wasser'=>'#2056ac','Sonstige'=>'#ffffff'];
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
        <label><span class="legend-dot" style="background:<?= $color ?>"></span><?= $diff ?><input type="checkbox" class="legend-cb" data-filter="diff" value="<?= $diff ?>"<?= $chk ?>></label><br>
        <?php endforeach; ?>
    </div>
</div>

<!-- Karte-Klick-Hinweis (nur für eingeloggte Nutzer sichtbar) -->
<?php if ($isLoggedIn): ?>
<div id="mapHint">
    <span class="badge bg-dark bg-opacity-75 text-light px-3 py-2 fs-7">
        Auf die Karte klicken, um einen Spot zu erstellen
    </span>
</div>
<?php endif; ?>

<!-- ============================================================
     Toast: Hinweis für nicht eingeloggte Nutzer beim Karten-Klick
============================================================ -->
<div class="position-fixed bottom-0 start-50 translate-middle-x p-3" style="z-index:1200">
    <div id="loginHintToast"
         class="toast align-items-center text-bg-warning border-0"
         role="alert" aria-live="assertive" data-bs-delay="3000">
        <div class="d-flex">
            <div class="toast-body">
                Bitte
                <a href="#" class="alert-link fw-bold"
                   data-bs-toggle="modal" data-bs-target="#loginModal">einloggen</a>,
                um Spots zu erstellen.
            </div>
            <button type="button" class="btn-close me-2 m-auto"
                    data-bs-dismiss="toast" aria-label="Schliessen"></button>
        </div>
    </div>
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

<!-- ============================================================
     Modal: Login
============================================================ -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="loginModalLabel">Einloggen</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <form id="loginForm" action="/private/php/auth/login_submit.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="redirect" value="/">
                    <div class="mb-3">
                        <input type="text" name="username"
                               class="form-control bg-secondary text-light border-0"
                               placeholder="Benutzername"
                               autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password"
                               class="form-control bg-secondary text-light border-0"
                               placeholder="Passwort"
                               autocomplete="current-password" required>
                    </div>
                    <div class="text-end mb-2">
                        <a href="/forgot_password.php" class="text-success small">Passwort vergessen?</a>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember_me" value="1" id="rememberMe"
                               class="form-check-input" style="accent-color: #198754;">
                        <label for="rememberMe" class="form-check-label small">Eingeloggt bleiben (30 Tage)</label>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2">
                        Einloggen
                    </button>
                </form>
                <hr class="border-secondary">
                <p class="text-center mb-0 small">
                    Noch kein Konto?
                    <a href="/register.php" class="text-success">Jetzt registrieren</a>
                </p>
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
                        <option value="Verein">Verein</option>
                        <option value="Wasser">Wasser</option>
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

                <div class="col-12">
                    <p class="text-secondary small mb-0">
                        &#128205; Koordinaten:
                        <span id="createCoordsDisplay" class="text-light fw-semibold"></span>
                    </p>
                </div>

                <div class="col-12">
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

<!-- ============================================================
     Scripts
============================================================ -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

<script src="/public/js/map.js"></script>
<script src="/public/js/pwa.js"></script>

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
