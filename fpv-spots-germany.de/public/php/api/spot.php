<?php
// =============================================================
// API: GET  /public/php/api/spot.php?id=X  – Einzelnen Spot laden
//      POST /public/php/api/spot.php?id=X  – Spot bearbeiten (_method=PUT)
//      POST /public/php/api/spot.php?id=X  – Spot löschen  (_method=DELETE)
//
// Hinweis: HTML-Formulare und die Fetch-API senden kein PUT/DELETE.
// Wir nutzen das _method-Override-Muster über POST.
// =============================================================
session_start();

require_once __DIR__ . '/../../../private/php/core/db.php';

header('Content-Type: application/json; charset=utf-8');

// Spot-ID aus Query-String holen und als Integer kasten
$spotId = (int)($_GET['id'] ?? 0);
if ($spotId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Gültige Spot-ID erforderlich.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ---------------------------------------------------------------
// GET – Einzelnen Spot zurückgeben
// ---------------------------------------------------------------
if ($method === 'GET') {
    $stmt = $pdo->prepare(
        "SELECT s.id, s.user_id, s.name, s.description,
                s.latitude, s.longitude, s.spot_type, s.difficulty,
                s.created_at, u.username
         FROM spots s
         JOIN users u ON s.user_id = u.id
         WHERE s.id = ?"
    );
    $stmt->execute([$spotId]);
    $spot = $stmt->fetch();

    if (!$spot) {
        http_response_code(404);
        echo json_encode(['error' => 'Spot nicht gefunden.']);
        exit;
    }

    $imgStmt = $pdo->prepare(
        "SELECT filename FROM spot_images WHERE spot_id = ? ORDER BY created_at DESC LIMIT 3"
    );
    $imgStmt->execute([$spotId]);
    $imgs = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
    $spot['images']          = array_slice($imgs, 0, 2);
    $spot['has_more_images'] = count($imgs) > 2;

    echo json_encode($spot, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------------------------------------------------------------
// POST mit _method-Override → PUT oder DELETE
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
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Ungültiger CSRF-Token.']);
        exit;
    }

    $override = strtoupper(trim($_POST['_method'] ?? ''));

    $isAdmin = !empty($_SESSION['is_admin']);

    // -----------------------------------------------------------
    // PUT – Spot aktualisieren
    // -----------------------------------------------------------
    if ($override === 'PUT') {

        // Besitzer oder Admin prüfen
        $stmt = $pdo->prepare("SELECT user_id FROM spots WHERE id = ?");
        $stmt->execute([$spotId]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Spot nicht gefunden.']);
            exit;
        }
        if ((int)$row['user_id'] !== (int)$_SESSION['user_id'] && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Keine Berechtigung.']);
            exit;
        }

        // Eingaben holen
        $name        = trim($_POST['name']        ?? '');
        $description = trim($_POST['description'] ?? '');
        $spotType    = trim($_POST['spot_type']   ?? '');
        $difficulty  = trim($_POST['difficulty']  ?? '');

        $allowedTypes = ['Bando', 'Feld', 'Gebirge', 'Park', 'Wald', 'Windpark', 'Sonstige'];
        $allowedDiff  = ['Anfänger', 'Mittel', 'Fortgeschritten', 'Profi'];

        if (empty($name) || strlen($name) > 100) {
            http_response_code(400);
            echo json_encode(['error' => 'Name ist erforderlich (max. 100 Zeichen).']);
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

        try {
            $stmt = $pdo->prepare(
                "UPDATE spots SET name = ?, description = ?, spot_type = ?, difficulty = ?
                 WHERE id = ?"
            );
            $stmt->execute([$name, $description, $spotType, $difficulty, $spotId]);

            // Aktualisierter Spot zurückgeben
            $stmt = $pdo->prepare(
                "SELECT s.id, s.user_id, s.name, s.description,
                        s.latitude, s.longitude, s.spot_type, s.difficulty,
                        s.created_at, u.username
                 FROM spots s
                 JOIN users u ON s.user_id = u.id
                 WHERE s.id = ?"
            );
            $stmt->execute([$spotId]);
            echo json_encode($stmt->fetch(), JSON_UNESCAPED_UNICODE);

        } catch (PDOException $e) {
            error_log('spot.php PUT error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Datenbankfehler.']);
        }
        exit;
    }

    // -----------------------------------------------------------
    // DELETE – Spot löschen
    // -----------------------------------------------------------
    if ($override === 'DELETE') {

        // Besitzer oder Admin prüfen
        $stmt = $pdo->prepare("SELECT user_id FROM spots WHERE id = ?");
        $stmt->execute([$spotId]);
        $row = $stmt->fetch();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Spot nicht gefunden.']);
            exit;
        }
        if ((int)$row['user_id'] !== (int)$_SESSION['user_id'] && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['error' => 'Keine Berechtigung.']);
            exit;
        }

        try {
            // Bilder vom Dateisystem entfernen, bevor der DB-Cascade die
            // Referenzen löscht (sonst entstehen Orphan-Files in /uploads/imgs/).
            $imgStmt = $pdo->prepare("SELECT filename FROM spot_images WHERE spot_id = ?");
            $imgStmt->execute([$spotId]);
            $uploadDir = __DIR__ . '/../../uploads/imgs/';
            foreach ($imgStmt->fetchAll() as $img) {
                $path = $uploadDir . $img['filename'];
                if (is_file($path)) {
                    @unlink($path);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM spots WHERE id = ?");
            $stmt->execute([$spotId]);
            echo json_encode(['success' => true]);

        } catch (PDOException $e) {
            error_log('spot.php DELETE error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Datenbankfehler.']);
        }
        exit;
    }

    // Unbekannter Override
    http_response_code(400);
    echo json_encode(['error' => 'Unbekannte Methode.']);
    exit;
}

// ---------------------------------------------------------------
// Andere Methoden ablehnen
// ---------------------------------------------------------------
http_response_code(405);
header('Allow: GET, POST');
echo json_encode(['error' => 'Methode nicht erlaubt.']);
