# Test Infrastructure Reference

AI reference document for the ct-custom theme test suite.

---

## Overview

| Layer | Tool | Tests | Config |
|-------|------|-------|--------|
| PHP (Backend) | PHPUnit 10 | 134 | `phpunit.xml` |
| JS (Frontend) | Vitest 3 + jsdom | 107 | `vite.config.js` `test` block |

Total: **241 tests** across PHP and JavaScript.

---

## PHP Test Architecture

### Bootstrap: `tests/bootstrap-wp-stubs.php`

Provides in-memory implementations of WordPress functions so tests run without a real WordPress installation:

- **Transients**: `set_transient()` / `get_transient()` / `delete_transient()` backed by `$GLOBALS['bs_test_transients']`
- **Users**: `wp_insert_user()` / `get_user_by()` / `wp_check_password()` backed by `$GLOBALS['bs_test_users']`
- **User meta**: `get_user_meta()` / `update_user_meta()` backed by `$GLOBALS['bs_test_user_meta']`
- **Options**: `get_option()` / `update_option()` backed by `$GLOBALS['bs_test_options']`
- **REST**: `WP_REST_Request` / `WP_REST_Response` / `WP_Error` classes
- **Mail**: `wp_mail()` stub (PHPMailer fallback produces harmless stderr; does not affect test outcomes)
- **Security**: `wp_hash_password()` / `check_ajax_referer()` stubs
- **Template**: `get_theme_mod()` / `get_bloginfo()` / `esc_url()` / `wp_strip_all_tags()` / `wp_get_attachment_image_url()`

Guard: `define('BS_WP_STUBS_LOADED', true)` prevents double-loading.

### Base Class: `tests/auth/AuthTestCase.php`

All auth tests extend `AuthTestCase` which:

1. Requires `bootstrap-wp-stubs.php` in `setUpBeforeClass()`
2. Resets ALL global state arrays in `setUp()`
3. Provides helpers:
   - `makeRequest(params, method, headers)` - creates `WP_REST_Request`
   - `createTestUser(overrides)` - inserts a test user with sensible defaults
   - `setJwtConfig(secret, expiry)` - configures JWT options
   - `generateToken(userId)` / `generateResetToken(email)` - creates JWT tokens
   - `loginAsUser(userId)` - sets `$GLOBALS['bs_test_current_user']`

### PHPUnit Configuration: `phpunit.xml`

```xml
<testsuites>
    <testsuite name="multilang">
        <directory>tests/multilang</directory>
    </testsuite>
    <testsuite name="auth">
        <directory>tests/auth</directory>
    </testsuite>
</testsuites>
```

Bootstrap: `vendor/autoload.php` (Composer PSR-4 autoloading).

---

## PHP Test File Map

### Auth Tests (`tests/auth/`)

| File | Tests | What it covers |
|------|-------|----------------|
| `JwtServiceTest.php` | 8 | JWT encode/decode, expiry, invalid tokens |
| `JwtAuthTest.php` | 6 | JWT middleware: extract from header, validate, reject |
| `PasswordValidatorTest.php` | 7 | Password strength rules (length, cases, digit, special) |
| `RateLimiterTest.php` | 5 | Rate limit check, increment, reset, TTL |
| `CodeGeneratorTest.php` | 3 | 6-digit code generation, format, uniqueness |
| `RestLoggerTest.php` | 4 | REST error logging, format, truncation |
| `LoginEndpointTest.php` | 10 | Login: valid/invalid creds, inactive account, rate limit, email login |
| `RegisterEndpointTest.php` | 15 | Register: validation (username, email, password), duplicates, rate limit, meta |
| `LogoutEndpointTest.php` | 2 | Logout: success, session cleared |
| `ForgotPasswordEndpointTest.php` | 6 | Forgot password: enumeration prevention, code storage, rate limit |
| `VerifyActivationEndpointTest.php` | 7 | Activation code: valid/invalid, expiry, rate limit, cleanup |
| `ResendActivationEndpointTest.php` | 5 | Resend activation: valid/unknown email, active account, rate limit |
| `VerifyResetCodeEndpointTest.php` | 5 | Reset code: valid/invalid, expiry, rate limit, cleanup |
| `ResetPasswordEndpointTest.php` | 10 | Password reset: token validation, weak password, same password, user not found |
| `ProfileUpdateEndpointTest.php` | 4 | Profile update: first/last name, display_name computation |
| `ProfileChangePasswordTest.php` | 7 | Profile password change: wrong current, same password, mismatch, weak |
| `ProfileUploadAvatarTest.php` | 7 | Avatar upload: valid JPEG, size limit, MIME check, rate limit, error handling |
| `PageAccessControlTest.php` | 8 | Page access: guest/logged-in redirects, admin pages, non-singular skip |

### Multilang Tests (`tests/multilang/`)

| File | Tests | What it covers |
|------|-------|----------------|
| `CldrPluralRulesTest.php` | varies | CLDR plural category resolution |
| `LanguageManagerTest.php` | varies | Language detection, switching, URL generation |
| `TranslationServiceTest.php` | varies | Translation loading, fallback, interpolation |
| `TranslationServiceBlockContentTest.php` | varies | Block content translation |
| `TranslatorTest.php` | varies | Translator facade |

---

