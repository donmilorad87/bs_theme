import { describe, it, expect, vi } from 'vitest';
import AuthValidator from './auth-validator.js';

function createField(attributes, value) {
    const container = document.createElement('div');
    const input = document.createElement('input');
    for (const [key, val] of Object.entries(attributes)) {
        input.setAttribute(key, val);
    }
    input.value = value || '';
    container.appendChild(input);
    return { container, input };
}

describe('AuthValidator', () => {
    it('isFormValid returns false for empty required field', () => {
        const validator = new AuthValidator();
        const { container } = createField({ 'data-ct-validate-required': 'true' }, '');

        const result = validator.isFormValid(container);

        expect(result).toBe(false);
    });

    it('isFormValid returns true for filled required field', () => {
        const validator = new AuthValidator();
        const { container } = createField({ 'data-ct-validate-required': 'true' }, 'test value');

        const result = validator.isFormValid(container);

        expect(result).toBe(true);
    });

    it('_isFieldValid returns true for valid email', () => {
        const validator = new AuthValidator();
        const { container, input } = createField({ 'data-ct-validate-email': 'true' }, 'user@example.com');

        const result = validator._isFieldValid(input, container);

        expect(result).toBe(true);
    });

    it('_isFieldValid returns false for invalid email', () => {
        const validator = new AuthValidator();
        const { container, input } = createField({ 'data-ct-validate-email': 'true' }, 'invalid-email');

        const result = validator._isFieldValid(input, container);

        expect(result).toBe(false);
    });

    it('_isFieldValid returns true for valid password "Test1ng!"', () => {
        const validator = new AuthValidator();
        const { container, input } = createField({ 'data-ct-validate-password': 'true' }, 'Test1ng!');

        const result = validator._isFieldValid(input, container);

        expect(result).toBe(true);
    });

    it('_isFieldValid returns false for weak password "test"', () => {
        const validator = new AuthValidator();
        const { container, input } = createField({ 'data-ct-validate-password': 'true' }, 'test');

        const result = validator._isFieldValid(input, container);

        expect(result).toBe(false);
    });

    it('_isFieldValid returns true for valid username "john_doe"', () => {
        const validator = new AuthValidator();
        const { container, input } = createField({ 'data-ct-validate-username': 'true' }, 'john_doe');

        const result = validator._isFieldValid(input, container);

        expect(result).toBe(true);
    });

    it('_isFieldValid returns false for short username "abc"', () => {
        const validator = new AuthValidator();
        const { container, input } = createField({ 'data-ct-validate-username': 'true' }, 'abc');

        const result = validator._isFieldValid(input, container);

        expect(result).toBe(false);
    });

    it('_isFieldValid returns true for matching fields', () => {
        const validator = new AuthValidator();
        const container = document.createElement('div');

        const passwordInput = document.createElement('input');
        passwordInput.setAttribute('name', 'password');
        passwordInput.value = 'Test1ng!';
        container.appendChild(passwordInput);

        const confirmInput = document.createElement('input');
        confirmInput.setAttribute('data-ct-validate-match', 'password');
        confirmInput.value = 'Test1ng!';
        container.appendChild(confirmInput);

        const result = validator._isFieldValid(confirmInput, container);

        expect(result).toBe(true);
    });

    it('_isFieldValid returns true for valid 6-digit code', () => {
        const validator = new AuthValidator();
        const { container, input } = createField({ 'data-ct-validate-code': 'true' }, '123456');

        const result = validator._isFieldValid(input, container);

        expect(result).toBe(true);
    });

    it('bindFields calls onValidityChange callback on input event', () => {
        const validator = new AuthValidator();
        const { container, input } = createField({ 'data-ct-validate-required': 'true' }, '');
        const callback = vi.fn();

        validator.bindFields(container, callback);
        input.value = 'test value';
        input.dispatchEvent(new Event('input', { bubbles: true }));

        expect(callback).toHaveBeenCalled();
    });
});
