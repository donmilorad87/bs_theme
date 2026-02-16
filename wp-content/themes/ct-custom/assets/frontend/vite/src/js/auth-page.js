/**
 * AuthPage - Handles the dedicated auth page (login-register.php template).
 *
 * State machine with explicit states: LOGIN, REGISTER, FORGOT,
 * ACTIVATION_CODE, RESET_CODE, RESET_PASSWORD.
 *
 * @package BS_Custom
 */

import { MAX_FIELDS, AUTH_FORMS, TAB_FORMS, FLOW_FORMS } from './auth/auth-config.js';
import AuthStore from './auth/auth-store.js';
import AuthApi from './auth/auth-api.js';
import AuthFormBinder from './auth/auth-form-binder.js';

function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

export default class AuthPage {
    constructor() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof window !== 'undefined', 'window must exist');

        this._card = document.querySelector('.ct-auth-card');

        if (!this._card) {
            return;
        }

        this._restUrl = this._card.getAttribute('data-rest-url') || '';
        this._nonce = this._card.getAttribute('data-nonce') || '';
        this._cacheVersion = this._card.getAttribute('data-cache-version') || '0';
        this._homeUrl = this._card.getAttribute('data-home-url') || '/';

        assert(typeof this._restUrl === 'string', 'restUrl must be a string');
        assert(typeof this._nonce === 'string', 'nonce must be a string');

        this._flowData = {};
        this._overlay = null;
        this._initSubSystems();
        this._bindAllForms();
        this._bindActions();
        this._handleHashNavigation();
    }

    _initSubSystems() {
        this._store = new AuthStore(this._cacheVersion);
        this._api = new AuthApi(this._restUrl, this._nonce, this._store);
        this._binder = new AuthFormBinder();

        this._api.setUnauthorizedHandler(() => {
            this._flowData = {};
            this._showForm('login');
        });
    }

    /**
     * Bind validation to all 6 panels at init (server-rendered forms).
     */
    _bindAllForms() {
        assert(this._card instanceof HTMLElement, 'card must be HTMLElement');

        const panels = this._card.querySelectorAll('[data-ct-auth-form]');
        const max = AUTH_FORMS.length;

        for (let i = 0; i < panels.length && i < max; i++) {
            this._binder.bind(panels[i]);
        }
    }

    _bindActions() {
        assert(typeof this._card.addEventListener === 'function', 'addEventListener must exist');
        assert(this._card !== null, 'card must exist');

        this._card.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-ct-auth-action]');
            if (!trigger) {
                return;
            }

            e.preventDefault();
            const action = trigger.getAttribute('data-ct-auth-action');
            this._handleAction(action);
        });

        /* Tab clicks */
        this._card.addEventListener('click', (e) => {
            const tab = e.target.closest('[data-ct-auth-tab]');
            if (!tab) {
                return;
            }

            e.preventDefault();
            const tabName = tab.getAttribute('data-ct-auth-tab');
            this._showForm(tabName);
        });
    }

    _handleHashNavigation() {
        if (window.location.hash === '#register') {
            this._showForm('register');
        }
    }

    _handleAction(action) {
        assert(typeof action === 'string', 'action must be a string');
        assert(action.length > 0, 'action must not be empty');

        switch (action) {
            case 'show-forgot':
                this._showForm('forgot-password');
                break;
            case 'show-login':
                this._showForm('login');
                break;
            case 'show-register':
                this._showForm('register');
                break;
            case 'back-to-login':
                this._flowData = {};
                this._showForm('login');
                break;
            case 'login':
                this._submitLogin();
                break;
            case 'register':
                this._submitRegister();
                break;
            case 'forgot':
                this._submitForgotPassword();
                break;
            case 'verify-activation':
                this._submitVerifyActivation();
                break;
            case 'verify-reset-code':
                this._submitVerifyResetCode();
                break;
            case 'reset-password':
                this._submitResetPassword();
                break;
            case 'resend-reset-code':
                this._resendResetCode();
                break;
            case 'resend-activation-code':
                this._resendActivationCode();
                break;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Panel switching                                                    */
    /* ------------------------------------------------------------------ */

    _showForm(name) {
        assert(typeof name === 'string', 'name must be a string');

        const panels = this._card.querySelectorAll('[data-ct-auth-form]');
        const max = AUTH_FORMS.length;

        for (let i = 0; i < panels.length && i < max; i++) {
            const isTarget = panels[i].getAttribute('data-ct-auth-form') === name;
            panels[i].classList.toggle('ct-auth-card__panel--active', isTarget);
        }

        const isTabForm = this._isTabForm(name);
        const tabs = this._card.querySelector('.ct-auth-card__tabs');
        const backBar = this._card.querySelector('[data-ct-auth-back-bar]');

        if (tabs) {
            tabs.style.display = isTabForm ? '' : 'none';
        }
        if (backBar) {
            backBar.style.display = isTabForm ? 'none' : '';
        }

        /* Update active tab */
        if (isTabForm) {
            const tabBtns = this._card.querySelectorAll('[data-ct-auth-tab]');
            const maxTabs = 5;
            for (let i = 0; i < tabBtns.length && i < maxTabs; i++) {
                const isActive = tabBtns[i].getAttribute('data-ct-auth-tab') === name;
                tabBtns[i].classList.toggle('ct-auth-card__tab--active', isActive);
                tabBtns[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
            }
        }

        /* Inject flow data into hidden fields */
        this._injectFlowData(name);

        /* Clear messages and focus first input */
        const activePanel = this._getActivePanel();
        if (activePanel) {
            this._clearMessages(activePanel);
            const firstInput = activePanel.querySelector('.ct-auth-form__input:not([readonly])');
            if (firstInput) {
                firstInput.focus();
            }
        }
    }

    _getActivePanel() {
        return this._card.querySelector('.ct-auth-card__panel--active');
    }

    _isTabForm(name) {
        for (let i = 0; i < TAB_FORMS.length; i++) {
            if (TAB_FORMS[i] === name) {
                return true;
            }
        }
        return false;
    }

    _injectFlowData(formName) {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        if (formName === 'activation-code' || formName === 'reset-code') {
            const emailField = panel.querySelector('input[name="email"]');
            if (emailField && this._flowData.email) {
                emailField.value = this._flowData.email;
            }
        }

        if (formName === 'reset-password') {
            const tokenField = panel.querySelector('input[name="reset_token"]');
            if (tokenField && this._flowData.reset_token) {
                tokenField.value = this._flowData.reset_token;
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Form submissions                                                   */
    /* ------------------------------------------------------------------ */

    _submitLogin() {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        const usernameInput = panel.querySelector('input[name="username_or_email"]');
        const passwordInput = panel.querySelector('input[name="password"]');

        if (!usernameInput || !passwordInput) { return; }

        const username = usernameInput.value.trim();
        const password = passwordInput.value;

        if (!username || !password) {
            this._showMessage(panel, 'error', 'Please fill in all fields.');
            return;
        }

        this._setLoading(panel, true);
        this._clearMessages(panel);
        this._blockPage();

        this._api.post('login', {
            username_or_email: username,
            password: password,
        })
            .then((data) => {
                if (data.success && data.data) {
                    if (data.data.token) {
                        this._store.setToken(data.data.token);
                    }
                    if (data.data.nonce) {
                        this._nonce = data.data.nonce;
                        this._api.setNonce(data.data.nonce);
                    }
                    this._redirectAfterLogin();
                } else {
                    this._setLoading(panel, false);
                    this._unblockPage();
                    if (data.data && data.data.requires_activation) {
                        this._flowData.email = data.data.email || '';
                        this._showForm('activation-code');
                    } else {
                        this._showMessage(panel, 'error', data.message || 'Login failed.');
                    }
                }
            })
            .catch(() => {
                this._setLoading(panel, false);
                this._unblockPage();
                this._showMessage(panel, 'error', 'An error occurred. Please try again.');
            });
    }

    _submitRegister() {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        const fields = {
            username: panel.querySelector('input[name="username"]'),
            email: panel.querySelector('input[name="email"]'),
            first_name: panel.querySelector('input[name="first_name"]'),
            last_name: panel.querySelector('input[name="last_name"]'),
            password: panel.querySelector('input[name="password"]'),
            password_confirm: panel.querySelector('input[name="password_confirm"]'),
        };

        if (!fields.username || !fields.email) { return; }

        const values = {};
        const keys = Object.keys(fields);
        for (let i = 0; i < keys.length && i < MAX_FIELDS; i++) {
            if (fields[keys[i]]) {
                values[keys[i]] = fields[keys[i]].value.trim();
            }
        }

        if (!values.username || !values.email || !values.first_name ||
            !values.last_name || !values.password || !values.password_confirm) {
            this._showMessage(panel, 'error', 'Please fill in all fields.');
            return;
        }

        if (values.password !== values.password_confirm) {
            this._showMessage(panel, 'error', 'Passwords do not match.');
            return;
        }

        if (values.password.length < 8) {
            this._showMessage(panel, 'error', 'Password must be at least 8 characters.');
            return;
        }

        this._setLoading(panel, true);
        this._clearMessages(panel);
        this._blockPage();

        this._api.post('register', values)
            .then((data) => {
                this._setLoading(panel, false);
                this._unblockPage();
                if (data.success) {
                    const email = (data.data && data.data.email) ? data.data.email : values.email;
                    this._flowData.email = email;
                    this._showForm('activation-code');
                } else {
                    this._showMessage(panel, 'error', data.message || 'Registration failed.');
                }
            })
            .catch(() => {
                this._setLoading(panel, false);
                this._unblockPage();
                this._showMessage(panel, 'error', 'An error occurred. Please try again.');
            });
    }

    _submitForgotPassword() {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        const emailInput = panel.querySelector('input[name="email"]');
        if (!emailInput) { return; }

        const email = emailInput.value.trim();

        if (!email) {
            this._showMessage(panel, 'error', 'Please enter your email address.');
            return;
        }

        this._setLoading(panel, true);
        this._clearMessages(panel);
        this._blockPage();

        this._api.post('forgot-password', { email: email })
            .then(() => {
                this._setLoading(panel, false);
                this._unblockPage();
                /* Always switch to reset-code to prevent email enumeration */
                this._flowData.email = email;
                this._showForm('reset-code');
            })
            .catch(() => {
                this._setLoading(panel, false);
                this._unblockPage();
                this._showMessage(panel, 'error', 'An error occurred. Please try again.');
            });
    }

    _resendResetCode() {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        const email = this._flowData.email || '';

        if (!email) {
            this._showMessage(panel, 'error', 'Session expired. Please start over.');
            return;
        }

        this._setLoading(panel, true);
        this._clearMessages(panel);

        this._api.post('forgot-password', { email: email })
            .then(() => {
                this._setLoading(panel, false);
                this._showMessage(panel, 'success', 'A new code has been sent to your email.');

                /* Clear the code input for the new attempt */
                const codeInput = panel.querySelector('input[name="code"]');
                if (codeInput) {
                    codeInput.value = '';
                    codeInput.focus();
                }
            })
            .catch(() => {
                this._setLoading(panel, false);
                this._showMessage(panel, 'error', 'An error occurred. Please try again.');
            });
    }

    _resendActivationCode() {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        const email = this._flowData.email || '';

        if (!email) {
            this._showMessage(panel, 'error', 'Session expired. Please start over.');
            return;
        }

        this._setLoading(panel, true);
        this._clearMessages(panel);

        this._api.post('resend-activation', { email: email })
            .then(() => {
                this._setLoading(panel, false);
                this._showMessage(panel, 'success', 'A new activation code has been sent to your email.');

                /* Clear the code input for the new attempt */
                const codeInput = panel.querySelector('input[name="code"]');
                if (codeInput) {
                    codeInput.value = '';
                    codeInput.focus();
                }
            })
            .catch(() => {
                this._setLoading(panel, false);
                this._showMessage(panel, 'error', 'An error occurred. Please try again.');
            });
    }

    _submitVerifyActivation() {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        const codeInput = panel.querySelector('input[name="code"]');
        const emailInput = panel.querySelector('input[name="email"]');

        if (!codeInput || !emailInput) { return; }

        const code = codeInput.value.trim();
        const email = emailInput.value.trim();

        if (!code || !email) {
            this._showMessage(panel, 'error', 'Please enter the 6-digit code.');
            return;
        }

        this._setLoading(panel, true);
        this._clearMessages(panel);

        this._api.post('verify-activation', { email: email, code: code })
            .then((data) => {
                this._setLoading(panel, false);
                if (data.success) {
                    this._showMessage(panel, 'success', data.message || 'Account activated! You can now log in.');
                    this._flowData = {};
                    setTimeout(() => {
                        this._showForm('login');
                    }, 1500);
                } else {
                    this._showMessage(panel, 'error', data.message || 'Invalid code.');
                }
            })
            .catch(() => {
                this._setLoading(panel, false);
                this._showMessage(panel, 'error', 'An error occurred. Please try again.');
            });
    }

    _submitVerifyResetCode() {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        const codeInput = panel.querySelector('input[name="code"]');
        const emailInput = panel.querySelector('input[name="email"]');

        if (!codeInput || !emailInput) { return; }

        const code = codeInput.value.trim();
        const email = emailInput.value.trim();

        if (!code || !email) {
            this._showMessage(panel, 'error', 'Please enter the 6-digit code.');
            return;
        }

        this._setLoading(panel, true);
        this._clearMessages(panel);

        this._api.post('verify-reset-code', { email: email, code: code })
            .then((data) => {
                this._setLoading(panel, false);
                if (data.success && data.data && data.data.reset_token) {
                    this._flowData.reset_token = data.data.reset_token;
                    this._showForm('reset-password');
                } else {
                    this._showMessage(panel, 'error', data.message || 'Invalid code.');
                }
            })
            .catch(() => {
                this._setLoading(panel, false);
                this._showMessage(panel, 'error', 'An error occurred. Please try again.');
            });
    }

    _submitResetPassword() {
        const panel = this._getActivePanel();
        if (!panel) { return; }

        const newPassInput = panel.querySelector('input[name="new_password"]');
        const confirmInput = panel.querySelector('input[name="new_password_confirm"]');
        const tokenInput = panel.querySelector('input[name="reset_token"]');

        if (!newPassInput || !confirmInput || !tokenInput) { return; }

        const newPassword = newPassInput.value;
        const confirmPassword = confirmInput.value;
        const resetToken = tokenInput.value;

        if (!newPassword || !confirmPassword) {
            this._showMessage(panel, 'error', 'Please fill in all password fields.');
            return;
        }

        if (newPassword !== confirmPassword) {
            this._showMessage(panel, 'error', 'Passwords do not match.');
            return;
        }

        if (newPassword.length < 8) {
            this._showMessage(panel, 'error', 'Password must be at least 8 characters.');
            return;
        }

        if (!resetToken) {
            this._showMessage(panel, 'error', 'Reset session expired. Please start over.');
            return;
        }

        this._setLoading(panel, true);
        this._clearMessages(panel);

        this._api.post('reset-password', {
            reset_token: resetToken,
            new_password: newPassword,
            new_password_confirm: confirmPassword,
        })
            .then((data) => {
                this._setLoading(panel, false);
                if (data.success) {
                    this._showMessage(panel, 'success', data.message || 'Password reset! You can now log in.');
                    this._flowData = {};
                    this._blockPage();
                    setTimeout(() => {
                        window.location.href = window.location.pathname;
                    }, 1500);
                } else {
                    this._showMessage(panel, 'error', data.message || 'Password reset failed.');
                }
            })
            .catch(() => {
                this._setLoading(panel, false);
                this._showMessage(panel, 'error', 'An error occurred. Please try again.');
            });
    }

    /* ------------------------------------------------------------------ */
    /*  Navigation                                                         */
    /* ------------------------------------------------------------------ */

    _redirectAfterLogin() {
        assert(typeof window.location !== 'undefined', 'window.location must exist');

        const params = new URLSearchParams(window.location.search);
        const redirectTo = params.get('redirect_to');

        if (redirectTo) {
            window.location.href = redirectTo;
        } else {
            window.location.href = this._homeUrl;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  UI helpers                                                         */
    /* ------------------------------------------------------------------ */

    _showMessage(panel, type, text) {
        if (!panel || !(panel instanceof HTMLElement)) { return; }

        const container = panel.querySelector('.ct-auth-form__messages');
        if (!container) { return; }

        container.innerHTML = '';

        const msg = document.createElement('div');
        msg.className = 'ct-auth-form__message fs14 ct-auth-form__message--' + type;
        msg.textContent = text;
        container.appendChild(msg);
    }

    _clearMessages(panel) {
        if (!panel) { return; }

        const container = panel.querySelector('.ct-auth-form__messages');
        if (container) {
            container.innerHTML = '';
        }
    }

    _setLoading(panel, loading) {
        if (!panel || !(panel instanceof HTMLElement)) { return; }

        const btn = panel.querySelector('.ct-auth-form__submit');
        if (!btn) { return; }

        btn.disabled = loading;

        if (loading) {
            btn.classList.add('ct-auth-form__submit--loading');
        } else {
            btn.classList.remove('ct-auth-form__submit--loading');
        }
    }

    _blockPage() {
        if (this._overlay) { return; }

        const overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:99999;cursor:not-allowed;';
        document.body.appendChild(overlay);
        this._overlay = overlay;
    }

    _unblockPage() {
        if (!this._overlay) { return; }

        this._overlay.remove();
        this._overlay = null;
    }
}
