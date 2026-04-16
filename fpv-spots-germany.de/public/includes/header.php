<?php
$isLoggedIn = $isLoggedIn ?? false;
$username = $username ?? '';
$csrfToken = $csrfToken ?? '';

$hasUnseenUpdates = false;
if ($isLoggedIn && isset($pdo)) {
    $latestUpdate = $pdo->query("SELECT created_at FROM updates ORDER BY created_at DESC LIMIT 1")->fetch();
    if ($latestUpdate) {
        $lastSeen = $_COOKIE['last_seen_update'] ?? '';
        $hasUnseenUpdates = ($lastSeen === '' || $latestUpdate['created_at'] > $lastSeen);
    }
}

$hasUnseenSuggestions = false;
if ($isLoggedIn && isset($pdo)) {
    $latestSuggestion = $pdo->query("SELECT created_at FROM suggestions ORDER BY created_at DESC LIMIT 1")->fetch();
    if ($latestSuggestion) {
        $lastSeenSuggestion = $_COOKIE['last_seen_suggestion'] ?? '';
        $hasUnseenSuggestions = ($lastSeenSuggestion === '' || $latestSuggestion['created_at'] > $lastSeenSuggestion);
    }
}

$hasUnreadComments = false;
if ($isLoggedIn && isset($pdo)) {
    try {
        $ncStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM user_notifications WHERE user_id = ? AND read_at IS NULL"
        );
        $ncStmt->execute([(int)$_SESSION['user_id']]);
        $hasUnreadComments = (bool)$ncStmt->fetchColumn();
    } catch (PDOException $e) {
        // Tabelle existiert noch nicht – kein Fehler anzeigen
    }
}

$hasKritikNotification = $hasUnseenSuggestions || $hasUnreadComments;
$hasAnyNotifications   = $hasUnseenUpdates || $hasKritikNotification;
?>

<link rel="icon" type="image/x-icon" href="/favicon.ico">
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
                    <li><a class="dropdown-item" href="/public/php/dashboard.php"><i class="bi bi-person-fill me-1"></i> Dashboard</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="/private/php/logout_submit.php" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <button type="submit" class="dropdown-item text-danger">Logout</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a></li>
                    <li><a class="dropdown-item" href="/public/php/register.php">Registrieren</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="/public/php/updates.php">Updates<?php if ($hasUnseenUpdates): ?> <span id="update-notify-link" class="text-warning fw-bold d-none" aria-label="Neue Updates vorhanden"><i class="bi bi-exclamation-circle-fill"></i></span><?php endif; ?></a></li>
                <li><a class="dropdown-item" href="/public/php/kritik.php">Verbesserungsvorschläge<?php if ($hasKritikNotification): ?> <span id="suggestion-notify-link" class="text-warning fw-bold d-none" aria-label="Neue Aktivität bei Verbesserungsvorschlägen"><i class="bi bi-exclamation-circle-fill"></i></span><?php endif; ?></a></li>
                <li><a class="dropdown-item" href="/public/php/kontakt.php">Kontakt</a></li>
                <li><a class="dropdown-item" href="/public/php/impressum.php">Impressum</a></li>
                <li><a class="dropdown-item" href="/public/php/datenschutz.php">Datenschutz</a></li>
            </ul>
        </div>
    </nav>
</header>

<?php if ($hasAnyNotifications): ?>
<script>
(function () {
    var btnBadge      = document.getElementById('update-notify-btn');
    var updateLink    = document.getElementById('update-notify-link');
    var suggestionLink = document.getElementById('suggestion-notify-link');
    if (!btnBadge) return;
    var dropdown = document.querySelector('.dropdown');
    dropdown.addEventListener('shown.bs.dropdown', function () {
        btnBadge.classList.add('d-none');
        if (updateLink)    updateLink.classList.remove('d-none');
        if (suggestionLink) suggestionLink.classList.remove('d-none');
    });
    dropdown.addEventListener('hidden.bs.dropdown', function () {
        if (updateLink)    updateLink.classList.add('d-none');
        if (suggestionLink) suggestionLink.classList.add('d-none');
        btnBadge.classList.remove('d-none');
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/cookie_banner.php'; ?>
<?php include __DIR__ . '/update_banner.php'; ?>
