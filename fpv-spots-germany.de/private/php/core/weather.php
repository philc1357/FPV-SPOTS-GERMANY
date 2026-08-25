<?php
declare(strict_types=1);
// =============================================================
// Wetter – Anbindung an Open-Meteo und den NOAA-Kp-Index
//
// Datenquellen:
//   Open-Meteo (CC BY 4.0), fuer Mitteleuropa auf Basis von
//   ICON-D2 des Deutschen Wetterdienstes.
//   NOAA SWPC fuer den globalen Kp-Index (geomagnetische
//   Aktivitaet, beeinflusst den GPS-Empfang).
//
// Warum Proxy und nicht Browser-Request – dieselben drei Gruende
// wie beim DIPUL-Kachelproxy in public/php/api/dipul_wms.php:
//
//   1. Die CSP bleibt bei connect-src 'self' – kein neuer
//      Fremdhost in der .htaccess.
//   2. Die IP unserer Besucher erreicht Open-Meteo/NOAA nie; es
//      entsteht kein zusaetzlicher Drittempfaenger nach DSGVO.
//   3. Cache und Rate-Limit liegen bei uns. Open-Meteo ist ein
//      kostenloser Dienst ohne Vertrag – ihn zu schonen ist
//      Anstand, nicht Optimierung.
//
// WICHTIG – bewusste Grundhaltung dieses Moduls:
// Hier wird NICHTS bewertet. Es gibt keine Ampel, keine
// "gut/schlecht zum Fliegen"-Einstufung und keine Schwellwerte.
// Ausgeliefert werden Messwerte und Modellprognosen mit
// deutschem Klartext zum Wettercode – die Entscheidung, ob ein
// Flug sinnvoll und zulaessig ist, trifft allein der Pilot.
// Das ist dieselbe Linie wie beim Geozonen-Katalog, nur aus dem
// umgekehrten Grund: dort waere eine Fehlentwarnung gefaehrlich,
// hier waere eine Flugempfehlung eine Aussage, die wir aus
// Wetterdaten schlicht nicht ableiten koennen.
// =============================================================

// -------------------------------------------------------------
// Endpunkte und Quellenangaben
// -------------------------------------------------------------
const OPEN_METEO_URL      = 'https://api.open-meteo.com/v1/forecast';
const OPEN_METEO_INFO_URL = 'https://open-meteo.com/';
const NOAA_KP_URL         = 'https://services.swpc.noaa.gov/products/noaa-planetary-k-index.json';
const NOAA_KP_INFO_URL    = 'https://www.swpc.noaa.gov/';

// -------------------------------------------------------------
// Zeit- und Mengengeruest
//
// WEATHER_TTL: Open-Meteo meldet in der Antwort selbst
// current.interval = 900, aktualisiert also viertelstuendlich.
// Laenger zu cachen hiesse, einen als "aktuell" beschrifteten
// Wert zu zeigen, den der Anbieter langst ersetzt hat.
//
// WEATHER_STALE_MAX: Notreserve. Faellt Open-Meteo aus, ist eine
// sichtbar als veraltet markierte Prognose immer noch nuetzlicher
// als eine Fehlermeldung – gerade unterwegs auf dem Feld.
// -------------------------------------------------------------
const WEATHER_TZ            = 'Europe/Berlin';
const WEATHER_TTL           = 900;     // 15 Min – Prognose
const WEATHER_STALE_MAX     = 21600;   // 6 h – Notreserve bei Upstream-Ausfall
const WEATHER_KP_TTL        = 3600;    // 1 h – der Kp-Wert wechselt nur alle 3 h
const WEATHER_FORECAST_DAYS = 7;
const WEATHER_HOURS         = 24;

// Gitter-Rundung: ICON-D2 rechnet auf einem ~2,2-km-Gitter und
// Open-Meteo liefert ohnehin die naechstgelegene Gitterzelle
// (die Antwort spiegelt die gerundete Koordinate zurueck). Drei
// Nachkommastellen (~110 m) sind weit darunter: feiner aufzuloesen
// erzeugt identische Antworten unter verschiedenen Cache-Keys.
// Umgekehrt teilen sich zwei Spots im selben Wald korrekt einen
// Eintrag. Die gerundete Koordinate geht auch in die Upstream-URL,
// sonst passten Key und Inhalt nicht mehr zusammen.
const WEATHER_COORD_PRECISION = 3;

