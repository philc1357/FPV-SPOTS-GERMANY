<?php
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
