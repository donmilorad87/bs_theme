# CT Custom

WordPress theme by Coalition Technologies, built on the Underscores (`_s`) starter theme.

---

## Requirements

- PHP 8.1+
- Node.js 18+
- Composer
- Docker & Docker Compose (for local development)

---

## Local Development Setup

### 1. Start Docker Environment

```bash
# Add to /etc/hosts
127.0.0.1 coalitiontest.local

# Start all services
docker compose up --build -d
```

First run takes 2-3 minutes. Wait for WordPress setup to complete:

```bash
docker compose logs -f wordpress | grep '\[entrypoint\]'
# Wait for: [entrypoint] WordPress setup complete!
```

### 2. Access

| URL | Purpose |
|-----|---------|
| `https://coalitiontest.local/` | WordPress site |
| `https://coalitiontest.local/wp-admin/` | Admin panel (`admin`/`admin`) |
| `https://coalitiontest.local/phpmyadmin/` | Database management |

### 3. Frontend Build

```bash
cd wp-content/themes/ct-custom/assets/frontend/vite
npm install
npm run dev       # Watch mode (HMR)
npm run build     # Production build
```

### 4. PHP Dependencies

```bash
cd wp-content/themes/ct-custom
composer install
```

---

## Project Structure

```
ct-custom/
  assets/
    frontend/
      vite/               # Vite build tool + JS/SCSS source
        src/
          js/             # ES6 modules (auth, profile, etc.)
          scss/           # SCSS stylesheets
        vite.config.js    # Entry points: app.js, share.js, app.scss
      js/                 # Built JS output
      css/                # Built CSS output
  src/                    # PHP classes (PSR-4 autoloaded)
  inc/                    # WordPress theme includes
    blocks/               # Custom Gutenberg blocks
    customizer/           # Theme customizer settings
    multilang/            # Multilingual support
    patterns/             # Block patterns
    sidebar/              # Sidebar functions
    template/             # Template utilities
  template-parts/         # Template components
    auth/                 # Login, register, forgot-password, profile, etc.
  template-admin/         # Admin page templates
  tests/                  # PHPUnit test suite
    auth/                 # Auth endpoint + service tests
    multilang/            # Translation system tests
  Documentation/          # Project documentation
```

---

## Testing

### PHP Tests (PHPUnit)

```bash
cd wp-content/themes/ct-custom
vendor/bin/phpunit                        # All tests (134)
vendor/bin/phpunit --testsuite auth       # Auth tests only
vendor/bin/phpunit --filter LoginEndpointTest  # Single file
```

### JavaScript Tests (Vitest)

```bash
cd wp-content/themes/ct-custom/assets/frontend/vite
npm test                                  # All tests (107)
npm run test:watch                        # Watch mode
npx vitest run src/js/auth-page-flows.test.js  # Single file
```

Total: **241 tests** across PHP and JavaScript.

---

## Key Features

- **Authentication System**: Custom login, registration, password reset with JWT tokens, rate limiting, and activation codes
- **Multilingual Support**: CLDR plural rules, language detection, URL-based language switching, translation management
- **Frontend**: Vanilla ES6 classes bundled with Vite, SCSS preprocessing
- **Profile Management**: User profile editing, avatar upload, password change, user messages

---

## Docker Infrastructure

| Service | Image | Purpose |
|---------|-------|---------|
| MySQL | `mysql:9.5` | Database (utf8mb4, 256M InnoDB buffer) |
| WordPress | `wordpress:php8.5-fpm` | PHP-FPM + WP-CLI + Composer + OPcache |
| Nginx | `nginx:latest` (custom) | HTTPS, HTTP/2+3, Brotli/Zstd compression |
| phpMyAdmin | `phpmyadmin:5.2.3-fpm` | Database management UI |

Configuration: `.env` at project root. See `Documentation/DOCKER_INFRASTRUCTURE/` for details.

---

## Documentation

| Directory | AI Reference | Developer Guide |
|-----------|-------------|----------------|
| Tests | `Documentation/TESTS/TEST.md` | `Documentation/TESTS/TEST_USERS.md` |
| Docker | `Documentation/DOCKER_INFRASTRUCTURE/DOCKER_INFRASTRUCTURE.md` | `Documentation/DOCKER_INFRASTRUCTURE/DOCKER_INFRASTRUCTURE_USER.md` |
| Translations | `Documentation/Translations/TRANSLATIONS.md` | `Documentation/Translations/TRANSLATIONS_USER.md` |
| User Management | `Documentation/USER_MANAGEMENT/USER_MANAGEMENT.md` | `Documentation/USER_MANAGEMENT/USER_MANAGEMENT_USER.md` |

---

## License

GPLv2 or later.
