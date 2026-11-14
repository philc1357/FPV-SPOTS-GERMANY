<?php
declare(strict_types=1);
// =============================================================
// FPV Spots Germany – Dashboard / Profilbereich
// =============================================================
require_once __DIR__ . "/../../../private/php/core/session_init.php";
require_once __DIR__ . '/../../../private/php/core/auth_check.php';

// Eingeloggt-Check zuerst
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}

// CSRF-Token für Formulare auf dieser Seite
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../../private/php/core/db.php';
require_once __DIR__ . '/../../../private/php/core/image_utils.php';

$isLoggedIn = true;
$userId     = (int)$_SESSION['user_id'];
$username   = htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8');
$csrfToken  = $_SESSION['csrf_token'];

// last_seen maximal alle 5 Minuten aktualisieren
if (empty($_SESSION['last_seen_ts']) || time() - $_SESSION['last_seen_ts'] > 300) {
    $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id = ?")->execute([$userId]);
    $_SESSION['last_seen_ts'] = time();
}

// Profil-Daten laden
$stmt = $pdo->prepare("SELECT username, email, bio, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData) {
    session_destroy();
    header('Location: /');
    exit;
}

// Eigene Spots laden (neueste zuerst)
$stmt = $pdo->prepare(
    "SELECT s.id, s.name, s.spot_type, s.difficulty, s.created_at,
            (SELECT si.filename FROM spot_images si WHERE si.spot_id = s.id ORDER BY si.created_at DESC LIMIT 1) AS cover
     FROM spots s
     WHERE s.user_id = ?
     ORDER BY s.created_at DESC"
);
$stmt->execute([$userId]);
$mySpots = $stmt->fetchAll();

// Favorisierte Spots laden (neueste Favorisierung zuerst)
$stmt = $pdo->prepare(
    "SELECT s.id, s.name, s.spot_type, s.difficulty, sf.created_at AS favorited_at,
            (SELECT si.filename FROM spot_images si WHERE si.spot_id = s.id ORDER BY si.created_at DESC LIMIT 1) AS cover
     FROM spot_favorites sf
     JOIN spots s ON sf.spot_id = s.id
     WHERE sf.user_id = ?
     ORDER BY sf.created_at DESC"
);
$stmt->execute([$userId]);
$favoriteSpots = $stmt->fetchAll();

// Anzahl eigener Kommentare (Kennzahl im Hero)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
$stmt->execute([$userId]);
$commentCount = (int)$stmt->fetchColumn();

// -------------------------------------------------------------
// Anzeige-Helfer
// -------------------------------------------------------------
$e = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$diffColors = [
    'Anfänger' => 'success', 'Mittel' => 'warning',
    'Fortgeschritten' => 'danger', 'Profi' => 'dark',
];
$typeColors = [
    'Bando'    => '#4b4e5a', 'Feld'     => '#ffe224',
    'Gebirge'  => '#b1602d', 'Park'     => '#3f9826',
    'Wald'     => '#2d6a4f', 'Windpark' => '#74c2e0',
    'Sonstige' => '#ffffff',
];

$initials = static function (string $name): string {
    $clean = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name) ?? '';
    $parts = preg_split('/\s+/', trim($clean)) ?: [];
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    if (mb_strlen($ini) < 2 && isset($parts[0])) {
        $ini = mb_strtoupper(mb_substr($parts[0], 0, 2));
    }
    return $ini !== '' ? $ini : '?';
};

$uploadDir   = __DIR__ . '/../../uploads/imgs/';
$memberSince = $userData['created_at'] ? date('d.m.Y', strtotime((string)$userData['created_at'])) : 'Unbekannt';
$memberYear  = $userData['created_at'] ? date('Y', strtotime((string)$userData['created_at'])) : '–';
$userName    = (string)$userData['username'];

/**
 * Gibt eine Spot-Kachel (Foto, Typ, Schwierigkeit, Aktionen) aus.
 * $mode 'own' = eigener Spot (Bearbeiten/Löschen), 'fav' = Favorit (entfernen).
 */
