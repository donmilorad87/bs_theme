# BS Custom — User Management & Authentication System (Technical Reference)

> Internal reference for Claude Code and developers maintaining the auth system.

---

## System Overview

The BS Custom theme includes a custom-built user authentication system that provides:

- **JWT-based authentication** with `firebase/php-jwt` (HS256 algorithm)
- **6-digit verification codes** stored in WordPress transients for account activation and password reset
- **IP-based and key-based rate limiting** via transient counters
- **Block-based page access control** using three Gutenberg blocks (`unprotected-page`, `protected-page`, `admin-page`)
- **Email verification flow** with SMTP via PHPMailer and Customizer-styled HTML templates
- **Profile management** with avatar upload, name editing, and password change
- **Separate Vite build projects** for auth page, profile page, and main frontend

No external authentication plugins are used. The system is self-contained within the theme.

### REST API Namespace

All auth endpoints are registered under `ct-auth/v1`. The `AuthRestController` hooks into `rest_api_init` and instantiates all endpoint classes.

### Authentication Methods

Two authentication strategies are supported by the permission callbacks:

1. **JWT Bearer token** -- `Authorization: Bearer <token>` header. Token stored client-side in `localStorage`.
2. **WordPress cookie** -- Standard `wp_logged_in_*` cookie. Used as fallback when no Bearer header is present.

The global function `bs_jwt_or_cookie_permission_check()` tries JWT first, then falls back to cookie auth. The function `bs_jwt_permission_check()` requires JWT only.

---

## File Map

### PHP — Traits (`src/RestApi/`)

| File | Trait | Purpose |
|------|-------|---------|
| `PasswordValidator.php` | `PasswordValidator` | Validates password strength: min 8 chars, lowercase, uppercase, digit, special character. Returns `true` or `WP_Error`. |
| `RateLimiter.php` | `RateLimiter` | IP-based and key-based rate limiting via WordPress transients. Methods: `get_client_ip()`, `is_rate_limited_by_ip()`, `is_rate_limited_by_key()`, `increment_rate_limit()`, `get_rate_limit_remaining()`, `format_wait_time()`. |
| `CodeGenerator.php` | `CodeGenerator` | Generates, stores, verifies, and deletes 6-digit zero-padded numeric codes. Uses `hash_equals()` for timing-safe comparison. Transient-backed with configurable TTL. |
| `RestLogger.php` | `RestLogger` | Logs messages with auto-prefixed class name to `error_log` when `WP_DEBUG` is enabled. Format: `[BS_REST_ClassName] message`. |

### PHP — Services (`src/Services/`)

| File | Class | Purpose |
|------|-------|---------|
| `JwtService.php` | `JwtService` | Issues and verifies JWTs using `firebase/php-jwt`. Config loaded from `bs_custom_jwt_auth` option. Issues session tokens (`user_id` claim) and short-lived reset tokens (`email` + `purpose` claims). |
| `JwtAuth.php` | `JwtAuth` | Static permission callbacks for REST endpoints. `jwt_permission_check()` validates Bearer token and sets current user via `wp_set_current_user()`. `jwt_or_cookie_permission_check()` tries JWT then falls back to `is_user_logged_in()`. |
| `MailService.php` | `MailService` | SMTP email sender via PHPMailer. Config from `bs_custom_email_config` option. Supports TLS/SSL/none encryption. Sends HTML emails with plain-text AltBody. |
| `EmailTemplate.php` | `EmailTemplate` | Generates styled HTML email content. Base template with accent bar, logo, title, content, and footer. Customizer-driven styling (colors, fonts, transforms). Methods for each email type: activation code, activation success, forgot password code, password reset success, password changed from profile. |

### PHP — Auth Endpoints (`src/RestApi/Endpoints/`)

