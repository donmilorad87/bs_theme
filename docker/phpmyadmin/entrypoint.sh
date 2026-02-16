#!/bin/sh
set -e

THEME_DIR="/var/www/html/themes/boodark"

# Copy BooDark theme from build stage into the volume
if [ -d /opt/boodark-theme ]; then
    rm -rf "${THEME_DIR}"
    cp -r /opt/boodark-theme "${THEME_DIR}"
    echo "[phpmyadmin] BooDark theme installed."
fi

# Hand off to original entrypoint
exec /docker-entrypoint.sh "$@"