$renderSpotCard = static function (array $s, string $mode) use ($e, $diffColors, $typeColors, $uploadDir, $csrfToken): void {
    $id        = (int)$s['id'];
    $typeColor = $typeColors[(string)$s['spot_type']] ?? '#fd7e14';
    $diffColor = $diffColors[(string)$s['difficulty']] ?? 'secondary';
    $date      = $mode === 'fav' ? ($s['favorited_at'] ?? null) : ($s['created_at'] ?? null);
    $dateText  = $date ? date('d.m.Y', strtotime((string)$date)) : 'Unbekannt';
    $coverFile = !empty($s['cover']) ? basename((string)$s['cover']) : '';
    $hasCover  = $coverFile !== '' && is_file($uploadDir . $coverFile);
    ?>
    <li class="db-spot" id="spot-row-<?= $id ?>" style="--sd-type: <?= $e($typeColor) ?>">
        <a class="db-spot-cover" href="/public/php/spot_detail.php?id=<?= $id ?>"
           aria-label="Details zu <?= $e($s['name']) ?>">
            <?php if ($hasCover): ?>
                <img src="<?= $e(thumb_url('/public/uploads/imgs/' . $coverFile)) ?>"
                     alt="" loading="lazy" decoding="async" data-hide-on-error="self">
            <?php else: ?>
                <span class="db-spot-ph" aria-hidden="true"><i class="bi bi-pin-map"></i></span>
            <?php endif; ?>
        </a>
        <div class="db-spot-body">
            <p class="db-spot-type">
                <span class="sd-type-dot" aria-hidden="true"></span><?= $e($s['spot_type'] ?: 'FPV') ?>
            </p>
            <h3 class="db-spot-name">
                <a href="/public/php/spot_detail.php?id=<?= $id ?>"><?= $e($s['name']) ?></a>
            </h3>
            <div class="fsg-tags">
                <span class="fsg-tag fsg-tag--diff-<?= $e($diffColor) ?>">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i><?= $e($s['difficulty'] ?: 'Unbekannt') ?>
                </span>
                <span class="fsg-meta">
                    <i class="bi <?= $mode === 'fav' ? 'bi-heart-fill' : 'bi-calendar3' ?>" aria-hidden="true"></i>
                    <?= $e($dateText) ?>
                </span>
            </div>
        </div>
        <div class="db-spot-tools">
            <a href="/?spot=<?= $id ?>" class="fsg-mini-btn" title="Auf der Karte anzeigen" aria-label="Auf der Karte anzeigen">
                <i class="bi bi-map" aria-hidden="true"></i>
            </a>
            <?php if ($mode === 'own'): ?>
                <a href="/public/php/edit_spot.php?id=<?= $id ?>" class="fsg-mini-btn" title="Spot bearbeiten" aria-label="Spot bearbeiten">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                </a>
                <button type="button" class="fsg-mini-btn fsg-mini-btn--danger" title="Spot löschen" aria-label="Spot löschen"
                        data-action="dash-delete-spot" data-spot-id="<?= $id ?>">
                    <i class="bi bi-trash3" aria-hidden="true"></i>
                </button>
            <?php else: ?>
                <form method="POST" action="/private/php/spots/favorite_submit.php" class="d-inline"
                      data-confirm="Aus Favoriten entfernen?">
                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                    <input type="hidden" name="spot_id" value="<?= $id ?>">
                    <input type="hidden" name="redirect" value="/public/php/dashboard.php">
                    <button type="submit" class="fsg-mini-btn fsg-mini-btn--danger" title="Als Favorit entfernen" aria-label="Als Favorit entfernen">
                        <i class="bi bi-heart-fill" aria-hidden="true"></i>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </li>
    <?php
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil – FPV Spots Germany</title>
    <meta name="description" content="Verwalte deine FPV-Spots, Favoriten und Kontodaten.">
    <meta name="robots" content="noindex, nofollow">
    <meta property="og:title" content="Profil – FPV Spots Germany">
    <meta property="og:description" content="Verwalte deine FPV-Spots, Favoriten und Kontodaten.">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="de_DE">
    <link rel="canonical" href="https://fpv-spots-germany.de/public/php/dashboard.php">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/head_assets.php'; ?>
    <link rel="stylesheet" href="<?= asset('/public/css/spot_detail.css') ?>">
    <link rel="stylesheet" href="<?= asset('/public/css/dashboard.css') ?>">
</head>
<body class="page-dash">

<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="db" data-bs-theme="dark">

    <!-- ====================================================
         Brotkrumen
    ==================================================== -->
    <nav aria-label="Brotkrumen" class="db-topline">
        <ol class="sd-crumbs">
            <li><a href="/">Karte</a></li>
            <li aria-current="page">Profil</li>
        </ol>
    </nav>

    <!-- ====================================================
         Hero: Avatar, Name, Aktionen, Kennzahlen
    ==================================================== -->
    <section class="db-hero" aria-labelledby="profilTitel">
        <div class="db-hero-head">
            <div class="db-hero-text">
                <p class="sd-eyebrow"><i class="bi bi-person-check" aria-hidden="true"></i>Mitglied seit <?= $e($memberSince) ?></p>
                <div class="db-title-row">
                    <h1 id="profilTitel" class="db-title"><?= $e($userName) ?></h1>
                    <span class="db-avatar" aria-hidden="true"><?= $e($initials($userName)) ?></span>
                </div>

                <!-- Über mich / Bio -->
                <div class="db-bio">
                    <div id="bioDisplay" class="fsg-lead db-bio-text">
                        <?php if (!empty($userData['bio'])): ?>
                            <?= nl2br($e($userData['bio'])) ?>
                        <?php else: ?>
                            <span class="fsg-empty">Noch keine Beschreibung vorhanden.</span>
                        <?php endif; ?>
                    </div>
                    <!-- Bio-Bearbeitungsformular (Bootstrap Collapse) -->
                    <div class="collapse" id="bioEditForm">
                        <div class="db-bio-form">
                            <div id="bioSuccessAlert" class="alert alert-success d-none py-2 mb-0 small" role="status"></div>
                            <div id="bioErrorAlert"   class="alert alert-danger  d-none py-2 mb-0 small" role="alert"></div>
                            <label for="bioTextarea" class="visually-hidden">Über mich</label>
                            <textarea id="bioTextarea" class="fsg-field"
                                      rows="5" maxlength="1000"
                                      placeholder="Beschreibe dich kurz (max. 1000 Zeichen)…"><?= $e($userData['bio'] ?? '') ?></textarea>
                            <div class="d-flex justify-content-between align-items-center">
                                <small id="bioCharCount" class="fsg-meta">0 / 1000</small>
                                <button id="bioSaveBtn" type="button" class="fsg-btn fsg-btn--primary fsg-btn--sm">
                                    <i class="bi bi-check2" aria-hidden="true"></i>Speichern
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /.db-hero-text -->
            <div class="sd-actions">
                <div class="dropdown">
                    <button class="fsg-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-gear" aria-hidden="true"></i>Konto
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark fsg-menu">
                        <li>
                            <button class="dropdown-item" id="bioEditBtn" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#bioEditForm"
                                    aria-expanded="false" aria-controls="bioEditForm">
                                <i class="bi bi-card-text me-2"></i>Beschreibung ändern
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/public/php/change_username.php"><i class="bi bi-person me-2"></i>Benutzername ändern</a></li>
                        <li><a class="dropdown-item" href="/public/php/change_email.php"><i class="bi bi-envelope me-2"></i>E-Mail ändern</a></li>
                        <li><a class="dropdown-item" href="/public/php/change_password.php"><i class="bi bi-key me-2"></i>Passwort ändern</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <ul class="sd-facts">
            <li>
                <a href="#meine-spots" class="sd-fact">
                    <span class="sd-fact-icon"><i class="bi bi-pin-map" aria-hidden="true"></i></span>
                    <span class="sd-fact-text">
                        <span class="sd-fact-label">Meine Spots</span>
                        <span class="sd-fact-value" id="factSpotCount"><?= count($mySpots) ?></span>
                    </span>
                </a>
            </li>
            <li>
                <a href="#favoriten" class="sd-fact">
                    <span class="sd-fact-icon"><i class="bi bi-heart" aria-hidden="true"></i></span>
                    <span class="sd-fact-text">
                        <span class="sd-fact-label">Favoriten</span>
                        <span class="sd-fact-value"><?= count($favoriteSpots) ?></span>
                    </span>
                </a>
            </li>
            <li>
                <span class="sd-fact">
                    <span class="sd-fact-icon"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                    <span class="sd-fact-text">
                        <span class="sd-fact-label">Kommentare</span>
                        <span class="sd-fact-value"><?= $commentCount ?></span>
                    </span>
                </span>
            </li>
            <li>
                <span class="sd-fact">
                    <span class="sd-fact-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
                    <span class="sd-fact-text">
                        <span class="sd-fact-label">Mitglied seit</span>
                        <span class="sd-fact-value"><?= $e($memberYear) ?></span>
                    </span>
                </span>
            </li>
        </ul>
    </section>

    <!-- ====================================================
         Zweispaltig: Spots & Favoriten links, Profil rechts
    ==================================================== -->
    <div class="db-layout">
        <div class="sd-main">

            <!-- ================================================
                 Meine Spots
            ================================================ -->
            <section class="fsg-card" id="meine-spots" aria-labelledby="meineSpotsTitel">
                <header class="fsg-card-head">
                    <div>
                        <p class="fsg-kicker"><span id="spotCount"><?= count($mySpots) ?></span> eingetragen</p>
                        <h2 id="meineSpotsTitel" class="fsg-h2">Meine Spots</h2>
                    </div>
                </header>

                <div id="dashSuccessAlert" class="alert alert-success d-none py-2 mb-0" role="status"></div>
                <div id="dashErrorAlert"   class="alert alert-danger  d-none py-2 mb-0" role="alert"></div>

                <?php if (empty($mySpots)): ?>
                    <p class="fsg-empty mb-0">
                        <i class="bi bi-pin-map me-1" aria-hidden="true"></i>Du hast noch keine Spots erstellt.
                        <a href="/">Zur Karte</a> und ersten Spot anlegen!
                    </p>
                <?php else: ?>
                    <ul class="db-grid">
                        <?php foreach ($mySpots as $spot) { $renderSpotCard($spot, 'own'); } ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- ================================================
                 Meine Favoriten
            ================================================ -->
            <section class="fsg-card" id="favoriten" aria-labelledby="favoritenTitel">
                <header class="fsg-card-head">
                    <div>
                        <p class="fsg-kicker"><?= count($favoriteSpots) === 1 ? '1 Spot' : count($favoriteSpots) . ' Spots' ?> gespeichert</p>
                        <h2 id="favoritenTitel" class="fsg-h2">Meine Favoriten</h2>
                    </div>
                </header>

                <?php if (empty($favoriteSpots)): ?>
                    <p class="fsg-empty mb-0">
                        <i class="bi bi-heart me-1" aria-hidden="true"></i>Du hast noch keine Spots favorisiert.
                    </p>
                <?php else: ?>
                    <ul class="db-grid">
                        <?php foreach ($favoriteSpots as $fav) { $renderSpotCard($fav, 'fav'); } ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

    </div><!-- /.db-layout -->
</main>

<!-- CSRF-Token für AJAX-Lösch-/Bio-Anfragen -->
<meta name="app-csrf-token" content="<?= $e($csrfToken) ?>">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/public/includes/foot_assets.php'; ?>

<script src="<?= asset('/public/js/pages/dashboard.js') ?>"></script>

</body>
</html>
