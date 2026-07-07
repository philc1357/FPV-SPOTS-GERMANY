<?php
declare(strict_types=1);
// =============================================================
// Zentrale Benachrichtigungs-Seite
// Bündelt globale Aktivitäten + persönliche Events
// =============================================================
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

if (!$isLoggedIn) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// -----------------------------------------------------------------
// Per-User "zuletzt gesehen" aus DB lesen (Cookie wäre browser-weit, nicht user-spezifisch)
// -----------------------------------------------------------------
$previousLastSeen = '1970-01-01 00:00:00';
try {
    $lsnStmt = $pdo->prepare('SELECT last_seen_notifications FROM users WHERE id = ?');
    $lsnStmt->execute([$userId]);
    $previousLastSeen = $lsnStmt->fetchColumn() ?: '1970-01-01 00:00:00';
} catch (PDOException $e) {
    error_log('benachrichtigungen.php last_seen read error: ' . $e->getMessage());
}

// Zeitstempel auf JETZT setzen – verbirgt das Header-Symbol nach Besuch
try {
    $pdo->prepare('UPDATE users SET last_seen_notifications = NOW() WHERE id = ?')
        ->execute([$userId]);
} catch (PDOException $e) {
    error_log('benachrichtigungen.php last_seen update error: ' . $e->getMessage());
}

// -----------------------------------------------------------------
// Persönliche user_notifications als gelesen markieren
// (außer 'new_message' – das hat eigene Logik via Messages-Seite)
// -----------------------------------------------------------------
try {
    $pdo->prepare(
        "UPDATE user_notifications SET read_at = NOW()
         WHERE user_id = ? AND read_at IS NULL AND type != 'new_message'"
    )->execute([$userId]);
} catch (PDOException $e) {
    error_log('benachrichtigungen.php mark-read error: ' . $e->getMessage());
}

// -----------------------------------------------------------------
// Daten laden – globale Feeds + persönliche Events
// -----------------------------------------------------------------
$events = [];

try {
    // Globale Aktivitäten (analog neuigkeiten.php) + Website-Updates + Forum-Posts
    $sql = "
        (SELECT 'spot_created' AS event_type,
                s.id           AS ref_id,
                s.name         AS title,
                s.spot_type    AS extra,
                s.created_at,
                u.id           AS actor_id,
                u.username     AS actor_name,
                NULL           AS body,
                0              AS is_personal
           FROM spots s
           JOIN users u ON s.user_id = u.id
          WHERE s.is_private = 0)
        UNION ALL
        (SELECT 'comment_added',
                sp.id,
                sp.name,
                sp.spot_type,
                c.created_at,
                u.id,
                u.username,
                c.body,
                0
           FROM comments c
           JOIN users u  ON c.user_id = u.id
           JOIN spots sp ON c.spot_id = sp.id
          WHERE sp.is_private = 0)
        UNION ALL
        (SELECT 'rating_added',
                sp.id,
                sp.name,
                sp.spot_type,
                r.created_at,
                u.id,
                u.username,
                CAST(r.stars AS CHAR),
                0
           FROM ratings r
           JOIN users u  ON r.user_id = u.id
           JOIN spots sp ON r.spot_id = sp.id
          WHERE sp.is_private = 0)
        UNION ALL
        (SELECT 'website_update',
                up.id,
                up.title,
                NULL,
                up.created_at,
                u.id,
                u.username,
                up.description,
                0
           FROM updates up
           JOIN users u ON up.user_id = u.id)
        UNION ALL
        (SELECT 'forum_post',
                fp.id,
                fp.title,
                NULL,
                fp.created_at,
                u.id,
                u.username,
                fp.body,
                0
           FROM forum_posts fp
           JOIN users u ON fp.user_id = u.id)
        ORDER BY created_at DESC
        LIMIT 50";
    $events = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('benachrichtigungen.php query error: ' . $e->getMessage());
}

