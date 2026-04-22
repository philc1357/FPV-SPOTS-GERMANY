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

if ($isLoggedIn && !empty($updates)) {
    setcookie('last_seen_update', $updates[0]['created_at'], [
        'expires'  => time() + 365 * 86400,
        'path'     => '/',
        'httponly'  => true,
        'secure'   => true,
        'samesite' => 'Lax',
    ]);
}

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/updates.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">

            <!-- ====================================================
                 Seitenüberschrift
            ==================================================== -->
            <div class="card card-dark text-light p-4 mb-4">
                <h1 class="h3 mb-1"><i class="bi bi-arrow-repeat me-2"></i>Website-Updates</h1>
                <p class="text-secondary mb-0">Alle Änderungen und Neuerungen auf FPV Spots Germany.</p>
                <p class="text-secondary mb-0">Damit die Änderungen in eurer Web-App sichtbar werden müsst ihr die App neu installieren.</p>
            </div>

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
                               class="form-control bg-dark text-light border-secondary"
                               maxlength="255"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="update-description" class="form-label">Beschreibung</label>
                        <textarea id="update-description"
                                  name="description"
                                  class="form-control bg-dark text-light border-secondary"
                                  rows="5"
                                  required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Veröffentlichen</button>
                </form>
            </section>
            <?php endif; ?>

            <!-- ====================================================
                 Update-Posts
            ==================================================== -->
            <?php if (empty($updates)): ?>
                <p class="text-secondary text-center py-4">Noch keine Updates vorhanden.</p>
            <?php else: ?>
                <?php foreach ($updates as $update):
                    $dateFormatted = date('d.m.Y', strtotime($update['created_at']));
                    $dateIso       = date('Y-m-d', strtotime($update['created_at']));
                ?>
                <article class="card card-dark text-light p-4 mb-3 update-card">
                    <header class="d-flex justify-content-between align-items-start mb-2">
                        <h2 class="h5 mb-0"><?= htmlspecialchars($update['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <time datetime="<?= htmlspecialchars($dateIso, ENT_QUOTES, 'UTF-8') ?>"
                              class="text-secondary small text-nowrap ms-3">
                            <?= htmlspecialchars($dateFormatted, ENT_QUOTES, 'UTF-8') ?>
                        </time>
                    </header>
                    <p class="mb-0 text-light" style="white-space: pre-wrap;"><?= htmlspecialchars($update['description'], ENT_QUOTES, 'UTF-8') ?></p>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

</body>
</html>
