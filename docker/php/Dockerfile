# =============================================================
# FPV Spots Germany – lokaler PHP/Apache-Entwicklungscontainer
# =============================================================
FROM php:8.3-apache

# -------------------------------------------------------------
# PHP-Extensions
#   pdo_mysql : PDO-Verbindung in private/php/core/db.php
#   gd        : Bildverarbeitung (image_utils.php, Spot-/Forum-Uploads)
#   exif      : Orientierungs-Auswertung bei Foto-Uploads
#   mbstring, curl, fileinfo sind im offiziellen Image bereits aktiv
# -------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd exif \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

# -------------------------------------------------------------
# Apache-Module
#   rewrite : Clean-URL-Routing aus .htaccess
#   headers : Security-Header + Set-Cookie-Edit-Regel aus .htaccess
#   ssl     : HTTPS (der Code erzwingt Secure-Cookies)
# -------------------------------------------------------------
RUN a2enmod rewrite headers ssl

# -------------------------------------------------------------
# Composer-Binary für spätere Dependency-Läufe im Container
# -------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -------------------------------------------------------------
# UID/GID-Mapping: www-data auf den Host-User umbiegen, damit
# Upload-Dateien im Bind-Mount dem Host-User gehören und nicht root
# -------------------------------------------------------------
ARG UID=1000
ARG GID=1000
RUN groupmod -g "${GID}" www-data \
    && usermod  -u "${UID}" -g "${GID}" www-data

# -------------------------------------------------------------
# Konfiguration
# -------------------------------------------------------------
COPY vhost.conf     /etc/apache2/sites-available/000-default.conf
COPY php.ini        /usr/local/etc/php/conf.d/zz-app.ini
COPY entrypoint.sh  /usr/local/bin/app-entrypoint.sh
RUN chmod +x /usr/local/bin/app-entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80 443

ENTRYPOINT ["/usr/local/bin/app-entrypoint.sh"]
CMD ["apache2-foreground"]
