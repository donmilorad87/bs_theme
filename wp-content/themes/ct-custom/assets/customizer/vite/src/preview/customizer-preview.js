/**
 * CustomizerPreview - Live preview bindings for the WordPress Customizer.
 *
 * Runs inside the customizer preview iframe. Rebuilds an inline
 * <style> element whenever any bound theme_mod changes, giving
 * the user instant visual feedback.
 *
 * Depends on wp.customize and jQuery (provided by WordPress).
 *
 * @package BS_Custom
 */

var MAX_COLOR_VARS = 50;
var MAX_SETTINGS = 250;
var STYLE_ELEMENT_ID = 'ct-custom-live-preview';

function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

function CustomizerPreview(api, $) {
    assert(typeof api === 'function', 'wp.customize API must be a function');
    assert(typeof $ === 'function', 'jQuery must be a function');

    this._api = api;
    this._$ = $;

    this._colorVariableMap = this._buildColorVariableMap();
    this._cssSettings = this._buildCssSettingsList();
    this._allSettings = this._buildAllSettingsList();

    this._bindTextSwaps();
    this._bindCssRebuilds();
    this._bindFontPreview();
    this._bindBackToTopPosition();
    this._bindBackToTopSettings();
    this._bindSiteIcon();
    this._bindContactPoint();
    this._scanPageTranslations();
}

CustomizerPreview.prototype._buildColorVariableMap = function () {
    assert(typeof this._api === 'function', 'API required');

    return {
        '--ct-topbar-bg-color': ['ct_topbar_bg_color', 'ct_topbar_bg_color_dark'],
        '--ct-topbar-text1-color': ['ct_topbar_text1_color', 'ct_topbar_text1_color_dark'],
        '--ct-topbar-text2-color': ['ct_topbar_text2_color', 'ct_topbar_text2_color_dark'],
        '--ct-topbar-links-color': ['ct_topbar_links_color', 'ct_topbar_links_color_dark'],
        '--ct-topbar-links-hover-color': ['ct_topbar_links_hover_color', 'ct_topbar_links_hover_color_dark'],
        '--ct-header-bg-color': ['ct_header_bg_color', 'ct_header_bg_color_dark'],
        '--ct-header-border-color': ['ct_header_border_color', 'ct_header_border_color_dark'],
        '--ct-site-title-color': ['ct_site_title_color', 'ct_site_title_color_dark'],
        '--ct-menu-top-color': ['ct_menu_top_color', 'ct_menu_top_color_dark'],
        '--ct-menu-active-underline-color': ['ct_menu_active_underline_color', 'ct_menu_active_underline_color_dark'],
        '--ct-menu-sub-color': ['ct_menu_sub_color', 'ct_menu_sub_color_dark'],
        '--ct-menu-sub-border-color': ['ct_menu_sub_border_color', 'ct_menu_sub_border_color_dark'],
        '--ct-menu-sub-bg-color': ['ct_menu_sub_bg_color', 'ct_menu_sub_bg_color_dark'],
        '--ct-menu-sub-hover-bg-color': ['ct_menu_sub_hover_bg_color', 'ct_menu_sub_hover_bg_color_dark'],
        '--ct-mobile-menu-bg-color': ['ct_mobile_menu_bg_color', 'ct_mobile_menu_bg_color_dark'],
        '--ct-mobile-menu-border-color': ['ct_mobile_menu_border_color', 'ct_mobile_menu_border_color_dark'],
        '--ct-breadcrumb-color': ['ct_breadcrumb_color', 'ct_breadcrumb_color_dark'],
        '--ct-breadcrumb-active-color': ['ct_breadcrumb_active_color', 'ct_breadcrumb_active_color_dark'],
        '--ct-body-bg-color': ['ct_body_bg_color', 'ct_body_bg_color_dark'],
        '--ct-h1-color': ['ct_h1_color', 'ct_h1_color_dark'],
        '--ct-h2-color': ['ct_h2_color', 'ct_h2_color_dark'],
        '--ct-h3-color': ['ct_h3_color', 'ct_h3_color_dark'],
        '--ct-h4-color': ['ct_h4_color', 'ct_h4_color_dark'],
        '--ct-h5-color': ['ct_h5_color', 'ct_h5_color_dark'],
        '--ct-paragraph-color': ['ct_paragraph_color', 'ct_paragraph_color_dark'],
        '--ct-special-color': ['ct_special_color', 'ct_special_color_dark'],
        '--ct-form-input-bg-color': ['ct_form_input_bg_color', 'ct_form_input_bg_color_dark'],
        '--ct-form-input-border-color': ['ct_form_input_border_color', 'ct_form_input_border_color_dark'],
        '--ct-form-submit-hover-color': ['ct_form_submit_hover_color', 'ct_form_submit_hover_color_dark'],
        '--ct-footer-bg-color': ['ct_footer_bg_color', 'ct_footer_bg_color_dark'],
        '--ct-footer-text-color': ['ct_footer_text_color', 'ct_footer_text_color_dark'],
        '--ct-footer-link-color': ['ct_footer_link_color', 'ct_footer_link_color_dark'],
        '--ct-footer-link-hover-color': ['ct_footer_link_hover_color', 'ct_footer_link_hover_color_dark'],
        '--ct-social-bg-color': ['ct_social_bg_color', 'ct_social_bg_color_dark'],
        '--ct-back-to-top-bg': ['ct_back_to_top_bg_color', 'ct_back_to_top_bg_color_dark'],
        '--ct-back-to-top-border-color': ['ct_back_to_top_border_color', 'ct_back_to_top_border_color_dark'],
    };
};

