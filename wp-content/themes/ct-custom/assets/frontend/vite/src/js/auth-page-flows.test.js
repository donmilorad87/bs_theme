/**
 * Auth Page Flow Integration Tests
 *
 * Simulates complete user interaction sequences on the auth page:
 * fill inputs, trigger validation, submit forms, verify redirects/messages.
 *
 * @package BS_Custom
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

/* ------------------------------------------------------------------ */
/*  Global mocks (before any imports that use them)                    */
/* ------------------------------------------------------------------ */

vi.stubGlobal('fetch', vi.fn());

import AuthPage from './auth-page.js';

/* ------------------------------------------------------------------ */
/*  Shared test utilities                                              */
/* ------------------------------------------------------------------ */

/**
 * Configure fetch mock to return specific responses per endpoint.
 * Keys are substrings matched against the URL; first match wins.
 *
 * @param {Object} responseMap  { endpointSubstring: { status, body } }
 */
function mockFetchResponses(responseMap) {
    fetch.mockImplementation((url) => {
        const keys = Object.keys(responseMap);
        for (let i = 0; i < keys.length; i++) {
            if (url.indexOf(keys[i]) !== -1) {
                const entry = responseMap[keys[i]];
                return Promise.resolve({
                    status: entry.status || 200,
                    json: () => Promise.resolve(entry.body),
                });
            }
        }
        return Promise.resolve({
            status: 200,
            json: () => Promise.resolve({ success: true }),
        });
    });
}

/**
 * Fill an input by name and dispatch 'input' event to trigger validation.
 *
 * @param {HTMLElement} container Parent element containing the input.
 * @param {string}      name      Input name attribute.
 * @param {string}      value     Value to set.
 */