// -------------------------------------------------------------
// Datei-Cache
//
// Bewusst dateibasiert wie der Kachel-Cache und nicht in der DB:
// das Projekt kennt keine Cache-Tabellen, und ein Schema fuer
// wegwerfbare Laufzeitdaten waere Ballast in database.sql.
// -------------------------------------------------------------
const WEATHER_CACHE_ROOT      = __DIR__ . '/../../data/weather_cache';
const WEATHER_CACHE_MAX_BYTES = 50 * 1024 * 1024;   // ~10 KB je Eintrag – reichlich
const WEATHER_GC_MAX_SECONDS  = 2;                  // Zeitschranke fuer den GC im Request

// Nur Cache-Misses werden gezaehlt, also echte Upstream-Abrufe.
const WEATHER_RATE_LIMIT_PER_HOUR = 60;


// =============================================================
// HTTP
// =============================================================

/**
 * Holt eine JSON-Antwort. Gibt bei jedem Fehler null zurueck
 * (Timeout, HTTP != 200, kaputtes JSON) – der Aufrufer darf aus
 * null KEINE inhaltliche Aussage ableiten, nur "nicht verfuegbar".
 */
function weather_http_json(string $url, int $timeout = 8): ?array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT      => 'fpv-spots-germany.de Wetterproxy',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status !== 200) {
        error_log('weather: Abruf fehlgeschlagen (HTTP ' . $status . ($err !== '' ? ', ' . $err : '') . ')');
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        error_log('weather: Antwort ist kein JSON');
        return null;
    }

    return $data;
}


// =============================================================
// Datei-Cache
// =============================================================

/** Zweistufige Ablage haelt die Verzeichnisse klein. */
function weather_cache_path(string $key): string
{
    return WEATHER_CACHE_ROOT . '/' . substr($key, 0, 2) . '/' . $key . '.json';
}

function weather_cache_key(float $lat, float $lon): string
{
    return sha1(sprintf('%.3F|%.3F', $lat, $lon));
}

/**
 * Liest einen Cache-Eintrag, wenn er hoechstens $maxAge Sekunden
 * alt ist. Gibt zusaetzlich das Alter zurueck, damit der Aufrufer
 * "frisch" von "Notreserve" unterscheiden kann.
 *
 * @return array{daten: array, alter: int}|null
 */
function weather_cache_read(string $key, int $maxAge): ?array
{
    $file = weather_cache_path($key);
    if (!is_file($file)) {
        return null;
    }

    $alter = time() - (int) filemtime($file);
    if ($alter >= $maxAge) {
        return null;
    }

    $roh = @file_get_contents($file);
    if ($roh === false || $roh === '') {
        return null;
    }

    $daten = json_decode($roh, true);
    if (!is_array($daten)) {
        return null;
    }

    return ['daten' => $daten, 'alter' => $alter];
}

/**
 * Schreibt atomar ueber eine temporaere Datei. Ohne das
 * rename() koennte ein abgebrochener Schreibvorgang halbes JSON
 * hinterlassen, das beim naechsten Lesen wie ein Ausfall aussieht.
 */
function weather_cache_write(string $key, array $daten): void
{
    $file = weather_cache_path($key);
    $dir  = dirname($file);

    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        error_log('weather: Cache-Verzeichnis nicht anlegbar: ' . $dir);
        return;
    }

    $json = json_encode($daten, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }

    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json) !== false) {
        @rename($tmp, $file);
    } else {
        error_log('weather: Cache nicht schreibbar: ' . $file);
    }
}

/**
 * Stundenweises Rate-Limit auf Upstream-Abrufe.
 *
 * Bewusst dateibasiert und NICHT ueber audit_logs: dieser Endpunkt
 * ist oeffentlich und rein lesend. Seine Zugriffe in der Audit-
 * Tabelle zu fuehren wuerde deren eigentlichen Zweck – eine
 * forensisch lesbare Spur sicherheitsrelevanter Aktionen –
 * verwaessern. Gespeichert wird nur ein Hash der IP in einem
 * Stundenbucket, den der GC nach zwei Stunden entfernt.
 *
 * @return bool true = Limit erreicht, kein weiterer Abruf
 */
