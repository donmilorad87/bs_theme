/**
 * Protected Page Block — Editor (vanilla JS).
 *
 * Placeholder-only block. When present on a page, guests
 * are redirected to the login page on the frontend.
 *
 * @package CT_Custom
 */
(function (wp) {
	'use strict';

	var el                = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var __                = wp.i18n.__;

	registerBlockType('ct-custom/protected-page', {
		edit: function () {
			var blockProps = useBlockProps({
				className: 'ct-access-block ct-access-block--protected',
				style: {
					padding:      '16px 20px',
					background:   '#e3f2fd',
					border:       '2px dashed #1976d2',
					borderRadius: '6px',
					display:      'flex',
					alignItems:   'center',
					gap:          '10px',
				},
			});

			return el('div', blockProps,
				el('span', {
					className: 'dashicons dashicons-lock',
					style: { fontSize: '22px', color: '#1565c0' },
				}),
				el('span', { style: { fontWeight: '600', color: '#0d47a1' } },
					__('Protected Page', 'ct-custom')
				),
				el('span', { style: { color: '#555', marginLeft: '4px' } },
					__('— Only logged-in users can view this page.', 'ct-custom')
				)
			);
		},

		save: function () {
			return null;
		},
	});

})(window.wp);
