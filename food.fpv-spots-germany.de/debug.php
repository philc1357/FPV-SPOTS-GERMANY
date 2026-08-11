<?php
declare(strict_types=1);

echo "<h2>Debug-Info</h2>";

$vendor_path = __DIR__ . '/../vendor/autoload.php';
$env_path = __DIR__ . '/../.env';

echo "Dir: " . __DIR__ . "<br>";
echo "Vendor-Pfad: $vendor_path<br>";
echo "Vendor existiert: " . (file_exists($vendor_path) ? "JA" : "NEIN") . "<br>";
echo ".env-Pfad: $env_path<br>";
echo ".env existiert: " . (file_exists($env_path) ? "JA" : "NEIN") . "<br>";

if (file_exists($env_path)) {
    $lines = file($env_path);
    echo "<h3>.env Inhalt (erste 10 Zeilen):</h3>";
    echo "<pre>";
    for ($i = 0; $i < min(10, count($lines)); $i++) {
        if (strpos($lines[$i], 'PASS') === false) {
            echo htmlspecialchars($lines[$i]);
        } else {
            echo "***PASSWORD*** \n";
        }
    }
    echo "</pre>";
}

if (file_exists($vendor_path)) {
    echo "<h3>Vendor lädt:</h3>";
    require_once $vendor_path;
    echo "Vendor erfolgreich geladen<br>";
}
?>