CustomizerPreview.prototype._buildCssSettingsList = function () {
    assert(typeof this._api === 'function', 'API required');

    return [
        'ct_topbar_text1_size', 'ct_topbar_text1_bold', 'ct_topbar_text1_italic', 'ct_topbar_text1_uppercase',
        'ct_topbar_text1_margin_left', 'ct_topbar_text1_margin_right', 'ct_topbar_text1_margin_top',
        'ct_topbar_text2_size', 'ct_topbar_text2_bold', 'ct_topbar_text2_italic', 'ct_topbar_text2_uppercase',
        'ct_topbar_text2_margin_left', 'ct_topbar_text2_margin_right', 'ct_topbar_text2_margin_top',
        'ct_topbar_links_size', 'ct_topbar_links_bold', 'ct_topbar_links_italic', 'ct_topbar_links_uppercase',
        'ct_topbar_links_margin_left', 'ct_topbar_links_margin_right', 'ct_topbar_links_margin_top',
        'ct_header_logo_height', 'ct_header_logo_margin_left', 'ct_header_logo_margin_right',
        'ct_header_logo_margin_top', 'ct_header_logo_margin_bottom',
        'ct_menu_top_font_size', 'ct_menu_top_bold', 'ct_menu_top_italic', 'ct_menu_top_uppercase',
        'ct_menu_top_margin_left', 'ct_menu_top_margin_right', 'ct_menu_top_margin_top',
        'ct_menu_sub_font_size', 'ct_menu_sub_bold', 'ct_menu_sub_italic', 'ct_menu_sub_uppercase',
        'ct_menu_sub_margin_left', 'ct_menu_sub_margin_right', 'ct_menu_sub_margin_top',
        'ct_menu_sub_border_width', 'ct_menu_sub_border_style',
        'ct_mobile_menu_border_width',
        'ct_breadcrumb_font_size', 'ct_breadcrumb_transform', 'ct_breadcrumb_active_bold', 'ct_breadcrumb_active_underline',
        'ct_h1_font_size', 'ct_h1_bold', 'ct_h1_italic', 'ct_h1_transform',
        'ct_h2_font_size', 'ct_h2_bold', 'ct_h2_italic', 'ct_h2_transform',
        'ct_h3_font_size', 'ct_h3_bold', 'ct_h3_italic', 'ct_h3_transform',
        'ct_h4_font_size', 'ct_h4_bold', 'ct_h4_italic', 'ct_h4_transform',
        'ct_h5_font_size', 'ct_h5_bold', 'ct_h5_italic', 'ct_h5_transform',
        'ct_paragraph_font_size', 'ct_paragraph_bold', 'ct_paragraph_italic', 'ct_paragraph_transform',
        'ct_paragraph_line_height', 'ct_paragraph_margin_top', 'ct_paragraph_margin_right',
        'ct_paragraph_margin_bottom', 'ct_paragraph_margin_left',
        'ct_special_font_size', 'ct_special_bold', 'ct_special_italic', 'ct_special_transform',
        'ct_back_to_top_border_width', 'ct_back_to_top_border_radius', 'ct_back_to_top_position',
        'ct_social_icon_width', 'ct_social_icon_height',
    ];
};

CustomizerPreview.prototype._buildAllSettingsList = function () {
    var colorIds = [];
    var count = 0;

    for (var varName in this._colorVariableMap) {
        if (count >= MAX_COLOR_VARS) {
            break;
        }
        if (Object.prototype.hasOwnProperty.call(this._colorVariableMap, varName)) {
            colorIds.push(this._colorVariableMap[varName][0]);
            colorIds.push(this._colorVariableMap[varName][1]);
            count++;
        }
    }

    assert(colorIds.length > 0, 'Color IDs must not be empty');

    return this._cssSettings.concat(colorIds);
};

/**
 * Resolve CLDR plural category for a language + count.
 * Mirrors PHP CT_CLDR_Plural_Rules and frontend translator.js.
 *
 * @param {string} iso2  Two-letter language code.
 * @param {number} count Integer count.
 * @return {string} Plural category (zero|one|two|few|many|other).
 */
CustomizerPreview.prototype._resolvePluralCategory = function (iso2, count) {
    assert(typeof iso2 === 'string', 'iso2 must be a string');
    assert(typeof count === 'number', 'count must be a number');

    var n = Math.abs(count);
    var mod10 = n % 10;
    var mod100 = n % 100;

    var noPluralLangs = ['ja', 'zh', 'ko', 'tr', 'vi', 'th', 'id', 'ms'];
    for (var i = 0; i < noPluralLangs.length; i++) {
        if (iso2 === noPluralLangs[i]) { return 'other'; }
    }

    var frenchLangs = ['fr', 'hi', 'fa'];
    for (var i = 0; i < frenchLangs.length; i++) {
        if (iso2 === frenchLangs[i]) { return n <= 1 ? 'one' : 'other'; }
    }

    var eastSlavicLangs = ['sr', 'ru', 'uk', 'be', 'hr', 'bs'];
    for (var i = 0; i < eastSlavicLangs.length; i++) {
        if (iso2 === eastSlavicLangs[i]) {
            if (mod10 === 1 && mod100 !== 11) { return 'one'; }
            if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) { return 'few'; }
            return 'other';
        }
    }

    var westSlavicLangs = ['cs', 'sk'];
    for (var i = 0; i < westSlavicLangs.length; i++) {
        if (iso2 === westSlavicLangs[i]) {
            if (n === 1) { return 'one'; }
            if (n >= 2 && n <= 4) { return 'few'; }
            return 'other';
        }
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
};

/**
 * Resolve ct_translate() patterns in a string using local translation data.
 * Handles full syntax: ct_translate('KEY'), ct_translate('KEY', {args}, count/form).
 *
 * @param {string} value Text potentially containing ct_translate() patterns.
 * @return {string} Text with patterns resolved (or original keys as fallback).
 */
