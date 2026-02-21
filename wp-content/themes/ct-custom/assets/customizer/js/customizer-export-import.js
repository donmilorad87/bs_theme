/**
 * CustomizerExportImport - Export/Import control for the WordPress Customizer panel.
 *
 * Renders export button and import file picker inside the Customizer panel.
 * Uses the same AJAX endpoints as the admin page (admin_export_settings,
 * admin_import_settings).
 *
 * Depends on wp.customize, jQuery, and ctExportImportData (localized).
 *
 * @package BS_Custom
 */

(function ($) {
    'use strict';

    var MAX_FILE_SIZE_MB = 10;

    function assert(condition, message) {
        if (!condition) {
            throw new Error('Assertion failed: ' + (message || ''));
        }
    }

    /**
     * @param {string} settingId - The Customizer setting ID.
     */
    function CustomizerExportImport(settingId) {
        assert(typeof settingId === 'string', 'settingId must be a string');
        assert(settingId.length > 0, 'settingId must not be empty');

        this.settingId = settingId;
        this.container = null;
        this._exporting = false;
        this._importing = false;
    }

    CustomizerExportImport.prototype.init = function (container) {
        assert(container instanceof HTMLElement, 'container must be an HTMLElement');

        this.container = container;
        this._render();
    };

    CustomizerExportImport.prototype._render = function () {
        assert(this.container instanceof HTMLElement, 'container must exist');

        var self = this;

        this.container.innerHTML =
            '<div class="ct-ei-control">'

            /* Export */
            + '<div class="ct-ei-section">'
            + '<h4>Export Settings</h4>'
            + '<p>Download all theme settings as a JSON file.</p>'
            + '<button type="button" class="ct-ei-btn ct-ei-export-btn">Export Settings</button>'
            + '<div class="ct-ei-export-status"></div>'
            + '</div>'

            + '<div class="ct-ei-divider"></div>'

            /* Import */
            + '<div class="ct-ei-section">'
            + '<h4>Import Settings</h4>'
            + '<p>Upload a JSON file to restore settings. This will overwrite current values.</p>'
            + '<input type="file" class="ct-ei-file-input" accept=".json">'
            + '<div class="ct-ei-file-info" style="display:none;"></div>'
            + '<button type="button" class="ct-ei-btn ct-ei-import-btn" disabled>Import Settings</button>'
            + '<div class="ct-ei-import-status"></div>'
            + '</div>'

            + '</div>';

        /* Bind export */
        var exportBtn = this.container.querySelector('.ct-ei-export-btn');
        exportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            self._exportSettings();
        });

        /* Bind file input */
        var fileInput = this.container.querySelector('.ct-ei-file-input');
        var importBtn = this.container.querySelector('.ct-ei-import-btn');

        fileInput.addEventListener('change', function () {
            var file = fileInput.files[0];
            var infoEl = self.container.querySelector('.ct-ei-file-info');

            if (file && file.name.indexOf('.json') !== -1) {
                importBtn.disabled = false;
                if (infoEl) {
                    infoEl.textContent = file.name;
                    infoEl.style.display = 'block';
                }
            } else {
                importBtn.disabled = true;
                if (infoEl) {
                    infoEl.style.display = 'none';
                }
            }
        });

        /* Bind import */
        importBtn.addEventListener('click', function (e) {
            e.preventDefault();
            self._importSettings();
        });
    };

    /* ─── Export ─── */

    CustomizerExportImport.prototype._exportSettings = function () {
        if (this._exporting) {
            return;
        }

        assert(typeof ctExportImportData !== 'undefined', 'ctExportImportData must be localized');

        var self = this;
        var statusEl = this.container.querySelector('.ct-ei-export-status');
        var exportBtn = this.container.querySelector('.ct-ei-export-btn');

        this._exporting = true;
        exportBtn.disabled = true;
        this._showStatus(statusEl, 'Exporting...', 'loading');

        $.ajax({
            url: ctExportImportData.ajaxUrl,
            method: 'POST',
            data: {
                nonce: ctExportImportData.exportNonce,
                action: 'admin_export_settings',
                input: '1'
            },
            success: function (result) {
                if (result.success) {
                    var json = JSON.stringify(result.data, null, 2);
                    var blob = new Blob([json], { type: 'application/json' });
                    var url = URL.createObjectURL(blob);

                    var link = document.createElement('a');
                    link.href = url;
                    link.download = 'ct-custom-settings-' + self._getDateStamp() + '.json';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);

                    self._showStatus(statusEl, 'Export complete.', 'success');
                } else {
                    self._showStatus(statusEl, 'Export failed.', 'error');
                }
            },
            error: function () {
                self._showStatus(statusEl, 'Network error.', 'error');
            },
            complete: function () {
                self._exporting = false;
                exportBtn.disabled = false;
                setTimeout(function () {
                    self._clearStatus(statusEl);
                }, 4000);
            }
        });
    };

    /* ─── Import ─── */

    CustomizerExportImport.prototype._importSettings = function () {
        if (this._importing) {
            return;
        }

        assert(typeof ctExportImportData !== 'undefined', 'ctExportImportData must be localized');

        var self = this;
        var fileInput = this.container.querySelector('.ct-ei-file-input');
        var importBtn = this.container.querySelector('.ct-ei-import-btn');
        var statusEl = this.container.querySelector('.ct-ei-import-status');

        if (!fileInput || !fileInput.files[0]) {
            return;
        }

        var file = fileInput.files[0];

        if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024) {
            this._showStatus(statusEl, 'File too large (max ' + MAX_FILE_SIZE_MB + 'MB).', 'error');
            return;
        }

        var reader = new FileReader();

        reader.onload = function (e) {
            var content = e.target.result;
            var parsed;

            try {
                parsed = JSON.parse(content);
            } catch (err) {
                self._showStatus(statusEl, 'Invalid JSON file.', 'error');
                return;
            }

            if (!parsed.theme || parsed.theme !== 'ct-custom') {
                self._showStatus(statusEl, 'Not a valid BS Custom export file.', 'error');
                return;
            }

            self._importing = true;
            importBtn.disabled = true;
            self._showStatus(statusEl, 'Importing...', 'loading');

            $.ajax({
                url: ctExportImportData.ajaxUrl,
                method: 'POST',
                data: {
                    nonce: ctExportImportData.importNonce,
                    action: 'admin_import_settings',
                    input: content
                },
                success: function (result) {
                    if (result.success) {
                        self._showStatus(statusEl, 'Imported. Reloading...', 'success');
                        setTimeout(function () {
                            window.location.reload();
                        }, 1500);
                    } else {
                        var msg = (result.data && result.data.message) ? result.data.message : 'Import failed.';
                        self._showStatus(statusEl, msg, 'error');
                    }
                },
                error: function () {
                    self._showStatus(statusEl, 'Network error.', 'error');
                },
                complete: function () {
                    self._importing = false;
                    importBtn.disabled = false;
                }
            });
        };

        reader.readAsText(file);
    };

    /* ─── Helpers ─── */

    CustomizerExportImport.prototype._showStatus = function (el, message, type) {
        if (!el) {
            return;
        }
        el.textContent = message;
        el.className = 'ct-ei-status ct-ei-status--' + type;
        el.style.display = 'block';
    };

    CustomizerExportImport.prototype._clearStatus = function (el) {
        if (!el) {
            return;
        }
        el.textContent = '';
        el.className = '';
        el.style.display = 'none';
    };

    CustomizerExportImport.prototype._getDateStamp = function () {
        var d = new Date();
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    };

    /* ─── Bootstrap ─── */

    wp.customize.control('bs_export_import', function (control) {
        control.deferred.embedded.done(function () {
            var container = control.container.find('.ct-export-import-control')[0];

            if (container) {
                var instance = new CustomizerExportImport('bs_export_import');
                instance.init(container);
            }
        });
    });

})(jQuery);
