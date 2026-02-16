/**
 * CustomizerFontWeights - Checkbox-to-hidden-input synchronization
 * for the font weights multi-checkbox control.
 *
 * Each checkbox toggle syncs the checked values into the hidden input
 * as a comma-separated string (e.g. "400,400i,700").  Also listens
 * for the ct-font-family-changed event to update available checkboxes
 * based on the selected font's variants.
 *
 * Triggers a debounced REST API call to download font files when
 * weights change or a new font family is selected.
 *
 * @package CT_Custom
 */

import { assert } from './utils.js';

var MAX_CHECKBOXES = 20;
var DEBOUNCE_REST_MS = 500;

/** Current font family (display name) — updated on ct-font-family-changed event */
var currentFamily = '';

/** Current API family name (for Google Fonts API calls) */
var currentApiFamily = '';

/** Debounce timer for REST API calls */
var restTimer = null;

/**
 * Read all checked checkboxes and write a comma-separated string
 * into the hidden input, then trigger a Customizer setting change.
 *
 * @param {HTMLElement} wrapper  The .ct-font-weights-control element.
 * @param {HTMLInputElement} hiddenInput  The .ct-font-weights-value hidden input.
 */
function syncToHidden(wrapper, hiddenInput) {
    assert(wrapper instanceof HTMLElement, 'wrapper must be an HTMLElement');
    assert(hiddenInput instanceof HTMLInputElement, 'hiddenInput must be an HTMLInputElement');

    var checkboxes = wrapper.querySelectorAll('.ct-font-weight-checkbox');
    var values = [];
    var count = 0;

    for (var i = 0; i < checkboxes.length; i++) {
        if (count >= MAX_CHECKBOXES) {
            break;
        }
        count++;

        if (checkboxes[i].checked) {
            values.push(checkboxes[i].value);
        }
    }

    var newValue = values.join(',');
    hiddenInput.value = newValue;

    /* Trigger jQuery change so WordPress Customizer picks up the new value */
    if (typeof jQuery !== 'undefined') {
        jQuery(hiddenInput).trigger('change');
    }
}

/**
 * Bind checkbox change events to sync with the hidden input.
 *
 * @param {HTMLElement} controlContainer  The full control container.
 */
function bindCheckboxes(controlContainer) {
    assert(controlContainer instanceof HTMLElement, 'controlContainer must be an HTMLElement');

    var wrapper = controlContainer.querySelector('.ct-font-weights-control');
    var hiddenInput = controlContainer.querySelector('.ct-font-weights-value');

    if (!wrapper || !hiddenInput) {
        return;
    }

    var checkboxes = wrapper.querySelectorAll('.ct-font-weight-checkbox');
    var count = 0;

    for (var i = 0; i < checkboxes.length; i++) {
        if (count >= MAX_CHECKBOXES) {
            break;
        }
        count++;

        checkboxes[i].addEventListener('change', function () {
            syncToHidden(wrapper, hiddenInput);
            triggerFontDownload();
        });
    }
}

/**
 * Listen for font-family changes and update which weight checkboxes
 * are enabled/visible based on the selected font's available variants.
 * Auto-checks ALL available weights when a new font is selected.
 *
 * @param {HTMLElement} controlContainer  The full control container.
 */
function bindFamilyChange(controlContainer) {
    assert(controlContainer instanceof HTMLElement, 'controlContainer must be an HTMLElement');

    var wrapper = controlContainer.querySelector('.ct-font-weights-control');
    if (!wrapper) {
        return;
    }

    document.addEventListener('ct-font-family-changed', function (e) {
        var variants = (e.detail && e.detail.variants) ? e.detail.variants.split(',') : [];
        var family = (e.detail && e.detail.family) ? e.detail.family : '';
        var apiFamily = (e.detail && e.detail.apiFamily) ? e.detail.apiFamily : family;
        var checkboxes = wrapper.querySelectorAll('.ct-font-weight-checkbox');
        var count = 0;

        currentFamily = family;
        currentApiFamily = apiFamily;

        for (var i = 0; i < checkboxes.length; i++) {
            if (count >= MAX_CHECKBOXES) {
                break;
            }
            count++;

            var cb = checkboxes[i];
            var weightVal = cb.value;

            /* Map variant names to our weight values */
            var isAvailable = variants.length === 0 || variants.indexOf(weightVal) !== -1
                || variants.indexOf(mapVariantToWeight(weightVal)) !== -1;

            var label = cb.closest('label');
            if (label) {
                label.style.display = isAvailable ? '' : 'none';
            }

            /* Auto-check all available weights; uncheck unavailable */
            cb.checked = isAvailable;
        }

        /* Sync after updating checkboxes */
        var hiddenInput = controlContainer.querySelector('.ct-font-weights-value');
        if (hiddenInput) {
            syncToHidden(wrapper, hiddenInput);
        }

        /* Trigger font download for the new family (cleanup + download) */
        triggerFontDownload();
    });
}

