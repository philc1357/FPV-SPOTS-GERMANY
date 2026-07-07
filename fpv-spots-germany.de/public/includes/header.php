<?php
declare(strict_types=1);
$isLoggedIn = $isLoggedIn ?? false;
$username = $username ?? '';
$csrfToken = $csrfToken ?? '';

// Verbesserungsvorschläge bleiben separat (eigenes Cookie + Symbol)
$hasUnseenSuggestions = false;
if ($isLoggedIn && isset($pdo)) {
    $latestSuggestion = $pdo->query("SELECT created_at FROM suggestions ORDER BY created_at DESC LIMIT 1")->fetch();
    if ($latestSuggestion) {
        $lastSeenSuggestion = $_COOKIE['last_seen_suggestion'] ?? '';
        $hasUnseenSuggestions = ($lastSeenSuggestion === '' || $latestSuggestion['created_at'] > $lastSeenSuggestion);
    }
}

// Persönliche In-App-Notifications für Verbesserungsvorschläge -> separates Symbol
$hasUnreadSuggestionComments = false;
if ($isLoggedIn && isset($pdo)) {
    try {
        $ncStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM user_notifications
              WHERE user_id = ? AND type = 'suggestion_comment' AND read_at IS NULL"
        );
        $ncStmt->execute([(int)$_SESSION['user_id']]);
        $hasUnreadSuggestionComments = (bool)$ncStmt->fetchColumn();
    } catch (PDOException $e) {
        // Tabelle existiert noch nicht – kein Fehler anzeigen
    }
}

$unreadMessageCount = 0;
if ($isLoggedIn && isset($pdo)) {
    try {
        $uid = (int)$_SESSION['user_id'];
        $msgStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM messages m
             JOIN conversations c ON m.conversation_id = c.id
             WHERE m.sender_id != ? AND m.read_at IS NULL
               AND ((c.user1_id = ? AND (c.deleted_by_user1 IS NULL OR m.created_at > c.deleted_by_user1))
                 OR (c.user2_id = ? AND (c.deleted_by_user2 IS NULL OR m.created_at > c.deleted_by_user2)))"
        );
        $msgStmt->execute([$uid, $uid, $uid]);
        $unreadMessageCount = (int)$msgStmt->fetchColumn();
    } catch (PDOException $e) {
        // Tabelle existiert noch nicht – kein Fehler anzeigen
    }
}
$hasUnreadMessages = $unreadMessageCount > 0;

// Einheitlicher Benachrichtigungs-Indikator (alles außer Direktnachrichten + Kritik)
// Quellen: spots, comments, ratings, updates, forum_posts + persönliche In-App-Events
$hasUnseenNotifications = false;
if ($isLoggedIn && isset($pdo)) {
    try {
        $lsnStmt = $pdo->prepare('SELECT last_seen_notifications FROM users WHERE id = ?');
        $lsnStmt->execute([(int)$_SESSION['user_id']]);
        $lastSeenNotif = $lsnStmt->fetchColumn() ?: '1970-01-01 00:00:00';
    } catch (PDOException $e) {
        $lastSeenNotif = '1970-01-01 00:00:00';
    }
    try {
        $sql = "SELECT 1 FROM (
                    SELECT MAX(s.created_at) AS c FROM spots s WHERE s.is_private = 0
                    UNION ALL SELECT MAX(c.created_at) FROM comments c
                              JOIN spots s ON c.spot_id = s.id WHERE s.is_private = 0
                    UNION ALL SELECT MAX(r.created_at) FROM ratings r
                              JOIN spots s ON r.spot_id = s.id WHERE s.is_private = 0
                    UNION ALL SELECT MAX(created_at) FROM updates
                    UNION ALL SELECT MAX(created_at) FROM forum_posts
                    UNION ALL SELECT MAX(created_at) FROM user_notifications
                              WHERE user_id = ?
                                AND type IN ('new_spot_comment','new_spot_rating')
                                AND read_at IS NULL
                ) t WHERE t.c IS NOT NULL AND t.c > ? LIMIT 1";
        $nStmt = $pdo->prepare($sql);
        $nStmt->execute([(int)$_SESSION['user_id'], $lastSeenNotif]);
        $hasUnseenNotifications = (bool)$nStmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('header notifications error: ' . $e->getMessage());
    }
}

