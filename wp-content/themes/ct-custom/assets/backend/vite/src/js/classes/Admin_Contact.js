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

        this._initSubTabs();
        this._bindFilters();
        this._bindPointerForm();
        this._bindPointerListButtons();
        this.loadUnreadCount();
        this.loadMessages(1);
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

        const messagesPanel = document.getElementById('ct_contact_messages_panel');
        const configPanel = document.getElementById('ct_contact_config_panel');

        if (messagesPanel) {
            messagesPanel.style.display = (tab === 'messages') ? '' : 'none';
        }
        if (configPanel) {
            configPanel.style.display = (tab === 'config') ? '' : 'none';
        }
    }

    /* ── Filters ── */

    _bindFilters() {
        const pointerFilter = document.getElementById('ct_contact_pointer_filter');
        const statusFilter = document.getElementById('ct_contact_status_filter');

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
        const container = document.getElementById('ct_contact_messages_list');
        if (!container) { return; }

        const pointerFilter = document.getElementById('ct_contact_pointer_filter');
        const statusFilter = document.getElementById('ct_contact_status_filter');

        const pointer = pointerFilter ? pointerFilter.value : '';
        const status = statusFilter ? statusFilter.value : 'all';

        const params = new URLSearchParams({ page: page, pointer: pointer, status: status });

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

            html += '<div class="ct-contact-card ' + readClass + '" data-id="' + msg.id + '">';
            html += '<div class="ct-contact-card__header">';
            html += '<strong>' + this._parent.escapeHtml(msg.sender_name) + '</strong>';
            html += '<span class="ct-contact-card__email">' + this._parent.escapeHtml(msg.sender_email) + '</span>';
            if (msg.sender_phone) {
                html += '<span class="ct-contact-card__phone">' + this._parent.escapeHtml(msg.sender_phone) + '</span>';
            }
            html += '<span class="ct-contact-card__date">' + this._parent.escapeHtml(date) + '</span>';
            html += '<span class="ct-contact-card__pointer">' + this._parent.escapeHtml(msg.pointer) + '</span>';
            html += '</div>';
            html += '<div class="ct-contact-card__body"><p>' + this._parent.escapeHtml(msg.body) + '</p></div>';

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

            if (hasUser) {
                html += '<button type="button" class="button ct-contact-reply-btn" data-id="' + msg.id + '">Reply</button>';
            }

            html += '<button type="button" class="button ct-contact-delete-btn" data-id="' + msg.id + '">Delete</button>';
            html += '</div>';

            html += '<div class="ct-contact-card__reply-form" id="ct_reply_form_' + msg.id + '" style="display:none;">';
            html += '<textarea class="ct-admin-input ct-contact-reply-text" rows="3" placeholder="Type your reply..."></textarea>';
            html += '<button type="button" class="button button-primary ct-contact-send-reply" data-id="' + msg.id + '">Send Reply</button>';
            html += '</div>';

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
                const form = document.getElementById('ct_reply_form_' + id);
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
        const form = document.getElementById('ct_reply_form_' + messageId);
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
        const container = document.getElementById('ct_contact_pagination');
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
        const badge = document.getElementById('ct_unread_badge');
        if (!badge) { return; }

        if (count > 0) {
            badge.textContent = count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    /* ── Pointer CRUD ── */

    _bindPointerForm() {
        const form = document.getElementById('add_contact_pointer_form');
        if (!form) { return; }

        const addBtn = document.getElementById('add_pointer_btn');
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.addPointer();
            });
        }

        const saveEditBtn = document.getElementById('save_pointer_edit_btn');
        if (saveEditBtn) {
            saveEditBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.addPointer();
            });
        }

        const cancelBtn = document.getElementById('cancel_pointer_edit_btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._cancelPointerEdit();
            });
        }

        const slugInput = form.elements['pointer_slug'];
        const labelInput = form.elements['pointer_label'];
        const emailsInput = form.elements['pointer_emails'];

        if (slugInput) {
            slugInput.addEventListener('input', () => this._validatePointerForm());
        }
        if (labelInput) {
            labelInput.addEventListener('input', () => this._validatePointerForm());
        }
        if (emailsInput) {
            emailsInput.addEventListener('input', () => this._validatePointerForm());
        }
    }

    _bindPointerListButtons() {
        const MAX_BTNS = 50;
        let count = 0;

        const editBtns = document.querySelectorAll('.ct-admin-edit-pointer');
        editBtns.forEach((btn) => {
            if (count >= MAX_BTNS) { return; }
            count++;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const index = parseInt(btn.getAttribute('data-index'), 10);
                if (!isNaN(index)) {
                    this.editPointer(index);
                }
            });
        });

        count = 0;
        const removeBtns = document.querySelectorAll('.ct-admin-remove-pointer');
        removeBtns.forEach((btn) => {
            if (count >= MAX_BTNS) { return; }
            count++;
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const index = parseInt(btn.getAttribute('data-index'), 10);
                if (!isNaN(index)) {
                    this.removePointer(index, btn);
                }
            });
        });
    }

    _validatePointerForm() {
        const form = document.getElementById('add_contact_pointer_form');
        if (!form) { return false; }

        const slugInput = form.elements['pointer_slug'];
        const labelInput = form.elements['pointer_label'];
        const emailsInput = form.elements['pointer_emails'];

        const slug = slugInput ? slugInput.value.trim() : '';
        const label = labelInput ? labelInput.value.trim() : '';
        const emailsStr = emailsInput ? emailsInput.value.trim() : '';

        let valid = true;

        const slugField = slugInput ? slugInput.closest('.ct-admin-field') : null;
        const slugError = document.getElementById('pointer_slug_error');
        if (!slug) {
            if (slugField) { slugField.classList.add('ct-field--invalid'); }
            if (slugError) { slugError.textContent = 'Slug is required.'; }
            valid = false;
        } else {
            if (slugField) { slugField.classList.remove('ct-field--invalid'); }
            if (slugError) { slugError.textContent = ''; }
        }

        const labelField = labelInput ? labelInput.closest('.ct-admin-field') : null;
        const labelError = document.getElementById('pointer_label_error');
        if (!label) {
            if (labelField) { labelField.classList.add('ct-field--invalid'); }
            if (labelError) { labelError.textContent = 'Label is required.'; }
            valid = false;
        } else {
            if (labelField) { labelField.classList.remove('ct-field--invalid'); }
            if (labelError) { labelError.textContent = ''; }
        }

        const emailsField = emailsInput ? emailsInput.closest('.ct-admin-field') : null;
        const emailsError = document.getElementById('pointer_emails_error');
        if (!emailsStr) {
            if (emailsField) { emailsField.classList.add('ct-field--invalid'); }
            if (emailsError) { emailsError.textContent = 'At least one email is required.'; }
            valid = false;
        } else {
            const emails = emailsStr.split(',').map((e) => e.trim()).filter((e) => e.length > 0);
            const invalidEmails = [];
            const maxEmails = 20;
            let count = 0;

            for (let i = 0; i < emails.length; i++) {
                if (count >= maxEmails) { break; }
                count++;
                if (!EMAIL_REGEX.test(emails[i])) {
                    invalidEmails.push(emails[i]);
                }
            }

            if (emails.length === 0) {
                if (emailsField) { emailsField.classList.add('ct-field--invalid'); }
                if (emailsError) { emailsError.textContent = 'At least one email is required.'; }
                valid = false;
            } else if (invalidEmails.length > 0) {
                if (emailsField) { emailsField.classList.add('ct-field--invalid'); }
                if (emailsError) { emailsError.textContent = 'Invalid email: ' + invalidEmails[0]; }
                valid = false;
            } else {
                if (emailsField) { emailsField.classList.remove('ct-field--invalid'); }
                if (emailsError) { emailsError.textContent = ''; }
            }
        }

        const addBtn = document.getElementById('add_pointer_btn');
        const saveEditBtn = document.getElementById('save_pointer_edit_btn');

        if (addBtn) { addBtn.disabled = !valid; }
        if (saveEditBtn) { saveEditBtn.disabled = !valid; }

        return valid;
    }

    async addPointer() {
        const form = document.getElementById('add_contact_pointer_form');
        if (!form) { return; }

        if (!this._validatePointerForm()) {
            return;
        }

        const addBtn = document.getElementById('add_pointer_btn');
        const slug = form.elements['pointer_slug'].value.trim().replace(/\s+/g, '_').toLowerCase();
        const label = form.elements['pointer_label'].value.trim();
        const emailsStr = form.elements['pointer_emails'].value.trim();

        const emails = emailsStr.split(',').map((e) => e.trim()).filter((e) => e.length > 0);

        const listEl = document.getElementById('contact_pointers_list');
        let currentPointers = this._getPointersData(listEl);

        const entry = { slug: slug, label: label, emails: emails };
        const isEditing = this._pointerEditIndex !== null;

        if (isEditing && this._pointerEditIndex >= 0 && this._pointerEditIndex < currentPointers.length) {
            currentPointers[this._pointerEditIndex] = entry;
        } else {
            if (currentPointers.length >= MAX_POINTERS) {
                this._parent.showNotice('Maximum pointers reached.', 'error');
                return;
            }
            currentPointers.push(entry);
        }

        await this.savePointers(currentPointers, listEl, addBtn, isEditing);
        this._clearPointerForm(form);
        this._setPointerFormMode('add');
    }

    editPointer(index) {
        const listEl = document.getElementById('contact_pointers_list');
        const pointers = this._getPointersData(listEl);

        if (index < 0 || index >= pointers.length) { return; }

        const pointer = pointers[index];
        const form = document.getElementById('add_contact_pointer_form');
        if (!form) { return; }

        form.elements['pointer_slug'].value = pointer.slug;
        form.elements['pointer_label'].value = pointer.label;
        form.elements['pointer_emails'].value = (pointer.emails || []).join(', ');

        this._setPointerFormMode('edit');
        this._pointerEditIndex = index;
        this._validatePointerForm();
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    async removePointer(index, triggerBtn) {
        const listEl = document.getElementById('contact_pointers_list');
        let pointers = this._getPointersData(listEl);

        if (index < 0 || index >= pointers.length) { return; }

        pointers.splice(index, 1);

        if (this._pointerEditIndex === index) {
            this._cancelPointerEdit();
        } else if (this._pointerEditIndex !== null && this._pointerEditIndex > index) {
            this._pointerEditIndex--;
        }

        await this.savePointers(pointers, listEl, triggerBtn, false);
    }

    async savePointers(pointers, listEl, btn, isEditing) {
        const nonceField = document.querySelector('#add_contact_pointer_form input[name="admin_save_contact_pointers_nonce"]');
        if (!nonceField) { return; }

        const data = new URLSearchParams({
            nonce: nonceField.value,
            action: 'admin_save_contact_pointers',
            input: JSON.stringify(pointers),
        });

        this._parent.setButtonState(btn, 'loading');

        try {
            const response = await fetch(ajaxurl, { method: 'POST', body: data });
            const result = await response.json();

            if (result.success) {
                this._parent.setButtonState(btn, 'success');
                if (listEl) {
                    listEl.dataset.pointers = JSON.stringify(pointers);
                }
                this.renderPointerList(pointers, listEl);
                this._parent.showNotice(isEditing ? 'Pointer updated.' : 'Pointer saved.', 'success');
            } else {
                this._parent.setButtonState(btn, 'error');
                this._parent.showNotice('Error saving pointer.', 'error');
            }
        } catch {
            this._parent.setButtonState(btn, 'error');
            this._parent.showNotice('Network error.', 'error');
        }
    }

    renderPointerList(pointers, container) {
        if (!container) { return; }

        if (!pointers || pointers.length === 0) {
            container.innerHTML = '<p class="ct-admin-no-items">No pointers configured yet.</p>';
            return;
        }

        let html = '<ul class="ct-admin-pointer-list">';
        const limit = Math.min(pointers.length, MAX_POINTERS);

        for (let i = 0; i < limit; i++) {
            const p = pointers[i];
            html += '<li class="ct-admin-pointer-item">'
                + '<span class="ct-admin-pointer-num">' + (i + 1) + '.</span>'
                + '<span class="ct-admin-pointer-slug">' + this._parent.escapeHtml(p.slug) + '</span>'
                + '<span class="ct-admin-pointer-label">' + this._parent.escapeHtml(p.label) + '</span>'
                + '<span class="ct-admin-pointer-emails">' + this._parent.escapeHtml((p.emails || []).join(', ')) + '</span>'
                + '<div class="ct-admin-pointer-actions">'
                + '<button type="button" class="button ct-admin-edit-pointer" data-index="' + i + '">Edit</button>'
                + '<button type="button" class="button ct-admin-remove-pointer" data-index="' + i + '">Remove</button>'
                + '</div>'
                + '</li>';
        }

        html += '</ul>';
        container.innerHTML = html;
        this._bindPointerListButtons();
    }

    _getPointersData(listEl) {
        if (!listEl || !listEl.dataset.pointers) {
            return [];
        }

        try {
            return JSON.parse(listEl.dataset.pointers);
        } catch {
            return [];
        }
    }

    _clearPointerForm(form) {
        if (!form) { return; }
        form.elements['pointer_slug'].value = '';
        form.elements['pointer_label'].value = '';
        form.elements['pointer_emails'].value = '';

        const fields = form.querySelectorAll('.ct-admin-field');
        const maxFields = 10;
        let count = 0;
        fields.forEach((field) => {
            if (count >= maxFields) { return; }
            count++;
            field.classList.remove('ct-field--invalid');
        });

        const errors = form.querySelectorAll('.ct-pointer-form__error');
        count = 0;
        errors.forEach((err) => {
            if (count >= maxFields) { return; }
            count++;
            err.textContent = '';
        });

        const addBtn = document.getElementById('add_pointer_btn');
        if (addBtn) { addBtn.disabled = true; }
    }

    _setPointerFormMode(mode) {
        const heading = document.getElementById('pointer_form_heading');
        const addBtn = document.getElementById('add_pointer_btn');
        const saveEditBtn = document.getElementById('save_pointer_edit_btn');
        const cancelBtn = document.getElementById('cancel_pointer_edit_btn');

        if (mode === 'edit') {
            if (heading) { heading.textContent = 'Edit Pointer'; }
            if (addBtn) { addBtn.style.display = 'none'; }
            if (saveEditBtn) { saveEditBtn.style.display = ''; }
            if (cancelBtn) { cancelBtn.style.display = ''; }
        } else {
            this._pointerEditIndex = null;
            if (heading) { heading.textContent = 'Add New Pointer'; }
            if (addBtn) { addBtn.style.display = ''; }
            if (saveEditBtn) { saveEditBtn.style.display = 'none'; }
            if (cancelBtn) { cancelBtn.style.display = 'none'; }
        }
    }

    _cancelPointerEdit() {
        const form = document.getElementById('add_contact_pointer_form');
        this._clearPointerForm(form);
        this._setPointerFormMode('add');
    }

    /* ── Helpers ── */

    _getRestNonce() {
        return typeof wpApiSettings !== 'undefined' ? wpApiSettings.nonce : '';
    }
}