CustomizerPreview.prototype._resolveTranslation = function (value) {
    if (typeof value !== 'string') {
        return value;
    }

    if (value.indexOf('ct_translate(') === -1) {
        return value;
    }

    var translations = window.ctTranslationData ? window.ctTranslationData.translations : {};
    var iso2 = (window.ctTranslationData && window.ctTranslationData.iso2) ? window.ctTranslationData.iso2 : 'en';
    var self = this;
    var maxMatches = 50;
    var matchCount = 0;
    var validForms = ['singular', 'zero', 'one', 'two', 'few', 'many', 'other'];

    /* Full ct_translate pattern matching PHP's parse_ct_translate_patterns */
    var pattern = /ct_translate\(\s*['"]([A-Z][A-Z0-9_]*)['"]\s*(?:,\s*[\[{](.*?)[\]}]\s*(?:,\s*(?:['"]([a-z]+)['"]|(\d+))\s*)?)?\)/g;

    value = value.replace(pattern, function (match, key, argsStr, formStr, countStr) {
        if (matchCount >= maxMatches) { return match; }
        matchCount++;

        var val = translations[key];
        if (typeof val === 'undefined') { return key; }

        /* Parse args from inline string (e.g. "'count': 5, 'name': 'test'") */
        var args = {};
        if (argsStr) {
            var argPattern = /['"]([a-zA-Z0-9_]+)['"]\s*:\s*(?:'([^']*)'|"([^"]*)"|(\d+(?:\.\d+)?))/g;
            var argMatch;
            var argCount = 0;
            while ((argMatch = argPattern.exec(argsStr)) !== null && argCount < 50) {
                argCount++;
                args[argMatch[1]] = argMatch[2] || argMatch[3] || argMatch[4] || '';
            }
        }

        /* Determine count/form for plural resolution */
        var count = null;
        var form = null;
        if (countStr) {
            count = parseInt(countStr, 10);
        } else if (formStr) {
            var isValidForm = false;
            for (var i = 0; i < validForms.length; i++) {
                if (formStr === validForms[i]) { isValidForm = true; break; }
            }
            if (isValidForm) {
                form = formStr;
            } else {
                count = parseInt(formStr, 10);
                if (isNaN(count)) { count = null; form = formStr; }
            }
        }

        /* Resolve plural / singular */
        var resolved;
        if (typeof val === 'string') {
            resolved = val;
        } else if (typeof val === 'object' && val !== null) {
            if (form) {
                resolved = val[form] || val['other'] || key;
            } else if (count !== null) {
                var category = self._resolvePluralCategory(iso2, count);
                resolved = val[category] || val['other'] || key;
            } else {
                resolved = val.singular || val.other || key;
            }
        } else {
            resolved = key;
        }

        if (typeof resolved !== 'string') {
            return key;
        }

        /* Replace ##placeholder## with arg values */
        var placeholderCount = 0;
        for (var placeholder in args) {
            if (placeholderCount >= 50) { break; }
            if (Object.prototype.hasOwnProperty.call(args, placeholder)) {
                placeholderCount++;
                var re = new RegExp('##' + placeholder + '##', 'g');
                resolved = resolved.replace(re, String(args[placeholder]));
            }
        }

        /* Strip unresolved placeholders */
        resolved = resolved.replace(/##[a-zA-Z0-9_]+##/g, '');

        return resolved;
    });

    return value;
};

CustomizerPreview.prototype._resolveCtTranslateAsync = function (value, selector) {
    assert(typeof value === 'string', 'Value must be a string');
    assert(typeof selector === 'string', 'Selector must be a string');

    if (value.indexOf('ct_translate(') === -1) {
        return;
    }

    if (typeof ctCustomizerData === 'undefined' || !ctCustomizerData.translationUrl) {
        console.warn('[ct-preview] ctCustomizerData or translationUrl is missing — cannot resolve ct_translate()');
        return;
    }

    var $ = this._$;
    var url = ctCustomizerData.translationUrl;
    var nonce = ctCustomizerData.nonce;
    var iso2 = (window.ctTranslationData && window.ctTranslationData.iso2) ? window.ctTranslationData.iso2 : '';

    /* Debounce per selector */
    if (!this._translateTimers) {
        this._translateTimers = {};
    }

    if (this._translateTimers[selector]) {
        clearTimeout(this._translateTimers[selector]);
    }

    var MAX_RETRIES = 1;
    var RETRY_DELAY_MS = 500;

    /* Use native fetch to bypass WordPress Customizer jQuery AJAX prefilter */
    function doRequest(attempt) {
        assert(attempt >= 0, 'Attempt must be non-negative');
        assert(attempt <= MAX_RETRIES, 'Attempt must not exceed max retries');

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nonce,
            },
            body: JSON.stringify({ text: value, iso2: iso2 }),
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.json();
            })
            .then(function (response) {
                if (response && response.success && response.data && response.data.resolved) {
                    $(selector).text(response.data.resolved);
                } else {
                    console.warn('[ct-preview] ct_translate API returned unexpected response:', response);
                }
            })
            .catch(function (err) {
                console.error('[ct-preview] ct_translate fetch failed (attempt ' + (attempt + 1) + '):', err.message, 'URL:', url);
                if (attempt < MAX_RETRIES) {
                    setTimeout(function () { doRequest(attempt + 1); }, RETRY_DELAY_MS);
                } else {
                    console.error('[ct-preview] ct_translate resolution failed after ' + (MAX_RETRIES + 1) + ' attempts for:', value);
                }
            });
    }

    this._translateTimers[selector] = setTimeout(function () {
        doRequest(0);
    }, 300);
};

/**
 * Scan all text nodes in the page for ct_translate() patterns and resolve them.
 * Uses TreeWalker for efficient DOM traversal. Resolves synchronously first
 * using local data, then falls back to async API for any remaining patterns.
 */
CustomizerPreview.prototype._scanPageTranslations = function () {
    assert(typeof document !== 'undefined', 'document must exist');

    if (!document.body) {
        return;
    }

    var MAX_SCAN_NODES = 200;
    var self = this;
    var matchingNodes = [];

    /* Walk all text nodes in the body */
    var walker = document.createTreeWalker(
        document.body,
        NodeFilter.SHOW_TEXT,
        null
    );

    var node;
    var scanned = 0;
    while ((node = walker.nextNode()) !== null && scanned < 5000) {
        scanned++;
        if (node.textContent.indexOf('ct_translate(') !== -1) {
            matchingNodes.push(node);
            if (matchingNodes.length >= MAX_SCAN_NODES) {
                break;
            }
        }
    }

    if (matchingNodes.length === 0) {
        return;
    }

    assert(matchingNodes.length <= MAX_SCAN_NODES, 'Scan nodes must be bounded');

    /* Sync pass: resolve using local translation data */
    var unresolvedNodes = [];
    for (var i = 0; i < matchingNodes.length; i++) {
        var textNode = matchingNodes[i];
        var original = textNode.textContent;
        var resolved = self._resolveTranslation(original);

        textNode.textContent = resolved;

        if (resolved.indexOf('ct_translate(') !== -1) {
            unresolvedNodes.push(textNode);
        }
    }

    /* Async pass: resolve remaining patterns via the resolve-translation API */
    if (unresolvedNodes.length === 0) {
        return;
    }

    if (typeof ctCustomizerData === 'undefined' || !ctCustomizerData.translationUrl) {
        return;
    }

    var url = ctCustomizerData.translationUrl;
    var nonce = ctCustomizerData.nonce;
    var iso2 = (window.ctTranslationData && window.ctTranslationData.iso2) ? window.ctTranslationData.iso2 : '';

    for (var j = 0; j < unresolvedNodes.length; j++) {
        (function (textNode) {
            var text = textNode.textContent;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce,
                },
                body: JSON.stringify({ text: text, iso2: iso2 }),
            })
                .then(function (res) {
                    if (!res.ok) { throw new Error('HTTP ' + res.status); }
                    return res.json();
                })
                .then(function (response) {
                    if (response && response.success && response.data && response.data.resolved) {
                        textNode.textContent = response.data.resolved;
                    }
                })
                .catch(function (err) {
                    console.warn('[ct-preview] scan: async resolve failed:', err.message);
                });
        })(unresolvedNodes[j]);
    }
};

