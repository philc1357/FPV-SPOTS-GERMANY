<?php
declare(strict_types=1);
// =============================================================
// Konto-Benachrichtigungen per E-Mail
//
// Sicherheitsrelevante Änderungen am Konto werden dem Inhaber
// per Mail gemeldet – so fällt eine Übernahme durch Dritte auf.
// Mailfehler werden nur geloggt und brechen den Ablauf nie ab.
// =============================================================

// -------------------------------------------------------------
// Hinweis-Mail "Dein Passwort wurde geändert"
// $via: 'change' (im Konto geändert) oder 'reset' (per Mail-Link)
// -------------------------------------------------------------
if (!function_exists('send_password_changed_mail')) {
    function send_password_changed_mail(string $email, string $username, string $via): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            // require statt require_once: liefert bei jedem Aufruf einen
            // frischen, funktionslokalen $mailer
            require __DIR__ . '/mailer_info.php';

            $name    = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $wie     = $via === 'reset' ? 'über den „Passwort vergessen“-Link' : 'in deinen Kontoeinstellungen';
            $zeit    = date('d.m.Y \u\m H:i') . ' Uhr';
            $resetUrl = 'https://fpv-spots-germany.de/forgot_password.php';
            $kontaktUrl = 'https://fpv-spots-germany.de/kontakt.php';

            $mailer->addAddress($email);
            $mailer->isHTML(true);
            $mailer->Subject = 'Dein Passwort wurde geändert – FPV Spots Germany';
            $mailer->Body = '
                <div style="font-family:Arial,sans-serif; max-width:500px; margin:auto; padding:20px;">
                    <h2 style="color:#198754;">FPV Spots Germany</h2>
                    <p>Hallo ' . $name . ',</p>
                    <p>das Passwort deines Kontos wurde am ' . $zeit . ' ' . $wie . ' geändert.
                       Aus Sicherheitsgründen wurdest du auf allen Geräten abgemeldet.</p>
                    <p>Warst du das selbst, musst du nichts weiter tun.</p>
                    <p><strong>Warst du das nicht?</strong> Setze dein Passwort sofort zurück und melde dich bei uns:</p>
                    <p style="text-align:center; margin:30px 0;">
                        <a href="' . $resetUrl . '"
                           style="background:#198754; color:#fff; padding:12px 24px; text-decoration:none; border-radius:6px; font-weight:bold;">
                            Passwort zurücksetzen
                        </a>
                    </p>
                    <p style="color:#666; font-size:13px;">Kontakt: <a href="' . $kontaktUrl . '">' . $kontaktUrl . '</a></p>
                </div>';
            $mailer->AltBody = "Hallo $username,\n\n"
                             . "das Passwort deines Kontos wurde am $zeit $wie geändert. "
                             . "Aus Sicherheitsgründen wurdest du auf allen Geräten abgemeldet.\n\n"
                             . "Warst du das nicht? Setze dein Passwort sofort zurück:\n$resetUrl\n\n"
                             . "Kontakt: $kontaktUrl";

            $mailer->send();
        } catch (\Throwable $e) {
            error_log('send_password_changed_mail failed: ' . $e->getMessage());
        }
    }
}
