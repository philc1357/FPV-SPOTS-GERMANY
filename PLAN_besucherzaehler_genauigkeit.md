# Eigenen Besucherzähler näher an die echten Zahlen bringen

## Context

In einer vorherigen Runde wurde bereits diagnostiziert, dass `site_visits` (eigener serverseitiger Zähler) und Umami Cloud (clientseitiges JS-Tracking) strukturell unterschiedlich messen und deshalb stark abweichende Zahlen liefern. Als erste Korrektur wurde der Admin-Traffic aus `site_visits` ausgeschlossen (`track_visit.php`, bereits umgesetzt, ungetestet committed im Arbeitsverzeichnis).

Der Nutzer ist mit der Genauigkeit weiterhin unzufrieden und hat zwei Fragen gestellt: (1) Kann der eigene Zähler näher an die echten Zahlen gebracht werden? (2) Lohnt sich ein Wechsel weg von Umami?

Zwei Explore-Agenten haben den Code dazu untersucht. Ergebnis:

- **Kein Doppelzähl-Bug** durch AJAX/API-Endpunkte gefunden — alle `public/php/api/*.php`-Dateien binden `header.php`/`track_visit()` nicht ein.
- **Bot-Filter-Lücken**: Der Regex in `track_visit.php` fängt die meisten großen Crawler (auch `Bytespider`, `GPTBot`, `ClaudeBot`), lässt aber `facebookexternalhit` (Messenger/WhatsApp-Link-Vorschau), `Claude-User`, `Google-InspectionTool`, `ia_archiver`/`omgili` (Archiv-/Such-Crawler) und `meta-externalagent` durch. `robots.txt` erlaubt zudem allen Crawlern uneingeschränkten Zugriff, d. h. mit nennenswertem, ungefiltertem Bot-Traffic ist zu rechnen.
- **Service-Worker-Bug** (`sw.js`): `STATIC_ASSETS` enthält `'/'` im Precache. Bei jedem Deploy (neue `CACHE_VERSION`) löst das für **jeden offenen Tab** einen unsichtbaren, cookie-authentifizierten GET auf `index.php` aus, der `track_visit()` durchläuft und zählt — ohne echten Seitenaufruf. Funktional überflüssig: `navigationStrategy()` (Zeile 174-178) fragt beim Offline-Fallback nie `caches.match('/')` ab, sondern immer nur `caches.match('/offline.html')`. Das Precachen von `'/'` hat also keinen Nutzen und lässt sich ersatzlos streichen.
- **Unique-Visitor-Zählung** hängt an `$_SESSION['visit_counted_date']`, die Session-Cookie-Lebensdauer ist aber `lifetime: 0` (verfällt beim Browser-Schließen). Jeder Neustart am selben Tag zählt denselben Menschen erneut als "neuen Besucher".
- **DNT/GPC**: `track_visit()` respektiert diese Signale bewusst nicht (anders als Umami) — vom Nutzer als so-lassen bestätigt, da `site_visits` nur anonyme Tagesaggregate ohne Personenbezug speichert.
- **Umami/Anbieterwechsel**: Vom Nutzer bewusst nicht verfolgt — jede rein clientseitige JS-Analytics-Lösung hat dieselbe strukturelle Unterzählung durch Adblocker/Tracking-Schutz, unabhängig vom Anbieter.

Vom Nutzer bestätigter Umsetzungsumfang:
1. Bot-Filter-Regex um die gefundenen Lücken erweitern.
2. Service-Worker-Precache-Bug fixen (`'/'` aus `STATIC_ASSETS` entfernen).
3. Unique-Visitor-Erkennung von einer reinen Session-Zugehörigkeit auf einen eigenen, von `PHPSESSID` entkoppelten Cookie mit Ablauf **zum Tagesende** umstellen (nicht länger) — verhindert Doppelzählung bei Neustarts am selben Tag, ohne ein mehrtägiges Profil zu erzeugen. Damit bewusst **keine** Consent-Pflicht nach TTDSG §25 (kein Wechsel zu einem echten Langzeit-Cookie, der einen Cookie-Consent-Banner erfordern würde).

## Umsetzung

### 1. Bot-Filter erweitern
**Datei:** `fpv-spots-germany.de/private/php/core/track_visit.php`, Zeile 33