function weather_rate_hit(string $ip, int $limit = WEATHER_RATE_LIMIT_PER_HOUR): bool
{
    $dir = WEATHER_CACHE_ROOT . '/_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;   // Ohne Zaehler lieber durchlassen als das Feature blockieren
    }

    $file = $dir . '/' . sha1($ip . '|' . date('YmdH')) . '.cnt';
    $hits = is_file($file) ? (int) @file_get_contents($file) : 0;

    if ($hits >= $limit) {
        return true;
    }

    @file_put_contents($file, (string) ($hits + 1), LOCK_EX);
    return false;
}

/**
 * Raeumt abgelaufene Eintraege, alte Rate-Zaehler und – falls die
 * Obergrenze ueberschritten ist – die aeltesten Dateien weg.
 *
 * Eigene Funktion statt Wiederverwendung von dipul_cache_gc():
 * jene liegt in public/php/api/dipul_wms.php, ist auf PNG-Kacheln
 * und DIPUL_BUNDLE_TTL festgelegt und gehoert zu einem
 * sicherheitskritischen Pfad. Sie zu verallgemeinern hiesse, diesen
 * Pfad fuer einen um Groessenordnungen kleineren Cache anzufassen.
 * Uebernommen ist die Struktur, nicht der Code.
 */
function weather_cache_gc(): void
{
    if (!is_dir(WEATHER_CACHE_ROOT)) {
        return;
    }

    $start  = microtime(true);
    $jetzt  = time();
    $dateien = [];
    $bytes   = 0;

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(WEATHER_CACHE_ROOT, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $eintrag) {
        if (microtime(true) - $start > WEATHER_GC_MAX_SECONDS) {
            return;   // Der GC laeuft im Nutzer-Request – niemals blockieren
        }
        if (!$eintrag->isFile()) {
            continue;
        }

        $pfad  = $eintrag->getPathname();
        $alter = $jetzt - $eintrag->getMTime();

        // Rate-Zaehler: nach zwei Stunden ist der Stundenbucket sicher durch
        if (str_ends_with($pfad, '.cnt')) {
            if ($alter > 7200) {
                @unlink($pfad);
            }
            continue;
        }

        // Liegengebliebene Schreibversuche
        if (str_ends_with($pfad, '.tmp')) {
            if ($alter > 300) {
                @unlink($pfad);
            }
            continue;
        }

        if ($alter > WEATHER_STALE_MAX) {
            @unlink($pfad);
            continue;
        }

        $dateien[$pfad] = $eintrag->getMTime();
        $bytes += $eintrag->getSize();
    }

    // Obergrenze: aelteste zuerst wegwerfen
    if ($bytes > WEATHER_CACHE_MAX_BYTES) {
        asort($dateien);
        foreach (array_keys($dateien) as $pfad) {
            if ($bytes <= WEATHER_CACHE_MAX_BYTES) {
                break;
            }
            $groesse = @filesize($pfad);
            if (@unlink($pfad) && $groesse !== false) {
                $bytes -= $groesse;
            }
        }
    }
}


