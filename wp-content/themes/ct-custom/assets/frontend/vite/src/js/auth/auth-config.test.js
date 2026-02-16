import { describe, it, expect } from 'vitest';
import {
    STORAGE_TOKEN_KEY,
    MAX_FIELDS,
    AUTH_FORMS,
    TAB_FORMS,
    FLOW_FORMS,
    VALIDATION,
} from './auth-config.js';

describe('auth-config', () => {
    it('STORAGE_TOKEN_KEY equals ct_auth_token', () => {
        expect(STORAGE_TOKEN_KEY).toBe('ct_auth_token');
    });

    it('MAX_FIELDS equals 20', () => {
        expect(MAX_FIELDS).toBe(20);
    });

    it('AUTH_FORMS contains exactly 6 form names', () => {
        expect(AUTH_FORMS).toHaveLength(6);
    });

    it('TAB_FORMS contains login and register only', () => {
        expect(TAB_FORMS).toEqual(['login', 'register']);
    });

    it('FLOW_FORMS contains 4 flow forms', () => {
        expect(FLOW_FORMS).toHaveLength(4);
        expect(FLOW_FORMS).toEqual(['forgot-password', 'activation-code', 'reset-code', 'reset-password']);
    });

    it('AUTH_FORMS includes all TAB_FORMS and FLOW_FORMS', () => {
        const combined = [...TAB_FORMS, ...FLOW_FORMS];
        expect(AUTH_FORMS).toEqual(expect.arrayContaining(combined));
        expect(AUTH_FORMS).toHaveLength(combined.length);
    });

    it('EMAIL_REGEX accepts valid email', () => {
        expect(VALIDATION.EMAIL_REGEX.test('user@example.com')).toBe(true);
        expect(VALIDATION.EMAIL_REGEX.test('test.user@domain.co.uk')).toBe(true);
    });

    it('EMAIL_REGEX rejects email without @', () => {
        expect(VALIDATION.EMAIL_REGEX.test('userexample.com')).toBe(false);
    });

    it('EMAIL_REGEX rejects email without domain', () => {
        expect(VALIDATION.EMAIL_REGEX.test('user@')).toBe(false);
        expect(VALIDATION.EMAIL_REGEX.test('user@domain')).toBe(false);
    });

    it('PASSWORD_RULES min-length passes for 8+ chars', () => {
        expect(VALIDATION.PASSWORD_RULES['min-length']('12345678')).toBe(true);
        expect(VALIDATION.PASSWORD_RULES['min-length']('123456789')).toBe(true);
    });

    it('PASSWORD_RULES min-length fails for 7 chars', () => {
        expect(VALIDATION.PASSWORD_RULES['min-length']('1234567')).toBe(false);
    });

    it('PASSWORD_RULES all rules pass for Test1ng!', () => {
        const password = 'Test1ng!';
        expect(VALIDATION.PASSWORD_RULES['min-length'](password)).toBe(true);
        expect(VALIDATION.PASSWORD_RULES.lowercase(password)).toBe(true);
        expect(VALIDATION.PASSWORD_RULES.uppercase(password)).toBe(true);
        expect(VALIDATION.PASSWORD_RULES.digit(password)).toBe(true);
        expect(VALIDATION.PASSWORD_RULES.special(password)).toBe(true);
    });

    it('USERNAME_RULES username-min fails for 3 chars', () => {
        expect(VALIDATION.USERNAME_RULES['username-min']('abc')).toBe(false);
    });

    it('CODE_REGEX accepts 6 digits, rejects 5 digits and letters', () => {
        expect(VALIDATION.CODE_REGEX.test('123456')).toBe(true);
        expect(VALIDATION.CODE_REGEX.test('12345')).toBe(false);
        expect(VALIDATION.CODE_REGEX.test('12345a')).toBe(false);
        expect(VALIDATION.CODE_REGEX.test('abcdef')).toBe(false);
    });
});
