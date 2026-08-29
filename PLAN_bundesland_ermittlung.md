# Bundesland automatisch je Spot ermitteln

## Kontext

Der Artifact-Report "Kurs auf Platz 1" nennt fehlende Landingpages je Bundesland/Stadt als größten ungenutzten SEO-Hebel (Phase 3). Voraussetzung dafür ist, dass jeder Spot weiß, in welchem Bundesland er liegt. Aktuell hat `spots` kein solches Feld — dieser Plan liefert nur die Datengrundlage (Bundesland automatisch speichern bei neuen Spots + Nachtrag für die 109 bestehenden), **nicht** die Landingpages selbst.

Gewählter Ansatz (mit Nutzer abgestimmt):
- **Nominatim** (`nominatim.openstreetmap.org/reverse`) als Reverse-Geocoding-Dienst — liefert `address.state` direkt als deutschen Bundesland-Namen, gleiche Datenbasis wie die OSM-Kartenkacheln, kein API-Key nötig.
- **Feste 16er-Liste** der Bundesländer, API-Antwort wird dagegen gemappt/validiert statt roh übernommen.
- **Einmaliger manueller Backfill** für bestehende Spots (kein Cron) — ein Bundesland ändert sich praktisch nie, anders als die DIPUL-Zonen.

Die Umsetzung folgt eng den bestehenden Modulen `private/php/core/weather.php` (HTTP-Fetch-Pattern, Fehlerbehandlung) und `private/php/core/geozones.php` (`refresh_spot_zones()` als Vorbild für die Persistenz-Funktion) sowie `private/php/maintenance/refresh_spot_zones.php` (Vorbild für das Batch-Skript).

## 1. Schema-Änderung (`database.sql`)

Neue Spalten in `spots`, analog zu `zones_checked_at`:

```sql
ALTER TABLE spots
  ADD COLUMN bundesland VARCHAR(30) DEFAULT NULL AFTER copter_size,
  ADD COLUMN bundesland_checked_at DATETIME DEFAULT NULL AFTER bundesland;
```

`bundesland` bleibt `NULL`, solange nicht erfolgreich ermittelt (z. B. Dienst nicht erreichbar oder Koordinate außerhalb Deutschlands) — unterscheidbar von "geprüft, aber kein Treffer". `database.sql` entsprechend direkt in der `CREATE TABLE spots`-Definition ergänzen (Zeile ~1293), nicht nur als ALTER, damit ein frischer `docker compose up` mit korrektem Schema startet.

## 2. Neues Core-Modul `private/php/core/bundesland.php`

Nach dem Muster von `weather.php`:

- **Konstante**: 16 Bundesländer als kanonische Liste (`BUNDESLAENDER = ['Baden-Württemberg', 'Bayern', ..., 'Thüringen']`).
- **`bundesland_http_json(string $url, int $timeout = 8): ?array`** — cURL-Fetch analog `weather_http_json()`, eigener User-Agent mit Kontakt-Info (Nominatim-Pflicht laut Nutzungsrichtlinie, z. B. `'fpv-spots-germany.de Bundeslandermittlung (+https://fpv-spots-germany.de)'`).
- **`bundesland_fetch(float $lat, float $lon): ?string`** — baut die Nominatim-Reverse-URL (`format=jsonv2&lat=...&lon=...&zoom=8&accept-language=de`), ruft `bundesland_http_json()` auf, liest `address.state`, mappt/validiert gegen `BUNDESLAENDER` (exakter String-Vergleich reicht, da Nominatim für DE-Koordinaten die offiziellen deutschen Namen liefert). Kein Treffer in der Liste → `null`.
- **`refresh_spot_bundesland(PDO $pdo, int $spotId, float $lat, float $lon): bool`** — Pendant zu `refresh_spot_zones()`: ruft `bundesland_fetch()`, setzt bei Erfolg `bundesland` + `bundesland_checked_at = NOW()`, bei Dienstausfall (`null` wegen HTTP-Fehler) bleibt beides unverändert und die Funktion liefert `false` (damit das Backfill-Skript es erneut versucht); bei einer Koordinate, die zwar erreichbar war aber keinem der 16 Länder zugeordnet werden konnte (z. B. Ausland), wird `bundesland_checked_at` trotzdem gesetzt, `bundesland` bleibt `NULL` — analog zur "außerhalb DIPUL-Gebiet, trotzdem geprüft"-Logik in `geozones.php`.
- Kein Datei-Cache nötig: Bundesland wird pro Spot nur einmal ermittelt (kein wiederholter Abruf wie beim Wetter), das Ergebnis liegt dauerhaft in `spots.bundesland`.
- **Rate-Limit beachten**: Nominatim verlangt max. 1 Request/Sekunde. Der einzelne Aufruf beim Anlegen eines Spots ist unkritisch; im Backfill-Skript (Abschnitt 4) wird das über `usleep()` zwischen den Anfragen sichergestellt.