// =============================================================
// Uebersetzungstabellen
//
// WMO-Wettercode 4677 in deutschen Klartext plus Bootstrap-Icon.
// Bewusst rein beschreibend: "Starke Regenschauer" sagt, was das
// Modell erwartet – nicht, ob man dabei fliegen sollte.
//
// Die Icon-Klassen sind zugleich die Whitelist fuers Frontend:
// dort wird ausschliesslich ein Wert aus dieser Tabelle in eine
// class geschrieben, nie ein Wert aus einer Fremdantwort.
// =============================================================
const WEATHER_CODES = [
    0  => ['Klar',                          'bi-sun',                'bi-moon-stars'],
    1  => ['Überwiegend klar',              'bi-sun',                'bi-moon-stars'],
    2  => ['Teilweise bewölkt',             'bi-cloud-sun',          'bi-cloud-moon'],
    3  => ['Bedeckt',                       'bi-clouds',             'bi-clouds'],
    45 => ['Nebel',                         'bi-cloud-fog2',         'bi-cloud-fog2'],
    48 => ['Gefrierender Nebel',            'bi-cloud-fog2',         'bi-cloud-fog2'],
    51 => ['Leichter Sprühregen',           'bi-cloud-drizzle',      'bi-cloud-drizzle'],
    53 => ['Sprühregen',                    'bi-cloud-drizzle',      'bi-cloud-drizzle'],
    55 => ['Starker Sprühregen',            'bi-cloud-drizzle',      'bi-cloud-drizzle'],
    56 => ['Leichter gefrierender Sprühregen', 'bi-cloud-sleet',     'bi-cloud-sleet'],
    57 => ['Gefrierender Sprühregen',       'bi-cloud-sleet',        'bi-cloud-sleet'],
    61 => ['Leichter Regen',                'bi-cloud-rain',         'bi-cloud-rain'],
    63 => ['Regen',                         'bi-cloud-rain',         'bi-cloud-rain'],
    65 => ['Starker Regen',                 'bi-cloud-rain-heavy',   'bi-cloud-rain-heavy'],
    66 => ['Leichter gefrierender Regen',   'bi-cloud-sleet',        'bi-cloud-sleet'],
    67 => ['Gefrierender Regen',            'bi-cloud-sleet',        'bi-cloud-sleet'],
    71 => ['Leichter Schneefall',           'bi-cloud-snow',         'bi-cloud-snow'],
    73 => ['Schneefall',                    'bi-cloud-snow',         'bi-cloud-snow'],
    75 => ['Starker Schneefall',            'bi-cloud-snow',         'bi-cloud-snow'],
    77 => ['Schneegriesel',                 'bi-cloud-snow',         'bi-cloud-snow'],
    80 => ['Leichte Regenschauer',          'bi-cloud-drizzle',      'bi-cloud-drizzle'],
    81 => ['Regenschauer',                  'bi-cloud-rain',         'bi-cloud-rain'],
    82 => ['Kräftige Regenschauer',         'bi-cloud-rain-heavy',   'bi-cloud-rain-heavy'],
    85 => ['Leichte Schneeschauer',         'bi-cloud-snow',         'bi-cloud-snow'],
    86 => ['Starke Schneeschauer',          'bi-cloud-snow',         'bi-cloud-snow'],
    95 => ['Gewitter',                      'bi-cloud-lightning',    'bi-cloud-lightning'],
    96 => ['Gewitter mit leichtem Hagel',   'bi-cloud-lightning-rain', 'bi-cloud-lightning-rain'],
    99 => ['Gewitter mit starkem Hagel',    'bi-cloud-hail',         'bi-cloud-hail'],
];

/**
 * Wettercode zu Text und Icon. Unbekannte Codes fallen auf eine
 * neutrale Wolke zurueck, statt den Block scheitern zu lassen.
 *
 * @return array{text: string, icon: string}
 */
function weather_code_meta(?int $code, bool $tag = true): array
{
    $eintrag = ($code !== null && isset(WEATHER_CODES[$code]))
        ? WEATHER_CODES[$code]
        : ['Keine Angabe', 'bi-cloud', 'bi-cloud'];

    return [
        'text' => $eintrag[0],
        'icon' => $tag ? $eintrag[1] : $eintrag[2],
    ];
}

// -------------------------------------------------------------
// Windrichtung
//
// Open-Meteo liefert die Richtung, AUS DER der Wind weht (meteo-
// rologische Konvention). Der Pfeil im Frontend wird deshalb um
// grad+180 gedreht, damit er in die Bewegungsrichtung zeigt –
// die Rechnung passiert dort, hier steht nur die Kurzform.
// -------------------------------------------------------------
const WEATHER_DIRECTIONS = [
    'N', 'NNO', 'NO', 'ONO', 'O', 'OSO', 'SO', 'SSO',
    'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW',
];

function weather_direction_label(?float $grad): ?string
{
    if ($grad === null) {
        return null;
    }
    $index = (int) round(fmod(fmod($grad, 360.0) + 360.0, 360.0) / 22.5) % 16;
    return WEATHER_DIRECTIONS[$index];
}

