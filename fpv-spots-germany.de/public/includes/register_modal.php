<?php
declare(strict_types=1);
if (!$isLoggedIn):
    $registerFlash = $_SESSION['register_flash'] ?? null;
    unset($_SESSION['register_flash']);
?>
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
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
                        <label for="registerUsername" class="visually-hidden">Benutzername</label>
                        <input id="registerUsername" type="text" name="username"
                               class="form-control"
                               placeholder="Benutzername" minlength="5" maxlength="50"
                               autocomplete="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="registerEmail" class="visually-hidden">E-Mail</label>
                        <input id="registerEmail" type="email" name="email"
                               class="form-control"
                               placeholder="Email" minlength="10" maxlength="100"
                               autocomplete="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="visually-hidden">Passwort</label>
                        <input id="password" type="password" name="password_field1"
                               class="form-control"
                               placeholder="Passwort" minlength="8" maxlength="50"
                               autocomplete="new-password" required>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirm" class="visually-hidden">Passwort wiederholen</label>
                        <input id="password_confirm" type="password" name="password"
                               class="form-control"
                               placeholder="Passwort wiederholen" maxlength="50"
                               autocomplete="new-password" required>
                        <div id="passwordError" style="display: none;" class="small text-danger mt-1">
                            Die Passwörter stimmen nicht überein.
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" value="1" required
                              >
                        <label class="form-check-label small" for="terms">
                            Ich habe die <a href="/nutzungsbedingungen.php" target="_blank" class="fw-semibold">Nutzungsbedingungen</a> gelesen und akzeptiere sie. *
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">Registrieren</button>
                </form>
                <hr>
                <p class="text-center mb-0 small">
                    Bereits registriert?
                    <a href="#" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#loginModal" class="fw-semibold">Jetzt einloggen</a>
                </p>
            </div>
        </div>
    </div>
</div>
<script src="<?= asset('/private/js/password_confirm.js') ?>"></script>
<script src="<?= asset('/public/js/pages/register_modal.js') ?>"></script>
<?php endif; ?>
