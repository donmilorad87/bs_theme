# Docker Infrastructure Reference

AI reference document for the ct-custom WordPress development environment.

---

## Overview

| Service | Image | Port | Purpose |
|---------|-------|------|---------|
| MySQL | `mysql:9.5` | 3306 (internal) | Database server |
| WordPress | `wordpress:php8.5-fpm` | 9000 (internal) | PHP-FPM application server |
| Nginx | `nginx:latest` (custom build) | 80, 443 (TCP+UDP) | Reverse proxy with Brotli/Zstd |
| phpMyAdmin | `phpmyadmin:5.2.3-fpm` | 9000 (internal) | Database management UI |

---

## Architecture

```
Browser
  |
  v
Nginx (:80 -> 301 -> :443)
  |--- HTTPS/HTTP2/HTTP3(QUIC)
  |
  |-- *.php --> fastcgi_pass wordpress:9000
  |-- /phpmyadmin/* --> fastcgi_pass phpmyadmin:9000
  |-- static assets --> served directly from shared volume
  |
  v
WordPress PHP-FPM <--> MySQL
```

### Volumes

| Volume | Mount Point | Purpose |
|--------|-------------|---------|
| `mysql_data` | `/var/lib/mysql` (mysql) | Persistent database storage |
| `wp_data` | `/var/www/html` (wordpress, nginx) | WordPress core files |
| `phpmyadmin_data` | `/var/www/html` (phpmyadmin), `/var/www/phpmyadmin` (nginx) | phpMyAdmin files |

### Bind Mounts (Host -> Container)

| Host Path | Container Path | Services |
|-----------|---------------|----------|
| `./wp-content/themes` | `/var/www/html/wp-content/themes` | wordpress, nginx |
| `./wp-content/plugins` | `/var/www/html/wp-content/plugins` | wordpress, nginx |
| `./wp-content/uploads` | `/var/www/html/wp-content/uploads` | wordpress, nginx |

---

## Service Details

### MySQL (`docker/mysql/`)

**Dockerfile**: Extends `mysql:9.5`, copies custom `my.cnf`.

**Configuration** (`my.cnf`):
- `character-set-server=utf8mb4`, `collation-server=utf8mb4_unicode_ci`
- `max_allowed_packet=64M`
- `innodb_buffer_pool_size=256M`
- `max_connections=100`
- Client default charset: `utf8mb4`

**Healthcheck**: `mysqladmin ping -h localhost` every 10s, 5 retries, 5s timeout.

**Environment variables** (from `.env`):
- `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`

---

### WordPress (`docker/wordpress/`)

**Dockerfile**: Extends `wordpress:php8.5-fpm`.

**Build features**:
- UID/GID remapping: `HOST_UID=1000`, `HOST_GID=1000` — `www-data` user remapped to match host user, preventing bind-mount permission issues
- Installed tools: `mysql-client`, `git`, `unzip`, WP-CLI, Composer
- OPcache tuning: `revalidate_freq=60`, `interned_strings_buffer=16`, `max_accelerated_files=10000`

**Entrypoint** (`entrypoint.sh`):
Runs setup in background (non-blocking) so `php-fpm` starts immediately, then hands off to original `docker-entrypoint.sh`.

Setup sequence:
1. `wait_for_wordpress()` — polls for `wp-includes/version.php` and `wp-config.php` (max 60 attempts, 2s interval)
2. `fix_permissions()` — creates required directories, sets `www-data` ownership, `775`/`664` permissions, SGID on writable dirs (`translations/`, `assets/fonts/`, `uploads/`)
3. `wait_for_database()` — polls `wp db query "SELECT 1"` via WP-CLI (max 30 attempts, 2s interval)
4. `configure_wp()` — sets `WP_HOME`, `WP_SITEURL` to `$LOCALE_URL`, enables `WP_DEBUG` + `WP_DEBUG_LOG`
5. `install_wp_core()` — runs `wp core install` if not installed, otherwise updates `siteurl`/`home` options
6. `install_theme()` — activates `ct-custom` if present, falls back to `twentytwentyfive`
7. `install_composer_deps()` — runs `composer install --no-dev` in theme directory if `composer.json` exists
8. `install_plugins()` — placeholder (all plugin install lines commented out)
9. `fix_permissions()` — runs again to fix ownership of newly created files

