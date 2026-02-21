/**
 * ContactForm - Handles contact form submission via REST API.
 *
 * Supports multiple dynamic forms on the same page with conditional logic,
 * captcha, and file uploads.
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
        this._formId = this._form.getAttribute('data-form-id') || '';
        this._captchaEnabled = this._form.getAttribute('data-captcha-enabled') === '1';
        this._uploadsEnabled = this._form.getAttribute('data-uploads-enabled') === '1';
        this._submitting = false;
        this._conditionFields = [];
        this._filePreviews = [];

        this._bindSubmit();
        this._initConditionalLogic();
        this._initUploads();
        this._initFilePreviews();
        if (this._captchaEnabled) {
            this._initCaptcha();
        }
    }

    _bindSubmit() {
        this._form.addEventListener('submit', (e) => {
            e.preventDefault();
            this._handleSubmit();
        });
    }

    _initUploads() {
        if (this._uploadsEnabled) {
            return;
        }

        const fileFields = this._form.querySelectorAll('.ct-contact-form__field[data-field-type="file"]');
        fileFields.forEach((field) => {
            field.dataset.forceHidden = '1';
            this._setFieldVisibility(field, false);
        });
    }

    _initFilePreviews() {
        const fileFields = this._form.querySelectorAll('.ct-contact-form__field[data-field-type="file"]');
        fileFields.forEach((field) => {
            const input = field.querySelector('input[type="file"]');
            const preview = field.querySelector('.ct-contact-form__file-preview');
            if (!input || !preview) {
                return;
            }

            const image = preview.querySelector('.ct-contact-form__file-preview-image');
            const video = preview.querySelector('.ct-contact-form__file-preview-video');
            const name = preview.querySelector('.ct-contact-form__file-preview-name');
            const placeholder = preview.querySelector('.ct-contact-form__file-preview-placeholder');
            const hide = (el) => {
                if (el) {
                    el.classList.add('dn');
                }
            };
            const show = (el) => {
                if (el) {
                    el.classList.remove('dn');
                }
            };

            const clearPreview = () => {
                if (preview.dataset.objectUrl) {
                    URL.revokeObjectURL(preview.dataset.objectUrl);
                    delete preview.dataset.objectUrl;
                }
                preview.classList.remove('is-active');
                if (image) {
                    image.removeAttribute('src');
                    hide(image);
                    image.onerror = null;
                }
                if (video) {
                    try {
                        video.pause();
                    } catch {
                        /* ignore */
                    }
                    video.removeAttribute('src');
                    video.load();
                    hide(video);
                }
                if (name) {
                    name.textContent = '';
                    hide(name);
                }
                if (placeholder) {
                    placeholder.textContent = 'Preview will appear here.';
                    show(placeholder);
                }
            };

            clearPreview();

            input.addEventListener('change', () => {
                const file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) {
                    clearPreview();
                    return;
                }

                const acceptRaw = input.getAttribute('accept') || field.getAttribute('data-accept') || '';
                const allowedTokens = this._getAcceptTokens(acceptRaw);
                if (!this._isFileAllowed(file, allowedTokens)) {
                    this._showMessage('error', 'File type not allowed. ' + this._formatAllowedTypes(acceptRaw));
                    input.value = '';
                    clearPreview();
                    return;
                }

                preview.classList.add('is-active');
                if (placeholder) {
                    hide(placeholder);
                }

                if (name) {
                    name.textContent = file.name || '';
                    show(name);
                }

                const type = (file.type || '').toLowerCase();
                const nameLower = (file.name || '').toLowerCase();
                const ext = nameLower.indexOf('.') !== -1 ? nameLower.split('.').pop() : '';
                const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
                const videoExts = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];
                const isImage = type.indexOf('image/') === 0 || (!type && imageExts.includes(ext));
                const isVideo = type.indexOf('video/') === 0 || (!type && videoExts.includes(ext));
                const isPdf = type === 'application/pdf' || ext === 'pdf';

                if (preview.dataset.objectUrl) {
                    URL.revokeObjectURL(preview.dataset.objectUrl);
                    delete preview.dataset.objectUrl;
                }

                let objectUrl = '';
                try {
                    objectUrl = URL.createObjectURL(file);
                    preview.dataset.objectUrl = objectUrl;
                } catch {
                    objectUrl = '';
                }

                if (isPdf) {
                    if (image) {
                        image.removeAttribute('src');
                        hide(image);
                    }
                    if (video) {
                        try {
                            video.pause();
                        } catch {
                            /* ignore */
                        }
                        video.removeAttribute('src');
                        video.load();
                        hide(video);
                    }
                    if (placeholder) {
                        placeholder.textContent = 'Preview not available for this file type.';
                        show(placeholder);
                    }
                    return;
                }

                if (isImage && image && objectUrl) {
                    image.onerror = () => {
                        hide(image);
                        if (placeholder) {
                            placeholder.textContent = 'Preview not available for this file type.';
                            show(placeholder);
                        }
                    };
                    image.src = objectUrl;
                    show(image);
                    if (video) {
                        video.removeAttribute('src');
                        video.load();
                        hide(video);
                    }
                    return;
                }

                if (isVideo && video && objectUrl) {
                    try {
                        video.src = objectUrl;
                        video.load();
                        show(video);
                    } catch {
                        video.removeAttribute('src');
                        video.load();
                        hide(video);
                    }
                    if (image) {
                        image.removeAttribute('src');
                        hide(image);
                    }
                    return;
                }

                if (image && objectUrl) {
                    image.onerror = () => {
                        hide(image);
                        if (placeholder) {
                            placeholder.textContent = 'Preview not available for this file type.';
                            show(placeholder);
                        }
                    };
                    image.src = objectUrl;
                    show(image);
                    if (video) {
                        video.removeAttribute('src');
                        video.load();
                        hide(video);
                    }
                    return;
                }

                if (placeholder) {
                    placeholder.textContent = 'Preview not available for this file type.';
                    show(placeholder);
                }
            });

            this._filePreviews.push({ preview, clearPreview });
        });

        if (this._filePreviews.length > 0) {
            this._form.addEventListener('reset', () => {
                this._clearFilePreviews();
            });
        }
    }

    _clearFilePreviews() {
        this._filePreviews.forEach((entry) => {
            if (entry && typeof entry.clearPreview === 'function') {
                entry.clearPreview();
            }
        });
    }

    _initConditionalLogic() {
        const fields = this._form.querySelectorAll('.ct-contact-form__field[data-conditions]');
        fields.forEach((field) => {
            const raw = field.getAttribute('data-conditions');
            if (!raw) {
                return;
            }
            try {
                const config = JSON.parse(raw);
                if (config && config.rules && Array.isArray(config.rules)) {
                    this._conditionFields.push({ element: field, config: config });
                }
            } catch {
                /* ignore */
            }
        });

        if (this._conditionFields.length > 0) {
            this._applyConditions();
            this._form.addEventListener('input', () => this._applyConditions());
            this._form.addEventListener('change', () => this._applyConditions());
        }
    }

    _applyConditions() {
        for (let i = 0; i < this._conditionFields.length; i++) {
            const entry = this._conditionFields[i];
            const field = entry.element;
            const config = entry.config;

            if (field.dataset.forceHidden === '1') {
                this._setFieldVisibility(field, false);
                continue;
            }

            if (!config.enabled) {
                this._setFieldVisibility(field, true);
                continue;
            }

            const rules = Array.isArray(config.rules) ? config.rules : [];
            if (rules.length === 0) {
                this._setFieldVisibility(field, true);
                continue;
            }

            const relation = config.relation === 'any' ? 'any' : 'all';
            let pass = relation === 'all';

            for (let r = 0; r < rules.length; r++) {
                const rule = rules[r];
                const result = this._evaluateRule(rule);
                if (relation === 'all' && !result) {
                    pass = false;
                    break;
                }
                if (relation === 'any' && result) {
                    pass = true;
                    break;
                }
                if (relation === 'any') {
                    pass = false;
                }
            }

            this._setFieldVisibility(field, pass);
        }
    }

    _evaluateRule(rule) {
        if (!rule || !rule.field) {
            return true;
        }

        const value = this._getFieldValue(rule.field);
        const operator = rule.operator || 'equals';
        const target = rule.value || '';

        if (operator === 'checked') {
            return this._isCheckedValue(value);
        }
        if (operator === 'not_checked') {
            return !this._isCheckedValue(value);
        }

        if (Array.isArray(value)) {
            if (operator === 'contains') {
                return value.includes(target);
            }
            if (operator === 'equals') {
                return value.length === 1 && value[0] === target;
            }
            if (operator === 'not_equals') {
                return value.length === 0 || value[0] !== target;
            }
            return false;
        }

        const stringValue = (value || '').toString();

        if (operator === 'equals') {
            return stringValue === target;
        }
        if (operator === 'not_equals') {
            return stringValue !== target;
        }
        if (operator === 'contains') {
            return stringValue.indexOf(target) !== -1;
        }
        if (operator === 'greater') {
            return parseFloat(stringValue) > parseFloat(target);
        }
        if (operator === 'less') {
            return parseFloat(stringValue) < parseFloat(target);
        }

        return true;
    }

    _isCheckedValue(value) {
        if (Array.isArray(value)) {
            return value.length > 0;
        }
        return value !== '' && value !== null && value !== false;
    }

    _setFieldVisibility(field, visible) {
        if (!field) {
            return;
        }

        field.classList.toggle('is-hidden', !visible);
        field.classList.toggle('dn', !visible);
        field.setAttribute('aria-hidden', visible ? 'false' : 'true');

        const inputs = field.querySelectorAll('input, select, textarea');
        inputs.forEach((input) => {
            if (!visible) {
                if (input.required) {
                    input.dataset.wasRequired = '1';
                }
                input.required = false;
                input.disabled = true;
            } else {
                if (input.dataset.wasRequired === '1') {
                    input.required = true;
                }
                input.disabled = false;
                delete input.dataset.wasRequired;
            }
        });
    }

    _getFieldValue(name) {
        if (!name) {
            return '';
        }

        const selector = `[name="${this._escapeSelector(name)}"]`;
        const selectorArray = `[name="${this._escapeSelector(name)}[]"]`;
        let inputs = this._form.querySelectorAll(selector);
        if (!inputs.length) {
            inputs = this._form.querySelectorAll(selectorArray);
        }
        if (!inputs.length) {
            return '';
        }

        const first = inputs[0];
        const type = first.type;

        if (type === 'checkbox') {
            if (inputs.length > 1) {
                const values = [];
                inputs.forEach((input) => {
                    if (input.checked) {
                        values.push(input.value || '1');
                    }
                });
                return values;
            }
            return first.checked ? (first.value || '1') : '';
        }

        if (type === 'radio') {
            for (let i = 0; i < inputs.length; i++) {
                if (inputs[i].checked) {
                    return inputs[i].value;
                }
            }
            return '';
        }

        return first.value;
    }

    _escapeSelector(value) {
        if (window.CSS && CSS.escape) {
            return CSS.escape(value);
        }
        return value.replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
    }

    _initCaptcha() {
        this._captchaCanvas = this._form.querySelector('.ct-contact-form__captcha-canvas');
        this._captchaInput = this._form.querySelector('input[name="captcha_value"]');
        this._captchaToken = this._form.querySelector('input[name="captcha_token"]');
        this._captchaRefresh = this._form.querySelector('.ct-contact-form__captcha-refresh');

        if (!this._captchaCanvas || !this._captchaInput || !this._captchaToken) {
            return;
        }

        if (this._captchaRefresh) {
            this._captchaRefresh.addEventListener('click', () => this._refreshCaptcha());
        }

        const presetCode = this._captchaCanvas.getAttribute('data-captcha-code') || '';
        const presetToken = this._captchaToken ? (this._captchaToken.value || '') : '';
        if (presetCode && presetToken) {
            this._drawCaptcha(presetCode);
            return;
        }

        this._refreshCaptcha();
    }

    async _refreshCaptcha() {
        if (!this._captchaCanvas) {
            return;
        }

        try {
            const response = await fetch(this._restUrl + '/contact/captcha', {
                method: 'GET',
                headers: { 'X-WP-Nonce': this._nonce },
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (data && data.success && data.data) {
                this._captchaToken.value = data.data.token || '';
                const code = data.data.code || '';
                this._captchaCanvas.setAttribute('data-captcha-code', code);
                this._drawCaptcha(code);
            }
        } catch {
            /* ignore */
        }
    }

    _drawCaptcha(code) {
        const canvas = this._captchaCanvas;
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            return;
        }

        const width = canvas.width;
        const height = canvas.height;
        const scaleX = width / 160;
        const scaleY = height / 50;

        const off = document.createElement('canvas');
        off.width = width;
        off.height = height;
        const octx = off.getContext('2d');
        if (!octx) {
            return;
        }

        octx.clearRect(0, 0, width, height);
        octx.fillStyle = '#f3f4f6';
        octx.fillRect(0, 0, width, height);

        // Subtle background pattern
        for (let i = 0; i < 16; i++) {
            octx.strokeStyle = `rgba(0,0,0,${Math.random() * 0.08})`;
            octx.beginPath();
            const x = Math.random() * width;
            const y = Math.random() * height;
            octx.moveTo(x, y);
            octx.lineTo(x + (Math.random() * 40 - 20) * scaleX, y + (Math.random() * 40 - 20) * scaleY);
            octx.stroke();
        }

        for (let i = 0; i < 22; i++) {
            octx.fillStyle = `rgba(0,0,0,${Math.random() * 0.25})`;
            octx.beginPath();
            octx.arc(Math.random() * width, Math.random() * height, (Math.random() * 2 + 1) * Math.max(scaleX, scaleY), 0, Math.PI * 2);
            octx.fill();
        }

        for (let i = 0; i < 4; i++) {
            octx.strokeStyle = `rgba(0,0,0,${0.15 + Math.random() * 0.45})`;
            octx.beginPath();
            octx.moveTo(Math.random() * width, Math.random() * height);
            octx.lineTo(Math.random() * width, Math.random() * height);
            octx.stroke();
        }

        for (let i = 0; i < 2; i++) {
            octx.strokeStyle = `rgba(0,0,0,${Math.random() * 0.3})`;
            octx.beginPath();
            const y = Math.random() * height;
            octx.moveTo(0, y);
            octx.bezierCurveTo(width * 0.25, y + (Math.random() * 24 - 12) * scaleY, width * 0.6, y + (Math.random() * 24 - 12) * scaleY, width, y + (Math.random() * 24 - 12) * scaleY);
            octx.stroke();
        }

        octx.textBaseline = 'middle';
        octx.textAlign = 'center';
        const fonts = ['sans-serif', 'serif', 'monospace', 'cursive'];

        for (let i = 0; i < code.length; i++) {
            const char = code[i];
            const fontSize = (18 + Math.floor(Math.random() * 10)) * Math.max(scaleX, scaleY);
            const fontWeight = Math.random() > 0.5 ? '700' : '500';
            const fontFamily = fonts[Math.floor(Math.random() * fonts.length)];
            octx.font = `${fontWeight} ${fontSize}px ${fontFamily}`;
            const x = (16 + i * 22 + (Math.random() * 6 - 3)) * scaleX;
            const y = height / 2 + (Math.random() * 12 - 6) * scaleY;
            const rotation = (Math.random() * 1.9) - 0.95;
            octx.save();
            octx.translate(x, y);
            octx.rotate(rotation);
            // Skew and scale to distort each character.
            const skewX = (Math.random() * 0.8) - 0.4;
            const skewY = (Math.random() * 0.8) - 0.4;
            const scale = 0.85 + Math.random() * 0.3;
            octx.transform(1, skewY, skewX, 1, 0, 0);
            octx.scale(scale, scale);
            // Draw extra shapes around letters to make recognition harder.
            for (let s = 0; s < 1; s++) {
                const boxW = (16 + Math.random() * 16) * scaleX;
                const boxH = (16 + Math.random() * 16) * scaleY;
                const boxX = ((Math.random() * 12) - 6) * scaleX;
                const boxY = ((Math.random() * 12) - 6) * scaleY;
                octx.strokeStyle = `rgba(0,0,0,${0.2 + Math.random() * 0.25})`;
                octx.lineWidth = 1;
                octx.strokeRect(boxX, boxY, boxW, boxH);
                octx.beginPath();
                octx.arc(boxX + Math.random() * boxW, boxY + Math.random() * boxH, (4 + Math.random() * 7) * Math.max(scaleX, scaleY), 0, Math.PI * 2);
                octx.stroke();
            }
            octx.fillStyle = `hsl(${Math.random() * 360}, 60%, 30%)`;
            octx.fillText(char, 0, 0);
            for (let s = 0; s < 1; s++) {
                octx.beginPath();
                octx.strokeStyle = `rgba(0,0,0,${0.25 + Math.random() * 0.3})`;
                octx.moveTo((-12 + Math.random() * 24) * scaleX, (-12 + Math.random() * 24) * scaleY);
                octx.lineTo((12 + Math.random() * 24) * scaleX, (12 + Math.random() * 24) * scaleY);
                octx.stroke();
            }
            octx.restore();
        }

        // Distort the full canvas with wave displacement.
        const src = octx.getImageData(0, 0, width, height);
        const dst = octx.createImageData(width, height);
        const phaseX = Math.random() * Math.PI * 2;
        const phaseY = Math.random() * Math.PI * 2;
        const ampX = (4 + Math.random() * 3) * scaleX;
        const ampY = (3 + Math.random() * 2) * scaleY;
        for (let y = 0; y < height; y++) {
            const dx = Math.floor(Math.sin((y / height) * Math.PI * 2 + phaseX) * ampX);
            for (let x = 0; x < width; x++) {
                const dy = Math.floor(Math.cos((x / width) * Math.PI * 2 + phaseY) * ampY);
                const sx = x + dx;
                const sy = y + dy;
                const dstIndex = (y * width + x) * 4;
                if (sx >= 0 && sx < width && sy >= 0 && sy < height) {
                    const srcIndex = (sy * width + sx) * 4;
                    dst.data[dstIndex] = src.data[srcIndex];
                    dst.data[dstIndex + 1] = src.data[srcIndex + 1];
                    dst.data[dstIndex + 2] = src.data[srcIndex + 2];
                    dst.data[dstIndex + 3] = src.data[srcIndex + 3];
                } else {
                    dst.data[dstIndex] = 255;
                    dst.data[dstIndex + 1] = 255;
                    dst.data[dstIndex + 2] = 255;
                    dst.data[dstIndex + 3] = 0;
                }
            }
        }
        octx.putImageData(dst, 0, 0);

        ctx.clearRect(0, 0, width, height);
        ctx.drawImage(off, 0, 0);

        // Final occluding strokes on top.
        for (let i = 0; i < 2; i++) {
            ctx.strokeStyle = `rgba(0,0,0,${0.3 + Math.random() * 0.3})`;
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(Math.random() * width, Math.random() * height);
            ctx.lineTo(Math.random() * width, Math.random() * height);
            ctx.stroke();
        }
    }

    async _handleSubmit() {
        if (this._submitting) {
            return;
        }

        this._applyConditions();

        const errors = this._validate();
        if (errors.length > 0) {
            this._showMessage('error', errors[0]);
            return;
        }

        this._submitting = true;
        this._setLoading(true);
        this._clearMessages();

        try {
            const formData = new FormData(this._form);
            if (this._formId) {
                formData.set('form_id', this._formId);
            }

            const response = await fetch(this._restUrl + '/contact/submit', {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': this._nonce,
                },
                credentials: 'same-origin',
                body: formData,
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
                this._form.reset();
                this._clearFilePreviews();
                this._applyConditions();
                if (this._captchaEnabled) {
                    this._refreshCaptcha();
                }
            } else {
                this._showMessage('error', data.message || 'Failed to send message.');
                if (this._captchaEnabled) {
                    this._refreshCaptcha();
                }
            }
        } catch {
            this._setLoading(false);
            this._submitting = false;
            this._showMessage('error', 'A network error occurred. Please try again.');
        }
    }

    _validate() {
        const errors = [];
        const fields = this._form.querySelectorAll('.ct-contact-form__field');

        fields.forEach((field) => {
            if (field.classList.contains('is-hidden') || field.getAttribute('aria-hidden') === 'true') {
                return;
            }

            const required = field.dataset.required === '1';
            if (!required) {
                return;
            }

            const type = field.dataset.fieldType || '';
            const name = field.dataset.fieldName || '';

            if (type === 'file') {
                const input = field.querySelector('input[type="file"]');
                if (!input || !input.files || input.files.length === 0) {
                    errors.push('Please upload the required file.');
                } else {
                    const acceptRaw = input.getAttribute('accept') || field.getAttribute('data-accept') || '';
                    const allowedTokens = this._getAcceptTokens(acceptRaw);
                    if (!this._isFileAllowed(input.files[0], allowedTokens)) {
                        errors.push('File type not allowed. ' + this._formatAllowedTypes(acceptRaw));
                    }
                }
                return;
            }

            const value = this._getFieldValue(name);
            if (type === 'checkbox_group') {
                if (!Array.isArray(value) || value.length === 0) {
                    errors.push('Please fill in all required fields.');
                }
                return;
            }

            if (type === 'checkbox') {
                if (!value) {
                    errors.push('Please fill in all required fields.');
                }
                return;
            }

            if (!value || value.toString().trim() === '') {
                errors.push('Please fill in all required fields.');
                return;
            }

            if (type === 'email' && !EMAIL_REGEX.test(value.toString().trim())) {
                errors.push('Please enter a valid email address.');
            }
        });

        if (this._captchaEnabled && this._captchaInput) {
            if (!this._captchaInput.value.trim()) {
                errors.push('Please enter the captcha code.');
            }
        }

        return errors;
    }

    _getAcceptTokens(acceptRaw) {
        const accept = (acceptRaw || '').trim();
        if (!accept) {
            return [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                '.jpg',
                '.jpeg',
                '.png',
                '.gif',
                '.webp',
            ];
        }
        return accept.split(',').map((token) => token.trim().toLowerCase()).filter((token) => token.length > 0);
    }

    _isFileAllowed(file, acceptTokens) {
        if (!file) {
            return false;
        }
        const type = (file.type || '').toLowerCase();
        const name = (file.name || '').toLowerCase();
        const ext = name.indexOf('.') !== -1 ? name.split('.').pop() : '';

        for (let i = 0; i < acceptTokens.length; i++) {
            let token = acceptTokens[i];
            if (!token) {
                continue;
            }
            if (token === '*' || token === '*/*') {
                return true;
            }
            if (token.endsWith('/*')) {
                const prefix = token.slice(0, -1);
                if (type && type.indexOf(prefix) === 0) {
                    return true;
                }
                continue;
            }
            if (token.indexOf('/') !== -1) {
                if (type && type === token) {
                    return true;
                }
                continue;
            }
            if (token[0] === '.') {
                token = token.slice(1);
            }
            if (ext && token === ext) {
                return true;
            }
        }

        return false;
    }

    _formatAllowedTypes(acceptRaw) {
        const raw = (acceptRaw || '').trim();
        if (!raw) {
            return 'Allowed: JPG, JPEG, PNG, GIF, WEBP.';
        }
        const tokens = raw.split(',').map((token) => token.trim()).filter((token) => token.length > 0);
        if (tokens.length === 0) {
            return 'Allowed: JPG, JPEG, PNG, GIF, WEBP.';
        }
        return 'Allowed: ' + tokens.join(', ') + '.';
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
