import { describe, it, expect, vi, beforeEach } from 'vitest';
import AuthApi from './auth-api.js';

describe('AuthApi', () => {
    let authApi;
    let mockStore;
    let mockFetch;

    beforeEach(() => {
        mockStore = {
            getToken: vi.fn(() => 'test-token-123')
        };

        mockFetch = vi.fn(() => Promise.resolve({
            json: () => Promise.resolve({ success: true }),
            status: 200
        }));

        vi.stubGlobal('fetch', mockFetch);

        authApi = new AuthApi('https://example.com/wp-json', 'test-nonce', mockStore);
    });

    it('post() sends POST without Authorization header', async () => {
        await authApi.post('/endpoint', { data: 'test' });

        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/endpoint',
            expect.objectContaining({
                method: 'POST',
                headers: expect.not.objectContaining({
                    Authorization: expect.anything()
                })
            })
        );
    });

    it('post() includes X-WP-Nonce header', async () => {
        await authApi.post('/endpoint', { data: 'test' });

        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/endpoint',
            expect.objectContaining({
                headers: expect.objectContaining({
                    'X-WP-Nonce': 'test-nonce'
                })
            })
        );
    });

    it('post() sends Content-Type application/json', async () => {
        await authApi.post('/endpoint', { data: 'test' });

        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/endpoint',
            expect.objectContaining({
                headers: expect.objectContaining({
                    'Content-Type': 'application/json'
                })
            })
        );
    });

    it('postAuth() includes Bearer token when store has token', async () => {
        await authApi.postAuth('/endpoint', { data: 'test' });

        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/endpoint',
            expect.objectContaining({
                headers: expect.objectContaining({
                    'Authorization': 'Bearer test-token-123'
                })
            })
        );
    });

    it('postAuth() omits Authorization when store has no token', async () => {
        mockStore.getToken.mockReturnValue(null);

        await authApi.postAuth('/endpoint', { data: 'test' });

        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/endpoint',
            expect.objectContaining({
                headers: expect.not.objectContaining({
                    Authorization: expect.anything()
                })
            })
        );
    });

    it('getAuth() sends GET with Bearer token', async () => {
        await authApi.getAuth('/endpoint');

        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/endpoint',
            expect.objectContaining({
                method: 'GET',
                headers: expect.objectContaining({
                    'Authorization': 'Bearer test-token-123',
                    'X-WP-Nonce': 'test-nonce'
                })
            })
        );
    });

    it('getAuth() calls onUnauthorized handler on 401', async () => {
        const unauthorizedHandler = vi.fn();
        authApi.setUnauthorizedHandler(unauthorizedHandler);

        mockFetch.mockResolvedValueOnce({
            json: () => Promise.resolve({ error: 'Unauthorized' }),
            status: 401
        });

        await authApi.getAuth('/endpoint');

        expect(unauthorizedHandler).toHaveBeenCalled();
    });

    it('uploadAuth() sends POST with Bearer token and FormData body', async () => {
        const formData = new FormData();
        formData.append('file', 'test-file');

        await authApi.uploadAuth('/upload', formData);

        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/upload',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    'Authorization': 'Bearer test-token-123',
                    'X-WP-Nonce': 'test-nonce'
                }),
                body: formData
            })
        );
    });

    it('setNonce() updates the nonce for subsequent requests', async () => {
        authApi.setNonce('new-nonce-456');

        await authApi.post('/endpoint', { data: 'test' });

        expect(mockFetch).toHaveBeenCalledWith(
            'https://example.com/wp-json/endpoint',
            expect.objectContaining({
                headers: expect.objectContaining({
                    'X-WP-Nonce': 'new-nonce-456'
                })
            })
        );
    });
});
