#!/bin/sh
# =============================================================
# Entrypoint – erzeugt beim ersten Start ein selbstsigniertes
# Zertifikat für https://fpv.localhost und startet dann Apache
# =============================================================
set -e

CERT_DIR="/etc/ssl/app"
CERT="${CERT_DIR}/server.crt"
KEY="${CERT_DIR}/server.key"

mkdir -p "$CERT_DIR"

if [ ! -f "$CERT" ] || [ ! -f "$KEY" ]; then
    echo "[entrypoint] Erzeuge selbstsigniertes Zertifikat für fpv.localhost ..."
    openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
        -subj "/C=DE/O=FPV Spots Germany (lokal)/CN=fpv.localhost" \
        -addext "subjectAltName=DNS:fpv.localhost,DNS:*.fpv.localhost,DNS:localhost,IP:127.0.0.1" \
        -addext "basicConstraints=critical,CA:TRUE" \
        -keyout "$KEY" -out "$CERT" 2>/dev/null
    chmod 600 "$KEY"
    chmod 644 "$CERT"
    echo "[entrypoint] Zertifikat liegt unter docker/certs/server.crt"
fi

exec "$@"
