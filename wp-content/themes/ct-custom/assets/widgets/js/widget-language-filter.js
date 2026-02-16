/**
 * Widget Language Filter
 *
 * Adds WPML-style language tabs to the Widgets admin screen.
 * Supports both classic widget editor and block widget editor.
 * Reads language data from the global `ctWidgetLangFilter` object
 * injected by CT_Widget_Language_Filter::enqueue_assets().
 *
 * All sidebars are per-language (ending in -{iso2}). There are no
 * non-language "default" sidebars. Tabs: language tabs + "Show All".
 */

const STORAGE_KEY = 'ct_widget_lang_filter';
const HIDDEN_CLASS = 'ct-widget-lang-filter-hidden';
const ACTIVE_CLASS = 'ct-widget-lang-tab--active';

/**
 * Classic Widget Editor — operates on #widgets-right DOM.
 */
class ClassicWidgetFilter {

	/** @type {Object} */
	config;

	/** @type {Map<string, HTMLElement[]>} keyed by iso2 code */
	sidebarMap = new Map();

	/** @type {HTMLElement[]} sidebars that don't match any known language */
	nonLangSidebars = [];

	/** @type {string} currently active filter key */
	activeKey = '';

	/** @type {Map<string, string>} sidebar id -> original title text */
	originalTitles = new Map();

	/**
	 * @param {Object} config  ctWidgetLangFilter global
	 */
	constructor(config) {
		this.config = config;
		this.mapSidebars();
		this.buildTabBar();

		const stored = loadStoredLang();
		this.applyFilter(stored || this.config.defaultIso2);
	}

	/**
	 * Group sidebar containers by language using known ISO2 suffixes.
	 */
	mapSidebars() {
		const knownIso2 = new Set(this.config.knownIso2 || []);

		const wrappers = document.querySelectorAll(
			'#widgets-right .widgets-holder-wrap'
		);
		const maxWrappers = 100;
		let count = 0;

		for (const wrapper of wrappers) {
			if (count >= maxWrappers) { break; }
			count++;

			const sortable = wrapper.querySelector('.widgets-sortables');
			if (!sortable) {
				this.nonLangSidebars.push(wrapper);
				continue;
			}

			const sidebarId = sortable.id;

			const titleEl = wrapper.querySelector('.sidebar-name h2, .sidebar-name h3');
			if (titleEl) {
				this.originalTitles.set(sidebarId, titleEl.textContent);
			}

			let matched = false;

			for (const iso2 of knownIso2) {
				if (sidebarId.endsWith('-' + iso2)) {
					if (!this.sidebarMap.has(iso2)) {
						this.sidebarMap.set(iso2, []);
					}
					this.sidebarMap.get(iso2).push(wrapper);
					matched = true;
					break;
				}
			}

			if (!matched) {
				this.nonLangSidebars.push(wrapper);
			}
		}
	}

	/**
	 * Build the tab bar and insert it before #widgets-right.
	 */
	buildTabBar() {
		const widgetsRight = document.getElementById('widgets-right');
		if (!widgetsRight) { return; }

		const bar = buildTabBarElement(this.config, (key) => {
			this.applyFilter(key);
			storeLang(key);
		});

		widgetsRight.parentNode.insertBefore(bar, widgetsRight);
	}

	/**
	 * Show only sidebars matching the selected language key.
	 *
	 * @param {string} key  iso2 code or 'all'
	 */
	applyFilter(key) {
		this.activeKey = key;
		const showAll = (key === 'all');

		for (const [mapKey, wrappers] of this.sidebarMap) {
			const visible = showAll || mapKey === key;
			const maxWrappers = 100;
			let count = 0;

			for (const wrapper of wrappers) {
				if (count >= maxWrappers) { break; }
				count++;

				if (visible) {
					wrapper.classList.remove(HIDDEN_CLASS);
				} else {
					wrapper.classList.add(HIDDEN_CLASS);
				}
			}
		}

		updateTabStates(key);
		this.updateSidebarTitles(key);
	}

	/**
	 * Strip "(Language Name)" suffix from titles when filtering
	 * a specific language.
	 *
	 * @param {string} key
	 */
	updateSidebarTitles(key) {
		const isSpecificLang = (key !== 'all');
		const langName = isSpecificLang
			? this.config.languages.find(l => l.iso2 === key)?.name
			: null;

		if (!isSpecificLang || !langName) {
			this.restoreOriginalTitles();
			return;
		}

		const wrappers = this.sidebarMap.get(key) || [];
		const maxWrappers = 100;
		let count = 0;

		for (const wrapper of wrappers) {
			if (count >= maxWrappers) { break; }
			count++;

			const sortable = wrapper.querySelector('.widgets-sortables');
			if (!sortable) { continue; }

			const titleEl = wrapper.querySelector('.sidebar-name h2, .sidebar-name h3');
			if (!titleEl) { continue; }

			const original = this.originalTitles.get(sortable.id) || titleEl.textContent;
			const suffix = ' (' + langName + ')';

			if (original.endsWith(suffix)) {
				titleEl.textContent = original.slice(0, -suffix.length);
			}
		}
	}

