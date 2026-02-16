/**
 * AuthValidator - Rule-based real-time validation engine.
 *
 * Duplicated from frontend/vite/src/js/auth/auth-validator.js for standalone auth bundle.
 *
 * @package BS_Custom
 */

import { VALIDATION } from './auth-config.js';

const MAX_FIELDS = 30;
const MAX_RULES = 10;

export default class AuthValidator {
    /**
     * Bind validation to all fields in a container.
     *
     * @param {HTMLElement} container Form container element.
     * @param {Function}    onValidityChange Called with (allValid: boolean).
     */
    bindFields(container, onValidityChange) {
        const inputs = container.querySelectorAll('[data-ct-validate-required], [data-ct-validate-password], [data-ct-validate-email], [data-ct-validate-match], [data-ct-validate-code], [data-ct-validate-username]');
        let count = 0;

        for (let i = 0; i < inputs.length && i < MAX_FIELDS; i++) {
            const input = inputs[i];
            count++;

            input.addEventListener('input', () => {
                this._validateField(input, container);
                if (onValidityChange) {
                    onValidityChange(this.isFormValid(container));
                }
            });

            input.addEventListener('blur', () => {
                this._validateField(input, container);
            });
        }
    }

    /**
     * Check if all validated fields in a container pass.
     *
     * @param {HTMLElement} container Form container.
     * @returns {boolean}
     */
    isFormValid(container) {
        const inputs = container.querySelectorAll('[data-ct-validate-required], [data-ct-validate-password], [data-ct-validate-email], [data-ct-validate-match], [data-ct-validate-code], [data-ct-validate-username]');
        let count = 0;

        for (let i = 0; i < inputs.length && i < MAX_FIELDS; i++) {
            const input = inputs[i];
            count++;

            if (!this._isFieldValid(input, container)) {
                return false;
            }
        }

        return count > 0;
    }

    /**
     * Validate a single field and update UI.
     *
     * @param {HTMLElement} input     The input element.
     * @param {HTMLElement} container Parent form container.
     */
    _validateField(input, container) {
        const value = input.value;

        /* Password validation with rule checklist */
        if (input.hasAttribute('data-ct-validate-password')) {
            this._validatePasswordRules(input, container);
        }

        /* Re-validate new_password "different" rule when current_password changes */
        if (input.name === 'current_password') {
            const newPassInput = container.querySelector('input[name="new_password"]');
            if (newPassInput && newPassInput.hasAttribute('data-ct-validate-password')) {
                this._validatePasswordRules(newPassInput, container);
            }
        }

        /* Username validation with rule checklist */
        if (input.hasAttribute('data-ct-validate-username')) {
            this._validateUsernameRules(input);
        }

        /* Match validation (confirm password) */
        if (input.hasAttribute('data-ct-validate-match')) {
            this._updateMatchUI(input, container);
        }

        /* Reverse match: when the source field changes, re-validate any confirm field pointing to it */
        if (input.name) {
            const reverseMatch = container.querySelector('[data-ct-validate-match="' + input.name + '"]');
            if (reverseMatch) {
                this._updateMatchUI(reverseMatch, container);
            }
        }
    }

    /**
     * Update the match hint UI for a confirm-password field.
     *
     * @param {HTMLElement} input     The confirm input with data-ct-validate-match.
     * @param {HTMLElement} container Parent form container.
     */
    _updateMatchUI(input, container) {
        const matchName = input.getAttribute('data-ct-validate-match');
        const matchInput = container.querySelector('input[name="' + matchName + '"]');
        if (!matchInput) { return; }

        const value = input.value;
        const matches = value === matchInput.value && value.length > 0;
        input.classList.toggle('ct-auth-form__input--valid', matches);
        input.classList.toggle('ct-auth-form__input--invalid', value.length > 0 && !matches);

        const hint = input.closest('.ct-auth-form__field').querySelector('.ct-auth-form__match-hint');
        if (hint) {
            hint.classList.toggle('ct-auth-form__match-hint--pass', matches);
            hint.classList.toggle('ct-auth-form__match-hint--fail', value.length > 0 && !matches);
            hint.classList.toggle('ct-auth-form__match-hint--hidden', value.length === 0);
        }
    }

