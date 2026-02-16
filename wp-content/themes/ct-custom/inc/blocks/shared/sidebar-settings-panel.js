/**
 * Sidebar Settings Panel — Block Editor Plugin (vanilla JS).
 *
 * Adds a "Sidebar Configuration" panel to the Document sidebar
 * with On/Off toggles and auto-detected override status.
 *
 * @package BS_Custom
 */
(function (wp) {
	'use strict';

	var el                        = wp.element.createElement;
	var registerPlugin            = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var ToggleControl             = wp.components.ToggleControl;
	var Button                    = wp.components.Button;
	var useSelect                 = wp.data.useSelect;
	var useDispatch               = wp.data.useDispatch;
	var createBlock               = wp.blocks.createBlock;
	var __                        = wp.i18n.__;

	var META_LEFT  = 'ct_sidebar_left';
	var META_RIGHT = 'ct_sidebar_right';
	var BLOCK_NAME = 'ct-custom/sidebar-content';

	/**
	 * Find the first sidebar-content block matching the given position.
	 *
	 * @param {Array}  blocks   Top-level blocks from the editor.
	 * @param {string} position 'left' or 'right'.
	 * @return {Object|null} Matching block or null.
	 */
	function findSidebarBlock(blocks, position) {
		var max = 200;

		for (var i = 0; i < blocks.length && i < max; i++) {
			var block = blocks[i];

			if (block.name !== BLOCK_NAME) {
				continue;
			}

			var blockPosition = (block.attributes && block.attributes.position)
				? block.attributes.position
				: 'left';

			if (blockPosition === position) {
				return block;
			}
		}

		return null;
	}

	/**
	 * Single sidebar control: toggle + source status + override button.
	 *
	 * @param {Object} props
	 * @param {string} props.label       Display label.
	 * @param {string} props.position    'left' or 'right'.
	 * @param {string} props.metaValue   Current meta value ('on' or 'off').
	 * @param {Function} props.onToggle  Called with new meta value.
	 * @param {Array}  props.blocks      All top-level editor blocks.
	 */
	function SidebarControl(props) {
		var label     = props.label;
		var position  = props.position;
		var isOn      = props.metaValue === 'on';
		var onToggle  = props.onToggle;
		var blocks    = props.blocks;

		var editBlocks = useDispatch('core/block-editor');

		var overrideBlock = findSidebarBlock(blocks, position);
		var hasOverride   = overrideBlock !== null;

		function handleInsertOverride() {
			var newBlock = createBlock(BLOCK_NAME, { position: position });
			editBlocks.insertBlock(newBlock);
		}

		function handleRemoveOverride() {
			if (overrideBlock) {
				editBlocks.removeBlock(overrideBlock.clientId);
			}
		}

		var children = [];

		children.push(
			el(ToggleControl, {
				label:    label,
				checked:  isOn,
				onChange: function (checked) {
					onToggle(checked ? 'on' : 'off');
				},
			})
		);

		if (isOn) {
			var statusStyle = {
				fontSize:   '12px',
				color:      '#757575',
				marginTop:  '-8px',
				marginBottom: '8px',
			};

			if (hasOverride) {
				children.push(
					el('p', { style: statusStyle },
						__('Source: Custom Content (override block found)', 'ct-custom')
					)
				);
				children.push(
					el(Button, {
						variant:  'secondary',
						isSmall:  true,
						onClick:  handleRemoveOverride,
						style:    { marginBottom: '16px' },
					}, __('Remove Override (Use Global)', 'ct-custom'))
				);
			} else {
				children.push(
					el('p', { style: statusStyle },
						__('Source: Global Widget Area', 'ct-custom')
					)
				);
				children.push(
					el(Button, {
						variant:  'secondary',
						isSmall:  true,
						onClick:  handleInsertOverride,
						style:    { marginBottom: '16px' },
					}, __('Override with Custom Content', 'ct-custom'))
				);
			}
		}

		return el('div', null, children);
	}

	function SidebarSettingsPanel() {
		var meta = useSelect(function (select) {
			var postMeta = select('core/editor').getEditedPostAttribute('meta');
			return postMeta || {};
		}, []);

		var blocks = useSelect(function (select) {
			return select('core/block-editor').getBlocks();
		}, []);

		var editPost = useDispatch('core/editor');

		var leftValue  = meta[META_LEFT]  || 'off';
		var rightValue = meta[META_RIGHT] || 'off';

		// Normalize legacy values for display.
		if (leftValue !== 'on' && leftValue !== 'off') {
			leftValue = 'on';
		}
		if (rightValue !== 'on' && rightValue !== 'off') {
			rightValue = 'on';
		}

		function onChangeLeft(value) {
			var update = {};
			update[META_LEFT] = value;
			editPost.editPost({ meta: update });
		}

		function onChangeRight(value) {
			var update = {};
			update[META_RIGHT] = value;
			editPost.editPost({ meta: update });
		}

		return el(PluginDocumentSettingPanel, {
			name:  'ct-sidebar-settings',
			title: __('Sidebar Configuration', 'ct-custom'),
			icon:  'columns',
		},
			el(SidebarControl, {
				label:     __('Left Sidebar', 'ct-custom'),
				position:  'left',
				metaValue: leftValue,
				onToggle:  onChangeLeft,
				blocks:    blocks,
			}),
			el(SidebarControl, {
				label:     __('Right Sidebar', 'ct-custom'),
				position:  'right',
				metaValue: rightValue,
				onToggle:  onChangeRight,
				blocks:    blocks,
			})
		);
	}

	registerPlugin('ct-sidebar-settings', {
		render: SidebarSettingsPanel,
		icon:   null,
	});

})(window.wp);