| File | Class | Route | Method | Auth | Purpose |
|------|-------|-------|--------|------|---------|
| `Login.php` | `Login` | `/login` | POST | Public | Authenticates via `wp_signon()`. Checks `bs_account_active` meta. Issues JWT on success. Resends activation code if account inactive. |
| `Register.php` | `Register` | `/register` | POST | Public | Creates subscriber account with `bs_account_active=0`. Validates username (min 4, alphanumeric/.-_, max 2 specials), email, password strength, password match. Sends activation code email. |
| `Logout.php` | `Logout` | `/logout` | POST | JWT/Cookie | Calls `wp_logout()`. Requires authenticated user. |
| `ForgotPassword.php` | `ForgotPassword` | `/forgot-password` | POST | Public | Generates 6-digit reset code, sends via email. Always returns success to prevent email enumeration. |
| `VerifyActivation.php` | `VerifyActivation` | `/verify-activation` | POST | Public | Verifies activation code, sets `bs_account_active=1`, deletes code, sends activation success email. |
| `ResendActivation.php` | `ResendActivation` | `/resend-activation` | POST | Public | Generates new activation code, sends via email. Only sends if account is still inactive. Always returns success to prevent email enumeration. |
| `VerifyResetCode.php` | `VerifyResetCode` | `/verify-reset-code` | POST | Public | Verifies reset code, deletes code, issues short-lived JWT reset token (10 min, `purpose=password_reset`). |
| `ResetPassword.php` | `ResetPassword` | `/reset-password` | POST | Public | Verifies reset JWT token (`purpose=password_reset`), validates new password, checks password not reused, calls `wp_set_password()`, sends confirmation email. |
| `FormTemplate.php` | `FormTemplate` | `/form/{form_name}` | GET | Mixed | Returns server-rendered HTML for auth forms. Public forms: login, register, forgot-password, activation-code, reset-code, reset-password. Protected forms (JWT/Cookie): profile. |
| `ProfileUpdate.php` | `ProfileUpdate` | `/profile/update` | POST | JWT/Cookie | Updates `first_name`, `last_name`, and `display_name` for current user. |
| `ProfileChangePassword.php` | `ProfileChangePassword` | `/profile/change-password` | POST | JWT/Cookie | Verifies current password, validates new password strength, rejects same password reuse, calls `wp_set_password()`, sends notification email. |
| `ProfileUploadAvatar.php` | `ProfileUploadAvatar` | `/profile/upload-avatar` | POST | JWT/Cookie | Uploads image to media library via `media_handle_sideload()`. Stores attachment ID as `bs_avatar_id` user meta. Max 5MB, JPEG/PNG/GIF/WebP only. |

### PHP — Route Registration

| File | Class | Purpose |
|------|-------|---------|
| `src/RestApi/AuthRestController.php` | `AuthRestController` | Hooks `rest_api_init`, instantiates all 18 endpoint classes (auth + contact), calls `register()` on each. Max 20 endpoints. |

### PHP — Access Control

| File | Class | Purpose |
|------|-------|---------|
| `src/Blocks/PageAccessControl.php` | `PageAccessControl` | Hooks `template_redirect`. Checks for access-control Gutenberg blocks in post content. Redirects based on login state and role. |

### PHP — Page Templates

| File | Purpose |
|------|---------|
| `login-register.php` | Page template for auth page. Redirects logged-in users to profile. Renders tabbed card with 6 form panels (login, register, forgot-password, activation-code, reset-code, reset-password). Passes `data-rest-url`, `data-nonce`, `data-cache-version`, `data-home-url` as data attributes. |
| `profile.php` | Page template for profile page. Redirects non-logged-in users to auth page. Renders profile card with profile form. Passes `data-rest-url`, `data-nonce`, `data-cache-version`, `data-auth-url` as data attributes. |

### PHP — Template Parts (`template-parts/auth/`)

| File | Purpose |
|------|---------|
| `login.php` | Login form: username/email + password fields, forgot password link, password visibility toggle, real-time validation rules. |
| `register.php` | Registration form: username (with validation rules), email, first/last name, password + confirm with real-time validation and match hint. |
| `forgot-password.php` | Email input form with "Send Reset Code" button. |
| `activation-code.php` | Hidden email field + 6-digit code input (`inputmode="numeric"`, `maxlength="6"`). Resend code link. |
| `reset-code.php` | Hidden email field + 6-digit code input. "Request new code" link. |
| `reset-password.php` | Hidden reset token + new password + confirm password with real-time validation and match hint. |
| `profile.php` | Two-tab layout (Profile, Messages). Avatar section with upload button. Read-only email/username. Editable first/last name. Change password section with current/new/confirm fields and "different from current" validation rule. Messages tab loads via AJAX. |

### PHP — Global Functions (`inc/template/auth-forms.php`)

| Function | Purpose |
|----------|---------|
| `bs_custom_get_auth_data()` | Returns `{is_logged_in, display_name}` array. |
| `bs_jwt_or_cookie_permission_check()` | REST permission callback: JWT or cookie auth. Delegates to `JwtAuth::jwt_or_cookie_permission_check()`. |
| `bs_jwt_permission_check()` | REST permission callback: JWT only. Delegates to `JwtAuth::jwt_permission_check()`. |
| `bs_custom_get_page_url_by_template()` | Finds a published page by template filename, filtered by current language via `bs_language` meta. Falls back to any matching page. |
| `bs_custom_get_auth_page_url()` | Returns permalink for `login-register.php` template page. |
| `bs_custom_get_profile_page_url()` | Returns permalink for `profile.php` template page. |

### JavaScript — Auth Page (`assets/frontend/vite/src/js/`)