**Depends on**: `mysql` (condition: `service_healthy`)

**Environment variables** (from `.env`):
- `WORDPRESS_DB_HOST`, `WORDPRESS_DB_NAME`, `WORDPRESS_DB_USER`, `WORDPRESS_DB_PASSWORD`
- `LOCALE_URL`, `WP_TITLE`, `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, `WP_ADMIN_EMAIL`

---

### Nginx (`docker/nginx/`)

**Dockerfile**: Multi-stage build.

**Stage 1 (builder)**: Compiles Brotli and Zstd nginx modules from source.
- Clones `google/ngx_brotli` and `tokers/zstd-nginx-module`
- Builds as dynamic modules against the installed nginx version's configure flags
- Produces 4 `.so` files: `ngx_http_brotli_filter_module.so`, `ngx_http_brotli_static_module.so`, `ngx_http_zstd_filter_module.so`, `ngx_http_zstd_static_module.so`

**Stage 2 (final)**: Runtime image.
- Installs `libbrotli1`, `libzstd1` runtime libraries
- Copies compiled modules from builder
- Injects `load_module` directives at top of `nginx.conf`
- Changes worker user from `nginx` to `www-data` (matches PHP-FPM user for shared cache/volume)
- Copies SSL certificates from `docker/nginx/certs/`
- Copies `default.conf`

**Configuration** (`default.conf`):

Port 80 server: HTTP->HTTPS 301 redirect.

Port 443 server:
- **Protocols**: HTTP/2 (`http2 on`), HTTP/3 QUIC (`listen 443 quic reuseport`), TLS 1.2+1.3, `ssl_early_data on`
- **Alt-Svc header**: Advertises h3 on all responses
- **Security**: `server_tokens off`, `fastcgi_hide_header X-Powered-By`
- **Upload limit**: `client_max_body_size 64M`
- **Compression** (triple-stack, all with `comp_level 6`, `min_length 256`):
  - Gzip (fallback): text, CSS, JS, JSON, XML, SVG, woff2
  - Brotli (preferred): same types, ~20% smaller than gzip
  - Zstd (fastest decompression): same types
- **PHP routing**: `fastcgi_pass wordpress:9000`, passes `HTTPS on` and `HTTP_AUTHORIZATION` header, 300s timeout
- **phpMyAdmin routing**: `location ^~ /phpmyadmin/` with alias to `/var/www/phpmyadmin/`, PHP routed to `phpmyadmin:9000`
- **Font caching**: `Cache-Control: public, max-age=31536000, immutable` for woff2/ttf/eot/otf
- **Static assets**: `Cache-Control: no-cache` for js/css/images (development mode)
- **Hidden files**: `location ~ /\.` returns `deny all`

**Depends on**: `wordpress`, `phpmyadmin`

**Exposed ports**: `${NGINX_PORT}:80`, `${NGINX_SSL_PORT}:443/tcp`, `${NGINX_SSL_PORT}:443/udp` (UDP for QUIC)

---

### phpMyAdmin (`docker/phpmyadmin/`)

**Dockerfile**: Extends `phpmyadmin:5.2.3-fpm`.

**Custom config** (`config.inc.php`):
- Server: host from `PMA_HOST` env, port from `PMA_PORT` env
- `AllowNoPassword = false`
- `MaxRows = 50`
- `SendErrorReports = 'never'`
- `ThemeDefault = 'boodark'`

**Entrypoint** (`entrypoint.sh`):
- Copies BooDark theme from `/opt/boodark-theme` to `/var/www/html/themes/boodark`
- Hands off to original phpMyAdmin entrypoint

**Depends on**: `mysql` (condition: `service_healthy`)

---

## Environment Variables (`.env`)

| Variable | Default | Used By |
|----------|---------|---------|
| `MYSQL_DATABASE` | `wordpress` | mysql, wordpress |
| `MYSQL_USER` | `wordpress` | mysql, wordpress |
| `MYSQL_PASSWORD` | `wordpress` | mysql, wordpress |
| `MYSQL_ROOT_PASSWORD` | `rootpassword` | mysql, phpmyadmin |
| `WORDPRESS_DB_HOST` | `mysql:3306` | wordpress |
| `LOCALE_URL` | `https://coalitiontest.local/` | wordpress |
| `WP_TITLE` | `Coalition Test` | wordpress |
| `WP_ADMIN_USER` | `admin` | wordpress |
| `WP_ADMIN_PASSWORD` | `admin` | wordpress |
| `WP_ADMIN_EMAIL` | `admin@coalitiontest.local` | wordpress |
| `NGINX_PORT` | `80` | nginx |
| `NGINX_SSL_PORT` | `443` | nginx |

