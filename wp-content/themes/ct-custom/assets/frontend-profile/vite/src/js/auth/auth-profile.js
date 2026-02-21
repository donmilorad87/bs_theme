/**
 * AuthProfile - Avatar upload, profile save, password change, user messages.
 *
 * Duplicated from frontend/vite/src/js/auth/auth-profile.js for standalone profile bundle.
 *
 * @package BS_Custom
 */

const MAX_MESSAGES = 100;
const MAX_REPLIES = 100;

export default class AuthProfile {
    /**
     * @param {AuthApi}  api          API client instance.
     * @param {Function} showMessage  Function(container, type, text).
     * @param {Function} setLoading   Function(container, loading).
     */
    constructor(api, showMessage, setLoading) {
        this._api = api;
        this._showMessage = showMessage;
        this._setLoading = setLoading;
    }

    /**
     * Handle avatar upload button click.
     *
     * @param {HTMLElement} container The profile container element.
     */
    handleAvatarUpload(container) {
        const fileInput = container.querySelector('.ct-auth-form__avatar-file-input');
        if (!fileInput) { return; }

        fileInput.onchange = () => {
            const file = fileInput.files[0];
            if (!file) { return; }

            const formData = new FormData();
            formData.append('avatar', file);

            this._setLoading(container, true);

            this._api.uploadAuth('profile/upload-avatar', formData)
                .then((data) => {
                    this._setLoading(container, false);
                    if (data.success && data.data) {
                        const avatarImg = container.querySelector('.ct-auth-form__avatar-img');
                        if (avatarImg && data.data.avatar_url) {
                            avatarImg.style.backgroundImage = 'url(' + data.data.avatar_url + ')';
                            avatarImg.innerHTML = '';
                        }
                        this._showMessage(container, 'success', data.message || 'Avatar uploaded.');
                    } else {
                        this._showMessage(container, 'error', data.message || 'Upload failed.');
                    }
                })
                .catch(() => {
                    this._setLoading(container, false);
                    this._showMessage(container, 'error', 'An error occurred. Please try again.');
                });

            fileInput.value = '';
        };

        fileInput.click();
    }

    /**
     * Save profile (first name, last name).
     *
     * @param {HTMLElement} container The profile container element.
     * @param {Function}    onSuccess Called with display_name on success.
     */
    saveProfile(container, onSuccess) {
        const firstName = container.querySelector('input[name="first_name"]');
        const lastName = container.querySelector('input[name="last_name"]');

        if (!firstName || !lastName) { return; }

        const firstVal = firstName.value.trim();
        const lastVal = lastName.value.trim();

        if (!firstVal || !lastVal) {
            this._showMessage(container, 'error', 'Please fill in both name fields.');
            return;
        }

        this._setLoading(container, true);

        this._api.postAuth('profile/update', {
            first_name: firstVal,
            last_name: lastVal,
        })
            .then((data) => {
                this._setLoading(container, false);
                if (data.success) {
                    this._showMessage(container, 'success', data.message || 'Profile updated.');
                    if (onSuccess && data.data) {
                        onSuccess(data.data.display_name);
                    }
                } else {
                    this._showMessage(container, 'error', data.message || 'Update failed.');
                }
            })
            .catch(() => {
                this._setLoading(container, false);
                this._showMessage(container, 'error', 'An error occurred. Please try again.');
            });
    }