| File | Class/Export | Purpose |
|------|-------------|---------|
| `auth-page.js` | `AuthPage` | State machine controller for login/register page. Manages 6 panel states (LOGIN, REGISTER, FORGOT, ACTIVATION_CODE, RESET_CODE, RESET_PASSWORD). Handles tab switching, back bar, form submissions, flow data injection, loading overlays, `#register` hash navigation, and `redirect_to` query param. |
| `auth-header.js` | `AuthHeader` | Logout handler for the header. Binds to `.ct-auth-links` container. Clears token from store, calls logout endpoint, reloads page. |

### JavaScript — Auth Modules (`assets/frontend/vite/src/js/auth/`)

| File | Class/Export | Purpose |
|------|-------------|---------|
| `auth-config.js` | Constants | Exports `STORAGE_TOKEN_KEY`, `MAX_FIELDS`, `AUTH_FORMS`, `TAB_FORMS`, `FLOW_FORMS`, `VALIDATION` (email regex, username rules, password rules, code regex). |
| `auth-store.js` | `AuthStore` | `localStorage` manager for JWT token. Methods: `getToken()`, `setToken()`, `clearToken()`. Key: `bs_auth_token`. |
| `auth-api.js` | `AuthApi` | REST client with two auth modes: `post()` (nonce-based for public endpoints) and `postAuth()` / `getAuth()` / `uploadAuth()` (JWT Bearer header for protected endpoints). All requests include `X-WP-Nonce` header and `credentials: 'same-origin'`. Supports 401 unauthorized handler callback. |
| `auth-validator.js` | `AuthValidator` | Rule-based real-time validation engine. Binds `input`/`blur` events. Validates: required, min-length, email regex, password rules (5 checks + "different" rule), username rules (3 checks), match (confirm password), 6-digit code regex. Updates CSS classes on rule elements (`--pass`, `--fail`, `--info`). |
| `auth-form-binder.js` | `AuthFormBinder` | Binds validation + submit events to form panels. Enables/disables submit button based on validation state. Binds Enter key submission (swallows when invalid). Binds password visibility toggle buttons. |
| `auth-profile.js` | `AuthProfile` | Profile page actions: avatar upload (via `FormData` + `uploadAuth()`), save profile (`postAuth('profile/update')`), change password (`postAuth('profile/change-password')`), load user messages (`getAuth('contact/user-messages')`). XSS-safe HTML escaping via `textContent`. |

### SCSS (`assets/frontend/vite/src/scss/`)

| File | Purpose |
|------|---------|
| `_auth.scss` | Auth links in topbar (`.ct-auth-links`, greeting, separator, links). Responsive adjustments. |
| `_auth-page.scss` | Auth page layout (`.ct-auth-page`, `.ct-auth-card`), tab navigation, back bar, form panels, profile page layout (`.ct-profile-page`, `.ct-profile-card`). Responsive breakpoints for md/sm. |

---

## Flow Diagrams

### Registration Flow

```
User                        Frontend (AuthPage)              REST API                    Database
 |                               |                              |                          |
 | fills register form           |                              |                          |
 |------------------------------>|                              |                          |
 |                               | POST /register               |                          |
 |                               |----------------------------->|                          |
 |                               |                              | validate username        |
 |                               |                              | validate email           |
 |                               |                              | validate password        |
 |                               |                              | check rate limit (IP)    |
 |                               |                              |                          |
 |                               |                              | wp_insert_user()         |
 |                               |                              |------------------------->|
 |                               |                              |                          | user created (subscriber)
 |                               |                              |                          | bs_account_active = '0'
 |                               |                              |                          |
 |                               |                              | generate 6-digit code    |
 |                               |                              | store_code() (transient) |
 |                               |                              | send activation email    |
 |                               |                              |                          |
 |                               | {success, email}             |                          |
 |                               |<-----------------------------|                          |
 |                               |                              |                          |
 |                               | switch to activation-code    |                          |
 |                               | panel, inject email          |                          |
 |<------------------------------|                              |                          |
```

### Login Flow

```
User                        Frontend (AuthPage)              REST API                    Database
 |                               |                              |                          |
 | fills login form              |                              |                          |
 |------------------------------>|                              |                          |
 |                               | POST /login                  |                          |
 |                               |----------------------------->|                          |
 |                               |                              | check rate limit (IP)    |
 |                               |                              | resolve_username()       |
 |                               |                              | wp_signon()              |
 |                               |                              |                          |
 |                               |                              |--- IF inactive (403) --->|
 |                               |                              |   wp_logout()            |
 |                               |                              |   resend activation code |
 |                               |                              |   send email             |
 |                               | {requires_activation, email} |                          |
 |                               |<-----------------------------|                          |
 |                               | switch to activation-code    |                          |
 |                               |                              |                          |
 |                               |                              |--- IF active (200) ----->|
 |                               |                              |   issue JWT              |
 |                               |                              |   wp_create_nonce()      |
 |                               | {token, nonce, display_name} |                          |
 |                               |<-----------------------------|                          |
 |                               | store.setToken()             |                          |
 |                               | redirect (redirect_to or     |                          |
 |                               |   home URL)                  |                          |
 |<------------------------------|                              |                          |
```

