/**
 * AuthApi - REST client with nonce-auth (public) and JWT-auth (protected).
 *
 * Duplicated from frontend/vite/src/js/auth/auth-api.js for standalone auth bundle.
 *
 * @package BS_Custom
 */

export default class AuthApi {
    /**
     * @param {string}    restUrl REST API base URL.
     * @param {string}    nonce   WP REST nonce.
     * @param {AuthStore} store   AuthStore instance.
     */
    constructor(restUrl, nonce, store) {
        this._restUrl = restUrl;
        this._nonce = nonce;
        this._store = store;
        this._onUnauthorized = null;
    }

    /**
     * Set a callback for 401 responses.
     *
     * @param {Function} callback
     */
    setUnauthorizedHandler(callback) {
        this._onUnauthorized = callback;
    }

    /**
     * Update the nonce after login.
     *
     * @param {string} nonce New nonce value.
     */
    setNonce(nonce) {
        this._nonce = nonce;
    }

    /**
     * Make a public POST request (nonce-based auth).
     *
     * @param {string} endpoint Endpoint path.
     * @param {object} body     Request body.
     * @returns {Promise<object>}
     */
    post(endpoint, body) {
        return this._request('POST', endpoint, body, false);
    }

    /**
     * Make a JWT-authenticated POST request.
     *
     * @param {string} endpoint Endpoint path.
     * @param {object} body     Request body.
     * @returns {Promise<object>}
     */
    postAuth(endpoint, body) {
        return this._request('POST', endpoint, body, true);
    }

    /**
     * Make a JWT-authenticated GET request.
     *
     * @param {string} endpoint Endpoint path (including query string).
     * @returns {Promise<object>}
     */
    getAuth(endpoint) {
        const token = this._store.getToken();
        const headers = {
            'X-WP-Nonce': this._nonce,
        };

        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }

        return fetch(this._restUrl + endpoint, {
            method: 'GET',
            headers: headers,
            credentials: 'same-origin',
        }).then((response) => {
            if (response.status === 401 && this._onUnauthorized) {
                this._onUnauthorized();
            }
            return response.json();
        });
    }

    /**
     * Make a JWT-authenticated file upload request.
     *
     * @param {string}   endpoint Endpoint path.
     * @param {FormData} formData FormData with file.
     * @returns {Promise<object>}
     */
    uploadAuth(endpoint, formData) {
        const token = this._store.getToken();
        const headers = {
            'X-WP-Nonce': this._nonce,
        };

        if (token) {
            headers['Authorization'] = 'Bearer ' + token;
        }

        return fetch(this._restUrl + endpoint, {
            method: 'POST',
            headers: headers,
            credentials: 'same-origin',
            body: formData,
        }).then((response) => {
            return response.json();
        });
    }

    /**
     * Internal request method.
     *
     * @param {string}  method    HTTP method.
     * @param {string}  endpoint  Endpoint path.
     * @param {object}  body      Request body.
     * @param {boolean} useJwt    Use JWT auth instead of nonce.
     * @returns {Promise<object>}
     */
    _request(method, endpoint, body, useJwt) {
        const headers = {
            'Content-Type': 'application/json',
            'X-WP-Nonce': this._nonce,
        };

        if (useJwt) {
            const token = this._store.getToken();
            if (token) {
                headers['Authorization'] = 'Bearer ' + token;
            }
        }

        return fetch(this._restUrl + endpoint, {
            method: method,
            headers: headers,
            credentials: 'same-origin',
            body: JSON.stringify(body),
        }).then((response) => {
            return response.json();
        });
    }
}
