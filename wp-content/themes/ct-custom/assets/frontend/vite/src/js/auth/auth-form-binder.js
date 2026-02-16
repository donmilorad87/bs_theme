/**
 * AuthFormBinder - Binds validation + submit events after HTML injection.
 *
 * @package CT_Custom
 */

import AuthValidator from './auth-validator.js';

const MAX_FIELDS = 30;
const MAX_TOGGLES = 10;

export default class AuthFormBinder {
    constructor() {
        this._validator = new AuthValidator();
    }

    /**
     * Bind events for a freshly injected form.
     *
     * @param {HTMLElement} panel The container element with form HTML.
     */
    bind(panel) {
        if (!panel) { return; }

        const form = panel.querySelector('.ct-auth-form');
        if (!form) { return; }

        /* Find the submit button to enable/disable */
        const submitBtn = form.querySelector('.ct-auth-form__submit--disabled');

        if (submitBtn) {
            this._validator.bindFields(form, (allValid) => {
                submitBtn.disabled = !allValid;
                submitBtn.classList.toggle('ct-auth-form__submit--disabled', !allValid);
            });

            this._bindEnterSubmit(form, submitBtn);
        } else {
            /* Forms without disabled submit just need validation UI */
            this._validator.bindFields(form, null);
        }

        this._bindPasswordToggles(form);
    }

    /**
     * Allow Enter key to submit the form when validation passes.
     * Swallows Enter when validation has not been met.
     *
     * @param {HTMLElement} form      Form container.
     * @param {HTMLElement} submitBtn The submit button to click.
     */
    _bindEnterSubmit(form, submitBtn) {
        form.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') { return; }

            /* Never submit from a textarea */
            if (e.target.tagName === 'TEXTAREA') { return; }

            e.preventDefault();

            if (!submitBtn.disabled) {
                submitBtn.click();
            }
        });
    }

    /**
     * Bind password visibility toggle buttons.
     *
     * @param {HTMLElement} form Form container.
     */
    _bindPasswordToggles(form) {
        const toggles = form.querySelectorAll('.ct-auth-form__password-toggle');

        for (let i = 0; i < toggles.length && i < MAX_TOGGLES; i++) {
            const btn = toggles[i];
            const wrap = btn.closest('.ct-auth-form__password-wrap');
            if (!wrap) { continue; }

            const input = wrap.querySelector('input');
            if (!input) { continue; }

            btn.addEventListener('click', () => {
                const isVisible = input.type === 'text';
                input.type = isVisible ? 'password' : 'text';
                btn.classList.toggle('ct-auth-form__password-toggle--active', !isVisible);
            });
        }
    }

    /**
     * Get the validator instance.
     *
     * @returns {AuthValidator}
     */
    getValidator() {
        return this._validator;
    }
}
