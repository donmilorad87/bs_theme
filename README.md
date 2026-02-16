# BS Custom — WordPress Development Environment

Dockerized WordPress development environment for the **BS Custom** theme by Blazing Sun.

---

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Add to `/etc/hosts`: `127.0.0.1 blazingsun.local`

### Start

```bash
docker compose up --build -d
```

First run takes 2-3 minutes. The WordPress entrypoint installs core, activates the theme, and runs Composer in the background. Monitor progress:

```bash
docker compose logs -f wordpress | grep '\[entrypoint\]'
# Wait for: [entrypoint] WordPress setup complete!
```

### Access

| URL | Purpose |
|-----|---------|
| `https://blazingsun.local/` | WordPress site |
| `https://blazingsun.local/wp-admin/` | Admin panel (`admin` / `admin`) |
| `https://blazingsun.local/phpmyadmin/` | Database management |

Your browser will warn about the self-signed SSL certificate — accept the exception to continue.

### Stop

```bash
docker compose down        # Stop (data persists in volumes)
docker compose down -v     # Stop and delete all data
```

---

## Docker Infrastructure

Four services orchestrated via `docker-compose.yml`:

| Service | Image | Role |
|---------|-------|------|
| **MySQL** | `mysql:9.5` | Database — utf8mb4, 256M InnoDB buffer, 100 max connections |
| **WordPress** | `wordpress:php8.5-fpm` | PHP-FPM app server — WP-CLI, Composer, OPcache tuning |
| **Nginx** | `nginx:latest` (custom multi-stage build) | Reverse proxy — HTTPS, HTTP/2, HTTP/3 (QUIC), Brotli + Zstd + Gzip compression |
| **phpMyAdmin** | `phpmyadmin:5.2.3-fpm` | Database UI — BooDark theme |

### Configuration

All environment variables are in `.env` at the project root:

```env
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=wordpress
LOCALE_URL=https://blazingsun.local/
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=admin
NGINX_PORT=80
NGINX_SSL_PORT=443
```

### Volumes & Bind Mounts

Theme, plugin, and upload directories are bind-mounted from the host for live editing:

| Host Path | Container Path |
|-----------|---------------|
| `./wp-content/themes/` | `/var/www/html/wp-content/themes/` |
| `./wp-content/plugins/` | `/var/www/html/wp-content/plugins/` |
| `./wp-content/uploads/` | `/var/www/html/wp-content/uploads/` |

Three named volumes persist database data, WordPress core files, and phpMyAdmin files across restarts.

---

## About the Theme

**BS Custom** is a WordPress theme built on the Underscores (`_s`) starter, using PHP 8.1+ with PSR-4 autoloading (`BSCustom\\` namespace). The frontend is vanilla ES6 classes bundled with Vite and SCSS.

Key capabilities:

- **Authentication** — Custom login, registration, password reset with JWT tokens, rate limiting, activation codes
- **Multilingual** — CLDR plural rules, URL-based language switching, translation management
- **Profile Management** — User profile editing, avatar upload, password change, messaging
- **Customizer** — Font management, dynamic CSS, export/import settings
- **Gutenberg Blocks** — Custom blocks for page access control, team members, sidebars

---

## Project Structure

```
ctwptest/
  docker-compose.yml          # Service definitions
  .env                        # Environment variables
  docker/
    mysql/                    # MySQL 9.5 + custom my.cnf
    wordpress/                # PHP 8.5-FPM + WP-CLI + Composer + entrypoint
    nginx/                    # Multi-stage build (Brotli/Zstd modules) + SSL certs
    phpmyadmin/               # phpMyAdmin 5.2.3-FPM + BooDark theme
  wp-content/
    themes/
      ct-custom/              # BS Custom theme source
        src/                  # PHP classes (PSR-4: BSCustom\)
        inc/                  # WordPress includes (blocks, customizer, patterns)
        assets/               # Frontend builds (Vite + JS + SCSS)
        tests/                # PHPUnit + Vitest test suites
        Documentation/        # Project documentation (see below)
    plugins/                  # WordPress plugins
    uploads/                  # Media uploads
```

---

## Documentation

Detailed documentation lives inside the theme at `wp-content/themes/ct-custom/Documentation/`. Each topic has two files: an AI reference doc and a developer guide.

| Topic | AI Reference | Developer Guide |
|-------|-------------|----------------|
| Docker Infrastructure | [DOCKER_INFRASTRUCTURE.md](wp-content/themes/ct-custom/Documentation/DOCKER_INFRASTRUCTURE/DOCKER_INFRASTRUCTURE.md) | [DOCKER_INFRASTRUCTURE_USER.md](wp-content/themes/ct-custom/Documentation/DOCKER_INFRASTRUCTURE/DOCKER_INFRASTRUCTURE_USER.md) |
| Tests | [TEST.md](wp-content/themes/ct-custom/Documentation/TESTS/TEST.md) | [TEST_USERS.md](wp-content/themes/ct-custom/Documentation/TESTS/TEST_USERS.md) |
| Translations | [TRANSLATIONS.md](wp-content/themes/ct-custom/Documentation/Translations/TRANSLATIONS.MD) | [TRANSLATIONS_USER.md](wp-content/themes/ct-custom/Documentation/Translations/TRANSLATIONS_USER.MD) |
| User Management | [USER_MANAGEMENT.md](wp-content/themes/ct-custom/Documentation/USER_MANAGEMENT/USER_MANAGEMENT.md) | [USER_MANAGEMENT_USER.md](wp-content/themes/ct-custom/Documentation/USER_MANAGEMENT/USER_MANAGEMENT_USER.md) |

---

## Common Commands

```bash
# Rebuild a single service
docker compose build wordpress && docker compose up -d wordpress

# Run WP-CLI inside the container
docker compose exec wordpress wp theme list --allow-root --path=/var/www/html

# View logs
docker compose logs -f nginx

# Full rebuild (no cache)
docker compose build --no-cache && docker compose up -d

# Reset everything (fresh install)
docker compose down -v && docker compose up --build -d
```

---

## License

GPLv2 or later.
