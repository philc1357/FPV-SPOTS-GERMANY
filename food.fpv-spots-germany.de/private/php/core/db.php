<?php
declare(strict_types=1);

// ============================================================
// PDO-Verbindung zur Food-Spots-Datenbank
// .env und Composer-Autoload werden aus dem gemeinsamen Stamm-
// verzeichnis /home/boss/source_code/fpv-spots-germany geladen.
// ============================================================

require_once __DIR__ . '/../../../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../..');
$dotenv->load();

$dsn = "mysql:host={$_ENV['FOOD_DB_HOST']};dbname={$_ENV['FOOD_DB_NAME']};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $_ENV['FOOD_DB_USER'], $_ENV['FOOD_DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('food db.php connection failed: ' . $e->getMessage());
    $pdo = null;
}
