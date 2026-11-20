<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../../vendor/autoload.php'; // Composer autoload
require_once __DIR__ . '/client_ip.php';                   // client_ip()-Helper global verfügbar

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../..');
$dotenv->load();

$dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log('db.php connection failed: ' . $e->getMessage());
    http_response_code(500);
    die("Datenbankfehler");  // Nie die echte Fehlermeldung an den User
}

// DSGVO: audit_logs werden nach 90 Tagen automatisch gelöscht (probabilistisch, ~1 % pro Request)
// Im selben Zug abgelaufene Login-Tokens entfernen, sonst wachsen die Tabellen unbegrenzt.
if (random_int(1, 100) === 1) {
    try {
        $pdo->exec("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $pdo->exec("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        $pdo->exec("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
        $pdo->exec("DELETE FROM login_devices WHERE expires_at < NOW()");
    } catch (PDOException $e) {
        // Aufräumen darf nie den eigentlichen Request abbrechen
        error_log('db.php cleanup failed: ' . $e->getMessage());
    }
}
