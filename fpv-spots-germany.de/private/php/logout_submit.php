<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    die('CSRF-Fehler');
}

// Remember-Me-Token aus DB und Cookie löschen
if (!empty($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) === 2) {
        require_once __DIR__ . '/db.php';
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        $stmt->execute([$parts[0]]);
    }
    setcookie('remember_me', '', [
        'expires'  => 1,
        'path'     => '/',
        'httponly'  => true,
        'secure'   => true,
        'samesite' => 'Lax',
    ]);
}

session_destroy();

// Redirect-Ziel: nur relative Pfade innerhalb der eigenen Seite erlauben
$redirect = $_POST['redirect'] ?? '';
if ($redirect !== '' && !preg_match('#^/[a-zA-Z0-9_]+\.php(\?[a-zA-Z0-9_=&]+)?$#', $redirect)) {
    $redirect = '';
}

header('Location: ' . ($redirect !== '' ? $redirect : '/'));
exit;