Regex um die belegten Lücken ergänzen:
```php
if ($userAgent === ''
    || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|headless|facebookexternalhit|claude-user|inspectiontool|archiver|omgili|externalagent/i', $userAgent)
) {
    return;
}
```

### 2. Service-Worker-Precache-Bug fixen
**Datei:** `fpv-spots-germany.de/sw.js`

- Zeile 20 (`'/',` in `STATIC_ASSETS`) entfernen.
- `CACHE_VERSION` (Zeile 4) von `'v24'` auf `'v25'` erhöhen, damit bestehende Clients den alten `'/'`-Cache-Eintrag beim nächsten SW-Update loswerden und die neue Precache-Liste greift. `ASSET_VERSION` bleibt unverändert — es hat sich kein CSS/JS-Inhalt geändert, nur die SW-eigene Precache-Liste.

### 3. Unique-Visitor-Cookie mit Tagesende-Ablauf
**Datei:** `fpv-spots-germany.de/private/php/core/track_visit.php`, Zeile 51-55 (aktuelle Session-Flag-Logik) sowie Zeile 94-98 (Setzen des Flags nach erfolgreichem Insert)

Ersetzt die Session-Flag-Logik durch einen eigenen Cookie, Muster analog zu `analytics_optout.php` (`secure`/`httponly`/`samesite=Lax`, `path=/`):

```php
// --- Unique-Erkennung über Tages-Cookie --------------------------
// Eigener, von PHPSESSID entkoppelter Cookie mit Ablauf zum
// Tagesende: verhindert Doppelzählung bei Browser-Neustarts am
// selben Tag (Session-Cookie verfällt sonst beim Schließen), ohne
// über den Tag hinaus wiederzuerkennen. Reiner Flag-Wert ("1"),
// keine Zufalls-ID – dient nicht der Wiedererkennung einzelner
// Personen, nur der Tages-Deduplizierung. Kein Consent nach
// TTDSG §25 nötig, da kein mehrtägiges Profil entsteht.
$isNew = empty($_COOKIE['fpv_counted_today']) ? 1 : 0;
if ($isNew === 1) {
    setcookie('fpv_counted_today', '1', [
        'expires'  => strtotime('tomorrow'),
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
```

Das bisherige Setzen von `$_SESSION['visit_counted_date']` nach dem Insert (Zeile 96-98) entfällt ersatzlos, das Feld wird nicht mehr gebraucht. Der bestehende `session_status() !== PHP_SESSION_ACTIVE`-Guard (Zeile 39-41) bleibt unverändert bestehen, da er weiterhin für den Admin-Ausschluss (`$_SESSION['is_admin']`) gebraucht wird.

Der neue Cookie taucht aktuell nicht in der Datenschutzerklärung (`datenschutz.php`) auf. Da er streng genommen kein Consent braucht (keine Wiedererkennung über den Tag hinaus, kein Personenbezug), ist das rechtlich unkritisch, eine kurze Erwähnung machen wir im Sinne der Transparenzpflicht (Art. 13 DSGVO) trotzdem.

## Verifikation

1. Syntax-Check im Container:
   ```
   docker compose exec web bash -c "php -l fpv-spots-germany.de/private/php/core/track_visit.php"
   ```
2. PHP-CLI-Simulation (wie in der vorherigen Runde) für drei Fälle gegen die lokale Dev-DB, jeweils mit Vergleich von `site_visits` vorher/nachher:
   - Bot-UA (z. B. `facebookexternalhit/1.1`) → `page_views`/`unique_visitors` bleiben unverändert.
   - Normaler UA, kein `fpv_counted_today`-Cookie gesetzt → `page_views` +1, `unique_visitors` +1, Cookie wird gesetzt.
   - Gleicher Request erneut, diesmal mit gesetztem `fpv_counted_today`-Cookie (simuliert Browser-Neustart am selben Tag) → `page_views` +1, `unique_visitors` **unverändert**.
3. `sw.js`: Diff-Kontrolle, dass `'/'` nicht mehr in `STATIC_ASSETS` steht und `CACHE_VERSION` korrekt auf `'v25'` erhöht wurde (keine Testsuite/kein Linter für JS im Projekt).
