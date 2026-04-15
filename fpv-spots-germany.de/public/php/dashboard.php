<?php
// =============================================================
// FPV Spots Germany – Dashboard / Profilbereich
// =============================================================
session_start();
require_once __DIR__ . '/../../private/php/auth_check.php';

// Eingeloggt-Check zuerst
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// CSRF-Token für Formulare auf dieser Seite
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../private/php/db.php';

$isLoggedIn = true;
$userId     = (int)$_SESSION['user_id'];
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];

// Profil-Daten laden
$stmt = $pdo->prepare("SELECT username, email, bio FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData) {
    session_destroy();
    header('Location: /');
    exit;
}

// Eigene Spots laden (neueste zuerst)
$stmt = $pdo->prepare(
    "SELECT id, name, spot_type, difficulty, latitude, longitude, created_at
     FROM spots
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->execute([$userId]);
$mySpots = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – FPV Spots Germany</title>
    <meta name="description" content="Verwalte deine FPV-Spots und Kontodaten.">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container py-4">
    <div class="row g-4">

        <!-- ====================================================
             Karte: Profil & Konto-Daten
        ==================================================== -->
        <div class="col-12 col-md-5">
            <div class="card card-dark text-light p-4 h-100">
                <h1 class="h4 mb-3">
                    <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>
                </h1>

                <table class="table table-dark table-borderless mb-3">
                    <tbody>
                        <tr>
                            <td class="text-secondary fw-semibold">Benutzername</td>
                            <td><?= htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <td class="text-secondary fw-semibold">E-Mail</td>
                            <td><?= htmlspecialchars($userData['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Über mich / Bio -->
                <div class="mb-3">
                    <p class="text-secondary fw-semibold mb-1"><i class="bi bi-card-text me-1"></i>Über mich</p>
                    <div id="bioDisplay" class="text-light small">
                        <?php if (!empty($userData['bio'])): ?>
                            <?= nl2br(htmlspecialchars($userData['bio'], ENT_QUOTES, 'UTF-8')) ?>
                        <?php else: ?>
                            <span class="text-secondary fst-italic">Noch keine Beschreibung vorhanden.</span>
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm mt-2"
                            id="bioEditBtn" type="button"
                            data-bs-toggle="collapse" data-bs-target="#bioEditForm"
                            aria-expanded="false" aria-controls="bioEditForm">
                        <i class="bi bi-pencil me-1"></i> Beschreibung bearbeiten
                    </button>
                </div>

                <!-- Bio-Bearbeitungsformular (Bootstrap Collapse) -->
                <div class="collapse mb-3" id="bioEditForm">
                    <div id="bioSuccessAlert" class="alert alert-success d-none py-1 small" role="alert"></div>
                    <div id="bioErrorAlert"   class="alert alert-danger  d-none py-1 small" role="alert"></div>
                    <textarea id="bioTextarea"
                              class="form-control bg-dark text-light border-secondary"
                              rows="4" maxlength="1000"
                              placeholder="Beschreibe dich kurz (max. 1000 Zeichen)…"><?= htmlspecialchars($userData['bio'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <small id="bioCharCount" class="text-secondary">0 / 1000</small>
                        <button id="bioSaveBtn" class="btn btn-primary btn-sm">
                            <i class="bi bi-check2 me-1"></i> Speichern
                        </button>
                    </div>
                </div>

                <!-- Dropdown: Kontodaten ändern -->
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle w-100"
                            type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-pencil me-1"></i> Kontodaten ändern
                    </button>
                    <ul class="dropdown-menu dropdown-menu-dark w-100">
                        <li><a class="dropdown-item" href="/public/php/change_username.php">Benutzername</a></li>
                        <li><a class="dropdown-item" href="/public/php/change_email.php">E-Mail</a></li>
                        <li><a class="dropdown-item" href="/public/php/change_password.php">Passwort</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ====================================================
             Karte: Meine Spots
        ==================================================== -->
        <div class="col-12 col-md-7">
            <div class="card card-dark text-light p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h4 mb-0"><i class="bi bi-pin-map-fill me-1"></i> Meine Spots</h2>
                    <span class="badge bg-primary"><?= count($mySpots) ?></span>
                </div>

                <?php if (empty($mySpots)): ?>
                    <p class="text-secondary">
                        Du hast noch keine Spots erstellt.
                        <a href="/" class="text-success">Zur Karte</a>
                        und ersten Spot anlegen!
                    </p>
                <?php else: ?>
                    <!-- Feedback-Alerts für JS-Aktionen -->
                    <div id="dashSuccessAlert" class="alert alert-success d-none" role="alert"></div>
                    <div id="dashErrorAlert"   class="alert alert-danger  d-none" role="alert"></div>

                    <div class="list-group list-group-flush">
                        <?php
                        $diffBadgeClass = [
                            'Anfänger'      => 'text-bg-success',
                            'Mittel'        => 'text-bg-warning',
                            'Fortgeschritten' => 'text-bg-danger',
                            'Profi'         => 'text-bg-dark',
                        ];
                        foreach ($mySpots as $spot):
                            $createdDate = date('d.m.Y', strtotime($spot['created_at']));
                            $badgeClass  = $diffBadgeClass[$spot['difficulty']] ?? 'text-bg-secondary';
                        ?>
                        <div class="list-group-item bg-transparent border-secondary spot-row py-3"
                             id="spot-row-<?= $spot['id'] ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1 me-3">
                                    <p class="mb-1 fw-semibold text-light">
                                        <?= htmlspecialchars($spot['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <small class="text-secondary">
                                        <?= htmlspecialchars($spot['spot_type'], ENT_QUOTES, 'UTF-8') ?>
                                        &bull;
                                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($spot['difficulty'], ENT_QUOTES, 'UTF-8') ?></span>
                                        &bull; <?= $createdDate ?>
                                    </small>
                                </div>
                                <div class="d-flex gap-2 flex-shrink-0">
                                    <a href="/?spot=<?= $spot['id'] ?>"
                                       class="btn btn-outline-light btn-sm"
                                       title="Auf der Karte anzeigen">
                                        <i class="bi bi-map"></i>
                                    </a>
                                    <a href="/public/php/edit_spot.php?id=<?= $spot['id'] ?>"
                                       class="btn btn-outline-primary btn-sm"
                                       title="Spot bearbeiten">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button class="btn btn-danger btn-sm"
                                            title="Spot löschen"
                                            onclick="dashDeleteSpot(<?= $spot['id'] ?>, this)">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.row -->
</main><!-- /.container -->

<!-- ============================================================
     CSRF-Token für AJAX-Lösch-Anfragen
============================================================ -->
<meta name="app-csrf-token" content="<?= $csrfToken ?>">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

<script>
'use strict';

const CSRF = document.querySelector('meta[name="app-csrf-token"]').content;

/** Spot vom Dashboard aus löschen */
async function dashDeleteSpot(spotId, btn) {
    if (!confirm('Diesen Spot wirklich löschen?')) return;

    btn.disabled = true;

    const fd = new FormData();
    fd.set('csrf_token', CSRF);
    fd.set('_method', 'DELETE');

    try {
        const res  = await fetch(`/public/php/api/spot.php?id=${encodeURIComponent(spotId)}`, {
            method: 'POST', body: fd,
        });
        const data = await res.json();

        if (!res.ok) {
            showDash('dashErrorAlert', data.error ?? 'Fehler beim Löschen.');
            btn.disabled = false;
            return;
        }

        // Zeile aus der Liste entfernen
        const row = document.getElementById(`spot-row-${spotId}`);
        if (row) row.remove();
        showDash('dashSuccessAlert', 'Spot erfolgreich gelöscht.');

        // Zähler aktualisieren
        const badge = document.querySelector('.badge.bg-primary');
        if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent, 10) - 1);

    } catch {
        showDash('dashErrorAlert', 'Netzwerkfehler. Bitte erneut versuchen.');
        btn.disabled = false;
    }
}

function showDash(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('d-none');
    setTimeout(() => el.classList.add('d-none'), 4000);
}

// ---------------------------------------------------------------
// Bio bearbeiten
// ---------------------------------------------------------------
const bioTextarea = document.getElementById('bioTextarea');
const bioCharCount = document.getElementById('bioCharCount');

function updateBioCount() {
    bioCharCount.textContent = bioTextarea.value.length + ' / 1000';
}
updateBioCount();
bioTextarea.addEventListener('input', updateBioCount);

document.getElementById('bioSaveBtn').addEventListener('click', async () => {
    const bio = bioTextarea.value.trim();
    if (bio.length < 1) {
        showBioAlert('error', 'Die Beschreibung darf nicht leer sein.');
        return;
    }

    const saveBtn = document.getElementById('bioSaveBtn');
    saveBtn.disabled = true;

    try {
        const res  = await fetch('/public/php/api/update_bio.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bio, csrf_token: CSRF }),
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('bioDisplay').innerHTML =
                bio.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
            showBioAlert('success', 'Beschreibung gespeichert.');
        } else {
            showBioAlert('error', data.error || 'Fehler beim Speichern.');
        }
    } catch {
        showBioAlert('error', 'Netzwerkfehler. Bitte erneut versuchen.');
    } finally {
        saveBtn.disabled = false;
    }
});

function showBioAlert(type, msg) {
    const s = document.getElementById('bioSuccessAlert');
    const e = document.getElementById('bioErrorAlert');
    s.classList.add('d-none');
    e.classList.add('d-none');
    if (type === 'success') { s.textContent = msg; s.classList.remove('d-none'); }
    else                    { e.textContent = msg; e.classList.remove('d-none'); }
}
</script>

</body>
</html>