// -------------------------------------------------------------
// Kp-Index
//
// Standardisierte Beschreibung der geomagnetischen Aktivitaet.
// Das ist eine Zustandsbeschreibung des Erdmagnetfelds, keine
// Flugempfehlung – hohe Werte koennen die GPS-Genauigkeit
// verschlechtern, mehr sagt der Wert nicht aus.
// -------------------------------------------------------------
function weather_kp_label(float $kp): string
{
    return match (true) {
        $kp < 3.0 => 'ruhig',
        $kp < 4.0 => 'unruhig',
        $kp < 5.0 => 'aktiv',
        $kp < 6.0 => 'geomagnetischer Sturm (G1)',
        $kp < 7.0 => 'geomagnetischer Sturm (G2)',
        $kp < 8.0 => 'starker Sturm (G3)',
        $kp < 9.0 => 'schwerer Sturm (G4)',
        default   => 'extremer Sturm (G5)',
    };
}


// =============================================================
// Upstream-Abruf
// =============================================================

/**
 * Ein einziger Request fuer current + hourly + daily.
 *
 * Die URL wird vollstaendig selbst zusammengebaut – aus $_GET
 * gelangt NICHTS in den Upstream-Aufruf. Die Koordinate stammt
 * aus der Datenbank und wird zusaetzlich auf denselben Wert
 * gerundet wie der Cache-Key, damit Key und Inhalt zusammenpassen.
 */
function weather_fetch_forecast(float $lat, float $lon): ?array
{
    $url = OPEN_METEO_URL . '?' . http_build_query([
        'latitude'        => round($lat, WEATHER_COORD_PRECISION),
        'longitude'       => round($lon, WEATHER_COORD_PRECISION),
        'timezone'        => WEATHER_TZ,
        'wind_speed_unit' => 'kmh',
        'forecast_days'   => WEATHER_FORECAST_DAYS,
        'current'         => 'temperature_2m,apparent_temperature,relative_humidity_2m,'
                           . 'precipitation,weather_code,cloud_cover,is_day,'
                           . 'wind_speed_10m,wind_direction_10m,wind_gusts_10m',
        'hourly'          => 'temperature_2m,precipitation_probability,precipitation,weather_code,'
                           . 'cloud_cover,visibility,is_day,'
                           . 'wind_speed_10m,wind_direction_10m,wind_gusts_10m',
        'daily'           => 'weather_code,temperature_2m_max,temperature_2m_min,'
                           . 'precipitation_sum,precipitation_probability_max,'
                           . 'wind_speed_10m_max,wind_gusts_10m_max,wind_direction_10m_dominant,'
                           . 'sunrise,sunset',
    ], '', '&', PHP_QUERY_RFC3986);

    $daten = weather_http_json($url);

    // Plausibilitaet: ohne diese drei Bloecke ist die Antwort unbrauchbar
    if ($daten === null
        || !isset($daten['current'], $daten['hourly']['time'], $daten['daily']['time'])
        || !is_array($daten['hourly']['time'])
        || !is_array($daten['daily']['time'])) {
        return null;
    }

    return $daten;
}

/**
 * Globaler Kp-Index von der NOAA.
 *
 * Der Wert ist nicht ortsabhaengig, deshalb ein einziger Cache-
 * Eintrag fuer die gesamte Seite. Faellt der Abruf aus, gibt die
 * Funktion still null zurueck: der Kp-Index ist eine Zusatzangabe
 * und kein Grund, den kompletten Wetterblock scheitern zu lassen.
 *
 * Format (live geprueft 2026-08-24): ein Array VON OBJEKTEN,
 * [{"time_tag":"…","Kp":1.67,"a_running":6,"station_count":6}, …],
 * chronologisch aufsteigend – gebraucht wird das LETZTE Element,
 * der Schluessel heisst "Kp" mit grossem K. Die aeltere, bei
 * anderen SWPC-Produkten uebliche Array-of-Arrays-Form mit
 * Kopfzeile wird zur Sicherheit ebenfalls verstanden.
 *
 * @return array{wert: float, text: string}|null
 */
