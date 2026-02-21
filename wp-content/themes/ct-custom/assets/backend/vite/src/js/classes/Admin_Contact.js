/**
 * Admin_Contact - Contact messaging admin panel.
 *
 * Handles messages list, mark read, delete, reply,
 * pointer CRUD, and badge count.
 *
 * @package BS_Custom
 */

const MAX_MESSAGES = 100;
const MAX_POINTERS = 20;
const MAX_REPLIES = 100;
const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export default class Admin_Contact {

    /**
     * @param {Admin_BS_Custom} parent Parent admin class.
     */
    constructor(parent) {
        this._parent = parent;
        this._pointerEditIndex = null;
        this._currentPage = 1;
    }

    initContact() {
        const section = document.querySelector('.ct-contact-admin');
        if (!section) {
            return;
        }

        this._section = section;
        this._restUrl = section.getAttribute('data-rest-url') || '';
        this._syncSettingsFlags();

        this._initSubTabs();
        this._bindFilters();
        this._initFormBuilder();
        this.loadUnreadCount();
        this.loadMessages(1);

        this._section.addEventListener('ct-contact-settings-updated', () => {
            this._syncSettingsFlags();
            this.loadMessages(this._currentPage || 1);
        });
    }

    /* ── Sub-tab navigation ── */

    _initSubTabs() {
        const buttons = this._section.querySelectorAll('.ct-contact-tabs__btn');
        const MAX_TABS = 5;
        let count = 0;

        buttons.forEach((btn) => {
            if (count >= MAX_TABS) { return; }
            count++;

            btn.addEventListener('click', () => {
                const tab = btn.getAttribute('data-tab');
                this._switchTab(tab, buttons);
            });
        });
    }

    _switchTab(tab, buttons) {
        const MAX_TABS = 5;
        let count = 0;

        buttons.forEach((btn) => {
            if (count >= MAX_TABS) { return; }
            count++;

            btn.classList.toggle('ct-contact-tabs__btn--active', btn.getAttribute('data-tab') === tab);
        });

        const messagesPanel = document.getElementById('bs_contact_messages_panel');
        const configPanel = document.getElementById('bs_contact_config_panel');

        if (messagesPanel) {
            messagesPanel.style.display = (tab === 'messages') ? '' : 'none';
        }
        if (configPanel) {
            configPanel.style.display = (tab === 'config') ? '' : 'none';
        }
    }

    /* ── Filters ── */

    _bindFilters() {
        const pointerFilter = document.getElementById('bs_contact_form_filter');
        const statusFilter = document.getElementById('bs_contact_status_filter');

        if (pointerFilter) {
            pointerFilter.addEventListener('change', () => {
                this._currentPage = 1;
                this.loadMessages(1);
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', () => {
                this._currentPage = 1;
                this.loadMessages(1);
            });
        }
    }

    /* ── Messages ── */

    async loadMessages(page) {
        const container = document.getElementById('bs_contact_messages_list');
        if (!container) { return; }

        this._syncSettingsFlags();

        const pointerFilter = document.getElementById('bs_contact_form_filter');
        const statusFilter = document.getElementById('bs_contact_status_filter');

        const pointer = pointerFilter ? pointerFilter.value : '';
        const status = statusFilter ? statusFilter.value : 'all';

        const params = new URLSearchParams({ page: page, status: status });
        if (pointer !== '') {
            params.set('form_id', pointer);
        } else {
            params.set('form_id', '0');
        }

        try {
            const response = await fetch(this._restUrl + '/contact/messages?' + params.toString(), {
                method: 'GET',
                headers: { 'X-WP-Nonce': this._getRestNonce() },
                credentials: 'same-origin',
            });

            const result = await response.json();

            if (result.success && result.data) {
                this._currentPage = result.data.current_page;
                this.renderMessagesList(result.data.messages, container);
                this._renderPagination(result.data);
                this.updateBadge(result.data.unread_count);
            } else {
                container.innerHTML = '<p class="ct-admin-no-items">Error loading messages.</p>';
            }
        } catch {
            container.innerHTML = '<p class="ct-admin-no-items">Network error.</p>';
        }
    }

    renderMessagesList(messages, container) {
        if (!container) { return; }

        if (!messages || messages.length === 0) {
            container.innerHTML = '<p class="ct-admin-no-items">No messages found.</p>';
            return;
        }

        let html = '';
        const limit = Math.min(messages.length, MAX_MESSAGES);

        for (let i = 0; i < limit; i++) {
            const msg = messages[i];
            const readClass = msg.is_read ? 'ct-contact-card--read' : 'ct-contact-card--unread';
            const date = new Date(msg.date).toLocaleString();
            const hasUser = msg.user_id > 0;
            const canReplyToUser = this._userManagementEnabled && hasUser;
            const canReplyToGuest = this._emailEnabled && !hasUser && msg.sender_email;
            const canReply = canReplyToUser || canReplyToGuest;

            html += '<div class="ct-contact-card ' + readClass + '" data-id="' + msg.id + '">';
            html += '<div class="ct-contact-card__header">';
            html += '<strong>' + this._parent.escapeHtml(msg.sender_name) + '</strong>';
            html += '<span class="ct-contact-card__email">' + this._parent.escapeHtml(msg.sender_email) + '</span>';
            if (msg.sender_phone) {
                html += '<span class="ct-contact-card__phone">' + this._parent.escapeHtml(msg.sender_phone) + '</span>';
            }
            html += '<span class="ct-contact-card__date">' + this._parent.escapeHtml(date) + '</span>';
            html += '<span class="ct-contact-card__pointer">' + this._parent.escapeHtml((msg.form_label || msg.pointer)) + '</span>';
            html += '</div>';
            html += '<div class="ct-contact-card__body"><p>' + this._parent.escapeHtml(msg.body) + '</p></div>';

            if (msg.attachments && msg.attachments.length > 0) {
                html += '<div class="ct-contact-card__attachments">';
                html += '<strong>Attachments:</strong>';
                html += '<ul class="ct-contact-card__attachments-list">';
                const attachLimit = Math.min(msg.attachments.length, 10);
                for (let a = 0; a < attachLimit; a++) {
                    const attachment = msg.attachments[a];
                    if (attachment && attachment.url) {
                        const name = attachment.name || attachment.url;
                        if (this._isImageAttachment(attachment)) {
                            html += '<li class="ct-contact-card__attachment ct-contact-card__attachment--image">';
                            html += '<a class="ct-contact-card__attachment-link" href="' + this._escapeAttr(attachment.url) + '" target="_blank" rel="noopener">';
                            html += '<img class="ct-contact-card__attachment-img" src="' + this._escapeAttr(attachment.url) + '" alt="' + this._escapeAttr(name) + '">';
                            html += '</a>';
                            html += '<span class="ct-contact-card__attachment-name">' + this._parent.escapeHtml(name) + '</span>';
                            html += '</li>';
                        } else {
                            html += '<li class="ct-contact-card__attachment">';
                            html += '<a class="ct-contact-card__attachment-link" href="' + this._escapeAttr(attachment.url) + '" target="_blank" rel="noopener">' + this._parent.escapeHtml(name) + '</a>';
                            html += '</li>';
                        }
                    }
                }
                html += '</ul></div>';
            }

            if (msg.replies && msg.replies.length > 0) {
                html += '<div class="ct-contact-card__replies">';
                const replyLimit = Math.min(msg.replies.length, MAX_REPLIES);
                for (let r = 0; r < replyLimit; r++) {
                    const reply = msg.replies[r];
                    const replyDate = new Date(reply.date).toLocaleString();
                    html += '<div class="ct-contact-card__reply">';
                    html += '<strong>' + this._parent.escapeHtml(reply.author_name) + '</strong>';
                    html += '<span class="ct-contact-card__reply-date">' + this._parent.escapeHtml(replyDate) + '</span>';
                    html += '<p>' + this._parent.escapeHtml(reply.body) + '</p>';
                    html += '</div>';
                }
                html += '</div>';
            }

            html += '<div class="ct-contact-card__actions">';
            html += '<button type="button" class="button ct-contact-toggle-read" data-id="' + msg.id + '" data-read="' + (msg.is_read ? '1' : '0') + '">';
            html += msg.is_read ? 'Mark Unread' : 'Mark Read';
            html += '</button>';

            if (canReply) {
                html += '<button type="button" class="button ct-contact-reply-btn" data-id="' + msg.id + '">Reply</button>';
            }

            html += '<button type="button" class="button ct-contact-delete-btn" data-id="' + msg.id + '">Delete</button>';
            html += '</div>';

            if (canReply) {
                html += '<div class="ct-contact-card__reply-form" id="bs_reply_form_' + msg.id + '" style="display:none;">';
                html += '<textarea class="ct-admin-input ct-contact-reply-text" rows="3" placeholder="Type your reply..."></textarea>';
                html += '<button type="button" class="button button-primary ct-contact-send-reply" data-id="' + msg.id + '">Send Reply</button>';
                html += '</div>';
            }

            html += '</div>';
        }

        container.innerHTML = html;
        this._bindMessageActions();
    }

    _bindMessageActions() {
        const MAX_BTNS = 200;
        let count = 0;

        const toggleBtns = this._section.querySelectorAll('.ct-contact-toggle-read');
        toggleBtns.forEach((btn) => {
            if (count >= MAX_BTNS) { return; }
            count++;
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const isRead = btn.getAttribute('data-read') === '1';
                this.markRead(id, !isRead);
            });
        });

        count = 0;
        const deleteBtns = this._section.querySelectorAll('.ct-contact-delete-btn');
        deleteBtns.forEach((btn) => {
            if (count >= MAX_BTNS) { return; }
            count++;
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                this.deleteMessage(id);
            });
        });

        count = 0;
        const replyBtns = this._section.querySelectorAll('.ct-contact-reply-btn');
        replyBtns.forEach((btn) => {
            if (count >= MAX_BTNS) { return; }
            count++;
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                this.showReplyForm(id);
            });
        });

        count = 0;
        const sendBtns = this._section.querySelectorAll('.ct-contact-send-reply');
        sendBtns.forEach((btn) => {
            if (count >= MAX_BTNS) { return; }
            count++;
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                const form = document.getElementById('bs_reply_form_' + id);
                const textarea = form ? form.querySelector('.ct-contact-reply-text') : null;
                if (textarea) {
                    this.replyToMessage(id, textarea.value.trim());
                }
            });
        });
    }

    async markRead(messageId, isRead) {
        try {
            const response = await fetch(this._restUrl + '/contact/mark-read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this._getRestNonce(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message_id: messageId, is_read: isRead }),
            });

            const result = await response.json();

            if (result.success) {
                this._parent.showNotice(result.message, 'success');
                this.loadMessages(this._currentPage);
            } else {
                this._parent.showNotice(result.message || 'Error.', 'error');
            }
        } catch {
            this._parent.showNotice('Network error.', 'error');
        }
    }

    async deleteMessage(messageId) {
        try {
            const response = await fetch(this._restUrl + '/contact/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this._getRestNonce(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message_id: messageId }),
            });

            const result = await response.json();

            if (result.success) {
                this._parent.showNotice(result.message, 'success');
                this.loadMessages(this._currentPage);
            } else {
                this._parent.showNotice(result.message || 'Error.', 'error');
            }
        } catch {
            this._parent.showNotice('Network error.', 'error');
        }
    }

    showReplyForm(messageId) {
        const form = document.getElementById('bs_reply_form_' + messageId);
        if (form) {
            form.style.display = form.style.display === 'none' ? '' : 'none';
        }
    }

    async replyToMessage(messageId, body) {
        if (!body) {
            this._parent.showNotice('Reply cannot be empty.', 'error');
            return;
        }

        try {
            const response = await fetch(this._restUrl + '/contact/reply', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this._getRestNonce(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message_id: messageId, reply_body: body }),
            });

            const result = await response.json();

            if (result.success) {
                this._parent.showNotice(result.message, 'success');
                this.loadMessages(this._currentPage);
            } else {
                this._parent.showNotice(result.message || 'Error.', 'error');
            }
        } catch {
            this._parent.showNotice('Network error.', 'error');
        }
    }

    _renderPagination(data) {
        const container = document.getElementById('bs_contact_pagination');
        if (!container) { return; }

        if (data.total_pages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';
        const maxPages = Math.min(data.total_pages, 50);

        for (let i = 1; i <= maxPages; i++) {
            const activeClass = (i === data.current_page) ? ' ct-contact-pagination__btn--active' : '';
            html += '<button type="button" class="button ct-contact-pagination__btn' + activeClass + '" data-page="' + i + '">' + i + '</button>';
        }

        container.innerHTML = html;

        const pageBtns = container.querySelectorAll('.ct-contact-pagination__btn');
        let count = 0;

        pageBtns.forEach((btn) => {
            if (count >= maxPages) { return; }
            count++;

            btn.addEventListener('click', () => {
                const page = parseInt(btn.getAttribute('data-page'), 10);
                this.loadMessages(page);
            });
        });
    }

    /* ── Badge ── */

    async loadUnreadCount() {
        const nonceField = document.querySelector('#admin_get_contact_messages_count_nonce');
        if (!nonceField) { return; }

        const data = new URLSearchParams({
            nonce: nonceField.value,
            action: 'admin_get_contact_messages_count',
        });

        try {
            const response = await fetch(ajaxurl, { method: 'POST', body: data });
            const result = await response.json();

            if (result.success && result.data) {
                this.updateBadge(result.data.count);
            }
        } catch {
            /* Silently fail */
        }
    }

    updateBadge(count) {
        const badge = document.getElementById('bs_unread_badge');
        if (!badge) { return; }

        if (count > 0) {
            badge.textContent = count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    /* ── Form Builder ── */

    _initFormBuilder() {
        const builder = document.getElementById('bs_contact_form_builder');
        if (!builder) {
            return;
        }

        this._builder = builder;
        this._formListEl = builder.querySelector('#bs_contact_form_list');
        this._formEditor = builder.querySelector('#bs_contact_form_editor');
        this._editorWrap = builder.querySelector('#bs_contact_form_editor_wrap');
        this._fieldsListEl = builder.querySelector('#bs_contact_fields_list');
        this._currentFormId = null;
        this._currentFields = [];
        this._isNewForm = true;

        this._bindFormBuilderActions();
        this._loadInitialForms();
    }

    _bindFormBuilderActions() {
        const addBtn = document.getElementById('bs_contact_form_add');
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._resetFormEditor(true);
            });
        }

        const saveBtn = document.getElementById('bs_contact_form_save');
        if (saveBtn) {
            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._saveForm();
            });
        }

        const deleteBtn = document.getElementById('bs_contact_form_delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._deleteForm();
            });
        }

        const cancelBtn = document.getElementById('bs_contact_form_cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._cancelFormEdit();
            });
        }

        const addFieldBtn = document.getElementById('bs_contact_field_add');
        if (addFieldBtn) {
            addFieldBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._addField();
            });
        }

        const uploadsToggle = document.getElementById('bs_contact_form_uploads');
        const uploadStorage = document.getElementById('bs_contact_form_upload_storage');
        if (uploadsToggle) {
            uploadsToggle.addEventListener('change', () => this._updateUploadSettingsUI());
        }
        if (uploadStorage) {
            uploadStorage.addEventListener('change', () => this._updateUploadSettingsUI());
        }
    }

    _loadInitialForms() {
        let forms = [];
        try {
            forms = JSON.parse(this._builder.getAttribute('data-forms') || '[]');
        } catch (e) {
            forms = [];
        }
        if (!Array.isArray(forms)) {
            forms = [];
        }

        this._forms = forms;
        this._renderFormList();
        this._hideEditor();
    }

    _renderFormList() {
        if (!this._formListEl) {
            return;
        }

        if (!this._forms || this._forms.length === 0) {
            this._formListEl.innerHTML = '<p class="ct-admin-no-items">No forms created yet.</p>';
            return;
        }

        let html = '<ul class="ct-admin-form-list">';
        const limit = Math.min(this._forms.length, 50);
        for (let i = 0; i < limit; i++) {
            const form = this._forms[i];
            const isActive = this._currentFormId === form.id;
            html += '<li class="ct-admin-form-item' + (isActive ? ' is-active' : '') + '" data-id="' + form.id + '">'
                + '<div class="ct-admin-form-item__title">' + this._parent.escapeHtml(form.title || 'Untitled Form') + '</div>'
                + '<div class="ct-admin-form-item__meta">[bs_contact_form id="' + form.id + '"]</div>'
                + '<button type="button" class="button ct-admin-form-edit" data-id="' + form.id + '">Edit</button>'
                + '</li>';
        }
        html += '</ul>';
        this._formListEl.innerHTML = html;

        const editBtns = this._formListEl.querySelectorAll('.ct-admin-form-edit');
        editBtns.forEach((btn) => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.getAttribute('data-id'), 10);
                if (!isNaN(id)) {
                    this._loadForm(id);
                }
            });
        });

        this._renderFormFilterOptions();
    }

    _renderFormFilterOptions() {
        const filter = document.getElementById('bs_contact_form_filter');
        if (!filter) {
            return;
        }

        const current = filter.value;
        filter.innerHTML = '<option value=\"\">All Forms</option>';

        if (Array.isArray(this._forms)) {
            for (let i = 0; i < this._forms.length && i < 50; i++) {
                const form = this._forms[i];
                const option = document.createElement('option');
                option.value = String(form.id);
                option.textContent = form.title || 'Untitled Form';
                if (String(form.id) === current) {
                    option.selected = true;
                }
                filter.appendChild(option);
            }
        }
    }

    async _loadForm(formId) {
        const nonceField = document.querySelector('#bs_contact_form_editor input[name="admin_save_contact_form_nonce"]');
        if (!nonceField) {
            return;
        }

        const data = new URLSearchParams({
            action: 'admin_get_contact_form',
            nonce: nonceField.value,
            form_id: String(formId),
        });

        try {
            const response = await fetch(ajaxurl, { method: 'POST', body: data });
            const result = await response.json();
            if (!result.success || !result.data) {
                this._parent.showNotice('Unable to load form.', 'error');
                return;
            }
            this._applyFormData(result.data);
            this._showEditor();
        } catch {
            this._parent.showNotice('Network error loading form.', 'error');
        }
    }

    _applyFormData(data) {
        this._currentFormId = data.id || null;
        this._isNewForm = !data.id;
        const titleInput = document.getElementById('bs_contact_form_title');
        const idInput = document.getElementById('bs_contact_form_id');
        const shortcodeInput = document.getElementById('bs_contact_form_shortcode');
        const emailsInput = document.getElementById('bs_contact_form_emails');
        const loggedInToggle = document.getElementById('bs_contact_form_logged_in');
        const captchaToggle = document.getElementById('bs_contact_form_captcha');
        const uploadsToggle = document.getElementById('bs_contact_form_uploads');
        const uploadStorage = document.getElementById('bs_contact_form_upload_storage');
        const s3Bucket = document.getElementById('bs_contact_form_s3_bucket');
        const s3Key = document.getElementById('bs_contact_form_s3_key');
        const s3Secret = document.getElementById('bs_contact_form_s3_secret');

        if (titleInput) {
            titleInput.value = data.title || '';
        }
        if (idInput) {
            idInput.value = data.id ? String(data.id) : '';
        }
        if (shortcodeInput) {
            shortcodeInput.value = data.id ? '[bs_contact_form id="' + data.id + '"]' : '';
        }

        const settings = data.settings || {};
        if (emailsInput) {
            emailsInput.value = Array.isArray(settings.emails) ? settings.emails.join(', ') : '';
            if (!this._emailEnabled) {
                emailsInput.value = '';
                emailsInput.disabled = true;
                emailsInput.closest('.ct-admin-field')?.classList.add('ct-admin-field--disabled');
            } else {
                emailsInput.disabled = false;
                emailsInput.closest('.ct-admin-field')?.classList.remove('ct-admin-field--disabled');
            }
        }
        if (loggedInToggle) {
            loggedInToggle.checked = !!settings.logged_in_only;
        }
        if (captchaToggle) {
            captchaToggle.checked = !!settings.captcha_enabled;
        }
        if (uploadsToggle) {
            uploadsToggle.checked = !!(settings.file_uploads && settings.file_uploads.enabled);
        }
        if (uploadStorage) {
            uploadStorage.value = settings.file_uploads && settings.file_uploads.storage ? settings.file_uploads.storage : 'wordpress';
        }
        if (s3Bucket) {
            s3Bucket.value = settings.file_uploads && settings.file_uploads.s3 ? (settings.file_uploads.s3.bucket || '') : '';
        }
        if (s3Key) {
            s3Key.value = settings.file_uploads && settings.file_uploads.s3 ? (settings.file_uploads.s3.access_key || '') : '';
        }
        if (s3Secret) {
            s3Secret.value = settings.file_uploads && settings.file_uploads.s3 ? (settings.file_uploads.s3.secret_key || '') : '';
        }

        this._updateUploadSettingsUI();

        this._currentFields = Array.isArray(data.fields) ? data.fields : [];
        this._renderFields();
        this._renderFormList();
        this._updateFormActionButtons();
    }

    _updateUploadSettingsUI() {
        const uploadsToggle = document.getElementById('bs_contact_form_uploads');
        const uploadStorage = document.getElementById('bs_contact_form_upload_storage');
        const s3Bucket = document.getElementById('bs_contact_form_s3_bucket');
        const s3Key = document.getElementById('bs_contact_form_s3_key');
        const s3Secret = document.getElementById('bs_contact_form_s3_secret');

        const uploadsEnabled = uploadsToggle ? uploadsToggle.checked : false;
        const isS3 = uploadStorage ? uploadStorage.value === 's3' : false;

        if (uploadStorage) {
            uploadStorage.closest('.ct-admin-field')?.classList.toggle('ct-admin-field--disabled', !uploadsEnabled);
            uploadStorage.disabled = !uploadsEnabled;
        }

        const s3Fields = [s3Bucket, s3Key, s3Secret];
        s3Fields.forEach((field) => {
            if (!field) {
                return;
            }
            const shouldShow = uploadsEnabled && isS3;
            const wrapper = field.closest('.ct-admin-field');
            if (wrapper) {
                wrapper.style.display = shouldShow ? '' : 'none';
            }
        });
    }

    _resetFormEditor(showEditor = false) {
        this._currentFormId = null;
        this._currentFields = [];
        this._isNewForm = true;
        this._applyFormData({
            id: null,
            title: '',
            settings: {
                emails: [],
                logged_in_only: false,
                captcha_enabled: false,
                file_uploads: {
                    enabled: false,
                    storage: 'wordpress',
                    s3: { bucket: '', access_key: '', secret_key: '' },
                },
            },
            fields: [],
        });
        if (showEditor) {
            this._showEditor();
        } else {
            this._hideEditor();
        }
    }

    _addField() {
        this._currentFields.push(this._getDefaultField());
        this._renderFields();
    }

    _getDefaultField() {
        return {
            id: 'field_' + Math.random().toString(36).slice(2, 8),
            type: 'text',
            label: 'New Field',
            name: 'field_' + (this._currentFields.length + 1),
            placeholder: '',
            required: false,
            options: [],
            conditions: { enabled: false, relation: 'all', rules: [] },
            min: '',
            max: '',
            step: '',
            default: '',
            accept: '',
        };
    }

    _renderFields() {
        if (!this._fieldsListEl) {
            return;
        }

        if (!this._currentFields || this._currentFields.length === 0) {
            this._fieldsListEl.innerHTML = '<p class="ct-admin-no-items">No fields added yet.</p>';
            return;
        }

        const fieldOptions = this._getFieldOptionsList();
        let html = '';
        const limit = Math.min(this._currentFields.length, 200);
        for (let i = 0; i < limit; i++) {
            const field = this._currentFields[i];
            const optionsValue = Array.isArray(field.options)
                ? field.options.map((opt) => (opt.value ? opt.value + '|' + opt.label : opt.label)).join('\\n')
                : '';
            const cond = field.conditions || { enabled: false, relation: 'all', rules: [] };
            const rules = Array.isArray(cond.rules) ? cond.rules : [];

            html += '<div class="ct-form-field" data-index="' + i + '">'
                + '<div class="ct-form-field__row">'
                + '<input type="text" class="ct-field-label" placeholder="Label" value="' + this._escapeAttr(field.label || '') + '">'
                + '<input type="text" class="ct-field-name" placeholder="name" value="' + this._escapeAttr(field.name || '') + '">'
                + '<select class="ct-field-type">' + this._renderFieldTypeOptions(field.type) + '</select>'
                + '<label class="ct-field-required"><input type="checkbox"' + (field.required ? ' checked' : '') + '> Required</label>'
                + '</div>'
                + '<div class="ct-form-field__row">'
                + '<input type="text" class="ct-field-placeholder" placeholder="Placeholder" value="' + this._escapeAttr(field.placeholder || '') + '">'
                + '<input type="text" class="ct-field-default" placeholder="Default" value="' + this._escapeAttr(field.default || '') + '">'
                + '</div>'
                + '<div class="ct-form-field__row ct-field-options"' + (this._typeNeedsOptions(field.type) ? '' : ' style="display:none;"') + '>'
                + '<textarea class="ct-field-options-input" placeholder="value|Label per line">' + this._parent.escapeHtml(optionsValue) + '</textarea>'
                + '</div>'
                + '<div class="ct-form-field__row ct-field-range"' + (this._typeNeedsRange(field.type) ? '' : ' style="display:none;"') + '>'
                + '<input type="number" class="ct-field-min" placeholder="Min" value="' + this._escapeAttr(field.min || '') + '">'
                + '<input type="number" class="ct-field-max" placeholder="Max" value="' + this._escapeAttr(field.max || '') + '">'
                + '<input type="number" class="ct-field-step" placeholder="Step" value="' + this._escapeAttr(field.step || '') + '">'
                + '</div>'
                + '<div class="ct-form-field__row ct-field-accept"' + (field.type === 'file' ? '' : ' style="display:none;"') + '>'
                + '<input type="text" class="ct-field-accept-input" placeholder="Allowed types (comma separated)" value="' + this._escapeAttr(field.accept || '') + '">'
                + '</div>'
                + '<div class="ct-form-field__conditions">'
                + '<label><input type="checkbox" class="ct-field-conditions-enabled"' + (cond.enabled ? ' checked' : '') + '> Conditional logic</label>'
                + '<select class="ct-field-conditions-relation">'
                + '<option value="all"' + (cond.relation === 'all' ? ' selected' : '') + '>All rules</option>'
                + '<option value="any"' + (cond.relation === 'any' ? ' selected' : '') + '>Any rule</option>'
                + '</select>'
                + '<div class="ct-field-conditions-list">' + this._renderConditionRows(rules, fieldOptions) + '</div>'
                + '<button type="button" class="button ct-field-add-rule">Add Rule</button>'
                + '</div>'
                + '<div class="ct-form-field__actions">'
                + '<button type="button" class="button ct-field-move-up">↑</button>'
                + '<button type="button" class="button ct-field-move-down">↓</button>'
                + '<button type="button" class="button ct-field-remove">Remove</button>'
                + '</div>'
                + '</div>';
        }

        this._fieldsListEl.innerHTML = html;
        this._bindFieldEvents();
    }

    _renderFieldTypeOptions(selected) {
        const types = [
            { value: 'text', label: 'Text' },
            { value: 'email', label: 'Email' },
            { value: 'tel', label: 'Phone' },
            { value: 'url', label: 'URL' },
            { value: 'number', label: 'Number' },
            { value: 'textarea', label: 'Textarea' },
            { value: 'select', label: 'Select' },
            { value: 'radio', label: 'Radio' },
            { value: 'checkbox', label: 'Checkbox' },
            { value: 'checkbox_group', label: 'Checkbox Group' },
            { value: 'range', label: 'Range' },
            { value: 'date', label: 'Date' },
            { value: 'file', label: 'File Upload' },
            { value: 'hidden', label: 'Hidden' },
        ];

        let html = '';
        for (let i = 0; i < types.length; i++) {
            const t = types[i];
            html += '<option value="' + t.value + '"' + (t.value === selected ? ' selected' : '') + '>' + t.label + '</option>';
        }
        return html;
    }

    _typeNeedsOptions(type) {
        return type === 'select' || type === 'radio' || type === 'checkbox_group';
    }

    _typeNeedsRange(type) {
        return type === 'number' || type === 'range';
    }

    _getFieldOptionsList() {
        const options = [];
        for (let i = 0; i < this._currentFields.length; i++) {
            const field = this._currentFields[i];
            if (!field || !field.name) {
                continue;
            }
            options.push({ value: field.name, label: field.label || field.name });
        }
        return options;
    }

    _renderConditionRows(rules, fieldOptions) {
        if (!rules || rules.length === 0) {
            return '';
        }

        let html = '';
        for (let i = 0; i < rules.length; i++) {
            const rule = rules[i] || {};
            html += '<div class="ct-field-condition" data-rule-index="' + i + '">'
                + '<select class="ct-field-condition-field">' + this._renderConditionFieldOptions(fieldOptions, rule.field) + '</select>'
                + '<select class="ct-field-condition-operator">' + this._renderConditionOperatorOptions(rule.operator) + '</select>'
                + '<input type="text" class="ct-field-condition-value" value="' + this._escapeAttr(rule.value || '') + '">'
                + '<button type="button" class="button ct-field-remove-rule">Remove</button>'
                + '</div>';
        }
        return html;
    }

    _renderConditionFieldOptions(options, selected) {
        let html = '<option value="">Select field</option>';
        for (let i = 0; i < options.length; i++) {
            const opt = options[i];
            html += '<option value="' + this._escapeAttr(opt.value) + '"' + (opt.value === selected ? ' selected' : '') + '>'
                + this._parent.escapeHtml(opt.label) + '</option>';
        }
        return html;
    }

    _renderConditionOperatorOptions(selected) {
        const ops = [
            { value: 'equals', label: 'Equals' },
            { value: 'not_equals', label: 'Not equals' },
            { value: 'contains', label: 'Contains' },
            { value: 'greater', label: 'Greater than' },
            { value: 'less', label: 'Less than' },
            { value: 'checked', label: 'Is checked' },
            { value: 'not_checked', label: 'Is not checked' },
        ];
        let html = '';
        for (let i = 0; i < ops.length; i++) {
            const op = ops[i];
            html += '<option value="' + op.value + '"' + (op.value === selected ? ' selected' : '') + '>' + op.label + '</option>';
        }
        return html;
    }

    _bindFieldEvents() {
        const fieldEls = this._fieldsListEl.querySelectorAll('.ct-form-field');
        fieldEls.forEach((el) => {
            const index = parseInt(el.getAttribute('data-index'), 10);
            if (isNaN(index) || !this._currentFields[index]) {
                return;
            }
            const field = this._currentFields[index];

            const labelInput = el.querySelector('.ct-field-label');
            const nameInput = el.querySelector('.ct-field-name');
            const typeSelect = el.querySelector('.ct-field-type');
            const requiredInput = el.querySelector('.ct-field-required input');
            const placeholderInput = el.querySelector('.ct-field-placeholder');
            const defaultInput = el.querySelector('.ct-field-default');
            const optionsInput = el.querySelector('.ct-field-options-input');
            const minInput = el.querySelector('.ct-field-min');
            const maxInput = el.querySelector('.ct-field-max');
            const stepInput = el.querySelector('.ct-field-step');
            const acceptInput = el.querySelector('.ct-field-accept-input');
            const condEnabled = el.querySelector('.ct-field-conditions-enabled');
            const condRelation = el.querySelector('.ct-field-conditions-relation');
            const addRuleBtn = el.querySelector('.ct-field-add-rule');

            if (labelInput) {
                labelInput.addEventListener('input', () => {
                    field.label = labelInput.value;
                    this._renderFormList();
                });
            }
            if (nameInput) {
                nameInput.addEventListener('input', () => {
                    field.name = nameInput.value;
                });
            }
            if (typeSelect) {
                typeSelect.addEventListener('change', () => {
                    field.type = typeSelect.value;
                    this._renderFields();
                });
            }
            if (requiredInput) {
                requiredInput.addEventListener('change', () => {
                    field.required = requiredInput.checked;
                });
            }
            if (placeholderInput) {
                placeholderInput.addEventListener('input', () => {
                    field.placeholder = placeholderInput.value;
                });
            }
            if (defaultInput) {
                defaultInput.addEventListener('input', () => {
                    field.default = defaultInput.value;
                });
            }
            if (optionsInput) {
                optionsInput.addEventListener('input', () => {
                    field.options = this._parseOptions(optionsInput.value);
                });
            }
            if (minInput) {
                minInput.addEventListener('input', () => {
                    field.min = minInput.value;
                });
            }
            if (maxInput) {
                maxInput.addEventListener('input', () => {
                    field.max = maxInput.value;
                });
            }
            if (stepInput) {
                stepInput.addEventListener('input', () => {
                    field.step = stepInput.value;
                });
            }
            if (acceptInput) {
                acceptInput.addEventListener('input', () => {
                    field.accept = acceptInput.value;
                });
            }

            if (!field.conditions) {
                field.conditions = { enabled: false, relation: 'all', rules: [] };
            }
            if (condEnabled) {
                condEnabled.addEventListener('change', () => {
                    field.conditions.enabled = condEnabled.checked;
                });
            }
            if (condRelation) {
                condRelation.addEventListener('change', () => {
                    field.conditions.relation = condRelation.value;
                });
            }
            if (addRuleBtn) {
                addRuleBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    field.conditions.rules.push({ field: '', operator: 'equals', value: '' });
                    this._renderFields();
                });
            }

            const ruleRows = el.querySelectorAll('.ct-field-condition');
            ruleRows.forEach((row) => {
                const ruleIndex = parseInt(row.getAttribute('data-rule-index'), 10);
                const rule = field.conditions.rules[ruleIndex];
                if (!rule) {
                    return;
                }
                const ruleField = row.querySelector('.ct-field-condition-field');
                const ruleOperator = row.querySelector('.ct-field-condition-operator');
                const ruleValue = row.querySelector('.ct-field-condition-value');
                const removeRuleBtn = row.querySelector('.ct-field-remove-rule');
                if (ruleField) {
                    ruleField.addEventListener('change', () => {
                        rule.field = ruleField.value;
                    });
                }
                if (ruleOperator) {
                    ruleOperator.addEventListener('change', () => {
                        rule.operator = ruleOperator.value;
                    });
                }
                if (ruleValue) {
                    ruleValue.addEventListener('input', () => {
                        rule.value = ruleValue.value;
                    });
                }
                if (removeRuleBtn) {
                    removeRuleBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        field.conditions.rules.splice(ruleIndex, 1);
                        this._renderFields();
                    });
                }
            });

            const moveUpBtn = el.querySelector('.ct-field-move-up');
            const moveDownBtn = el.querySelector('.ct-field-move-down');
            const removeBtn = el.querySelector('.ct-field-remove');
            if (moveUpBtn) {
                moveUpBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (index > 0) {
                        const tmp = this._currentFields[index - 1];
                        this._currentFields[index - 1] = this._currentFields[index];
                        this._currentFields[index] = tmp;
                        this._renderFields();
                    }
                });
            }
            if (moveDownBtn) {
                moveDownBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (index < this._currentFields.length - 1) {
                        const tmp = this._currentFields[index + 1];
                        this._currentFields[index + 1] = this._currentFields[index];
                        this._currentFields[index] = tmp;
                        this._renderFields();
                    }
                });
            }
            if (removeBtn) {
                removeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this._currentFields.splice(index, 1);
                    this._renderFields();
                });
            }
        });
    }

    _parseOptions(value) {
        const lines = value.split('\\n');
        const options = [];
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) {
                continue;
            }
            if (line.includes('|')) {
                const parts = line.split('|');
                const optValue = parts[0].trim();
                const optLabel = parts.slice(1).join('|').trim() || optValue;
                options.push({ value: optValue, label: optLabel });
            } else {
                options.push({ value: line, label: line });
            }
        }
        return options;
    }

    async _saveForm() {
        const nonceField = document.querySelector('#bs_contact_form_editor input[name="admin_save_contact_form_nonce"]');
        if (!nonceField) {
            return;
        }

        const titleInput = document.getElementById('bs_contact_form_title');
        const idInput = document.getElementById('bs_contact_form_id');
        const emailsInput = document.getElementById('bs_contact_form_emails');
        const loggedInToggle = document.getElementById('bs_contact_form_logged_in');
        const captchaToggle = document.getElementById('bs_contact_form_captcha');
        const uploadsToggle = document.getElementById('bs_contact_form_uploads');
        const uploadStorage = document.getElementById('bs_contact_form_upload_storage');
        const s3Bucket = document.getElementById('bs_contact_form_s3_bucket');
        const s3Key = document.getElementById('bs_contact_form_s3_key');
        const s3Secret = document.getElementById('bs_contact_form_s3_secret');

        const payload = {
            id: idInput && idInput.value ? parseInt(idInput.value, 10) : 0,
            title: titleInput ? titleInput.value.trim() : '',
            settings: {
                emails: emailsInput ? emailsInput.value.split(',').map((v) => v.trim()).filter((v) => v.length > 0) : [],
                logged_in_only: loggedInToggle ? !!loggedInToggle.checked : false,
                captcha_enabled: captchaToggle ? !!captchaToggle.checked : false,
                file_uploads: {
                    enabled: uploadsToggle ? !!uploadsToggle.checked : false,
                    storage: uploadStorage ? uploadStorage.value : 'wordpress',
                    s3: {
                        bucket: s3Bucket ? s3Bucket.value.trim() : '',
                        access_key: s3Key ? s3Key.value.trim() : '',
                        secret_key: s3Secret ? s3Secret.value.trim() : '',
                    },
                },
            },
            fields: this._currentFields,
        };

        const data = new URLSearchParams({
            action: 'admin_save_contact_form',
            nonce: nonceField.value,
            input: JSON.stringify(payload),
        });

        try {
            const response = await fetch(ajaxurl, { method: 'POST', body: data });
            const result = await response.json();
            if (result.success && result.data) {
                this._parent.showNotice(result.data.message || 'Form saved.', 'success');
                if (result.data.forms) {
                    this._forms = result.data.forms;
                }
                if (result.data.form) {
                    this._applyFormData(result.data.form);
                }
                this._hideEditor();
            } else {
                this._parent.showNotice(result.data && result.data.message ? result.data.message : 'Error saving form.', 'error');
            }
        } catch {
            this._parent.showNotice('Network error.', 'error');
        }
    }

    async _deleteForm() {
        const idInput = document.getElementById('bs_contact_form_id');
        const nonceField = document.querySelector('#bs_contact_form_editor input[name="admin_save_contact_form_nonce"]');
        if (!idInput || !idInput.value || !nonceField) {
            return;
        }

        const data = new URLSearchParams({
            action: 'admin_delete_contact_form',
            nonce: nonceField.value,
            form_id: idInput.value,
        });

        try {
            const response = await fetch(ajaxurl, { method: 'POST', body: data });
            const result = await response.json();
            if (result.success && result.data) {
                this._parent.showNotice(result.data.message || 'Form deleted.', 'success');
                this._forms = result.data.forms || [];
                this._resetFormEditor(false);
                this._renderFormList();
            } else {
                this._parent.showNotice(result.data && result.data.message ? result.data.message : 'Error deleting form.', 'error');
            }
        } catch {
            this._parent.showNotice('Network error.', 'error');
        }
    }

    _cancelFormEdit() {
        this._resetFormEditor(false);
    }

    _showEditor() {
        if (this._editorWrap) {
            this._editorWrap.classList.remove('is-hidden');
        }
        this._hideList();
    }

    _hideEditor() {
        if (this._editorWrap) {
            this._editorWrap.classList.add('is-hidden');
        }
        this._showList();
    }

    _showList() {
        const listWrap = this._builder ? this._builder.querySelector('.ct-admin-form-builder__list') : null;
        if (listWrap) {
            listWrap.style.display = '';
        }
    }

    _hideList() {
        const listWrap = this._builder ? this._builder.querySelector('.ct-admin-form-builder__list') : null;
        if (listWrap) {
            listWrap.style.display = 'none';
        }
    }

    _updateFormActionButtons() {
        const deleteBtn = document.getElementById('bs_contact_form_delete');
        const cancelBtn = document.getElementById('bs_contact_form_cancel');
        const shortcodeField = document.getElementById('bs_contact_form_shortcode_field');
        if (!deleteBtn || !cancelBtn) {
            return;
        }

        if (this._isNewForm) {
            deleteBtn.style.display = 'none';
            cancelBtn.style.display = '';
            cancelBtn.textContent = 'Cancel Add';
            if (shortcodeField) {
                shortcodeField.style.display = 'none';
            }
        } else {
            deleteBtn.style.display = '';
            cancelBtn.style.display = 'none';
            if (shortcodeField) {
                shortcodeField.style.display = '';
            }
        }
    }
    /* ── Helpers ── */

    _escapeAttr(value) {
        if (value === null || typeof value === 'undefined') {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/\"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    _syncSettingsFlags() {
        if (!this._section) {
            return;
        }
        this._userManagementEnabled = this._section.getAttribute('data-user-management-enabled') !== '0';
        this._emailEnabled = this._section.getAttribute('data-email-enabled') !== '0';
    }

    _isImageAttachment(attachment) {
        if (!attachment) {
            return false;
        }
        const type = (attachment.type || '').toLowerCase();
        if (type.indexOf('image/') === 0) {
            return true;
        }
        const url = (attachment.url || '').toLowerCase().split('?')[0];
        return /\.(png|jpe?g|gif|webp|bmp|svg)$/.test(url);
    }

    _getRestNonce() {
        return typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '';
    }
}
