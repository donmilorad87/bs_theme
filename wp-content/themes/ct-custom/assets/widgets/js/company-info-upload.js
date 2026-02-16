/**
 * Company Info Widget — media upload handler.
 *
 * Handles the "Select Logo" and "Reset to Default" buttons
 * in the CompanyInfoWidget admin form. Uses event delegation
 * on document so dynamically-added widget forms (block editor,
 * Customizer) are handled automatically.
 */
(function () {
    'use strict';

    if (typeof jQuery === 'undefined') {
        return;
    }

    var $ = jQuery;

    /* ── Select Logo ───────────────────────────────────────────── */
    $(document)
        .off('click.ctUploadLogo', '.ct-upload-logo')
        .on('click.ctUploadLogo', '.ct-upload-logo', function (e) {
            e.preventDefault();

            if (typeof wp === 'undefined' || typeof wp.media !== 'function') {
                return;
            }

            var button      = $(this);
            var targetInput = $(button.data('target'));
            var preview     = $(button.data('preview'));
            var removeBtn   = button.siblings('.ct-remove-logo');
            var frameTitle  = button.data('frame-title') || 'Select Logo';
            var resetLabel  = button.data('reset-label') || 'Reset to Default';

            var frame = wp.media({
                title:    frameTitle,
                multiple: false,
                library:  { type: 'image' }
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();

                targetInput.val(attachment.id).trigger('change');

                var url = attachment.sizes && attachment.sizes.thumbnail
                    ? attachment.sizes.thumbnail.url
                    : attachment.url;

                preview.attr('src', url).show();

                if (removeBtn.length) {
                    removeBtn.show();
                } else {
                    button.after(
                        '<button type="button" class="button ct-remove-logo"'
                        + ' data-target="' + button.data('target') + '"'
                        + ' data-preview="' + button.data('preview') + '"'
                        + ' data-customizer-logo="' + (button.data('customizer-logo') || '') + '">'
                        + resetLabel
                        + '</button>'
                    );
                }

                button.siblings('em').hide();
            });

            frame.open();
        });

    /* ── Reset to Default ──────────────────────────────────────── */
    $(document)
        .off('click.ctRemoveLogo', '.ct-remove-logo')
        .on('click.ctRemoveLogo', '.ct-remove-logo', function (e) {
            e.preventDefault();

            var button         = $(this);
            var customizerLogo = button.data('customizer-logo');

            $(button.data('target')).val('0').trigger('change');

            if (customizerLogo) {
                $(button.data('preview')).attr('src', customizerLogo).show();
            } else {
                $(button.data('preview')).hide().attr('src', '');
            }

            button.remove();
        });
})();