### Password Reset Flow

```
User                        Frontend (AuthPage)              REST API                    Database
 |                               |                              |                          |
 | Step 1: Request code          |                              |                          |
 |------------------------------>|                              |                          |
 |                               | POST /forgot-password        |                          |
 |                               |----------------------------->|                          |
 |                               |                              | check rate limit (email) |
 |                               |                              | lookup user by email     |
 |                               |                              | generate 6-digit code    |
 |                               |                              | store_code() (transient) |
 |                               |                              | send reset code email    |
 |                               |                              |                          |
 |                               | {success} (always)           |                          |
 |                               |<-----------------------------|                          |
 |                               | switch to reset-code panel   |                          |
 |                               |                              |                          |
 | Step 2: Verify code           |                              |                          |
 |------------------------------>|                              |                          |
 |                               | POST /verify-reset-code      |                          |
 |                               |----------------------------->|                          |
 |                               |                              | check rate limit (IP)    |
 |                               |                              | verify_code()            |
 |                               |                              |   (hash_equals)          |
 |                               |                              | delete_code()            |
 |                               |                              | issue_reset_token()      |
 |                               |                              |   (10 min JWT)           |
 |                               | {reset_token}                |                          |
 |                               |<-----------------------------|                          |
 |                               | store in _flowData           |                          |
 |                               | switch to reset-password     |                          |
 |                               |                              |                          |
 | Step 3: Set new password      |                              |                          |
 |------------------------------>|                              |                          |
 |                               | POST /reset-password         |                          |
 |                               |----------------------------->|                          |
 |                               |                              | verify reset JWT         |
 |                               |                              |   (purpose check)        |
 |                               |                              | validate password        |
 |                               |                              | reject reuse of current  |
 |                               |                              | wp_set_password()        |
 |                               |                              | send success email       |
 |                               | {success}                    |                          |
 |                               |<-----------------------------|                          |
 |                               | show success, redirect to    |                          |
 |                               | login after 1.5s             |                          |
 |<------------------------------|                              |                          |
```

### Profile Update Flow

```
User                        Frontend (ProfilePage)           REST API                    Database
 |                               |                              |                          |
 | edits first/last name         |                              |                          |
 |------------------------------>|                              |                          |
 |                               | POST /profile/update         |                          |
 |                               | (Bearer JWT + X-WP-Nonce)    |                          |
 |                               |----------------------------->|                          |
 |                               |                              | jwt_or_cookie check      |
 |                               |                              | validate non-empty names |
 |                               |                              | wp_update_user()         |
 |                               |                              |   (first, last, display) |
 |                               | {success, display_name}      |                          |
 |                               |<-----------------------------|                          |
 |                               | update header greeting       |                          |
 |<------------------------------|                              |                          |
```

### Logout Flow

```
User                        Frontend (AuthHeader/ProfilePage)  REST API
 |                               |                               |
 | clicks logout                 |                               |
 |------------------------------>|                               |
 |                               | store.clearToken()            |
 |                               | POST /logout                  |
 |                               | (Bearer JWT or Cookie)        |
 |                               |------------------------------>|
 |                               |                               | wp_logout()
 |                               | {success}                     |
 |                               |<------------------------------|
 |                               | window.location.reload()      |
 |<------------------------------|                               |
```

---

## Rate Limiting Configuration

| Endpoint | Transient Prefix | Key Type | Max Attempts | Window (seconds) | Window (human) |
|----------|-----------------|----------|-------------|-----------------|----------------|
| Login | `bs_login_attempts_` | IP | 5 | 300 | 5 minutes |
| Register | `bs_register_attempts_` | IP | 3 | 3600 | 1 hour |
| Forgot Password | `bs_forgot_attempts_` | Email | 3 | 3600 | 1 hour |
| Verify Activation | `bs_verify_activation_` | IP | 5 | 300 | 5 minutes |
| Resend Activation | `bs_resend_activation_` | Email | 3 | 3600 | 1 hour |
| Verify Reset Code | `bs_verify_reset_` | IP | 5 | 300 | 5 minutes |
| Upload Avatar | `bs_avatar_upload_` | User ID | 5 | 60 | 1 minute |

### Transient Key Format

All transient keys are formed as: `{prefix}` + `md5({identifier})`.

Example: `bs_login_attempts_` + `md5('192.168.1.1')` = `bs_login_attempts_c0e31a9...`

### Rate Limit Response

Rate-limited requests return HTTP 429 with a human-readable wait time computed from the transient expiration timestamp. The `format_wait_time()` method returns strings like "4 minute(s) and 30 second(s)".

Exception: `ForgotPassword` and `ResendActivation` return HTTP 200 even when rate-limited to prevent email enumeration.