    /**
     * Validate password rules and update the checklist UI.
     *
     * @param {HTMLElement} input     Password input element.
     * @param {HTMLElement} container Parent form container.
     */
    _validatePasswordRules(input, container) {
        const value = input.value;
        const field = input.closest('.ct-auth-form__field');
        const validation = field ? field.querySelector('.ct-auth-form__validation') : null;
        if (!validation) { return; }

        /* Show rules only when field has content */
        validation.classList.toggle('ct-auth-form__validation--hidden', value.length === 0);

        const rules = validation.querySelectorAll('.ct-auth-form__rule');
        let ruleCount = 0;

        for (let i = 0; i < rules.length && i < MAX_RULES; i++) {
            const ruleEl = rules[i];
            const ruleName = ruleEl.getAttribute('data-rule');
            ruleCount++;

            /* "different" rule: compare against another field */
            if (ruleName === 'different') {
                const compareName = ruleEl.getAttribute('data-rule-compare');
                if (compareName && container) {
                    const compareInput = container.querySelector('input[name="' + compareName + '"]');
                    const compareVal = compareInput ? compareInput.value : '';
                    const bothFilled = value.length > 0 && compareVal.length > 0;
                    const isDifferent = bothFilled && value !== compareVal;
                    const isSame = bothFilled && value === compareVal;
                    ruleEl.classList.toggle('ct-auth-form__rule--pass', isDifferent);
                    ruleEl.classList.toggle('ct-auth-form__rule--info', isSame);
                    ruleEl.classList.remove('ct-auth-form__rule--fail');
                }
                continue;
            }

            if (!ruleName || !VALIDATION.PASSWORD_RULES[ruleName]) {
                continue;
            }

            const passes = VALIDATION.PASSWORD_RULES[ruleName](value);
            ruleEl.classList.toggle('ct-auth-form__rule--pass', passes);
            ruleEl.classList.toggle('ct-auth-form__rule--fail', value.length > 0 && !passes);
        }
    }

    /**
     * Validate username rules and update the checklist UI.
     *
     * @param {HTMLElement} input Username input element.
     */
    _validateUsernameRules(input) {
        const value = input.value;
        const field = input.closest('.ct-auth-form__field');
        const validation = field ? field.querySelector('.ct-auth-form__validation') : null;
        if (!validation) { return; }

        /* Show rules only when field has content */
        validation.classList.toggle('ct-auth-form__validation--hidden', value.length === 0);

        const rules = validation.querySelectorAll('.ct-auth-form__rule');

        for (let i = 0; i < rules.length && i < MAX_RULES; i++) {
            const ruleEl = rules[i];
            const ruleName = ruleEl.getAttribute('data-rule');

            if (!ruleName || !VALIDATION.USERNAME_RULES[ruleName]) {
                continue;
            }

            const passes = VALIDATION.USERNAME_RULES[ruleName](value);
            ruleEl.classList.toggle('ct-auth-form__rule--pass', passes);
            ruleEl.classList.toggle('ct-auth-form__rule--fail', value.length > 0 && !passes);
        }
    }

    /**
     * Check if a single field is valid without updating UI.
     *
     * @param {HTMLElement} input     Input element.
     * @param {HTMLElement} container Form container.
     * @returns {boolean}
     */
    _isFieldValid(input, container) {
        const value = input.value.trim();

        if (input.hasAttribute('data-ct-validate-required') && value.length === 0) {
            return false;
        }

        if (input.hasAttribute('data-ct-validate-min')) {
            const min = parseInt(input.getAttribute('data-ct-validate-min'), 10);
            if (value.length < min) {
                return false;
            }
        }

        if (input.hasAttribute('data-ct-validate-email')) {
            if (!VALIDATION.EMAIL_REGEX.test(value)) {
                return false;
            }
        }

        if (input.hasAttribute('data-ct-validate-password')) {
            const rules = VALIDATION.PASSWORD_RULES;
            const ruleNames = Object.keys(rules);
            for (let i = 0; i < ruleNames.length && i < MAX_RULES; i++) {
                if (!rules[ruleNames[i]](value)) {
                    return false;
                }
            }

            /* Check "different" rule if present */
            const fieldEl = input.closest('.ct-auth-form__field');
            const diffRule = fieldEl ? fieldEl.querySelector('.ct-auth-form__rule[data-rule="different"]') : null;
            if (diffRule) {
                const compareName = diffRule.getAttribute('data-rule-compare');
                if (compareName) {
                    const compareInput = container.querySelector('input[name="' + compareName + '"]');
                    if (compareInput && compareInput.value.length > 0 && value === compareInput.value) {
                        return false;
                    }
                }
            }
        }

        if (input.hasAttribute('data-ct-validate-username')) {
            const rules = VALIDATION.USERNAME_RULES;
            const ruleNames = Object.keys(rules);
            for (let i = 0; i < ruleNames.length && i < MAX_RULES; i++) {
                if (!rules[ruleNames[i]](value)) {
                    return false;
                }
            }
        }

        if (input.hasAttribute('data-ct-validate-match')) {
            const matchName = input.getAttribute('data-ct-validate-match');
            const matchInput = container.querySelector('input[name="' + matchName + '"]');
            if (matchInput && value !== matchInput.value) {
                return false;
            }
        }

        if (input.hasAttribute('data-ct-validate-code')) {
            if (!VALIDATION.CODE_REGEX.test(value)) {
                return false;
            }
        }

        return true;
    }
}
