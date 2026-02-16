/**
 * Admin Page Block — Editor (vanilla JS).
 *
 * Placeholder-only block. When present on a page, non-admin users
 * are redirected away on the frontend.
 *
 * @package CT_Custom
 */
(function (wp) {
	'use strict';

	var el                = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var __                = wp.i18n.__;

	registerBlockType('ct-custom/admin-page', {
		edit: function () {
			var blockProps = useBlockProps({
				className: 'ct-access-block ct-access-block--admin',
				style: {
					padding:      '16px 20px',
					background:   '#fff3e0',
					border:       '2px dashed #e65100',
					borderRadius: '6px',
					display:      'flex',
					alignItems:   'center',
					gap:          '10px',
				},
			});

			return el('div', blockProps,
				el('span', {
					className: 'dashicons dashicons-shield',
					style: { fontSize: '22px', color: '#e65100' },
				}),
				el('span', { style: { fontWeight: '600', color: '#bf360c' } },
					__('Admin Page', 'ct-custom')
				),
				el('span', { style: { color: '#555', marginLeft: '4px' } },
					__('— Only administrators can view this page.', 'ct-custom')
				)
			);
		},

		save: function () {
			return null;
		},
	});

})(window.wp);
