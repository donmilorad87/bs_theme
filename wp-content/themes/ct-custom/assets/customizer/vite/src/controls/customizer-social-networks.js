/**
 * CustomizerSocialNetworks - Repeater control for managing social
 * network entries (name, URL, icon) inside the WordPress Customizer panel.
 *
 * Syncs data to a hidden textarea bound to the Customizer setting
 * so "Publish" persists changes via the standard Customizer flow.
 *
 * @package BS_Custom
 */

import { assert, escapeHtml } from './utils.js';

var MAX_NETWORKS = 50;

/**
 * @param {string} settingId - The Customizer setting ID.
 */
function CustomizerSocialNetworks(settingId) {
    assert(typeof settingId === 'string', 'settingId must be a string');
    assert(settingId.length > 0, 'settingId must not be empty');

    this.settingId = settingId;
    this.container = null;
    this.textarea = null;
    this.editIndex = -1;
}

CustomizerSocialNetworks.prototype.init = function (container) {
    assert(container instanceof HTMLElement, 'container must be an HTMLElement');

    this.container = container;
    this.textarea = container.parentElement.querySelector('.ct-social-networks-textarea');

    assert(this.textarea instanceof HTMLTextAreaElement, 'textarea must exist');

    this._renderList();
    this._renderForm();
};

/* --- Data access --- */

CustomizerSocialNetworks.prototype._getNetworks = function () {
    var raw = this.textarea.value;
    var parsed = [];

    try {
        parsed = JSON.parse(raw);
    } catch (e) {
        parsed = [];
    }

    if (!Array.isArray(parsed)) {
        parsed = [];
    }

    return parsed;
};

CustomizerSocialNetworks.prototype._setNetworks = function (networks) {
    assert(Array.isArray(networks), 'networks must be an array');

    var json = JSON.stringify(networks);
    this.textarea.value = json;

    /* Push to Customizer so it marks dirty + triggers selective refresh */
    var setting = wp.customize(this.settingId);
    if (setting) {
        setting.set(json);
    }
};

/* --- Rendering --- */

CustomizerSocialNetworks.prototype._renderList = function () {
    var networks = this._getNetworks();
    var listEl = this.container.querySelector('.ct-sn-list');

    if (!listEl) {
        listEl = document.createElement('div');
        listEl.className = 'ct-sn-list';
        this.container.insertBefore(listEl, this.container.firstChild);
    }

    if (networks.length === 0) {
        listEl.innerHTML = '<p class="ct-sn-empty">No social networks added yet.</p>';
        this._bindListButtons();
        return;
    }

    var html = '';
    var count = 0;

    for (var i = 0; i < networks.length; i++) {
        if (count >= MAX_NETWORKS) {
            break;
        }
        count++;

        var n = networks[i];
        var iconHtml = '';

        if (n.icon_url) {
            iconHtml = '<img src="' + escapeHtml(n.icon_url) + '" alt="" class="ct-sn-icon-preview">';
        } else {
            iconHtml = '<span class="ct-sn-icon-placeholder dashicons dashicons-share"></span>';
        }

        html += '<div class="ct-sn-item" data-index="' + i + '">'
            + '<span class="ct-sn-item-icon">' + iconHtml + '</span>'
            + '<span class="ct-sn-item-name">' + escapeHtml(n.name || '') + '</span>'
            + '<span class="ct-sn-item-actions">'
            + '<button type="button" class="button ct-sn-edit" data-index="' + i + '" title="Edit">'
            + '<span class="dashicons dashicons-edit"></span>'
            + '</button>'
            + '<button type="button" class="button ct-sn-remove" data-index="' + i + '" title="Remove">'
            + '<span class="dashicons dashicons-trash"></span>'
            + '</button>'
            + '</span>'
            + '</div>';
    }

    listEl.innerHTML = html;
    this._bindListButtons();
};

CustomizerSocialNetworks.prototype._renderForm = function () {
    var existing = this.container.querySelector('.ct-sn-form');
    if (existing) {
        return;
    }

    var form = document.createElement('div');
    form.className = 'ct-sn-form';

    form.innerHTML = '<div class="ct-sn-form-field">'
        + '<label>Name</label>'
        + '<input type="text" class="ct-sn-input-name" maxlength="100" placeholder="e.g. Facebook">'
        + '</div>'
        + '<div class="ct-sn-form-field">'
        + '<label>URL</label>'
        + '<input type="url" class="ct-sn-input-url" maxlength="500" placeholder="https://">'
        + '</div>'
        + '<div class="ct-sn-form-field">'
        + '<label>Icon</label>'
        + '<div class="ct-sn-icon-row">'
        + '<div class="ct-sn-icon-thumb"></div>'
        + '<button type="button" class="button ct-sn-select-icon">Select Icon</button>'
        + '<button type="button" class="button ct-sn-remove-icon" style="display:none;">Remove</button>'
        + '</div>'
        + '<input type="hidden" class="ct-sn-input-icon-id" value="0">'
        + '<input type="hidden" class="ct-sn-input-icon-url" value="">'
        + '</div>'
        + '<div class="ct-sn-form-field ct-sn-form-errors"></div>'
        + '<div class="ct-sn-form-actions">'
        + '<button type="button" class="button button-primary ct-sn-save">Add Network</button>'
        + '<button type="button" class="button ct-sn-cancel" style="display:none;">Cancel</button>'
        + '</div>';

    this.container.appendChild(form);
    this._bindFormButtons(form);
};