CustomizerPreview.prototype._bindTextSwaps = function () {
    assert(typeof this._api === 'function', 'API required');
    assert(typeof this._$ === 'function', 'jQuery required');

    var self = this;
    var $ = this._$;
    var api = this._api;

    var textBindings = [
        ['blogname', '.site-title a, .site-title-text'],
        ['blogdescription', '.site-description'],
        ['ct_topbar_text1_content', '.topbar__text1:not(.topbar__phone-link)'],
        ['ct_contact_heading', '.ct-contact-heading'],
        ['ct_contact_content', '.ct-contact-content'],
        ['ct_hero_title', '.hero-section__title'],
        ['ct_hero_description', '.hero-section__description'],
        ['ct_section2_title', '.content-section__title'],
        ['ct_section2_description', '.content-section__description'],
        ['ct_reach_us_title', '.contact-section__info .section-title'],
        ['ct_contact_us_title', '.contact-section__form .section-title'],
    ];

    var count = 0;
    var maxBindings = 20;

    for (var i = 0; i < textBindings.length; i++) {
        if (count >= maxBindings) {
            break;
        }
        count++;

        var settingId = textBindings[i][0];
        var selector = textBindings[i][1];

        api(settingId, (function (sel) {
            return function (value) {
                value.bind(function (to) {
                    var resolved = self._resolveTranslation(to);
                    if (resolved.indexOf('ct_translate(') !== -1) {
                        $(sel).text('\u2026');
                    } else {
                        $(sel).text(resolved);
                    }
                    self._resolveCtTranslateAsync(to, sel);
                });
            };
        })(selector));
    }

    api('ct_topbar_text2_content', function (value) {
        value.bind(function (to) {
            var $link = $('.topbar__phone-link');
            $link.text(to);
            $link.attr('href', 'tel:' + to.replace(/[^0-9+.]/g, ''));
        });
    });

    api('ct_footer_copyright', function (value) {
        value.bind(function (to) {
            var year = new Date().getFullYear().toString();
            var rendered = to.replace(/\{year\}/g, year);
            $('.ct-footer-copyright').html(rendered);
        });
    });
};

CustomizerPreview.prototype._bindCssRebuilds = function () {
    assert(Array.isArray(this._allSettings), 'All settings must be an array');
    assert(this._allSettings.length > 0, 'All settings must not be empty');

    var api = this._api;
    var self = this;
    var count = 0;

    for (var i = 0; i < this._allSettings.length; i++) {
        if (count >= MAX_SETTINGS) {
            break;
        }
        count++;

        var settingId = this._allSettings[i];

        api(settingId, function (value) {
            value.bind(function () { self._updatePreviewCSS(); });
        });
    }
};

CustomizerPreview.prototype._getVal = function (id) {
    assert(typeof id === 'string', 'Setting ID must be a string');

    var setting = this._api(id);
    return setting ? setting.get() : '';
};

CustomizerPreview.prototype._checkboxStyleProps = function (bold, italic, uppercase) {
    assert(typeof bold !== 'undefined', 'Bold must be defined');

    var props = bold ? 'font-weight:700;' : 'font-weight:400;';
    props += italic ? 'font-style:italic;' : 'font-style:normal;';
    props += uppercase ? 'text-transform:uppercase;' : 'text-transform:none;';
    return props;
};

