<?php
declare(strict_types=1);
// =============================================================
// Bundesland – Reverse-Geocoding der Spot-Koordinate
//
// Datenquelle: Nominatim (OpenStreetMap), ODbL. Dieselbe Daten-
// basis wie die Kartenkacheln, kein API-Key noetig.
//
// Zweck: jeder Spot soll wissen, in welchem Bundesland er liegt –
// Grundlage fuer spaetere Landingpages je Bundesland. Das Ergebnis
// steht dauerhaft in spots.bundesland; es gibt deshalb bewusst
// KEINEN Datei-Cache wie beim Wetter: die Koordinate eines Spots
// aendert sich nicht, also wird pro Spot genau einmal abgefragt.
//
// WICHTIG – warum der ISO-Code und nicht address.state:
// Nominatim liefert fuer die Stadtstaaten Berlin und Hamburg
// KEIN state-Feld, nur city (live geprueft 2026-08-27). Ein
// Abgleich ueber address.state haette zwei der 16 Laender
// systematisch nie erkannt. ISO3166-2-lvl4 ist dagegen bei allen
// deutschen Treffern gesetzt und eindeutig; address.state dient
// nur noch als Rueckfallebene.
//
// Nutzungsrichtlinie von Nominatim (bindend, kein Vertrag):
//   - identifizierender User-Agent mit Kontaktmoeglichkeit
//   - hoechstens 1 Anfrage pro Sekunde
// Der einzelne Aufruf beim Anlegen eines Spots ist unkritisch;
// das Backfill-Skript drosselt sich ueber NOMINATIM_MIN_INTERVAL_US.
// =============================================================

// point_in_dipul_area()/DIPUL_BBOX_DE als Deutschland-Vorfilter.
// Die Datei definiert beim Laden nur Konstanten und Funktionen,
// hat also keine Seiteneffekte – und eine zweite, eigene Bounding
// Box waere eine Kopie, die irgendwann auseinanderlaeuft.
require_once __DIR__ . '/geozones.php';

// -------------------------------------------------------------
// Endpunkt und Quellenangabe
// -------------------------------------------------------------
const NOMINATIM_REVERSE_URL = 'https://nominatim.openstreetmap.org/reverse';
const NOMINATIM_INFO_URL    = 'https://nominatim.openstreetmap.org/';
const NOMINATIM_ATTRIBUTION = 'Daten © OpenStreetMap-Mitwirkende, ODbL';

// Kontakt im User-Agent ist Pflicht, nicht Hoeflichkeit: ohne ihn
// sperrt Nominatim den Aufrufer ohne Vorwarnung.
const NOMINATIM_USER_AGENT = 'fpv-spots-germany.de Bundeslandermittlung (+https://fpv-spots-germany.de)';

// Mindestabstand zwischen zwei Anfragen. Steht hier und nicht im
// Backfill-Skript, weil das Limit zum Dienst gehoert und fuer jeden
// kuenftigen Aufrufer gilt. 1,1 s statt exakt 1 s als Sicherheits-
// abstand – die Laufzeit des Requests selbst kommt noch obendrauf.
const NOMINATIM_MIN_INTERVAL_US = 1100000;

// -------------------------------------------------------------
// Die 16 Bundeslaender, indiziert nach ISO 3166-2:DE
//
// Zugleich Whitelist: was hier nicht drinsteht, wird nicht
// gespeichert. Eine Fremdantwort landet damit nie ungeprueft in
// der Datenbank, auch wenn Nominatim einmal etwas Unerwartetes
// liefert (Achtung, ISO-Code und amtliches Kfz-Kuerzel sind nicht
// dasselbe: Niedersachsen ist DE-NI, nicht DE-NDS).
// -------------------------------------------------------------
const BUNDESLAENDER_ISO = [
    'DE-BW' => 'Baden-Württemberg',
    'DE-BY' => 'Bayern',
    'DE-BE' => 'Berlin',
    'DE-BB' => 'Brandenburg',
    'DE-HB' => 'Bremen',
    'DE-HH' => 'Hamburg',
    'DE-HE' => 'Hessen',
    'DE-MV' => 'Mecklenburg-Vorpommern',
    'DE-NI' => 'Niedersachsen',
    'DE-NW' => 'Nordrhein-Westfalen',
    'DE-RP' => 'Rheinland-Pfalz',
    'DE-SL' => 'Saarland',
    'DE-SN' => 'Sachsen',
    'DE-ST' => 'Sachsen-Anhalt',
    'DE-SH' => 'Schleswig-Holstein',
    'DE-TH' => 'Thüringen',
];

/** Die 16 Namen als Liste – Rueckfallebene fuer address.state. */
function bundeslaender_namen(): array
{
    return array_values(BUNDESLAENDER_ISO);
}


// =============================================================
// HTTP
// =============================================================

/**
 * Holt eine JSON-Antwort von Nominatim. Gibt bei jedem Fehler null
 * zurueck (Timeout, HTTP != 200, kaputtes JSON) – der Aufrufer darf
 * aus null KEINE inhaltliche Aussage ableiten, nur "nicht erreichbar".
 *
 * Eigene Funktion statt weather_http_json(): dort ist der User-Agent
 * fest auf "Wetterproxy" gesetzt, und genau der muss hier ein anderer
 * sein. Uebernommen ist das Muster, nicht der Code.
 */