---

## JWT Configuration

### Storage

JWT configuration is stored in the WordPress option `bs_custom_jwt_auth` as a JSON string:

```json
{
    "secret": "your-secret-key-at-least-16-chars",
    "expiration_hours": 24
}
```

### Token Types

**Session Token** (issued on login):

| Claim | Value |
|-------|-------|
| `iss` | `get_site_url()` |
| `iat` | Current timestamp |
| `exp` | Current + `expiration_hours * 3600` |
| `user_id` | WordPress user ID (integer) |

**Reset Token** (issued on verify-reset-code):

| Claim | Value |
|-------|-------|
| `iss` | `get_site_url()` |
| `iat` | Current timestamp |
| `exp` | Current + `ttl_minutes * 60` (default 10 min) |
| `email` | User's email address |
| `purpose` | `"password_reset"` |

### Algorithm and Limits

| Setting | Value |
|---------|-------|
| Algorithm | HS256 |
| Max token length | 4096 bytes |
| Min secret length | 16 characters |
| Default session TTL | 24 hours |
| Reset token TTL | 10 minutes |

### Verification Flow

1. Extract `Bearer <token>` from `Authorization` header
2. Check token length <= 4096
3. Load secret from `bs_custom_jwt_auth` option
4. Decode using `Firebase\JWT\JWT::decode()` with `Key(secret, 'HS256')`
5. On `ExpiredException` or any `Exception`, return `false`
6. For session tokens: extract `user_id`, look up user, call `wp_set_current_user()`
7. For reset tokens: verify `purpose === 'password_reset'` and `email` claim exists

### Client-Side Token Storage

JWT tokens are stored in `localStorage` under key `bs_auth_token`. The `AuthStore` class manages get/set/clear operations. Token is cleared on logout and after password change.

---

## Security Design Decisions

### Email Enumeration Prevention

The `ForgotPassword` and `ResendActivation` endpoints always return `{success: true}` with a generic message regardless of whether the email exists in the database. Rate limiting still applies behind the scenes, but the response does not reveal whether the email is registered.

### Timing-Safe Code Comparison

All 6-digit verification codes are compared using PHP's `hash_equals()` function, which prevents timing-based side-channel attacks that could reveal valid code characters.

### CSRF Nonce Protection

All REST API requests include the `X-WP-Nonce` header with a `wp_rest` nonce. For public endpoints, this provides CSRF protection. For authenticated endpoints, the JWT Bearer token provides additional identity verification.

After successful login, the server returns a fresh nonce in the response, which the frontend stores and uses for subsequent requests via `api.setNonce()`.

### Password Reuse Prevention

Both `ResetPassword` and `ProfileChangePassword` endpoints reject new passwords that are identical to the current password, using `wp_check_password()` to compare against the stored hash.

### Account Activation Gate

New accounts are created with `bs_account_active = '0'` user meta. The login endpoint checks this meta and returns HTTP 403 with `requires_activation = true` if the account is inactive. It also automatically re-sends a fresh activation code and logs the user out.

### Input Sanitization

All endpoint arguments use WordPress sanitization callbacks:
- `sanitize_text_field` for text inputs
- `sanitize_email` for email inputs
- `sanitize_user` for usernames
- `sanitize_file_name` for uploaded file names
- Passwords are NOT sanitized (passed raw to `wp_signon()` / `wp_set_password()`)

### File Upload Security

Avatar uploads enforce:
- Maximum file size: 5MB (`MAX_FILE_SIZE = 5242880`)
- Allowed MIME types: `image/jpeg`, `image/png`, `image/gif`, `image/webp`
- Upload error code validation
- Rate limiting: 5 uploads per user per minute

### Error Message Abstraction

Login failures return a generic "Invalid credentials." message regardless of whether the username/email was not found or the password was wrong. This prevents username enumeration.

---

## Access Control Blocks

### Block Names

| Block | Constant | Behavior |
|-------|----------|----------|
| `ct-custom/unprotected-page` | `BLOCK_UNPROTECTED` | Guests only. Logged-in users are redirected to the profile page. |
| `ct-custom/protected-page` | `BLOCK_PROTECTED` | Logged-in users only. Guests are redirected to the auth (login) page. |
| `ct-custom/admin-page` | `BLOCK_ADMIN` | Admins only (`manage_options` capability). Guests go to auth page. Non-admin logged-in users go to home page. |

### How It Works

1. `PageAccessControl::boot()` registers a `template_redirect` hook.
2. On each singular page request, `handle_redirect()` checks if the page content contains any of the three access-control blocks using `has_block()`.
3. If the visitor does not meet the requirement, they are redirected using `wp_safe_redirect()` followed by `exit`.
4. The redirect targets are resolved via `bs_custom_get_profile_page_url()`, `bs_custom_get_auth_page_url()`, and `bs_get_language_home_url()`.