CustomizerPreview.prototype._buildVariableCSS = function () {
    var lightVars = '';
    var darkVars = '';
    var count = 0;

    for (var vn in this._colorVariableMap) {
        if (count >= MAX_COLOR_VARS) {
            break;
        }
        if (Object.prototype.hasOwnProperty.call(this._colorVariableMap, vn)) {
            lightVars += vn + ':' + this._getVal(this._colorVariableMap[vn][0]) + ';';
            darkVars += vn + ':' + this._getVal(this._colorVariableMap[vn][1]) + ';';
            count++;
        }
    }

    assert(lightVars.length > 0, 'Light vars must not be empty');

    var css = '';
    css += 'body, body[data-theme="light"] { color-scheme:light;' + lightVars + '}';
    css += 'body[data-theme="dark"] { color-scheme:dark;' + darkVars + '}';
    css += 'body { background-color:var(--ct-body-bg-color); }';
    return css;
};

CustomizerPreview.prototype._buildDynamicCSS = function () {
    var v = {};

    for (var i = 0; i < this._cssSettings.length; i++) {
        v[this._cssSettings[i]] = this._getVal(this._cssSettings[i]);
    }

    assert(Object.keys(v).length > 0, 'Values must not be empty');

    var t1P = this._checkboxStyleProps(v.ct_topbar_text1_bold, v.ct_topbar_text1_italic, v.ct_topbar_text1_uppercase);
    var t2P = this._checkboxStyleProps(v.ct_topbar_text2_bold, v.ct_topbar_text2_italic, v.ct_topbar_text2_uppercase);
    var lnP = this._checkboxStyleProps(v.ct_topbar_links_bold, v.ct_topbar_links_italic, v.ct_topbar_links_uppercase);
    var tmP = this._checkboxStyleProps(v.ct_menu_top_bold, v.ct_menu_top_italic, v.ct_menu_top_uppercase);
    var smP = this._checkboxStyleProps(v.ct_menu_sub_bold, v.ct_menu_sub_italic, v.ct_menu_sub_uppercase);

    var subBorder = v.ct_menu_sub_border_width + 'px ' + v.ct_menu_sub_border_style + ' var(--ct-menu-sub-border-color)';

    var css = this._buildVariableCSS();
    css += this._buildTopbarCSS(v, t1P, t2P, lnP);
    css += this._buildHeaderCSS(v);
    css += this._buildMenuCSS(v, tmP, smP, subBorder);
    css += this._buildMobileMenuCSS(v);
    css += this._buildBreadcrumbCSS(v);
    css += this._buildTypographyCSS(v);
    css += this._buildFormCSS(v);
    css += this._buildFooterCSS();
    css += this._buildBackToTopCSS(v);
    return css;
};

CustomizerPreview.prototype._buildTopbarCSS = function (v, t1P, t2P, lnP) {
    assert(typeof v === 'object', 'Values must be an object');

    var css = '.topbar { background-color:var(--ct-topbar-bg-color); }';
    css += '.topbar__text1 {' +
        'font-size:' + v.ct_topbar_text1_size + 'px;color:var(--ct-topbar-text1-color);' + t1P +
        'margin:' + v.ct_topbar_text1_margin_top + 'px ' + v.ct_topbar_text1_margin_right + 'px 0 ' + v.ct_topbar_text1_margin_left + 'px;}';
    css += '.topbar__phone-link { color:var(--ct-topbar-text2-color); }';
    css += '.topbar__text2 {' +
        'font-size:' + v.ct_topbar_text2_size + 'px;color:var(--ct-topbar-text2-color);' + t2P +
        'margin:' + v.ct_topbar_text2_margin_top + 'px ' + v.ct_topbar_text2_margin_right + 'px 0 ' + v.ct_topbar_text2_margin_left + 'px;}';
    css += '.topbar__right .menu li a {' +
        'font-size:' + v.ct_topbar_links_size + 'px;color:var(--ct-topbar-links-color);' + lnP +
        'margin:' + v.ct_topbar_links_margin_top + 'px ' + v.ct_topbar_links_margin_right + 'px 0 ' + v.ct_topbar_links_margin_left + 'px;}';
    css += '.topbar__right .menu li a:hover { color:var(--ct-topbar-links-hover-color); }';
    return css;
};

CustomizerPreview.prototype._buildHeaderCSS = function (v) {
    assert(typeof v === 'object', 'Values must be an object');

    var css = '.site-header { background-color:var(--ct-header-bg-color); border-bottom:1px solid var(--ct-header-border-color); }';
    css += '.site-header__logo {' +
        'margin:' + v.ct_header_logo_margin_top + 'px ' + v.ct_header_logo_margin_right + 'px ' + v.ct_header_logo_margin_bottom + 'px ' + v.ct_header_logo_margin_left + 'px;}';
    css += '.site-header__logo img { height:' + v.ct_header_logo_height + 'px; width:auto; }';
    css += '.site-header__logo .site-title-text { font-size:28px; font-weight:700; color:var(--ct-site-title-color); }';
    return css;
};

CustomizerPreview.prototype._buildMenuCSS = function (v, tmP, smP, subBorder) {
    assert(typeof v === 'object', 'Values must be an object');
    assert(typeof subBorder === 'string', 'Sub border must be a string');

    var css = '.main-navigation .menu > li > a {' +
        'font-size:' + v.ct_menu_top_font_size + 'px;color:var(--ct-menu-top-color);' + tmP +
        'margin:' + v.ct_menu_top_margin_top + 'px ' + v.ct_menu_top_margin_right + 'px 0 ' + v.ct_menu_top_margin_left + 'px;}';
    css += '.main-navigation .menu > li > a:hover,' +
        '.main-navigation .menu > li.current-menu-item > a,' +
        '.main-navigation .menu > li.current-menu-ancestor > a {' +
        'border-bottom-color:var(--ct-menu-active-underline-color);}';
    css += '.main-navigation .sub-menu { background:var(--ct-menu-sub-bg-color); border:' + subBorder + '; box-shadow:0 2px 8px rgba(0,0,0,0.08); }';
    css += '.main-navigation .sub-menu li a {' +
        'font-size:' + v.ct_menu_sub_font_size + 'px;color:var(--ct-menu-sub-color);' + smP +
        'margin:' + v.ct_menu_sub_margin_top + 'px ' + v.ct_menu_sub_margin_right + 'px 0 ' + v.ct_menu_sub_margin_left + 'px;' +
        'border-bottom:' + subBorder + ';}';
    css += '.main-navigation .sub-menu li a:hover { background:var(--ct-menu-sub-hover-bg-color); }';
    return css;
};

