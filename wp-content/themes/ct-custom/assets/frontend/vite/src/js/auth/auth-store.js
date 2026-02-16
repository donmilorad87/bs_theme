/**
 * AuthStore - localStorage manager for JWT token.
 *
 * @package CT_Custom
 */

import { STORAGE_TOKEN_KEY } from './auth-config.js';

export default class AuthStore {
    /**
     * @param {string} cacheVersion - Current cache version from server.
     */
    constructor(cacheVersion) {
        this._cacheVersion = cacheVersion || '0';
    }

    /**
     * Get the stored JWT token.
     *
     * @returns {string|null}
     */
    getToken() {
        return localStorage.getItem(STORAGE_TOKEN_KEY);
    }

    /**
     * Store a JWT token.
     *
     * @param {string} token JWT string.
     */
    setToken(token) {
        localStorage.setItem(STORAGE_TOKEN_KEY, token);
    }

    /**
     * Clear the stored JWT token.
     */
    clearToken() {
        localStorage.removeItem(STORAGE_TOKEN_KEY);
    }
}
