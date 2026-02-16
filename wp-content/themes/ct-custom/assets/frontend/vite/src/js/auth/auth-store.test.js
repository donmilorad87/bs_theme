import { describe, it, expect, beforeEach } from 'vitest';
import AuthStore from './auth-store.js';
import { STORAGE_TOKEN_KEY } from './auth-config.js';

describe('AuthStore', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('getToken returns null by default', () => {
        const store = new AuthStore();
        const token = store.getToken();
        expect(token).toBeNull();
    });

    it('setToken stores and getToken retrieves', () => {
        const store = new AuthStore();
        const testToken = 'test-jwt-token-12345';

        store.setToken(testToken);
        const retrievedToken = store.getToken();

        expect(retrievedToken).toBe(testToken);
        expect(localStorage.getItem(STORAGE_TOKEN_KEY)).toBe(testToken);
    });

    it('clearToken removes the token', () => {
        const store = new AuthStore();
        const testToken = 'test-jwt-token-67890';

        store.setToken(testToken);
        expect(store.getToken()).toBe(testToken);

        store.clearToken();
        expect(store.getToken()).toBeNull();
        expect(localStorage.getItem(STORAGE_TOKEN_KEY)).toBeNull();
    });

    it('constructor stores cacheVersion', () => {
        const version = '1.2.3';
        const store = new AuthStore(version);

        expect(store._cacheVersion).toBe(version);
    });
});