function fillInput(container, name, value) {
    const input = container.querySelector('input[name="' + name + '"]');
    if (!input) { return; }
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

/**
 * Read all message texts from a panel's messages container.
 *
 * @param {HTMLElement} panel The auth panel element.
 * @returns {string[]}
 */
function getMessages(panel) {
    const msgs = panel.querySelectorAll('.ct-auth-form__message');
    const result = [];
    for (let i = 0; i < msgs.length; i++) {
        result.push(msgs[i].textContent);
    }
    return result;
}

/**
 * Dispatch Enter keydown on a form element.
 *
 * @param {HTMLElement} form The form or container element.
 */
function pressEnter(form) {
    const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
    const firstInput = form.querySelector('input:not([type="hidden"])');
    if (firstInput) {
        firstInput.dispatchEvent(event);
    }
}

/**
 * Click an action button by its data-ct-auth-action value.
 *
 * @param {HTMLElement} card   The auth card element.
 * @param {string}      action Action name.
 */
function clickAction(card, action) {
    const btn = card.querySelector('[data-ct-auth-action="' + action + '"]');
    if (btn) { btn.click(); }
}

/**
 * Flush pending microtasks (resolved promises).
 * Uses Promise.resolve() loop instead of setTimeout so it works
 * with both real and fake timers.
 */
async function flushPromises() {
    for (let i = 0; i < 10; i++) {
        await Promise.resolve();
    }
}

/* ------------------------------------------------------------------ */
/*  Strong password that passes all 5 rules                            */
/* ------------------------------------------------------------------ */

const STRONG_PASS = 'Test1234!';

/* ------------------------------------------------------------------ */
/*  DOM fixture                                                        */
/* ------------------------------------------------------------------ */

/**
 * Full DOM fixture mirroring the actual PHP templates.
 * Includes all data-ct-validate-* attributes, validation checklists,
 * --disabled buttons, password wraps, and match hints.
 */
function getAuthCardHTML(homeUrl) {
    homeUrl = homeUrl || '/';
    return `
    <div class="ct-auth-card"
         data-rest-url="http://test.local/wp-json/ct/v1/"
         data-nonce="test-nonce-123"
         data-cache-version="1"
         data-home-url="${homeUrl}">

        <div class="ct-auth-card__tabs" style="display:block">
            <button data-ct-auth-tab="login" class="ct-auth-card__tab--active" aria-selected="true">Login</button>
            <button data-ct-auth-tab="register" aria-selected="false">Register</button>
        </div>
        <div data-ct-auth-back-bar style="display:none">
            <button data-ct-auth-action="back-to-login">Back</button>
        </div>

        <!-- LOGIN -->
        <div data-ct-auth-form="login" class="ct-auth-card__panel--active">
            <div class="ct-auth-form">
                <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>
                <div class="ct-auth-form__fields">
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input fs14" name="username_or_email"
                               data-ct-validate-required="true"
                               data-ct-validate-min="1">
                    </div>
                    <div class="ct-auth-form__field">
                        <div class="ct-auth-form__password-wrap">
                            <input type="password" class="ct-auth-form__input fs14" name="password"
                                   data-ct-validate-required="true"
                                   data-ct-validate-password="true">
                            <button type="button" class="ct-auth-form__password-toggle"></button>
                        </div>
                        <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                            <div class="ct-auth-form__rule df aic" data-rule="min-length">At least 8 characters</div>
                            <div class="ct-auth-form__rule df aic" data-rule="lowercase">One lowercase letter</div>
                            <div class="ct-auth-form__rule df aic" data-rule="uppercase">One uppercase letter</div>
                            <div class="ct-auth-form__rule df aic" data-rule="digit">One digit</div>
                            <div class="ct-auth-form__rule df aic" data-rule="special">One special character</div>
                        </div>
                    </div>
                </div>
                <button type="button" class="ct-auth-form__submit ct-auth-form__submit--disabled" data-ct-auth-action="login" disabled>Log In</button>
            </div>
        </div>

        <!-- REGISTER -->
        <div data-ct-auth-form="register">
            <div class="ct-auth-form">
                <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>
                <div class="ct-auth-form__fields">
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input fs14" name="username"
                               data-ct-validate-required="true"
                               data-ct-validate-username="true">
                        <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                            <div class="ct-auth-form__rule df aic" data-rule="username-min">At least 4 characters</div>
                            <div class="ct-auth-form__rule df aic" data-rule="username-chars">Only letters, numbers, -, _, .</div>
                            <div class="ct-auth-form__rule df aic" data-rule="username-special">Max 2 special characters</div>
                        </div>
                    </div>
                    <div class="ct-auth-form__field">
                        <input type="email" class="ct-auth-form__input fs14" name="email"
                               data-ct-validate-required="true"
                               data-ct-validate-email="true">
                    </div>
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input fs14" name="first_name"
                               data-ct-validate-required="true">
                    </div>
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input fs14" name="last_name"
                               data-ct-validate-required="true">
                    </div>
                    <div class="ct-auth-form__field">
                        <div class="ct-auth-form__password-wrap">
                            <input type="password" class="ct-auth-form__input fs14" name="password"
                                   data-ct-validate-required="true"
                                   data-ct-validate-password="true">
                            <button type="button" class="ct-auth-form__password-toggle"></button>
                        </div>
                        <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                            <div class="ct-auth-form__rule df aic" data-rule="min-length">At least 8 characters</div>
                            <div class="ct-auth-form__rule df aic" data-rule="lowercase">One lowercase letter</div>
                            <div class="ct-auth-form__rule df aic" data-rule="uppercase">One uppercase letter</div>
                            <div class="ct-auth-form__rule df aic" data-rule="digit">One digit</div>
                            <div class="ct-auth-form__rule df aic" data-rule="special">One special character</div>
                        </div>
                    </div>
                    <div class="ct-auth-form__field">
                        <div class="ct-auth-form__password-wrap">
                            <input type="password" class="ct-auth-form__input fs14" name="password_confirm"
                                   data-ct-validate-required="true"
                                   data-ct-validate-match="password">
                            <button type="button" class="ct-auth-form__password-toggle"></button>
                        </div>
                        <div class="ct-auth-form__match-hint ct-auth-form__match-hint--hidden" aria-live="polite">
                            <div class="ct-auth-form__rule ct-auth-form__match-hint-pass df aic">Passwords match</div>
                            <div class="ct-auth-form__rule ct-auth-form__match-hint-fail df aic">Passwords do not match</div>
                        </div>
                    </div>
                </div>
                <button type="button" class="ct-auth-form__submit ct-auth-form__submit--disabled" data-ct-auth-action="register" disabled>Create Account</button>
            </div>
        </div>

        <!-- FORGOT PASSWORD -->
        <div data-ct-auth-form="forgot-password">
            <div class="ct-auth-form">
                <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>
                <div class="ct-auth-form__fields">
                    <div class="ct-auth-form__field">
                        <input type="email" class="ct-auth-form__input fs14" name="email"
                               data-ct-validate-required="true"
                               data-ct-validate-email="true">
                    </div>
                </div>
                <button type="button" class="ct-auth-form__submit ct-auth-form__submit--disabled" data-ct-auth-action="forgot" disabled>Send Reset Code</button>
            </div>
        </div>

        <!-- ACTIVATION CODE -->
        <div data-ct-auth-form="activation-code">
            <div class="ct-auth-form">
                <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>
                <div class="ct-auth-form__fields">
                    <input type="hidden" name="email" value="">
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input ct-auth-form__code-input" name="code"
                               inputmode="numeric" maxlength="6"
                               data-ct-validate-required="true"
                               data-ct-validate-code="true">
                    </div>
                </div>
                <button type="button" class="ct-auth-form__submit" data-ct-auth-action="verify-activation">Verify Code</button>
                <a href="#" data-ct-auth-action="resend-activation-code">Resend code</a>
            </div>
        </div>

        <!-- RESET CODE -->
        <div data-ct-auth-form="reset-code">
            <div class="ct-auth-form">
                <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>
                <div class="ct-auth-form__fields">
                    <input type="hidden" name="email" value="">
                    <div class="ct-auth-form__field">
                        <input type="text" class="ct-auth-form__input ct-auth-form__code-input" name="code"
                               inputmode="numeric" maxlength="6"
                               data-ct-validate-required="true"
                               data-ct-validate-code="true">
                    </div>
                </div>
                <button type="button" class="ct-auth-form__submit" data-ct-auth-action="verify-reset-code">Verify Code</button>
                <a href="#" data-ct-auth-action="resend-reset-code">Request new code</a>
            </div>
        </div>

        <!-- RESET PASSWORD -->
        <div data-ct-auth-form="reset-password">
            <div class="ct-auth-form">
                <div class="ct-auth-form__messages" role="alert" aria-live="polite"></div>
                <div class="ct-auth-form__fields">
                    <input type="hidden" name="reset_token" value="">
                    <div class="ct-auth-form__field">
                        <div class="ct-auth-form__password-wrap">
                            <input type="password" class="ct-auth-form__input fs14" name="new_password"
                                   data-ct-validate-required="true"
                                   data-ct-validate-password="true">
                            <button type="button" class="ct-auth-form__password-toggle"></button>
                        </div>
                        <div class="ct-auth-form__validation ct-auth-form__validation--hidden" aria-live="polite">
                            <div class="ct-auth-form__rule df aic" data-rule="min-length">At least 8 characters</div>
                            <div class="ct-auth-form__rule df aic" data-rule="lowercase">One lowercase letter</div>
                            <div class="ct-auth-form__rule df aic" data-rule="uppercase">One uppercase letter</div>
                            <div class="ct-auth-form__rule df aic" data-rule="digit">One digit</div>
                            <div class="ct-auth-form__rule df aic" data-rule="special">One special character</div>
                        </div>
                    </div>
                    <div class="ct-auth-form__field">
                        <div class="ct-auth-form__password-wrap">
                            <input type="password" class="ct-auth-form__input fs14" name="new_password_confirm"
                                   data-ct-validate-required="true"
                                   data-ct-validate-match="new_password">
                            <button type="button" class="ct-auth-form__password-toggle"></button>
                        </div>
                        <div class="ct-auth-form__match-hint ct-auth-form__match-hint--hidden" aria-live="polite">
                            <div class="ct-auth-form__rule ct-auth-form__match-hint-pass df aic">Passwords match</div>
                            <div class="ct-auth-form__rule ct-auth-form__match-hint-fail df aic">Passwords do not match</div>
                        </div>
                    </div>
                </div>
                <button type="button" class="ct-auth-form__submit ct-auth-form__submit--disabled" data-ct-auth-action="reset-password" disabled>Reset Password</button>
            </div>
        </div>
    </div>`;
}

/* ------------------------------------------------------------------ */
/*  Setup / Teardown                                                   */
/* ------------------------------------------------------------------ */

let locationMock;

beforeEach(() => {
    fetch.mockReset();
    localStorage.clear();

    locationMock = {
        href: 'http://test.local/login-register/',
        search: '',
        hash: '',
        pathname: '/login-register/',
        reload: vi.fn(),
    };
    delete window.location;
    window.location = locationMock;
});

afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

/* ================================================================== */
/*  Group A: Login Flow                                                */
/* ================================================================== */

describe('Auth Page Flows — Login', () => {

    it('1. Login button starts disabled when inputs empty', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        const btn = document.querySelector('[data-ct-auth-action="login"]');

        expect(btn.disabled).toBe(true);
        expect(btn.classList.contains('ct-auth-form__submit--disabled')).toBe(true);
    });

    it('2. Login button enables when username and strong password filled', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');
        const btn = panel.querySelector('[data-ct-auth-action="login"]');

        fillInput(panel, 'username_or_email', 'admin');
        fillInput(panel, 'password', STRONG_PASS);

        expect(btn.disabled).toBe(false);
        expect(btn.classList.contains('ct-auth-form__submit--disabled')).toBe(false);
    });

    it('3. Login button stays disabled when password fails validation', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');
        const btn = panel.querySelector('[data-ct-auth-action="login"]');

        fillInput(panel, 'username_or_email', 'admin');
        fillInput(panel, 'password', 'test');

        expect(btn.disabled).toBe(true);
    });

    it('4. Enter keypress submits login when button enabled', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            login: { status: 200, body: { success: true, data: { token: 'jwt-tok-123', nonce: 'n2' } } },
        });
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');

        fillInput(panel, 'username_or_email', 'admin');
        fillInput(panel, 'password', STRONG_PASS);
        pressEnter(panel.querySelector('.ct-auth-form'));

        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);
        const callUrl = fetch.mock.calls[0][0];
        expect(callUrl).toContain('login');
    });

    it('5. Enter keypress does NOT submit when button disabled', async () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');

        fillInput(panel, 'username_or_email', '');
        fillInput(panel, 'password', '');
        pressEnter(panel.querySelector('.ct-auth-form'));

        await flushPromises();

        expect(fetch).not.toHaveBeenCalled();
    });

    it('6. Successful login stores token + redirects to / (default)', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            login: { status: 200, body: { success: true, data: { token: 'jwt-abc', nonce: 'n2' } } },
        });
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');

        fillInput(panel, 'username_or_email', 'admin');
        fillInput(panel, 'password', STRONG_PASS);
        clickAction(document.querySelector('.ct-auth-card'), 'login');

        await flushPromises();

        expect(localStorage.getItem('ct_auth_token')).toBe('jwt-abc');
        expect(locationMock.href).toBe('/');
    });

    it('7. Successful login redirects to /sr/ when data-home-url="/sr/"', async () => {
        document.body.innerHTML = getAuthCardHTML('/sr/');
        mockFetchResponses({
            login: { status: 200, body: { success: true, data: { token: 'jwt-sr', nonce: 'n2' } } },
        });
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');

        fillInput(panel, 'username_or_email', 'user');
        fillInput(panel, 'password', STRONG_PASS);
        clickAction(document.querySelector('.ct-auth-card'), 'login');

        await flushPromises();

        expect(locationMock.href).toBe('/sr/');
    });

    it('8. Successful login respects ?redirect_to over home URL', async () => {
        document.body.innerHTML = getAuthCardHTML();
        locationMock.search = '?redirect_to=/dashboard/';
        mockFetchResponses({
            login: { status: 200, body: { success: true, data: { token: 'jwt-redir', nonce: 'n2' } } },
        });
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');

        fillInput(panel, 'username_or_email', 'admin');
        fillInput(panel, 'password', STRONG_PASS);
        clickAction(document.querySelector('.ct-auth-card'), 'login');

        await flushPromises();

        expect(locationMock.href).toBe('/dashboard/');
    });

    it('9. Failed login shows error message on panel', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            login: { status: 401, body: { success: false, message: 'Invalid credentials.' } },
        });
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');

        fillInput(panel, 'username_or_email', 'admin');
        fillInput(panel, 'password', STRONG_PASS);
        clickAction(document.querySelector('.ct-auth-card'), 'login');

        await flushPromises();

        const msgs = getMessages(panel);
        expect(msgs.length).toBe(1);
        expect(msgs[0]).toBe('Invalid credentials.');
    });

    it('10. Inactive account response switches to activation-code with email injected', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            login: {
                status: 403,
                body: {
                    success: false,
                    message: 'Account not activated.',
                    data: { requires_activation: true, email: 'user@test.com' },
                },
            },
        });
        const page = new AuthPage();
        const panel = document.querySelector('[data-ct-auth-form="login"]');

        fillInput(panel, 'username_or_email', 'user');
        fillInput(panel, 'password', STRONG_PASS);
        clickAction(document.querySelector('.ct-auth-card'), 'login');

        await flushPromises();

        const activationPanel = document.querySelector('[data-ct-auth-form="activation-code"]');
        expect(activationPanel.classList.contains('ct-auth-card__panel--active')).toBe(true);

        const emailHidden = activationPanel.querySelector('input[name="email"]');
        expect(emailHidden.value).toBe('user@test.com');
    });
});

