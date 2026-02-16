#!/bin/bash
set -e

WP_PATH="/var/www/html"

wait_for_wordpress() {
    local attempt=0
    local max_attempts=60

    echo "[entrypoint] Waiting for WordPress files..."
    while [ ! -f "${WP_PATH}/wp-includes/version.php" ] && [ $attempt -lt $max_attempts ]; do
        sleep 2
        attempt=$((attempt + 1))
    done

    attempt=0
    echo "[entrypoint] Waiting for wp-config.php..."
    while [ ! -f "${WP_PATH}/wp-config.php" ] && [ $attempt -lt $max_attempts ]; do
        sleep 2
        attempt=$((attempt + 1))
    done
}

wait_for_database() {
    local attempt=0
    local max_attempts=30

    echo "[entrypoint] Waiting for database connection..."
    while [ $attempt -lt $max_attempts ]; do
        if wp db query "SELECT 1" --allow-root --path="${WP_PATH}" >/dev/null 2>&1; then
            echo "[entrypoint] Database is ready."
            return 0
        fi
        attempt=$((attempt + 1))
        sleep 2
    done

    echo "[entrypoint] ERROR: Database not reachable after ${max_attempts} attempts."
    return 1
}

configure_wp() {
    echo "[entrypoint] Setting WP constants in wp-config.php..."
    wp config set WP_HOME "${LOCALE_URL}" --allow-root --path="${WP_PATH}"
    wp config set WP_SITEURL "${LOCALE_URL}" --allow-root --path="${WP_PATH}"
    wp config set WP_DEBUG true --raw --allow-root --path="${WP_PATH}"
    wp config set WP_DEBUG_LOG true --raw --allow-root --path="${WP_PATH}"
}

install_wp_core() {
    if wp core is-installed --allow-root --path="${WP_PATH}" 2>/dev/null; then
        echo "[entrypoint] WordPress already installed, updating URLs..."
        wp option update siteurl "${LOCALE_URL}" --allow-root --path="${WP_PATH}"
        wp option update home "${LOCALE_URL}" --allow-root --path="${WP_PATH}"
        return 0
    fi

    echo "[entrypoint] Installing WordPress core..."
    wp core install \
        --url="${LOCALE_URL}" \
        --title="${WP_TITLE}" \
        --admin_user="${WP_ADMIN_USER}" \
        --admin_password="${WP_ADMIN_PASSWORD}" \
        --admin_email="${WP_ADMIN_EMAIL}" \
        --skip-email \
        --allow-root \
        --path="${WP_PATH}"
}

install_theme() {
    if wp theme is-installed ct-custom --allow-root --path="${WP_PATH}" 2>/dev/null; then
        echo "[entrypoint] CT Custom theme found, activating..."
        wp theme activate ct-custom --allow-root --path="${WP_PATH}"
        return 0
    fi

    echo "[entrypoint] CT Custom theme not found, falling back to twentytwentyfive..."
    if wp theme is-installed twentytwentyfive --allow-root --path="${WP_PATH}" 2>/dev/null; then
        echo "[entrypoint] Theme twentytwentyfive already installed, activating..."
        wp theme activate twentytwentyfive --allow-root --path="${WP_PATH}"
        return 0
    fi

    echo "[entrypoint] Installing default theme..."
    wp theme install twentytwentyfive --activate --allow-root --path="${WP_PATH}"
}

install_plugins() {
    echo "[entrypoint] Installing and activating wordpress plugins..."
    #wp plugin install contact-form-7 --activate --allow-root --path="${WP_PATH}"
    echo "[entrypoint] Plugins ready."
}

fix_permissions() {
    echo "[entrypoint] Fixing wp-content permissions..."
    mkdir -p "${WP_PATH}/wp-content/uploads"
    mkdir -p "${WP_PATH}/wp-content/plugins"
    mkdir -p "${WP_PATH}/wp-content/themes"
    mkdir -p "${WP_PATH}/wp-content/upgrade"
    mkdir -p "${WP_PATH}/wp-content/themes/ct-custom/assets/fonts"
    mkdir -p "${WP_PATH}/wp-content/themes/ct-custom/translations"

    # With HOST_UID matching www-data, chown is mainly to align any
    # stale volume files from older builds.
    chown -R www-data:www-data "${WP_PATH}/wp-content"
    find "${WP_PATH}/wp-content" -type d -exec chmod 775 {} \;
    find "${WP_PATH}/wp-content" -type f -exec chmod 664 {} \;

    # Writable directories: PHP-FPM (www-data) must write here.
    # SGID ensures new files inherit the group.
    local writable_dirs="
        ${WP_PATH}/wp-content/themes/ct-custom/translations
        ${WP_PATH}/wp-content/themes/ct-custom/assets/fonts
        ${WP_PATH}/wp-content/uploads
    "
    for dir in $writable_dirs; do
        if [ -d "$dir" ]; then
            chown -R www-data:www-data "$dir"
            chmod 2775 "$dir"
            find "$dir" -type f -exec chmod 664 {} \;
        fi
    done

    echo "[entrypoint] Permissions fixed."
}

install_composer_deps() {
    local theme_dir="${WP_PATH}/wp-content/themes/ct-custom"

    if [ ! -f "${theme_dir}/composer.json" ]; then
        echo "[entrypoint] No composer.json in theme, skipping Composer install."
        return 0
    fi

    echo "[entrypoint] Installing Composer dependencies in theme..."
    composer install --no-dev --no-interaction --working-dir="${theme_dir}" 2>&1
    echo "[entrypoint] Composer dependencies installed."
}

setup_wordpress() {
    wait_for_wordpress
    fix_permissions
    wait_for_database
    configure_wp
    install_wp_core
    install_theme
    install_composer_deps
    install_plugins
    fix_permissions
    echo "[entrypoint] WordPress setup complete!"
}

# Run setup in background so php-fpm can start immediately
setup_wordpress &

# Hand off to the original WordPress entrypoint
exec docker-entrypoint.sh "$@"
