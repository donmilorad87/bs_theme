import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock fetch globally before importing AuthPage
vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({
    json: () => Promise.resolve({}),
    status: 200
})));

import AuthPage from './auth-page.js';

describe('AuthPage', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div class="ct-auth-card"
                 data-rest-url="http://test.com/wp-json/ct/v1/"
                 data-nonce="test-nonce"
                 data-cache-version="1"
                 data-home-url="/">
                <div class="ct-auth-card__tabs" style="display:block">
                    <button data-ct-auth-tab="login" class="ct-auth-card__tab--active" aria-selected="true">Login</button>
                    <button data-ct-auth-tab="register" aria-selected="false">Register</button>
                </div>
                <div data-ct-auth-back-bar style="display:none">
                    <button data-ct-auth-action="back-to-login">Back</button>
                </div>
                <div data-ct-auth-form="login" class="ct-auth-card__panel--active">
                    <div class="ct-auth-form">
                        <div class="ct-auth-form__messages"></div>
                        <input class="ct-auth-form__input" name="username_or_email" value="" />
                        <input class="ct-auth-form__input" name="password" value="" type="password" />
                        <button class="ct-auth-form__submit" data-ct-auth-action="login">Login</button>
                    </div>
                </div>
                <div data-ct-auth-form="register">
                    <div class="ct-auth-form">
                        <div class="ct-auth-form__messages"></div>
                        <input class="ct-auth-form__input" name="username" value="" />
                        <input class="ct-auth-form__input" name="email" value="" />
                        <input class="ct-auth-form__input" name="first_name" value="" />
                        <input class="ct-auth-form__input" name="last_name" value="" />
                        <input class="ct-auth-form__input" name="password" value="" type="password" />
                        <input class="ct-auth-form__input" name="password_confirm" value="" type="password" />
                        <button class="ct-auth-form__submit" data-ct-auth-action="register">Register</button>
                    </div>
                </div>
                <div data-ct-auth-form="forgot-password">
                    <div class="ct-auth-form">
                        <div class="ct-auth-form__messages"></div>
                        <input class="ct-auth-form__input" name="email" value="" />
                        <button class="ct-auth-form__submit" data-ct-auth-action="forgot">Send</button>
                    </div>
                </div>
                <div data-ct-auth-form="activation-code">
                    <div class="ct-auth-form">
                        <div class="ct-auth-form__messages"></div>
                        <input name="email" type="hidden" value="" />
                        <input class="ct-auth-form__input" name="code" value="" />
                        <button class="ct-auth-form__submit" data-ct-auth-action="verify-activation">Verify</button>
                    </div>
                </div>
                <div data-ct-auth-form="reset-code">
                    <div class="ct-auth-form">
                        <div class="ct-auth-form__messages"></div>
                        <input name="email" type="hidden" value="" />
                        <input class="ct-auth-form__input" name="code" value="" />
                        <button class="ct-auth-form__submit" data-ct-auth-action="verify-reset-code">Verify</button>
                    </div>
                </div>
                <div data-ct-auth-form="reset-password">
                    <div class="ct-auth-form">
                        <div class="ct-auth-form__messages"></div>
                        <input name="reset_token" type="hidden" value="" />
                        <input class="ct-auth-form__input" name="new_password" value="" type="password" />
                        <input class="ct-auth-form__input" name="new_password_confirm" value="" type="password" />
                        <button class="ct-auth-form__submit" data-ct-auth-action="reset-password">Reset</button>
                    </div>
                </div>
            </div>
        `;
        window.location.hash = '';
    });

    it('Constructor finds .ct-auth-card and initializes', () => {
        const authPage = new AuthPage();

        expect(authPage).toBeDefined();
        expect(authPage._card).toBeDefined();
        expect(authPage._card.classList.contains('ct-auth-card')).toBe(true);
        expect(authPage._restUrl).toBe('http://test.com/wp-json/ct/v1/');
        expect(authPage._nonce).toBe('test-nonce');
        expect(authPage._cacheVersion).toBe('1');
        expect(authPage._homeUrl).toBe('/');
    });

    it('Constructor returns early when no .ct-auth-card exists', () => {
        document.body.innerHTML = '';

        const authPage = new AuthPage();

        expect(authPage._card).toBeNull();
    });

    it('_showForm("register") sets register panel active, hides login panel', () => {
        const authPage = new AuthPage();
        const loginPanel = document.querySelector('[data-ct-auth-form="login"]');
        const registerPanel = document.querySelector('[data-ct-auth-form="register"]');

        authPage._showForm('register');

        expect(loginPanel.classList.contains('ct-auth-card__panel--active')).toBe(false);
        expect(registerPanel.classList.contains('ct-auth-card__panel--active')).toBe(true);
    });

    it('_showForm("register") updates tab aria-selected', () => {
        const authPage = new AuthPage();
        const loginTab = document.querySelector('[data-ct-auth-tab="login"]');
        const registerTab = document.querySelector('[data-ct-auth-tab="register"]');

        authPage._showForm('register');

        expect(loginTab.getAttribute('aria-selected')).toBe('false');
        expect(loginTab.classList.contains('ct-auth-card__tab--active')).toBe(false);
        expect(registerTab.getAttribute('aria-selected')).toBe('true');
        expect(registerTab.classList.contains('ct-auth-card__tab--active')).toBe(true);
    });

    it('_showForm("forgot-password") hides tabs, shows back bar', () => {
        const authPage = new AuthPage();
        const tabsEl = document.querySelector('.ct-auth-card__tabs');
        const backBarEl = document.querySelector('[data-ct-auth-back-bar]');

        authPage._showForm('forgot-password');

        expect(tabsEl.style.display).toBe('none');
        expect(backBarEl.style.display).toBe('');
    });

    it('_showForm("login") shows tabs, hides back bar', () => {
        const authPage = new AuthPage();
        const tabsEl = document.querySelector('.ct-auth-card__tabs');
        const backBarEl = document.querySelector('[data-ct-auth-back-bar]');

        // First switch to a non-tab form
        authPage._showForm('forgot-password');
        // Then switch back to login
        authPage._showForm('login');

        expect(tabsEl.style.display).toBe('');
        expect(backBarEl.style.display).toBe('none');
    });

    it('Clicking tab button switches form', () => {
        const authPage = new AuthPage();
        const registerTab = document.querySelector('[data-ct-auth-tab="register"]');
        const loginPanel = document.querySelector('[data-ct-auth-form="login"]');
        const registerPanel = document.querySelector('[data-ct-auth-form="register"]');

        registerTab.click();

        expect(loginPanel.classList.contains('ct-auth-card__panel--active')).toBe(false);
        expect(registerPanel.classList.contains('ct-auth-card__panel--active')).toBe(true);
        expect(registerTab.getAttribute('aria-selected')).toBe('true');
        expect(registerTab.classList.contains('ct-auth-card__tab--active')).toBe(true);
    });

    it('Hash #register auto-switches to register form', () => {
        window.location.hash = '#register';

        const authPage = new AuthPage();
        const loginPanel = document.querySelector('[data-ct-auth-form="login"]');
        const registerPanel = document.querySelector('[data-ct-auth-form="register"]');

        expect(loginPanel.classList.contains('ct-auth-card__panel--active')).toBe(false);
        expect(registerPanel.classList.contains('ct-auth-card__panel--active')).toBe(true);
    });
});
