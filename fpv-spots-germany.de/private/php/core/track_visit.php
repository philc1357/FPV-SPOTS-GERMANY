<?php
declare(strict_types=1);
// =============================================================
// Besucherzählung – Tagesstatistik für das Admintool
//
// Zählt pro Kalendertag zwei Werte in der Tabelle `site_visits`:
//   - page_views      : jeder Seitenaufruf
//   - unique_visitors : jede Session einmal pro Tag
//
// Datenschutz: Es werden AUSSCHLIESSLICH zwei Zähler pro Tag
// gespeichert – keine IP, kein User-Agent, keine User-ID, kein
// Zeitstempel einzelner Aufrufe. Die Unterscheidung "neuer
// Besucher" passiert über einen Tages-Cookie (fpv_counted_today,
// verfällt zum Tagesende) statt über die PHP-Session, die beim
// Browser-Neustart am selben Tag sonst verlorenginge.
//
// Robustheit: Das Tracking darf niemals eine Seite kaputtmachen.
// Jeder Fehler (DB weg, Tabelle fehlt, .env unlesbar) führt zu
// einem stillen Abbruch mit error_log(), nie zu einer Ausgabe.
// =============================================================

if (!function_exists('track_visit')) {
    /**
     * Zählt den aktuellen Seitenaufruf. $existingPdo wird
     * wiederverwendet, falls die Seite ohnehin schon eine
     * DB-Verbindung geöffnet hat.
     */
    function track_visit(?PDO $existingPdo = null): void
    {
        // --- Bot-Filter -------------------------------------------------
        // Crawler halten keine Session und würden sonst bei jedem Request
        // als neuer "Besucher" gezählt – das verfälscht die Statistik.
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($userAgent === ''
            || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|headless|facebookexternalhit|claude-user|inspectiontool|archiver|omgili|externalagent/i', $userAgent)
        ) {
            return;
        }

        // Ohne Session ist der Admin-Ausschluss unten nicht prüfbar → gar
        // nicht zählen (Unique-Erkennung selbst hängt nur noch am Cookie).
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // --- Admin-Ausschluss --------------------------------------------
        // Wie beim Umami-Tracking (analytics.php::umami_enabled()) soll der
        // eigene Traffic als eingeloggter Admin nicht in die Statistik
        // einfließen.
        if (!empty($_SESSION['is_admin'])) {
            return;
        }

        // --- Unique-Erkennung über Tages-Cookie --------------------------
        // Eigener, von PHPSESSID entkoppelter Cookie mit Ablauf zum
        // Tagesende: verhindert Doppelzählung bei Browser-Neustarts am
        // selben Tag (Session-Cookie verfällt sonst beim Schließen), ohne
        // über den Tag hinaus wiederzuerkennen. Reiner Flag-Wert ("1"),
        // keine Zufalls-ID – dient nicht der Wiedererkennung einzelner
        // Personen, nur der Tages-Deduplizierung. Kein Consent nach
        // TTDSG §25 nötig, da kein mehrtägiges Profil entsteht.
        $isNew = empty($_COOKIE['fpv_counted_today']) ? 1 : 0;

        // --- DB-Verbindung ----------------------------------------------
        // Bewusst NICHT db.php einbinden: dessen Fehlerpfad ruft die()
        // auf und würde statische Seiten (Impressum, Login …) bei einem
        // DB-Ausfall mitten im HTML abbrechen.
        $pdo = $existingPdo;
        if ($pdo === null) {
            try {
                if (empty($_ENV['DB_HOST'])) {
                    require_once __DIR__ . '/../../../../vendor/autoload.php';
                    Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../..')->safeLoad();
                }
                $pdo = new PDO(
                    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
                    $_ENV['DB_USER'],
                    $_ENV['DB_PASS'],
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
            } catch (Throwable $e) {
                error_log('track_visit.php connection failed: ' . $e->getMessage());
                return;
            }
        }

        // --- Zähler hochsetzen (ein einziger Upsert) --------------------
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO site_visits (visit_date, page_views, unique_visitors)
                 VALUES (CURDATE(), 1, :is_new)
                 ON DUPLICATE KEY UPDATE
                     page_views      = page_views + 1,
                     unique_visitors = unique_visitors + VALUES(unique_visitors)"
            );
            $stmt->execute([':is_new' => $isNew]);

            // Cookie erst nach erfolgreichem Schreiben setzen, damit ein
            // Fehlschlag den Besucher nicht dauerhaft "verschluckt".
            if ($isNew === 1) {
                setcookie('fpv_counted_today', '1', [
                    'expires'  => strtotime('tomorrow'),
                    'path'     => '/',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
        } catch (PDOException $e) {
            error_log('track_visit.php insert failed: ' . $e->getMessage());
        }
    }
}
