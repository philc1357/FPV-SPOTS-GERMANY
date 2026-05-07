<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Forum
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';
require_once __DIR__ . '/../../../private/php/core/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = (int)($_SESSION['user_id'] ?? 0);
$csrfToken  = $_SESSION['csrf_token'];

$forumError   = $_SESSION['forum_error']   ?? '';
$forumSuccess = $_SESSION['forum_success'] ?? '';
unset($_SESSION['forum_error'], $_SESSION['forum_success']);

// Beiträge laden – neueste zuerst
$stmt = $pdo->query(
    "SELECT p.id, p.user_id, p.title, p.body, p.created_at, u.username
     FROM forum_posts p
     JOIN users u ON p.user_id = u.id
     ORDER BY p.created_at DESC"
);
$posts = $stmt->fetchAll();

$images   = [];
$comments = [];
if (!empty($posts)) {
    $ids = array_map('intval', array_column($posts, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $iStmt = $pdo->prepare(
        "SELECT id, post_id, filename FROM forum_post_images
         WHERE post_id IN ($placeholders) ORDER BY id ASC"
    );
    $iStmt->execute($ids);
    foreach ($iStmt->fetchAll() as $img) {
        $images[(int)$img['post_id']][] = $img;
    }

    $cStmt = $pdo->prepare(
        "SELECT c.id, c.post_id, c.user_id, c.parent_id, c.body, c.created_at, u.username
         FROM forum_comments c
         JOIN users u ON c.user_id = u.id
         WHERE c.post_id IN ($placeholders)
         ORDER BY c.created_at ASC"
    );
    $cStmt->execute($ids);
    $rawComments = $cStmt->fetchAll();
    // In Top-Level + Replies aufteilen (max. 2 Ebenen)
    $repliesByParent = [];
    foreach ($rawComments as $c) {
        if ($c['parent_id'] !== null) {
            $repliesByParent[(int)$c['parent_id']][] = $c;
        }
    }
    foreach ($rawComments as $c) {
        if ($c['parent_id'] === null) {
            $cWithReplies = $c;
            $cWithReplies['replies'] = $repliesByParent[(int)$c['id']] ?? [];
            $comments[(int)$c['post_id']][] = $cWithReplies;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum – FPV Spots Germany</title>
    <meta name="description" content="Tausche dich mit der FPV-Community aus: Diskussionen, Tipps und Erfahrungen rund um FPV-Spots in Deutschland.">
    <meta name="robots" content="index, follow">
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

            <!-- Einleitung -->
            <header class="card card-dark text-light p-4 mb-4">
                <h1 class="h3 mb-2">Forum</h1>
                <p class="text-secondary mb-0">
                    Diskutiere mit der <span>FPV-Community</span>: Stelle Fragen, teile Erfahrungen oder zeige deine Lieblingsspots.
                </p>
            </header>

            <?php if ($forumError !== ''): ?>
                <div class="alert forum-error mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?= htmlspecialchars($forumError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($forumSuccess !== ''): ?>
                <div class="alert alert-success mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <?= htmlspecialchars($forumSuccess, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <!-- Neuen Beitrag erstellen -->
            <section class="card card-dark text-light p-4 mb-4">
                <h2 class="h5 mb-3">Neuen Beitrag erstellen</h2>
                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/forum/forum_post_submit.php" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="mb-2">
                            <label for="forum-title" class="form-label small text-secondary">Überschrift</label>
                            <input type="text" id="forum-title" name="title"
                                   class="form-control bg-secondary text-light border-0"
                                   minlength="3" maxlength="150" required>
                        </div>
                        <div class="mb-2">
                            <label for="forum-body" class="form-label small text-secondary">Beitrag</label>
                            <textarea id="forum-body" name="body"
                                      class="form-control bg-secondary text-light border-0"
                                      rows="5" minlength="10" maxlength="5000" required></textarea>
                        </div>
                        <div class="mb-2">
                            <label for="forum-photos" class="form-label small text-secondary">Bilder (max. 4, je 5 MB, JPG/PNG)</label>
                            <input type="file" id="forum-photos" name="photos[]"
                                   class="form-control bg-secondary text-light border-0"
                                   accept="image/jpeg,image/png" multiple>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-success btn-sm">Beitrag veröffentlichen</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-secondary mb-0">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="text-success">Einloggen</a>,
                        um einen Beitrag zu erstellen.
                    </p>
                <?php endif; ?>
            </section>

            <!-- Beiträge -->
            <section>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Beiträge</h2>
                    <span class="badge bg-primary"><?= count($posts) ?></span>
                </div>

                <?php if (empty($posts)): ?>
                    <p class="text-secondary">Noch keine Beiträge. Sei der Erste!</p>
                <?php else: ?>
                    <?php foreach ($posts as $p):
                        $pid       = (int)$p['id'];
                        $pDate     = date('d.m.Y H:i', strtotime($p['created_at']));
                        $pImages   = $images[$pid]   ?? [];
                        $pComments = $comments[$pid] ?? [];
                    ?>
                    <article class="card card-dark text-light p-4 mb-4 forum-post" id="post-<?= $pid ?>">
                        <header class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <h3 class="h5 mb-0 forum-post-title"><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="d-flex gap-1">
                                <?php if ($isLoggedIn && $userId === (int)$p['user_id']): ?>
                                    <a href="/forum_edit.php?id=<?= $pid ?>"
                                       class="btn btn-outline-secondary btn-sm py-0 px-1"
                                       title="Beitrag bearbeiten"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                                <?php if (!empty($_SESSION['is_admin'])): ?>
                                    <form method="POST" action="/private/php/forum/forum_post_delete_submit.php"
                                          class="d-inline"
                                          onsubmit="return confirm('Beitrag wirklich löschen? Alle Kommentare und Bilder werden mitgelöscht.')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="post_id" value="<?= $pid ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"
                                                title="Beitrag löschen"><i class="bi bi-trash3"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </header>
                        
                        <div class="forum-post-body"><?= htmlspecialchars($p['body'], ENT_QUOTES, 'UTF-8') ?></div>

                        <p class="text-secondary small mb-2">
                            <i class="bi bi-person-fill"></i>
                            <a href="/profile.php?id=<?= (int)$p['user_id'] ?>"
                               class="text-info text-decoration-none"
                               title="Profil ansehen"><?= htmlspecialchars($p['username'], ENT_QUOTES, 'UTF-8') ?></a>
                            &bull; <i class="bi bi-calendar3"></i> <?= $pDate ?>
                        </p>


                        <?php if (!empty($pImages)): ?>
                            <div class="forum-image-grid">
                                <?php foreach ($pImages as $img):
                                    $src = '/public/uploads/forum/' . htmlspecialchars($img['filename'], ENT_QUOTES, 'UTF-8');
                                ?>
                                    <img class="forum-image-thumb"
                                         src="<?= $src ?>"
                                         alt="Beitragsbild"
                                         data-bs-toggle="modal"
                                         data-bs-target="#photoModal"
                                         data-img-src="<?= $src ?>"
                                         data-img-alt="Beitragsbild">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Kommentare -->
                        <div class="forum-comments">
                            <?php
                                $commentTotal = 0;
                                foreach ($pComments as $cTmp) {
                                    $commentTotal += 1 + count($cTmp['replies'] ?? []);
                                }
                            ?>
                            <h4 class="h6 mb-2 text-secondary">
                                <i class="bi bi-chat-left-text"></i>
                                Kommentare (<?= $commentTotal ?>)
                            </h4>

                            <?php foreach ($pComments as $c):
                                $cId      = (int)$c['id'];
                                $cDate    = date('d.m.Y H:i', strtotime($c['created_at']));
                                $cReplies = $c['replies'] ?? [];
                            ?>
                                <div class="forum-comment" id="comment-<?= $cId ?>">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <small class="text-secondary">
                                            <i class="bi bi-person-fill"></i>
                                            <a href="/profile.php?id=<?= (int)$c['user_id'] ?>"
                                               class="text-info text-decoration-none"><?= htmlspecialchars($c['username'], ENT_QUOTES, 'UTF-8') ?></a>
                                            &bull; <?= $cDate ?>
                                        </small>
                                        <?php if (!empty($_SESSION['is_admin'])): ?>
                                            <form method="POST" action="/private/php/forum/forum_comment_delete_submit.php"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Kommentar (und ggf. Antworten) wirklich löschen?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="comment_id" value="<?= $cId ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"
                                                        title="Kommentar löschen"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                    <div class="forum-comment-body mt-1"><?= htmlspecialchars($c['body'], ENT_QUOTES, 'UTF-8') ?></div>

                                    <?php if ($isLoggedIn): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary mt-1 forum-reply-toggle"
                                                data-reply-target="reply-form-<?= $cId ?>">
                                            <i class="bi bi-reply"></i> Antworten
                                        </button>
                                    <?php endif; ?>

                                    <?php if (!empty($cReplies)): ?>
                                        <div class="forum-replies">
                                            <?php foreach ($cReplies as $r):
                                                $rId   = (int)$r['id'];
                                                $rDate = date('d.m.Y H:i', strtotime($r['created_at']));
                                            ?>
                                                <div class="forum-comment forum-reply" id="comment-<?= $rId ?>">
                                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                                        <small class="text-secondary">
                                                            <i class="bi bi-person-fill"></i>
                                                            <a href="/profile.php?id=<?= (int)$r['user_id'] ?>"
                                                               class="text-info text-decoration-none"><?= htmlspecialchars($r['username'], ENT_QUOTES, 'UTF-8') ?></a>
                                                            &bull; <?= $rDate ?>
                                                        </small>
                                                        <?php if (!empty($_SESSION['is_admin'])): ?>
                                                            <form method="POST" action="/private/php/forum/forum_comment_delete_submit.php"
                                                                  class="d-inline"
                                                                  onsubmit="return confirm('Antwort wirklich löschen?')">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                                <input type="hidden" name="comment_id" value="<?= $rId ?>">
                                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"
                                                                        title="Antwort löschen"><i class="bi bi-trash3"></i></button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="forum-comment-body mt-1"><?= htmlspecialchars($r['body'], ENT_QUOTES, 'UTF-8') ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($isLoggedIn): ?>
                                        <form method="POST" action="/private/php/forum/forum_comment_submit.php"
                                              class="forum-reply-form d-none" id="reply-form-<?= $cId ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="post_id" value="<?= $pid ?>">
                                            <input type="hidden" name="parent_id" value="<?= $cId ?>">
                                            <div class="input-group input-group-sm mt-1">
                                                <textarea name="body"
                                                          class="form-control bg-secondary text-light border-secondary"
                                                          placeholder="Antwort schreiben…"
                                                          rows="2"
                                                          minlength="2"
                                                          maxlength="2000"
                                                          required></textarea>
                                                <button type="submit" class="btn btn-success">Antworten</button>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($isLoggedIn): ?>
                                <form method="POST" action="/private/php/forum/forum_comment_submit.php" class="mt-2">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="post_id" value="<?= $pid ?>">
                                    <div class="input-group input-group-sm">
                                        <textarea name="body"
                                                  class="form-control bg-secondary text-light border-secondary"
                                                  placeholder="Kommentar schreiben…"
                                                  rows="2"
                                                  minlength="2"
                                                  maxlength="2000"
                                                  required></textarea>
                                        <button type="submit" class="btn btn-success">Senden</button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <p class="text-secondary small mb-0 mt-2">
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="text-success">Einloggen</a>,
                                    um zu kommentieren.
                                </p>
                            <?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        </div>
    </div>
</main>

<!-- Foto-Popup-Modal -->
<div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-modal="true" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h2 class="modal-title h5" id="photoModalLabel"><i class="bi bi-image me-2"></i>Foto</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Schließen"></button>
            </div>
            <div class="modal-body text-center">
                <img id="photoModalImg" src="" alt="" class="img-fluid rounded mx-auto d-block">
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>
<script>
(function () {
    var modal = document.getElementById('photoModal');
    if (!modal) return;
    var imgEl = document.getElementById('photoModalImg');
    modal.addEventListener('show.bs.modal', function (event) {
        var trigger = event.relatedTarget;
        if (!trigger) return;
        imgEl.src = trigger.getAttribute('data-img-src') || '';
        imgEl.alt = trigger.getAttribute('data-img-alt') || '';
    });
    modal.addEventListener('hidden.bs.modal', function () {
        imgEl.src = '';
        imgEl.alt = '';
    });
})();

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.forum-reply-toggle');
    if (!btn) return;
    var formId = btn.getAttribute('data-reply-target');
    if (!formId) return;
    var form = document.getElementById(formId);
    if (!form) return;
    form.classList.toggle('d-none');
    if (!form.classList.contains('d-none')) {
        var ta = form.querySelector('textarea');
        if (ta) ta.focus();
    }
});
</script>
</body>
</html>
