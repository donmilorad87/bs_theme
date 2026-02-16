import Admin_Contact from './Admin_Contact.js';

export default class Admin_CT_Custom {

    constructor() {
        this.btnResetTimers = new WeakMap();

        this.initExportImport();

        this.initEmailSettings();
        this.initJwtAuth();

        this._contact = new Admin_Contact(this);
        this._contact.initContact();
    }

    setButtonState(btn, state) {
        if (!btn) {
            return;
        }

        const prevTimer = this.btnResetTimers.get(btn);
        if (prevTimer) {
            clearTimeout(prevTimer);
            this.btnResetTimers.delete(btn);
        }

        if (!btn.dataset.originalText) {
            btn.dataset.originalText = btn.textContent;
        }

        btn.classList.remove('ct-btn--loading', 'ct-btn--success', 'ct-btn--error');

        if (!state) {
            btn.textContent = btn.dataset.originalText;
            btn.disabled = false;
            return;
        }

        if (state === 'loading') {
            btn.textContent = '';
            btn.classList.add('ct-btn--loading');
            btn.disabled = true;
        } else if (state === 'success') {
            btn.textContent = '';
            btn.classList.add('ct-btn--success');
            btn.disabled = true;

            const timer = setTimeout(() => {
                this.setButtonState(btn, null);
            }, 2000);
            this.btnResetTimers.set(btn, timer);
        } else if (state === 'error') {
            btn.textContent = '';
            btn.classList.add('ct-btn--error');
            btn.disabled = true;

            const timer = setTimeout(() => {
                this.setButtonState(btn, null);
            }, 2000);
            this.btnResetTimers.set(btn, timer);
        }
    }

    /* ─── Email Settings ─── */

    initEmailSettings() {
        this.ecDebounceTimer = null;
        this.ecSaving = false;
        this.ecActiveFields = new Set();
        this.ecFieldResetTimers = new Map();

        this.initEmailConfigForm();
    }

    initEmailConfigForm() {
        const form = document.querySelector('#email_config_form');
        if (!form) {
            return;
        }

        const inputs = form.querySelectorAll('.emailConfigInputs');
        const MAX_INPUTS = 20;
        let count = 0;

        inputs.forEach((input) => {
            if (count >= MAX_INPUTS) {
                return;
            }
            count++;

            input.addEventListener('input', () => {
                this.addFieldToSet(input.closest('.ct-admin-field'), this.ecActiveFields, this.ecFieldResetTimers);
                this.debounceEmailConfigSave(form);
            });

            input.addEventListener('change', () => {
                this.addFieldToSet(input.closest('.ct-admin-field'), this.ecActiveFields, this.ecFieldResetTimers);
                this.debounceEmailConfigSave(form);
            });
        });
    }

    debounceEmailConfigSave(form) {
        if (this.ecDebounceTimer) {
            clearTimeout(this.ecDebounceTimer);
        }

        this.ecDebounceTimer = setTimeout(() => {
            this.saveEmailConfig(form);
        }, 500);
    }

    async saveEmailConfig(form) {
        if (this.ecSaving) {
            return;
        }

        this.ecSaving = true;
        const fieldsToUpdate = new Set(this.ecActiveFields);
        const nonce = form.elements['admin_save_email_config_nonce'].value;

        const payload = JSON.stringify({
            host: (form.elements['ec_host'] || {}).value || '',
            port: parseInt((form.elements['ec_port'] || {}).value, 10) || 587,
            username: (form.elements['ec_username'] || {}).value || '',
            password: (form.elements['ec_password'] || {}).value || '',
            encryption: (form.elements['ec_encryption'] || {}).value || 'tls',
            from_email: (form.elements['ec_from_email'] || {}).value || '',
            from_name: (form.elements['ec_from_name'] || {}).value || '',
        });

        const data = new URLSearchParams({
            nonce: nonce,
            action: 'admin_save_email_config',
            input: payload,
        });

        try {
            const response = await fetch(ajaxurl, { method: 'POST', body: data });
            const result = await response.json();

            if (result.success) {
                this.setFieldsState('saved', fieldsToUpdate, this.ecActiveFields, this.ecFieldResetTimers);
                this.showNotice('Email configuration saved.', 'success');

                const pwField = form.elements['ec_password'];
                if (pwField) {
                    pwField.value = '';
                }
            } else {
                this.setFieldsState('error', fieldsToUpdate, this.ecActiveFields, this.ecFieldResetTimers);
                this.showNotice('Error saving email configuration.', 'error');
            }
        } catch {
            this.setFieldsState('error', fieldsToUpdate, this.ecActiveFields, this.ecFieldResetTimers);
            this.showNotice('Network error. Please try again.', 'error');
        } finally {
            this.ecSaving = false;
            if (this.ecActiveFields.size > 0) {
                this.debounceEmailConfigSave(form);
            }
        }
    }