### Usage

To restrict a page, add the appropriate Gutenberg block to the page content in the block editor. No block renders visible output; the blocks exist solely as access-control markers parsed at the PHP level.

---

## Email Templates

### Template Structure

All emails use a shared base template (`EmailTemplate::wrap_in_base_template()`) with:

- Outer wrapper table with `#f4f4f4` background
- 600px centered content table with configurable background
- 4px accent-color header bar
- Optional site logo (custom_logo image or styled site name)
- Styled title heading
- Content area with configurable text styles
- Footer with contact point, social links, and copyright

### Customizer-Driven Styling

Template colors and typography are loaded from `get_theme_mod()` with these keys:

| Theme Mod Key | Default | Purpose |
|---------------|---------|---------|
| `bs_email_title_font_size` | 24 | Title font size (px) |
| `bs_email_title_color` | `#333333` | Title text color |
| `bs_email_title_bold` | `true` | Title font weight |
| `bs_email_title_transform` | `none` | Title text-transform |
| `bs_email_text_font_size` | 15 | Body text font size (px) |
| `bs_email_text_color` | `#555555` | Body text color |
| `bs_email_text_line_height` | 1.6 | Body line height |
| `bs_email_border_color` | `#E5E5E5` | Border/divider color |
| `bs_email_bg_color` | `#FFFFFF` | Content background color |
| `bs_email_accent_color` | `#FF6B35` | Accent color (header bar, code display, links) |

### Email Types

| Method | Subject | Trigger | Content |
|--------|---------|---------|---------|
| `activation_code($code)` | "Activate Your Account" | Registration, inactive login | Welcome message + centered 6-digit code in monospace font + "expires in 30 minutes" |
| `activation_success()` | "Account Activated" | Successful activation | Confirmation message + "you can now log in" |
| `forgot_password_code($code)` | "Password Reset Code" | Forgot password request | Reset request message + centered 6-digit code + "expires in 15 minutes" |
| `password_reset_success()` | "Password Reset Successful" | Successful password reset | Confirmation message + "contact support if not you" |
| `password_changed_from_profile()` | "Password Changed" | Profile password change | Notification + "reset password if not you" |

### SMTP Configuration

SMTP settings are stored in the WordPress option `bs_custom_email_config` as a JSON string:

```json
{
    "host": "smtp.example.com",
    "port": 587,
    "username": "user@example.com",
    "password": "smtp-password",
    "encryption": "tls",
    "from_email": "noreply@example.com",
    "from_name": "Site Name"
}
```

If `host` is empty, PHPMailer falls back to the default mail transport. If `from_email` is empty, it falls back to `admin_email`. If `from_name` is empty, it falls back to `bloginfo('name')`.

---

## Constants Reference

### PHP Constants

| Constant | Value | Class | Purpose |
|----------|-------|-------|---------|
| `PasswordValidator::PW_MIN_LENGTH` | 8 | `PasswordValidator` | Minimum password length |
| `Login::MAX_ATTEMPTS` | 5 | `Login` | Max login attempts per IP |
| `Login::WINDOW_SEC` | 300 | `Login` | Login rate limit window (5 min) |
| `Login::ACTIVATION_PREFIX` | `bs_activation_code_` | `Login` | Transient prefix for activation codes |
| `Login::ACTIVATION_TTL` | 1800 | `Login` | Activation code TTL (30 min) |
| `Register::MAX_ATTEMPTS` | 3 | `Register` | Max registrations per IP |
| `Register::WINDOW_SEC` | 3600 | `Register` | Registration rate limit window (1 hour) |
| `Register::MIN_USERNAME` | 4 | `Register` | Minimum username length |
| `Register::MAX_USERNAME_SPECIAL` | 2 | `Register` | Max special chars in username |
| `Register::ACTIVATION_PREFIX` | `bs_activation_code_` | `Register` | Transient prefix for activation codes |
| `Register::ACTIVATION_TTL` | 1800 | `Register` | Activation code TTL (30 min) |
| `ForgotPassword::MAX_ATTEMPTS` | 3 | `ForgotPassword` | Max forgot-password per email |
| `ForgotPassword::WINDOW_SEC` | 3600 | `ForgotPassword` | Forgot-password rate limit window (1 hour) |
| `ForgotPassword::RESET_PREFIX` | `bs_reset_code_` | `ForgotPassword` | Transient prefix for reset codes |
| `ForgotPassword::RESET_TTL` | 900 | `ForgotPassword` | Reset code TTL (15 min) |
| `VerifyActivation::MAX_ATTEMPTS` | 5 | `VerifyActivation` | Max verify-activation per IP |
| `VerifyActivation::WINDOW_SEC` | 300 | `VerifyActivation` | Verify-activation rate limit (5 min) |
| `ResendActivation::MAX_ATTEMPTS` | 3 | `ResendActivation` | Max resend per email |
| `ResendActivation::WINDOW_SEC` | 3600 | `ResendActivation` | Resend rate limit (1 hour) |
| `VerifyResetCode::MAX_ATTEMPTS` | 5 | `VerifyResetCode` | Max verify-reset per IP |
| `VerifyResetCode::WINDOW_SEC` | 300 | `VerifyResetCode` | Verify-reset rate limit (5 min) |
| `VerifyResetCode::TOKEN_TTL` | 10 | `VerifyResetCode` | Reset token lifetime (minutes) |
| `ProfileUploadAvatar::MAX_FILE_SIZE` | 5242880 | `ProfileUploadAvatar` | Max avatar file size (5 MB) |
| `ProfileUploadAvatar::MAX_UPLOADS` | 5 | `ProfileUploadAvatar` | Max uploads per user per window |
| `ProfileUploadAvatar::WINDOW_SEC` | 60 | `ProfileUploadAvatar` | Upload rate limit (1 min) |
| `JwtService::ALGORITHM` | `HS256` | `JwtService` | JWT signing algorithm |
| `JwtService::MAX_TOKEN_LEN` | 4096 | `JwtService` | Max token length (bytes) |
| `JwtService::MIN_SECRET_LEN` | 16 | `JwtService` | Min secret key length |
| `AuthRestController::MAX_ENDPOINTS` | 20 | `AuthRestController` | Max registered endpoints |
| `FormTemplate::MAX_FORMS` | 10 | `FormTemplate` | Max form template count |
| `MailService::MAX_RECIPIENT_LEN` | 320 | `MailService` | Max email recipient length |
| `MailService::MAX_SUBJECT_LEN` | 998 | `MailService` | Max email subject length |
| `EmailTemplate::MAX_CODE_LENGTH` | 10 | `EmailTemplate` | Max code length for templates |