## JavaScript Test Architecture

### Vitest Configuration

In `vite.config.js`:

```js
test: {
    environment: 'jsdom',
    include: ['src/js/**/*.test.js'],
}
```

### Test Patterns

- **Fetch mocking**: `vi.stubGlobal('fetch', vi.fn())` before imports
- **DOM fixtures**: HTML strings set via `document.body.innerHTML` in `beforeEach`
- **window.location mocking**: `delete window.location; window.location = mockObject;`
- **scrollIntoView stub**: `Element.prototype.scrollIntoView = vi.fn()` (jsdom limitation)
- **Fake timers**: `vi.useFakeTimers()` + `vi.advanceTimersByTime(ms)` for `setTimeout` tests
- **Promise flushing**: `await Promise.resolve()` loop (works with both real and fake timers)
- **Cleanup**: `vi.useRealTimers()` in `afterEach` to prevent fake timer leaks

### DOM Fixtures Mirror PHP Templates

Integration test fixtures replicate the exact HTML structure from PHP template files (`template-parts/auth/*.php`):

- All `data-ct-validate-*` attributes for validation binding
- `.ct-auth-form__validation` rule checklists with `data-rule` attributes
- `.ct-auth-form__submit--disabled` initial state with `disabled` attribute
- `.ct-auth-form__password-wrap` containers with toggle buttons
- `.ct-auth-form__match-hint` containers for password confirmation
- `data-ct-password-section` with the `different` rule and `data-rule-compare`
- Hidden `input[name="email"]` and `input[name="reset_token"]` for flow data injection

---

## JavaScript Test File Map

### Unit Tests (`src/js/auth/`)

| File | Tests | What it covers |
|------|-------|----------------|
| `auth-config.test.js` | 14 | Password rules, username rules, email regex, code regex, constants |
| `auth-validator.test.js` | 11 | Field validation: required, min, email, password, username, match, code |
| `auth-store.test.js` | 4 | localStorage: get/set/clear token |
| `auth-api.test.js` | 9 | REST client: post, postAuth, getAuth, JWT header, nonce, 401 handler |
| `auth-profile.test.js` | 9 | Profile API: save, change password, avatar upload, messages loading, XSS escape |

### Component Tests (`src/js/`)

| File | Tests | What it covers |
|------|-------|----------------|
| `auth-page.test.js` | 8 | AuthPage: constructor, panel switching, tab clicks, hash navigation |
| `auth-header.test.js` | 5 | AuthHeader: greeting display, logout click, token clear |

### Integration Flow Tests (`src/js/`)

| File | Tests | What it covers |
|------|-------|----------------|
| `auth-page-flows.test.js` | 30 | Complete auth page flows: login, register, activation, forgot, reset |
| `profile-page-flows.test.js` | 17 | Complete profile page flows: tabs, save, password, logout, messages |

---

## How PHP Stubs Work

### Transient Simulation

```php
// set_transient stores value + TTL
$GLOBALS['bs_test_transients']['key'] = $value;
$GLOBALS['bs_test_transient_ttl']['key'] = time() + $expiration;

// get_transient checks TTL, returns false if expired
// delete_transient removes both entries
```

### User Simulation

```php
// wp_insert_user assigns auto-incrementing ID
$id = $GLOBALS['bs_test_next_user_id']++;
$GLOBALS['bs_test_users'][$id] = $user_data;

// get_user_by searches by 'login', 'email', or 'id'
// wp_check_password uses password_verify()
```

### Mail Simulation

PHPMailer is loaded but has no SMTP/sendmail configured. The `MailService::send()` method catches `PHPMailerException` and returns `false`. Endpoint tests verify behavior regardless of mail delivery.

---

## How JS DOM Fixtures Mirror PHP Templates

Each integration test file builds HTML that matches the rendered output of PHP templates:

| PHP Template | JS Fixture Function | Key Elements |
|-------------|-------------------|--------------|
| `template-parts/auth/login.php` | `getAuthCardHTML()` | `data-ct-validate-min="1"` on username, password rules checklist |
| `template-parts/auth/register.php` | `getAuthCardHTML()` | Username rules, email validation, match hint |
| `template-parts/auth/forgot-password.php` | `getAuthCardHTML()` | Email validation, `--disabled` button |
| `template-parts/auth/activation-code.php` | `getAuthCardHTML()` | NO `--disabled` on button, hidden email field |
| `template-parts/auth/reset-code.php` | `getAuthCardHTML()` | NO `--disabled` on button, hidden email field |
| `template-parts/auth/reset-password.php` | `getAuthCardHTML()` | Password + confirm with match hint, `--disabled` button |
| `template-parts/auth/profile.php` | `getProfileCardHTML()` | Tabs, password `different` rule, messages panel |

---

## Running Tests

### PHP Tests

```bash
cd wp-content/themes/ct-custom
vendor/bin/phpunit                    # all PHP tests
vendor/bin/phpunit --testsuite auth   # auth tests only
vendor/bin/phpunit --filter LoginEndpointTest  # single file
```

### JavaScript Tests

```bash
cd wp-content/themes/ct-custom/assets/frontend/vite
npm test              # all JS tests (vitest run)
npm run test:watch    # watch mode (vitest)
npx vitest run src/js/auth-page-flows.test.js  # single file
```