/* ================================================================== */
/*  Group B: Register Flow                                             */
/* ================================================================== */

describe('Auth Page Flows — Register', () => {

    function switchToRegister(page) {
        page._showForm('register');
    }

    function fillAllRegisterFields(panel) {
        fillInput(panel, 'username', 'testuser');
        fillInput(panel, 'email', 'test@example.com');
        fillInput(panel, 'first_name', 'John');
        fillInput(panel, 'last_name', 'Doe');
        fillInput(panel, 'password', STRONG_PASS);
        fillInput(panel, 'password_confirm', STRONG_PASS);
    }

    it('11. Register button starts disabled', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        switchToRegister(page);
        const panel = document.querySelector('[data-ct-auth-form="register"]');
        const btn = panel.querySelector('[data-ct-auth-action="register"]');

        expect(btn.disabled).toBe(true);
    });

    it('12. Register button enables when all 6 fields valid', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        switchToRegister(page);
        const panel = document.querySelector('[data-ct-auth-form="register"]');
        const btn = panel.querySelector('[data-ct-auth-action="register"]');

        fillAllRegisterFields(panel);

        expect(btn.disabled).toBe(false);
    });

    it('13. Register button stays disabled with invalid username (3 special chars)', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        switchToRegister(page);
        const panel = document.querySelector('[data-ct-auth-form="register"]');
        const btn = panel.querySelector('[data-ct-auth-action="register"]');

        fillInput(panel, 'username', 'a-b.c_d');
        fillInput(panel, 'email', 'test@example.com');
        fillInput(panel, 'first_name', 'John');
        fillInput(panel, 'last_name', 'Doe');
        fillInput(panel, 'password', STRONG_PASS);
        fillInput(panel, 'password_confirm', STRONG_PASS);

        expect(btn.disabled).toBe(true);
    });

    it('14. Register button stays disabled with invalid email', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        switchToRegister(page);
        const panel = document.querySelector('[data-ct-auth-form="register"]');
        const btn = panel.querySelector('[data-ct-auth-action="register"]');

        fillInput(panel, 'username', 'testuser');
        fillInput(panel, 'email', 'not-an-email');
        fillInput(panel, 'first_name', 'John');
        fillInput(panel, 'last_name', 'Doe');
        fillInput(panel, 'password', STRONG_PASS);
        fillInput(panel, 'password_confirm', STRONG_PASS);

        expect(btn.disabled).toBe(true);
    });

    it('15. Enter keypress submits register when valid, prevented when not', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            register: { status: 200, body: { success: true, data: { email: 'test@example.com' } } },
        });
        const page = new AuthPage();
        switchToRegister(page);
        const panel = document.querySelector('[data-ct-auth-form="register"]');
        const form = panel.querySelector('.ct-auth-form');

        /* Press Enter with empty fields — should NOT submit */
        pressEnter(form);
        await flushPromises();
        expect(fetch).not.toHaveBeenCalled();

        /* Fill all fields and press Enter — should submit */
        fillAllRegisterFields(panel);
        pressEnter(form);
        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch.mock.calls[0][0]).toContain('register');
    });

    it('16. Successful register switches to activation-code with email preserved', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            register: { status: 200, body: { success: true, data: { email: 'new@user.com' } } },
        });
        const page = new AuthPage();
        switchToRegister(page);
        const panel = document.querySelector('[data-ct-auth-form="register"]');

        fillAllRegisterFields(panel);
        clickAction(document.querySelector('.ct-auth-card'), 'register');

        await flushPromises();

        const activationPanel = document.querySelector('[data-ct-auth-form="activation-code"]');
        expect(activationPanel.classList.contains('ct-auth-card__panel--active')).toBe(true);

        const emailHidden = activationPanel.querySelector('input[name="email"]');
        expect(emailHidden.value).toBe('new@user.com');
    });

    it('17. Failed register shows error message', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            register: { status: 400, body: { success: false, message: 'Username already taken.' } },
        });
        const page = new AuthPage();
        switchToRegister(page);
        const panel = document.querySelector('[data-ct-auth-form="register"]');

        fillAllRegisterFields(panel);
        clickAction(document.querySelector('.ct-auth-card'), 'register');

        await flushPromises();

        const msgs = getMessages(panel);
        expect(msgs.length).toBe(1);
        expect(msgs[0]).toBe('Username already taken.');
    });

    it('18. Network error shows generic error message', async () => {
        document.body.innerHTML = getAuthCardHTML();
        fetch.mockImplementation(() => Promise.reject(new Error('Network failure')));

        const page = new AuthPage();
        switchToRegister(page);
        const panel = document.querySelector('[data-ct-auth-form="register"]');

        fillAllRegisterFields(panel);
        clickAction(document.querySelector('.ct-auth-card'), 'register');

        await flushPromises();

        const msgs = getMessages(panel);
        expect(msgs.length).toBe(1);
        expect(msgs[0]).toContain('error occurred');
    });
});

