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
        const inputs = container.querySelectorAll('[data-ct-validate-required], [data-ct-validate-password], [data-ct-validate-email], [data-ct-validate-match], [data-ct-validate-code], [data-ct-validate-username], [data-ct-validate-agree]');
        let count = 0;

        for (let i = 0; i < inputs.length && i < MAX_FIELDS; i++) {
            const input = inputs[i];
            count++;

            const eventType = input.type === 'checkbox' ? 'change' : 'input';

            input.addEventListener(eventType, () => {
                this._validateField(input, container);
                if (onValidityChange) {
                    onValidityChange(this.isFormValid(container));
                }
            });

            if (input.type !== 'checkbox') {
                input.addEventListener('blur', () => {
                    this._validateField(input, container);
                });
            }
        }
    }

    /**
     * Check if all validated fields in a container pass.
     *
     * @param {HTMLElement} container Form container.
     * @returns {boolean}
     */
    isFormValid(container) {
        const inputs = container.querySelectorAll('[data-ct-validate-required], [data-ct-validate-password], [data-ct-validate-email], [data-ct-validate-match], [data-ct-validate-code], [data-ct-validate-username], [data-ct-validate-agree]');
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
     * Force-validate ALL fields in a container, showing hints even for empty fields.
     *
     * Unlike _validateField which hides hints when empty, this method marks
     * empty required fields as invalid with visible validation hints.
     *
     * @param {HTMLElement} container Form container element.
     */
    validateAll(container) {
        const inputs = container.querySelectorAll('[data-ct-validate-required], [data-ct-validate-password], [data-ct-validate-email], [data-ct-validate-match], [data-ct-validate-code], [data-ct-validate-username], [data-ct-validate-agree]');

        for (let i = 0; i < inputs.length && i < MAX_FIELDS; i++) {
            const input = inputs[i];
            const isValid = this._isFieldValid(input, container);

            /* Agree checkbox */
            if (input.hasAttribute('data-ct-validate-agree')) {
                this._validateAgreeRules(input);
                continue;
            }

            /* Password fields: show rules even when empty */
            if (input.hasAttribute('data-ct-validate-password')) {
                this._validatePasswordRules(input, container);
                const field = input.closest('.ct-auth-form__field');
                const validation = field ? field.querySelector('.ct-auth-form__validation') : null;
                if (validation && input.value.length === 0) {
                    validation.classList.remove('ct-auth-form__validation--hidden');
                    const rules = validation.querySelectorAll('.ct-auth-form__rule');
                    for (let j = 0; j < rules.length && j < MAX_RULES; j++) {
                        rules[j].classList.add('ct-auth-form__rule--fail');
                        rules[j].classList.remove('ct-auth-form__rule--pass');
                    }
                }
                input.classList.toggle('ct-auth-form__input--valid', isValid);
                input.classList.toggle('ct-auth-form__input--invalid', !isValid);
                continue;
            }

            /* Username fields: show rules even when empty */
            if (input.hasAttribute('data-ct-validate-username')) {
                this._validateUsernameRules(input);
                const field = input.closest('.ct-auth-form__field');
                const validation = field ? field.querySelector('.ct-auth-form__validation') : null;
                if (validation && input.value.length === 0) {
                    validation.classList.remove('ct-auth-form__validation--hidden');
                    const rules = validation.querySelectorAll('.ct-auth-form__rule');
                    for (let j = 0; j < rules.length && j < MAX_RULES; j++) {
                        rules[j].classList.add('ct-auth-form__rule--fail');
                        rules[j].classList.remove('ct-auth-form__rule--pass');
                    }
                }
                input.classList.toggle('ct-auth-form__input--valid', isValid);
                input.classList.toggle('ct-auth-form__input--invalid', !isValid);
                continue;
            }

            /* Email fields */
            if (input.hasAttribute('data-ct-validate-email')) {
                this._validateEmailRules(input, true);
                continue;
            }

            /* Match fields (confirm password) */
            if (input.hasAttribute('data-ct-validate-match')) {
                this._updateMatchUI(input, container);
                if (input.value.length === 0) {
                    input.classList.remove('ct-auth-form__input--valid');
                    input.classList.add('ct-auth-form__input--invalid');
                }
                continue;
            }

            /* Required-only fields (first name, last name) */
            if (this._isRequiredOnly(input)) {
                this._validateRequiredRules(input, true);
                continue;
            }
        }
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
            const allPass = this._isFieldValid(input, container);
            input.classList.toggle('ct-auth-form__input--valid', allPass && value.length > 0);
            input.classList.toggle('ct-auth-form__input--invalid', !allPass && value.length > 0);
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
            const allPass = this._isFieldValid(input, container);
            input.classList.toggle('ct-auth-form__input--valid', allPass && value.length > 0);
            input.classList.toggle('ct-auth-form__input--invalid', !allPass && value.length > 0);
        }

        /* Email validation */
        if (input.hasAttribute('data-ct-validate-email')) {
            this._validateEmailRules(input, false);
        }

        /* Required-only fields (no password/username/email/match/agree attrs) */
        if (this._isRequiredOnly(input)) {
            this._validateRequiredRules(input, false);
        }

        /* Agree checkbox */
        if (input.hasAttribute('data-ct-validate-agree')) {
            this._validateAgreeRules(input);
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
     * Check if a field has only data-ct-validate-required (no other validators).
     *
     * @param {HTMLElement} input Input element.
     * @returns {boolean}
     */
    _isRequiredOnly(input) {
        return input.hasAttribute('data-ct-validate-required') &&
            !input.hasAttribute('data-ct-validate-password') &&
            !input.hasAttribute('data-ct-validate-username') &&
            !input.hasAttribute('data-ct-validate-email') &&
            !input.hasAttribute('data-ct-validate-match') &&
            !input.hasAttribute('data-ct-validate-code') &&
            !input.hasAttribute('data-ct-validate-agree');
    }

    /**
     * Validate email rules and update the hint UI.
     *
     * @param {HTMLElement} input     Email input element.
     * @param {boolean}     forceShow Show hints even when empty (for validateAll).
     */
    _validateEmailRules(input, forceShow) {
        const value = input.value;
        const field = input.closest('.ct-auth-form__field');
        const validation = field ? field.querySelector('.ct-auth-form__validation') : null;

        const isEmpty = value.length === 0;
        const passes = !isEmpty && VALIDATION.EMAIL_REGEX.test(value);

        input.classList.toggle('ct-auth-form__input--valid', passes);
        input.classList.toggle('ct-auth-form__input--invalid', (forceShow && isEmpty) || (!isEmpty && !passes));

        if (!validation) { return; }

        if (!forceShow && isEmpty) {
            validation.classList.add('ct-auth-form__validation--hidden');
            return;
        }

        validation.classList.remove('ct-auth-form__validation--hidden');

        const rules = validation.querySelectorAll('.ct-auth-form__rule');
        for (let i = 0; i < rules.length && i < MAX_RULES; i++) {
            rules[i].classList.toggle('ct-auth-form__rule--pass', passes);
            rules[i].classList.toggle('ct-auth-form__rule--fail', !passes);
        }
    }

    /**
     * Validate required-only fields and update UI.
     *
     * @param {HTMLElement} input     Input element.
     * @param {boolean}     forceShow Show hints even when empty (for validateAll).
     */
    _validateRequiredRules(input, forceShow) {
        const value = input.value.trim();
        const field = input.closest('.ct-auth-form__field');
        const validation = field ? field.querySelector('.ct-auth-form__validation') : null;

        const filled = value.length > 0;

        input.classList.toggle('ct-auth-form__input--valid', filled);
        input.classList.toggle('ct-auth-form__input--invalid', forceShow && !filled);

        if (!validation) { return; }

        if (!forceShow && !filled) {
            validation.classList.add('ct-auth-form__validation--hidden');
            return;
        }

        if (filled) {
            validation.classList.add('ct-auth-form__validation--hidden');
        } else {
            validation.classList.remove('ct-auth-form__validation--hidden');
            const rules = validation.querySelectorAll('.ct-auth-form__rule');
            for (let i = 0; i < rules.length && i < MAX_RULES; i++) {
                rules[i].classList.add('ct-auth-form__rule--fail');
                rules[i].classList.remove('ct-auth-form__rule--pass');
            }
        }
    }

    /**
     * Validate the agree checkbox and update UI.
     *
     * @param {HTMLElement} input Checkbox input element.
     */
    _validateAgreeRules(input) {
        const checked = input.checked;
        const wrapper = input.closest('.ct-auth-form__agree');
        const validation = wrapper ? wrapper.querySelector('.ct-auth-form__validation') : null;

        if (!validation) { return; }

        if (checked) {
            validation.classList.add('ct-auth-form__validation--hidden');
        } else {
            validation.classList.remove('ct-auth-form__validation--hidden');
            const rules = validation.querySelectorAll('.ct-auth-form__rule');
            for (let i = 0; i < rules.length && i < MAX_RULES; i++) {
                rules[i].classList.add('ct-auth-form__rule--fail');
                rules[i].classList.remove('ct-auth-form__rule--pass');
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
        /* Agree checkbox */
        if (input.hasAttribute('data-ct-validate-agree')) {
            return input.checked === true;
        }

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
