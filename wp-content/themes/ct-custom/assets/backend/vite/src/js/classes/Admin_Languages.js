/**
 * Admin Languages management.
 *
 * Handles language CRUD, translation editor, and page translation
 * within the admin Languages accordion tab.
 *
 * @package CT_Custom
 */
export default class Admin_Languages {

    constructor() {
        this.section = document.querySelector('.ct-lang-section');

        if (!this.section) {
            return;
        }

        this.nonce   = this.section.dataset.nonce || '';
        this.ajaxUrl = this.section.dataset.ajaxUrl || '';

        this.currentTransKey    = '';
        this.allKeys            = [];
        this.debounceTimer      = null;
        this.currentKeyIsPlural = false;

        this.initLanguages();
        this.initTranslationEditor();
        this.initPageTranslations();
    }

    /* ═══ A) Language Management ═══ */

    initLanguages() {
        const form = document.getElementById('ct_add_language_form');
        if (form) {
            form.addEventListener('submit', (e) => this.handleAddLanguage(e));
            this._bindLangFormValidation(form);
        }

        const flagBtn = document.getElementById('ct_lang_flag_btn');
        if (flagBtn) {
            flagBtn.addEventListener('click', () => this.openMediaPicker());
        }

        const flagRemove = document.getElementById('ct_lang_flag_remove');
        if (flagRemove) {
            flagRemove.addEventListener('click', () => this.clearFlag());
        }

        /* Edit modal bindings */
        const editForm = document.getElementById('ct_edit_language_form');
        if (editForm) {
            editForm.addEventListener('submit', (e) => this.handleUpdateLanguage(e));
        }

        const editCancel = document.getElementById('ct_edit_cancel');
        if (editCancel) {
            editCancel.addEventListener('click', () => this.closeEditModal());
        }

        const editBackdrop = document.querySelector('.ct-lang-edit-modal__backdrop');
        if (editBackdrop) {
            editBackdrop.addEventListener('click', () => this.closeEditModal());
        }

        const editFlagBtn = document.getElementById('ct_edit_flag_btn');
        if (editFlagBtn) {
            editFlagBtn.addEventListener('click', () => this.openEditMediaPicker());
        }

        const editFlagRemove = document.getElementById('ct_edit_flag_remove');
        if (editFlagRemove) {
            editFlagRemove.addEventListener('click', () => this.clearEditFlag());
        }

        this.bindTableActions();
    }

    bindTableActions() {
        const table = document.getElementById('ct_language_table');
        if (!table) { return; }

        table.addEventListener('click', (e) => {
            const target = e.target;
            const row    = target.closest('tr[data-iso2]');
            if (!row) { return; }

            const iso2 = row.dataset.iso2;

            if (target.classList.contains('ct-lang-edit')) {
                this.openEditModal(target);
            } else if (target.classList.contains('ct-lang-set-default')) {
                this.setDefaultLanguage(iso2);
            } else if (target.classList.contains('ct-lang-remove')) {
                this.removeLanguage(iso2);
            }
        });

        table.addEventListener('change', (e) => {
            if (e.target.classList.contains('ct-lang-enable-toggle')) {
                const row  = e.target.closest('tr[data-iso2]');
                const iso2 = row ? row.dataset.iso2 : '';
                if (iso2) {
                    this.toggleLanguageEnabled(iso2, e.target.checked);
                }
            }
        });
    }

