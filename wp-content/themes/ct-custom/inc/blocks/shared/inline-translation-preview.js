/**
 * Inline Translation Preview — Block Editor (vanilla JS).
 *
 * Scans the editor DOM (inside the editor iframe) for ct_translate()
 * patterns and replaces them with yellow chip elements showing the
 * resolved translation. Each chip has an × button to remove the
 * pattern from the block content.
 *
 * Skips the currently selected block so the user can edit raw patterns.
 *
 * Depends on window.ctEditorTranslator (editor-translator.js).
 *
 * @package CT_Custom
 */
(function (wp) {
	'use strict';

	var MARKER        = 'ct_translate(';
	var CHIP_CLASS    = 'ct-inline-tp';
	var STYLE_ID      = 'ct-inline-tp-styles';
	var DEBOUNCE_MS   = 250;
	var MAX_NODES     = 500;
	var MAX_CHIPS     = 50;
	var MAX_ATTRS     = 20;
	var POLL_INTERVAL = 200;
	var POLL_MAX      = 50;

	/** Regex matching complete ct_translate() calls. */
	var PATTERN_RE = /ct_translate\(\s*['"]([A-Z][A-Z0-9_]*)['"]\s*(?:,\s*(?:\[(.*?)\]|\{(.*?)\})\s*(?:,\s*(?:['"]([a-z]+)['"]|(\d+))\s*)?)?\)/g;

	/* ── State ──────────────────────────────────────────── */

	var isApplying      = false;
	var debounceTimer   = null;
	var editorObserver  = null;
	var lastSelectedId  = '';

	/* ── Editor iframe helpers ──────────────────────────── */

	/**
	 * Get the Document that contains the editor content.
	 * WP 6.0+ renders the block editor inside an iframe.
	 *
	 * @return {Document|null}
	 */
	function getEditorDocument() {
		try {
			var iframe = document.querySelector('iframe[name="editor-canvas"]');

			if (iframe && iframe.contentDocument) {
				return iframe.contentDocument;
			}
		} catch (e) {
			/* iframe not ready or cross-origin */
		}

		/* Fallback: editor in main document (older WP) */
		return document;
	}

	/**
	 * Find the editor content wrapper element.
	 *
	 * @return {Element|null}
	 */
	function getEditorRoot() {
		var doc = getEditorDocument();

		return doc.querySelector('.editor-styles-wrapper') ||
		       doc.querySelector('.block-editor-block-list__layout') ||
		       null;
	}

	/**
	 * Get the selected block's DOM element inside the editor.
	 *
	 * @return {Element|null}
	 */
	function getSelectedBlockElement() {
		var store    = wp.data.select('core/block-editor');
		var clientId = store.getSelectedBlockClientId();

		if (!clientId) {
			return null;
		}

		var doc = getEditorDocument();

		return doc.querySelector('[data-block="' + clientId + '"]') || null;
	}

	/* ── CSS injection into iframe ─────────────────────── */

	/**
	 * Ensure chip styles exist in the editor document.
	 * For iframe editors the main document's stylesheet doesn't apply.
	 *
	 * @param {Document} doc Editor document.
	 */
	function ensureStyles(doc) {
		if (doc.getElementById(STYLE_ID)) {
			return;
		}

		var css =
			'.ct-inline-tp{' +
				'display:inline-flex;align-items:center;gap:4px;' +
				'padding:2px 8px 2px 8px;margin:1px 2px;border-radius:4px;' +
				'background:#FFF9C4;border:1px solid #F9A825;' +
				'font-size:inherit;line-height:1.4;color:#1e1e1e;' +
				'cursor:default;vertical-align:baseline;' +
				'font-style:normal;user-select:none;' +
			'}' +
			'.ct-inline-tp__arrow{' +
				'color:#F9A825;font-weight:700;flex-shrink:0;' +
			'}' +
			'.ct-inline-tp__label{' +
				'word-break:break-word;' +
			'}' +
			'.ct-inline-tp__close{' +
				'display:inline-flex;align-items:center;justify-content:center;' +
				'width:16px;height:16px;margin-left:2px;border-radius:50%;' +
				'background:rgba(0,0,0,0.08);color:#888;font-size:12px;' +
				'line-height:1;cursor:pointer;flex-shrink:0;border:none;' +
				'padding:0;font-family:inherit;' +
			'}' +
			'.ct-inline-tp__close:hover{' +
				'background:rgba(0,0,0,0.18);color:#333;' +
			'}';

		var style  = doc.createElement('style');
		style.id   = STYLE_ID;
		style.type = 'text/css';
		style.textContent = css;
		doc.head.appendChild(style);
	}

	/* ── Chip creation ─────────────────────────────────── */

	/**
	 * Create a yellow chip element for a resolved translation.
	 *
	 * @param {string}   patternText  Raw ct_translate(...) string.
	 * @param {string}   resolvedText Resolved translation.
	 * @param {Document} doc          Target document (iframe or main).
	 * @return {Element}
	 */
	function createChip(patternText, resolvedText, doc) {
		var chip = doc.createElement('span');
		chip.className       = CHIP_CLASS;
		chip.contentEditable = 'false';
		chip.setAttribute('data-ct-pattern', patternText);

		var arrow       = doc.createElement('span');
		arrow.className = 'ct-inline-tp__arrow';
		arrow.textContent = '\u2192';

		var label       = doc.createElement('span');
		label.className = 'ct-inline-tp__label';
		label.textContent = resolvedText;

		var close       = doc.createElement('span');
		close.className = 'ct-inline-tp__close';
		close.textContent = '\u00D7';
		close.title     = 'Remove translation';

		close.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();

			/* Find block clientId before detaching */
			var blockEl = findAncestorBlock(chip);
			var cid     = blockEl ? blockEl.getAttribute('data-block') : null;

			/* Remove chip from DOM immediately */
			if (chip.parentNode) {
				chip.parentNode.removeChild(chip);
			}

			/* Remove pattern from block attributes */
			if (cid) {
				removePatternFromBlock(cid, patternText);
			}
		});

		chip.appendChild(arrow);
		chip.appendChild(label);
		chip.appendChild(close);

		return chip;
	}

	/**
	 * Walk up from an element to find the closest [data-block] ancestor.
	 *
	 * @param {Element} el Starting element.
	 * @return {Element|null}
	 */
	function findAncestorBlock(el) {
		var node = el;

		while (node && node.nodeType === 1) {
			if (node.hasAttribute && node.hasAttribute('data-block')) {
				return node;
			}

			node = node.parentElement;
		}

		return null;
	}

	/* ── Pattern removal from block attributes ─────────── */

	/**
	 * Remove a ct_translate() pattern from a block's attributes.
	 *
	 * @param {string} clientId    Block client ID.
	 * @param {string} patternText Raw ct_translate(...) string.
	 */
	function removePatternFromBlock(clientId, patternText) {
		var block = wp.data.select('core/block-editor').getBlock(clientId);

		if (!block || !block.attributes) {
			return;
		}

		var keys    = Object.keys(block.attributes);
		var limit   = Math.min(keys.length, MAX_ATTRS);
		var updated = {};
		var found   = false;

		for (var i = 0; i < limit; i++) {
			var key = keys[i];
			var val = block.attributes[key];
			var str = attrToString(val);

			if (str && str.indexOf(patternText) !== -1) {
				updated[key] = str.replace(patternText, '').trim();
				found = true;
			}
		}

		if (found) {
			wp.data.dispatch('core/block-editor')
			       .updateBlockAttributes(clientId, updated);
		}
	}

	/**
	 * Coerce an attribute value to a plain string.
	 * Handles WP 6.2+ RichTextData objects.
	 *
	 * @param {*} val
	 * @return {string}
	 */
	function attrToString(val) {
		if (typeof val === 'string') {
			return val;
		}

		if (val && typeof val === 'object' &&
			typeof val.toString === 'function' &&
			val.toString !== Object.prototype.toString) {
			return val.toString();
		}

		return '';
	}

	/* ── Clear all chips ───────────────────────────────── */

	/**
	 * Remove all chip elements, restoring original pattern text.
	 */
	function clearAllChips() {
		var doc      = getEditorDocument();
		var chips    = doc.querySelectorAll('.' + CHIP_CLASS);
		var limit    = Math.min(chips.length, MAX_CHIPS * 2);
		var parents  = [];

		for (var i = 0; i < limit; i++) {
			var chip   = chips[i];
			var parent = chip.parentNode;

			if (!parent) {
				continue;
			}

			/* Re-insert pattern text before removing chip */
			var pat = chip.getAttribute('data-ct-pattern');

			if (pat) {
				parent.insertBefore(doc.createTextNode(pat), chip);
			}

			parent.removeChild(chip);

			if (parents.indexOf(parent) === -1) {
				parents.push(parent);
			}
		}

		/* Merge adjacent text nodes */
		for (var j = 0; j < parents.length; j++) {
			if (parents[j].normalize) {
				parents[j].normalize();
			}
		}
	}

	/* ── Core scan & replace ───────────────────────────── */

	/**
	 * Scan the editor DOM for ct_translate() patterns and
	 * replace them with chip elements.
	 */
	function scanAndInsertChips() {
		var root = getEditorRoot();

		if (!root || !window.ctEditorTranslator) {
			return;
		}

		var doc = getEditorDocument();
		ensureStyles(doc);

		var selectedEl = getSelectedBlockElement();

		isApplying = true;

		/* Remove previous chips (restoring pattern text) */
		clearAllChips();

		/* Fast bail: no patterns in editor after restoring text */
		if ((root.textContent || '').indexOf(MARKER) === -1) {
			consumePendingMutations();
			isApplying = false;
			return;
		}

		/* Collect text nodes containing patterns */
		var walker    = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
		var node;
		var count     = 0;
		var chipCount = 0;
		var toReplace = [];

		while ((node = walker.nextNode()) !== null && count < MAX_NODES) {
			count++;

			var text = node.textContent;

			if (!text || text.indexOf(MARKER) === -1) {
				continue;
			}

			/* Skip text inside the selected block */
			if (selectedEl && selectedEl.contains(node)) {
				continue;
			}

			if (chipCount >= MAX_CHIPS) {
				break;
			}

			/* Find all complete patterns in this text node */
			PATTERN_RE.lastIndex = 0;

			var matches = [];
			var match;

			while ((match = PATTERN_RE.exec(text)) !== null) {
				var fullMatch = match[0];
				var resolved  = window.ctEditorTranslator.resolve(fullMatch);

				if (resolved !== fullMatch) {
					matches.push({
						start:    match.index,
						end:      match.index + fullMatch.length,
						pattern:  fullMatch,
						resolved: resolved
					});
					chipCount++;
				}
			}

			if (matches.length > 0) {
				toReplace.push({ node: node, matches: matches });
			}
		}

		/* Replace text nodes with chips */
		for (var i = 0; i < toReplace.length; i++) {
			replaceTextNodeWithChips(
				toReplace[i].node,
				toReplace[i].matches,
				doc
			);
		}

		consumePendingMutations();
		isApplying = false;
	}

	/**
	 * Replace a text node's ct_translate patterns with chip elements.
	 *
	 * @param {Text}     textNode Text node to process.
	 * @param {Object[]} matches  Array of {start, end, pattern, resolved}.
	 * @param {Document} doc      Target document.
	 */
	function replaceTextNodeWithChips(textNode, matches, doc) {
		var parent = textNode.parentNode;

		if (!parent) {
			return;
		}

		var text    = textNode.textContent;
		var frag    = doc.createDocumentFragment();
		var lastEnd = 0;

		for (var i = 0; i < matches.length; i++) {
			var m = matches[i];

			/* Text before this match */
			if (m.start > lastEnd) {
				frag.appendChild(doc.createTextNode(text.substring(lastEnd, m.start)));
			}

			/* Chip for this match */
			frag.appendChild(createChip(m.pattern, m.resolved, doc));
			lastEnd = m.end;
		}

		/* Text after last match */
		if (lastEnd < text.length) {
			frag.appendChild(doc.createTextNode(text.substring(lastEnd)));
		}

		parent.replaceChild(frag, textNode);
	}

	/* ── Debounced scan ────────────────────────────────── */

	function scheduleScan() {
		if (debounceTimer !== null) {
			clearTimeout(debounceTimer);
		}

		debounceTimer = setTimeout(function () {
			debounceTimer = null;
			scanAndInsertChips();
		}, DEBOUNCE_MS);
	}

	/**
	 * Consume pending MutationObserver records so our own DOM
	 * changes don't re-trigger the observer callback.
	 */
	function consumePendingMutations() {
		if (editorObserver) {
			editorObserver.takeRecords();
		}
	}

	/* ── Init & observe ────────────────────────────────── */

	function init() {
		if (!window.ctEditorTranslator) {
			return;
		}

		if (!wp.data || !wp.data.select || !wp.data.subscribe) {
			return;
		}

		var attempts = 0;

		function poll() {
			attempts++;

			if (getEditorRoot()) {
				startObserving();
				return;
			}

			if (attempts < POLL_MAX) {
				setTimeout(poll, POLL_INTERVAL);
			}
		}

		poll();
	}

	/**
	 * Start watching for editor changes.
	 */
	function startObserving() {
		var root = getEditorRoot();
		var doc  = getEditorDocument();

		/* Re-scan when block selection changes */
		wp.data.subscribe(function () {
			var cid = wp.data.select('core/block-editor')
			              .getSelectedBlockClientId() || '';

			if (cid !== lastSelectedId) {
				lastSelectedId = cid;
				scheduleScan();
			}
		});

		/* Re-scan when editor DOM mutates (React re-renders, typing) */
		editorObserver = new MutationObserver(function () {
			if (isApplying) {
				return;
			}

			scheduleScan();
		});

		editorObserver.observe(root, {
			childList:     true,
			subtree:       true,
			characterData: true
		});

		/* Initial scan after two animation frames (let React paint) */
		requestAnimationFrame(function () {
			requestAnimationFrame(function () {
				scanAndInsertChips();
			});
		});
	}

	/* ── Boot ──────────────────────────────────────────── */

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			setTimeout(init, 200);
		});
	} else {
		setTimeout(init, 200);
	}

})(window.wp);