### JavaScript Constants (`auth-config.js`)

| Constant | Value | Purpose |
|----------|-------|---------|
| `STORAGE_TOKEN_KEY` | `'bs_auth_token'` | localStorage key for JWT token |
| `MAX_FIELDS` | 20 | Max form fields to iterate |
| `AUTH_FORMS` | `['login', 'register', 'forgot-password', 'activation-code', 'reset-code', 'reset-password']` | All auth form panel names |
| `TAB_FORMS` | `['login', 'register']` | Forms shown with tab navigation |
| `FLOW_FORMS` | `['forgot-password', 'activation-code', 'reset-code', 'reset-password']` | Forms shown with back bar |
| `VALIDATION.EMAIL_REGEX` | `/^[^\s@]+@[^\s@]+\.[^\s@]+$/` | Client-side email validation |
| `VALIDATION.MIN_USERNAME` | 4 | Min username length |
| `VALIDATION.MIN_PASSWORD` | 8 | Min password length |
| `VALIDATION.CODE_REGEX` | `/^\d{6}$/` | 6-digit code validation |
| `VALIDATION.PASSWORD_RULES` | 5 rules | min-length, lowercase, uppercase, digit, special |
| `VALIDATION.USERNAME_RULES` | 3 rules | username-min, username-chars, username-special |

---

## Vite Build Projects

### Project Structure

The auth system uses three separate Vite build projects to achieve code-split, page-specific bundles:

| Project | Directory | Entry JS | Entry SCSS | Output JS | Output CSS |
|---------|-----------|----------|------------|-----------|------------|
| Main Frontend | `assets/frontend/vite/` | `src/js/app.js` | `src/scss/app.scss` | `js/app.js` | `css/app.css` |
| Frontend Auth | `assets/frontend-auth/vite/` | `src/js/auth-app.js` | `src/scss/auth-app.scss` | `js/auth-app.js` | `css/auth-app.css` |
| Frontend Profile | `assets/frontend-profile/vite/` | `src/js/profile-app.js` | `src/scss/profile-app.scss` | `js/profile-app.js` | `css/profile-app.css` |

### Main Frontend Project

The main Vite project (`assets/frontend/vite/`) handles the global site. It includes:

- `auth-header.js` -- Logout handler for the site header (loaded on all pages)
- `_auth.scss` -- Topbar auth link styles
- `_auth-page.scss` -- Auth page and profile page layout styles
- `auth/` modules -- Shared auth modules (config, store, api, validator, form-binder, profile)

This project outputs to `assets/frontend/` and is enqueued site-wide.

### Frontend Auth Project

The auth project (`assets/frontend-auth/vite/`) is a standalone bundle for the `login-register.php` page template. It contains:

