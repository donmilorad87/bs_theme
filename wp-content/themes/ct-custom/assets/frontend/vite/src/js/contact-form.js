/**
 * ContactForm - Handles contact form submission via REST API.
 *
 * Supports multiple forms on the same page. Each form uses its own
 * data-pointer attribute but submits to the same REST endpoint.
 *
 * @package BS_Custom
 */

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const MAX_FORMS = 20;

export default class ContactForm {

    constructor() {
        const forms = document.querySelectorAll('.ct-contact-form');

        if (!forms.length) {
            return;
        }

        this._instances = [];
        let count = 0;

        for (let i = 0; i < forms.length; i++) {
            if (count >= MAX_FORMS) {
                break;
            }
            count++;

            this._instances.push(new ContactFormInstance(forms[i]));
        }
    }
}

class ContactFormInstance {

    constructor(form) {
        this._form = form;
        this._restUrl = this._form.getAttribute('data-rest-url') || '';
        this._nonce = this._form.getAttribute('data-nonce') || '';
        this._pointer = this._form.getAttribute('data-pointer') || 'contact_us';
        this._submitting = false;

        this._bindSubmit();
    }

    _bindSubmit() {
        this._form.addEventListener('submit', (e) => {
            e.preventDefault();
            this._handleSubmit();
        });
    }

    async _handleSubmit() {
        if (this._submitting) {
            return;
        }

        const name = this._form.elements['name'].value.trim();
        const email = this._form.elements['email'].value.trim();
        const phone = this._form.elements['phone'].value.trim();
        const message = this._form.elements['message'].value.trim();

        const errors = this._validate(name, email, message);
        if (errors.length > 0) {
            this._showMessage('error', errors.join(' '));
            return;
        }

        this._submitting = true;
        this._setLoading(true);
        this._clearMessages();

        try {
            const response = await fetch(this._restUrl + '/contact/submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this._nonce,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: name,
                    email: email,
                    phone: phone,
                    message: message,
                    pointer: this._pointer,
                }),
            });

            const data = await response.json();

            this._setLoading(false);
            this._submitting = false;

            if (response.status === 429) {
                this._showMessage('error', data.message || 'Too many submissions. Please try again later.');
                return;
            }

            if (data.success) {
                this._showMessage('success', data.message || 'Message sent successfully.');
                this._form.elements['name'].value = '';
                this._form.elements['email'].value = '';
                this._form.elements['phone'].value = '';
                this._form.elements['message'].value = '';
            } else {
                this._showMessage('error', data.message || 'Failed to send message.');
            }
        } catch {
            this._setLoading(false);
            this._submitting = false;
            this._showMessage('error', 'A network error occurred. Please try again.');
        }
    }

    _validate(name, email, message) {
        const errors = [];

        if (!name) {
            errors.push('Name is required.');
        }
        if (!email) {
            errors.push('Email is required.');
        } else if (!EMAIL_REGEX.test(email)) {
            errors.push('Please enter a valid email address.');
        }
        if (!message) {
            errors.push('Message is required.');
        }

        return errors;
    }

    _showMessage(type, text) {
        const container = this._form.querySelector('.ct-contact-form__messages');
        if (!container) { return; }

        container.innerHTML = '';

        const msg = document.createElement('div');
        msg.className = 'ct-contact-form__message fs14 ct-contact-form__message--' + type;
        msg.textContent = text;
        container.appendChild(msg);
    }

    _clearMessages() {
        const container = this._form.querySelector('.ct-contact-form__messages');
        if (container) {
            container.innerHTML = '';
        }
    }

    _setLoading(loading) {
        const btn = this._form.querySelector('.ct-contact-form__submit');
        if (!btn) { return; }

        btn.disabled = loading;

        if (loading) {
            btn.classList.add('ct-contact-form__submit--loading');
        } else {
            btn.classList.remove('ct-contact-form__submit--loading');
        }
    }
}
