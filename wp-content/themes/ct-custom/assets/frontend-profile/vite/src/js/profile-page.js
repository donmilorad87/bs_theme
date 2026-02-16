/**
 * ProfilePage - Handles the dedicated profile page (profile.php template).
 *
 * Duplicated from frontend/vite/src/js/profile-page.js for standalone profile bundle.
 *
 * @package BS_Custom
 */

import AuthStore from './auth/auth-store.js';
import AuthApi from './auth/auth-api.js';
import AuthFormBinder from './auth/auth-form-binder.js';
import AuthProfile from './auth/auth-profile.js';

function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

export default class ProfilePage {
    constructor() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof window !== 'undefined', 'window must exist');

        this._card = document.querySelector('.ct-profile-card');

        if (!this._card) {
            return;
        }

        this._restUrl = this._card.getAttribute('data-rest-url') || '';
        this._nonce = this._card.getAttribute('data-nonce') || '';
        this._cacheVersion = this._card.getAttribute('data-cache-version') || '0';
        this._authUrl = this._card.getAttribute('data-auth-url') || '/login-register/';

        assert(typeof this._restUrl === 'string', 'restUrl must be a string');
        assert(typeof this._nonce === 'string', 'nonce must be a string');

        this._profileMessagesLoaded = false;
        this._overlay = null;
        this._initSubSystems();
        this._bindForm();
        this._bindActions();
        this._bindProfileTabs();
    }

    _initSubSystems() {
        this._store = new AuthStore(this._cacheVersion);
        this._api = new AuthApi(this._restUrl, this._nonce, this._store);
        this._binder = new AuthFormBinder();
        this._profile = new AuthProfile(this._api, this._showMessage.bind(this), this._setLoading.bind(this));

        this._api.setUnauthorizedHandler(() => {
            window.location.href = this._authUrl;
        });
    }

    _bindForm() {
        assert(this._card instanceof HTMLElement, 'card must be HTMLElement');

        this._binder.bind(this._card);
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
    }

    _handleAction(action) {
        assert(typeof action === 'string', 'action must be a string');
        assert(action.length > 0, 'action must not be empty');

        switch (action) {
            case 'save-profile':
                this._submitSaveProfile();
                break;
            case 'change-password':
                this._submitChangePassword();
                break;
            case 'upload-avatar':
                this._handleAvatarUpload();
                break;
            case 'logout':
                this._submitLogout();
                break;
        }
    }

    _bindProfileTabs() {
        const tabs = this._card.querySelectorAll('[data-ct-profile-tab]');
        const MAX_TABS = 5;
        let count = 0;

        tabs.forEach((tab) => {
            if (count >= MAX_TABS) { return; }
            count++;

            tab.addEventListener('click', () => {
                this._switchProfileTab(tab.getAttribute('data-ct-profile-tab'), tabs);
            });
        });
    }

    _switchProfileTab(tabName, tabs) {
        if (!tabName) { return; }

        const MAX_TABS = 5;
        let count = 0;

        tabs.forEach((tab) => {
            if (count >= MAX_TABS) { return; }
            count++;

            const isActive = tab.getAttribute('data-ct-profile-tab') === tabName;
            tab.classList.toggle('ct-auth-form__tab--active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        const panels = this._card.querySelectorAll('[data-ct-profile-panel]');
        const MAX_PANELS = 5;
        count = 0;

        panels.forEach((panel) => {
            if (count >= MAX_PANELS) { return; }
            count++;

            const isActive = panel.getAttribute('data-ct-profile-panel') === tabName;
            panel.classList.toggle('ct-auth-form__tab-panel--active', isActive);
            panel.style.display = isActive ? '' : 'none';
        });

        if (tabName === 'messages' && !this._profileMessagesLoaded) {
            this._profileMessagesLoaded = true;
            this._profile.loadUserMessages(this._card);
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Profile actions                                                    */
    /* ------------------------------------------------------------------ */

    _submitSaveProfile() {
        this._profile.saveProfile(this._card, (displayName) => {
            this._updateDisplayName(displayName);
        });
    }

    _submitChangePassword() {
        this._profile.changePassword(this._card, () => {
            this._store.clearToken();
            this._blockPage();
            setTimeout(() => {
                window.location.href = this._authUrl;
            }, 1500);
        });
    }

    _handleAvatarUpload() {
        this._profile.handleAvatarUpload(this._card);
    }

    _submitLogout() {
        this._store.clearToken();

        this._api.post('logout', {})
            .then(() => {
                window.location.reload();
            })
            .catch(() => {
                window.location.reload();
            });
    }

    /* ------------------------------------------------------------------ */
    /*  UI helpers                                                         */
    /* ------------------------------------------------------------------ */

    _updateDisplayName(displayName) {
        const authLinks = document.querySelector('.ct-auth-links');
        if (!authLinks) { return; }

        const greeting = authLinks.querySelector('.ct-auth-links__greeting');
        if (greeting) {
            greeting.textContent = displayName;
        }
    }

    _showMessage(container, type, text) {
        if (!container || !(container instanceof HTMLElement)) { return; }

        const msgContainer = container.querySelector('.ct-auth-form__messages');
        if (!msgContainer) { return; }

        msgContainer.innerHTML = '';

        const msg = document.createElement('div');
        msg.className = 'ct-auth-form__message fs14 ct-auth-form__message--' + type;
        msg.textContent = text;
        msgContainer.appendChild(msg);

        msgContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    _setLoading(container, loading) {
        if (!container || !(container instanceof HTMLElement)) { return; }

        const btn = container.querySelector('.ct-auth-form__submit');
        if (!btn) { return; }

        btn.disabled = loading;

        if (loading) {
            btn.classList.add('ct-auth-form__submit--loading');
            this._blockPage();
        } else {
            btn.classList.remove('ct-auth-form__submit--loading');
            this._unblockPage();
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