---

## File Map

```
docker-compose.yml              # Service definitions, volumes, ports
.env                            # Environment variables

docker/
  mysql/
    Dockerfile                  # MySQL 9.5 with custom config
    my.cnf                      # Character set, buffer pool, connections

  wordpress/
    Dockerfile                  # PHP 8.5-FPM + WP-CLI + Composer + OPcache
    entrypoint.sh               # Background setup: wait -> configure -> install

  nginx/
    Dockerfile                  # Multi-stage build: compile Brotli + Zstd modules
    default.conf                # HTTPS, HTTP/2+3, triple compression, routing
    certs/                      # Self-signed SSL certificates
      selfsigned.crt
      selfsigned.key

  phpmyadmin/
    Dockerfile                  # phpMyAdmin 5.2.3-FPM
    config.inc.php              # Server config, BooDark theme
    entrypoint.sh               # Theme copy into volume
    themes/
      boodark/                  # Dark theme files
```

---

## Network Topology

All services are on the default Docker Compose network. Internal communication:

| From | To | Protocol | Port |
|------|----|----------|------|
| nginx | wordpress | FastCGI | 9000 |
| nginx | phpmyadmin | FastCGI | 9000 |
| wordpress | mysql | MySQL | 3306 |
| phpmyadmin | mysql | MySQL | 3306 |

External access (host):
- `http://coalitiontest.local:80` -> 301 -> `https://coalitiontest.local:443`
- `https://coalitiontest.local/phpmyadmin/` -> phpMyAdmin UI

---

## Startup Sequence

1. **MySQL** starts, runs healthcheck (`mysqladmin ping`)
2. **WordPress** starts after MySQL is healthy. PHP-FPM starts immediately. Background setup begins:
   - Waits for WP core files (from `docker-entrypoint.sh` volume)
   - Fixes permissions
   - Waits for DB connection
   - Configures WP constants (`WP_HOME`, `WP_SITEURL`, `WP_DEBUG`)
   - Installs WP core (or updates URLs if already installed)
   - Activates `ct-custom` theme
   - Runs `composer install --no-dev`
3. **phpMyAdmin** starts after MySQL is healthy. Copies BooDark theme, starts PHP-FPM.
4. **Nginx** starts after WordPress and phpMyAdmin. Serves requests immediately.

---

## Key Design Decisions

1. **PHP-FPM + Nginx separation**: WordPress and phpMyAdmin both use FPM images (not Apache), with Nginx as the single entry point for HTTP/HTTPS.
2. **Multi-stage Nginx build**: Brotli and Zstd modules are compiled from source to match the exact Nginx version, then only runtime libraries are carried to the final image.
3. **Background entrypoint**: WordPress setup runs in a background subshell so PHP-FPM starts without blocking. This avoids Nginx connection failures during initial setup.
4. **UID/GID matching**: The WordPress container remaps `www-data` to `1000:1000` (host user), preventing permission conflicts on bind-mounted theme/plugin files.
5. **Triple compression**: Gzip (universal fallback), Brotli (~20% smaller), Zstd (fastest decompression) — clients negotiate the best supported encoding.
6. **HTTP/3 QUIC**: Nginx listens on 443/udp with `quic reuseport` for HTTP/3 support, advertised via `Alt-Svc` header.
7. **Self-signed SSL**: Development uses self-signed certificates stored in `docker/nginx/certs/`.