/* ================================================================== */
/*  Group C: Activation Code Flow                                      */
/* ================================================================== */

describe('Auth Page Flows — Activation Code', () => {

    function setupActivationPanel(page, email) {
        page._flowData.email = email || 'user@test.com';
        page._showForm('activation-code');
    }

    it('19. Valid code shows success + switches to login after 1500ms', async () => {
        vi.useFakeTimers();
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'verify-activation': { status: 200, body: { success: true, message: 'Account activated!' } },
        });
        const page = new AuthPage();
        setupActivationPanel(page, 'user@test.com');
        const panel = document.querySelector('[data-ct-auth-form="activation-code"]');

        fillInput(panel, 'code', '123456');
        clickAction(document.querySelector('.ct-auth-card'), 'verify-activation');

        await flushPromises();

        const msgs = getMessages(panel);
        expect(msgs[0]).toContain('activated');

        vi.advanceTimersByTime(1500);

        const loginPanel = document.querySelector('[data-ct-auth-form="login"]');
        expect(loginPanel.classList.contains('ct-auth-card__panel--active')).toBe(true);

        vi.useRealTimers();
    });

    it('20. Invalid code shows error message', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'verify-activation': { status: 400, body: { success: false, message: 'Invalid code.' } },
        });
        const page = new AuthPage();
        setupActivationPanel(page, 'user@test.com');
        const panel = document.querySelector('[data-ct-auth-form="activation-code"]');

        fillInput(panel, 'code', '000000');
        clickAction(document.querySelector('.ct-auth-card'), 'verify-activation');

        await flushPromises();

        const msgs = getMessages(panel);
        expect(msgs[0]).toBe('Invalid code.');
    });

    it('21. Resend activation sends API request, shows success, clears code input', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'resend-activation': { status: 200, body: { success: true } },
        });
        const page = new AuthPage();
        setupActivationPanel(page, 'user@test.com');
        const panel = document.querySelector('[data-ct-auth-form="activation-code"]');

        fillInput(panel, 'code', '111111');
        clickAction(document.querySelector('.ct-auth-card'), 'resend-activation-code');

        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch.mock.calls[0][0]).toContain('resend-activation');

        const msgs = getMessages(panel);
        expect(msgs[0]).toContain('new activation code');

        const codeInput = panel.querySelector('input[name="code"]');
        expect(codeInput.value).toBe('');
    });
});