- `auth-app.js` -- Entry point that instantiates `AuthPage` on DOMContentLoaded
- `auth-page.js` -- Full auth page controller (duplicated from main frontend)
- `auth/` modules -- Duplicated auth modules (config, store, api, validator, form-binder)
- `_auth-form.scss` -- Form component styles (inputs, labels, buttons, messages, validation rules, password toggles, code inputs, avatars)
- `_auth-page.scss` -- Page layout styles
- `_variables.scss` -- Shared SCSS variables

This project outputs to `assets/frontend-auth/` and is enqueued only on the login-register page.

### Frontend Profile Project

The profile project (`assets/frontend-profile/vite/`) is a standalone bundle for the `profile.php` page template. It contains:

- `profile-app.js` -- Entry point that instantiates `ProfilePage` on DOMContentLoaded
- `profile-page.js` -- Profile page controller with tab switching, avatar upload, save profile, change password, logout, user messages
- `auth/` modules -- Duplicated auth modules (config, store, api, validator, form-binder, profile)
- `_auth-form.scss` -- Form component styles (shared with auth project)
- `_profile-page.scss` -- Profile-specific layout styles
- `_variables.scss` -- Shared SCSS variables

This project outputs to `assets/frontend-profile/` and is enqueued only on the profile page.

### Build Commands

Each project is built independently from its own directory:

```bash
cd assets/frontend/vite && npx vite build
cd assets/frontend-auth/vite && npx vite build
cd assets/frontend-profile/vite && npx vite build
```

All three use `emptyOutDir: false` to avoid clearing sibling output files.

---

## User Meta Keys

| Meta Key | Values | Purpose |
|----------|--------|---------|
| `bs_account_active` | `'0'` or `'1'` | Account activation state. Set to `'0'` on registration, updated to `'1'` on activation. Checked on login. |
| `bs_avatar_id` | Attachment ID (integer) | WordPress media library attachment ID for the user's custom avatar. Set by `ProfileUploadAvatar`. Used by a `pre_get_avatar_data` filter to serve the local image. |

---

## WordPress Options

| Option Key | Format | Purpose |
|------------|--------|---------|
| `bs_custom_jwt_auth` | JSON string | JWT configuration: `secret`, `expiration_hours` |
| `bs_custom_email_config` | JSON string | SMTP configuration: `host`, `port`, `username`, `password`, `encryption`, `from_email`, `from_name` |

---

## Data Attributes (HTML)

### Auth Page (`login-register.php`)

| Attribute | Element | Purpose |
|-----------|---------|---------|
| `data-rest-url` | `.ct-auth-card` | REST API base URL (`/wp-json/ct-auth/v1/`) |
| `data-nonce` | `.ct-auth-card` | WordPress REST nonce (`wp_rest`) |
| `data-cache-version` | `.ct-auth-card` | Theme version for cache busting |
| `data-home-url` | `.ct-auth-card` | Language-aware home URL for post-login redirect |
| `data-ct-auth-tab` | Tab buttons | Tab name: `login` or `register` |
| `data-ct-auth-form` | Panel divs | Panel name: `login`, `register`, `forgot-password`, `activation-code`, `reset-code`, `reset-password` |
| `data-ct-auth-action` | Buttons/links | Action name: `login`, `register`, `forgot`, `show-forgot`, `show-login`, `show-register`, `back-to-login`, `verify-activation`, `verify-reset-code`, `reset-password`, `resend-reset-code`, `resend-activation-code` |
| `data-ct-auth-back-bar` | Back bar div | Marker for the back-to-login navigation bar |

### Profile Page (`profile.php`)

| Attribute | Element | Purpose |
|-----------|---------|---------|
| `data-rest-url` | `.ct-profile-card` | REST API base URL |
| `data-nonce` | `.ct-profile-card` | WordPress REST nonce |
| `data-cache-version` | `.ct-profile-card` | Theme version |
| `data-auth-url` | `.ct-profile-card` | Login page URL for unauthorized redirects |
| `data-ct-profile-tab` | Tab buttons | Tab name: `profile` or `messages` |
| `data-ct-profile-panel` | Panel divs | Panel name: `profile` or `messages` |
| `data-ct-auth-action` | Buttons | Action name: `save-profile`, `change-password`, `upload-avatar`, `logout` |
| `data-ct-password-section` | Section div | Marker for the change password section (scoped messages) |

### Validation Attributes (all forms)

| Attribute | Purpose |
|-----------|---------|
| `data-ct-validate-required` | Field must not be empty |
| `data-ct-validate-email` | Field must match email regex |
| `data-ct-validate-password` | Field triggers password rule checklist |
| `data-ct-validate-username` | Field triggers username rule checklist |
| `data-ct-validate-match="field_name"` | Field must match the named field's value |
| `data-ct-validate-code` | Field must match 6-digit code regex |
| `data-ct-validate-min="N"` | Field must have minimum N characters |
| `data-rule="rule_name"` | Rule element for visual feedback (pass/fail) |
| `data-rule-compare="field_name"` | For "different" rule: compare against named field |