	/**
	 * Restore all sidebar titles to their original text.
	 */
	restoreOriginalTitles() {
		for (const [sidebarId, originalTitle] of this.originalTitles) {
			const sortable = document.getElementById(sidebarId);
			if (!sortable) { continue; }

			const wrapper = sortable.closest('.widgets-holder-wrap');
			if (!wrapper) { continue; }

			const titleEl = wrapper.querySelector('.sidebar-name h2, .sidebar-name h3');
			if (titleEl) {
				titleEl.textContent = originalTitle;
			}
		}
	}
}

/**
 * Block Widget Editor — uses MutationObserver to watch React-rendered areas.
 */
class BlockWidgetFilter {

	/** @type {Object} */
	config;

	/** @type {string} */
	activeKey = '';

	/** @type {MutationObserver|null} */
	observer = null;

	/** @type {number} rAF handle for debouncing observer callbacks */
	pendingRaf = 0;

	/** @type {Map<string, string>} lowercase language name -> iso2 */
	langNameMap = new Map();

	/**
	 * Label selectors to try, in priority order.
	 *
	 * WordPress renders widget-area titles inside a PanelBody whose
	 * toggle button carries the text. Older WP versions may use
	 * __label or __header-text instead.
	 */
	static LABEL_SELECTORS = [
		'.components-panel__body-toggle',
		'.components-panel__body-title',
		'.wp-block-widget-area__label',
		'.wp-block-widget-area__header-text',
	];

	/**
	 * @param {Object} config  ctWidgetLangFilter global
	 */
	constructor(config) {
		this.config = config;

		const maxLangs = 50;
		let count = 0;
		for (const lang of config.languages) {
			if (count >= maxLangs) { break; }
			count++;
			this.langNameMap.set(lang.name.toLowerCase(), lang.iso2);
		}

		const stored = loadStoredLang();
		this.activeKey = stored || this.config.defaultIso2;

		this.injectTabBar();
		this.applyFilter(this.activeKey);
		this.observeEditor();
	}

	/**
	 * Insert the tab bar before the block editor main area.
	 */
	injectTabBar() {
		const editor = document.querySelector('.edit-widgets-block-editor');
		if (!editor) { return; }

		const existing = document.querySelector('.ct-widget-lang-filter-bar');
		if (existing) { return; }

		const bar = buildTabBarElement(this.config, (key) => {
			this.activeKey = key;
			this.applyFilter(key);
			storeLang(key);
		});

		editor.parentNode.insertBefore(bar, editor);
	}

	/**
	 * Watch for DOM changes in the block editor (React re-renders).
	 *
	 * Uses requestAnimationFrame to debounce rapid mutations so
	 * applyFilter runs at most once per paint frame.
	 */
	observeEditor() {
		const target = document.querySelector('.edit-widgets-block-editor') ||
		               document.getElementById('wpbody-content');
		if (!target) { return; }

		this.observer = new MutationObserver(() => {
			if (this.pendingRaf) { return; }
			this.pendingRaf = requestAnimationFrame(() => {
				this.pendingRaf = 0;
				this.applyFilter(this.activeKey);
			});
		});

		this.observer.observe(target, {
			childList: true,
			subtree: true,
		});
	}

	/**
	 * Find the label text for a widget area element.
	 *
	 * Tries multiple selectors for cross-version WordPress compat.
	 *
	 * @param {HTMLElement} area
	 * @returns {string} Label text, or empty string if not found.
	 */
	getAreaLabel(area) {
		const max = BlockWidgetFilter.LABEL_SELECTORS.length;

		for (let i = 0; i < max; i++) {
			const el = area.querySelector(BlockWidgetFilter.LABEL_SELECTORS[i]);
			if (el) {
				const text = el.textContent.trim();
				if (text) { return text; }
			}
		}

		return '';
	}

	/**
	 * Show/hide widget area sections based on their label text.
	 *
	 * All per-language sidebars have "(LanguageName)" in their label.
	 * Match the language name to an iso2 code via langNameMap.
	 *
	 * @param {string} key  iso2 code or 'all'
	 */
	applyFilter(key) {
		this.activeKey = key;
		const showAll = (key === 'all');

		const langPattern = /\(([^)]+)\)\s*$/;

		const areas = document.querySelectorAll('.wp-block-widget-area');
		const maxAreas = 100;
		let count = 0;