/* ================================================================== */
/*  Group D: Forgot Password Flow                                      */
/* ================================================================== */

describe('Auth Page Flows — Forgot Password', () => {

    function switchToForgot(page) {
        page._showForm('forgot-password');
    }

    it('22. Forgot button starts disabled, enables with valid email', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        switchToForgot(page);
        const panel = document.querySelector('[data-ct-auth-form="forgot-password"]');
        const btn = panel.querySelector('[data-ct-auth-action="forgot"]');

        expect(btn.disabled).toBe(true);

        fillInput(panel, 'email', 'user@example.com');

        expect(btn.disabled).toBe(false);
    });

    it('23. Submit always switches to reset-code form (anti-enumeration)', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'forgot-password': { status: 200, body: { success: true } },
        });
        const page = new AuthPage();
        switchToForgot(page);
        const panel = document.querySelector('[data-ct-auth-form="forgot-password"]');

        fillInput(panel, 'email', 'user@example.com');
        clickAction(document.querySelector('.ct-auth-card'), 'forgot');

        await flushPromises();

        const resetCodePanel = document.querySelector('[data-ct-auth-form="reset-code"]');
        expect(resetCodePanel.classList.contains('ct-auth-card__panel--active')).toBe(true);

        const emailHidden = resetCodePanel.querySelector('input[name="email"]');
        expect(emailHidden.value).toBe('user@example.com');
    });

    it('24. Enter keypress submits when valid, prevented when empty', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'forgot-password': { status: 200, body: { success: true } },
        });
        const page = new AuthPage();
        switchToForgot(page);
        const panel = document.querySelector('[data-ct-auth-form="forgot-password"]');
        const form = panel.querySelector('.ct-auth-form');

        /* Empty — should not submit */
        pressEnter(form);
        await flushPromises();
        expect(fetch).not.toHaveBeenCalled();

        /* Valid email — should submit */
        fillInput(panel, 'email', 'user@example.com');
        pressEnter(form);
        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch.mock.calls[0][0]).toContain('forgot-password');
    });

    it('25. Valid reset code stores reset_token + switches to reset-password form', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'forgot-password': { status: 200, body: { success: true } },
            'verify-reset-code': { status: 200, body: { success: true, data: { reset_token: 'rst-token-xyz' } } },
        });
        const page = new AuthPage();

        /* Step 1: Submit forgot password */
        switchToForgot(page);
        const forgotPanel = document.querySelector('[data-ct-auth-form="forgot-password"]');
        fillInput(forgotPanel, 'email', 'user@example.com');
        clickAction(document.querySelector('.ct-auth-card'), 'forgot');
        await flushPromises();

        /* Now on reset-code panel */
        const resetCodePanel = document.querySelector('[data-ct-auth-form="reset-code"]');
        expect(resetCodePanel.classList.contains('ct-auth-card__panel--active')).toBe(true);

        /* Step 2: Enter the code */
        fillInput(resetCodePanel, 'code', '654321');
        clickAction(document.querySelector('.ct-auth-card'), 'verify-reset-code');
        await flushPromises();

        /* Should switch to reset-password */
        const resetPassPanel = document.querySelector('[data-ct-auth-form="reset-password"]');
        expect(resetPassPanel.classList.contains('ct-auth-card__panel--active')).toBe(true);

        const tokenHidden = resetPassPanel.querySelector('input[name="reset_token"]');
        expect(tokenHidden.value).toBe('rst-token-xyz');
    });

    it('26. Resend reset code sends forgot-password request, shows success', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'forgot-password': { status: 200, body: { success: true } },
        });
        const page = new AuthPage();
        page._flowData.email = 'user@example.com';
        page._showForm('reset-code');
        const panel = document.querySelector('[data-ct-auth-form="reset-code"]');

        fillInput(panel, 'code', '111111');
        clickAction(document.querySelector('.ct-auth-card'), 'resend-reset-code');
        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch.mock.calls[0][0]).toContain('forgot-password');

        const msgs = getMessages(panel);
        expect(msgs[0]).toContain('new code');

        const codeInput = panel.querySelector('input[name="code"]');
        expect(codeInput.value).toBe('');
    });
});

