<?php if (!$isLoggedIn): ?>
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="loginModalLabel">Einloggen</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <form action="/private/php/login_submit.php" method="POST">
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
                    <a href="/public/php/register.php" class="text-success">Jetzt registrieren</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