CustomizerPreview.prototype._buildMobileMenuCSS = function (v) {
    assert(typeof v === 'object', 'Values must be an object');
    assert(typeof v.ct_mobile_menu_border_width !== 'undefined', 'Mobile border width must exist');

    var bw = v.ct_mobile_menu_border_width;
    var css = '@media screen and (max-width:768px) {';
    css += '.main-navigation .menu { background:var(--ct-mobile-menu-bg-color);' +
        'border:' + bw + 'px solid var(--ct-mobile-menu-border-color);' +
        'box-shadow:0 4px 12px rgba(0,0,0,0.1); }';
    css += '.menu-toggle { border:' + bw + 'px solid var(--ct-mobile-menu-border-color); }';
    css += '}';
    return css;
};

CustomizerPreview.prototype._buildBreadcrumbCSS = function (v) {
    assert(typeof v === 'object', 'Values must be an object');

    var bcFw = v.ct_breadcrumb_active_bold ? 'font-weight:700;' : 'font-weight:400;';
    var bcTd = v.ct_breadcrumb_active_underline ? 'text-decoration:underline;' : 'text-decoration:none;';

    var css = '.breadcrumbs a, .breadcrumbs .breadcrumb-separator {' +
        'font-size:' + v.ct_breadcrumb_font_size + 'px;color:var(--ct-breadcrumb-color);' +
        'text-transform:' + v.ct_breadcrumb_transform + ';}';
    css += '.breadcrumbs a:hover { color:var(--ct-breadcrumb-active-color); }';
    css += '.breadcrumbs .breadcrumb-current {' +
        'font-size:' + v.ct_breadcrumb_font_size + 'px;color:var(--ct-breadcrumb-active-color);' +
        'text-transform:' + v.ct_breadcrumb_transform + ';' + bcFw + bcTd + '}';
    return css;
};

CustomizerPreview.prototype._buildTypographyCSS = function (v) {
    assert(typeof v === 'object', 'Values must be an object');

    var headings = [
        ['h1, .entry-title, .page-title', 'ct_h1'],
        ['h2', 'ct_h2'],
        ['h3, .section-title', 'ct_h3'],
        ['h4', 'ct_h4'],
        ['h5', 'ct_h5'],
    ];

    var css = '';
    var maxHeadings = 5;

    for (var i = 0; i < headings.length && i < maxHeadings; i++) {
        var sel = headings[i][0];
        var prefix = headings[i][1];
        var fw = v[prefix + '_bold'] ? 'font-weight:700;' : 'font-weight:400;';
        var fs = v[prefix + '_italic'] ? 'font-style:italic;' : 'font-style:normal;';
        css += sel + '{font-size:' + v[prefix + '_font_size'] + 'px;color:var(--' + prefix.replace('_', '-') + '-color);' +
            fw + fs + 'text-transform:' + v[prefix + '_transform'] + ';}';
    }

    var pFw = v.ct_paragraph_bold ? 'font-weight:700;' : 'font-weight:400;';
    var pFs = v.ct_paragraph_italic ? 'font-style:italic;' : 'font-style:normal;';
    var pLh = v.ct_paragraph_line_height || 1.6;
    var pMt = v.ct_paragraph_margin_top || 0;
    var pMr = v.ct_paragraph_margin_right || 0;
    var pMb = v.ct_paragraph_margin_bottom || 16;
    var pMl = v.ct_paragraph_margin_left || 0;
    css += '.ct-p {' +
        'font-size:' + v.ct_paragraph_font_size + 'px;color:var(--ct-paragraph-color);' +
        pFw + pFs + 'text-transform:' + v.ct_paragraph_transform + ';' +
        'line-height:' + pLh + ';' +
        'margin-top:' + pMt + 'px;' +
        'margin-right:' + pMr + 'px;' +
        'margin-bottom:' + pMb + 'px;' +
        'margin-left:' + pMl + 'px;}';

    var sFw = v.ct_special_bold ? 'font-weight:700;' : 'font-weight:400;';
    var sFs = v.ct_special_italic ? 'font-style:italic;' : 'font-style:normal;';
    css += '.special-text, .reach-us__company, .reach-us__address, .reach-us__phone, .reach-us__fax, .reach-us__email {' +
        'font-size:' + v.ct_special_font_size + 'px;color:var(--ct-special-color);' +
        sFw + sFs + 'text-transform:' + v.ct_special_transform + ';}';

    return css;
};

CustomizerPreview.prototype._buildFormCSS = function (v) {
    assert(typeof this._api === 'function', 'API required');

    var iconW = v.ct_social_icon_width || 36;
    var iconH = v.ct_social_icon_height || 36;

    var css = '.contact-section .section-title::after {' +
        'background:linear-gradient(to right, var(--ct-topbar-bg-color) 30%, #ccc 30%);}';
    css += '.ct-contact-form input[type="text"], .ct-contact-form input[type="email"], .ct-contact-form input[type="tel"], .ct-contact-form textarea {' +
        'border:1px solid var(--ct-form-input-border-color);background:var(--ct-form-input-bg-color);color:var(--ct-paragraph-color);}';
    css += '.ct-contact-form input:focus, .ct-contact-form textarea:focus { border-color:var(--ct-topbar-bg-color); }';
    css += '.ct-contact-form__submit { background:var(--ct-topbar-bg-color); }';
    css += '.ct-contact-form__submit:hover { background:var(--ct-form-submit-hover-color); }';
    css += '.social-icons a:not(.customize-unpreviewable), .social-icons .share-button { width:' + iconW + 'px;height:' + iconH + 'px; }';
    css += '.social-icons .share-button { background:var(--ct-social-bg-color); }';
    css += '.social-icons a img, .social-icons a svg, .social-icons .share-button svg { width:' + iconW + 'px;height:' + iconH + 'px; }';
    return css;
};

