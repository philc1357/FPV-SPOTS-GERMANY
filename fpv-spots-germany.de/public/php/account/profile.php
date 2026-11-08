<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Öffentliches Benutzerprofil
// Nur für eingeloggte Benutzer aufrufbar.
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

// Eingeloggt-Check zuerst (Profile nur für eingeloggte Benutzer)
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// CSRF-Token für Header-Formulare (z. B. Logout)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

require_once __DIR__ . '/../../../private/php/core/db.php';

// Profil-ID aus URL validieren
$profileId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if (!$profileId) {
    header('Location: /');
    exit;
}

// Eigenes Profil → auf Dashboard umleiten
if ($profileId === (int)$_SESSION['user_id']) {
    header('Location: /dashboard.php');
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$isLoggedIn = true;

// Profil-Daten laden (nur öffentlich sichtbare Felder)
$stmt = $pdo->prepare("SELECT username, bio, created_at FROM users WHERE id = ?");
$stmt->execute([$profileId]);
$profile = $stmt->fetch();

if (!$profile) {
    header('Location: /');
    exit;
}

// Spots des Profilbenutzers laden
$stmt = $pdo->prepare(
    "SELECT id, name, spot_type, difficulty, latitude, longitude, created_at
     FROM spots
     WHERE user_id = ?
     ORDER BY created_at DESC"
);
$stmt->execute([$profileId]);
$userSpots = $stmt->fetchAll();

$profileUsername = htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8');
$memberSince     = date('m.Y', strtotime($profile['created_at']));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil von <?= $profileUsername ?> – FPV Spots Germany</title>
    <meta name="description" content="Öffentliches Profil von <?= $profileUsername ?> auf FPV Spots Germany.">
    <meta name="robots" content="noindex, nofollow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/map_assets_head.php'; ?>
    <meta name="app-csrf-token" content="<?= $csrfToken ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container py-4">
    <div class="row g-4">

        <!-- ====================================================
             Karte: Öffentliche Profil-Infos
        ==================================================== -->
        <div class="col-12">
            <article class="card card-dark text-light p-4 h-100">
                <h1 class="h4 mb-3">
                    <i class="bi bi-person-fill me-1"></i><?= $profileUsername ?>
                    <button class="btn btn-outline-success btn-sm ms-2"
                            data-bs-toggle="modal" data-bs-target="#sendMessageModal"
                            title="Nachricht senden">
                        <i class="bi bi-envelope-fill"></i>
                    </button>
                </h1>

                <table class="table table-dark table-borderless mb-3">
                    <tbody>
                        <tr>
                            <td class="text-secondary fw-semibold">Benutzername</td>
                            <td><?= $profileUsername ?></td>
                        </tr>
                        <tr>
                            <td class="text-secondary fw-semibold">Mitglied seit</td>
                            <td><?= htmlspecialchars($memberSince, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Über mich / Bio (read-only) -->
                <div class="mb-0">
                    <p class="text-secondary fw-semibold mb-1">
                        <i class="bi bi-card-text me-1"></i>Über mich
                    </p>
                    <div class="text-light small">
                        <?php if (!empty($profile['bio'])): ?>
                            <?= nl2br(htmlspecialchars($profile['bio'], ENT_QUOTES, 'UTF-8')) ?>
                        <?php else: ?>
                            <span class="text-secondary fst-italic">Keine Beschreibung vorhanden.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        </div>

        <!-- ====================================================
             Mini-Karte: Spots dieses Benutzers
        ==================================================== -->
        <div class="col-12">
            <section class="card card-dark text-light p-0 overflow-hidden position-relative" aria-label="Karte der Spots von <?= $profileUsername ?>">
                <h2 class="h5 px-4 pt-3 pb-3 mb-0">
                    <i class="bi bi-pin-map-fill me-1"></i>Spots von <?= $profileUsername ?>
                </h2>
                <div id="profileMap" style="height:360px;width:100%;border-radius:0 0 .375rem .375rem;"></div>

                <!-- Spot-Detail-Overlay (überdeckt die Karte) -->
                <div id="mapOverlay" style="display:none;position:absolute;left:0;right:0;bottom:0;height:360px;background:#1e1e2e;overflow-y:auto;z-index:1000;">
                    <div class="d-flex justify-content-between align-items-start p-3 border-bottom border-secondary">
                        <h3 class="h6 mb-0 fw-semibold text-light" id="mapOverlayTitle"></h3>
                        <button type="button" class="btn-close btn-close-white ms-3 flex-shrink-0" id="mapOverlayClose" aria-label="Schließen"></button>
                    </div>
                    <div class="p-3" id="mapOverlayBody"></div>
                </div>
            </section>
        </div>

        <!-- ====================================================
             Liste: Spots dieses Benutzers
        ==================================================== -->
        <div class="col-12">
            <section class="card card-dark text-light p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">
                        <i class="bi bi-pin-map-fill me-1"></i>
                        Listenansicht
                    </h2>
                    <span class="badge bg-primary"><?= count($userSpots) ?></span>
                </div>

                <?php if (empty($userSpots)): ?>
                    <p class="text-secondary mb-0">
                        <?= $profileUsername ?> hat noch keine Spots erstellt.
                    </p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php
                        $diffBadgeClass = [
                            'Anfänger'        => 'text-bg-success',
                            'Mittel'          => 'text-bg-warning',
                            'Fortgeschritten' => 'text-bg-danger',
                            'Profi'           => 'text-bg-dark',
                        ];
                        foreach ($userSpots as $spot):
                            $createdDate = date('d.m.Y', strtotime($spot['created_at']));
                            $badgeClass  = $diffBadgeClass[$spot['difficulty']] ?? 'text-bg-secondary';
                            $spotId      = (int)$spot['id'];
                        ?>
                        <div class="list-group-item bg-transparent border-secondary py-3">
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
                                    <a href="/?spot=<?= $spotId ?>"
                                       class="btn btn-outline-light btn-sm"
                                       title="Auf der Karte anzeigen">
                                        <i class="bi bi-map"></i>
                                    </a>
                                    <a href="/public/php/spot_detail.php?id=<?= $spotId ?>"
                                       class="btn btn-outline-primary btn-sm"
                                       title="Spot-Details anzeigen">
                                        <i class="bi bi-info-circle"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

    </div><!-- /.row -->
</main><!-- /.container -->

<!-- Nachricht senden Modal -->
<div class="modal fade" id="sendMessageModal" tabindex="-1" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="sendMessageModalLabel">
                    <i class="bi bi-envelope-fill me-1"></i>Nachricht an <?= $profileUsername ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <div id="msgSuccessAlert" class="alert alert-success d-none py-2 small"></div>
                <div id="msgErrorAlert" class="alert alert-danger d-none py-2 small"></div>
                <textarea id="msgBody" class="form-control bg-secondary text-light border-0"
                          rows="4" maxlength="2000"
                          placeholder="Deine Nachricht..."></textarea>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <small id="msgCharCount" class="text-secondary">0 / 2000</small>
                    <button id="msgSendBtn" class="btn btn-success btn-sm">
                        <i class="bi bi-send-fill me-1"></i>Senden
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/map_assets_foot.php'; ?>

<script src="<?= asset('/public/js/pages/profile_spots.js') ?>" data-profile-id="<?= (int)$profileId ?>"></script>

<script src="<?= asset('/public/js/pages/profile_message.js') ?>" data-profile-id="<?= (int)$profileId ?>"></script>
</body>
</html>