// Persönliche Events der letzten 90 Tage zusätzlich einsammeln
$personalEvents = [];
try {
    $pStmt = $pdo->prepare(
        "SELECT n.id           AS notif_id,
                n.type         AS event_type,
                n.reference_id AS ref_id,
                n.created_at,
                n.read_at
           FROM user_notifications n
          WHERE n.user_id = ?
            AND n.type IN ('new_spot_comment','new_spot_rating','suggestion_comment')
            AND n.created_at >= (NOW() - INTERVAL 90 DAY)
          ORDER BY n.created_at DESC
          LIMIT 50"
    );
    $pStmt->execute([$userId]);
    $rawPersonal = $pStmt->fetchAll(PDO::FETCH_ASSOC);

    // Per Typ in Maps gruppieren, um anschließend mit JOIN-Daten anzureichern
    $commentIds = [];
    $ratingIds  = [];
    $suggestIds = [];
    foreach ($rawPersonal as $row) {
        $rid = (int)$row['ref_id'];
        if ($row['event_type'] === 'new_spot_comment')  $commentIds[] = $rid;
        if ($row['event_type'] === 'new_spot_rating')   $ratingIds[]  = $rid;
        if ($row['event_type'] === 'suggestion_comment') $suggestIds[] = $rid;
    }

    $commentMap = [];
    if ($commentIds) {
        $in = implode(',', array_fill(0, count($commentIds), '?'));
        $cs = $pdo->prepare(
            "SELECT c.id, c.body, c.created_at, c.spot_id,
                    sp.name AS spot_name, sp.spot_type,
                    u.id AS actor_id, u.username AS actor_name
               FROM comments c
               JOIN spots sp ON c.spot_id = sp.id
               JOIN users u  ON c.user_id = u.id
              WHERE c.id IN ($in)"
        );
        $cs->execute($commentIds);
        foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $r) $commentMap[(int)$r['id']] = $r;
    }

    $ratingMap = [];
    if ($ratingIds) {
        $in = implode(',', array_fill(0, count($ratingIds), '?'));
        $rs = $pdo->prepare(
            "SELECT r.id, r.stars, r.created_at, r.spot_id,
                    sp.name AS spot_name, sp.spot_type,
                    u.id AS actor_id, u.username AS actor_name
               FROM ratings r
               JOIN spots sp ON r.spot_id = sp.id
               JOIN users u  ON r.user_id = u.id
              WHERE r.id IN ($in)"
        );
        $rs->execute($ratingIds);
        foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $r) $ratingMap[(int)$r['id']] = $r;
    }

    foreach ($rawPersonal as $row) {
        $rid  = (int)$row['ref_id'];
        $type = $row['event_type'];
        if ($type === 'new_spot_comment' && isset($commentMap[$rid])) {
            $c = $commentMap[$rid];
            $personalEvents[] = [
                'event_type' => 'personal_comment',
                'ref_id'     => (int)$c['spot_id'],
                'title'      => $c['spot_name'],
                'extra'      => $c['spot_type'],
                'created_at' => $row['created_at'],
                'actor_id'   => (int)$c['actor_id'],
                'actor_name' => $c['actor_name'],
                'body'       => $c['body'],
                'is_personal'=> 1,
            ];
        } elseif ($type === 'new_spot_rating' && isset($ratingMap[$rid])) {
            $r = $ratingMap[$rid];
            $personalEvents[] = [
                'event_type' => 'personal_rating',
                'ref_id'     => (int)$r['spot_id'],
                'title'      => $r['spot_name'],
                'extra'      => $r['spot_type'],
                'created_at' => $row['created_at'],
                'actor_id'   => (int)$r['actor_id'],
                'actor_name' => $r['actor_name'],
                'body'       => (string)(int)$r['stars'],
                'is_personal'=> 1,
            ];
        } elseif ($type === 'suggestion_comment') {
            $personalEvents[] = [
                'event_type' => 'personal_suggestion_comment',
                'ref_id'     => $rid,
                'title'      => 'Dein Verbesserungsvorschlag wurde kommentiert',
                'extra'      => null,
                'created_at' => $row['created_at'],
                'actor_id'   => 0,
                'actor_name' => '',
                'body'       => null,
                'is_personal'=> 1,
            ];
        }
    }
} catch (PDOException $e) {
    error_log('benachrichtigungen.php personal-events error: ' . $e->getMessage());
}