CustomizerPreview.prototype._buildFooterCSS = function () {
    assert(typeof this._api === 'function', 'API required');

    var css = '.site-footer { background:var(--ct-footer-bg-color); color:var(--ct-footer-text-color); }';
    css += '.site-footer a { color:var(--ct-footer-link-color); }';
    css += '.site-footer a:hover { color:var(--ct-footer-link-hover-color); }';
    return css;
};

CustomizerPreview.prototype._buildBackToTopCSS = function (v) {
    assert(typeof v === 'object', 'Values must be an object');

    var bw = v.ct_back_to_top_border_width || 1;
    var br = v.ct_back_to_top_border_radius || 8;

    return '.ct-back-to-top {' +
        '--ct-back-to-top-border-width:' + bw + 'px;' +
        '--ct-back-to-top-border-radius:' + br + 'px;' +
        '}';
};

CustomizerPreview.prototype._bindSiteIcon = function () {
    assert(typeof this._api === 'function', 'API required');

    var api = this._api;
    var $ = this._$;

    api('site_icon', function (value) {
        value.bind(function (to) {
            if (!to) {
                $('link[rel="icon"]').remove();
                $('link[rel="apple-touch-icon"]').remove();
                return;
            }

            if (typeof ctCustomizerData === 'undefined') {
                return;
            }

            var url = ctCustomizerData.restUrl + to;
            $.ajax({
                url: url,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', ctCustomizerData.nonce);
                },
                success: function (data) {
                    if (!data || !data.source_url) {
                        return;
                    }

                    var sizes = data.media_details && data.media_details.sizes
                        ? data.media_details.sizes
                        : {};

                    var icon32 = sizes['site_icon-32'] ? sizes['site_icon-32'].source_url : data.source_url;
                    var icon180 = sizes['site_icon-180'] ? sizes['site_icon-180'].source_url : data.source_url;
                    var icon192 = sizes['site_icon-192'] ? sizes['site_icon-192'].source_url : data.source_url;
                    var icon270 = sizes['site_icon-270'] ? sizes['site_icon-270'].source_url : data.source_url;

                    $('link[rel="icon"][sizes="32x32"]').attr('href', icon32);
                    $('link[rel="icon"][sizes="192x192"]').attr('href', icon192);
                    $('link[rel="apple-touch-icon"]').attr('href', icon180);

                    if ($('meta[name="msapplication-TileImage"]').length > 0) {
                        $('meta[name="msapplication-TileImage"]').attr('content', icon270);
                    }
                },
            });
        });
    });
};

CustomizerPreview.prototype._bindBackToTopPosition = function () {
    assert(typeof this._api === 'function', 'API required');

    var api = this._api;

    api('ct_back_to_top_position', function (value) {
        value.bind(function (to) {
            var btn = document.getElementById('ct-back-to-top');
            if (!btn) {
                return;
            }
            btn.classList.remove('ct-back-to-top--left', 'ct-back-to-top--right');
            btn.classList.add('ct-back-to-top--' + (to === 'left' ? 'left' : 'right'));
        });
    });
};

CustomizerPreview.prototype._bindBackToTopSettings = function () {
    assert(typeof this._api === 'function', 'API required');

    var api = this._api;
    var $ = this._$;

    /* Enable / Disable toggle */
    api('ct_back_to_top_enabled', function (value) {
        value.bind(function (to) {
            var btn = document.getElementById('ct-back-to-top');
            if (!btn) { return; }
            if (to) {
                btn.classList.remove('ct-back-to-top--disabled');
            } else {
                btn.classList.add('ct-back-to-top--disabled');
            }
        });
    });

    /* Label text */
    api('ct_back_to_top_label', function (value) {
        value.bind(function (to) {
            var btn = document.getElementById('ct-back-to-top');
            if (!btn) { return; }

            var labelEl = btn.querySelector('.ct-back-to-top__label');

            if (to) {
                if (!labelEl) {
                    labelEl = document.createElement('span');
                    labelEl.className = 'ct-back-to-top__label';
                    btn.appendChild(labelEl);
                }
                labelEl.textContent = to;
                btn.classList.add('ct-back-to-top--has-label');
                btn.setAttribute('aria-label', to);
            } else {
                if (labelEl) {
                    labelEl.remove();
                }
                btn.classList.remove('ct-back-to-top--has-label');
                btn.setAttribute('aria-label', 'Back to top');
            }
        });
    });

    /* Custom icon */
    api('ct_back_to_top_icon', function (value) {
        value.bind(function (to) {
            var btn = document.getElementById('ct-back-to-top');
            if (!btn) { return; }

            var iconWrap = btn.querySelector('.ct-back-to-top__icon');
            if (!iconWrap) { return; }

            var defaultSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"></polyline></svg>';

            if (!to) {
                iconWrap.innerHTML = defaultSvg;
                return;
            }

            if (typeof ctCustomizerData === 'undefined') {
                return;
            }

            var url = ctCustomizerData.restUrl + to;
            $.ajax({
                url: url,
                beforeSend: function (xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', ctCustomizerData.nonce);
                },
                success: function (data) {
                    if (!data || !data.source_url) {
                        iconWrap.innerHTML = defaultSvg;
                        return;
                    }
                    var imgUrl = data.source_url;
                    iconWrap.innerHTML = '<img src="' + imgUrl + '" width="20" height="20" aria-hidden="true" alt="" style="object-fit:contain;">';
                },
                error: function () {
                    iconWrap.innerHTML = defaultSvg;
                },
            });
        });
    });
};

