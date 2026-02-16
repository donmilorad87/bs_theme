# Docker Infrastructure Guide for Developers

Practical guide for running, configuring, and troubleshooting the ct-custom WordPress Docker environment.

---

## Quick Start

### Prerequisites

- Docker and Docker Compose installed
- `/etc/hosts` entry: `127.0.0.1 blazingsun.local`

### Start the Environment

```bash
cd /path/to/ctwptest
docker compose up --build -d
```

First run takes 2-3 minutes (MySQL init, WordPress install, Nginx module compilation). Subsequent starts are faster due to cached images and persistent volumes.

### Access

| URL | Service |
|-----|---------|
| `https://blazingsun.local/` | WordPress site |
| `https://blazingsun.local/wp-admin/` | WordPress admin |
| `https://blazingsun.local/phpmyadmin/` | phpMyAdmin |

**Default admin credentials**: `admin` / `admin` (configurable in `.env`).

Your browser will warn about the self-signed certificate. Accept the security exception to continue.

### Stop the Environment

```bash
docker compose down        # Stop containers (data persists in volumes)
docker compose down -v     # Stop containers AND delete all data volumes
```

---

## Services

### MySQL 9.5

- **Database**: `wordpress`
- **User**: `wordpress` / `wordpress`
- **Root password**: `rootpassword`
- **Config**: UTF-8 (`utf8mb4`), 256M InnoDB buffer, 100 max connections, 64M max packet

### WordPress (PHP 8.5-FPM)

- Runs as PHP-FPM on port 9000 (internal, not exposed to host)
- Includes WP-CLI, Composer, OPcache
- `WP_DEBUG` and `WP_DEBUG_LOG` enabled by default
- Debug log location: `wp-content/debug.log` (inside the `wp_data` volume)

### Nginx

- Reverse proxy serving both WordPress and phpMyAdmin
- HTTPS with self-signed certificate
- HTTP/2 and HTTP/3 (QUIC) enabled
- Triple compression: Gzip, Brotli, Zstd
- Font assets cached for 1 year; JS/CSS/images use `no-cache` (development mode)

### phpMyAdmin 5.2.3

- Accessible at `/phpmyadmin/`
- Login with MySQL credentials (`wordpress`/`wordpress` or `root`/`rootpassword`)
- BooDark dark theme pre-installed

---

## Configuration

### Environment Variables

All configuration is in `.env` at the project root:

```env
# Database
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=wordpress
MYSQL_ROOT_PASSWORD=rootpassword
WORDPRESS_DB_HOST=mysql:3306

# WordPress
LOCALE_URL=https://blazingsun.local/
WP_TITLE=Blazing Sun
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin
WP_ADMIN_EMAIL=admin@blazingsun.local

# Nginx ports
NGINX_PORT=80
NGINX_SSL_PORT=443
```

After changing `.env`, rebuild containers:

```bash
docker compose up --build -d
```

### Changing the Local Domain

1. Update `LOCALE_URL` in `.env`
2. Update `server_name` in `docker/nginx/default.conf`
3. Update `/etc/hosts` on your machine
4. Rebuild: `docker compose up --build -d`

### Changing Ports

Update `NGINX_PORT` and/or `NGINX_SSL_PORT` in `.env`. No rebuild needed:

```bash
docker compose down && docker compose up -d
```

---

## Development Workflow

### File Editing

Theme, plugin, and upload files are bind-mounted from the host:

| Host Path | Container Path |
|-----------|---------------|
| `./wp-content/themes/` | `/var/www/html/wp-content/themes/` |
| `./wp-content/plugins/` | `/var/www/html/wp-content/plugins/` |
| `./wp-content/uploads/` | `/var/www/html/wp-content/uploads/` |

Edit files on your host machine. Changes appear instantly in WordPress (no container restart needed for PHP/template changes).

### Running WP-CLI Commands

```bash
docker compose exec wordpress wp --allow-root --path=/var/www/html <command>
```

Examples:

```bash
# List installed themes
docker compose exec wordpress wp theme list --allow-root --path=/var/www/html

# Activate a theme
docker compose exec wordpress wp theme activate ct-custom --allow-root --path=/var/www/html

# Export database
docker compose exec wordpress wp db export /tmp/backup.sql --allow-root --path=/var/www/html

# Search-replace URLs
docker compose exec wordpress wp search-replace 'old-url.local' 'new-url.local' --allow-root --path=/var/www/html
```

### Running Composer in the Theme

```bash
docker compose exec wordpress composer install --working-dir=/var/www/html/wp-content/themes/ct-custom
```

