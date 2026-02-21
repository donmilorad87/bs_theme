(function () {
    'use strict';

    var statusTimers = new WeakMap();

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function showStatus(el, message, isError) {
        if (!el) {
            return;
        }
        el.textContent = message || '';
        el.style.color = isError ? '#dc2626' : '#059669';

        var prevTimer = statusTimers.get(el);
        if (prevTimer) {
            clearTimeout(prevTimer);
        }

        var timer = setTimeout(function () {
            el.textContent = '';
        }, 3000);
        statusTimers.set(el, timer);
    }

    function postForm(ajaxUrl, formData) {
        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        }).then(function (response) {
            return response.json();
        });
    }

    function initGeneralSettings() {
        var section = document.querySelector('.ct-general-section');
        if (!section) {
            return;
        }

        var toggle = section.querySelector('#bs_back_to_top_enabled');
        var topbarToggle = section.querySelector('#bs_topbar_enabled');
        var themeToggle = section.querySelector('#bs_theme_toggle_enabled');
        var themeModeInputs = section.querySelectorAll('input[name="bs_theme_color_mode"]');
        var themeModeWrap = section.querySelector('.ct-general-theme-mode');
        var themePositionSelect = section.querySelector('#bs_theme_toggle_position');
        var langPositionSelect = section.querySelector('#bs_lang_switcher_position');
        var languagesToggle = section.querySelector('#bs_languages_enabled');
        var userManagementToggle = section.querySelector('#bs_user_management_enabled');
        var emailToggle = section.querySelector('#bs_email_enabled');
        var contactThrottleLimit = section.querySelector('#bs_contact_throttle_limit');
        var contactThrottleWindow = section.querySelector('#bs_contact_throttle_window');
        var authPositionSelect = section.querySelector('#bs_auth_links_position');
        var jwtSecretInput = document.querySelector('#jwt_secret');
        var jwtExpirationInput = document.querySelector('#jwt_expiration_hours');
        var jwtGenerateBtn = document.querySelector('#generate_jwt_secret_btn');
        var jwtForm = document.querySelector('#jwt_auth_form');
        var languagesNavItem = document.querySelector('label[for="rd_languages"]');
        var languagesNavRadio = document.querySelector('#rd_languages');
        var languagesPanel = document.querySelector('.ct-admin-panel--languages');
        var emailNavItem = document.querySelector('label[for="rd_email"]');
        var emailNavRadio = document.querySelector('#rd_email');
        var emailPanel = document.querySelector('.ct-admin-panel--email');
        var statusEl = section.querySelector('#bs_general_status');
        var nonce = section.getAttribute('data-nonce') || '';
        var ajaxUrl = section.getAttribute('data-ajax-url') || window.ajaxurl || '';

        if (!toggle || !topbarToggle || !themeToggle || !themeModeInputs.length || !themePositionSelect || !nonce || !ajaxUrl) {
            return;
        }

        var saving = false;
        var pending = false;

        function saveGeneral() {
            if (saving) {
                pending = true;
                return;
            }

            saving = true;

            var payload = {
                back_to_top_enabled: !!toggle.checked,
                topbar_enabled: !!topbarToggle.checked,
                theme_toggle_enabled: !!themeToggle.checked,
                theme_color_mode: getThemeMode(),
                theme_toggle_position: themePositionSelect.value || 'header',
            };
            if (langPositionSelect) {
                payload.lang_switcher_position = langPositionSelect.value || 'top_header';
            }
            if (languagesToggle) {
                payload.languages_enabled = !!languagesToggle.checked;
            }
            if (userManagementToggle) {
                payload.user_management_enabled = !!userManagementToggle.checked;
            }
            if (authPositionSelect) {
                payload.auth_links_position = authPositionSelect.value || 'top_header';
            }
            if (emailToggle) {
                payload.email_enabled = !!emailToggle.checked;
            }
            if (contactThrottleLimit) {
                payload.contact_throttle_limit = parseInt(contactThrottleLimit.value, 10) || 1;
            }
            if (contactThrottleWindow) {
                payload.contact_throttle_window = parseInt(contactThrottleWindow.value, 10) || 1;
            }

            var fd = new FormData();
            fd.append('action', 'admin_save_general_settings');
            fd.append('nonce', nonce);
            fd.append('input', JSON.stringify(payload));

            postForm(ajaxUrl, fd)
                .then(function (result) {
                    if (result && result.success) {
                        showStatus(statusEl, result.data && result.data.message ? result.data.message : 'Settings saved.', false);
                        if (window.ctToast && typeof window.ctToast.show === 'function') {
                            window.ctToast.show('General settings saved.', 'success');
                        }
                    } else {
                        showStatus(statusEl, 'Error saving settings.', true);
                        if (window.ctToast && typeof window.ctToast.show === 'function') {
                            window.ctToast.show('Error saving general settings.', 'error');
                        }
                    }
                })
                .catch(function () {
                    showStatus(statusEl, 'Network error.', true);
                    if (window.ctToast && typeof window.ctToast.show === 'function') {
                        window.ctToast.show('Network error saving general settings.', 'error');
                    }
                })
                .finally(function () {
                    saving = false;
                    if (pending) {
                        pending = false;
                        saveGeneral();
                    }
                });
        }

        function getThemeMode() {
            for (var i = 0; i < themeModeInputs.length; i++) {
                if (themeModeInputs[i].checked) {
                    return themeModeInputs[i].value || 'light';
                }
            }
            return 'light';
        }

        function syncThemeModeState() {
            var disabled = !!themeToggle.checked;
            for (var i = 0; i < themeModeInputs.length; i++) {
                themeModeInputs[i].disabled = disabled;
            }
            if (themeModeWrap) {
                themeModeWrap.classList.toggle('ct-general-theme-mode--disabled', disabled);
            }
        }

        function syncAuthPositionState() {
            if (!userManagementToggle || !authPositionSelect) {
                return;
            }
            authPositionSelect.disabled = !userManagementToggle.checked;
        }

        function syncJwtState() {
            if (!userManagementToggle) {
                return;
            }
            var enabled = !!userManagementToggle.checked;
            if (jwtSecretInput) {
                jwtSecretInput.disabled = !enabled;
            }
            if (jwtExpirationInput) {
                jwtExpirationInput.disabled = !enabled;
            }
            if (jwtGenerateBtn) {
                jwtGenerateBtn.disabled = !enabled;
            }
            if (jwtForm) {
                jwtForm.setAttribute('data-user-management-enabled', enabled ? '1' : '0');
            }
        }

        function syncEmailNavState() {
            if (!emailToggle) {
                return;
            }
            var enabled = !!emailToggle.checked;
            if (emailNavItem) {
                emailNavItem.style.display = enabled ? '' : 'none';
            }
            if (emailNavRadio) {
                emailNavRadio.disabled = !enabled;
                if (!enabled && emailNavRadio.checked) {
                    var generalRadio = document.querySelector('#rd_general');
                    if (generalRadio) {
                        generalRadio.checked = true;
                    }
                }
            }
            if (emailPanel) {
                emailPanel.style.display = enabled ? '' : 'none';
            }
        }

        function syncLanguagesNavState() {
            if (!languagesToggle) {
                return;
            }
            var enabled = !!languagesToggle.checked;
            if (languagesNavItem) {
                languagesNavItem.style.display = enabled ? '' : 'none';
            }
            if (languagesNavRadio) {
                languagesNavRadio.disabled = !enabled;
                if (!enabled && languagesNavRadio.checked) {
                    var generalRadio = document.querySelector('#rd_general');
                    if (generalRadio) {
                        generalRadio.checked = true;
                    }
                }
            }
            if (languagesPanel) {
                languagesPanel.style.display = enabled ? '' : 'none';
            }
            if (langPositionSelect) {
                var langField = langPositionSelect.closest('.ct-seo-field');
                if (langField) {
                    langField.style.display = enabled ? '' : 'none';
                }
            }
        }

        function notifyContactSettings() {
            var contactSection = document.querySelector('.ct-contact-admin');
            if (!contactSection) {
                return;
            }
            if (userManagementToggle) {
                contactSection.setAttribute('data-user-management-enabled', userManagementToggle.checked ? '1' : '0');
            }
            if (emailToggle) {
                contactSection.setAttribute('data-email-enabled', emailToggle.checked ? '1' : '0');
            }
            if (typeof CustomEvent === 'function') {
                contactSection.dispatchEvent(new CustomEvent('ct-contact-settings-updated'));
            }
        }

        syncThemeModeState();
        syncAuthPositionState();
        syncJwtState();
        syncEmailNavState();
        syncLanguagesNavState();

        toggle.addEventListener('change', saveGeneral);
        topbarToggle.addEventListener('change', saveGeneral);
        themeToggle.addEventListener('change', function () {
            syncThemeModeState();
            saveGeneral();
        });
        for (var i = 0; i < themeModeInputs.length; i++) {
            themeModeInputs[i].addEventListener('change', saveGeneral);
        }
        themePositionSelect.addEventListener('change', saveGeneral);
        if (langPositionSelect) {
            langPositionSelect.addEventListener('change', saveGeneral);
        }
        if (languagesToggle) {
            languagesToggle.addEventListener('change', function () {
                syncLanguagesNavState();
                saveGeneral();
            });
        }
        if (userManagementToggle) {
            userManagementToggle.addEventListener('change', function () {
                syncAuthPositionState();
                syncJwtState();
                notifyContactSettings();
                saveGeneral();
            });
        }
        if (emailToggle) {
            emailToggle.addEventListener('change', function () {
                syncEmailNavState();
                notifyContactSettings();
                saveGeneral();
            });
        }
        if (contactThrottleLimit) {
            contactThrottleLimit.addEventListener('change', saveGeneral);
        }
        if (contactThrottleWindow) {
            contactThrottleWindow.addEventListener('change', saveGeneral);
        }
        if (authPositionSelect) {
            authPositionSelect.addEventListener('change', saveGeneral);
        }
    }

    function initSocialIcons() {
        var section = document.querySelector('.ct-seo-section');
        var form = document.getElementById('bs_seo_social_icons_form');
        if (!section || !form) {
            return;
        }

        var nonce = section.getAttribute('data-nonce') || '';
        var ajaxUrl = section.getAttribute('data-ajax-url') || window.ajaxurl || '';

        if (!nonce || !ajaxUrl) {
            return;
        }

        var listEl = document.getElementById('bs_social_icons_list');
        var emptyEl = document.getElementById('bs_social_icons_empty');
        var saveBtn = document.getElementById('bs_social_icons_save');
        var statusEl = document.getElementById('bs_social_icons_status');

        var toggleIcons = document.getElementById('bs_social_icons_enabled');
        var toggleShare = document.getElementById('bs_social_share_enabled');

        var nameInput = document.getElementById('bs_social_icon_name');
        var linkInput = document.getElementById('bs_social_icon_link');
        var mediaIdInput = document.getElementById('bs_social_icon_media_id');
        var mediaUrlInput = document.getElementById('bs_social_icon_media_url');
        var previewEl = document.getElementById('social_icon_preview');
        var selectBtn = document.getElementById('bs_social_icon_select');
        var removeBtn = document.getElementById('bs_social_icon_remove');
        var addBtn = document.getElementById('bs_social_icon_add');
        var cancelBtn = document.getElementById('bs_social_icon_cancel');

        var networks = [];
        try {
            networks = JSON.parse(form.getAttribute('data-networks') || '[]');
        } catch (e) {
            networks = [];
        }
        if (!Array.isArray(networks)) {
            networks = [];
        }

        var editIndex = -1;
        var saving = false;
        var pending = false;

        function setPreview(url) {
            if (!previewEl) {
                return;
            }
            if (!url) {
                previewEl.innerHTML = '<span class="ct-admin-no-image">No icon selected.</span>';
                if (removeBtn) {
                    removeBtn.style.display = 'none';
                }
                return;
            }
            previewEl.innerHTML = '<img src="' + escapeHtml(url) + '" alt="">';
            if (removeBtn) {
                removeBtn.style.display = '';
            }
        }

        function resetForm() {
            if (nameInput) {
                nameInput.value = '';
            }
            if (linkInput) {
                linkInput.value = '';
            }
            if (mediaIdInput) {
                mediaIdInput.value = '0';
            }
            if (mediaUrlInput) {
                mediaUrlInput.value = '';
            }
            setPreview('');
            editIndex = -1;
            if (addBtn) {
                addBtn.textContent = 'Add Network';
            }
            if (cancelBtn) {
                cancelBtn.style.display = 'none';
            }
        }

        function fillForm(network) {
            if (!network) {
                return;
            }
            if (nameInput) {
                nameInput.value = network.name || '';
            }
            if (linkInput) {
                linkInput.value = network.url || '';
            }
            if (mediaIdInput) {
                mediaIdInput.value = network.icon_id ? String(network.icon_id) : '0';
            }
            if (mediaUrlInput) {
                mediaUrlInput.value = network.icon_url || '';
            }
            setPreview(network.icon_url || '');
            if (addBtn) {
                addBtn.textContent = 'Save Changes';
            }
            if (cancelBtn) {
                cancelBtn.style.display = '';
            }
        }

        function validateNetwork(name, url) {
            var errors = [];
            if (!name || name.trim() === '') {
                errors.push('Name is required.');
            }
            if (!url || url.trim() === '') {
                errors.push('URL is required.');
            } else if (!/^https?:\/\//i.test(url.trim())) {
                errors.push('URL must start with http:// or https://.');
            }
            return errors;
        }

        function renderList() {
            if (!listEl) {
                return;
            }

            listEl.innerHTML = '';

            if (!Array.isArray(networks) || networks.length === 0) {
                if (emptyEl) {
                    emptyEl.style.display = '';
                }
                return;
            }

            if (emptyEl) {
                emptyEl.style.display = 'none';
            }

            networks.forEach(function (network, index) {
                var li = document.createElement('li');
                li.className = 'ct-admin-social-item';

                var iconHtml = '';
                if (network.icon_url) {
                    iconHtml = '<img class="ct-admin-social-icon" src="' + escapeHtml(network.icon_url) + '" alt="">';
                } else {
                    iconHtml = '<span class="ct-admin-social-icon"></span>';
                }

                li.innerHTML =
                    '<span class="ct-admin-social-num">' + (index + 1) + '</span>' +
                    iconHtml +
                    '<span class="ct-admin-social-name">' + escapeHtml(network.name || '') + '</span>' +
                    '<a class="ct-admin-social-url" href="' + escapeHtml(network.url || '') + '" target="_blank" rel="noopener">' + escapeHtml(network.url || '') + '</a>' +
                    '<span class="ct-admin-social-actions">' +
                    '<button type="button" class="button ct-admin-edit-social" data-index="' + index + '">Edit</button>' +
                    '<button type="button" class="button ct-admin-remove-social" data-index="' + index + '">Remove</button>' +
                    '</span>';

                listEl.appendChild(li);
            });
        }

        function saveSettings() {
            if (saving) {
                pending = true;
                return;
            }

            saving = true;

            var payload = {
                social_icons_enabled: toggleIcons && toggleIcons.checked ? 'on' : 'off',
                share_enabled: !!(toggleShare && toggleShare.checked),
                networks: networks,
            };

            var fd = new FormData();
            fd.append('action', 'admin_save_seo_social_icons');
            fd.append('nonce', nonce);
            fd.append('input', JSON.stringify(payload));

            postForm(ajaxUrl, fd)
                .then(function (result) {
                    if (result && result.success) {
                        showStatus(statusEl, result.data && result.data.message ? result.data.message : 'Social icons saved.', false);
                        if (window.ctToast && typeof window.ctToast.show === 'function') {
                            window.ctToast.show('Social icons saved.', 'success');
                        }
                    } else {
                        showStatus(statusEl, 'Error saving social icons.', true);
                        if (window.ctToast && typeof window.ctToast.show === 'function') {
                            window.ctToast.show('Error saving social icons.', 'error');
                        }
                    }
                })
                .catch(function () {
                    showStatus(statusEl, 'Network error.', true);
                    if (window.ctToast && typeof window.ctToast.show === 'function') {
                        window.ctToast.show('Network error saving social icons.', 'error');
                    }
                })
                .finally(function () {
                    saving = false;
                    if (pending) {
                        pending = false;
                        saveSettings();
                    }
                });
        }

        function handleAddOrUpdate() {
            if (!nameInput || !linkInput) {
                return;
            }

            var nameVal = nameInput.value || '';
            var urlVal = linkInput.value || '';
            var errors = validateNetwork(nameVal, urlVal);

            if (errors.length > 0) {
                if (window.ctToast && typeof window.ctToast.show === 'function') {
                    window.ctToast.show(errors[0], 'error');
                }
                return;
            }

            if (editIndex < 0 && networks.length >= 50) {
                if (window.ctToast && typeof window.ctToast.show === 'function') {
                    window.ctToast.show('Maximum of 50 networks reached.', 'error');
                }
                return;
            }

            var iconIdVal = mediaIdInput ? parseInt(mediaIdInput.value, 10) || 0 : 0;
            var iconUrlVal = mediaUrlInput ? mediaUrlInput.value || '' : '';

            var entry = {
                name: nameVal.trim(),
                url: urlVal.trim(),
                icon_id: iconIdVal,
                icon_url: iconUrlVal,
            };

            if (editIndex >= 0 && editIndex < networks.length) {
                networks[editIndex] = entry;
            } else {
                networks.push(entry);
            }

            renderList();
            resetForm();
            saveSettings();
        }

        if (listEl) {
            listEl.addEventListener('click', function (event) {
                var editBtn = event.target.closest('.ct-admin-edit-social');
                var removeBtnEl = event.target.closest('.ct-admin-remove-social');

                if (editBtn) {
                    event.preventDefault();
                    var idx = parseInt(editBtn.getAttribute('data-index'), 10);
                    if (!isNaN(idx) && networks[idx]) {
                        editIndex = idx;
                        fillForm(networks[idx]);
                    }
                    return;
                }

                if (removeBtnEl) {
                    event.preventDefault();
                    var rIdx = parseInt(removeBtnEl.getAttribute('data-index'), 10);
                    if (!isNaN(rIdx) && networks[rIdx]) {
                        networks.splice(rIdx, 1);
                        renderList();
                        if (editIndex === rIdx) {
                            resetForm();
                        } else if (editIndex > rIdx) {
                            editIndex -= 1;
                        }
                        saveSettings();
                    }
                }
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', function (event) {
                event.preventDefault();
                handleAddOrUpdate();
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function (event) {
                event.preventDefault();
                resetForm();
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', function (event) {
                event.preventDefault();
                saveSettings();
            });
        }

        if (toggleIcons) {
            toggleIcons.addEventListener('change', saveSettings);
        }
        if (toggleShare) {
            toggleShare.addEventListener('change', saveSettings);
        }

        if (selectBtn && typeof wp !== 'undefined' && wp.media) {
            selectBtn.addEventListener('click', function (event) {
                event.preventDefault();
                var frame = wp.media({
                    title: 'Select Social Icon',
                    button: { text: 'Use This Icon' },
                    multiple: false,
                });

                frame.on('select', function () {
                    var selection = frame.state().get('selection').first();
                    if (!selection) {
                        return;
                    }
                    var data = selection.toJSON();
                    if (!data || !data.id) {
                        return;
                    }
                    if (mediaIdInput) {
                        mediaIdInput.value = String(data.id);
                    }
                    if (mediaUrlInput) {
                        mediaUrlInput.value = data.url || '';
                    }
                    setPreview(data.url || '');
                });

                frame.open();
            });
        }

        if (removeBtn) {
            removeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                if (mediaIdInput) {
                    mediaIdInput.value = '0';
                }
                if (mediaUrlInput) {
                    mediaUrlInput.value = '';
                }
                setPreview('');
            });
        }

        renderList();
        resetForm();
    }

    function initContactPoint() {
        var section = document.querySelector('.ct-seo-section');
        var form = document.getElementById('bs_seo_contact_point_form');
        if (!section || !form) {
            return;
        }

        var nonce = section.getAttribute('data-nonce') || '';
        var ajaxUrl = section.getAttribute('data-ajax-url') || window.ajaxurl || '';
        var saveBtn = document.getElementById('bs_seo_contact_point_save');
        var statusEl = document.getElementById('bs_seo_contact_point_status');

        if (!nonce || !ajaxUrl || !saveBtn) {
            return;
        }

        function getVal(id) {
            var el = document.getElementById(id);
            return el ? el.value : '';
        }

        saveBtn.addEventListener('click', function (event) {
            event.preventDefault();

            var payload = {
                company: getVal('bs_cp_company'),
                telephone: getVal('bs_cp_telephone'),
                fax_number: getVal('bs_cp_fax'),
                email: getVal('bs_cp_email'),
                contact_type: getVal('bs_cp_contact_type'),
                address: {
                    street_number: getVal('bs_cp_street_number'),
                    street_address: getVal('bs_cp_street_address'),
                    city: getVal('bs_cp_city'),
                    state: getVal('bs_cp_state'),
                    postal_code: getVal('bs_cp_postal_code'),
                    country: getVal('bs_cp_country'),
                },
            };

            var fd = new FormData();
            fd.append('action', 'admin_save_seo_contact_point');
            fd.append('nonce', nonce);
            fd.append('input', JSON.stringify(payload));

            postForm(ajaxUrl, fd)
                .then(function (result) {
                    if (result && result.success) {
                        showStatus(statusEl, result.data && result.data.message ? result.data.message : 'Contact point saved.', false);
                        if (window.ctToast && typeof window.ctToast.show === 'function') {
                            window.ctToast.show('Contact point saved.', 'success');
                        }
                    } else {
                        showStatus(statusEl, 'Error saving contact point.', true);
                        if (window.ctToast && typeof window.ctToast.show === 'function') {
                            window.ctToast.show('Error saving contact point.', 'error');
                        }
                    }
                })
                .catch(function () {
                    showStatus(statusEl, 'Network error.', true);
                    if (window.ctToast && typeof window.ctToast.show === 'function') {
                        window.ctToast.show('Network error saving contact point.', 'error');
                    }
                });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initGeneralSettings();
        initSocialIcons();
        initContactPoint();
    });
})();