/* --- List event delegation --- */

CustomizerSocialNetworks.prototype._bindListButtons = function () {
    var self = this;
    var listEl = this.container.querySelector('.ct-sn-list');

    if (!listEl) {
        return;
    }

    /* Remove old listeners by cloning */
    var clone = listEl.cloneNode(true);
    listEl.parentNode.replaceChild(clone, listEl);

    clone.addEventListener('click', function (e) {
        var btn = e.target.closest('.ct-sn-edit');
        if (btn) {
            e.preventDefault();
            var idx = parseInt(btn.getAttribute('data-index'), 10);
            self._startEdit(idx);
            return;
        }

        var removeBtn = e.target.closest('.ct-sn-remove');
        if (removeBtn) {
            e.preventDefault();
            var removeIdx = parseInt(removeBtn.getAttribute('data-index'), 10);
            self._removeNetwork(removeIdx);
        }
    });
};

/* --- Form event bindings --- */

CustomizerSocialNetworks.prototype._bindFormButtons = function (form) {
    var self = this;

    form.querySelector('.ct-sn-select-icon').addEventListener('click', function (e) {
        e.preventDefault();
        self._openMediaLibrary(function (id, url) {
            form.querySelector('.ct-sn-input-icon-id').value = id;
            form.querySelector('.ct-sn-input-icon-url').value = url;

            var thumb = form.querySelector('.ct-sn-icon-thumb');
            thumb.innerHTML = '<img src="' + escapeHtml(url) + '" alt="">';

            form.querySelector('.ct-sn-remove-icon').style.display = '';
        });
    });

    form.querySelector('.ct-sn-remove-icon').addEventListener('click', function (e) {
        e.preventDefault();
        form.querySelector('.ct-sn-input-icon-id').value = '0';
        form.querySelector('.ct-sn-input-icon-url').value = '';
        form.querySelector('.ct-sn-icon-thumb').innerHTML = '';
        this.style.display = 'none';
    });

    form.querySelector('.ct-sn-save').addEventListener('click', function (e) {
        e.preventDefault();
        if (self.editIndex >= 0) {
            self._saveEdit();
        } else {
            self._addNetwork();
        }
    });

    form.querySelector('.ct-sn-cancel').addEventListener('click', function (e) {
        e.preventDefault();
        self._cancelEdit();
    });
};

/* --- CRUD operations --- */

CustomizerSocialNetworks.prototype._addNetwork = function () {
    var form = this.container.querySelector('.ct-sn-form');
    var data = this._readFormData(form);
    var errors = this._validateForm(data.name, data.url, data.iconId);

    if (errors.length > 0) {
        this._showErrors(form, errors);
        return;
    }

    var networks = this._getNetworks();

    if (networks.length >= MAX_NETWORKS) {
        this._showErrors(form, ['Maximum of ' + MAX_NETWORKS + ' networks reached.']);
        return;
    }

    networks.push({
        name: data.name,
        url: data.url,
        icon_id: data.iconId,
        icon_url: data.iconUrl
    });

    this._setNetworks(networks);
    this._clearForm(form);
    this._renderList();
};

CustomizerSocialNetworks.prototype._startEdit = function (index) {
    assert(typeof index === 'number', 'index must be a number');

    var networks = this._getNetworks();

    if (index < 0 || index >= networks.length) {
        return;
    }

    this.editIndex = index;
    var n = networks[index];
    var form = this.container.querySelector('.ct-sn-form');

    form.querySelector('.ct-sn-input-name').value = n.name || '';
    form.querySelector('.ct-sn-input-url').value = n.url || '';
    form.querySelector('.ct-sn-input-icon-id').value = n.icon_id || 0;
    form.querySelector('.ct-sn-input-icon-url').value = n.icon_url || '';

    var thumb = form.querySelector('.ct-sn-icon-thumb');
    if (n.icon_url) {
        thumb.innerHTML = '<img src="' + escapeHtml(n.icon_url) + '" alt="">';
        form.querySelector('.ct-sn-remove-icon').style.display = '';
    } else {
        thumb.innerHTML = '';
        form.querySelector('.ct-sn-remove-icon').style.display = 'none';
    }

    form.querySelector('.ct-sn-save').textContent = 'Save Changes';
    form.querySelector('.ct-sn-cancel').style.display = '';

    this._clearErrors(form);
};

