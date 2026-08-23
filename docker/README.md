# Lokale Docker-Umgebung

PHP 8.3 + Apache, MariaDB 10.11 und phpMyAdmin. Am Anwendungscode wird nichts verändert –
die Umgebung bildet das Produktionsverhalten nach (inklusive erzwungenem HTTPS).

## Start

```bash
docker compose up -d --build
```

Beim ersten Start passieren zwei Dinge automatisch:

1. `database.sql` wird in die Datenbank `d046b4e2` importiert.
2. Es wird ein selbstsigniertes Zertifikat nach `docker/certs/` erzeugt.

## URLs

| Was | Adresse |
|---|---|
| Website | <https://fpv.localhost> |
| phpMyAdmin | <http://localhost:8081> |
| MariaDB (extern, z. B. DBeaver) | `127.0.0.1:3306` |

**Warum `fpv.localhost` und nicht `localhost`?** Die `.htaccess` sendet einen
`Strict-Transport-Security`-Header mit einem Jahr Laufzeit. HSTS gilt hostweit und ignoriert
den Port – über `https://localhost` würde der Browser also *alle* künftigen
`http://localhost:*`-Projekte ein Jahr lang zwangsweise auf HTTPS umbiegen.
`*.localhost` löst in Chrome und Firefox automatisch auf `127.0.0.1` auf, ein
`/etc/hosts`-Eintrag ist nicht nötig.

## Zugangsdaten (nur lokal)

```
DB   d046b4e2 / fpvlocal      (root / root)
```

Sie stehen in `compose.yaml` und `docker/.env.docker`. Die echte `.env` im Projekt-Root
wird **nicht** verwendet: `docker/.env.docker` wird im Container darübergemountet, damit
Produktions-Passwörter gar nicht erst im Container landen.

## Zertifikatswarnung loswerden (optional)

Die einmalige Browser-Warnung zu akzeptieren reicht. Wer sie dauerhaft loswerden will,
importiert `docker/certs/server.crt` in den Zertifikatsspeicher des Browsers
(Firefox: Einstellungen → Zertifikate → Importieren, „Websites vertrauen" ankreuzen).

## Häufige Befehle

```bash
docker compose logs -f web           # Apache- und PHP-Fehler live
docker compose exec web bash         # Shell im PHP-Container
docker compose exec db mariadb -u d046b4e2 -pfpvlocal d046b4e2

docker compose down                  # stoppen, Daten bleiben erhalten
docker compose down -v               # stoppen und Datenbank löschen
```

### Dump neu einspielen

Das Init-Skript läuft nur bei leerem Datenverzeichnis. Für einen Reimport:

```bash
docker compose down -v && docker compose up -d
```

Alternativ ohne Volume-Reset:

```bash
docker compose exec -T db mariadb -u root -proot d046b4e2 < database.sql
```

### Neuen Dump ziehen

```bash
docker compose exec db mariadb-dump -u root -proot d046b4e2 > database.sql
```

## Bekannte Einschränkung: Mailversand

Es läuft kein Mail-Container, und die SMTP-Werte in `docker/.env.docker` sind Platzhalter.
Damit schlagen die drei Flows fehl, die den Mailer einbinden:

- `private/php/contact/kontakt_submit.php`
- `private/php/auth/forgot_password_submit.php`
- `private/php/suggestions/suggestion_comment_submit.php`

**Registrierung und Login sind nicht betroffen** – `register_submit.php` verschickt keine Mail.
Falls Mailversand doch gebraucht wird: Mailpit ergänzen. Dafür muss `mailer.php` angepasst
werden, weil dort `ENCRYPTION_SMTPS` fest verdrahtet ist.

## Aufbau

```
compose.yaml              Services web / db / phpmyadmin
docker/
├── php/
│   ├── Dockerfile        PHP 8.3-apache + pdo_mysql, gd, exif
│   ├── vhost.conf        DocumentRoot fpv-spots-germany.de, AllowOverride All
│   ├── php.ini           Dev-Overrides (Uploads, display_errors)
│   └── entrypoint.sh     erzeugt das Zertifikat beim ersten Start
├── .env.docker           lokale App-Config (mountet über /var/www/html/.env)
└── certs/                generiert, nicht im Git
```

Gemountet wird der **Projekt-Root** nach `/var/www/html`, `DocumentRoot` ist das
Unterverzeichnis `fpv-spots-germany.de/`. Das ist nötig, weil `db.php` und `mailer.php`
`vendor/autoload.php` und die `.env` eine Ebene über dem Webroot erwarten – und es sorgt
zugleich dafür, dass `vendor/`, `.env` und `database.sql` nicht über den Webserver
erreichbar sind.

Apache läuft mit UID/GID 1000, damit hochgeladene Dateien in
`fpv-spots-germany.de/public/uploads/` auf dem Host dem eigenen Benutzer gehören.