    /* ─── JWT Auth ─── */

    initJwtAuth() {
        this.jwtDebounceTimer = null;
        this.jwtSaving = false;
        this.jwtActiveFields = new Set();
        this.jwtFieldResetTimers = new Map();

        const form = document.querySelector('#jwt_auth_form');
        if (!form) {
            return;
        }

        const inputs = form.querySelectorAll('.jwtAuthInputs');
        const MAX_INPUTS = 10;
        let count = 0;

        inputs.forEach((input) => {
            if (count >= MAX_INPUTS) {
                return;
            }
            count++;

            input.addEventListener('input', () => {
                this.addFieldToSet(input.closest('.ct-admin-field'), this.jwtActiveFields, this.jwtFieldResetTimers);
                this.debounceJwtSave(form);
            });

            input.addEventListener('change', () => {
                this.addFieldToSet(input.closest('.ct-admin-field'), this.jwtActiveFields, this.jwtFieldResetTimers);
                this.debounceJwtSave(form);
            });
        });

        const generateBtn = document.querySelector('#generate_jwt_secret_btn');
        if (generateBtn) {
            generateBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const secretInput = document.querySelector('#jwt_secret');
                if (secretInput) {
                    const bytes = new Uint8Array(32);
                    crypto.getRandomValues(bytes);
                    const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
                    secretInput.value = hex;
                    secretInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        }
    }

    debounceJwtSave(form) {
        if (this.jwtDebounceTimer) {
            clearTimeout(this.jwtDebounceTimer);
        }

        this.jwtDebounceTimer = setTimeout(() => {
            this.saveJwtAuth(form);
        }, 500);
    }

    async saveJwtAuth(form) {
        if (this.jwtSaving) {
            return;
        }

        this.jwtSaving = true;
        const fieldsToUpdate = new Set(this.jwtActiveFields);
        const nonce = form.elements['admin_save_jwt_auth_nonce'].value;

        const payload = JSON.stringify({
            secret: (form.elements['jwt_secret'] || {}).value || '',
            expiration_hours: parseInt((form.elements['jwt_expiration_hours'] || {}).value, 10) || 24,
        });

        const data = new URLSearchParams({
            nonce: nonce,
            action: 'admin_save_jwt_auth',
            input: payload,
        });

        try {
            const response = await fetch(ajaxurl, { method: 'POST', body: data });
            const result = await response.json();

            if (result.success) {
                this.setFieldsState('saved', fieldsToUpdate, this.jwtActiveFields, this.jwtFieldResetTimers);
                this.showNotice('JWT auth settings saved.', 'success');
            } else {
                this.setFieldsState('error', fieldsToUpdate, this.jwtActiveFields, this.jwtFieldResetTimers);
                this.showNotice('Error saving JWT auth settings.', 'error');
            }
        } catch {
            this.setFieldsState('error', fieldsToUpdate, this.jwtActiveFields, this.jwtFieldResetTimers);
            this.showNotice('Network error. Please try again.', 'error');
        } finally {
            this.jwtSaving = false;
            if (this.jwtActiveFields.size > 0) {
                this.debounceJwtSave(form);
            }
        }
    }

    /* ─── Shared field state helpers ─── */

    addFieldToSet(field, activeSet, resetTimers) {
        if (!field) {
            return;
        }

        const prevTimer = resetTimers.get(field);
        if (prevTimer) {
            clearTimeout(prevTimer);
            resetTimers.delete(field);
        }

        field.classList.remove('ct-field--saved', 'ct-field--error');
        field.classList.add('ct-field--saving');
        activeSet.add(field);
    }

    setFieldsState(state, fields, activeSet, resetTimers) {
        const targetFields = fields || activeSet;

        for (const field of targetFields) {
            const prevTimer = resetTimers.get(field);
            if (prevTimer) {
                clearTimeout(prevTimer);
                resetTimers.delete(field);
            }

            field.classList.remove('ct-field--saving', 'ct-field--saved', 'ct-field--error');

            if (state) {
                field.classList.add('ct-field--' + state);

                if (state === 'saved' || state === 'error') {
                    const timer = setTimeout(() => {
                        field.classList.remove('ct-field--saved', 'ct-field--error');
                        resetTimers.delete(field);
                    }, 2000);
                    resetTimers.set(field, timer);
                }
            }

            activeSet.delete(field);
        }
    }

    initExportImport() {
        const exportBtn = document.querySelector('#export_settings_btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.exportSettings();
            });
        }

