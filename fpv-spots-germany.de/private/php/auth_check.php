<?php
// =============================================================
// Auto-Login über Remember-Me-Cookie
// Wird VOR session_start()-abhängiger Logik eingebunden.
// Voraussetzung: session_start() wurde bereits aufgerufen.
// =============================================================

if (!isset($_SESSION['user_id']) && !empty($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) === 2) {
        [$selector, $validator] = $parts;

        // DB-Verbindung holen (nur wenn noch nicht geladen)
        if (!isset($pdo)) {
            require_once __DIR__ . '/db.php';
        }

        $stmt = $pdo->prepare(
            "SELECT rt.id, rt.validator_hash, rt.user_id, rt.expires_at,
                    u.username, u.admin
             FROM remember_tokens rt
             JOIN users u ON rt.user_id = u.id
             WHERE rt.selector = ? AND rt.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if ($token && hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            // Auto-Login erfolgreich
            session_regenerate_id(true);
            $_SESSION['user_id']  = (int)$token['user_id'];
            $_SESSION['username'] = $token['username'];
            $_SESSION['is_admin'] = (int)$token['admin'] === 1;

            // Token rotieren: alten löschen, neuen erstellen (atomar via Transaktion)
            $newSelector  = bin2hex(random_bytes(16));
            $newValidator = bin2hex(random_bytes(32));
            $expiresAt    = date('Y-m-d H:i:s', time() + 30 * 86400);

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE id = ?");
                $stmt->execute([$token['id']]);

                $stmt = $pdo->prepare(
                    "INSERT INTO remember_tokens (selector, validator_hash, user_id, expires_at)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $newSelector,
                    hash('sha256', $newValidator),
                    $token['user_id'],
                    $expiresAt,
                ]);

                $pdo->commit();

                // Cookie erst nach erfolgreichem DB-Commit setzen
                setcookie('remember_me', "$newSelector:$newValidator", [
                    'expires'  => time() + 30 * 86400,
                    'path'     => '/',
                    'httponly'  => true,
                    'secure'   => true,
                    'samesite' => 'Lax',
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                // Alter Cookie bleibt erhalten; Fehler intern loggen
                error_log('remember_me token rotation failed: ' . $e->getMessage());
            }
        } else {
            // Ungültiges Cookie entfernen
            setcookie('remember_me', '', [
                'expires'  => 1,
                'path'     => '/',
                'httponly'  => true,
                'secure'   => true,
                'samesite' => 'Lax',
            ]);
        }
    }
}