    /**
     * Load user messages into the profile container.
     *
     * @param {HTMLElement} container The profile container element.
     */
    loadUserMessages(container) {
        const messagesEl = container.querySelector('#bs_profile_messages');
        if (!messagesEl) { return; }

        this._api.getAuth('contact/user-messages')
            .then((data) => {
                if (!data.success || !data.data || !data.data.messages) {
                    messagesEl.innerHTML = '<p class="ct-auth-form__no-messages fs14">No messages yet.</p>';
                    return;
                }

                const messages = data.data.messages;

                if (messages.length === 0) {
                    messagesEl.innerHTML = '<p class="ct-auth-form__no-messages fs14">No messages yet.</p>';
                    return;
                }

                let html = '';
                const limit = Math.min(messages.length, MAX_MESSAGES);

                for (let i = 0; i < limit; i++) {
                    const msg = messages[i];
                    const date = new Date(msg.date).toLocaleDateString();

                    html += '<div class="ct-auth-form__message-card">';
                    html += '<div class="ct-auth-form__message-card-header">';
                    html += '<span class="ct-auth-form__message-card-date">' + this._escapeHtml(date) + '</span>';
                    html += '<span class="ct-auth-form__message-card-pointer">' + this._escapeHtml(msg.form_label || msg.pointer || '') + '</span>';
                    html += '</div>';
                    html += '<p class="ct-auth-form__message-card-body fs14">' + this._escapeHtml(msg.body) + '</p>';

                    if (msg.replies && msg.replies.length > 0) {
                        const replyLimit = Math.min(msg.replies.length, MAX_REPLIES);
                        for (let r = 0; r < replyLimit; r++) {
                            const reply = msg.replies[r];
                            const replyDate = new Date(reply.date).toLocaleDateString();

                            html += '<div class="ct-auth-form__message-reply">';
                            html += '<div class="ct-auth-form__message-reply-header">';
                            html += '<strong>' + this._escapeHtml(reply.author_name) + '</strong>';
                            html += '<span>' + this._escapeHtml(replyDate) + '</span>';
                            html += '</div>';
                            html += '<p class="m0">' + this._escapeHtml(reply.body) + '</p>';
                            html += '</div>';
                        }
                    }

                    html += '</div>';
                }

                messagesEl.innerHTML = html;
            })
            .catch(() => {
                messagesEl.innerHTML = '<p class="ct-auth-form__no-messages fs14">Could not load messages.</p>';
            });
    }

    /**
     * Escape HTML to prevent XSS.
     *
     * @param {string} str Raw string.
     * @returns {string} Escaped string.
     */
    _escapeHtml(str) {
        if (typeof str !== 'string') { return ''; }
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Change password from profile.
     *
     * @param {HTMLElement} container The profile container element.
     * @param {Function}    onSuccess Called after successful password change.
     */
    changePassword(container, onSuccess) {
        const section = container.querySelector('[data-ct-password-section]') || container;

        const currentPass = container.querySelector('input[name="current_password"]');
        const newPass = container.querySelector('input[name="new_password"]');
        const confirmPass = container.querySelector('input[name="new_password_confirm"]');

        if (!currentPass || !newPass || !confirmPass) { return; }

        const currentVal = currentPass.value;
        const newVal = newPass.value;
        const confirmVal = confirmPass.value;

        if (!currentVal || !newVal || !confirmVal) {
            this._showMessage(section, 'error', 'Please fill in all password fields.');
            return;
        }

        if (currentVal === newVal) {
            this._showMessage(section, 'error', 'New password must be different from your current password.');
            return;
        }

        if (newVal !== confirmVal) {
            this._showMessage(section, 'error', 'New passwords do not match.');
            return;
        }

        if (newVal.length < 8) {
            this._showMessage(section, 'error', 'Password must be at least 8 characters.');
            return;
        }

        this._setLoading(section, true);

        this._api.postAuth('profile/change-password', {
            current_password: currentVal,
            new_password: newVal,
            new_password_confirm: confirmVal,
        })
            .then((data) => {
                this._setLoading(section, false);
                if (data.success) {
                    this._showMessage(section, 'success', data.message || 'Password changed.');
                    currentPass.value = '';
                    newPass.value = '';
                    confirmPass.value = '';
                    if (onSuccess) { onSuccess(); }
                } else {
                    this._showMessage(section, 'error', data.message || 'Password change failed.');
                }
            })
            .catch(() => {
                this._setLoading(section, false);
                this._showMessage(section, 'error', 'An error occurred. Please try again.');
            });
    }
}
