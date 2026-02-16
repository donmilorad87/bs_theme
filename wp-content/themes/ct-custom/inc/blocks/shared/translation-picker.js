/**
 * Translation Picker — Block Editor Component (vanilla JS).
 *
 * Provides a reusable translation key picker for Gutenberg blocks.
 * Supports singular, plural forms, and argumented translations.
 * Shows a live preview of the resolved translation.
 *
 * Usage in a block:
 *   window.ctTranslationPicker.createControl({
 *       label:    'Button Text',
 *       help:     'Optional help text.',
 *       value:    attrs.buttonText,
 *       onChange: function (val) { props.setAttributes({ buttonText: val }); }
 *   });
 *
 * @package BS_Custom
 */
(function (wp) {
    'use strict';

    var el            = wp.element.createElement;
    var Fragment      = wp.element.Fragment;
    var useState      = wp.element.useState;
    var TextControl   = wp.components.TextControl;
    var SelectControl = wp.components.SelectControl;
    var Button        = wp.components.Button;
    var Modal         = wp.components.Modal;
    var __            = wp.i18n.__;

    var MAX_DISPLAY_KEYS = 200;
    var MAX_PLACEHOLDERS = 10;

    /* ── Data helpers ──────────────────────────────── */

    /**
     * Get the translation keys list from PHP.
     *
     * @return {string[]}
     */
    function getKeys() {
        if (window.ctTranslationPickerData &&
            Array.isArray(window.ctTranslationPickerData.keys)) {
            return window.ctTranslationPickerData.keys;
        }

        return [];
    }

    /**
     * Get the full translations dictionary.
     *
     * @return {Object}
     */
    function getTranslations() {
        if (window.ctTranslationPreviewData &&
            window.ctTranslationPreviewData.translations) {
            return window.ctTranslationPreviewData.translations;
        }

        return {};
    }

    /**
     * Check if a translation key has plural forms (object value).
     *
     * @param {string} key
     * @return {boolean}
     */
    function keyHasForms(key) {
        var val = getTranslations()[key];

        return typeof val === 'object' && val !== null;
    }

    /**
     * Get available form names for a key.
     *
     * @param {string} key
     * @return {string[]}
     */
    function getAvailableForms(key) {
        var val = getTranslations()[key];

        if (typeof val !== 'object' || val === null) {
            return [];
        }

        return Object.keys(val);
    }

    /**
     * Detect ##placeholder## patterns in a key's translation strings.
     *
     * @param {string} key
     * @return {string[]} Unique placeholder names.
     */
    function detectPlaceholders(key) {
        var val     = getTranslations()[key];
        var strings = [];
        var seen    = {};
        var result  = [];

        if (typeof val === 'string') {
            strings.push(val);
        } else if (typeof val === 'object' && val !== null) {
            var keys = Object.keys(val);

            for (var i = 0; i < keys.length; i++) {
                if (typeof val[keys[i]] === 'string') {
                    strings.push(val[keys[i]]);
                }
            }
        }

        var re = /##([a-zA-Z0-9_]+)##/g;

        for (var j = 0; j < strings.length; j++) {
            var match;
            re.lastIndex = 0;

            while ((match = re.exec(strings[j])) !== null) {
                if (!seen[match[1]]) {
                    seen[match[1]] = true;
                    result.push(match[1]);

                    if (result.length >= MAX_PLACEHOLDERS) {
                        return result;
                    }
                }
            }
        }

        return result;
    }

    /**
     * Build a ct_translate() pattern string from key, args and form.
     *
     * @param {string} key
     * @param {Object} args  Placeholder → value map.
     * @param {string} form  Plural form name ('' for default/singular).
     * @return {string}
     */
    function buildPattern(key, args, form) {
        var parts = ["ct_translate('", key, "'"];

        /* Collect non-empty args */
        var argKeys  = Object.keys(args);
        var argParts = [];

        for (var i = 0; i < argKeys.length; i++) {
            if (args[argKeys[i]] !== '') {
                argParts.push("'" + argKeys[i] + "'=>'" + args[argKeys[i]] + "'");
            }
        }

        var hasArgs = argParts.length > 0;

        if (hasArgs || form) {
            parts.push(",[");
            parts.push(argParts.join(","));
            parts.push("]");

            if (form) {
                parts.push(",'" + form + "'");
            }
        }

        parts.push(")");

        return parts.join('');
    }

    /**
     * Resolve a pattern to its translated text.
     *
     * @param {string} pattern
     * @return {string}
     */
    function resolvePattern(pattern) {
        if (window.ctEditorTranslator) {
            return window.ctEditorTranslator.resolve(pattern);
        }

        return pattern;
    }

    /* ── Key filter ────────────────────────────────── */

    /**
     * Filter keys by search term.
     *
     * @param {string[]} allKeys
     * @param {string}   term
     * @return {string[]}
     */
    function filterKeys(allKeys, term) {
        if (!term) {
            return allKeys.length <= MAX_DISPLAY_KEYS
                ? allKeys
                : allKeys.slice(0, MAX_DISPLAY_KEYS);
        }

        var lower  = term.toLowerCase();
        var result = [];

        for (var i = 0; i < allKeys.length; i++) {
            if (allKeys[i].toLowerCase().indexOf(lower) !== -1) {
                result.push(allKeys[i]);

                if (result.length >= MAX_DISPLAY_KEYS) {
                    break;
                }
            }
        }

        return result;
    }

    /* ── Config panel (plural/args) ────────────────── */

    /**
     * Build the configuration panel for a key with forms/args.
     *
     * @param {Object} opts
     * @return {Object} React element.
     */
    function ConfigPanel(opts) {
        var key     = opts.configKey;
        var form    = opts.configForm;
        var args    = opts.configArgs;
        var setForm = opts.setForm;
        var setArgs = opts.setArgs;

        var forms        = getAvailableForms(key);
        var placeholders = detectPlaceholders(key);

        /* Form dropdown options */
        var formOptions = [
            { label: __('Default (singular)', 'ct-custom'), value: '' }
        ];

        for (var i = 0; i < forms.length; i++) {
            formOptions.push({ label: forms[i], value: forms[i] });
        }

        /* Live preview */
        var pattern     = buildPattern(key, args, form);
        var resolved    = resolvePattern(pattern);
        var previewText = (resolved !== pattern)
            ? resolved
            : __('(no translation found)', 'ct-custom');

        /* Arg text inputs */
        var argInputs = [];

        for (var j = 0; j < placeholders.length; j++) {
            (function (name) {
                argInputs.push(
                    el(TextControl, {
                        key:         'arg-' + name,
                        label:       name,
                        value:       args[name] || '',
                        placeholder: __('Value for ##', 'ct-custom') + name + '##',
                        onChange:     function (val) {
                            var updated    = {};
                            var existKeys  = Object.keys(args);

                            for (var x = 0; x < existKeys.length; x++) {
                                updated[existKeys[x]] = args[existKeys[x]];
                            }

                            updated[name] = val;
                            setArgs(updated);
                        }
                    })
                );
            })(placeholders[j]);
        }

        return el('div', { className: 'ct-tp-config' },

            /* Back button */
            el(Button, {
                variant:   'tertiary',
                size:      'compact',
                className: 'ct-tp-config__back',
                onClick:   opts.onBack,
                icon:      'arrow-left-alt2'
            }, __('Back to list', 'ct-custom')),

            /* Key display */
            el('div', { className: 'ct-tp-config__key' },
                el('strong', null, __('Key:', 'ct-custom') + ' '),
                el('code', null, key)
            ),

            /* Form selector */
            forms.length > 0
                ? el(SelectControl, {
                      label:     __('Plural Form', 'ct-custom'),
                      value:     form,
                      options:   formOptions,
                      onChange:  setForm,
                      className: 'ct-tp-config__form'
                  })
                : null,

            /* Arg inputs */
            argInputs.length > 0
                ? el('div', { className: 'ct-tp-config__args' },
                      el('p', { className: 'ct-tp-config__args-label' },
                          __('Arguments:', 'ct-custom')
                      ),
                      argInputs
                  )
                : null,

            /* Live preview */
            el('div', { className: 'ct-tp-config__preview' },
                el('span', { className: 'ct-tp-config__preview-label' },
                    __('Preview:', 'ct-custom') + ' '
                ),
                el('span', { className: 'ct-tp-config__preview-value' }, previewText)
            ),

            /* Generated pattern */
            el('div', { className: 'ct-tp-config__pattern' },
                el('code', null, pattern)
            ),

            /* Insert button */
            el(Button, {
                variant:   'primary',
                className: 'ct-tp-config__insert',
                onClick:   opts.onInsert
            }, __('Insert Pattern', 'ct-custom'))
        );
    }

    /* ── Main component ────────────────────────────── */

    function TranslationPickerControl(props) {
        var stOpen    = useState(false);
        var stSearch  = useState('');
        var stCfgKey  = useState(null);
        var stCfgForm = useState('');
        var stCfgArgs = useState({});

        var modalOpen   = stOpen[0];
        var setOpen     = stOpen[1];
        var searchTerm  = stSearch[0];
        var setSearch   = stSearch[1];
        var activeKey   = stCfgKey[0];
        var setKey      = stCfgKey[1];
        var activeForm  = stCfgForm[0];
        var setForm     = stCfgForm[1];
        var activeArgs  = stCfgArgs[0];
        var setArgs     = stCfgArgs[1];

        var hasTranslation = typeof props.value === 'string' &&
                             props.value.indexOf('ct_translate(') !== -1;

        /* ── Actions ── */

        function resetModal() {
            setSearch('');
            setKey(null);
            setForm('');
            setArgs({});
        }

        function onOpenModal() {
            resetModal();
            setOpen(true);
        }

        function onCloseModal() {
            resetModal();
            setOpen(false);
        }

        function insertAndClose(pattern) {
            props.onChange(pattern);
            onCloseModal();
        }

        function onPickKey(key) {
            var hasPlural = keyHasForms(key);
            var hasArgs   = detectPlaceholders(key).length > 0;

            if (hasPlural || hasArgs) {
                setKey(key);
                setForm('');
                setArgs({});
            } else {
                insertAndClose("ct_translate('" + key + "')");
            }
        }

        function onInsertConfigured() {
            insertAndClose(buildPattern(activeKey, activeArgs, activeForm));
        }

        function onBackToList() {
            setKey(null);
            setForm('');
            setArgs({});
        }

        function onClearTranslation() {
            props.onChange('');
        }

        /* ── Preview below input ── */

        var previewEl = null;

        if (hasTranslation && window.ctEditorTranslator) {
            var resolved = resolvePattern(props.value);

            if (resolved !== props.value) {
                previewEl = el('div', { className: 'ct-translation-picker__preview' },
                    el('span', { className: 'ct-translation-picker__preview-arrow' }, '\u2192 '),
                    el('span', { className: 'ct-translation-picker__preview-text' }, resolved)
                );
            }
        }

        /* ── Modal ── */

        var modalEl = null;

        if (modalOpen) {
            var innerContent;

            if (activeKey) {
                /* Configuration panel for plural/argumented key */
                innerContent = el(ConfigPanel, {
                    configKey:  activeKey,
                    configForm: activeForm,
                    configArgs: activeArgs,
                    setForm:    setForm,
                    setArgs:    setArgs,
                    onInsert:   onInsertConfigured,
                    onBack:     onBackToList
                });
            } else {
                /* Key search & list */
                var allKeys      = getKeys();
                var filtered     = filterKeys(allKeys, searchTerm);
                var translations = getTranslations();
                var keyItems     = [];

                for (var k = 0; k < filtered.length; k++) {
                    (function (key) {
                        var isPlural = typeof translations[key] === 'object' &&
                                       translations[key] !== null;

                        keyItems.push(
                            el('button', {
                                key:       key,
                                className: 'ct-translation-picker__key-item',
                                type:      'button',
                                onClick:   function () { onPickKey(key); }
                            },
                                el('span', { className: 'ct-translation-picker__key-name' }, key),
                                isPlural
                                    ? el('span', {
                                          className: 'ct-translation-picker__key-badge'
                                      }, 'plural')
                                    : null
                            )
                        );
                    })(filtered[k]);
                }

                if (keyItems.length === 0) {
                    keyItems.push(
                        el('p', {
                            key:       'empty',
                            className: 'ct-translation-picker__empty'
                        }, __('No matching keys found.', 'ct-custom'))
                    );
                }

                innerContent = el(Fragment, null,
                    el(TextControl, {
                        label:       __('Search keys', 'ct-custom'),
                        value:       searchTerm,
                        onChange:    setSearch,
                        placeholder: __('Type to filter\u2026', 'ct-custom'),
                        className:   'ct-translation-picker__search'
                    }),
                    el('div', { className: 'ct-translation-picker__list' }, keyItems)
                );
            }

            modalEl = el(Modal, {
                title: activeKey
                    ? __('Configure Translation', 'ct-custom')
                    : __('Pick Translation Key', 'ct-custom'),
                onRequestClose: onCloseModal,
                className:      'ct-translation-picker__modal'
            }, innerContent);
        }

        /* ── Render ── */

        return el(Fragment, null,
            el('div', { className: 'ct-translation-picker' },
                el(TextControl, {
                    label:    props.label || '',
                    help:     props.help || '',
                    value:    props.value || '',
                    onChange: props.onChange
                }),
                previewEl,
                el('div', { className: 'ct-translation-picker__actions' },
                    el(Button, {
                        variant:   'secondary',
                        size:      'compact',
                        className: 'ct-translation-picker__pick-btn',
                        onClick:   onOpenModal
                    }, __('Pick Key', 'ct-custom')),
                    hasTranslation
                        ? el(Button, {
                              variant:       'tertiary',
                              size:          'compact',
                              isDestructive: true,
                              className:     'ct-translation-picker__clear-btn',
                              onClick:       onClearTranslation
                          }, __('Clear', 'ct-custom'))
                        : null
                )
            ),
            modalEl
        );
    }

    /* ── Public API ── */

    window.ctTranslationPicker = {
        /**
         * Create a translation picker control element.
         *
         * @param {Object}   props
         * @param {string}   props.label
         * @param {string}   [props.help]
         * @param {string}   props.value
         * @param {Function} props.onChange
         * @return {Object}  React element
         */
        createControl: function (props) {
            return el(TranslationPickerControl, props);
        }
    };

})(window.wp);
