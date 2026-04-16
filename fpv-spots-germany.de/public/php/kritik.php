<?php
// =============================================================
// FPV Spots Germany – Verbesserungsvorschläge
// =============================================================
session_start();
require_once __DIR__ . '/../../private/php/auth_check.php';

require_once __DIR__ . '/../../private/php/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = (int)($_SESSION['user_id'] ?? 0);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];

// Alle Vorschläge laden mit Vote-Anzahl und eigenem Vote-Status
$stmt = $pdo->prepare(
    "SELECT s.id, s.body, s.created_at, u.username,
            COUNT(v.user_id) AS vote_count,
            MAX(CASE WHEN v.user_id = :uid THEN 1 ELSE 0 END) AS user_voted
     FROM suggestions s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN suggestion_votes v ON v.suggestion_id = s.id
     GROUP BY s.id, s.body, s.created_at, u.username
     ORDER BY vote_count DESC, s.created_at DESC"
);
$stmt->execute([':uid' => $userId]);
$suggestions = $stmt->fetchAll();

// Ungelesene Kommentar-Benachrichtigungen als gelesen markieren
if ($isLoggedIn) {
    try {
        $stmt = $pdo->prepare(
            "UPDATE user_notifications SET read_at = NOW()
             WHERE user_id = ? AND type = 'suggestion_comment' AND read_at IS NULL"
        );
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log('kritik.php notification read error: ' . $e->getMessage());
    }
}

// Cookie setzen: neuesten Vorschlag als gesehen markieren
if ($isLoggedIn && !empty($suggestions)) {
    $latestCreated = $pdo->query("SELECT MAX(created_at) AS max_at FROM suggestions")->fetchColumn();
    if ($latestCreated) {
        setcookie('last_seen_suggestion', $latestCreated, [
            'expires'  => time() + 365 * 86400,
            'path'     => '/',
            'httponly' => true,
            'secure'   => true,
            'samesite' => 'Lax',
        ]);
    }
}

