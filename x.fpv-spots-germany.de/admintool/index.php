<?php
// =============================================================
// FPV Spots Germany – Admin-Bereich: Nutzerliste
// Nur zugänglich für eingeloggte Admins (admin = 1).
// =============================================================
session_start();

// Zugriffsschutz: nicht eingeloggte Admins werden umgeleitet
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

$dsn = "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    die('Datenbankfehler');
}

// Alle Nutzer abrufen (nur id und username – kein password_hash o. Ä.)
$stmt = $pdo->query("SELECT id, username, email FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();

// Letzte Seitenbesuche pro Nutzer (neueste zuerst)
$stmtVisits = $pdo->query(
    "SELECT id, username, last_seen FROM users ORDER BY last_seen DESC, id ASC"
);
$visits = $stmtVisits->fetchAll();

$adminName = htmlspecialchars($_SESSION['admin_username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nutzerliste – Admin | FPV Spots Germany</title>
    <meta name="robots" content="noindex, nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
          crossorigin="anonymous">
    <style>
        body {
            background-color: #1a1a2e;
            color: #cdd6f4;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .admin-header {
            border-bottom: 1px solid #0f3460;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
        }
        .admin-header h1 {
            font-size: 1.5rem;
            color: #e94560;
        }
        .table-dark thead th {
            color: #e94560;
        }
    </style>
</head>
<body>

<div class="container">

    <header class="admin-header d-flex justify-content-between align-items-center">
        <h1>Admin-Bereich – Nutzerliste</h1>
        <div class="d-flex align-items-center gap-3">
            <span class="text-secondary small">Eingeloggt als <strong class="text-light"><?= $adminName ?></strong></span>
            <form method="POST" action="/">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-sm btn-outline-danger">Abmelden</button>
            </form>
        </div>
    </header>

    <main>
        <section aria-labelledby="user-list-heading">
            <h2 id="user-list-heading" class="h5 mb-3 text-secondary">
                Registrierte Nutzer (<?= count($users) ?>)
            </h2>

            <?php if (empty($users)): ?>
                <p class="text-warning">Keine Nutzer gefunden.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-striped table-hover align-middle">
                        <thead>
                            <tr>
                                <th scope="col">User-ID</th>
                                <th scope="col">Benutzername</th>
                                <th scope="col">E-Mail</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= (int)$user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section aria-labelledby="last-visit-heading" class="mt-5">
            <h2 id="last-visit-heading" class="h5 mb-3 text-secondary">
                Letzte Seitenbesuche
            </h2>

            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th scope="col">User-ID</th>
                            <th scope="col">Benutzername</th>
                            <th scope="col">Zuletzt gesehen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $v): ?>
                            <tr>
                                <td><?= (int)$v['id'] ?></td>
                                <td><?= htmlspecialchars($v['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php if ($v['last_seen']): ?>
                                        <time datetime="<?= htmlspecialchars($v['last_seen'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(
                                                date('d.m.Y H:i', strtotime($v['last_seen'])),
                                                ENT_QUOTES, 'UTF-8'
                                            ) ?> Uhr
                                        </time>
                                    <?php else: ?>
                                        <span class="text-secondary fst-italic">noch nicht besucht</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

</div>

</body>
</html>
