<?php
declare(strict_types=1);
if (!$isLoggedIn):
    $registerFlash = $_SESSION['register_flash'] ?? null;
    unset($_SESSION['register_flash']);
?>
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="registerModalLabel">Registrieren</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal" aria-label="Schliessen"></button>
            </div>
            <div class="modal-body">
                <?php if ($registerFlash): ?>
                    <div class="alert alert-<?= htmlspecialchars($registerFlash['type'], ENT_QUOTES, 'UTF-8') ?> small" role="alert">
                        <?= htmlspecialchars($registerFlash['msg'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <form id="passwordForm" action="/private/php/auth/register_submit.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <input type="text" name="username"
                               class="form-control bg-secondary text-light border-0"
                               placeholder="Benutzername" minlength="5" maxlength="50"
                               autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <input type="email" name="email"
                               class="form-control bg-secondary text-light border-0"
                               placeholder="Email" minlength="10" maxlength="100"
                               autocomplete="email" required>
                    </div>
                    <div class="mb-3">
                        <input id="password" type="password" name="password_field1"
                               class="form-control bg-secondary text-light border-0"
                               placeholder="Passwort" minlength="8" maxlength="50"
                               autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <input id="password_confirm" type="password" name="password"
                               class="form-control bg-secondary text-light border-0"
                               placeholder="Passwort wiederholen" maxlength="50"
                               autocomplete="new-password" required>
                        <div id="passwordError" style="color: #ff6b6b; display: none; margin-top: 5px;" class="small">
                            Die Passwörter stimmen nicht überein.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" value="1" required
                               style="accent-color: #198754;">
                        <label class="form-check-label small" for="terms">
                            Ich habe die <a href="/nutzungsbedingungen.php" target="_blank" class="text-success fw-semibold">Nutzungsbedingungen</a> gelesen und akzeptiere sie. *
                        </label>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2">Registrieren</button>
                </form>
                <hr class="border-secondary">
                <p class="text-center mb-0 small">
                    Bereits registriert?
                    <a href="#" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#loginModal" class="text-success">Jetzt einloggen</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script src="/private/js/password_confirm.js"></script>
<script>
(function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('showRegister') === '1') {
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('registerModal');
            if (el && window.bootstrap) {
                new bootstrap.Modal(el).show();
            }
            params.delete('showRegister');
            var qs = params.toString();
            var url = window.location.pathname + (qs ? '?' + qs : '') + window.location.hash;
            window.history.replaceState({}, '', url);
        });
    }
})();
</script>
<?php endif; ?>
