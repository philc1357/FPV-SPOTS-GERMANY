<?php
declare(strict_types=1);
// =============================================================
// Reichweitenmessung mit Umami – Konfiguration und Script-Tag
//
// Diese Datei ist die einzige Stelle, an der entschieden wird, OB
// gemessen wird, und die einzige, die das Tracking-Tag erzeugt.
// Eingebunden wird sie aus public/includes/head_assets.php.
//
// Aufbau der Messung (siehe auch README.md):
//   Browser -> /public/js/umami.js  (gepinnte Kopie, First-Party)
//   Browser -> /api/send            (eigener Proxy)
//   Proxy   -> UMAMI_COLLECT_HOST   (gateway.umami.is)
// Der Browser des Besuchers baut nie eine Verbindung zu Umami auf.
// Das haelt die CSP bei 'self' und laesst die IP-Kuerzung im
// eigenen Server passieren.
//
// Robustheit: Wie track_visit.php darf hier nichts eine Seite
// abbrechen. Fehlt die .env oder ist ein Wert Unsinn, liefert
// umami_enabled() einfach false und es wird kein Tag gerendert.
// =============================================================

require_once __DIR__ . '/assets.php';   // asset() fuer das Cache-Busting

// Name des Opt-out-Cookies. Wird in private/php/legal/analytics_optout.php
// gesetzt und hier ausgewertet – Widerspruch nach Art. 21 DSGVO.
if (!defined('UMAMI_OPTOUT_COOKIE')) {
    define('UMAMI_OPTOUT_COOKIE', 'analytics_optout');
}

if (!function_exists('umami_env')) {
    // =========================================================
    // .env-Zugriff
    //
    // db.php laedt die .env normalerweise schon. Statische Seiten
    // (Impressum, Login) kommen aber ohne DB aus – dort wird sie
    // hier nachgeladen, und zwar mit safeLoad(): eine fehlende
    // .env darf keine Exception werfen.
    // =========================================================
    function umami_env(string $key, string $default = ''): string
    {
        static $loaded = false;

        if (!$loaded) {
            $loaded = true;
            if (!isset($_ENV['UMAMI_ENABLED'])) {
                try {
                    require_once __DIR__ . '/../../../../vendor/autoload.php';
                    Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../..')->safeLoad();
                } catch (Throwable $e) {
                    error_log('analytics.php env load failed: ' . $e->getMessage());
                }
            }
        }

        $value = $_ENV[$key] ?? '';
        return is_string($value) && $value !== '' ? trim($value) : $default;
    }
}

if (!function_exists('umami_website_id')) {
    /**
     * Website-ID aus der .env – nur als UUID akzeptiert. Ein
     * Tippfehler soll kein halbes Tag erzeugen, sondern gar keins.
     */
    function umami_website_id(): string
    {
        $id = umami_env('UMAMI_WEBSITE_ID');
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)
            ? $id
            : '';
    }
}

if (!function_exists('umami_collect_host')) {
    /**
     * Upstream fuer den Proxy aus Phase 03 – ohne Pfad, ohne
     * Slash am Ende. Konfigurierbar, weil Umami seine Endpunkte
     * schon einmal unangekuendigt verschoben hat
     * (analytics.eu.umami.is antwortet heute mit 404).
     */
    function umami_collect_host(): string
    {
        $host = umami_env('UMAMI_COLLECT_HOST', 'https://gateway.umami.is');
        if (!preg_match('#^https://[a-z0-9.-]+(:\d{1,5})?$#i', rtrim($host, '/'))) {
            error_log('analytics.php: ungueltiger UMAMI_COLLECT_HOST');
            return 'https://gateway.umami.is';
        }
        return rtrim($host, '/');
    }
}

