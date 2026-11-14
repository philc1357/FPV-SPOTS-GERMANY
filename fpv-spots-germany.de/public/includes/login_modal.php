<?php
declare(strict_types=1);
if (!$isLoggedIn):
    $loginFlash = $_SESSION['login_flash'] ?? null;
    unset($_SESSION['login_flash']);

    // Rücksprungziel: explizites ?redirect= (Login-Pflicht-Seiten), sonst die aktuelle Seite
    require_once __DIR__ . '/../../private/php/core/redirect.php';
    $loginRedirect = isset($_GET['redirect']) && is_string($_GET['redirect'])
        ? safe_redirect_target($_GET['redirect'])
        : '/';
    if ($loginRedirect === '/' && !isset($_GET['redirect'])) {
        $loginRedirect = strip_login_params(safe_redirect_target($_SERVER['REQUEST_URI'] ?? '/'));
    }
?>
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="loginModalLabel">Einloggen</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <?php if ($loginFlash): ?>
                    <div class="alert alert-<?= htmlspecialchars($loginFlash['type'], ENT_QUOTES, 'UTF-8') ?> small" role="alert">
                        <?= htmlspecialchars($loginFlash['msg'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <form id="loginForm" action="/private/php/auth/login_submit.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="redirect" id="loginRedirect" value="<?= htmlspecialchars($loginRedirect, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label for="loginUsername" class="visually-hidden">Benutzername</label>
                        <input id="loginUsername" type="text" name="username"
                               class="form-control"
                               placeholder="Benutzername"
                               autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="loginPassword" class="visually-hidden">Passwort</label>
                        <input id="loginPassword" type="password" name="password"
                               class="form-control"
                               placeholder="Passwort"
                               autocomplete="current-password" required>
                    </div>
                    <!-- <div class="text-end mb-2">
                        <a href="/public/php/forgot_password.php" class="small">Passwort vergessen?</a>
                    </div> -->
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember_me" value="1" id="rememberMeModal"
                               class="form-check-input">
                        <label for="rememberMeModal" class="form-check-label small">Eingeloggt bleiben (30 Tage)</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">Einloggen</button>
                </form>
                <hr>
                <p class="text-center mb-0 small">
                    Noch kein Konto?
                    <a href="#" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#registerModal" class="fw-semibold">Jetzt registrieren</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('/public/js/pages/login_modal.js') ?>"></script>
<?php endif; ?>