CustomizerSocialNetworks.prototype._saveEdit = function () {
    var form = this.container.querySelector('.ct-sn-form');
    var data = this._readFormData(form);
    var errors = this._validateForm(data.name, data.url, data.iconId);

    if (errors.length > 0) {
        this._showErrors(form, errors);
        return;
    }

    var networks = this._getNetworks();

    if (this.editIndex < 0 || this.editIndex >= networks.length) {
        this._cancelEdit();
        return;
    }

    networks[this.editIndex] = {
        name: data.name,
        url: data.url,
        icon_id: data.iconId,
        icon_url: data.iconUrl
    };

    this._setNetworks(networks);
    this._cancelEdit();
    this._renderList();
};

CustomizerSocialNetworks.prototype._cancelEdit = function () {
    this.editIndex = -1;

    var form = this.container.querySelector('.ct-sn-form');

    this._clearForm(form);
    form.querySelector('.ct-sn-save').textContent = 'Add Network';
    form.querySelector('.ct-sn-cancel').style.display = 'none';
};

CustomizerSocialNetworks.prototype._removeNetwork = function (index) {
    assert(typeof index === 'number', 'index must be a number');

    var networks = this._getNetworks();

    if (index < 0 || index >= networks.length) {
        return;
    }

    networks.splice(index, 1);
    this._setNetworks(networks);

    /* Reset edit state if we were editing the removed item */
    if (this.editIndex === index) {
        this._cancelEdit();
    } else if (this.editIndex > index) {
        this.editIndex--;
    }

    this._renderList();
};

/* --- Media library --- */

CustomizerSocialNetworks.prototype._openMediaLibrary = function (callback) {
    assert(typeof callback === 'function', 'callback must be a function');

    var frame = wp.media({
        title: 'Select Social Icon',
        button: { text: 'Use This Icon' },
        multiple: false
    });

    frame.on('select', function () {
        var attachment = frame.state().get('selection').first().toJSON();

        assert(attachment && attachment.id, 'Attachment must have an id');

        callback(attachment.id, attachment.url);
    });

    frame.open();
};

/* --- Validation --- */

CustomizerSocialNetworks.prototype._validateForm = function (name, url, iconId) {
    assert(typeof name === 'string', 'name must be a string');
    assert(typeof url === 'string', 'url must be a string');

    var errors = [];

    if (name.trim() === '') {
        errors.push('Name is required.');
    }

    if (url.trim() === '') {
        errors.push('URL is required.');
    } else if (!/^https?:\/\/.+/i.test(url.trim())) {
        errors.push('URL must start with http:// or https://.');
    }

    return errors;
};

/* --- Form helpers --- */

CustomizerSocialNetworks.prototype._readFormData = function (form) {
    return {
        name: form.querySelector('.ct-sn-input-name').value,
        url: form.querySelector('.ct-sn-input-url').value,
        iconId: parseInt(form.querySelector('.ct-sn-input-icon-id').value, 10) || 0,
        iconUrl: form.querySelector('.ct-sn-input-icon-url').value
    };
};

CustomizerSocialNetworks.prototype._clearForm = function (form) {
    form.querySelector('.ct-sn-input-name').value = '';
    form.querySelector('.ct-sn-input-url').value = '';
    form.querySelector('.ct-sn-input-icon-id').value = '0';
    form.querySelector('.ct-sn-input-icon-url').value = '';
    form.querySelector('.ct-sn-icon-thumb').innerHTML = '';
    form.querySelector('.ct-sn-remove-icon').style.display = 'none';

    this._clearErrors(form);
};

CustomizerSocialNetworks.prototype._showErrors = function (form, errors) {
    assert(Array.isArray(errors), 'errors must be an array');

    var el = form.querySelector('.ct-sn-form-errors');
    var html = '';
    var count = 0;

    for (var i = 0; i < errors.length; i++) {
        if (count >= 5) {
            break;
        }
        count++;
        html += '<p class="ct-sn-error">' + escapeHtml(errors[i]) + '</p>';
    }

    el.innerHTML = html;
};

CustomizerSocialNetworks.prototype._clearErrors = function (form) {
    var el = form.querySelector('.ct-sn-form-errors');
    if (el) {
        el.innerHTML = '';
    }
};

/* --- Bootstrap --- */

export function init() {
    wp.customize.control('bs_custom_social_networks', function (control) {
        control.deferred.embedded.done(function () {
            var container = control.container.find('.ct-social-networks-control')[0];

            if (container) {
                var instance = new CustomizerSocialNetworks('bs_custom_social_networks');
                instance.init(container);
            }
        });
    });
}
