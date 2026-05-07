<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../private/php/core/session_init.php';
require_once __DIR__ . '/../../../private/php/core/auth_check.php';
require_once __DIR__ . '/../../../private/php/core/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin    = !empty($_SESSION['is_admin']);
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];

$stmt = $pdo->query(
    "(SELECT 'spot_created' AS event_type,
             s.id          AS spot_id,
             s.name        AS spot_name,
             s.spot_type,
             s.created_at,
             u.id          AS user_id,
             u.username,
             NULL          AS comment_body
      FROM spots s
      JOIN users u ON s.user_id = u.id
      WHERE s.is_private = 0)
     UNION ALL
     (SELECT 'comment_added' AS event_type,
             sp.id           AS spot_id,
             sp.name         AS spot_name,
             sp.spot_type,
             c.created_at,
             u.id            AS user_id,
             u.username,
             c.body          AS comment_body
      FROM comments c
      JOIN users u  ON c.user_id  = u.id
      JOIN spots sp ON c.spot_id  = sp.id
      WHERE sp.is_private = 0)
     UNION ALL
     (SELECT 'rating_added' AS event_type,
             sp.id          AS spot_id,
             sp.name        AS spot_name,
             sp.spot_type,
             r.created_at,
             u.id           AS user_id,
             u.username,
             CAST(r.stars AS CHAR) AS comment_body
      FROM ratings r
      JOIN users u  ON r.user_id  = u.id
      JOIN spots sp ON r.spot_id  = sp.id
      WHERE sp.is_private = 0)
     ORDER BY created_at DESC
     LIMIT 50"
);
$events = $stmt->fetchAll();

if (!empty($events)) {
    $newTs = $events[0]['created_at'];
    setcookie('last_seen_neuigkeiten', $newTs, [
        'expires'  => time() + 365 * 86400,
        'path'     => '/',
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['last_seen_neuigkeiten'] = $newTs;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neuigkeiten – FPV Spots Germany</title>
    <meta name="description" content="Aktuelle Community-Aktivitäten: neue Spots und Kommentare auf FPV Spots Germany.">
    <meta name="robots" content="index, follow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/public/css/neuigkeiten.css">
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>
<?php include __DIR__ . '/../../includes/login_modal.php'; ?>
<?php include __DIR__ . '/../../includes/register_modal.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">

            <div class="card card-dark text-light p-4 mb-4">
                <h1 class="h3 mb-1"><i class="bi bi-activity me-2"></i>Neuigkeiten</h1>
                <p class="text-secondary mb-0">Aktuelle Aktivitäten der FPV Spots Germany Community.</p>
            </div>

            <?php if (empty($events)): ?>
                <p class="text-secondary text-center py-4">Noch keine Aktivität vorhanden.</p>
            <?php else:
                $months    = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                $today     = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $lastDate  = null;
            ?>
                <?php foreach ($events as $event):
                    $dateFormatted = date('d.m.Y H:i', strtotime($event['created_at']));
                    $dateIso       = date('c', strtotime($event['created_at']));
                    $spotName      = htmlspecialchars($event['spot_name'], ENT_QUOTES, 'UTF-8');
                    $spotType      = htmlspecialchars($event['spot_type'], ENT_QUOTES, 'UTF-8');
                    $spotUser      = htmlspecialchars($event['username'], ENT_QUOTES, 'UTF-8');
                    $spotUserId    = (int)$event['user_id'];
                    $spotId        = (int)$event['spot_id'];
                    $isComment     = $event['event_type'] === 'comment_added';
                    $isRating      = $event['event_type'] === 'rating_added';
                    $stars         = $isRating ? (int)$event['comment_body'] : 0;
                    $eventDate     = date('Y-m-d', strtotime($event['created_at']));
                    if ($eventDate !== $lastDate):
                        $lastDate = $eventDate;
                        if ($eventDate === $today) {
                            $dayLabel = 'Heute';
                        } elseif ($eventDate === $yesterday) {
                            $dayLabel = 'Gestern';
                        } else {
                            $ts = strtotime($event['created_at']);
                            $dayLabel = date('j', $ts) . '. ' . $months[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
                        }
                ?>
                <div class="d-flex align-items-center my-3">
                    <hr class="flex-grow-1 opacity-25">
                    <span class="mx-3 text-secondary small fw-semibold"><?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    <hr class="flex-grow-1 opacity-25">
                </div>
                <?php endif; ?>
                <article class="card card-dark text-light p-3 mb-3 news-card">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="flex-grow-1 me-3 min-w-0">
                            <?php if ($isComment): ?>
                                <p class="mb-1 small text-info">
                                    <i class="bi bi-chat-fill me-1"></i>Neuer Kommentar
                                </p>
                                <p class="mb-1 fw-semibold">
                                    <?= $spotName ?>
                                    <span class="badge bg-secondary ms-2"><?= $spotType ?></span>
                                </p>
                                <p class="mb-1 text-light small fst-italic news-comment">
                                    „<?= htmlspecialchars($event['comment_body'], ENT_QUOTES, 'UTF-8') ?>"
                                </p>
                            <?php elseif ($isRating): ?>
                                <p class="mb-1 small text-warning">
                                    <i class="bi bi-star-fill me-1"></i>Neue Bewertung
                                </p>
                                <p class="mb-1 fw-semibold">
                                    <?= $spotName ?>
                                    <span class="badge bg-secondary ms-2"><?= $spotType ?></span>
                                </p>
                                <p class="mb-1 text-warning small">
                                    <?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?>
                                </p>
                            <?php else: ?>
                                <p class="mb-1 small text-success">
                                    <i class="bi bi-plus-circle-fill me-1"></i>Neuer Spot
                                </p>
                                <p class="mb-1 fw-semibold">
                                    <?= $spotName ?>
                                    <span class="badge bg-secondary ms-2"><?= $spotType ?></span>
                                </p>
                            <?php endif; ?>
                            <p class="mb-0 small text-secondary">
                                von
                                <a href="/profile.php?id=<?= $spotUserId ?>"
                                   class="text-secondary"><?= $spotUser ?></a>
                            </p>
                        </div>
                        <time datetime="<?= htmlspecialchars($dateIso, ENT_QUOTES, 'UTF-8') ?>"
                              class="text-secondary small text-nowrap">
                            <?= htmlspecialchars($dateFormatted, ENT_QUOTES, 'UTF-8') ?>
                        </time>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if (!$isComment && !$isRating): ?>
                        <a href="/?spot=<?= $spotId ?>"
                           class="btn btn-outline-light btn-sm">
                            <i class="bi bi-map me-1"></i>Auf der Karte anzeigen
                        </a>
                        <?php endif; ?>
                        <a href="/spot_detail.php?id=<?= $spotId ?>"
                           class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-info-circle me-1"></i>Zur Detailseite
                        </a>
                    </div>
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