if (!function_exists('umami_ip_mode')) {
    /**
     * Wie die Besucher-IP an Umami weitergereicht wird:
     *   truncate – letztes Oktett auf 0 bzw. /48 bei IPv6 (Vorgabe)
     *   full     – unveraendert
     *   none     – gar nicht (kostet die Laenderauswertung)
     * Ausgewertet wird der Wert im Proxy, nicht hier.
     */
    function umami_ip_mode(): string
    {
        $mode = strtolower(umami_env('UMAMI_IP_MODE', 'truncate'));
        return in_array($mode, ['truncate', 'full', 'none'], true) ? $mode : 'truncate';
    }
}

if (!function_exists('umami_site_url')) {
    // =========================================================
    // Eigene Origin (APP_BASE_URL)
    //
    // Zwei Verwendungen:
    //   1. data-host-url im Tracker-Tag. Ohne das Attribut sendet
    //      der Tracker an gateway.umami.is – der Proxy waere
    //      wirkungslos und die CSP wuerde den Request blocken.
    //   2. Vergleichswert im Sammel-Proxy (Phase 03) fuer Origin
    //      und payload.hostname.
    //
    // Bewusst NICHT aus $_SERVER['HTTP_HOST'] abgeleitet: der
    // Host-Header ist Nutzereingabe. Waere er die Quelle, koennte
    // jemand mit gefaelschtem Host beliebige Hostnamen unter der
    // eigenen Website-ID in die Statistik schreiben. Fehlt oder
    // stimmt die Konfiguration nicht, wird gar nicht gemessen.
    // =========================================================
    function umami_site_url(): string
    {
        $url = rtrim(umami_env('APP_BASE_URL'), '/');
        if (!preg_match('#^https://[a-z0-9.-]{1,253}(:\d{1,5})?$#i', $url)) {
            if ($url !== '') {
                error_log('analytics.php: ungueltige APP_BASE_URL');
            }
            return '';
        }
        return $url;
    }
}

if (!function_exists('umami_enabled')) {
    // =========================================================
    // Abschaltlogik
    //
    // Alle Gruende, aus denen NICHT gemessen wird. Wichtig: Die
    // Pruefung passiert serverseitig, das Tag wird bei einem
    // Treffer gar nicht erst gerendert. Es gibt dann keinen
    // Request, den man noch umgehen koennte – deutlich sauberer
    // als ein Flag im JavaScript.
    // =========================================================
    function umami_enabled(): bool
    {
        // Kein Tracking ausserhalb eines echten Requests (CLI, Cron)
        if (PHP_SAPI === 'cli') {
            return false;
        }

        // Globaler Schalter und gueltige Konfiguration
        if (umami_env('UMAMI_ENABLED') !== '1' || umami_website_id() === '' || umami_site_url() === '') {
            return false;
        }

        // Widerspruch des Besuchers (Opt-out-Schalter, Phase 06)
        if (!empty($_COOKIE[UMAMI_OPTOUT_COOKIE])) {
            return false;
        }

        // Do-Not-Track / Global Privacy Control respektieren.
        // Der Tracker koennte das per data-do-not-track selbst,
        // aber dann waere die Datei trotzdem schon geladen.
        $dnt = $_SERVER['HTTP_DNT'] ?? '';
        if ($dnt === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') {
            return false;
        }

        // Eigene Klicks: Admin-Session und Admin-Subdomain
        if (!empty($_SESSION['is_admin'])) {
            return false;
        }
        if (str_starts_with(strtolower($_SERVER['HTTP_HOST'] ?? ''), 'x.')) {
            return false;
        }

        return true;
    }
}