/**
 * Map Google Fonts variant names to our weight value format.
 * Google uses "regular", "italic", "100italic" etc.
 *
 * @param {string} weight  Our weight value like "400", "400i", "700".
 * @return {string}  The Google Fonts variant name.
 */
function mapVariantToWeight(weight) {
    assert(typeof weight === 'string', 'weight must be a string');

    var map = {
        '100': '100', '200': '200', '300': '300',
        '400': 'regular', '500': '500', '600': '600',
        '700': '700', '800': '800', '900': '900',
        '100i': '100italic', '200i': '200italic', '300i': '300italic',
        '400i': 'italic', '500i': '500italic', '600i': '600italic',
        '700i': '700italic', '800i': '800italic', '900i': '900italic'
    };

    return map[weight] || weight;
}

/**
 * Auto-check all available weight checkboxes for the current font family.
 * Reads the current font's variants from the font-family datalist and
 * checks all visible/available weight checkboxes.
 *
 * @param {HTMLElement} controlContainer  The full control container.
 */
function autoCheckAllWeights(controlContainer) {
    assert(controlContainer instanceof HTMLElement, 'controlContainer must be an HTMLElement');

    var wrapper = controlContainer.querySelector('.ct-font-weights-control');
    if (!wrapper) {
        return;
    }

    var checkboxes = wrapper.querySelectorAll('.ct-font-weight-checkbox');
    var count = 0;

    for (var i = 0; i < checkboxes.length; i++) {
        if (count >= MAX_CHECKBOXES) {
            break;
        }
        count++;

        var cb = checkboxes[i];
        var label = cb.closest('label');

        /* Check all visible (available) checkboxes */
        if (label && label.style.display !== 'none') {
            cb.checked = true;
        }
    }

    /* Sync checked state to hidden input */
    var hiddenInput = controlContainer.querySelector('.ct-font-weights-value');
    if (hiddenInput) {
        syncToHidden(wrapper, hiddenInput);
    }
}

/**
 * Send a debounced cleanup request to the REST API (empty weights = clean all files).
 * Clears ct_font_face_css and removes the preview style from the iframe.
 * Uses the shared restTimer to avoid overlapping with download requests.
 */
function triggerFontCleanup() {
    if (restTimer) {
        clearTimeout(restTimer);
    }

    /* Remove preview immediately for instant visual feedback */
    removePreviewStyle();

    /* Clear face CSS setting immediately */
    if (typeof wp !== 'undefined' && wp.customize) {
        var cssSetting = wp.customize('ct_font_face_css');
        if (cssSetting) {
            cssSetting.set('');
        }
    }

    /* Debounce the REST call to avoid rate limiting */
    restTimer = setTimeout(function () {
        var fontData = window.ctCustomizerFontData;
        if (!fontData || !fontData.apiUrl || !fontData.nonce) {
            return;
        }

        /* Determine the family to send (needed for endpoint validation) */
        var family = currentFamily || currentApiFamily || '';
        if (!family && typeof wp !== 'undefined' && wp.customize) {
            var familySetting = wp.customize('ct_font_family');
            if (familySetting) {
                family = familySetting.get() || '';
            }
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', fontData.apiUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-WP-Nonce', fontData.nonce);
        xhr.send(JSON.stringify({ family: family, weights: '' }));
    }, DEBOUNCE_REST_MS);
}

/**
 * Remove the font preview style element from the Customizer preview iframe.
 */
function removePreviewStyle() {
    var previewFrame = document.querySelector('#customize-preview iframe');
    if (!previewFrame || !previewFrame.contentDocument) {
        return;
    }

    var existing = previewFrame.contentDocument.getElementById('ct-font-face-preview');
    if (existing) {
        existing.parentNode.removeChild(existing);
    }
}

/**
 * Trigger a debounced REST API call to download font files.
 * Called after weight checkbox changes or font family changes.
 */
function triggerFontDownload() {
    if (restTimer) {
        clearTimeout(restTimer);
    }

    restTimer = setTimeout(function () {
        /* Skip download if fonts are disabled */
        if (typeof wp !== 'undefined' && wp.customize) {
            var enabledSetting = wp.customize('ct_font_enabled');
            if (enabledSetting && !enabledSetting.get()) {
                return;
            }
        }

        var family = currentFamily;
        var apiFamily = currentApiFamily || family;
        var weights = '';

        /* Read current weights from the Customizer setting */
        if (typeof wp !== 'undefined' && wp.customize) {
            var weightsSetting = wp.customize('ct_font_weights');
            if (weightsSetting) {
                weights = weightsSetting.get() || '';
            }

            /* Read family from setting if not set by event */
            if (!family) {
                var familySetting = wp.customize('ct_font_family');
                if (familySetting) {
                    family = familySetting.get() || '';
                    apiFamily = family;
                }
            }
        }

        if (!family || !weights) {
            return;
        }

        downloadFont(family, apiFamily, weights);
    }, DEBOUNCE_REST_MS);
}

