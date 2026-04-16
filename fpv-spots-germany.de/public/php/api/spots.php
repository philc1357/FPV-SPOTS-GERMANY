<?php
// =============================================================
// API: GET /public/php/api/spots.php   – Alle Spots laden
//      POST /public/php/api/spots.php  – Neuen Spot erstellen
// =============================================================
session_start();

// Datenbankverbindung (von public/php/api/ → 3 Ebenen hoch → ressources/ → private/php/)
require_once __DIR__ . '/../../../private/php/db.php';

header('Content-Type: application/json; charset=utf-8');

// Nur GET und POST erlaubt
$method = $_SERVER['REQUEST_METHOD'];

// ---------------------------------------------------------------
// GET – Alle Spots als JSON zurückgeben
// ---------------------------------------------------------------
if ($method === 'GET') {
    $filterUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($filterUserId) {
        $stmt = $pdo->prepare(
            "SELECT s.id, s.user_id, s.name, s.description,
                    s.latitude, s.longitude, s.spot_type, s.difficulty,
                    s.parking_info, s.created_at, u.username
             FROM spots s
             JOIN users u ON s.user_id = u.id
             WHERE s.user_id = ?
             ORDER BY s.created_at DESC"
        );
        $stmt->execute([$filterUserId]);
    } else {
        $stmt = $pdo->query(
            "SELECT s.id, s.user_id, s.name, s.description,
                    s.latitude, s.longitude, s.spot_type, s.difficulty,
                    s.parking_info, s.created_at, u.username
             FROM spots s
             JOIN users u ON s.user_id = u.id
             ORDER BY s.created_at DESC"
        );
    }

    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------
// POST – Neuen Spot erstellen (Login erforderlich)
// ---------------------------------------------------------------
if ($method === 'POST') {

    // Authentifizierung prüfen
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Nicht eingeloggt.']);
        exit;
    }

    // CSRF-Token prüfen
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Ungültiger CSRF-Token.']);
        exit;
    }

    // Eingaben holen und bereinigen
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $spotType    = trim($_POST['spot_type']   ?? '');
    $difficulty  = trim($_POST['difficulty']  ?? '');
    $latitude    = $_POST['latitude']  ?? '';
    $longitude   = $_POST['longitude'] ?? '';

    // Erlaubte Enum-Werte (serverseitige Validierung – nie nur Client vertrauen!)
    $allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Verein', 'Wasser', 'Sonstige'];
    $allowedDiff  = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];

    // Validierung
    if (empty($name) || strlen($name) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Name ist erforderlich und darf maximal 100 Zeichen lang sein.']);
        exit;
    }
    if (!in_array($spotType, $allowedTypes, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültiger Spot-Typ.']);
        exit;
    }
    if (!in_array($difficulty, $allowedDiff, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Schwierigkeit.']);
        exit;
    }
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ungültige Koordinaten.']);
        exit;
    }
    $lat = (float)$latitude;
    $lng = (float)$longitude;
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        http_response_code(400);
        echo json_encode(['error' => 'Koordinaten ausserhalb des gültigen Bereichs.']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    try {
        // Spot einfügen
        $stmt = $pdo->prepare(
            "INSERT INTO spots (user_id, name, description, latitude, longitude, spot_type, difficulty)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$userId, $name, $description, $lat, $lng, $spotType, $difficulty]);

        $spotId = (int)$pdo->lastInsertId();

        // Neu angelegten Spot mit Username zurückgeben
        $stmt = $pdo->prepare(
            "SELECT s.id, s.user_id, s.name, s.description,
                    s.latitude, s.longitude, s.spot_type, s.difficulty,
                    s.parking_info, s.created_at, u.username
             FROM spots s
             JOIN users u ON s.user_id = u.id
             WHERE s.id = ?"
        );
        $stmt->execute([$spotId]);
        $spot = $stmt->fetch();

        http_response_code(201);
        echo json_encode($spot, JSON_UNESCAPED_UNICODE);

    } catch (PDOException $e) {
        error_log('spots.php POST error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Datenbankfehler. Bitte erneut versuchen.']);
    }
    exit;
}

// ---------------------------------------------------------------
// Andere Methoden ablehnen
// ---------------------------------------------------------------
http_response_code(405);
header('Allow: GET, POST');
echo json_encode(['error' => 'Methode nicht erlaubt.']);
