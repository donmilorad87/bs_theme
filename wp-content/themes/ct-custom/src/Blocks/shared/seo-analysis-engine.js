/**
 * SEO Analysis Engine — Pure Function Module
 *
 * Exposes window.bsSeoAnalysis.analyze(data) which returns a score and
 * check-list array for the SEO sidebar panel.
 *
 * All loops use bounded iteration (NASA Power-of-10 compliant).
 * Zero external dependencies.
 *
 * @package BS_Custom
 */
(function () {
	'use strict';

	/* ── Upper-bound constants (Power-of-10 rule 2) ───────────────── */
	var MAX_WORDS     = 100000;
	var MAX_SENTENCES = 50000;
	var MAX_IMAGES    = 5000;
	var MAX_HEADINGS  = 500;
	var MAX_LINKS     = 10000;
	var MAX_CHARS     = 500000;

	/* ── Helpers ──────────────────────────────────────────────────── */

	/**
	 * Strip HTML tags from a string using a bounded loop.
	 *
	 * @param {string} html Raw HTML string.
	 * @return {string} Plain text.
	 */
	function stripTags(html) {
		if (typeof html !== 'string') {
			return '';
		}

		var result = '';
		var inTag  = false;
		var len    = html.length < MAX_CHARS ? html.length : MAX_CHARS;

		for (var i = 0; i < len; i++) {
			var ch = html.charAt(i);

			if (ch === '<') {
				inTag = true;
				continue;
			}
			if (ch === '>') {
				inTag = false;
				result += ' ';
				continue;
			}
			if (!inTag) {
				result += ch;
			}
		}

		return result;
	}

	/**
	 * Count words in plain text.
	 *
	 * @param {string} text Plain text.
	 * @return {number} Word count.
	 */
	function countWords(text) {
		if (typeof text !== 'string' || text.trim() === '') {
			return 0;
		}

		var words = text.trim().split(/\s+/);
		var count = words.length;

		return count > MAX_WORDS ? MAX_WORDS : count;
	}

	/**
	 * Count syllables in a word using a simple vowel-group heuristic.
	 *
	 * @param {string} word Single word.
	 * @return {number} Estimated syllable count (minimum 1).
	 */
	function countSyllables(word) {
		if (typeof word !== 'string' || word.length === 0) {
			return 1;
		}

		var w       = word.toLowerCase().replace(/[^a-z]/g, '');
		var len     = w.length < 100 ? w.length : 100;
		var count   = 0;
		var prevVow = false;
		var vowels  = 'aeiouy';

		for (var i = 0; i < len; i++) {
			var isVowel = vowels.indexOf(w.charAt(i)) !== -1;

			if (isVowel && !prevVow) {
				count++;
			}
			prevVow = isVowel;
		}

		/* Silent e at end */
		if (w.length > 2 && w.charAt(w.length - 1) === 'e') {
			count--;
		}

		return count < 1 ? 1 : count;
	}

	/**
	 * Split text into sentences.
	 *
	 * @param {string} text Plain text.
	 * @return {string[]} Array of sentence strings.
	 */
	function splitSentences(text) {
		if (typeof text !== 'string' || text.trim() === '') {
			return [];
		}

		var raw    = text.split(/[.!?]+/);
		var result = [];
		var max    = raw.length < MAX_SENTENCES ? raw.length : MAX_SENTENCES;

		for (var i = 0; i < max; i++) {
			var s = raw[i].trim();

			if (s.length > 0) {
				result.push(s);
			}
		}

		return result;
	}

	/**
	 * Calculate Flesch Reading Ease score.
	 *
	 * Formula: 206.835 - 1.015 * (words/sentences) - 84.6 * (syllables/words)
	 *
	 * @param {string} text Plain text content.
	 * @return {number} Flesch score (0-100 range, clamped).
	 */
	function fleschReadingEase(text) {
		var sentences    = splitSentences(text);
		var sentenceCount = sentences.length;

		if (sentenceCount === 0) {
			return 0;
		}

		var words     = text.trim().split(/\s+/);
		var wordCount = words.length < MAX_WORDS ? words.length : MAX_WORDS;

		if (wordCount === 0) {
			return 0;
		}

		var totalSyllables = 0;

		for (var i = 0; i < wordCount; i++) {
			totalSyllables += countSyllables(words[i]);
		}

		var avgSentenceLength = wordCount / sentenceCount;
		var avgSyllablesWord  = totalSyllables / wordCount;
		var score             = 206.835 - (1.015 * avgSentenceLength) - (84.6 * avgSyllablesWord);

		/* Clamp to 0-100 */
		if (score < 0) { score = 0; }
		if (score > 100) { score = 100; }

		return Math.round(score);
	}

	/**
	 * Check if a keyword appears in text (case-insensitive).
	 *
	 * @param {string} text    Haystack.
	 * @param {string} keyword Needle.
	 * @return {boolean} True if keyword found.
	 */
	function containsKeyword(text, keyword) {
		if (typeof text !== 'string' || typeof keyword !== 'string') {
			return false;
		}

		if (keyword.trim() === '') {
			return false;
		}

		return text.toLowerCase().indexOf(keyword.toLowerCase().trim()) !== -1;
	}

	/**
	 * Count occurrences of keyword in text (case-insensitive, bounded).
	 *
	 * @param {string} text    Haystack.
	 * @param {string} keyword Needle.
	 * @return {number} Occurrence count.
	 */
	function countKeyword(text, keyword) {
		if (typeof text !== 'string' || typeof keyword !== 'string') {
			return 0;
		}

		var kw = keyword.toLowerCase().trim();

		if (kw === '') {
			return 0;
		}

		var lower = text.toLowerCase();
		var count = 0;
		var pos   = 0;
		var max   = MAX_WORDS;

		for (var i = 0; i < max; i++) {
			var idx = lower.indexOf(kw, pos);

			if (idx === -1) {
				break;
			}

			count++;
			pos = idx + kw.length;
		}

		return count;
	}

	/* ── Individual Check Functions ───────────────────────────────── */

	/**
	 * Check 1: Focus keyword in title.
	 */
	function checkKeywordInTitle(data) {
		var keyword = data.focusKeyword || '';
		var title   = data.title || '';

		if (keyword.trim() === '') {
			return { id: 'keyword_in_title', label: 'Focus keyword in title', status: 'warning', detail: 'No focus keyword set.' };
		}

		if (containsKeyword(title, keyword)) {
			return { id: 'keyword_in_title', label: 'Focus keyword in title', status: 'good', detail: 'Focus keyword found in title.' };
		}

		return { id: 'keyword_in_title', label: 'Focus keyword in title', status: 'bad', detail: 'Focus keyword not found in title.' };
	}

	/**
	 * Check 2: Focus keyword in meta description.
	 */
	function checkKeywordInDescription(data) {
		var keyword     = data.focusKeyword || '';
		var description = data.description || '';

		if (keyword.trim() === '') {
			return { id: 'keyword_in_description', label: 'Focus keyword in description', status: 'warning', detail: 'No focus keyword set.' };
		}

		if (containsKeyword(description, keyword)) {
			return { id: 'keyword_in_description', label: 'Focus keyword in description', status: 'good', detail: 'Focus keyword found in meta description.' };
		}

		return { id: 'keyword_in_description', label: 'Focus keyword in description', status: 'bad', detail: 'Focus keyword not found in meta description.' };
	}

	/**
	 * Check 3: Focus keyword in URL slug.
	 */
	function checkKeywordInUrl(data) {
		var keyword = data.focusKeyword || '';
		var url     = data.url || '';

		if (keyword.trim() === '') {
			return { id: 'keyword_in_url', label: 'Focus keyword in URL', status: 'warning', detail: 'No focus keyword set.' };
		}

		/* Compare slug portion with keyword, dashes replaced with spaces */
		var slug = url.replace(/[-_]/g, ' ');

		if (containsKeyword(slug, keyword)) {
			return { id: 'keyword_in_url', label: 'Focus keyword in URL', status: 'good', detail: 'Focus keyword found in URL slug.' };
		}

		return { id: 'keyword_in_url', label: 'Focus keyword in URL', status: 'bad', detail: 'Focus keyword not found in URL slug.' };
	}

	/**
	 * Check 4: Focus keyword appears in content.
	 */
	function checkKeywordInContent(data) {
		var keyword = data.focusKeyword || '';
		var content = data.content || '';

		if (keyword.trim() === '') {
			return { id: 'keyword_in_content', label: 'Focus keyword in content', status: 'warning', detail: 'No focus keyword set.' };
		}

		var plainText = stripTags(content);

		if (containsKeyword(plainText, keyword)) {
			return { id: 'keyword_in_content', label: 'Focus keyword in content', status: 'good', detail: 'Focus keyword found in content.' };
		}

		return { id: 'keyword_in_content', label: 'Focus keyword in content', status: 'bad', detail: 'Focus keyword not found in content.' };
	}

	/**
	 * Check 5: Keyword density 1-3%.
	 */
	function checkKeywordDensity(data) {
		var keyword = data.focusKeyword || '';
		var content = data.content || '';

		if (keyword.trim() === '') {
			return { id: 'keyword_density', label: 'Keyword density', status: 'warning', detail: 'No focus keyword set.' };
		}

		var plainText  = stripTags(content);
		var wordCount  = countWords(plainText);

		if (wordCount === 0) {
			return { id: 'keyword_density', label: 'Keyword density', status: 'bad', detail: 'No content to analyze.' };
		}

		var kwCount    = countKeyword(plainText, keyword);

		if (kwCount === 0) {
			return { id: 'keyword_density', label: 'Keyword density', status: 'bad', detail: 'Keyword density is 0%. Aim for 1-3%.' };
		}

		/* Keyword phrase may consist of multiple words */
		var kwWordCount = keyword.trim().split(/\s+/).length;
		var density     = (kwCount * kwWordCount / wordCount) * 100;
		var rounded     = Math.round(density * 10) / 10;

		if (density >= 1 && density <= 3) {
			return { id: 'keyword_density', label: 'Keyword density', status: 'good', detail: 'Keyword density: ' + rounded + '% (optimal 1-3%).' };
		}

		return { id: 'keyword_density', label: 'Keyword density', status: 'warning', detail: 'Keyword density: ' + rounded + '%. Aim for 1-3%.' };
	}

	/**
	 * Check 6: Title length 30-60 characters.
	 */
	function checkTitleLength(data) {
		var title = data.title || '';
		var len   = title.length;

		if (len === 0) {
			return { id: 'title_length', label: 'Title length', status: 'bad', detail: 'No title set.' };
		}

		if (len >= 30 && len <= 60) {
			return { id: 'title_length', label: 'Title length', status: 'good', detail: 'Title length: ' + len + ' characters (optimal 30-60).' };
		}

		return { id: 'title_length', label: 'Title length', status: 'warning', detail: 'Title length: ' + len + ' characters. Aim for 30-60.' };
	}

	/**
	 * Check 7: Meta description length 120-160 characters.
	 */
	function checkDescriptionLength(data) {
		var description = data.description || '';
		var len         = description.length;

		if (len === 0) {
			return { id: 'description_length', label: 'Description length', status: 'bad', detail: 'No meta description set.' };
		}

		if (len >= 120 && len <= 160) {
			return { id: 'description_length', label: 'Description length', status: 'good', detail: 'Description length: ' + len + ' characters (optimal 120-160).' };
		}

		return { id: 'description_length', label: 'Description length', status: 'warning', detail: 'Description length: ' + len + ' characters. Aim for 120-160.' };
	}

	/**
	 * Check 8: Content length (300+ words good, 100-300 warning, <100 bad).
	 */
	function checkContentLength(data) {
		var content   = data.content || '';
		var plainText = stripTags(content);
		var wordCount = countWords(plainText);

		if (wordCount >= 300) {
			return { id: 'content_length', label: 'Content length', status: 'good', detail: wordCount + ' words (300+ recommended).' };
		}

		if (wordCount >= 100) {
			return { id: 'content_length', label: 'Content length', status: 'warning', detail: wordCount + ' words. Aim for 300+ words.' };
		}

		return { id: 'content_length', label: 'Content length', status: 'bad', detail: wordCount + ' words. Aim for at least 300 words.' };
	}

	/**
	 * Check 9: All images have alt text.
	 */
	function checkImageAlt(data) {
		var images = data.images || [];
		var total  = images.length < MAX_IMAGES ? images.length : MAX_IMAGES;

		if (total === 0) {
			return { id: 'image_alt', label: 'Image alt text', status: 'warning', detail: 'No images found in content.' };
		}

		var missing = 0;

		for (var i = 0; i < total; i++) {
			var img = images[i];
			var alt = (img && typeof img.alt === 'string') ? img.alt.trim() : '';

			if (alt === '') {
				missing++;
			}
		}

		if (missing === 0) {
			return { id: 'image_alt', label: 'Image alt text', status: 'good', detail: 'All ' + total + ' images have alt text.' };
		}

		return { id: 'image_alt', label: 'Image alt text', status: 'bad', detail: missing + ' of ' + total + ' images missing alt text.' };
	}

	/**
	 * Check 10: Readability (Flesch Reading Ease).
	 */
	function checkReadability(data) {
		var content   = data.content || '';
		var plainText = stripTags(content);
		var wordCount = countWords(plainText);

		if (wordCount < 30) {
			return { id: 'readability', label: 'Readability', status: 'warning', detail: 'Not enough content for readability analysis (need 30+ words).' };
		}

		var score = fleschReadingEase(plainText);

		if (score >= 60) {
			return { id: 'readability', label: 'Readability', status: 'good', detail: 'Flesch score: ' + score + '/100 (easy to read).' };
		}

		if (score >= 30) {
			return { id: 'readability', label: 'Readability', status: 'warning', detail: 'Flesch score: ' + score + '/100. Consider simpler sentences.' };
		}

		return { id: 'readability', label: 'Readability', status: 'bad', detail: 'Flesch score: ' + score + '/100. Content is difficult to read.' };
	}

	/* ── Main Analysis Function ──────────────────────────────────── */

	/**
	 * Run all SEO checks and return a score + checklist.
	 *
	 * @param {Object} data Analysis input data.
	 * @param {string} data.title          Page/post title.
	 * @param {string} data.description    Meta description.
	 * @param {string} data.focusKeyword   Primary keyword.
	 * @param {string} data.content        Raw HTML content.
	 * @param {string} data.url            URL slug.
	 * @param {Array}  data.headings       Array of heading objects.
	 * @param {Array}  data.images         Array of { alt: string } objects.
	 * @param {Array}  data.links          Array of link objects.
	 * @return {{ score: number, checks: Array<{id: string, label: string, status: string, detail: string}> }}
	 */
	function analyze(data) {
		/* Validate input */
		if (typeof data !== 'object' || data === null) {
			data = {};
		}

		var checkFunctions = [
			checkKeywordInTitle,
			checkKeywordInDescription,
			checkKeywordInUrl,
			checkKeywordInContent,
			checkKeywordDensity,
			checkTitleLength,
			checkDescriptionLength,
			checkContentLength,
			checkImageAlt,
			checkReadability,
		];

		var checks       = [];
		var goodCount    = 0;
		var totalChecks  = checkFunctions.length;
		var maxChecks    = 20;

		for (var i = 0; i < totalChecks && i < maxChecks; i++) {
			var result = checkFunctions[i](data);
			checks.push(result);

			if (result.status === 'good') {
				goodCount++;
			}
		}

		/* Score: sum of good checks * 10 (max 100) */
		var score = goodCount * 10;

		if (score > 100) {
			score = 100;
		}

		return {
			score:  score,
			checks: checks,
		};
	}

	/* ── Export as global ─────────────────────────────────────────── */
	window.bsSeoAnalysis = {
		analyze: analyze,
	};

})();
