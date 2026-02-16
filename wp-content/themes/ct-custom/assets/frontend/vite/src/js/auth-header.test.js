import { describe, it, expect, vi, beforeEach } from 'vitest';
import AuthHeader from './auth-header.js';

vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({
    json: () => Promise.resolve({ success: true }),
    status: 200
})));

describe('AuthHeader', () => {
    beforeEach(() => {
        localStorage.clear();
        vi.clearAllMocks();
        document.body.innerHTML = `
            <div class="ct-auth-links"
                 data-rest-url="http://test.com/wp-json/ct/v1/"
                 data-nonce="test-nonce"
                 data-cache-version="1">
                <span class="ct-auth-links__greeting">Hi, John</span>
                <a href="#" data-ct-auth-action="logout" class="ct-auth-links__link">Logout</a>
            </div>
        `;
    });

    it('should find .ct-auth-links and initialize', () => {
        const authHeader = new AuthHeader();

        const container = document.querySelector('.ct-auth-links');
        expect(container).not.toBeNull();
        expect(authHeader).toBeDefined();
    });

    it('should return early when no .ct-auth-links exists', () => {
        document.body.innerHTML = '';

        const authHeader = new AuthHeader();

        expect(authHeader).toBeDefined();
    });

    it('should clear token from localStorage when logout is clicked', async () => {
        localStorage.setItem('ct_auth_token', 'test-jwt');
        expect(localStorage.getItem('ct_auth_token')).toBe('test-jwt');

        new AuthHeader();

        const logoutButton = document.querySelector('[data-ct-auth-action="logout"]');
        logoutButton.click();

        await new Promise(resolve => setTimeout(resolve, 0));

        expect(localStorage.getItem('ct_auth_token')).toBeNull();
    });

    it('should call fetch with logout endpoint when logout is clicked', async () => {
        new AuthHeader();

        const logoutButton = document.querySelector('[data-ct-auth-action="logout"]');
        logoutButton.click();

        await new Promise(resolve => setTimeout(resolve, 0));

        expect(fetch).toHaveBeenCalledWith(
            'http://test.com/wp-json/ct/v1/logout',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': 'test-nonce'
                }),
                body: JSON.stringify({})
            })
        );
    });

    it('should call window.location.reload after logout', async () => {
        const reloadMock = vi.fn();
        Object.defineProperty(window, 'location', {
            value: { ...window.location, reload: reloadMock },
            writable: true
        });

        new AuthHeader();

        const logoutButton = document.querySelector('[data-ct-auth-action="logout"]');
        const clickEvent = new Event('click', { bubbles: true });
        logoutButton.dispatchEvent(clickEvent);

        await new Promise(resolve => setTimeout(resolve, 10));

        expect(reloadMock).toHaveBeenCalled();
    });
});
