/**
 * AuthHeader - Handles logout from the header on any page.
 *
 * @package BS_Custom
 */

import AuthStore from './auth/auth-store.js';
import AuthApi from './auth/auth-api.js';

function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

export default class AuthHeader {
    constructor() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof window !== 'undefined', 'window must exist');

        this._authLinks = document.querySelector('.ct-auth-links');

        if (!this._authLinks) {
            return;
        }

        const restUrl = this._authLinks.getAttribute('data-rest-url') || '';
        const nonce = this._authLinks.getAttribute('data-nonce') || '';
        const cacheVersion = this._authLinks.getAttribute('data-cache-version') || '0';

        this._store = new AuthStore(cacheVersion);
        this._api = new AuthApi(restUrl, nonce, this._store);
        this._bindLogout();
    }

    _bindLogout() {
        assert(this._authLinks !== null, 'authLinks must exist');
        assert(typeof this._authLinks.addEventListener === 'function', 'addEventListener must exist');

        this._authLinks.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-ct-auth-action="logout"]');
            if (!trigger) {
                return;
            }

            e.preventDefault();
            this._store.clearToken();

            this._api.post('logout', {})
                .then(() => {
                    window.location.reload();
                })
                .catch(() => {
                    window.location.reload();
                });
        });
    }
}
