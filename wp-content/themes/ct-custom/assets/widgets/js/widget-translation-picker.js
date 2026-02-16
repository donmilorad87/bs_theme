/**
 * Widget Translation Picker.
 *
 * Adds a "Pick Key" button next to widget text inputs that opens
 * a searchable dropdown of available translation keys. Selecting
 * a key sets the input value to ct_translate('KEY').
 *
 * Shows a yellow preview element below the input with the resolved
 * translation text when the value contains a ct_translate() pattern.
 *
 * Uses event delegation on document so dynamically-added widget
 * forms (Customizer, block widget editor) work automatically.
 */
(function () {
    'use strict';

    if (typeof jQuery === 'undefined') {
        return;
    }

    var $ = jQuery;
    var MAX_VISIBLE_KEYS = 200;

    /**
     * Get the translation keys array from localized data.
     *
     * @return {string[]}
     */
    function getKeys() {
        if (typeof ctWidgetTranslationPicker !== 'undefined' && Array.isArray(ctWidgetTranslationPicker.keys)) {
            return ctWidgetTranslationPicker.keys;
        }
        return [];
    }

    /**
     * Resolve a ct_translate() pattern to its translated text.
     * Delegates to window.ctEditorTranslator if available.
     *
     * @param {string} value Input value (may contain ct_translate pattern).
     * @return {string|null} Resolved text, or null if not a pattern.
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
     * Update or create the preview element for a .ct-wtp wrapper.
     *
     * @param {jQuery} $wrapper The .ct-wtp container.
     */
    function updatePreview($wrapper) {
        var $target  = $wrapper.find('.ct-wtp__target');
        var value    = $target.val() || '';
        var resolved = resolveTranslation(value);

        var $preview = $wrapper.next('.ct-wtp__preview');

        if (resolved === null || resolved === value) {
            /* No ct_translate pattern or resolver unavailable — hide preview */
            if ($preview.length) {
                $preview.remove();
            }
            return;
        }

        if (!$preview.length) {
            $preview = $(
                '<div class="ct-wtp__preview">' +
                    '<span class="ct-wtp__preview-arrow">\u2192 </span>' +
                    '<span class="ct-wtp__preview-text"></span>' +
                '</div>'
            );
            $wrapper.after($preview);
        }

        $preview.find('.ct-wtp__preview-text').text(resolved);
    }

    /**
     * Render key list items into a dropdown <ul>.
     *
     * @param {jQuery}   $list  The <ul> element.
     * @param {string}   filter Search string (case-insensitive).
     */
    function renderKeys($list, filter) {
        var keys    = getKeys();
        var html    = '';
        var count   = 0;
        var needle  = (filter || '').toUpperCase();
        var maxKeys = keys.length;
        var i       = 0;

        for (i = 0; i < maxKeys; i++) {
            if (count >= MAX_VISIBLE_KEYS) {
                break;
            }

            if (needle && keys[i].toUpperCase().indexOf(needle) === -1) {
                continue;
            }

            var safeKey = keys[i].replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            html += '<li class="ct-wtp__key-item" data-key="' + safeKey + '">' + safeKey + '</li>';
            count++;
        }

        if (count === 0) {
            html = '<li class="ct-wtp__no-results">No keys found</li>';
        }

        $list.html(html);
    }

    /* -- Toggle dropdown ------------------------------------------------ */
    $(document)
        .off('click.ctWtpToggle', '.ct-wtp__pick-btn')
        .on('click.ctWtpToggle', '.ct-wtp__pick-btn', function (e) {
            e.preventDefault();
            e.stopPropagation();

            var $wrapper  = $(this).closest('.ct-wtp');
            var $dropdown = $wrapper.find('.ct-wtp__dropdown');
            var $list     = $wrapper.find('.ct-wtp__key-list');
            var $search   = $wrapper.find('.ct-wtp__search');

            /* Close any other open dropdowns first */
            $('.ct-wtp__dropdown').not($dropdown).hide();

            if ($dropdown.is(':visible')) {
                $dropdown.hide();
                return;
            }

            $search.val('');
            renderKeys($list, '');
            $dropdown.show();
            $search.trigger('focus');
        });

    /* -- Search filter -------------------------------------------------- */
    $(document)
        .off('input.ctWtpSearch', '.ct-wtp__search')
        .on('input.ctWtpSearch', '.ct-wtp__search', function () {
            var $wrapper = $(this).closest('.ct-wtp');
            var $list    = $wrapper.find('.ct-wtp__key-list');

            renderKeys($list, $(this).val());
        });

    /* -- Select key ------------------------------------------------------ */
    $(document)
        .off('click.ctWtpSelect', '.ct-wtp__key-item')
        .on('click.ctWtpSelect', '.ct-wtp__key-item', function (e) {
            e.preventDefault();

            var $item    = $(this);
            var key      = $item.data('key');
            var $wrapper = $item.closest('.ct-wtp');
            var $target  = $wrapper.find('.ct-wtp__target');

            $target.val("ct_translate('" + key + "')").trigger('change');
            $wrapper.find('.ct-wtp__dropdown').hide();

            updatePreview($wrapper);
        });

    /* -- Update preview on manual input --------------------------------- */
    $(document)
        .off('input.ctWtpPreview change.ctWtpPreview', '.ct-wtp__target')
        .on('input.ctWtpPreview change.ctWtpPreview', '.ct-wtp__target', function () {
            var $wrapper = $(this).closest('.ct-wtp');
            updatePreview($wrapper);
        });

    /* -- Close on outside click ----------------------------------------- */
    $(document)
        .off('click.ctWtpOutside')
        .on('click.ctWtpOutside', function (e) {
            if (!$(e.target).closest('.ct-wtp').length) {
                $('.ct-wtp__dropdown').hide();
            }
        });

    /* -- Initialize previews for existing widgets on page load ---------- */
    $(function () {
        $('.ct-wtp').each(function () {
            updatePreview($(this));
        });
    });

    /* -- Re-initialize previews when widgets are updated (WP fires this) */
    $(document).on('widget-updated widget-added', function () {
        setTimeout(function () {
            $('.ct-wtp').each(function () {
                updatePreview($(this));
            });
        }, 100);
    });
})();