if (!function_exists('umami_script_tag')) {
    // =========================================================
    // Script-Tag
    //
    // Bewusste Attributwahl (geprueft am heruntergeladenen
    // Tracker, Stand 26.08.2026):
    //   data-host-url        zwingend, siehe umami_site_url()
    //   data-exclude-search  Query-Strings raus – dort stehen
    //                        u. a. die PRG-Marker (?ok=spot)
    //   data-do-not-track    zweite Verteidigungslinie im Client
    //   data-performance     bewusst NICHT gesetzt: Web Vitals
    //                        erzeugen mehrere Ereignisse pro
    //                        Aufruf und fressen das Kontingent
    //   data-domains         bewusst NICHT gesetzt: der Proxy
    //                        prueft den Hostnamen ohnehin
    //                        serverseitig, und die Sperre wuerde
    //                        das lokale Testen unmoeglich machen
    //
    // Die Boolean-Attribute brauchen explizit "true" – der
    // Tracker vergleicht per === mit dem String, ein blosses
    // Attribut ohne Wert wuerde ignoriert.
    // =========================================================
    function umami_script_tag(): string
    {
        if (!umami_enabled()) {
            return '';
        }

        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        return '<script defer src="' . $esc(asset('/public/js/umami.js')) . '"'
             . ' data-website-id="' . $esc(umami_website_id()) . '"'
             . ' data-host-url="' . $esc(umami_site_url()) . '"'
             . ' data-exclude-search="true"'
             . ' data-do-not-track="true"></script>';
    }
}

if (!function_exists('umami_event_tag')) {
    // =========================================================
    // Ereignis-Script (public/js/analytics.js)
    //
    // Ergaenzt den Tracker um alles, was ein data-umami-event-
    // Attribut nicht abbildet: PRG-Marker, Kartenzustaende,
    // Verbindungsabbrueche. Wird wie das Tracker-Tag nur bei
    // aktiver Messung gerendert – bei Opt-out entsteht kein
    // Request, und window.fpvTrack existiert dann gar nicht.
    // Aufrufer in map.js/pwa.js pruefen das deshalb selbst.
    //
    // defer ist Pflicht und die Reihenfolge ebenfalls: umami.js
    // muss vorher ausgefuehrt sein, sonst fehlt window.umami.
    // Bei defer bleibt die Dokumentreihenfolge erhalten.
    // =========================================================
    function umami_event_tag(): string
    {
        if (!umami_enabled()) {
            return '';
        }

        return '<script defer src="'
             . htmlspecialchars(asset('/public/js/analytics.js'), ENT_QUOTES, 'UTF-8')
             . '"></script>';
    }
}

if (!function_exists('umami_prg_marker')) {
    // =========================================================
    // PRG-Marker an ein Redirect-Ziel haengen
    //
    // Registrierung, Spot-Anlage, Kommentare und Bewertungen
    // enden im PRG-Muster mit einem Redirect – im Moment des
    // Erfolgs laeuft kein JavaScript, das ein Ereignis melden
    // koennte. Das Redirect-Ziel bekommt deshalb ?ok=<marker>;
    // public/js/analytics.js uebersetzt ihn einmalig in ein
    // Ereignis und entfernt ihn per history.replaceState().
    //
    // Der Marker landet nicht in den erfassten URLs, weil das
    // Tracker-Tag mit data-exclude-search laeuft.
    //
    // Ein vorhandener Fragmentbezeichner (#photos) muss hinter
    // dem Query-String bleiben, sonst zeigt der Link ins Leere.
    // =========================================================
    function umami_prg_marker(string $target, string $marker): string
    {
        // Nur schlichte Kleinbuchstaben-Marker – der Wert landet
        // in einer URL und wird clientseitig gegen eine Whitelist
        // geprueft. Alles andere waere ein stiller Fehler.
        if (!preg_match('/^[a-z_]{1,32}$/', $marker)) {
            error_log('analytics.php: ungueltiger PRG-Marker "' . $marker . '"');
            return $target;
        }

        $hashPos  = strpos($target, '#');
        $fragment = $hashPos === false ? '' : substr($target, $hashPos);
        $base     = $hashPos === false ? $target : substr($target, 0, $hashPos);

        $sep = str_contains($base, '?') ? '&' : '?';

        return $base . $sep . 'ok=' . $marker . $fragment;
    }
}