function bundesland_http_json(string $url, int $timeout = 8): ?array
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
        CURLOPT_USERAGENT      => NOMINATIM_USER_AGENT,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status !== 200) {
        error_log('bundesland: Abruf fehlgeschlagen (HTTP ' . $status . ($err !== '' ? ', ' . $err : '') . ')');
        return null;
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        error_log('bundesland: Antwort ist kein JSON');
        return null;
    }

    return $data;
}


// =============================================================
// Auswertung
// =============================================================

/**
 * Ordnet einen Nominatim-Adressblock einem der 16 Bundeslaender zu.
 *
 * Reihenfolge: erst der ISO-Code (immer gesetzt, auch fuer Berlin
 * und Hamburg), dann als Rueckfallebene der Klartextname. Beides
 * wird gegen BUNDESLAENDER_ISO geprueft, roh uebernommen wird nichts.
 *
 * @return string|null Name des Bundeslandes, sonst null
 */
function bundesland_aus_adresse(array $address): ?string
{
    // Ausland: Zuerich liefert z. B. brav ein state ("Zürich"), das
    // nur zufaellig kein deutsches Bundesland ist. Der Laendercode
    // ist die verlaessliche Schranke, nicht der Name.
    $land = $address['country_code'] ?? null;
    if (!is_string($land) || strtolower($land) !== 'de') {
        return null;
    }

    $iso = $address['ISO3166-2-lvl4'] ?? null;
    if (is_string($iso) && isset(BUNDESLAENDER_ISO[strtoupper($iso)])) {
        return BUNDESLAENDER_ISO[strtoupper($iso)];
    }

    $state = $address['state'] ?? null;
    if (is_string($state) && in_array($state, bundeslaender_namen(), true)) {
        return $state;
    }

    return null;
}

/**
 * Fragt Nominatim nach dem Bundesland einer Koordinate.
 *
 * Rueckgabe – die Dreiwertigkeit ist Absicht, dasselbe Muster wie
 * fetchZonesForPoint() im Geozonen-Modul:
 *   ['name' => 'Bayern']  zugeordnet
 *   ['name' => null]      Dienst hat geantwortet, aber kein
 *                         deutsches Bundesland zuordenbar
 *                         (Ausland, offene See, Grenzfall)
 *   null                  Abruf fehlgeschlagen – der Aufrufer darf
 *                         daraus NICHT "kein Bundesland" ableiten,
 *                         sonst gilt ein Dienstausfall dauerhaft
 *                         als geprueft und wird nie nachgeholt
 */
function bundesland_fetch(float $lat, float $lon): ?array
{
    // Ausserhalb der Deutschland-Box gibt es nichts zu fragen. Spart
    // den Request und markiert den Spot trotzdem als geprueft, damit
    // das Backfill-Skript ihn nicht endlos erneut anfasst.
    if (!point_in_dipul_area($lat, $lon)) {
        return ['name' => null];
    }

    // Die URL wird vollstaendig selbst gebaut; die Koordinate stammt
    // aus der Datenbank, aus $_GET/$_POST gelangt hier nichts hinein.
    // zoom=8 waehlt die Verwaltungsebene grob – der address-Block
    // enthaelt die vollstaendige Hierarchie unabhaengig davon.
    $url = NOMINATIM_REVERSE_URL . '?' . http_build_query([
        'format'          => 'jsonv2',
        'lat'             => $lat,
        'lon'             => $lon,
        'zoom'            => 8,
        'accept-language' => 'de',
    ], '', '&', PHP_QUERY_RFC3986);

    $daten = bundesland_http_json($url);
    if ($daten === null) {
        return null;
    }

    // Koordinaten ohne Treffer (offene See, Niemandsland) beantwortet
    // Nominatim mit HTTP 200 und {"error":"Unable to geocode"} – das
    // ist eine gueltige Antwort, kein Ausfall.
    if (isset($daten['error'])) {
        return ['name' => null];
    }

    $address = $daten['address'] ?? null;
    if (!is_array($address)) {
        error_log('bundesland: Antwort ohne address-Block');
        return null;
    }

    return ['name' => bundesland_aus_adresse($address)];
}


// =============================================================
// Persistenz
// =============================================================

/**
 * Ermittelt das Bundesland eines Spots und schreibt es weg.
 *
 * Wichtig – dieselbe Logik wie bei refresh_spot_zones(): schlaegt
 * der Abruf fehl, bleiben bundesland UND bundesland_checked_at
 * unveraendert (bei neuen Spots also NULL). Nur so laesst sich
 * "geprueft, keinem Land zuordenbar" von "nie geprueft" trennen –
 * wuerden wir den Zeitstempel trotzdem setzen, gaebe das
 * Backfill-Skript den Spot fuer immer verloren.
 *
 * @return bool true, wenn geprueft und gespeichert wurde
 */
function refresh_spot_bundesland(PDO $pdo, int $spotId, float $lat, float $lon): bool
{
    $treffer = bundesland_fetch($lat, $lon);
    if ($treffer === null) {
        return false;   // Backfill holt es spaeter nach
    }

    try {
        $pdo->prepare('UPDATE spots SET bundesland = ?, bundesland_checked_at = NOW() WHERE id = ?')
            ->execute([$treffer['name'], $spotId]);
    } catch (PDOException $e) {
        error_log('bundesland: Speichern fehlgeschlagen: ' . $e->getMessage());
        return false;
    }

    return true;
}
