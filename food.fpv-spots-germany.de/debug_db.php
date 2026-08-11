<?php
declare(strict_types=1);

echo "<h2>DB Debug</h2>";

// Vendor laden
require_once __DIR__ . '/../vendor/autoload.php';

echo "Vendor geladen<br>";

// Dotenv laden
$env_path = __DIR__ . '/..';
echo "ENV-Path: $env_path<br>";

$dotenv = Dotenv\Dotenv::createImmutable($env_path);
$dotenv->load();

echo "Dotenv geladen<br>";

// Umgebungsvariablen überprüfen
echo "<h3>Umgebungsvariablen:</h3>";
echo "FOOD_DB_HOST: " . ($_ENV['FOOD_DB_HOST'] ?? 'FEHLT') . "<br>";
echo "FOOD_DB_NAME: " . ($_ENV['FOOD_DB_NAME'] ?? 'FEHLT') . "<br>";
echo "FOOD_DB_USER: " . ($_ENV['FOOD_DB_USER'] ?? 'FEHLT') . "<br>";
echo "FOOD_DB_PASS: " . (isset($_ENV['FOOD_DB_PASS']) ? '***' : 'FEHLT') . "<br>";

// DB-Connection versuchen
echo "<h3>DB-Connection:</h3>";

try {
    $dsn = "mysql:host={$_ENV['FOOD_DB_HOST']};dbname={$_ENV['FOOD_DB_NAME']};charset=utf8mb4";
    echo "DSN: $dsn<br>";

    $pdo = new PDO($dsn, $_ENV['FOOD_DB_USER'], $_ENV['FOOD_DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    echo "<strong style='color:green;'>DB-Connection erfolgreich!</strong><br>";

} catch (PDOException $e) {
    echo "<strong style='color:red;'>DB-Fehler: " . htmlspecialchars($e->getMessage()) . "</strong><br>";
}
?>