function weather_fetch_kp(): ?array
{
    $cacheKey = 'kp_global';   // -> weather_cache/kp/kp_global.json
    $treffer  = weather_cache_read($cacheKey, WEATHER_KP_TTL);
    if ($treffer !== null) {
        return $treffer['daten'] ?: null;
    }

    $roh = weather_http_json(NOAA_KP_URL, 6);
    if (!is_array($roh) || $roh === []) {
        return null;
    }

    $letzter = end($roh);
    $wert    = null;

    if (is_array($letzter) && isset($letzter['Kp'])) {
        $wert = $letzter['Kp'];                       // Array von Objekten
    } elseif (is_array($letzter) && isset($letzter[1])) {
        $wert = $letzter[1];                          // Array von Arrays (Kopfzeile in [0])
    }

    if (!is_numeric($wert)) {
        error_log('weather: Kp-Antwort in unerwartetem Format');
        return null;
    }

    $kp = ['wert' => round((float) $wert, 2), 'text' => weather_kp_label((float) $wert)];
    weather_cache_write($cacheKey, $kp);

    return $kp;
}


// =============================================================
// Aufbereitung fuer das Frontend
// =============================================================

/** Rundet, laesst fehlende Werte aber ausdruecklich als null durch. */
function weather_num(mixed $wert, int $stellen = 0): int|float|null
{
    if (!is_numeric($wert)) {
        return null;
    }
    $gerundet = round((float) $wert, $stellen);
    return $stellen === 0 ? (int) $gerundet : $gerundet;
}

/** "2026-08-24T15:00" -> "15:00" – die Zeitstrings sind bereits lokal. */
function weather_uhrzeit(?string $iso): string
{
    return is_string($iso) && strlen($iso) >= 16 ? substr($iso, 11, 5) : '';
}

/**
 * Index der laufenden Stunde in hourly.time.
 *
 * Der wichtigste Handgriff in diesem Modul: hourly.time beginnt
 * bei 00:00 des heutigen Tages und laeuft ueber alle 168 Stunden
 * der Prognose. Ohne diesen Versatz zeigte der 24-Stunden-Streifen
 * die vergangene Nacht statt der naechsten 24 Stunden.
 *
 * Gesucht wird ueber den Zeitstempel aus current (auf die volle
 * Stunde gekuerzt, weil current viertelstuendlich aktualisiert),
 * nicht ueber eine eigene Rechnung mit date() – sonst koennten
 * Serverzeitzone und Antwort auseinanderlaufen.
 */
function weather_stunden_index(array $raw): int
{
    $jetzt = $raw['current']['time'] ?? '';
    if (is_string($jetzt) && strlen($jetzt) >= 13) {
        $volleStunde = substr($jetzt, 0, 13) . ':00';
        $index = array_search($volleStunde, $raw['hourly']['time'], true);
        if (is_int($index)) {
            return $index;
        }
    }

    error_log('weather: laufende Stunde nicht in hourly.time gefunden');
    return 0;
}

/**
 * Rohantwort -> schlanke Struktur, die zugleich das API-JSON ist.
 *
 * Alle Zeiten werden hier serverseitig zu fertigen Anzeigetexten
 * geformt. Der Browser rechnet bewusst nichts um: ein Besucher in
 * einer anderen Zeitzone soll die Zeiten AM SPOT sehen, nicht die
 * seiner eigenen Uhr.
 */
