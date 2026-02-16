/**
 * Customizer Translation Control.
 *
 * Handles the "Pick Key" button, searchable dropdown, and value setting
 * for CT_Translation_Control in the Customizer panel.
 *
 * @package CT_Custom
 */

export function init() {
    if (typeof wp === 'undefined' || !wp.customize) {
        return;
    }

    wp.customize.controlConstructor.ct_translation = wp.customize.Control.extend({
        ready: function () {
            var control = this;
            var container = control.container[0] || control.container;

            var input    = container.querySelector('.ct-translation-control__input');
            var pickBtn  = container.querySelector('.ct-translation-control__pick-btn');
            var dropdown = container.querySelector('.ct-translation-control__dropdown');
            var search   = container.querySelector('.ct-translation-control__search');
            var keyList  = container.querySelector('.ct-translation-control__key-list');

            if (!input || !pickBtn || !dropdown || !keyList) {
                return;
            }

            var keys = control.params.translationKeys || [];
            var isOpen = false;
            var previewEl = null;

            /**
             * Resolve a ct_translate() pattern to its translated text.
             */
            function resolveTranslation(value) {
                if (!value || value.indexOf('ct_translate(') === -1) {
                    return null;
                }
                if (window.ctEditorTranslator && typeof window.ctEditorTranslator.resolve === 'function') {
                    return window.ctEditorTranslator.resolve(value);
                }
                return null;
            }

            /**
             * Show, update, or hide the yellow preview element.
             */
            function updatePreview() {
                var value    = input.value || '';
                var resolved = resolveTranslation(value);

                if (resolved === null || resolved === value) {
                    if (previewEl) {
                        previewEl.style.display = 'none';
                    }
                    return;
                }

                if (!previewEl) {
                    previewEl = document.createElement('div');
                    previewEl.className = 'ct-translation-control__preview';

                    var arrow = document.createElement('span');
                    arrow.className = 'ct-translation-control__preview-arrow';
                    arrow.textContent = '\u2192 ';

                    var text = document.createElement('span');
                    text.className = 'ct-translation-control__preview-text';

                    previewEl.appendChild(arrow);
                    previewEl.appendChild(text);

                    var ctrlEl = container.querySelector('.ct-translation-control');
                    if (ctrlEl) {
                        ctrlEl.appendChild(previewEl);
                    }
                }

                previewEl.querySelector('.ct-translation-control__preview-text').textContent = resolved;
                previewEl.style.display = 'flex';
            }

            /* Render key list */
            function renderKeys(filter) {
                keyList.innerHTML = '';
                var lower = (filter || '').toLowerCase();
                var max = 500;
                var count = 0;

                for (var i = 0; i < keys.length; i++) {
                    if (count >= max) { break; }

                    var key = keys[i];
                    if (lower && key.toLowerCase().indexOf(lower) === -1) {
                        continue;
                    }
                    count++;

                    var li = document.createElement('li');
                    li.className = 'ct-translation-control__key-item';
                    li.textContent = key;
                    li.dataset.key = key;

                    li.addEventListener('click', (function (k) {
                        return function () {
                            input.value = "ct_translate('" + k + "')";
                            control.setting.set(input.value);
                            closeDropdown();
                            updatePreview();
                        };
                    })(key));

                    keyList.appendChild(li);
                }

                if (count === 0) {
                    keyList.innerHTML = '<li class="ct-translation-control__key-empty">No keys found</li>';
                }
            }

            function openDropdown() {
                isOpen = true;
                dropdown.style.display = 'block';
                renderKeys('');
                if (search) { search.value = ''; search.focus(); }
            }

            function closeDropdown() {
                isOpen = false;
                dropdown.style.display = 'none';
            }

            pickBtn.addEventListener('click', function () {
                isOpen ? closeDropdown() : openDropdown();
            });

            if (search) {
                search.addEventListener('input', function () {
                    renderKeys(search.value);
                });
            }

            /* Close on outside click */
            document.addEventListener('click', function (e) {
                if (isOpen && !container.contains(e.target)) {
                    closeDropdown();
                }
            });

            /* Sync text input changes and update preview */
            input.addEventListener('input', function () {
                control.setting.set(input.value);
                updatePreview();
            });

            /* Initialize preview for existing value */
            updatePreview();
        }
    });
}
