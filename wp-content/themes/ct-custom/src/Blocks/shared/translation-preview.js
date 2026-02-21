/**
 * Translation Preview — Block Editor Plugin (vanilla JS).
 *
 * Adds a "Translation Preview" sidebar panel listing all translations
 * found in the current post.
 *
 * Depends on window.ctEditorTranslator (editor-translator.js).
 *
 * @package BS_Custom
 */
(function (wp) {
	'use strict';

	if ( ! wp.editPost || ! wp.editPost.PluginDocumentSettingPanel ) {
		return;
	}

	var el                        = wp.element.createElement;
	var useMemo                   = wp.element.useMemo;
	var useSelect                 = wp.data.useSelect;
	var registerPlugin            = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
	var __                        = wp.i18n.__;

	var MAX_BLOCKS_SCAN    = 500;
	var MAX_PATTERNS_BLOCK = 50;
	var MAX_ATTR_SCAN      = 20;

	/**
	 * Coerce an attribute value to a plain string.
	 * Handles both plain strings and WP 6.2+ RichTextData objects
	 * (which have toString/valueOf but typeof === 'object').
	 *
	 * @param {*} val Attribute value.
	 * @return {string} Plain string or empty string.
	 */
	function attrToString(val) {
		if (typeof val === 'string') {
			return val;
		}

		/* RichTextData: has toString() that returns HTML string */
		if (val && typeof val === 'object' && typeof val.toString === 'function' && val.toString !== Object.prototype.toString) {
			return val.toString();
		}

		return '';
	}

	/**
	 * Extract all attribute values from a block that might
	 * contain bs_translate() patterns. Handles plain strings
	 * and RichTextData objects. Scans all attributes to support
	 * any block type (core/paragraph, core/details, etc.).
	 *
	 * @param {string} blockName  Block type name.
	 * @param {Object} attributes Block attributes.
	 * @return {string} Concatenated text or empty string.
	 */
	function extractText(blockName, attributes) {
		if (!attributes) {
			return '';
		}

		var parts = [];
		var keys  = Object.keys(attributes);
		var limit = Math.min(keys.length, MAX_ATTR_SCAN);

		for (var i = 0; i < limit; i++) {
			var str = attrToString(attributes[keys[i]]);
			if (str && str.indexOf('bs_translate(') !== -1) {
				parts.push(str);
			}
		}

		return parts.join(' ');
	}

	/* ── Sidebar panel: list all translations ──────────────── */

	function TranslationPreviewPanel() {
		var blocks = useSelect(function (select) {
			return select('core/block-editor').getBlocks();
		}, []);

		var allPatterns = useMemo(function () {
			if (!window.ctEditorTranslator || !blocks) {
				return [];
			}

			var results = [];
			var scanned = 0;
			var stack   = blocks.slice();

			while (stack.length > 0 && scanned < MAX_BLOCKS_SCAN) {
				var block = stack.shift();
				scanned++;

				var text = extractText(block.name, block.attributes);

				if (text && text.indexOf('bs_translate(') !== -1) {
					var found = window.ctEditorTranslator.findPatterns(text);
					var limit = Math.min(found.length, MAX_PATTERNS_BLOCK);

					for (var j = 0; j < limit; j++) {
						results.push(found[j]);
					}
				}

				/* Add inner blocks to scan */
				if (block.innerBlocks && block.innerBlocks.length > 0) {
					var innerLimit = Math.min(block.innerBlocks.length, MAX_BLOCKS_SCAN - scanned);
					for (var k = 0; k < innerLimit; k++) {
						stack.push(block.innerBlocks[k]);
					}
				}
			}

			return results;
		}, [blocks]);

		/* Don't render panel when no patterns exist */
		if (!allPatterns || allPatterns.length === 0) {
			return null;
		}

		var items = [];

		for (var i = 0; i < allPatterns.length; i++) {
			items.push(
				el('li', {
					key:       'item-' + i,
					className: 'ct-translation-preview-panel__item'
				},
					el('span', {
						className: 'ct-translation-preview-panel__key'
					}, allPatterns[i].key),
					el('span', {
						className: 'ct-translation-preview-panel__resolved'
					},
						el('span', {
							className: 'ct-translation-preview-panel__resolved-arrow'
						}, '\u2192'),
						allPatterns[i].resolved
					)
				)
			);
		}

		return el(PluginDocumentSettingPanel, {
			name:  'ct-translation-preview',
			title: __('Translation Preview', 'ct-custom'),
			icon:  'translation'
		},
			el('ul', { className: 'ct-translation-preview-panel__list' }, items)
		);
	}

	registerPlugin('ct-translation-preview', {
		render: TranslationPreviewPanel,
		icon:   null
	});

})(window.wp);
