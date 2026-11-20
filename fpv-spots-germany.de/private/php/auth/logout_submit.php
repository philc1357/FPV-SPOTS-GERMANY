<?php
declare(strict_types=1);
require_once __DIR__ . "/../core/session_init.php";
require_once __DIR__ . "/../core/session_guard.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

require_once __DIR__ . "/../core/redirect.php";

// CSRF ungültig (z. B. Session abgelaufen, Seite nicht neu geladen):
// keine rohe Fehlerseite, sondern zurück zur Startseite. Ohne gültige
// Session ist man dort ohnehin ausgeloggt.
if (!hash_equals($_SESSION['csrf_token'] ?? '', post_str('csrf_token'))) {
    header('Location: /');
    exit;
}

// Remember-Me-Token aus DB und Cookie löschen
if (is_string($_COOKIE['remember_me'] ?? null) && $_COOKIE['remember_me'] !== '') {
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) === 2) {
        require_once __DIR__ . '/../core/db.php';
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        $stmt->execute([$parts[0]]);
    }
    clear_remember_cookie();
}

// Session-Daten löschen und Session-Cookie im Browser entfernen
destroy_current_session();

// Redirect-Ziel: gleicher Open-Redirect-Schutz wie beim Login
header('Location: ' . strip_login_params(safe_redirect_target(post_str('redirect'))));
exit;