/**
 * CustomizerContactPoint - Fixed-shape form control for managing contact
 * point data (phone, fax, email, contact type, address) inside the
 * WordPress Customizer panel.
 *
 * Syncs data to a hidden textarea bound to the Customizer setting
 * so "Publish" persists changes via the standard Customizer flow.
 *
 * @package BS_Custom
 */

(function ($) {
    'use strict';

    var DEBOUNCE_MS = 300;

    function assert(condition, message) {
        if (!condition) {
            throw new Error('Assertion failed: ' + (message || ''));
        }
    }

    function escapeHtml(text) {
        assert(typeof text === 'string', 'escapeHtml expects a string');

        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * @param {string} settingId - The Customizer setting ID.
     */
    function CustomizerContactPoint(settingId) {
        assert(typeof settingId === 'string', 'settingId must be a string');
        assert(settingId.length > 0, 'settingId must not be empty');

        this.settingId = settingId;
        this.container = null;
        this.textarea = null;
        this._debounceTimer = null;
    }

    CustomizerContactPoint.prototype.init = function (container) {
        assert(container instanceof HTMLElement, 'container must be an HTMLElement');

        this.container = container;
        this.textarea = container.parentElement.querySelector('.ct-contact-point-textarea');

        assert(this.textarea instanceof HTMLTextAreaElement, 'textarea must exist');

        this._renderForm();
    };

    /* ─── Data access ─── */

    CustomizerContactPoint.prototype._getData = function () {
        var raw = this.textarea.value;
        var parsed = {};

        try {
            parsed = JSON.parse(raw);
        } catch (e) {
            parsed = {};
        }

        if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
            parsed = {};
        }

        return {
            telephone: parsed.telephone || '',
            fax_number: parsed.fax_number || '',
            email: parsed.email || '',
            contact_type: parsed.contact_type || 'customer service',
            address: {
                street_number: (parsed.address && parsed.address.street_number) || '',
                street_address: (parsed.address && parsed.address.street_address) || '',
                city: (parsed.address && parsed.address.city) || '',
                state: (parsed.address && parsed.address.state) || '',
                postal_code: (parsed.address && parsed.address.postal_code) || '',
                country: (parsed.address && parsed.address.country) || ''
            }
        };
    };

    CustomizerContactPoint.prototype._setData = function (data) {
        assert(typeof data === 'object' && data !== null, 'data must be an object');

        var json = JSON.stringify(data);
        this.textarea.value = json;

        var setting = wp.customize(this.settingId);
        if (setting) {
            setting.set(json);
        }
    };

    /* ─── Rendering ─── */

    CustomizerContactPoint.prototype._renderForm = function () {
        var data = this._getData();
        var self = this;
        var control = wp.customize.control(this.settingId);
        var choices = (control && control.params && control.params.contact_type_choices)
            ? control.params.contact_type_choices
            : { 'customer service': 'Customer Service' };

        var optionsHtml = '';
        var choiceKeys = Object.keys(choices);
        var maxChoices = 20;
        var choiceCount = 0;

        for (var i = 0; i < choiceKeys.length; i++) {
            if (choiceCount >= maxChoices) {
                break;
            }
            choiceCount++;

            var key = choiceKeys[i];
            var label = choices[key];
            var sel = (data.contact_type === key) ? ' selected' : '';
            optionsHtml += '<option value="' + escapeHtml(key) + '"' + sel + '>'
                + escapeHtml(label) + '</option>';
        }

        this.container.innerHTML =
            '<div class="ct-cp-form">'

            /* Telephone */
            + '<div class="ct-cp-field">'
            + '<label>Telephone</label>'
            + '<input type="text" class="ct-cp-telephone" maxlength="50" value="' + escapeHtml(data.telephone) + '" placeholder="e.g. +1 (555) 123-4567">'
            + '</div>'

            /* Fax */
            + '<div class="ct-cp-field">'
            + '<label>Fax</label>'
            + '<input type="text" class="ct-cp-fax" maxlength="50" value="' + escapeHtml(data.fax_number) + '" placeholder="e.g. +1 (555) 123-4568">'
            + '</div>'

            /* Email */
            + '<div class="ct-cp-field">'
            + '<label>Email</label>'
            + '<input type="email" class="ct-cp-email" maxlength="254" value="' + escapeHtml(data.email) + '" placeholder="e.g. info@example.com">'
            + '</div>'

            /* Contact Type */
            + '<div class="ct-cp-field">'
            + '<label>Contact Type</label>'
            + '<select class="ct-cp-contact-type">' + optionsHtml + '</select>'
            + '</div>'

            /* Address divider */
            + '<div class="ct-cp-divider"><span>Postal Address</span></div>'

            /* Street Number */
            + '<div class="ct-cp-field">'
            + '<label>Street Number</label>'
            + '<input type="text" class="ct-cp-street-number" maxlength="20" value="' + escapeHtml(data.address.street_number) + '">'
            + '</div>'

            /* Street Address */
            + '<div class="ct-cp-field">'
            + '<label>Street Address</label>'
            + '<input type="text" class="ct-cp-street-address" maxlength="200" value="' + escapeHtml(data.address.street_address) + '">'
            + '</div>'

            /* City */
            + '<div class="ct-cp-field">'
            + '<label>City</label>'
            + '<input type="text" class="ct-cp-city" maxlength="100" value="' + escapeHtml(data.address.city) + '">'
            + '</div>'

            /* State */
            + '<div class="ct-cp-field">'
            + '<label>State</label>'
            + '<input type="text" class="ct-cp-state" maxlength="100" value="' + escapeHtml(data.address.state) + '">'
            + '</div>'

            /* Postal Code */
            + '<div class="ct-cp-field">'
            + '<label>Postal Code</label>'
            + '<input type="text" class="ct-cp-postal-code" maxlength="20" value="' + escapeHtml(data.address.postal_code) + '">'
            + '</div>'

            /* Country */
            + '<div class="ct-cp-field">'
            + '<label>Country</label>'
            + '<input type="text" class="ct-cp-country" maxlength="100" value="' + escapeHtml(data.address.country) + '">'
            + '</div>'

            + '</div>';

        /* Bind change events */
        var form = this.container.querySelector('.ct-cp-form');
        var inputs = form.querySelectorAll('input, select');
        var handler = function () { self._onFieldChange(); };
        var max = 20;
        var count = 0;

        for (var j = 0; j < inputs.length; j++) {
            if (count >= max) {
                break;
            }
            count++;
            inputs[j].addEventListener('input', handler);
            inputs[j].addEventListener('change', handler);
        }
    };

    /* ─── Form reading ─── */

    CustomizerContactPoint.prototype._readForm = function () {
        var form = this.container.querySelector('.ct-cp-form');

        assert(form instanceof HTMLElement, 'form must exist');

        return {
            telephone: form.querySelector('.ct-cp-telephone').value,
            fax_number: form.querySelector('.ct-cp-fax').value,
            email: form.querySelector('.ct-cp-email').value,
            contact_type: form.querySelector('.ct-cp-contact-type').value,
            address: {
                street_number: form.querySelector('.ct-cp-street-number').value,
                street_address: form.querySelector('.ct-cp-street-address').value,
                city: form.querySelector('.ct-cp-city').value,
                state: form.querySelector('.ct-cp-state').value,
                postal_code: form.querySelector('.ct-cp-postal-code').value,
                country: form.querySelector('.ct-cp-country').value
            }
        };
    };

    /* ─── Debounced change handler ─── */

    CustomizerContactPoint.prototype._onFieldChange = function () {
        var self = this;

        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
        }

        this._debounceTimer = setTimeout(function () {
            var data = self._readForm();
            self._setData(data);
        }, DEBOUNCE_MS);
    };

    /* ─── Bootstrap ─── */

    wp.customize.control('bs_custom_contact_point', function (control) {
        control.deferred.embedded.done(function () {
            var container = control.container.find('.ct-contact-point-control')[0];

            if (container) {
                var instance = new CustomizerContactPoint('bs_custom_contact_point');
                instance.init(container);
            }
        });
    });

})(jQuery);
