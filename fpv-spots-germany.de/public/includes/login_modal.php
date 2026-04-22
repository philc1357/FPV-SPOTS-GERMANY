<?php
declare(strict_types=1);
if (!$isLoggedIn):
    $loginFlash = $_SESSION['login_flash'] ?? null;
    unset($_SESSION['login_flash']);
?>
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
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
                <form action="/private/php/auth/login_submit.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <input type="text" name="username"
                               class="form-control bg-secondary text-light border-0"
                               placeholder="Benutzername"
                               autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password"
                               class="form-control bg-secondary text-light border-0"
                               placeholder="Passwort"
                               autocomplete="current-password" required>
                    </div>
                    <!-- <div class="text-end mb-2">
                        <a href="/public/php/forgot_password.php" class="text-success small">Passwort vergessen?</a>
                    </div> -->
                    <div class="form-check mb-3">
                        <input type="checkbox" name="remember_me" value="1" id="rememberMe"
                               class="form-check-input" style="accent-color: #198754;">
                        <label for="rememberMe" class="form-check-label small">Eingeloggt bleiben (30 Tage)</label>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2">Einloggen</button>
                </form>
                <hr class="border-secondary">
                <p class="text-center mb-0 small">
                    Noch kein Konto?
                    <a href="#" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#registerModal" class="text-success">Jetzt registrieren</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('showLogin') === '1') {
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('loginModal');
            if (el && window.bootstrap) {
                new bootstrap.Modal(el).show();
            }
            params.delete('showLogin');
            var qs = params.toString();
            var url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
            window.history.replaceState({}, '', url);
        });
    }
})();
</script>
<?php endif; ?>