/**
 * Call the REST API to download font files and get @font-face CSS.
 *
 * @param {string} family    Font display name (for CSS font-family).
 * @param {string} apiFamily Font API name (for Google Fonts API).
 * @param {string} weights   Comma-separated weights.
 */
function downloadFont(family, apiFamily, weights) {
    assert(typeof family === 'string', 'family must be a string');
    assert(typeof apiFamily === 'string', 'apiFamily must be a string');
    assert(typeof weights === 'string', 'weights must be a string');

    var fontData = window.ctCustomizerFontData;
    if (!fontData || !fontData.apiUrl || !fontData.nonce) {
        return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open('POST', fontData.apiUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-WP-Nonce', fontData.nonce);

    xhr.onload = function () {
        if (xhr.status !== 200) {
            return;
        }

        var response = null;
        try {
            response = JSON.parse(xhr.responseText);
        } catch (e) {
            return;
        }

        if (!response || !response.success || !response.data) {
            return;
        }

        var faceCss = response.data.face_css || '';

        /* Update the ct_font_face_css setting in the Customizer */
        if (typeof wp !== 'undefined' && wp.customize) {
            var cssSetting = wp.customize('ct_font_face_css');
            if (cssSetting) {
                cssSetting.set(faceCss);
            }
        }

        /* Inject @font-face + body font-family into preview iframe */
        injectPreviewStyle(faceCss, family);
    };

    xhr.send(JSON.stringify({ family: apiFamily, weights: weights }));
}

/**
 * Inject @font-face CSS and body font-family rule into the
 * Customizer preview iframe for instant font preview.
 *
 * @param {string} css    The @font-face CSS string.
 * @param {string} family The font family name.
 */
function injectPreviewStyle(css, family) {
    assert(typeof css === 'string', 'css must be a string');
    assert(typeof family === 'string', 'family must be a string');

    if (typeof wp === 'undefined' || !wp.customize) {
        return;
    }

    var previewFrame = document.querySelector('#customize-preview iframe');
    if (!previewFrame || !previewFrame.contentDocument) {
        return;
    }

    var doc = previewFrame.contentDocument;

    /* Build full CSS: @font-face rules + body font-family */
    var fullCss = css;
    if (family) {
        fullCss += "\nbody, button, input, select, textarea { font-family: '" + family
            + "', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }";
    }

    var styleId = 'ct-font-face-preview';
    var existing = doc.getElementById(styleId);

    if (existing) {
        existing.textContent = fullCss;
    } else {
        var style = doc.createElement('style');
        style.id = styleId;
        style.textContent = fullCss;
        doc.head.appendChild(style);
    }
}

/**
 * Set the active (visible) state of the font family and weights controls.
 *
 * @param {boolean} isActive  Whether the controls should be visible.
 */
function setFontControlsActive(isActive) {
    if (typeof wp === 'undefined' || !wp.customize) {
        return;
    }

    wp.customize.control('ct_font_family', function (familyControl) {
        familyControl.active.set(isActive);
    });

    wp.customize.control('ct_font_weights', function (weightsControl) {
        weightsControl.active.set(isActive);
    });
}

/**
 * Bootstrap — runs in the customizer panel.
 */
export function init() {
    if (typeof wp === 'undefined' || !wp.customize) {
        return;
    }

    /* Read current family from setting on init */
    wp.customize('ct_font_family', function (setting) {
        currentFamily = setting.get() || '';
    });

    /** @type {HTMLElement|null} Weights control container — set once embedded */
    var weightsContainer = null;

    wp.customize.control('ct_font_weights', function (control) {
        control.deferred.embedded.done(function () {
            var container = control.container[0];
            if (container) {
                weightsContainer = container;
                bindCheckboxes(container);
                bindFamilyChange(container);
            }
        });
    });

    /* Toggle visibility: listen for ct_font_enabled changes */
    wp.customize('ct_font_enabled', function (setting) {
        /* Set initial visibility based on current value */
        var initialValue = setting.get();
        setFontControlsActive(!!initialValue);

        /* React to toggle changes */
        setting.bind(function (newValue) {
            var isEnabled = !!newValue;
            setFontControlsActive(isEnabled);

            if (isEnabled) {
                /* Re-enabling: auto-check all weights and trigger download */
                if (weightsContainer) {
                    autoCheckAllWeights(weightsContainer);
                }
                triggerFontDownload();
            } else {
                /* Disabling: clean all font files and remove preview */
                triggerFontCleanup();
            }
        });
    });
}
