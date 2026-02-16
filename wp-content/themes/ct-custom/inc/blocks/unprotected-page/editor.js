/**
 * Unprotected Page Block — Editor (vanilla JS).
 *
 * Placeholder-only block. When present on a page, logged-in users
 * are redirected to the profile page on the frontend.
 *
 * @package BS_Custom
 */
(function (wp) {
	'use strict';

	var el                = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var __                = wp.i18n.__;

	registerBlockType('ct-custom/unprotected-page', {
		edit: function () {
			var blockProps = useBlockProps({
				className: 'ct-access-block ct-access-block--unprotected',
				style: {
					padding:      '16px 20px',
					background:   '#e8f5e9',
					border:       '2px dashed #4caf50',
					borderRadius: '6px',
					display:      'flex',
					alignItems:   'center',
					gap:          '10px',
				},
			});

			return el('div', blockProps,
				el('span', {
					className: 'dashicons dashicons-unlock',
					style: { fontSize: '22px', color: '#388e3c' },
				}),
				el('span', { style: { fontWeight: '600', color: '#2e7d32' } },
					__('Unprotected Page', 'ct-custom')
				),
				el('span', { style: { color: '#555', marginLeft: '4px' } },
					__('— Only guests (not logged in) can view this page.', 'ct-custom')
				)
			);
		},

		save: function () {
			return null;
		},
	});

})(window.wp);