    _bindLangFormValidation(form) {
        const ids = ['ct_lang_iso2', 'ct_lang_iso3', 'ct_lang_native_name', 'ct_lang_locale'];
        const maxFields = 4;
        let count = 0;

        for (const id of ids) {
            if (count >= maxFields) { break; }
            count++;

            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', () => this._validateLangForm());
            }
        }
    }

    _validateLangForm() {
        const iso2Input = document.getElementById('ct_lang_iso2');
        const iso3Input = document.getElementById('ct_lang_iso3');
        const nameInput = document.getElementById('ct_lang_native_name');
        const localeInput = document.getElementById('ct_lang_locale');
        const submitBtn = document.getElementById('ct_lang_submit_btn');

        const iso2 = iso2Input ? iso2Input.value.trim() : '';
        const iso3 = iso3Input ? iso3Input.value.trim() : '';
        const name = nameInput ? nameInput.value.trim() : '';
        const locale = localeInput ? localeInput.value.trim() : '';

        let valid = true;

        /* ISO 639-1: required, exactly 2 lowercase letters */
        const iso2Field = iso2Input ? iso2Input.closest('.ct-lang-add-form__field') : null;
        const iso2Error = document.getElementById('ct_lang_iso2_error');
        if (!iso2) {
            if (iso2Field) { iso2Field.classList.add('ct-field--invalid'); }
            if (iso2Error) { iso2Error.textContent = 'ISO code is required.'; }
            valid = false;
        } else if (!/^[a-z]{2}$/.test(iso2)) {
            if (iso2Field) { iso2Field.classList.add('ct-field--invalid'); }
            if (iso2Error) { iso2Error.textContent = 'Must be exactly 2 lowercase letters.'; }
            valid = false;
        } else {
            if (iso2Field) { iso2Field.classList.remove('ct-field--invalid'); }
            if (iso2Error) { iso2Error.textContent = ''; }
        }

        /* ISO 639-2: optional, but if provided must be 3 lowercase letters */
        const iso3Field = iso3Input ? iso3Input.closest('.ct-lang-add-form__field') : null;
        const iso3Error = document.getElementById('ct_lang_iso3_error');
        if (iso3 && !/^[a-z]{3}$/.test(iso3)) {
            if (iso3Field) { iso3Field.classList.add('ct-field--invalid'); }
            if (iso3Error) { iso3Error.textContent = 'Must be exactly 3 lowercase letters.'; }
            valid = false;
        } else {
            if (iso3Field) { iso3Field.classList.remove('ct-field--invalid'); }
            if (iso3Error) { iso3Error.textContent = ''; }
        }

        /* Native Name: required */
        const nameField = nameInput ? nameInput.closest('.ct-lang-add-form__field') : null;
        const nameError = document.getElementById('ct_lang_native_name_error');
        if (!name) {
            if (nameField) { nameField.classList.add('ct-field--invalid'); }
            if (nameError) { nameError.textContent = 'Native name is required.'; }
            valid = false;
        } else {
            if (nameField) { nameField.classList.remove('ct-field--invalid'); }
            if (nameError) { nameError.textContent = ''; }
        }

        /* Locale: optional, but if provided must match xx_XX pattern */
        const localeField = localeInput ? localeInput.closest('.ct-lang-add-form__field') : null;
        const localeError = document.getElementById('ct_lang_locale_error');
        if (locale && !/^[a-z]{2}_[A-Z]{2}$/.test(locale)) {
            if (localeField) { localeField.classList.add('ct-field--invalid'); }
            if (localeError) { localeError.textContent = 'Must match format: xx_XX (e.g. fr_FR).'; }
            valid = false;
        } else {
            if (localeField) { localeField.classList.remove('ct-field--invalid'); }
            if (localeError) { localeError.textContent = ''; }
        }

        if (submitBtn) { submitBtn.disabled = !valid; }

        return valid;
    }

    async handleAddLanguage(e) {
        e.preventDefault();

        if (!this._validateLangForm()) { return; }

        const iso2       = document.getElementById('ct_lang_iso2').value.trim().toLowerCase();
        const iso3       = document.getElementById('ct_lang_iso3').value.trim().toLowerCase();
        const nativeName = document.getElementById('ct_lang_native_name').value.trim();
        const locale     = document.getElementById('ct_lang_locale').value.trim();
        const flag       = document.getElementById('ct_lang_flag').value.trim();

        const data = { iso2, iso3, native_name: nativeName, locale, flag };

        const result = await this.ajaxPost('admin_add_language', JSON.stringify(data));

        if (result.success) {
            window.ctToast.show(result.data.message || 'Language added.', 'success');
            document.getElementById('ct_add_language_form').reset();
            this.clearFlag();

            const submitBtn = document.getElementById('ct_lang_submit_btn');
            if (submitBtn) { submitBtn.disabled = true; }

            const fields = document.querySelectorAll('#ct_add_language_form .ct-field--invalid');
            const maxF = 10;
            let fc = 0;
            for (const f of fields) {
                if (fc >= maxF) { break; }
                fc++;
                f.classList.remove('ct-field--invalid');
            }

            if (result.data.language) {
                this.appendLanguageRow(result.data.language);
            }
        } else {
            const type = result.data?.type || 'error';
            window.ctToast.show(result.data?.message || 'Error adding language.', type);
        }
    }

    async setDefaultLanguage(iso2) {
        const formData = new FormData();
        formData.append('action', 'admin_set_default_language');
        formData.append('nonce', this.nonce);
        formData.append('iso2', iso2);

        const result = await this.ajaxPostForm(formData);

        if (result.success) {
            window.ctToast.show(result.data.message || 'Default language updated.', 'success');
            this.updateDefaultInTable(iso2);
        } else {
            const type = result.data?.type || 'error';
            window.ctToast.show(result.data?.message || 'Failed to set default language.', type);
        }
    }

    removeLanguage(iso2) {
        this.showConfirmModal(
            'Remove Language',
            `Are you sure you want to remove the language <strong>${this.escapeHtml(iso2.toUpperCase())}</strong>?`,
            'Remove',
            () => this.showRemovePagesModal(iso2)
        );
    }

    /** @private */
    showRemovePagesModal(iso2) {
        this.showConfirmModal(
            'Remove Language',
            `<p>The language <strong>${this.escapeHtml(iso2.toUpperCase())}</strong>, its pages, menus and widgets will be removed.</p>`
            + '<label class="ct-confirm-modal__checkbox-label">'
            + '<input type="checkbox" id="ct_remove_force_delete" class="ct-confirm-modal__checkbox" checked>'
            + '<span>Delete pages permanently (skip trash)</span>'
            + '</label>'
            + '<label class="ct-confirm-modal__checkbox-label">'
            + '<input type="checkbox" id="ct_remove_menus" class="ct-confirm-modal__checkbox" checked>'
            + '<span>Remove menus</span>'
            + '</label>'
            + '<label class="ct-confirm-modal__checkbox-label">'
            + '<input type="checkbox" id="ct_remove_widgets" class="ct-confirm-modal__checkbox" checked>'
            + '<span>Remove widgets</span>'
            + '</label>',
            'Remove Language',
            () => {
                const forceDelete   = !!document.getElementById('ct_remove_force_delete')?.checked;
                const removeMenus   = !!document.getElementById('ct_remove_menus')?.checked;
                const removeWidgets = !!document.getElementById('ct_remove_widgets')?.checked;
                this.executeRemoveLanguage(iso2, forceDelete, removeMenus, removeWidgets);
            }
        );
    }

    /** @private */
    async executeRemoveLanguage(iso2, forceDelete, removeMenus = true, removeWidgets = true) {
        const formData = new FormData();
        formData.append('action', 'admin_remove_language');
        formData.append('nonce', this.nonce);
        formData.append('iso2', iso2);
        formData.append('force_delete', forceDelete ? 'true' : 'false');
        formData.append('remove_menus', removeMenus ? 'true' : 'false');
        formData.append('remove_widgets', removeWidgets ? 'true' : 'false');

        const result = await this.ajaxPostForm(formData);

        if (result.success) {
            window.ctToast.show(result.data.message || 'Language removed.', 'success');
            this.removeLanguageRow(iso2);
        } else {
            const type = result.data?.type || 'error';
            window.ctToast.show(result.data?.message || 'Failed to remove language.', type);
        }
    }

    /**
     * Show a reusable confirmation modal.
     *
     * @param {string}   title         Modal heading.
     * @param {string}   bodyHtml      Inner HTML for the body.
     * @param {string}   confirmLabel  Primary button text.
     * @param {Function} onConfirm     Called when primary button is clicked.
     * @param {string}   [altLabel]    Optional secondary action button text.
     * @param {Function} [onAlt]       Called when secondary action is clicked.
     */
    showConfirmModal(title, bodyHtml, confirmLabel, onConfirm, altLabel = '', onAlt = null) {
        this.closeConfirmModal();

        const backdrop = document.createElement('div');
        backdrop.className = 'ct-confirm-modal__backdrop';

        const panel = document.createElement('div');
        panel.className = 'ct-confirm-modal__panel';

        const heading = document.createElement('h3');
        heading.className = 'ct-confirm-modal__title';
        heading.textContent = title;

        const body = document.createElement('div');
        body.className = 'ct-confirm-modal__body';
        body.innerHTML = bodyHtml;

        const actions = document.createElement('div');
        actions.className = 'ct-confirm-modal__actions';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'button ct-confirm-modal__cancel';
        cancelBtn.textContent = 'Cancel';

        const confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'button button-primary ct-confirm-modal__confirm';
        confirmBtn.textContent = confirmLabel;

        actions.appendChild(cancelBtn);

        if (altLabel && onAlt) {
            const altBtn = document.createElement('button');
            altBtn.type = 'button';
            altBtn.className = 'button ct-confirm-modal__alt';
            altBtn.textContent = altLabel;
            actions.appendChild(altBtn);
            altBtn.addEventListener('click', () => { onAlt(); close(); });
        }

        actions.appendChild(confirmBtn);

        panel.appendChild(heading);
        panel.appendChild(body);
        panel.appendChild(actions);

        const modal = document.createElement('div');
        modal.className = 'ct-confirm-modal';
        modal.id = 'ct_confirm_modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.appendChild(backdrop);
        modal.appendChild(panel);

        const close = () => modal.remove();
        cancelBtn.addEventListener('click', close);
        backdrop.addEventListener('click', close);
        confirmBtn.addEventListener('click', () => { onConfirm(); close(); });

        document.body.appendChild(modal);
        confirmBtn.focus();
    }

    closeConfirmModal() {
        const modal = document.getElementById('ct_confirm_modal');
        if (modal) { modal.remove(); }
    }

    async toggleLanguageEnabled(iso2, enabled) {
        const mgr = await this.ajaxPost('admin_save_languages',
            JSON.stringify(this.getUpdatedLanguagesArray(iso2, 'enabled', enabled))
        );

        if (!mgr.success) {
            window.location.reload();
        }
    }

    getUpdatedLanguagesArray(iso2, field, value) {
        const rows = document.querySelectorAll('#ct_language_table tbody tr[data-iso2]');
        const langs = [];
        const max = 50;
        let count = 0;

        for (const row of rows) {
            if (count >= max) { break; }
            count++;

            const currentIso = row.dataset.iso2;
            const cells = row.querySelectorAll('td');
            const lang = {
                iso2: currentIso,
                native_name: cells[2] ? cells[2].textContent.trim() : '',
                enabled: currentIso === iso2 ? value : !!row.querySelector('.ct-lang-enable-toggle:checked'),
            };
            langs.push(lang);
        }

        return langs;
    }

    openMediaPicker() {
        if (!wp || !wp.media) { return; }

        const frame = wp.media({
            title: 'Choose Flag',
            multiple: false,
            library: { type: 'image' },
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('ct_lang_flag').value = attachment.url || '';

            const preview = document.getElementById('ct_lang_flag_preview');
            if (preview) {
                preview.src = attachment.url || '';
                preview.style.display = attachment.url ? 'block' : 'none';
            }

            const removeBtn = document.getElementById('ct_lang_flag_remove');
            if (removeBtn) {
                removeBtn.style.display = attachment.url ? 'inline-block' : 'none';
            }
        });

        frame.open();
    }

    clearFlag() {
        document.getElementById('ct_lang_flag').value = '';
        const preview = document.getElementById('ct_lang_flag_preview');
        if (preview) { preview.style.display = 'none'; }
        const removeBtn = document.getElementById('ct_lang_flag_remove');
        if (removeBtn) { removeBtn.style.display = 'none'; }
    }

    /* ═══ Table DOM Helpers ═══ */

    appendLanguageRow(lang) {
        const tbody = document.querySelector('#ct_language_table tbody');
        if (!tbody) { return; }

        const row = this.buildLanguageRow(lang);
        tbody.appendChild(row);
    }

    removeLanguageRow(iso2) {
        const row = document.querySelector(`#ct_language_table tbody tr[data-iso2="${iso2}"]`);
        if (row) { row.remove(); }
    }

    updateDefaultInTable(newDefaultIso2) {
        const rows = document.querySelectorAll('#ct_language_table tbody tr[data-iso2]');
        const max = 50;
        let count = 0;

        for (const row of rows) {
            if (count >= max) { break; }
            count++;

            const iso2       = row.dataset.iso2;
            const defaultTd  = row.querySelectorAll('td')[4];
            const enabledTd  = row.querySelectorAll('td')[5];
            const actionsTd  = row.querySelectorAll('td')[6];

            if (!defaultTd || !enabledTd || !actionsTd) { continue; }

            if (iso2 === newDefaultIso2) {
                defaultTd.innerHTML = '<span class="ct-lang-table__badge ct-lang-table__badge--default">Default</span>';

                const toggle = enabledTd.querySelector('.ct-lang-enable-toggle');
                if (toggle) {
                    toggle.checked = true;
                    toggle.disabled = true;
                }

                const removeBtn = actionsTd.querySelector('.ct-lang-remove');
                if (removeBtn) { removeBtn.remove(); }
            } else {
                const badge = defaultTd.querySelector('.ct-lang-table__badge--default');
                if (badge) {
                    defaultTd.innerHTML = '<button type="button" class="button button-small ct-lang-set-default">Set Default</button>';
                }

                const toggle = enabledTd.querySelector('.ct-lang-enable-toggle');
                if (toggle) { toggle.disabled = false; }

                const editBtn = actionsTd.querySelector('.ct-lang-edit');
                if (editBtn && !actionsTd.querySelector('.ct-lang-remove')) {
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'button button-small ct-lang-remove';
                    removeBtn.title = 'Remove';
                    removeBtn.textContent = '\u00D7';
                    actionsTd.appendChild(removeBtn);
                }
            }
        }
    }

    updateLanguageRow(iso2, data) {
        const row = document.querySelector(`#ct_language_table tbody tr[data-iso2="${iso2}"]`);
        if (!row) { return; }

        const cells = row.querySelectorAll('td');

        if (cells[0] && data.flag !== undefined) {
            if (data.flag) {
                cells[0].innerHTML = `<img src="${this.escapeAttr(data.flag)}" alt="" class="ct-lang-table__flag">`;
            } else {
                cells[0].innerHTML = `<span class="ct-lang-table__flag-placeholder">${this.escapeHtml(iso2.toUpperCase())}</span>`;
            }
        }

        if (cells[2] && data.native_name) {
            cells[2].textContent = data.native_name;
        }

        if (cells[3] && data.locale !== undefined) {
            cells[3].textContent = data.locale || '';
        }

        const editBtn = row.querySelector('.ct-lang-edit');
        if (editBtn) {
            editBtn.dataset.iso2       = iso2;
            editBtn.dataset.iso3       = data.iso3 || '';
            editBtn.dataset.nativeName = data.native_name || '';
            editBtn.dataset.locale     = data.locale || '';
            editBtn.dataset.flag       = data.flag || '';
        }
    }

    buildLanguageRow(lang) {
        const row = document.createElement('tr');
        row.dataset.iso2 = lang.iso2;

        const iso2       = lang.iso2 || '';
        const nativeName = lang.native_name || '';
        const flag       = lang.flag || '';
        const locales    = Array.isArray(lang.locales) ? lang.locales.join(', ') : '';
        const isDefault  = !!lang.is_default;
        const isEnabled  = lang.enabled !== false;
        const iso3       = lang.iso3 || '';
        const locale     = Array.isArray(lang.locales) && lang.locales.length > 0 ? lang.locales[0] : '';

        /* Flag cell */
        const flagTd = document.createElement('td');
        if (flag) {
            const img = document.createElement('img');
            img.src = flag;
            img.alt = '';
            img.className = 'ct-lang-table__flag';
            flagTd.appendChild(img);
        } else {
            const span = document.createElement('span');
            span.className = 'ct-lang-table__flag-placeholder';
            span.textContent = iso2.toUpperCase();
            flagTd.appendChild(span);
        }

        /* Code cell */
        const codeTd = document.createElement('td');
        const code = document.createElement('code');
        code.textContent = iso2;
        codeTd.appendChild(code);

        /* Name cell */
        const nameTd = document.createElement('td');
        nameTd.textContent = nativeName;

        /* Locales cell */
        const localeTd = document.createElement('td');
        localeTd.textContent = locales;

        /* Default cell */
        const defaultTd = document.createElement('td');
        if (isDefault) {
            defaultTd.innerHTML = '<span class="ct-lang-table__badge ct-lang-table__badge--default">Default</span>';
        } else {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'button button-small ct-lang-set-default';
            btn.textContent = 'Set Default';
            defaultTd.appendChild(btn);
        }

        /* Enabled cell */
        const enabledTd = document.createElement('td');
        const label = document.createElement('label');
        label.className = 'ct-lang-toggle';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'ct-lang-toggle__input ct-lang-enable-toggle';
        checkbox.checked = isEnabled;
        if (isDefault) { checkbox.disabled = true; }
        const slider = document.createElement('span');
        slider.className = 'ct-lang-toggle__slider';
        label.appendChild(checkbox);
        label.appendChild(slider);
        enabledTd.appendChild(label);

        /* Actions cell */
        const actionsTd = document.createElement('td');
        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'button button-small ct-lang-edit';
        editBtn.dataset.iso2       = iso2;
        editBtn.dataset.iso3       = iso3;
        editBtn.dataset.nativeName = nativeName;
        editBtn.dataset.locale     = locale;
        editBtn.dataset.flag       = flag;
        editBtn.title = 'Edit';
        editBtn.textContent = 'Edit';
        actionsTd.appendChild(editBtn);

        if (!isDefault) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'button button-small ct-lang-remove';
            removeBtn.title = 'Remove';
            removeBtn.textContent = '\u00D7';
            actionsTd.appendChild(removeBtn);
        }

        row.appendChild(flagTd);
        row.appendChild(codeTd);
        row.appendChild(nameTd);
        row.appendChild(localeTd);
        row.appendChild(defaultTd);
        row.appendChild(enabledTd);
        row.appendChild(actionsTd);

        return row;
    }

    /* ═══ Edit Language Modal ═══ */

    openEditModal(button) {
        const modal = document.getElementById('ct_lang_edit_modal');
        if (!modal) { return; }

        document.getElementById('ct_edit_iso2').value = button.dataset.iso2 || '';
        document.getElementById('ct_edit_iso2_badge').textContent = (button.dataset.iso2 || '').toUpperCase();
        document.getElementById('ct_edit_native_name').value = button.dataset.nativeName || '';
        document.getElementById('ct_edit_iso3').value = button.dataset.iso3 || '';
        document.getElementById('ct_edit_locale').value = button.dataset.locale || '';
        document.getElementById('ct_edit_flag').value = button.dataset.flag || '';

        const preview = document.getElementById('ct_edit_flag_preview');
        const removeBtn = document.getElementById('ct_edit_flag_remove');

        if (button.dataset.flag) {
            preview.src = button.dataset.flag;
            preview.style.display = 'block';
            removeBtn.style.display = 'inline-block';
        } else {
            preview.style.display = 'none';
            removeBtn.style.display = 'none';
        }

        const status = document.getElementById('ct_edit_status');
        if (status) { status.textContent = ''; }

        modal.style.display = 'flex';
    }

    closeEditModal() {
        const modal = document.getElementById('ct_lang_edit_modal');
        if (modal) { modal.style.display = 'none'; }
    }

    async handleUpdateLanguage(e) {
        e.preventDefault();

        const iso2       = document.getElementById('ct_edit_iso2').value.trim();
        const nativeName = document.getElementById('ct_edit_native_name').value.trim();
        const iso3       = document.getElementById('ct_edit_iso3').value.trim().toLowerCase();
        const locale     = document.getElementById('ct_edit_locale').value.trim();
        const flag       = document.getElementById('ct_edit_flag').value.trim();

        if (!iso2 || !nativeName) { return; }

        const data = { iso2, iso3, native_name: nativeName, locale, flag };
        const result = await this.ajaxPost('admin_update_language', JSON.stringify(data));

        if (result.success) {
            window.ctToast.show(result.data.message || 'Language updated.', 'success');
            this.updateLanguageRow(iso2, { iso3, native_name: nativeName, locale, flag });
            this.closeEditModal();
        } else {
            const type = result.data?.type || 'error';
            window.ctToast.show(result.data?.message || 'Failed to update language.', type);
        }
    }

    openEditMediaPicker() {
        if (!wp || !wp.media) { return; }

        const frame = wp.media({
            title: 'Choose Flag',
            multiple: false,
            library: { type: 'image' },
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('ct_edit_flag').value = attachment.url || '';

            const preview = document.getElementById('ct_edit_flag_preview');
            if (preview) {
                preview.src = attachment.url || '';
                preview.style.display = attachment.url ? 'block' : 'none';
            }

            const removeBtn = document.getElementById('ct_edit_flag_remove');
            if (removeBtn) {
                removeBtn.style.display = attachment.url ? 'inline-block' : 'none';
            }
        });

        frame.open();
    }

    clearEditFlag() {
        document.getElementById('ct_edit_flag').value = '';
        const preview = document.getElementById('ct_edit_flag_preview');
        if (preview) { preview.style.display = 'none'; }
        const removeBtn = document.getElementById('ct_edit_flag_remove');
        if (removeBtn) { removeBtn.style.display = 'none'; }
    }

    /* ═══ B) Translation Editor ═══ */

    initTranslationEditor() {
        this.loadTranslationKeys();

        const search = document.getElementById('ct_trans_search');
        if (search) {
            search.addEventListener('input', () => this.filterKeys(search.value));
        }

        const addKeyBtn = document.getElementById('ct_trans_add_key');
        if (addKeyBtn) {
            addKeyBtn.addEventListener('click', () => this.openAddKeyModal());
        }

        this.initAddKeyModal();

        const deleteKeyBtn = document.getElementById('ct_trans_delete_key');
        if (deleteKeyBtn) {
            deleteKeyBtn.addEventListener('click', () => this.deleteCurrentKey());
        }

        const singularBtn = document.getElementById('ct_trans_type_singular');
        if (singularBtn) {
            singularBtn.addEventListener('click', () => this.toggleKeyType('singular'));
        }

        const pluralBtn = document.getElementById('ct_trans_type_plural');
        if (pluralBtn) {
            pluralBtn.addEventListener('click', () => this.toggleKeyType('plural'));
        }

        const exportBtn = document.getElementById('ct_trans_export');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportTranslations());
        }

        const importBtn = document.getElementById('ct_trans_import');
        if (importBtn) {
            importBtn.addEventListener('click', () => {
                document.getElementById('ct_trans_import_file')?.click();
            });
        }

        const importFile = document.getElementById('ct_trans_import_file');
        if (importFile) {
            importFile.addEventListener('change', (e) => this.importTranslations(e));
        }
    }

    async loadTranslationKeys() {
        const result = await this.ajaxPostSimple('admin_get_translation_keys');

        if (result.success && result.data.keys) {
            this.allKeys = result.data.keys;
            this.renderKeyList(this.allKeys);
        }
    }

    renderKeyList(keys) {
        const list = document.getElementById('ct_trans_key_list');
        if (!list) { return; }

        list.innerHTML = '';
        const max = 500;
        let count = 0;

        for (const key of keys) {
            if (count >= max) { break; }
            count++;

            const li = document.createElement('li');
            li.className = 'ct-trans-editor__key-item';
            li.textContent = key;
            li.dataset.key = key;

            if (key === this.currentTransKey) {
                li.classList.add('ct-trans-editor__key-item--active');
            }

            li.addEventListener('click', () => this.selectKey(key));
            list.appendChild(li);
        }

        if (keys.length === 0) {
            list.innerHTML = '<li class="ct-trans-editor__key-empty">No keys found</li>';
        }
    }

    filterKeys(query) {
        const lower = query.toLowerCase();
        const filtered = this.allKeys.filter(k => k.toLowerCase().includes(lower));
        this.renderKeyList(filtered);
    }

    async selectKey(key) {
        this.currentTransKey = key;

        const placeholder = document.getElementById('ct_trans_placeholder');
        const fields = document.getElementById('ct_trans_fields');
        const header = document.getElementById('ct_trans_current_key');

        if (placeholder) { placeholder.style.display = 'none'; }
        if (fields) { fields.style.display = 'block'; }
        if (header) { header.textContent = key; }

        this.renderKeyList(this.allKeys.filter(k => {
            const search = document.getElementById('ct_trans_search');
            if (!search || !search.value) { return true; }
            return k.toLowerCase().includes(search.value.toLowerCase());
        }));

        /* Load values for each language */
        const container = document.getElementById('ct_trans_lang_fields');
        if (!container) { return; }

        container.innerHTML = '<p>Loading...</p>';

        const result = await this.ajaxPostSimple('admin_export_translations');

        if (!result.success) {
            container.innerHTML = '<p>Error loading translations.</p>';
            return;
        }

        const translations = result.data.translations || {};
        const languages = result.data.languages || [];

        /* Detect whether key is plural globally: if ANY language has an object value */
        this.currentKeyIsPlural = false;
        const maxDetect = 50;
        let detectCount = 0;

        for (const lang of languages) {
            if (detectCount >= maxDetect) { break; }
            detectCount++;

            const val = (translations[lang.iso2] || {})[key];

            if (typeof val === 'object' && val !== null) {
                this.currentKeyIsPlural = true;
                break;
            }
        }

        this.updateToggleUI();

        container.innerHTML = '';
        const showPlural = this.currentKeyIsPlural;

        const max = 50;
        let count = 0;

        for (const lang of languages) {
            if (count >= max) { break; }
            count++;

            const iso2 = lang.iso2;
            const value = (translations[iso2] || {})[key];

            /* Extract singular and plural values from stored data */
            let singularVal = '';
            const pluralVals = {};

            if (typeof value === 'string') {
                singularVal = value;
            } else if (typeof value === 'object' && value !== null) {
                singularVal = value.singular || '';
                const forms = ['zero', 'one', 'two', 'few', 'many', 'other'];
                const maxF = 6;
                let fc = 0;
                for (const f of forms) {
                    if (fc >= maxF) { break; }
                    fc++;
                    pluralVals[f] = value[f] || '';
                }
            }

            const fieldWrap = document.createElement('div');
            fieldWrap.className = 'ct-trans-editor__lang-field';

            const label = document.createElement('label');
            label.textContent = `${lang.native_name} (${iso2})`;
            fieldWrap.appendChild(label);

            /* Singular input (always rendered, visibility toggled) */
            const singularWrap = document.createElement('div');
            singularWrap.className = 'ct-trans-editor__singular-wrap';
            singularWrap.style.display = showPlural ? 'none' : 'block';

            const singularInput = document.createElement('input');
            singularInput.type = 'text';
            singularInput.value = singularVal;
            singularInput.dataset.iso2 = iso2;
            singularInput.dataset.singular = 'true';
            singularInput.addEventListener('input', () => {
                this.debounceSaveTranslation(key, iso2);
                this.detectPlaceholders();
            });

            singularWrap.appendChild(singularInput);
            fieldWrap.appendChild(singularWrap);

            /* Plural inputs (always rendered, visibility toggled) */
            const pluralWrap = document.createElement('div');
            pluralWrap.className = 'ct-trans-editor__plural-wrap';
            pluralWrap.style.display = showPlural ? 'block' : 'none';

            const forms = ['zero', 'one', 'two', 'few', 'many', 'other'];
            const maxForms = 6;
            let formCount = 0;

            for (const form of forms) {
                if (formCount >= maxForms) { break; }
                formCount++;

                const formWrap = document.createElement('div');
                formWrap.className = 'ct-trans-editor__plural-form';

                const formLabel = document.createElement('span');
                formLabel.className = 'ct-trans-editor__plural-label';
                formLabel.textContent = form;

                const input = document.createElement('input');
                input.type = 'text';
                input.value = pluralVals[form] || '';
                input.dataset.iso2 = iso2;
                input.dataset.form = form;
                input.dataset.plural = 'true';
                input.addEventListener('input', () => {
                    this.debounceSaveTranslation(key, iso2);
                    this.detectPlaceholders();
                });

                formWrap.appendChild(formLabel);
                formWrap.appendChild(input);
                pluralWrap.appendChild(formWrap);
            }

            fieldWrap.appendChild(pluralWrap);
            container.appendChild(fieldWrap);
        }

        this.detectPlaceholders();
    }

    debounceSaveTranslation(key, iso2) {
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        this.debounceTimer = setTimeout(() => {
            this.saveTranslation(key, iso2);
        }, 500);
    }

    async saveTranslation(key, iso2) {
        const container = document.getElementById('ct_trans_lang_fields');
        if (!container) { return; }

        /* Read singular value */
        const singularInput = container.querySelector(`input[data-iso2="${iso2}"][data-singular]`);
        const singularVal = singularInput ? singularInput.value : '';

        /* Read plural values */
        const pluralInputs = container.querySelectorAll(`input[data-iso2="${iso2}"][data-plural="true"]`);
        let hasAnyPlural = false;
        const pluralVals = {};
        const max = 6;
        let count = 0;

        for (const input of pluralInputs) {
            if (count >= max) { break; }
            count++;
            pluralVals[input.dataset.form] = input.value;
            if (input.value.trim()) {
                hasAnyPlural = true;
            }
        }

        /* Use the key-level plural flag, not per-field content, to decide format */
        let value;
        if (this.currentKeyIsPlural) {
            value = { singular: singularVal };
            const forms = ['zero', 'one', 'two', 'few', 'many', 'other'];
            const maxF = 6;
            let fc = 0;
            for (const form of forms) {
                if (fc >= maxF) { break; }
                fc++;
                if (pluralVals[form]) {
                    value[form] = pluralVals[form];
                }
            }
        } else {
            value = singularVal;
        }

        const result = await this.ajaxPost('admin_save_translation', JSON.stringify({ key, iso2, value }));

        if (result.success) {
            window.ctToast.show('Saved', 'success');
        } else {
            window.ctToast.show(result.data?.message || 'Error saving translation.', 'error');
        }
    }

    initAddKeyModal() {
        const modal     = document.getElementById('ct_add_key_modal');
        const backdrop  = modal ? modal.querySelector('.ct-add-key-modal__backdrop') : null;
        const cancelBtn = document.getElementById('ct_add_key_cancel');
        const confirmBtn = document.getElementById('ct_add_key_confirm');
        const input     = document.getElementById('ct_add_key_input');

        if (!modal) { return; }

        if (backdrop) {
            backdrop.addEventListener('click', () => this.closeAddKeyModal());
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => this.closeAddKeyModal());
        }
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => this.addTranslationKey());
        }
        if (input) {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') { this.addTranslationKey(); }
                if (e.key === 'Escape') { this.closeAddKeyModal(); }
            });
        }
    }

    openAddKeyModal() {
        const modal = document.getElementById('ct_add_key_modal');
        const input = document.getElementById('ct_add_key_input');
        if (!modal) { return; }

        modal.style.display = 'flex';
        if (input) {
            input.value = '';
            input.focus();
        }
    }

    closeAddKeyModal() {
        const modal = document.getElementById('ct_add_key_modal');
        if (modal) { modal.style.display = 'none'; }
    }

    async addTranslationKey() {
        const input = document.getElementById('ct_add_key_input');
        const key = input ? input.value : '';
        if (!key || !key.trim()) { return; }

        const cleanKey = key.trim().toUpperCase().replace(/[^A-Z0-9_]/g, '_');

        const formData = new FormData();
        formData.append('action', 'admin_add_translation_key');
        formData.append('nonce', this.nonce);
        formData.append('key', cleanKey);

        const result = await this.ajaxPostForm(formData);

        if (result.success) {
            this.closeAddKeyModal();
            await this.loadTranslationKeys();
            this.selectKey(cleanKey);
        }
    }

    async deleteCurrentKey() {
        if (!this.currentTransKey) { return; }
        if (!confirm(`Delete key "${this.currentTransKey}" from all languages?`)) { return; }

        const formData = new FormData();
        formData.append('action', 'admin_delete_translation_key');
        formData.append('nonce', this.nonce);
        formData.append('key', this.currentTransKey);

        const result = await this.ajaxPostForm(formData);

        if (result.success) {
            this.currentTransKey = '';
            document.getElementById('ct_trans_placeholder').style.display = 'block';
            document.getElementById('ct_trans_fields').style.display = 'none';
            await this.loadTranslationKeys();
        }
    }

    updateToggleUI() {
        const singularBtn = document.getElementById('ct_trans_type_singular');
        const pluralBtn = document.getElementById('ct_trans_type_plural');
        if (!singularBtn || !pluralBtn) { return; }

        if (this.currentKeyIsPlural) {
            singularBtn.classList.remove('ct-trans-type-toggle__btn--active');
            pluralBtn.classList.add('ct-trans-type-toggle__btn--active');
        } else {
            singularBtn.classList.add('ct-trans-type-toggle__btn--active');
            pluralBtn.classList.remove('ct-trans-type-toggle__btn--active');
        }
    }

    toggleKeyType(newType) {
        const isPlural = newType === 'plural';
        if (isPlural === this.currentKeyIsPlural) { return; }

        this.currentKeyIsPlural = isPlural;
        this.updateToggleUI();

        /* Toggle visibility of singular/plural wrappers */
        const container = document.getElementById('ct_trans_lang_fields');
        if (!container) { return; }

        const singularWraps = container.querySelectorAll('.ct-trans-editor__singular-wrap');
        const pluralWraps = container.querySelectorAll('.ct-trans-editor__plural-wrap');
        const maxWraps = 50;
        let wrapCount = 0;

        for (const wrap of singularWraps) {
            if (wrapCount >= maxWraps) { break; }
            wrapCount++;
            wrap.style.display = isPlural ? 'none' : 'block';
        }

        wrapCount = 0;
        for (const wrap of pluralWraps) {
            if (wrapCount >= maxWraps) { break; }
            wrapCount++;
            wrap.style.display = isPlural ? 'block' : 'none';
        }

        this.detectPlaceholders();
    }

    /**
     * Save every language for the current key, converting format to match
     * the currentKeyIsPlural flag. This ensures all JSON files use the same
     * format (object vs string) for the key.
     */
    async saveAllLanguagesForKey(key) {
        const container = document.getElementById('ct_trans_lang_fields');
        if (!container) { return; }

        const fields = container.querySelectorAll('.ct-trans-editor__lang-field');
        const max = 50;
        let count = 0;

        for (const field of fields) {
            if (count >= max) { break; }
            count++;

            const singularInput = field.querySelector('input[data-singular]');
            if (!singularInput) { continue; }

            const iso2 = singularInput.dataset.iso2;
            await this.saveTranslation(key, iso2);
        }
    }

    detectPlaceholders() {
        const container = document.getElementById('ct_trans_lang_fields');
        if (!container) { return; }

        const inputs = container.querySelectorAll('input[type="text"]');
        const placeholders = new Set();
        const pattern = /##([a-zA-Z0-9_]+)##/g;
        const maxInputs = 500;
        let inputCount = 0;

        for (const input of inputs) {
            if (inputCount >= maxInputs) { break; }
            inputCount++;

            pattern.lastIndex = 0;
            let match;
            while ((match = pattern.exec(input.value)) !== null) {
                placeholders.add(match[1]);
            }
        }

        this.renderPlaceholderBadges(placeholders);
    }

    renderPlaceholderBadges(placeholders) {
        const wrapper = document.getElementById('ct_trans_placeholders');
        const list = document.getElementById('ct_trans_placeholder_list');
        if (!wrapper || !list) { return; }

        if (placeholders.size === 0) {
            wrapper.style.display = 'none';
            return;
        }

        wrapper.style.display = 'flex';
        list.innerHTML = '';
        const maxBadges = 20;
        let badgeCount = 0;

        for (const name of placeholders) {
            if (badgeCount >= maxBadges) { break; }
            badgeCount++;

            const badge = document.createElement('span');
            badge.className = 'ct-trans-placeholders__badge';
            badge.textContent = `##${name}##`;
            list.appendChild(badge);
        }
    }

    async exportTranslations() {
        const result = await this.ajaxPostSimple('admin_export_translations');

        if (result.success) {
            const blob = new Blob([JSON.stringify(result.data, null, 2)], { type: 'application/json' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = 'ct-translations-export.json';
            a.click();
            URL.revokeObjectURL(url);
        }
    }

    async importTranslations(e) {
        const file = e.target.files[0];
        if (!file) { return; }

        const text = await file.text();
        const result = await this.ajaxPost('admin_import_translations', text);

        if (result.success) {
            window.ctToast.show(result.data.message || 'Translations imported.', 'success');
            window.location.reload();
        } else {
            window.ctToast.show(result.data?.message || 'Import failed.', 'error');
        }

        e.target.value = '';
    }

    /* ═══ C) Page Translations ═══ */

    initPageTranslations() {
        const filter = document.getElementById('ct_page_lang_filter');
        if (!filter) { return; }

        filter.addEventListener('change', () => this.loadPagesForLanguage(filter.value));

        if (filter.value) {
            this.loadPagesForLanguage(filter.value);
        }
    }

    async loadPagesForLanguage(iso2) {
        const container = document.getElementById('ct_page_list');
        if (!container) { return; }

        container.innerHTML = '<p class="ct-page-trans__loading">Loading pages...</p>';

        const formData = new FormData();
        formData.append('action', 'admin_get_pages_by_language');
        formData.append('nonce', this.nonce);
        formData.append('iso2', iso2);

        const result = await this.ajaxPostForm(formData);

        if (!result.success) {
            container.innerHTML = '<p>Error loading pages.</p>';
            return;
        }

        const pages = result.data.pages || [];
        container.innerHTML = '';

        if (pages.length === 0) {
            container.innerHTML = '<p>No pages found for this language.</p>';
            return;
        }

        const max = 200;
        let count = 0;

        for (const page of pages) {
            if (count >= max) { break; }
            count++;

            const card = document.createElement('div');
            card.className = 'ct-page-trans__card';

            /* Header: title, status badge, slug, Quick Edit button, Edit in WP link */
            const header = document.createElement('div');
            header.className = 'ct-page-trans__card-header';

            const titleEl = document.createElement('strong');
            titleEl.className = 'ct-page-trans__card-title';
            titleEl.textContent = page.title;

            const statusBadge = document.createElement('span');
            statusBadge.className = 'ct-page-trans__card-status ct-page-trans__card-status--' + this.escapeAttr(page.status);
            statusBadge.textContent = page.status;

            const slugDisplay = document.createElement('span');
            slugDisplay.className = 'ct-page-trans__card-slug';
            slugDisplay.textContent = '/' + (page.slug || '') + '/';

            const headerActions = document.createElement('span');
            headerActions.className = 'ct-page-trans__card-actions';

            const quickEditBtn = document.createElement('button');
            quickEditBtn.type = 'button';
            quickEditBtn.className = 'button button-small ct-page-trans__quick-edit-btn';
            quickEditBtn.textContent = 'Quick Edit';

            headerActions.appendChild(quickEditBtn);

            if (page.edit_url) {
                const wpLink = document.createElement('a');
                wpLink.href = page.edit_url;
                wpLink.className = 'button button-small';
                wpLink.target = '_blank';
                wpLink.textContent = 'Edit in WP';
                wpLink.addEventListener('click', (e) => e.stopPropagation());
                headerActions.appendChild(wpLink);
            }

            header.appendChild(titleEl);
            header.appendChild(statusBadge);
            header.appendChild(slugDisplay);
            header.appendChild(headerActions);

            /* Quick Edit panel (hidden by default) — single flex row */
            const editPanel = document.createElement('div');
            editPanel.className = 'ct-page-trans__quick-edit';
            editPanel.style.display = 'none';

            const titleGroup = document.createElement('div');
            titleGroup.className = 'ct-page-trans__quick-edit-group';
            const titleLabel = document.createElement('label');
            titleLabel.textContent = 'Title:';
            const titleInput = document.createElement('input');
            titleInput.type = 'text';
            titleInput.className = 'ct-page-trans__title';
            titleInput.value = page.title;
            titleGroup.appendChild(titleLabel);
            titleGroup.appendChild(titleInput);

            const slugGroup = document.createElement('div');
            slugGroup.className = 'ct-page-trans__quick-edit-group';
            const slugLabel = document.createElement('label');
            slugLabel.textContent = 'Slug:';
            const slugInput = document.createElement('input');
            slugInput.type = 'text';
            slugInput.className = 'ct-page-trans__slug';
            slugInput.value = page.slug || '';
            slugGroup.appendChild(slugLabel);
            slugGroup.appendChild(slugInput);

            const parentGroup = document.createElement('div');
            parentGroup.className = 'ct-page-trans__quick-edit-group';
            const parentLabel = document.createElement('label');
            parentLabel.textContent = 'Parent:';
            const parentSelect = document.createElement('select');
            parentSelect.className = 'ct-page-trans__parent';

            const noneOption = document.createElement('option');
            noneOption.value = '0';
            noneOption.textContent = '(no parent)';
            parentSelect.appendChild(noneOption);

            const maxOptions = 200;
            let optCount = 0;
            for (const p of pages) {
                if (optCount >= maxOptions) { break; }
                if (p.id === page.id) { continue; }
                optCount++;
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.title;
                if (p.id === page.parent_id) { opt.selected = true; }
                parentSelect.appendChild(opt);
            }

            parentGroup.appendChild(parentLabel);
            parentGroup.appendChild(parentSelect);

            const btnRow = document.createElement('div');
            btnRow.className = 'ct-page-trans__quick-edit-buttons';

            const saveBtn = document.createElement('button');
            saveBtn.type = 'button';
            saveBtn.className = 'button button-primary button-small ct-page-trans__save';
            saveBtn.textContent = 'Save';

            const cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'button button-small ct-page-trans__cancel';
            cancelBtn.textContent = 'Cancel';

            btnRow.appendChild(saveBtn);
            btnRow.appendChild(cancelBtn);

            editPanel.appendChild(titleGroup);
            editPanel.appendChild(slugGroup);
            editPanel.appendChild(parentGroup);
            editPanel.appendChild(btnRow);

            card.appendChild(header);
            card.appendChild(editPanel);

            /* Toggle Quick Edit panel */
            quickEditBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const isOpen = editPanel.style.display !== 'none';
                editPanel.style.display = isOpen ? 'none' : 'flex';
                quickEditBtn.textContent = isOpen ? 'Quick Edit' : 'Close';
            });

            /* Cancel: restore original values and collapse */
            cancelBtn.addEventListener('click', () => {
                titleInput.value = titleEl.textContent;
                slugInput.value = slugDisplay.textContent.replace(/^\/|\/$/g, '');
                parentSelect.value = String(page.parent_id || 0);
                editPanel.style.display = 'none';
                quickEditBtn.textContent = 'Quick Edit';
            });

            /* Save: send title + slug, update header */
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';

                const res = await this.ajaxPost('admin_save_page_translation', JSON.stringify({
                    post_id: page.id,
                    title: titleInput.value,
                    slug: slugInput.value,
                    parent_id: parseInt(parentSelect.value, 10) || 0,
                }));

                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';

                if (res.success) {
                    const newTitle = res.data.title || titleInput.value;
                    const newSlug  = res.data.slug || slugInput.value;

                    titleEl.textContent = newTitle;
                    slugDisplay.textContent = '/' + newSlug + '/';
                    titleInput.value = newTitle;
                    slugInput.value = newSlug;

                    if (res.data.parent_id !== undefined) {
                        page.parent_id = res.data.parent_id;
                        parentSelect.value = String(res.data.parent_id);
                    }

                    editPanel.style.display = 'none';
                    quickEditBtn.textContent = 'Quick Edit';
                }
            });

            container.appendChild(card);
        }
    }

    /* ═══ Helpers ═══ */

    async ajaxPost(action, inputJson) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.nonce);
        formData.append('input', inputJson);

        return this.ajaxPostForm(formData);
    }

    async ajaxPostSimple(action) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.nonce);

        return this.ajaxPostForm(formData);
    }

    async ajaxPostForm(formData) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData,
            });
            return await response.json();
        } catch (err) {
            return { success: false, data: { message: err.message } };
        }
    }

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    escapeAttr(str) {
        return (str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
}
