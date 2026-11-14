<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Website-Updates / Changelog
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

require_once __DIR__ . '/../../../private/php/core/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin    = !empty($_SESSION['is_admin']);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];

// Alle Update-Posts laden (neueste zuerst)
$stmt = $pdo->query(
    "SELECT id, title, description, created_at
     FROM updates
     ORDER BY created_at DESC"
);
$updates = $stmt->fetchAll();

$success = isset($_GET['success']);
$error   = isset($_GET['error']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Updates – FPV Spots Germany</title>
    <meta name="description" content="Alle Änderungen und Neuerungen auf FPV Spots Germany im Überblick.">
    <meta name="robots" content="index, follow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="<?= asset('/public/css/updates.css') ?>">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">

            <!-- ====================================================
                 Seitenüberschrift
            ==================================================== -->
            <?php
                $pageTitle  = 'Website-Updates';
                $pageKicker = 'Changelog';
                $pageIcon   = 'bi-arrow-repeat';
                $pageLead   = 'Alle Änderungen und Neuerungen auf FPV Spots Germany. Damit die Änderungen in eurer Web-App sichtbar werden, müsst ihr die App neu installieren.';
                include __DIR__ . '/../../includes/page_head.php';
            ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">Update erfolgreich veröffentlicht.</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">Fehler beim Erstellen des Updates. Bitte erneut versuchen.</div>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <!-- ====================================================
                 Formular: Neuen Post erstellen (nur Admin)
            ==================================================== -->
            <section class="card card-dark text-light p-4 mb-4" aria-label="Neuen Update-Post erstellen">
                <h2 class="h5 mb-3"><i class="bi bi-plus-circle me-1"></i> Neuen Update-Post erstellen</h2>
                <form method="POST" action="/private/php/admin/update_submit.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="mb-3">
                        <label for="update-title" class="form-label">Titel</label>
                        <input type="text"
                               id="update-title"
                               name="title"
                               class="form-control"
                               maxlength="255"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="update-description" class="form-label">Beschreibung</label>
                        <textarea id="update-description"
                                  name="description"
                                  class="form-control"
                                  rows="5"
                                  required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Veröffentlichen</button>
                </form>
            </section>
            <?php endif; ?>

            <!-- ====================================================
                 Update-Posts – jedes Update als eigene Karte
            ==================================================== -->
            <section>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Alle Updates</h2>
                    <span class="badge bg-primary"><?= count($updates) ?></span>
                </div>

            <?php if (empty($updates)): ?>
                <p class="text-secondary">Noch keine Updates vorhanden.</p>
            <?php else: ?>
                <?php foreach ($updates as $update):
                    $dateFormatted = date('d.m.Y', strtotime($update['created_at']));
                    $dateIso       = date('Y-m-d', strtotime($update['created_at']));
                ?>
                <article class="card card-dark text-light p-4 mb-4 update-card">
                    <header class="d-flex justify-content-between align-items-start mb-2">
                        <h3 class="h5 mb-0 update-title"><?= htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <time datetime="<?= htmlspecialchars($dateIso, ENT_QUOTES, 'UTF-8') ?>"
                              class="text-secondary small text-nowrap ms-3">
                            <?= htmlspecialchars($dateFormatted, ENT_QUOTES, 'UTF-8') ?>
                        </time>
                    </header>
                    <p class="mb-0 text-light update-body"><?= htmlspecialchars($update['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
            </section>

        </div>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>

</body>
</html>