// Mergen + nach Datum sortieren
$all = array_merge($personalEvents, $events);
usort($all, function ($a, $b) {
    return strcmp($b['created_at'], $a['created_at']);
});
$all = array_slice($all, 0, 80);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benachrichtigungen – FPV Spots Germany</title>
    <meta name="description" content="Übersicht aller Benachrichtigungen auf FPV Spots Germany.">
    <meta name="robots" content="noindex, nofollow">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    
    <link rel="stylesheet" href="/public/css/neuigkeiten.css">
    <style>
        .notification-new {
            background-color: rgba(255, 193, 7, 0.10);
            box-shadow: 0 0 0 1px rgba(255, 193, 7, 0.45);
        }
        .notification-new .badge.bg-danger {
            background-color: #d63384 !important;
        }
    </style>
</head>
<body class="text-light">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">

            <div class="card card-dark text-light p-4 mb-4">
                <h1 class="h3 mb-1"><i class="bi bi-bell-fill me-2"></i>Benachrichtigungen</h1>
                <p class="text-secondary mb-0">Alle neuen Aktivitäten der Community und persönliche Hinweise.</p>
            </div>

            <?php if (empty($all)): ?>
                <p class="text-secondary text-center py-4">Keine Benachrichtigungen vorhanden.</p>
            <?php else:
                $months    = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];
                $today     = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                $lastDate  = null;
            ?>
                <?php foreach ($all as $event):
                    $dateFormatted = date('d.m.Y H:i', strtotime($event['created_at']));
                    $dateIso       = date('c', strtotime($event['created_at']));
                    $title         = htmlspecialchars((string)$event['title'], ENT_QUOTES, 'UTF-8');
                    $extra         = $event['extra'] !== null ? htmlspecialchars((string)$event['extra'], ENT_QUOTES, 'UTF-8') : '';
                    $actorName     = htmlspecialchars((string)$event['actor_name'], ENT_QUOTES, 'UTF-8');
                    $actorId       = (int)$event['actor_id'];
                    $refId         = (int)$event['ref_id'];
                    $type          = $event['event_type'];
                    $isPersonal    = !empty($event['is_personal']);
                    $isNew         = $event['created_at'] > $previousLastSeen;
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
                <article class="card card-dark text-light p-3 mb-3 news-card<?= $isPersonal ? ' border border-warning' : '' ?><?= $isNew ? ' notification-new' : '' ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="flex-grow-1 me-3 min-w-0">
                            <?php if ($isNew): ?>
                                <span class="badge bg-danger mb-2" aria-label="Neue Benachrichtigung">
                                    <i class="bi bi-stars me-1"></i>Neu
                                </span>
                            <?php endif; ?>
                            <?php if ($type === 'personal_comment'): ?>
                                <p class="mb-1 small text-warning fw-semibold">
                                    <i class="bi bi-chat-fill me-1"></i>Neuer Kommentar zu deinem Spot
                                </p>
                                <p class="mb-1 fw-semibold"><?= $title ?> <?php if ($extra): ?><span class="badge bg-secondary ms-2"><?= $extra ?></span><?php endif; ?></p>
                                <p class="mb-1 text-light small fst-italic">„<?= htmlspecialchars((string)$event['body'], ENT_QUOTES, 'UTF-8') ?>"</p>
                            <?php elseif ($type === 'personal_rating'):
                                $stars = (int)$event['body'];
                            ?>
                                <p class="mb-1 small text-warning fw-semibold">
                                    <i class="bi bi-star-fill me-1"></i>Neue Bewertung für deinen Spot
                                </p>
                                <p class="mb-1 fw-semibold"><?= $title ?> <?php if ($extra): ?><span class="badge bg-secondary ms-2"><?= $extra ?></span><?php endif; ?></p>
                                <p class="mb-1 text-warning small"><?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?></p>
                            <?php elseif ($type === 'personal_suggestion_comment'): ?>
                                <p class="mb-1 small text-warning fw-semibold">
                                    <i class="bi bi-chat-square-text-fill me-1"></i><?= $title ?>
                                </p>
                            <?php elseif ($type === 'comment_added'): ?>
                                <p class="mb-1 small text-info"><i class="bi bi-chat-fill me-1"></i>Neuer Kommentar</p>
                                <p class="mb-1 fw-semibold"><?= $title ?> <span class="badge bg-secondary ms-2"><?= $extra ?></span></p>
                                <p class="mb-1 text-light small fst-italic">„<?= htmlspecialchars((string)$event['body'], ENT_QUOTES, 'UTF-8') ?>"</p>
                            <?php elseif ($type === 'rating_added'):
                                $stars = (int)$event['body'];
                            ?>
                                <p class="mb-1 small text-warning"><i class="bi bi-star-fill me-1"></i>Neue Bewertung</p>
                                <p class="mb-1 fw-semibold"><?= $title ?> <span class="badge bg-secondary ms-2"><?= $extra ?></span></p>
                                <p class="mb-1 text-warning small"><?= str_repeat('★', $stars) ?><?= str_repeat('☆', 5 - $stars) ?></p>
                            <?php elseif ($type === 'spot_created'): ?>
                                <p class="mb-1 small text-success"><i class="bi bi-plus-circle-fill me-1"></i>Neuer Spot</p>
                                <p class="mb-1 fw-semibold"><?= $title ?> <span class="badge bg-secondary ms-2"><?= $extra ?></span></p>
                            <?php elseif ($type === 'website_update'): ?>
                                <p class="mb-1 small text-primary"><i class="bi bi-arrow-repeat me-1"></i>Website-Update</p>
                                <p class="mb-1 fw-semibold"><?= $title ?></p>
                            <?php elseif ($type === 'forum_post'): ?>
                                <p class="mb-1 small text-info"><i class="bi bi-people-fill me-1"></i>Neuer Forum-Beitrag</p>
                                <p class="mb-1 fw-semibold"><?= $title ?></p>
                            <?php endif; ?>
                            <?php if ($actorId > 0 && $actorName !== ''): ?>
                            <p class="mb-0 small text-secondary">
                                von <a href="/profile.php?id=<?= $actorId ?>" class="text-secondary"><?= $actorName ?></a>
                            </p>
                            <?php endif; ?>
                        </div>
                        <time datetime="<?= htmlspecialchars($dateIso, ENT_QUOTES, 'UTF-8') ?>"
                              class="text-secondary small text-nowrap">
                            <?= htmlspecialchars($dateFormatted, ENT_QUOTES, 'UTF-8') ?>
                        </time>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if (in_array($type, ['spot_created','comment_added','rating_added','personal_comment','personal_rating'], true)): ?>
                            <a href="/spot_detail.php?id=<?= $refId ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-info-circle me-1"></i>Zur Detailseite
                            </a>
                        <?php elseif ($type === 'website_update'): ?>
                            <a href="/updates.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-right me-1"></i>Zu den Updates
                            </a>
                        <?php elseif ($type === 'forum_post'): ?>
                            <a href="/forum.php#post-<?= $refId ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-right me-1"></i>Zum Forum
                            </a>
                        <?php elseif ($type === 'personal_suggestion_comment'): ?>
                            <a href="/kritik.php#suggestion-<?= $refId ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-right me-1"></i>Zum Vorschlag
                            </a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>

</body>
</html>