/* ================================================================== */
/*  Group E: Reset Password Flow                                       */
/* ================================================================== */

describe('Auth Page Flows — Reset Password', () => {

    function setupResetPasswordPanel(page) {
        page._flowData.reset_token = 'test-reset-token-abc';
        page._showForm('reset-password');
    }

    it('27. Reset button starts disabled, enables with valid matching passwords', () => {
        document.body.innerHTML = getAuthCardHTML();
        const page = new AuthPage();
        setupResetPasswordPanel(page);
        const panel = document.querySelector('[data-ct-auth-form="reset-password"]');
        const btn = panel.querySelector('[data-ct-auth-action="reset-password"]');

        expect(btn.disabled).toBe(true);

        fillInput(panel, 'new_password', STRONG_PASS);
        fillInput(panel, 'new_password_confirm', STRONG_PASS);

        expect(btn.disabled).toBe(false);
    });

    it('28. Valid submit shows success + redirects after 1500ms', async () => {
        vi.useFakeTimers();
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'reset-password': { status: 200, body: { success: true, message: 'Password reset!' } },
        });
        const page = new AuthPage();
        setupResetPasswordPanel(page);
        const panel = document.querySelector('[data-ct-auth-form="reset-password"]');

        fillInput(panel, 'new_password', STRONG_PASS);
        fillInput(panel, 'new_password_confirm', STRONG_PASS);
        clickAction(document.querySelector('.ct-auth-card'), 'reset-password');

        await flushPromises();

        const msgs = getMessages(panel);
        expect(msgs[0]).toContain('Password reset');

        vi.advanceTimersByTime(1500);

        expect(locationMock.href).toBe('/login-register/');

        vi.useRealTimers();
    });

    it('29. API failure shows error message', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'reset-password': { status: 400, body: { success: false, message: 'Same as current password.' } },
        });
        const page = new AuthPage();
        setupResetPasswordPanel(page);
        const panel = document.querySelector('[data-ct-auth-form="reset-password"]');

        fillInput(panel, 'new_password', STRONG_PASS);
        fillInput(panel, 'new_password_confirm', STRONG_PASS);
        clickAction(document.querySelector('.ct-auth-card'), 'reset-password');

        await flushPromises();

        const msgs = getMessages(panel);
        expect(msgs[0]).toBe('Same as current password.');
    });

    it('30. Enter keypress submits when enabled', async () => {
        document.body.innerHTML = getAuthCardHTML();
        mockFetchResponses({
            'reset-password': { status: 200, body: { success: true, message: 'Password reset!' } },
        });
        const page = new AuthPage();
        setupResetPasswordPanel(page);
        const panel = document.querySelector('[data-ct-auth-form="reset-password"]');
        const form = panel.querySelector('.ct-auth-form');

        /* Disabled — should NOT submit */
        pressEnter(form);
        await flushPromises();
        expect(fetch).not.toHaveBeenCalled();

        /* Valid — should submit */
        fillInput(panel, 'new_password', STRONG_PASS);
        fillInput(panel, 'new_password_confirm', STRONG_PASS);
        pressEnter(form);
        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch.mock.calls[0][0]).toContain('reset-password');
    });
});
