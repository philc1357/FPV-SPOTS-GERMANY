<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Forum-Beitrag bearbeiten
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';
require_once __DIR__ . '/../../../private/php/core/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /forum.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId    = (int)$_SESSION['user_id'];
$csrfToken = $_SESSION['csrf_token'];

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    header('Location: /forum.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, user_id, title, body FROM forum_posts WHERE id = ?");
$stmt->execute([$postId]);
$post = $stmt->fetch();

if (!$post || (int)$post['user_id'] !== $userId) {
    header('Location: /forum.php');
    exit;
}

$imgStmt = $pdo->prepare("SELECT id, filename FROM forum_post_images WHERE post_id = ? ORDER BY id ASC");
$imgStmt->execute([$postId]);
$existingImages = $imgStmt->fetchAll();

$forumError = $_SESSION['forum_error'] ?? '';
unset($_SESSION['forum_error']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beitrag bearbeiten – Forum – FPV Spots Germany</title>
    <meta name="description" content="Bearbeite deinen Forum-Beitrag.">
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/forum.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1 class="h3 mb-0">Beitrag bearbeiten</h1>
                <a href="/forum.php#post-<?= (int)$post['id'] ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Zurück zum Forum
                </a>
            </div>

            <?php if ($forumError !== ''): ?>
                <div class="alert forum-error mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?= htmlspecialchars($forumError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <section class="card card-dark text-light p-4">
                <form method="POST" action="/private/php/forum/forum_post_update_submit.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">

                    <div class="mb-3">
                        <label for="forum-title" class="form-label small text-secondary">Überschrift</label>
                        <input type="text" id="forum-title" name="title"
                               class="form-control bg-secondary text-light border-0"
                               minlength="3" maxlength="150" required
                               value="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="mb-3">
                        <label for="forum-body" class="form-label small text-secondary">Beitrag</label>
                        <textarea id="forum-body" name="body"
                                  class="form-control bg-secondary text-light border-0"
                                  rows="6" minlength="10" maxlength="5000" required><?= htmlspecialchars($post['body'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <?php if (!empty($existingImages)): ?>
                        <div class="mb-3">
                            <label class="form-label small text-secondary">Vorhandene Bilder (<?= count($existingImages) ?> / 4) – zum Entfernen markieren</label>
                            <div class="forum-edit-image-grid">
                                <?php foreach ($existingImages as $img):
                                    $iid = (int)$img['id'];
                                    $src = '/public/uploads/forum/' . htmlspecialchars($img['filename'], ENT_QUOTES, 'UTF-8');
                                ?>
                                    <label class="forum-edit-image-tile" for="rm-<?= $iid ?>">
                                        <img src="<?= $src ?>" alt="Beitragsbild">
                                        <span class="forum-edit-remove">
                                            <input type="checkbox" id="rm-<?= $iid ?>" name="remove_image_ids[]" value="<?= $iid ?>">
                                            <i class="bi bi-trash3"></i>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="forum-new-photos" class="form-label small text-secondary">
                            Neue Bilder hinzufügen (max. 4 Bilder gesamt, je 5 MB, JPG/PNG)
                        </label>
                        <input type="file" id="forum-new-photos" name="new_photos[]"
                               class="form-control bg-secondary text-light border-0"
                               accept="image/jpeg,image/png" multiple>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="/forum.php#post-<?= (int)$post['id'] ?>" class="btn btn-outline-secondary btn-sm">Abbrechen</a>
                        <button type="submit" class="btn btn-success btn-sm">Änderungen speichern</button>
                    </div>
                </form>
            </section>

        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<script>
document.querySelectorAll('.forum-edit-image-tile input[type="checkbox"]').forEach(function (cb) {
    var tile = cb.closest('.forum-edit-image-tile');
    cb.addEventListener('change', function () {
        tile.classList.toggle('is-removing', cb.checked);
    });
});
</script>
</body>
</html>