        const fileInput = document.querySelector('#import_settings_file');
        const importBtn = document.querySelector('#import_settings_btn');

        if (fileInput && importBtn) {
            fileInput.addEventListener('change', () => {
                const file = fileInput.files[0];
                const infoEl = document.querySelector('#import_file_info');
                const nameEl = document.querySelector('#import_file_name');

                if (file && file.name.endsWith('.json')) {
                    importBtn.disabled = false;
                    if (infoEl && nameEl) {
                        nameEl.textContent = file.name;
                        infoEl.style.display = 'block';
                    }
                } else {
                    importBtn.disabled = true;
                    if (infoEl) {
                        infoEl.style.display = 'none';
                    }
                }
            });

            importBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.importSettings();
            });
        }
    }

    async exportSettings() {
        const form = document.querySelector('#export_settings_form');
        if (!form) {
            return;
        }

        const exportBtn = document.querySelector('#export_settings_btn');
        const nonce = form.elements['admin_export_settings_nonce'].value;

        const data = new URLSearchParams({
            nonce: nonce,
            action: 'admin_export_settings',
            input: '1',
        });

        this.setButtonState(exportBtn, 'loading');

        try {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                body: data,
            });

            const result = await response.json();

            if (result.success) {
                const json = JSON.stringify(result.data, null, 2);
                const blob = new Blob([json], { type: 'application/json' });
                const url = URL.createObjectURL(blob);

                const link = document.createElement('a');
                link.href = url;
                link.download = 'ct-custom-settings-' + this.getDateStamp() + '.json';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                this.setButtonState(exportBtn, 'success');
                this.showNotice('Settings exported successfully.', 'success');
            } else {
                this.setButtonState(exportBtn, 'error');
                this.showNotice('Error exporting settings.', 'error');
            }
        } catch {
            this.setButtonState(exportBtn, 'error');
            this.showNotice('Network error. Please try again.', 'error');
        }
    }

    importSettings() {
        const form = document.querySelector('#import_settings_form');
        const fileInput = document.querySelector('#import_settings_file');
        const importBtn = document.querySelector('#import_settings_btn');

        if (!form || !fileInput || !fileInput.files[0]) {
            return;
        }

        const nonce = form.elements['admin_import_settings_nonce'].value;
        const file = fileInput.files[0];
        const reader = new FileReader();

        reader.onload = async (e) => {
            const content = e.target.result;

            let parsed;
            try {
                parsed = JSON.parse(content);
            } catch (err) {
                this.showNotice('Invalid JSON file.', 'error');
                return;
            }

            if (!parsed.theme || parsed.theme !== 'ct-custom') {
                this.showNotice('This file is not a valid CT Custom theme export.', 'error');
                return;
            }

            const data = new URLSearchParams({
                nonce: nonce,
                action: 'admin_import_settings',
                input: content,
            });

            this.setButtonState(importBtn, 'loading');

            try {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    body: data,
                });

                const result = await response.json();

                if (result.success) {
                    this.setButtonState(importBtn, 'success');
                    this.showNotice('Settings imported successfully. Reloading page...', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    const msg = result.data && result.data.message ? result.data.message : 'Error importing settings.';
                    this.setButtonState(importBtn, 'error');
                    this.showNotice(msg, 'error');
                }
            } catch {
                this.setButtonState(importBtn, 'error');
                this.showNotice('Network error. Please try again.', 'error');
            }
        };

        reader.readAsText(file);
    }

    getDateStamp() {
        const d = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    showNotice(message, type) {
        const existing = document.querySelector('.ct-admin-notice');
        if (existing) {
            existing.remove();
        }

        const notice = document.createElement('div');
        notice.className = 'ct-admin-notice ct-admin-notice--' + type;
        notice.textContent = message;
        document.querySelector('.wrap').prepend(notice);

        setTimeout(() => {
            notice.remove();
        }, 4000);
    }
}