## 3. Einbindung in `spot_submit.php`

Direkt nach dem bestehenden Block (Zeile ~183–190):

```php
if ($newSpotId > 0) {
    refresh_spot_zones($pdo, $newSpotId, (float)$lat, (float)$lng);
    refresh_spot_bundesland($pdo, $newSpotId, (float)$lat, (float)$lng);
}
```

Synchron, aus demselben Grund wie beim Zonencheck: Ersteller soll das Ergebnis sofort sehen können, Fehlschlag blockiert das Anlegen nicht (Backfill-Skript holt es später nach). `require_once` für `core/bundesland.php` am Kopf der Datei ergänzen, neben dem bestehenden Require von `core/geozones.php`.

Zusätzlich prüfen, ob `private/php/spots/edit_spot_submit.php` Koordinaten ändern lässt — falls ja, dort denselben Aufruf ergänzen (Bundesland muss bei Koordinatenänderung neu ermittelt werden, `bundesland_checked_at` zurücksetzen bzw. neu abrufen).

## 4. Backfill-Skript `private/php/maintenance/refresh_spot_bundesland.php`

1:1 nach Vorbild `refresh_spot_zones.php`:

- CLI-Guard (`PHP_SAPI !== 'cli'`).
- `require_once` für `core/db.php` und `core/bundesland.php`.
- `--limit=N`-Flag, gedeckelt (z. B. max. 200).
- SQL: `SELECT id, latitude, longitude, name FROM spots WHERE bundesland_checked_at IS NULL ORDER BY id ASC LIMIT ?`.
- Pro Spot: `refresh_spot_bundesland($pdo, $id, $lat, $lon)` aufrufen, Fortschrittsausgabe (`#id  Name  OK Bayern` / `FEHLER`), `usleep()` zwischen den Aufrufen — wegen Nominatims 1-req/s-Limit hier **mindestens 1.100.000 µs** (statt der 500ms bei `refresh_spot_zones.php`, da dort viele parallele WFS-Requests pro Spot laufen, hier aber ein einzelner sequenzieller Request an einen strenger limitierten Dienst).
- Abschluss-Zusammenfassung, Exit-Code je nach Fehlerzahl.
- Kein Cron-Eintrag im Kopfkommentar (nur manueller Aufruf dokumentieren), da einmaliger Lauf gewünscht.
- Bei 109 bestehenden Spots und 1 req/s dauert ein Komplettlauf ohne `--limit` ca. 2 Minuten — unproblematisch in einem Durchgang.

## Betroffene Dateien

- `fpv-spots-germany.de/database.sql` — Spalten `bundesland`, `bundesland_checked_at` in `spots`.
- `fpv-spots-germany.de/private/php/core/bundesland.php` — neu.
- `fpv-spots-germany.de/private/php/spots/spot_submit.php` — Aufruf ergänzen + Require.
- `fpv-spots-germany.de/private/php/spots/edit_spot_submit.php` — ggf. Aufruf ergänzen (abhängig davon, ob Koordinaten dort editierbar sind — vor Umsetzung kurz prüfen).
- `fpv-spots-germany.de/private/php/maintenance/refresh_spot_bundesland.php` — neu.

## Verifikation

1. `find fpv-spots-germany.de -name '*.php' -print0 | xargs -0 -n1 php -l` — Syntax-Check aller geänderten/neuen Dateien.
2. `docker compose up -d --build`, dann `docker compose down -v && docker compose up -d`, um zu prüfen, dass das erweiterte `database.sql` sauber neu einliest.
3. Im laufenden Container einen Test-Spot mit bekannten Koordinaten (z. B. München, 48.1374/11.5755) über die UI anlegen und per `docker compose exec db mariadb -u root -proot d046b4e2 -e "SELECT id, name, bundesland, bundesland_checked_at FROM spots ORDER BY id DESC LIMIT 1;"` prüfen, dass `bundesland = 'Bayern'` gesetzt wurde.
4. Backfill-Skript testweise mit `--limit=5` gegen die bestehenden 109 Spots laufen lassen (`docker compose exec web php private/php/maintenance/refresh_spot_bundesland.php --limit=5`), Ausgabe und DB-Stand prüfen, danach den vollen Lauf ohne `--limit` ausführen.
5. Stichprobe: einen Spot mit Koordinate außerhalb Deutschlands (falls vorhanden) oder testweise künstlich prüfen, dass `bundesland_checked_at` gesetzt, aber `bundesland` `NULL` bleibt, statt eines Fehlers.