		for (const area of areas) {
			if (count >= maxAreas) { break; }
			count++;

			const labelText = this.getAreaLabel(area);
			if (!labelText) {
				continue;
			}

			const langMatch = labelText.match(langPattern);
			if (!langMatch) {
				/* Sidebar without a language suffix — always visible */
				area.classList.remove(HIDDEN_CLASS);
				continue;
			}

			if (showAll) {
				area.classList.remove(HIDDEN_CLASS);
				continue;
			}

			const langName = langMatch[1];
			const iso2 = this.langNameMap.get(langName.toLowerCase()) || null;

			if (iso2 === key) {
				area.classList.remove(HIDDEN_CLASS);
			} else {
				area.classList.add(HIDDEN_CLASS);
			}
		}

		updateTabStates(key);
	}
}

// --- Shared Utilities ---

/**
 * Build a tab bar DOM element.
 *
 * Tabs: one per language + "Show All". No "Default (Fallback)" tab.
 *
 * @param {Object}   config
 * @param {Function} onSelect  Called with the selected key.
 * @returns {HTMLElement}
 */
function buildTabBarElement(config, onSelect) {
	const bar = document.createElement('div');
	bar.className = 'ct-widget-lang-filter-bar';
	bar.setAttribute('role', 'tablist');
	bar.setAttribute('aria-label', 'Filter sidebars by language');

	const maxLangs = 50;
	let count = 0;

	for (const lang of config.languages) {
		if (count >= maxLangs) { break; }
		count++;

		bar.appendChild(createTab(lang.iso2, lang.name, onSelect));
	}

	bar.appendChild(createTab('all', 'Show All', onSelect));

	return bar;
}

/**
 * Create a single tab button.
 *
 * @param {string}   key
 * @param {string}   label
 * @param {Function} onSelect
 * @returns {HTMLButtonElement}
 */
function createTab(key, label, onSelect) {
	const btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'ct-widget-lang-tab';
	btn.dataset.langKey = key;
	btn.setAttribute('role', 'tab');
	btn.setAttribute('aria-selected', 'false');
	btn.textContent = label;

	btn.addEventListener('click', () => {
		onSelect(key);
	});

	return btn;
}

/**
 * Update active tab visual state across all tab bars.
 *
 * @param {string} activeKey
 */
function updateTabStates(activeKey) {
	const tabs = document.querySelectorAll('.ct-widget-lang-tab');
	const maxTabs = 100;
	let count = 0;

	for (const tab of tabs) {
		if (count >= maxTabs) { break; }
		count++;

		const isActive = tab.dataset.langKey === activeKey;
		tab.classList.toggle(ACTIVE_CLASS, isActive);
		tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
	}
}

/**
 * Load last selected language from localStorage.
 *
 * @returns {string|null}
 */
function loadStoredLang() {
	try {
		return localStorage.getItem(STORAGE_KEY);
	} catch {
		return null;
	}
}

/**
 * Persist selected language to localStorage.
 *
 * @param {string} key
 */
function storeLang(key) {
	try {
		localStorage.setItem(STORAGE_KEY, key);
	} catch {
		/* localStorage unavailable — silently ignore */
	}
}

// --- Initialization ---

document.addEventListener('DOMContentLoaded', () => {
	if (typeof ctWidgetLangFilter === 'undefined') { return; }
	if (!ctWidgetLangFilter.languages || ctWidgetLangFilter.languages.length === 0) { return; }

	/* Classic widget editor: #widgets-right exists in the DOM */
	if (document.getElementById('widgets-right')) {
		new ClassicWidgetFilter(ctWidgetLangFilter);
		return;
	}

	/* Block widget editor: .edit-widgets-block-editor may render async */
	const blockEditor = document.querySelector('.edit-widgets-block-editor');
	if (blockEditor) {
		new BlockWidgetFilter(ctWidgetLangFilter);
		return;
	}

	/* Block editor may not be rendered yet — wait for it with bounded observation */
	var waitMutationCount = 0;
	var WAIT_MAX_MUTATIONS = 500;

	const waitObserver = new MutationObserver((mutations, obs) => {
		waitMutationCount++;
		if (waitMutationCount >= WAIT_MAX_MUTATIONS) {
			obs.disconnect();
			return;
		}

		const editor = document.querySelector('.edit-widgets-block-editor');
		if (editor) {
			obs.disconnect();
			new BlockWidgetFilter(ctWidgetLangFilter);
		}
	});

	const wpbody = document.getElementById('wpbody-content');
	if (wpbody) {
		waitObserver.observe(wpbody, { childList: true, subtree: true });

		/* Hard timeout: stop observing after 10 seconds */
		setTimeout(() => { waitObserver.disconnect(); }, 10000);
	}
});