$hasKritikNotification = $hasUnseenSuggestions || $hasUnreadSuggestionComments;
$hasAnyNotifications   = $hasUnseenNotifications || $hasKritikNotification || $hasUnreadMessages;
?>

<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="stylesheet" href="/public/css/banners.css">
<!-- ============================================================
     Navbar
============================================================ -->
<header class="bg-dark pb-2 sticky-top">
    <nav class="navbar navbar-dark px-3" style="height:56px;">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/" title="Startseite">
            <img src="/public/imgs/logo.png" alt="FPV Spots Germany Logo" height="40">
            <span>FPV Spots Germany</span>
        </a>
        <div class="dropdown">
            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if ($isLoggedIn): ?>
                    <i class="bi bi-person-fill"></i>
                <?php else: ?>
                    Menu
                <?php endif; ?>
                <?php if ($hasAnyNotifications): ?>
                    <span id="update-notify-btn" class="text-warning fw-bold" aria-label="Neue Benachrichtigungen vorhanden"><i class="bi bi-exclamation-circle-fill"></i></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                <?php if ($isLoggedIn): ?>
                    <li><a class="dropdown-item" href="/dashboard.php"><i class="bi bi-person-fill me-1"></i> Dashboard</a></li>
                    <li>
                        <a class="dropdown-item" href="/messages.php">
                            <i class="bi bi-envelope-fill me-1"></i> Nachrichten
                            <span id="message-notify-badge"
                                  class="badge bg-warning text-dark ms-1<?= $unreadMessageCount === 0 ? ' d-none' : '' ?>"
                                  aria-label="Ungelesene Nachrichten">
                                <?= $unreadMessageCount ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="/benachrichtigungen.php">
                            <i class="bi bi-bell-fill me-1"></i> Benachrichtigungen
                            <?php if ($hasUnseenNotifications): ?>
                                <span id="notification-notify-link" class="text-warning fw-bold ms-1 d-none" aria-label="Neue Benachrichtigungen vorhanden"><i class="bi bi-exclamation-circle-fill"></i></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="/private/php/auth/logout_submit.php" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <button type="submit" class="dropdown-item text-danger">Logout</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a></li>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#registerModal">Registrieren</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/forum.php">Forum</a></li>
                <li><a class="dropdown-item" href="/kritik.php">Verbesserungsvorschläge<?php if ($hasKritikNotification): ?> <span id="suggestion-notify-link" class="text-warning fw-bold d-none" aria-label="Neue Aktivität bei Verbesserungsvorschlägen"><i class="bi bi-exclamation-circle-fill"></i></span><?php endif; ?></a></li>
                <li><a class="dropdown-item" href="/neuigkeiten.php">Community Updates</a></li>
                <li><a class="dropdown-item" href="/updates.php">Website Updates</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/kontakt.php">Kontakt</a></li>
                <li><a class="dropdown-item" href="/nutzungsbedingungen.php">Nutzungsbedingungen</a></li>
                <li><a class="dropdown-item" href="/impressum.php">Impressum</a></li>
                <li><a class="dropdown-item" href="/datenschutz.php">Datenschutz</a></li>
                <li><a class="dropdown-item" href="/github.php"><i class="bi bi-github me-1"></i> GitHub</a></li>
            </ul>
        </div>
    </nav>
</header>

<?php include __DIR__ . '/cookie_banner.php'; ?>
<?php include __DIR__ . '/update_banner.php'; ?>
<script src="/public/js/banners.js" defer></script>
