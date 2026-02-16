/**
 * Sidebar Content Block — Editor (vanilla JS).
 *
 * Provides a container with InnerBlocks for custom sidebar content.
 * Position attribute (left/right) is controlled via InspectorControls.
 * Warns if duplicate blocks share the same position.
 *
 * @package CT_Custom
 */
(function (wp) {
	'use strict';

	var el                = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InnerBlocks       = wp.blockEditor.InnerBlocks;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps      = wp.blockEditor.useBlockProps;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var Notice            = wp.components.Notice;
	var useSelect         = wp.data.useSelect;
	var __                = wp.i18n.__;

	var POSITION_OPTIONS = [
		{ label: __('Left Sidebar', 'ct-custom'),  value: 'left' },
		{ label: __('Right Sidebar', 'ct-custom'), value: 'right' },
	];

	var MAX_BLOCKS_SCAN = 200;

	registerBlockType('ct-custom/sidebar-content', {
		edit: function (props) {
			var position = props.attributes.position;
			var clientId = props.clientId;

			var blockProps = useBlockProps({
				className: 'ct-sidebar-content-editor ct-sidebar-content-editor--' + position,
			});

			var hasDuplicate = useSelect(function (select) {
				var blocks = select('core/block-editor').getBlocks();
				var count  = 0;
				var found  = 0;
				var limit  = Math.min(blocks.length, MAX_BLOCKS_SCAN);

				for (var i = 0; i < limit; i++) {
					if (blocks[i].name !== 'ct-custom/sidebar-content') {
						continue;
					}
					if (blocks[i].attributes.position !== position) {
						continue;
					}
					found++;
					if (found > 1) {
						return true;
					}
				}

				return false;
			}, [position]);

			var positionLabel = position === 'left'
				? __('Left Sidebar', 'ct-custom')
				: __('Right Sidebar', 'ct-custom');

			var duplicateNotice = null;
			if (hasDuplicate) {
				duplicateNotice = el(Notice, {
					status:      'warning',
					isDismissible: false,
					className:   'ct-sidebar-content-editor__notice',
				}, __('Multiple sidebar content blocks share this position. Only the first will be used.', 'ct-custom'));
			}

			return el('div', blockProps,
				el(InspectorControls, null,
					el(PanelBody, {
						title:       __('Sidebar Position', 'ct-custom'),
						initialOpen: true,
					},
						el(SelectControl, {
							label:    __('Position', 'ct-custom'),
							value:    position,
							options:  POSITION_OPTIONS,
							onChange: function (val) {
								props.setAttributes({ position: val });
							},
						})
					)
				),
				el('div', { className: 'ct-sidebar-content-editor__header' },
					el('span', {
						className: 'ct-sidebar-content-editor__label',
					}, positionLabel + ' ' + __('Content', 'ct-custom'))
				),
				duplicateNotice,
				el('div', { className: 'ct-sidebar-content-editor__body' },
					el(InnerBlocks, {
						templateLock: false,
					})
				)
			);
		},

		save: function () {
			return el(InnerBlocks.Content);
		},
	});

})(window.wp);