CustomizerPreview.prototype._bindContactPoint = function () {
    assert(typeof this._api === 'function', 'API required');
    assert(typeof this._$ === 'function', 'jQuery required');

    var $ = this._$;
    var api = this._api;
    var MAX_BLOCKS = 10;

    api('bs_custom_contact_point', function (value) {
        value.bind(function (raw) {
            var cp = {};
            if (raw && typeof raw === 'string') {
                try { cp = JSON.parse(raw); } catch (e) { cp = {}; }
            } else if (raw && typeof raw === 'object') {
                cp = raw;
            }

            var phone = cp.telephone || '';
            var fax = cp.fax_number || '';
            var email = cp.email || '';
            var addr = (cp.address && typeof cp.address === 'object') ? cp.address : {};

            var streetNumber = addr.street_number || '';
            var streetAddress = addr.street_address || '';
            var postal = addr.postal_code || '';
            var city = addr.city || '';
            var state = addr.state || '';
            var country = addr.country || '';

            var line1 = (streetNumber + ' ' + streetAddress).replace(/^\s+|\s+$/g, '');
            var line2Parts = [];
            if (postal) { line2Parts.push(postal); }
            if (city) { line2Parts.push(city); }
            if (state) { line2Parts.push(state); }
            if (country) { line2Parts.push(country); }
            var line2 = line2Parts.join(' ');

            var widgetStreet = (streetNumber + ' ' + streetAddress).replace(/^\s+|\s+$/g, '');
            var widgetCityParts = [];
            if (postal) { widgetCityParts.push(postal); }
            if (city) { widgetCityParts.push(city); }
            var widgetCity = widgetCityParts.join(' ');

            var hasAddress = line1 || line2;
            var phoneTel = phone.replace(/[^0-9+]/g, '');

            var $blocks = $('.ct-contact-point-block');
            var blockCount = $blocks.length;
            if (blockCount > MAX_BLOCKS) { blockCount = MAX_BLOCKS; }

            for (var i = 0; i < blockCount; i++) {
                var $block = $blocks.eq(i);

                $block.find('.ct-cp-address').toggleClass('ct-cp-hidden', !hasAddress);

                $block.find('.ct-cp-address-line1').text(line1);
                $block.find('.ct-cp-address-line2').text(line2);

                $block.find('.ct-cp-street').text(widgetStreet).toggleClass('ct-cp-hidden', !widgetStreet);
                $block.find('.ct-cp-city').text(widgetCity).toggleClass('ct-cp-hidden', !widgetCity);
                $block.find('.ct-cp-state').text(state).toggleClass('ct-cp-hidden', !state);
                $block.find('.ct-cp-country').text(country).toggleClass('ct-cp-hidden', !country);

                $block.find('.ct-cp-phone').toggleClass('ct-cp-hidden', !phone);
                $block.find('.ct-cp-phone-value').text(phone);
                $block.find('.ct-cp-phone-link').attr('href', 'tel:' + phoneTel);

                $block.find('.ct-cp-fax').toggleClass('ct-cp-hidden', !fax);
                $block.find('.ct-cp-fax-value').text(fax);

                $block.find('.ct-cp-email').toggleClass('ct-cp-hidden', !email);
                $block.find('.ct-cp-email-value').text(email);
                $block.find('.ct-cp-email-link').attr('href', 'mailto:' + email);
            }
        });
    });
};

/**
 * Live font preview — listens for ct_font_face_css, ct_font_family,
 * and ct_font_enabled changes and injects a <style> block.
 */
CustomizerPreview.prototype._bindFontPreview = function () {
    assert(typeof this._api === 'function', 'API required');

    var api = this._api;
    var FONT_STYLE_ID = 'ct-font-face-preview';

    var SYSTEM_FONT_STACK = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif";

    function ensureStyleEl() {
        var el = document.getElementById(FONT_STYLE_ID);
        if (!el) {
            el = document.createElement('style');
            el.id = FONT_STYLE_ID;
            document.head.appendChild(el);
        }
        return el;
    }

    function updateFontStyle() {
        var enabled = api('ct_font_enabled') ? api('ct_font_enabled').get() : false;
        var faceCss = api('ct_font_face_css') ? api('ct_font_face_css').get() : '';
        var family = api('ct_font_family') ? api('ct_font_family').get() : '';

        var styleEl = ensureStyleEl();

        if (!enabled) {
            /* Override PHP-rendered font CSS with system stack */
            styleEl.textContent = 'body, button, input, select, textarea { font-family: ' + SYSTEM_FONT_STACK + '; }';
            return;
        }

        if (!faceCss) {
            styleEl.textContent = '';
            return;
        }

        var fullCss = faceCss;
        if (family) {
            fullCss += "\nbody, button, input, select, textarea { font-family: '" + family
                + "', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }";
        }

        styleEl.textContent = fullCss;
    }

    api('ct_font_face_css', function (value) {
        value.bind(function () { updateFontStyle(); });
    });

    api('ct_font_family', function (value) {
        value.bind(function () { updateFontStyle(); });
    });

    api('ct_font_enabled', function (value) {
        value.bind(function () { updateFontStyle(); });
    });

    /* Apply current font state on initial load */
    updateFontStyle();
};

CustomizerPreview.prototype._updatePreviewCSS = function () {
    assert(typeof document !== 'undefined', 'document must exist');

    var styleEl = document.getElementById(STYLE_ELEMENT_ID);
    if (!styleEl) {
        styleEl = document.createElement('style');
        styleEl.id = STYLE_ELEMENT_ID;
        document.head.appendChild(styleEl);
    }
    styleEl.textContent = this._buildDynamicCSS();

    assert(styleEl.textContent.length > 0, 'CSS must not be empty');
};

/* Bootstrap — only runs inside the customizer preview iframe */
export function init() {
    if (typeof wp !== 'undefined' && wp.customize && typeof jQuery !== 'undefined') {
        jQuery(function ($) {
            new CustomizerPreview(wp.customize, $);
        });
    }
}
