<?php
declare(strict_types=1);
// =============================================================
// Spot erstellen – klassisches Form-POST aus index.php
// =============================================================
require_once __DIR__ . "/../core/session_init.php";

require_once __DIR__ . '/../core/db.php';

// Nur POST akzeptieren
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

// Authentifizierung pruefen
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// CSRF-Token pruefen
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

// Eingaben holen und bereinigen
$name        = trim($_POST['name']        ?? '');
$description = trim($_POST['description'] ?? '');
$spotType    = trim($_POST['spot_type']   ?? '');
$difficulty   = trim($_POST['difficulty']  ?? '');
$parkingInfo  = trim($_POST['parking_info'] ?? '');
$latitude     = $_POST['latitude']  ?? '';
$longitude    = $_POST['longitude'] ?? '';

// Parkinfo: leer oder nicht gesendet => "Unbekannt"
if ($parkingInfo === '') {
    $parkingInfo = 'Unbekannt';
}

// Coptergröße: Whitelist-Filterung der Mehrfachauswahl
$allowedSizes  = ['Tinywhoop', '2-3 Zoll', '3-5 Zoll', '5+ Zoll'];
$rawSizes      = is_array($_POST['copter_size'] ?? null) ? $_POST['copter_size'] : [];
$copterSize    = implode(',', array_values(array_intersect($rawSizes, $allowedSizes)));

// Erlaubte Enum-Werte (muessen mit Formular UND DB-Schema uebereinstimmen)
$allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Wald', 'Windpark', 'Sonstige'];
$allowedDiff  = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];

// Validierung
if (empty($name) || strlen($name) > 100 || strlen($name) < 5) {
    $error = 'Name ist erforderlich. Muss mindestens 5 und darf maximal 100 Zeichen lang sein.';
}
elseif (empty($description) || strlen($description) < 10) {
    $error = 'Schreibe bitte eine kurze Beschreibung zu deinem Spot.';
}
elseif (strlen($description) > 2000) {
    $error = 'Beschreibung darf maximal 2000 Zeichen lang sein.';
}
elseif (!in_array($spotType, $allowedTypes, true)) {
    $error = 'Ungültiger Spot-Typ.';
}
elseif (!in_array($difficulty, $allowedDiff, true)) {
    $error = 'Ungültige Schwierigkeit.';
}
elseif (strlen($parkingInfo) > 500) {
    $error = 'Parkinfo darf maximal 500 Zeichen lang sein.';
}
elseif (!is_numeric($latitude) || !is_numeric($longitude)) {
    $error = 'Ungültige Koordinaten.';
}
else {
    $lat = (float)$latitude;
    $lng = (float)$longitude;

    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        $error = 'Koordinaten ausserhalb des gültigen Bereichs.';
    }
}

// Bei Fehler: zurueck zur Karte
if (isset($error)) {
    // Fehler in Session speichern fuer Anzeige
    $_SESSION['spot_error'] = $error;
    header('Location: /');
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $stmt = $pdo->prepare(
        "INSERT INTO spots (user_id, name, description, latitude, longitude, spot_type, difficulty, parking_info, copter_size)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$userId, $name, $description, $lat, $lng, $spotType, $difficulty, $parkingInfo, $copterSize]);

    // Audit-Log
    $logSql = "INSERT INTO audit_logs (user_id, action, ip_address) VALUES (?, 'SPOT_CREATED', ?)";
    $pdo->prepare($logSql)->execute([$userId, client_ip()]);

    // Legende-Cookies aktualisieren: Typ und Schwierigkeit des neuen Spots aktivieren,
    // damit der Marker nach dem Redirect nicht durch den Filter versteckt wird.
    $allLegendTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Verein', 'Wasser', 'Sonstige'];
    $allLegendDiffs = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];
    $legendTypes = isset($_COOKIE['legend_types'])
        ? array_values(array_intersect(explode(',', $_COOKIE['legend_types']), $allLegendTypes))
        : $allLegendTypes;
    $legendDiffs = isset($_COOKIE['legend_diffs'])
        ? array_values(array_intersect(explode(',', $_COOKIE['legend_diffs']), $allLegendDiffs))
        : $allLegendDiffs;

    if (!in_array($spotType, $legendTypes, true)) {
        $legendTypes[] = $spotType;
    }
    if (!in_array($difficulty, $legendDiffs, true)) {
        $legendDiffs[] = $difficulty;
    }

    $cookieExpires = !empty($_COOKIE['remember_me']) ? time() + 30 * 86400 : 0;
    $legendCookieOpts = [
        'expires'  => $cookieExpires,
        'path'     => '/',
        'httponly' => true,
        'secure'   => true,
        'samesite' => 'Lax',
    ];
    setcookie('legend_types', implode(',', $legendTypes), $legendCookieOpts);
    setcookie('legend_diffs', implode(',', $legendDiffs), $legendCookieOpts);

    header('Location: /');
    exit;

} catch (PDOException $e) {
    error_log('spot_submit.php error: ' . $e->getMessage());
    $_SESSION['spot_error'] = 'Datenbankfehler. Bitte erneut versuchen.';
    header('Location: /');
    exit;
}
