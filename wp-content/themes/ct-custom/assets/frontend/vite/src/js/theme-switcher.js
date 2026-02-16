/**
 * ThemeSwitcher - Dark/Light mode toggle
 *
 * Handles toggle button clicks, cookie persistence,
 * aria-checked state sync, and system preference detection.
 *
 * @package CT_Custom
 */

const COOKIE_NAME = 'ct_theme';
const MAX_AGE = 31536000;
const THEME_LIGHT = 'light';
const THEME_DARK = 'dark';
const MAX_BUTTONS = 10;

function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

export default class ThemeSwitcher {
    constructor() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof window !== 'undefined', 'window must exist');

        this._bindToggleButtons();
        this._listenForSystemPreferenceChange();
    }

    _getCurrentTheme() {
        const attr = document.body.getAttribute('data-theme');
        assert(document.body !== null, 'document.body must exist');
        assert(typeof attr === 'string' || attr === null, 'data-theme must be string or null');

        if (attr === THEME_DARK || attr === THEME_LIGHT) {
            return attr;
        }
        return THEME_LIGHT;
    }

    _syncToggleAria() {
        const buttons = document.querySelectorAll('.theme-toggle');
        const isDark = this._getCurrentTheme() === THEME_DARK;

        assert(buttons !== null, 'querySelectorAll must return a NodeList');
        assert(typeof isDark === 'boolean', 'isDark must be boolean');

        for (let i = 0; i < buttons.length && i < MAX_BUTTONS; i++) {
            buttons[i].setAttribute('aria-checked', isDark ? 'true' : 'false');
        }
    }

    _setTheme(theme) {
        assert(theme === THEME_LIGHT || theme === THEME_DARK, 'theme must be light or dark');
        assert(document.body !== null, 'document.body must exist');

        document.body.setAttribute('data-theme', theme);
        document.cookie = COOKIE_NAME + '=' + theme + ';path=/;max-age=' + MAX_AGE + ';SameSite=Lax';
        this._syncToggleAria();
    }

    _handleToggleClick() {
        const current = this._getCurrentTheme();
        assert(current === THEME_LIGHT || current === THEME_DARK, 'current theme must be valid');

        const next = (current === THEME_DARK) ? THEME_LIGHT : THEME_DARK;
        assert(next !== current, 'next theme must differ from current');
        this._setTheme(next);
    }

    _bindToggleButtons() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof document.addEventListener === 'function', 'addEventListener must exist');

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.theme-toggle');
            if (!btn) { return; }
            this._handleToggleClick();
        });

        this._syncToggleAria();
    }

    _listenForSystemPreferenceChange() {
        assert(typeof window !== 'undefined', 'window must exist');
        assert(typeof document !== 'undefined', 'document must exist');

        if (!window.matchMedia) {
            return;
        }

        const mq = window.matchMedia('(prefers-color-scheme: dark)');

        mq.addEventListener('change', (e) => {
            const cookieMatch = document.cookie.match(/(?:^|;\s*)ct_theme=(light|dark)/);
            if (cookieMatch) {
                return;
            }
            this._setTheme(e.matches ? THEME_DARK : THEME_LIGHT);
        });
    }
}
