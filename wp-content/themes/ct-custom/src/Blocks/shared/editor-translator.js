/**
 * Editor Translation Resolver — Block Editor (vanilla JS).
 *
 * Client-side port of Translator.php for live preview in the editor.
 * Reads from window.ctTranslationPreviewData (iso2 + translations dictionary)
 * injected via wp_localize_script.
 *
 * Exposes window.ctEditorTranslator with:
 *   - resolve(text)       — replaces all bs_translate() patterns in a string
 *   - findPatterns(text)  — returns array of {match, key, resolved} objects
 *
 * @package BS_Custom
 */
(function () {
	'use strict';

	var MAX_PATTERN_MATCHES = 200;
	var MAX_ARGS            = 50;

	/**
	 * Regex matching bs_translate('KEY', [.../{...}], 'form'|N) patterns.
	 * Mirrors the PHP regex in Translator::parse_ct_translate_internal().
	 */
	var PATTERN = /bs_translate\(\s*['"]([A-Z][A-Z0-9_]*)['"]\s*(?:,\s*(?:\[(.*?)\]|\{(.*?)\})\s*(?:,\s*(?:['"]([a-z]+)['"]|(\d+))\s*)?)?\)/g;

	/**
	 * Parse key-value pairs from inline args string.
	 *
	 * Supports PHP arrow syntax: 'key' => 'value'
	 * Supports JSON colon syntax: "key": "value"
	 * Mirrors Translator::parse_inline_args().
	 *
	 * @param {string} argsStr Content between delimiters.
	 * @return {Object}
	 */
	function parseInlineArgs(argsStr) {
		if (!argsStr || argsStr.trim() === '') {
			return {};
		}

		var args     = {};
		var argCount = 0;

		/* Try PHP arrow syntax first: 'key' => 'value' or 'key' => 5 */
		var arrowPattern = /['"]([\w]+)['"]\s*=>\s*(?:['"]([^'"]*)['"']|(\d+))/g;
		var arrowMatch;

		while ((arrowMatch = arrowPattern.exec(argsStr)) !== null) {
			if (argCount >= MAX_ARGS) { break; }
			argCount++;

			args[arrowMatch[1]] = (arrowMatch[3] !== undefined && arrowMatch[3] !== '')
				? arrowMatch[3]
				: (arrowMatch[2] !== undefined ? arrowMatch[2] : '');
		}

		/* If no arrow matches, try JSON colon syntax: "key": "value" or "key": 5 */
		if (argCount === 0) {
			var colonPattern = /['"]([\w]+)['"]\s*:\s*(?:['"]([^'"]*)['"']|(\d+))/g;
			var colonMatch;

			while ((colonMatch = colonPattern.exec(argsStr)) !== null) {
				if (argCount >= MAX_ARGS) { break; }
				argCount++;

				args[colonMatch[1]] = (colonMatch[3] !== undefined && colonMatch[3] !== '')
					? colonMatch[3]
					: (colonMatch[2] !== undefined ? colonMatch[2] : '');
			}
		}

		return args;
	}

	/**
	 * Resolve CLDR plural category for a language and count.
	 * Mirrors resolvePluralCategory() from translator.js.
	 *
	 * @param {string} iso2  Language code.
	 * @param {number} count Integer count.
	 * @return {string} Plural category.
	 */
	function resolvePluralCategory(iso2, count) {
		var n      = Math.abs(count);
		var mod10  = n % 10;
		var mod100 = n % 100;

		var noPluralLangs = ['ja', 'zh', 'ko', 'tr', 'vi', 'th', 'id', 'ms'];
		if (noPluralLangs.indexOf(iso2) !== -1) {
			return 'other';
		}

		var frenchLangs = ['fr', 'hi', 'fa'];
		if (frenchLangs.indexOf(iso2) !== -1) {
			return n <= 1 ? 'one' : 'other';
		}

		var eastSlavicLangs = ['sr', 'ru', 'uk', 'be', 'hr', 'bs'];
		if (eastSlavicLangs.indexOf(iso2) !== -1) {
			if (mod10 === 1 && mod100 !== 11) { return 'one'; }
			if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) { return 'few'; }
			return 'other';
		}

		var westSlavicLangs = ['cs', 'sk'];
		if (westSlavicLangs.indexOf(iso2) !== -1) {
			if (n === 1) { return 'one'; }
			if (n >= 2 && n <= 4) { return 'few'; }
			return 'other';
		}

		if (iso2 === 'pl') {
			if (n === 1) { return 'one'; }
			if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) { return 'few'; }
			return 'many';
		}

		if (iso2 === 'ar') {
			if (n === 0) { return 'zero'; }
			if (n === 1) { return 'one'; }
			if (n === 2) { return 'two'; }
			if (mod100 >= 3 && mod100 <= 10) { return 'few'; }
			if (mod100 >= 11 && mod100 <= 99) { return 'many'; }
			return 'other';
		}

		/* Germanic / default */
		return n === 1 ? 'one' : 'other';
	}

	/**
	 * Resolve a single translation key.
	 * Mirrors Translator::resolve_key().
	 *
	 * @param {Object}           translations Full dictionary.
	 * @param {string}           iso2         Language code.
	 * @param {string}           key          Translation key.
	 * @param {Object}           args         Placeholder replacements.
	 * @param {string|number|null} count      Plural count or form name.
	 * @return {string}
	 */
	function resolveKey(translations, iso2, key, args, count) {
		var validForms = ['singular', 'zero', 'one', 'two', 'few', 'many', 'other'];

		if (!translations[key]) {
			return key;
		}

		var value = translations[key];

		/* Plural handling */
		if (typeof value === 'object' && value !== null) {
			var singularFallback = value.singular || key;

			if (count === null || count === undefined) {
				value = singularFallback;
			} else if (typeof count === 'string' && validForms.indexOf(count) !== -1) {
				value = value[count] || singularFallback;
			} else {
				var category = resolvePluralCategory(iso2, Number(count));
				value = value[category] || value.other || singularFallback;
			}
		}

		if (typeof value !== 'string') {
			return key;
		}

		/* Placeholder replacement */
		var argCount = 0;
		for (var placeholder in args) {
			if (!args.hasOwnProperty(placeholder)) { continue; }
			if (argCount >= MAX_ARGS) { break; }
			argCount++;

			var search = '##' + placeholder + '##';
			while (value.indexOf(search) !== -1) {
				value = value.replace(search, String(args[placeholder]));
			}
		}

		/* Strip unresolved placeholders */
		value = value.replace(/##[a-zA-Z0-9_]+##/g, '');

		return value;
	}

	/**
	 * Resolve a single pattern match to its translated string.
	 *
	 * @param {RegExpExecArray} match        Regex match array.
	 * @param {Object}         translations  Full dictionary.
	 * @param {string}         iso2          Language code.
	 * @return {string}
	 */
	function resolveMatch(match, translations, iso2) {
		var key     = match[1];
		var argsStr = '';

		if (match[2] && match[2] !== '') {
			argsStr = match[2];
		} else if (match[3] && match[3] !== '') {
			argsStr = match[3];
		}

		var args  = parseInlineArgs(argsStr);
		var count = null;

		if (match[4] && match[4] !== '') {
			count = match[4]; /* string form name */
		} else if (match[5] !== undefined && match[5] !== '') {
			count = parseInt(match[5], 10); /* numeric count */
		}

		return resolveKey(translations, iso2, key, args, count);
	}

	/**
	 * Get translation data from the localized script object.
	 *
	 * @return {{iso2: string, translations: Object}}
	 */
	function getData() {
		var data = window.ctTranslationPreviewData || {};
		return {
			iso2:         data.iso2 || 'en',
			translations: data.translations || {}
		};
	}

	/**
	 * Replace all bs_translate() patterns in a text string.
	 *
	 * @param {string} text Input text.
	 * @return {string}
	 */
	function resolve(text) {
		if (!text || typeof text !== 'string') {
			return text || '';
		}

		if (text.indexOf('bs_translate(') === -1) {
			return text;
		}

		var d          = getData();
		var matchCount = 0;
		var regex      = new RegExp(PATTERN.source, 'g');

		var result = text.replace(regex, function (fullMatch, p1, p2, p3, p4, p5) {
			if (matchCount >= MAX_PATTERN_MATCHES) {
				return fullMatch;
			}
			matchCount++;

			return resolveMatch([fullMatch, p1, p2, p3, p4, p5], d.translations, d.iso2);
		});

		return result;
	}

	/**
	 * Find all bs_translate() patterns in a text string.
	 *
	 * @param {string} text Input text.
	 * @return {Array<{match: string, key: string, resolved: string}>}
	 */
	function findPatterns(text) {
		if (!text || typeof text !== 'string') {
			return [];
		}

		if (text.indexOf('bs_translate(') === -1) {
			return [];
		}

		var d       = getData();
		var results = [];
		var regex   = new RegExp(PATTERN.source, 'g');
		var m;

		while ((m = regex.exec(text)) !== null) {
			if (results.length >= MAX_PATTERN_MATCHES) {
				break;
			}

			results.push({
				match:    m[0],
				key:      m[1],
				resolved: resolveMatch(m, d.translations, d.iso2)
			});
		}

		return results;
	}

	/* ── Public API ── */
	window.ctEditorTranslator = {
		resolve:      resolve,
		findPatterns: findPatterns
	};

})();