Or from the host if Composer is installed locally:

```bash
cd wp-content/themes/ct-custom
composer install
```

### Viewing Logs

```bash
# All services
docker compose logs -f

# Single service
docker compose logs -f wordpress
docker compose logs -f nginx
docker compose logs -f mysql

# WordPress debug log
docker compose exec wordpress cat /var/www/html/wp-content/debug.log
```

### Accessing MySQL Directly

```bash
# Via docker compose
docker compose exec mysql mysql -u wordpress -pwordpress wordpress

# Via phpMyAdmin
# Open https://blazingsun.local/phpmyadmin/
```

---

## Rebuilding

### Rebuild a Single Service

```bash
docker compose build wordpress    # Rebuild WordPress image only
docker compose up -d wordpress    # Restart with new image
```

### Full Rebuild (no cache)

```bash
docker compose build --no-cache
docker compose up -d
```

### Reset Everything (fresh install)

```bash
docker compose down -v            # Remove containers + volumes
docker compose up --build -d      # Build images + start fresh
```

---

## SSL Certificates

The environment uses self-signed certificates stored in `docker/nginx/certs/`.

### Generating New Certificates

```bash
cd docker/nginx/certs
openssl req -x509 -nodes -days 3650 \
  -newkey rsa:2048 \
  -keyout selfsigned.key \
  -out selfsigned.crt \
  -subj "/CN=blazingsun.local"
```

Rebuild Nginx after replacing certificates:

```bash
docker compose build nginx && docker compose up -d nginx
```

### Trusting the Certificate (optional)

To avoid browser warnings:

**Linux (Chrome/Chromium)**:
```bash
sudo cp docker/nginx/certs/selfsigned.crt /usr/local/share/ca-certificates/blazingsun.crt
sudo update-ca-certificates
```

**macOS**:
```bash
sudo security add-trusted-cert -d -r trustRoot -k /Library/Keychains/System.keychain docker/nginx/certs/selfsigned.crt
```

---

## Troubleshooting

### WordPress Shows "Error Establishing Database Connection"

The database might not be ready yet. Check MySQL health:

```bash
docker compose ps                   # Check service status
docker compose logs mysql           # Check MySQL logs
docker compose exec mysql mysqladmin ping -h localhost
```

If MySQL is healthy but WordPress can't connect, verify `.env` credentials match.

### Permission Errors on Theme/Plugin Files

The WordPress container remaps `www-data` to UID/GID 1000 (your host user). If you see permission errors:

```bash
# Fix from host
sudo chown -R 1000:1000 wp-content/

# Or fix from container
docker compose exec wordpress chown -R www-data:www-data /var/www/html/wp-content
```

### Nginx Returns 502 Bad Gateway

WordPress PHP-FPM hasn't started yet, or crashed:

```bash
docker compose logs wordpress       # Check for errors
docker compose restart wordpress    # Restart PHP-FPM
```

### Container Keeps Restarting

Check logs for the failing service:

```bash
docker compose logs --tail=50 <service-name>
```

Common causes:
- MySQL: corrupted data volume -> `docker compose down -v` and rebuild
- WordPress: entrypoint script error -> check `docker compose logs wordpress`
- Nginx: config syntax error -> `docker compose exec nginx nginx -t`

### Browser Shows "Connection Refused"

1. Check containers are running: `docker compose ps`
2. Check ports aren't in use: `sudo lsof -i :80 -i :443`
3. Check `/etc/hosts` has `127.0.0.1 blazingsun.local`

### phpMyAdmin Shows Blank Page

The BooDark theme might not have copied correctly:

```bash
docker compose restart phpmyadmin
```

### Slow First Page Load

The first request after `docker compose up` may take 10-20 seconds while the background WordPress setup completes (installing core, activating theme, running Composer). Check progress:

```bash
docker compose logs -f wordpress | grep '\[entrypoint\]'
```

Wait for `[entrypoint] WordPress setup complete!` before testing.

---

## Adding WordPress Plugins

### Via WP-CLI

```bash
docker compose exec wordpress wp plugin install <plugin-slug> --activate --allow-root --path=/var/www/html
```

### Via Entrypoint (Auto-install on Build)

Edit `docker/wordpress/entrypoint.sh`, uncomment or add lines in `install_plugins()`:

```bash
install_plugins() {
    wp plugin install contact-form-7 --activate --allow-root --path="${WP_PATH}"
    wp plugin install woocommerce --activate --allow-root --path="${WP_PATH}"
}
```

Rebuild: `docker compose build wordpress && docker compose up -d wordpress`
