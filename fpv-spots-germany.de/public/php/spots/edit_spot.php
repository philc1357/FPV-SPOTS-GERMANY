<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Spot bearbeiten
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../../private/php/core/db.php';

$userId    = (int)$_SESSION['user_id'];
$username  = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');$csrfToken = $_SESSION['csrf_token'];
$isAdmin   = !empty($_SESSION['is_admin']);

// Spot-ID aus URL
$spotId = (int)($_GET['id'] ?? 0);
if ($spotId <= 0) {
    header('Location: /public/php/dashboard.php');
    exit;
}

// Spot laden
$stmt = $pdo->prepare("SELECT id, user_id, name, description, spot_type, difficulty, parking_info, copter_size FROM spots WHERE id = ?");
$stmt->execute([$spotId]);
$spot = $stmt->fetch();

if (!$spot || ((int)$spot['user_id'] !== $userId && !$isAdmin)) {
    header('Location: /public/php/dashboard.php');
    exit;
}

$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spot bearbeiten – FPV Spots Germany</title>
    <meta name="description" content="Bearbeite deinen FPV-Spot.">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card card-dark text-light p-4">
                <h1 class="h4 mb-4">Spot bearbeiten</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">Spot erfolgreich aktualisiert!</div>
                <?php endif; ?>

                <form method="POST" action="/private/php/spots/edit_spot_submit.php">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="spot_id" value="<?= $spot['id'] ?>">

                    <div class="mb-3">
                        <label for="editName" class="form-label small fw-semibold">Spot-Name *</label>
                        <input type="text" id="editName" name="name"
                               class="form-control bg-secondary text-light border-0"
                               value="<?= htmlspecialchars($spot['name'], ENT_QUOTES, 'UTF-8') ?>"
                               maxlength="100" required>
                    </div>

                    <div class="mb-3">
                        <label for="editDesc" class="form-label small fw-semibold">Beschreibung *</label>
                        <textarea id="editDesc" name="description"
                                  class="form-control bg-secondary text-light border-0"
                                  rows="3" maxlength="2000"><?= htmlspecialchars($spot['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="editType" class="form-label small fw-semibold">Typ *</label>
                            <select id="editType" name="spot_type"
                                    class="form-select bg-secondary text-light border-0" required>
                                <?php foreach (['Bando','Feld','Gebirge','Park','Wald','Windpark','Sonstige'] as $t): ?>
                                    <option value="<?= $t ?>" <?= $spot['spot_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="editDiff" class="form-label small fw-semibold">Schwierigkeit *</label>
                            <select id="editDiff" name="difficulty"
                                    class="form-select bg-secondary text-light border-0" required>
                                <?php foreach (['Anfänger','Mittel','Fortgeschritten','Profi'] as $d): ?>
                                    <option value="<?= $d ?>" <?= $spot['difficulty'] === $d ? 'selected' : '' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php
                            $selectedSizes = !empty($spot['copter_size'])
                                ? explode(',', $spot['copter_size'])
                                : [];
                        ?>
                        <label class="form-label small fw-semibold">Coptergröße <span class="text-secondary fw-normal">(Mehrfachauswahl)</span></label>
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary dropdown-toggle w-100 text-start"
                                    type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                    aria-expanded="false" id="editCopterBtn">
                                <?= !empty($selectedSizes) ? htmlspecialchars(implode(', ', $selectedSizes), ENT_QUOTES, 'UTF-8') : 'Alle Größen wählen…' ?>
                            </button>
                            <ul class="dropdown-menu bg-secondary w-100 px-4 py-1" aria-labelledby="editCopterBtn">
                                <?php foreach (['Tinywhoop', '2-3 Zoll', '3-5 Zoll', '5+ Zoll'] as $cs):
                                    $csId = 'cs_edit_' . preg_replace('/[^a-z0-9]/i', '_', $cs);
                                ?>
                                <li class="form-check px-2 py-1">
                                    <input class="form-check-input edit-copter-check" type="checkbox"
                                           name="copter_size[]"
                                           value="<?= htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') ?>"
                                           id="<?= $csId ?>"
                                           <?= in_array($cs, $selectedSizes, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-light" for="<?= $csId ?>"><?= htmlspecialchars($cs, ENT_QUOTES, 'UTF-8') ?></label>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Parkmöglichkeit</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="editParkingUnknown"
                                   <?= ($spot['parking_info'] ?? 'Unbekannt') === 'Unbekannt' ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="editParkingUnknown">Unbekannt</label>
                        </div>
                        <textarea id="editParkingInfo" name="parking_info"
                                  class="form-control bg-secondary text-light border-0"
                                  placeholder="z. B. Kostenloser Parkplatz direkt am Feld…"
                                  rows="2" maxlength="500"
                                  <?= ($spot['parking_info'] ?? 'Unbekannt') === 'Unbekannt' ? 'disabled' : '' ?>><?= ($spot['parking_info'] ?? 'Unbekannt') !== 'Unbekannt' ? htmlspecialchars($spot['parking_info'], ENT_QUOTES, 'UTF-8') : '' ?></textarea>
                    </div>


                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        Änderungen speichern
                    </button>
                </form>

                <a href="/public/php/spot_detail.php?id=<?= $spot['id'] ?>" class="btn btn-outline-light w-100 mt-3">Zur Spot-Detailseite</a>
                <a href="/public/php/dashboard.php" class="btn btn-outline-secondary w-100 mt-2">Zum Dashboard</a>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<script>
(function () {
    var checks = document.querySelectorAll('.edit-copter-check');
    var btn = document.getElementById('editCopterBtn');
    if (!btn) return;
    checks.forEach(function (cb) {
        cb.addEventListener('change', function () {
            var selected = Array.from(checks).filter(c => c.checked).map(c => c.value);
            btn.textContent = selected.length ? selected.join(', ') : 'Alle Größen wählen…';
        });
    });
})();
document.getElementById('editParkingUnknown').addEventListener('change', function () {
    var ta = document.getElementById('editParkingInfo');
    if (this.checked) {
        ta.disabled = true;
        ta.value = '';
    } else {
        ta.disabled = false;
        ta.focus();
    }
});
</script>

</body>
</html>
