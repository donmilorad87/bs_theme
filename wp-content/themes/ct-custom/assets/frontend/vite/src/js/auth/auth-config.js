/**
 * Auth Config - Constants and configuration.
 *
 * @package BS_Custom
 */

export const STORAGE_TOKEN_KEY = 'ct_auth_token';

export const MAX_FIELDS = 20;

export const AUTH_FORMS = ['login', 'register', 'forgot-password', 'activation-code', 'reset-code', 'reset-password'];
export const TAB_FORMS = ['login', 'register'];
export const FLOW_FORMS = ['forgot-password', 'activation-code', 'reset-code', 'reset-password'];

export const VALIDATION = {
    EMAIL_REGEX: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    MIN_USERNAME: 4,
    MIN_PASSWORD: 8,
    PASSWORD_RULES: {
        'min-length': (v) => v.length >= 8,
        lowercase: (v) => /[a-z]/.test(v),
        uppercase: (v) => /[A-Z]/.test(v),
        digit: (v) => /\d/.test(v),
        special: (v) => /[^a-zA-Z0-9]/.test(v),
    },
    USERNAME_RULES: {
        'username-min': (v) => v.length >= 4,
        'username-chars': (v) => v.length === 0 || /^[a-zA-Z0-9._-]+$/.test(v),
        'username-special': (v) => (v.match(/[._-]/g) || []).length <= 2,
    },
    CODE_REGEX: /^\d{6}$/,
};
