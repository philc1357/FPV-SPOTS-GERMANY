# Änderungsübersicht (8 bisher ungepushte Commits)

Chronologisch, älteste zuerst.

## 1. `efb5317` – Mehrfach-Upload für Spot-Fotos
- Bis zu 10 Bilder gleichzeitig auswählbar; JS lädt sie einzeln nacheinander hoch (Fortschrittsbalken, umgeht `post_max_size`).
- Ungültige Dateien (zu groß / falsches Format) werden einzeln übersprungen, Prüfung im Browser und serverseitig.
- Ergebnis-Report nach dem Reload (hochgeladen / übersprungen mit Grund).
- Rate-Limit von 20 auf 50 Bilder pro Stunde, Spot-Cap von 30 Bildern entfernt, Admins ohne Zähl-Limits.
- README und Hilfeseite angepasst.
- Betroffen: `upload_submit.php`, `spot_detail.php`, `hilfe.php`, `README.md`.

## 2. `af662de` – Altlasten entfernt
- `private/php/data_changes/*` gelöscht: web-erreichbare Handler, die das aktuelle Passwort nicht prüften (Nachfolger liegen in `account/`).
- Einmal-Tool `admin/strip_exif.php` entfernt.

## 3. `b44b6b3` – Session-Prüfung in allen schreibenden Endpunkten
- `auth_check.php` (Remember-Me, Session-Freshness, ToS-Wall) jetzt in allen Submit-Handlern und Spot-APIs.
  Nach einer Passwortänderung sind fremde Sessions auch für Schreibaktionen ungültig; die ToS-Wall ist nicht mehr umgehbar.
- `enforce_session_freshness()` liest den Admin-Status bei jedem Request frisch aus der DB; gelöschte Konten verlieren die Session sofort.
- `wants_json_response()`: API-/AJAX-Aufrufe (`ajax=1`) erhalten bei abgelaufener Session bzw. fehlender ToS-Zustimmung JSON statt Redirect.

## 4. `4704009` – Sicherheitshärtung
- **Kontaktformular:** zusätzliches IP-Limit (5/h) über `audit_logs` (`CONTACT_SUBMITTED`), da wechselnde E-Mail-Adressen das alte Limit umgingen.
- **Uploads:** `image_dimensions_ok()` lehnt Bilder > 12 000 px Kantenlänge bzw. > 40 Megapixel ab (Decompression-Bombs).
- **`.htaccess`:** `Require all denied` für `private/data`, `private/php/core`, `private/php/cli`, `private/php/maintenance`.
- Login-Redirect verwirft protokoll-relative Ziele (`//host/...`).
- `post_str()` (`core/input.php`): `$_POST`-Arrays führen nicht mehr zu TypeError/500.
- **CSP:** `script-src` ohne `'unsafe-inline'`, zusätzlich `object-src 'none'` und `base-uri 'self'`.
  Inline-Skripte nach `public/js/pages/` ausgelagert, Inline-Handler durch `data-confirm`/`data-action`/`data-hide-on-error`/`data-fallback-src` ersetzt (`public/js/ui_actions.js`).
- `ASSET_VERSION` 29 / `CACHE_VERSION` v30, neue Startseiten-Skripte im Pre-Cache.
- README (Sicherheitskonzept) und CLAUDE.md ergänzt.

## 5. `f81b872` – `post_str`-Aufrufe repariert
- Beim Umstellen wurde `trim($_POST[...])` zu `trimpost_str(...)` statt `trim(post_str(...))` – Fatal Error in Login, Registrierung, Spots, Forum, Kommentaren, Kontakt und Spot-APIs. In 20 Dateien korrigiert.

## 6. `0d9a4f8` – www auf Hauptdomain umleiten
- `www` und ohne-www hatten getrennte Cookies/Sessions (u. a. Badge bei Verbesserungsvorschlägen tauchte nach jedem Login wieder auf) → Redirect auf die Hauptdomain.
- `.htaccess` hängte HttpOnly/Secure/SameSite doppelt an Cookies, obwohl PHP sie schon setzt → entfernt.

## 7. `bde1f09` – Einheitliche Seitenbreite
- 800 px Breite und `<article>` für Rechts-, Hilfe- und Kontaktseiten; Kontaktformular höher.
- Betroffen: `kontakt.php`, `impressum.php`, `nutzungsbedingungen.php`, `terms_accept.php`.

## 8. `c83f14e` – Karten-Code als ES-Module, Spots per Kartenausschnitt
- `map.js` (953 Zeilen) zerlegt in `public/js/map/`: `main`, `core`, `state`, `util`, `baselayers`, `spot_style`, `layers/{registry,dipul,spots}`, `features/{spot_panel,placing,a3_circle,legend,location,pilot_class}`.
- Overlay-Registry für künftige Datenlayer.
- `.htaccess`: no-cache für `/public/js/map/` (Imports tragen kein `?v=`).
- `sw.js`: neue Module in `STATIC_ASSETS`, Versionen angehoben.
- Neu: `maintenance/bump_assets.php` hebt `ASSET_VERSION`/`CACHE_VERSION` gemeinsam an.
- API `spots.php`: validierter `bbox`-Parameter mit `LIMIT 2000`; Frontend lädt bei `moveend` nach.
  Index `idx_spots_lat_lng` per ALTER angelegt (nicht in den Dumps).