// Admin-Kommentare laden
$comments = [];
if (!empty($suggestions)) {
    $ids   = implode(',', array_map('intval', array_column($suggestions, 'id')));
    $cStmt = $pdo->query(
        "SELECT sc.id, sc.suggestion_id, sc.body, sc.created_at, u.username
         FROM suggestion_comments sc
         JOIN users u ON sc.user_id = u.id
         WHERE sc.suggestion_id IN ($ids)
         ORDER BY sc.created_at ASC"
    );
    foreach ($cStmt->fetchAll() as $c) {
        $comments[(int)$c['suggestion_id']][] = $c;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verbesserungsvorschläge – FPV Spots Germany</title>
    <meta name="description" content="Teile deine Ideen und Verbesserungsvorschläge für FPV Spots Germany mit der Community und vote für die besten Vorschläge.">
    <meta name="robots" content="index, follow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/kritik.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/login_modal.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">

            <!-- ====================================================
                 Einleitung
            ==================================================== -->
            <div class="card card-dark text-light p-4 mb-4">
                <h1 class="h3 mb-2">Verbesserungsvorschläge</h1>
                <p class="text-secondary mb-0">
                    Hast du eine Idee, wie <b><span>FPV-Spots-Germany</span></b> besser werden kann? Teile deinen Vorschlag mit der <span>Community</span> und vote für die Ideen, die dir am besten gefallen.
                </p>
            </div>

            <!-- ====================================================
                 Neuen Vorschlag posten
            ==================================================== -->
            <div class="card card-dark text-light p-4 mb-4">
                <h2 class="h5 mb-3">Vorschlag einreichen</h2>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" action="/private/php/suggestion_submit.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <textarea name="body"
                                  class="form-control bg-secondary text-light border-0 mb-2"
                                  placeholder="Dein Verbesserungsvorschlag..."
                                  rows="4"
                                  minlength="10"
                                  maxlength="1000"
                                  required></textarea>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-secondary">Mindestens 10, maximal 1000 Zeichen</small>
                            <button type="submit" class="btn btn-success btn-sm">Vorschlag absenden</button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-secondary mb-0">
                        <a href="/public/php/login.php" class="text-success">Einloggen</a>, um einen Vorschlag einzureichen.
                    </p>
                <?php endif; ?>
            </div>

            <!-- ====================================================
                 Vorschlagsliste
            ==================================================== -->
            <div class="card card-dark text-light p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Alle Vorschläge</h2>
                    <span class="badge bg-primary"><?= count($suggestions) ?></span>
                </div>

                <?php if (empty($suggestions)): ?>
                    <p class="text-secondary">Noch keine Vorschläge. Sei der Erste!</p>
                <?php else: ?>
                    <?php foreach ($suggestions as $s):
                        $date      = date('d.m.Y', strtotime($s['created_at']));
                        $voteCount = (int)$s['vote_count'];
                        $voted     = (bool)$s['user_voted'];
                    ?>
                    <article class="suggestion-item py-3" id="suggestion-<?= $s['id'] ?>">
                        <div class="d-flex gap-3 align-items-start">

                            <!-- Vote-Button -->
                            <div class="text-center" style="min-width: 52px;">
                                <?php if ($isLoggedIn && !$voted): ?>
                                    <form method="POST" action="/private/php/suggestion_vote_submit.php">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="suggestion_id" value="<?= $s['id'] ?>">
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm d-block w-100"
                                                title="Für diesen Vorschlag voten"
                                                onclick="openVoteConfirm(this.form)"><i class="bi bi-hand-thumbs-up-fill"></i></button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-sm d-block w-100 <?= $voted ? 'btn-success' : 'btn-outline-secondary' ?>"
                                            disabled
                                            title="<?= $voted ? 'Du hast bereits gevoted' : 'Einloggen zum Voten' ?>"><i class="bi bi-hand-thumbs-up-fill"></i></button>
                                <?php endif; ?>
                                <small class="text-secondary d-block mt-1"><?= $voteCount ?></small>
                            </div>

                            <!-- Inhalt -->
                            <div class="flex-grow-1">
                                <p class="mb-1"><?= nl2br(htmlspecialchars($s['body'])) ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-secondary">
                                        <i class="bi bi-person-fill"></i> <?= htmlspecialchars($s['username']) ?> &bull; <i class="bi bi-calendar3"></i> <?= $date ?>
                                    </small>
                                    <?php if (!empty($_SESSION['is_admin'])): ?>
                                        <form method="POST" action="/private/php/suggestion_delete_submit.php"
                                              class="d-inline"
                                              onsubmit="return confirm('Vorschlag wirklich löschen?')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="suggestion_id" value="<?= $s['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"
                                                    title="Löschen"><i class="bi bi-trash3"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <!-- Admin-Kommentare (öffentlich sichtbar) -->
                                <?php if (!empty($comments[$s['id']])): ?>
                                    <?php foreach ($comments[$s['id']] as $c): ?>
                                        <?php $cDate = date('d.m.Y', strtotime($c['created_at'])); ?>
                                        <div class="admin-comment mt-2 p-2 border-start border-success ps-3">
                                            <p class="mb-1 small"><?= nl2br(htmlspecialchars($c['body'], ENT_QUOTES, 'UTF-8')) ?></p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-success"><i class="bi bi-chat-fill"></i> Admin-Antwort &bull; <i class="bi bi-calendar3"></i> <?= $cDate ?></small>
                                                <?php if (!empty($_SESSION['is_admin'])): ?>
                                                    <form method="POST" action="/private/php/suggestion_comment_delete_submit.php"
                                                          class="d-inline"
                                                          onsubmit="return confirm('Kommentar wirklich löschen?')">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="comment_id" value="<?= $c['id'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"
                                                                title="Kommentar löschen"><i class="bi bi-trash3"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                                <!-- Admin-Kommentar-Formular -->
                                <?php if (!empty($_SESSION['is_admin'])): ?>
                                    <form method="POST" action="/private/php/suggestion_comment_submit.php" class="mt-2">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                        <input type="hidden" name="suggestion_id" value="<?= $s['id'] ?>">
                                        <div class="input-group input-group-sm">
                                            <textarea name="body"
                                                      class="form-control bg-secondary text-light border-secondary"
                                                      placeholder="Admin-Kommentar hinzufügen..."
                                                      rows="2"
                                                      minlength="3"
                                                      maxlength="1000"
                                                      required></textarea>
                                            <button type="submit" class="btn btn-success">Kommentieren</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>

                        </div>
                    </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</main>

<!-- Bestätigungs-Modal für Vote -->
<div class="modal fade" id="voteConfirmModal" tabindex="-1" aria-labelledby="voteConfirmModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="voteConfirmModalLabel">Vote bestätigen</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <p>Diese Aktion kann nicht rückgängig gemacht werden, bist du sicher dass du diesen Verbesserungsvorschlag voten möchtest?</p>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-success flex-fill" id="voteConfirmBtn">Ja, voten</button>
                    <button type="button" class="btn btn-danger flex-fill" data-bs-dismiss="modal">Abbrechen</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"></script>

<script>
let pendingVoteForm = null;

function openVoteConfirm(form) {
    pendingVoteForm = form;
    new bootstrap.Modal(document.getElementById('voteConfirmModal')).show();
}

document.getElementById('voteConfirmBtn').addEventListener('click', () => {
    if (pendingVoteForm) pendingVoteForm.submit();
});
</script>

</body>
</html>