function weather_shape(array $raw, ?array $kp): array
{
    $current = $raw['current'];
    $hourly  = $raw['hourly'];
    $daily   = $raw['daily'];
    $start   = weather_stunden_index($raw);

    // ── Jetzt ────────────────────────────────────────────────
    $istTag = (int) ($current['is_day'] ?? 1) === 1;
    $meta   = weather_code_meta(weather_num($current['weather_code'] ?? null), $istTag);

    // Sichtweite gibt es nur in hourly (Einheit Meter) – der Wert
    // der laufenden Stunde ist die beste verfuegbare "Sicht jetzt".
    $sichtM  = $hourly['visibility'][$start] ?? null;
    $sichtKm = is_numeric($sichtM) ? round(((float) $sichtM) / 1000, 1) : null;

    $richtungGrad = weather_num($current['wind_direction_10m'] ?? null);

    $aktuell = [
        'temp'         => weather_num($current['temperature_2m'] ?? null, 1),
        'gefuehlt'     => weather_num($current['apparent_temperature'] ?? null, 1),
        'feuchte'      => weather_num($current['relative_humidity_2m'] ?? null),
        'wind'         => weather_num($current['wind_speed_10m'] ?? null),
        'boeen'        => weather_num($current['wind_gusts_10m'] ?? null),
        'richtung'     => $richtungGrad,
        'richtung_text'=> weather_direction_label($richtungGrad === null ? null : (float) $richtungGrad),
        'niederschlag' => weather_num($current['precipitation'] ?? null, 1),
        'bewoelkung'   => weather_num($current['cloud_cover'] ?? null),
        'sicht_km'     => $sichtKm,
        'text'         => $meta['text'],
        'icon'         => $meta['icon'],
    ];

    // ── Naechste 24 Stunden ──────────────────────────────────
    // Windwerte bleiben ganzzahlig: bei einer Modellaufloesung von
    // rund 2 km waeren Nachkommastellen in km/h Scheingenauigkeit.
    $stunden = [];
    $ende    = min($start + WEATHER_HOURS, count($hourly['time']));

    for ($i = $start; $i < $ende; $i++) {
        $tag  = (int) ($hourly['is_day'][$i] ?? 1) === 1;
        $code = weather_num($hourly['weather_code'][$i] ?? null);
        $m    = weather_code_meta($code, $tag);
        $grad = weather_num($hourly['wind_direction_10m'][$i] ?? null);

        $stunden[] = [
            'zeit'          => weather_uhrzeit($hourly['time'][$i] ?? null),
            'temp'          => weather_num($hourly['temperature_2m'][$i] ?? null),
            'wind'          => weather_num($hourly['wind_speed_10m'][$i] ?? null),
            'boeen'         => weather_num($hourly['wind_gusts_10m'][$i] ?? null),
            'richtung'      => $grad,
            'richtung_text' => weather_direction_label($grad === null ? null : (float) $grad),
            'regen_prozent' => weather_num($hourly['precipitation_probability'][$i] ?? null),
            'text'          => $m['text'],
            'icon'          => $m['icon'],
        ];
    }

    // ── 7 Tage ───────────────────────────────────────────────
    // Wochentag aus dem Datum der Antwort, nicht aus einer eigenen
    // Zaehlung – so bleiben Sommerzeitwechsel folgenlos.
    $wochentage = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
    $zone       = new DateTimeZone(WEATHER_TZ);
    $tage       = [];

    foreach ($daily['time'] as $i => $datum) {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $datum, $zone);
        if ($d === false) {
            continue;
        }

        $code = weather_num($daily['weather_code'][$i] ?? null);
        $m    = weather_code_meta($code, true);
        $grad = weather_num($daily['wind_direction_10m_dominant'][$i] ?? null);

        $tage[] = [
            'datum'         => $d->format('d.m.'),
            'wochentag'     => $wochentage[(int) $d->format('w')],
            'heute'         => $i === 0,
            'tmax'          => weather_num($daily['temperature_2m_max'][$i] ?? null),
            'tmin'          => weather_num($daily['temperature_2m_min'][$i] ?? null),
            'wind'          => weather_num($daily['wind_speed_10m_max'][$i] ?? null),
            'boeen'         => weather_num($daily['wind_gusts_10m_max'][$i] ?? null),
            'richtung'      => $grad,
            'richtung_text' => weather_direction_label($grad === null ? null : (float) $grad),
            'regen_mm'      => weather_num($daily['precipitation_sum'][$i] ?? null, 1),
            'regen_prozent' => weather_num($daily['precipitation_probability_max'][$i] ?? null),
            'text'          => $m['text'],
            'icon'          => $m['icon'],
        ];
    }

    // ── Sonnenzeiten heute ───────────────────────────────────
    $sonne = [
        'aufgang'   => weather_uhrzeit($daily['sunrise'][0] ?? null),
        'untergang' => weather_uhrzeit($daily['sunset'][0] ?? null),
    ];

    // ── Stand ────────────────────────────────────────────────
    // Zeitpunkt des Upstream-Abrufs. Er wandert mit in den Cache
    // und bleibt dort stehen – genau deshalb ist die Anzeige auch
    // dann ehrlich, wenn der Service Worker offline einen alten
    // Eintrag ausliefert.
    $jetzt = new DateTimeImmutable('now', $zone);

    return [
        'stand'      => $jetzt->format(DateTimeInterface::ATOM),
        'stand_text' => $jetzt->format('d.m.Y, H:i') . ' Uhr',
        'aktuell'    => $aktuell,
        'sonne'      => $sonne,
        'stunden'    => $stunden,
        'tage'       => $tage,
        'kp'         => $kp,
    ];
}
