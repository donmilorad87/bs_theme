/**
 * CustomizerFontFamily - Datalist-backed font family input.
 *
 * When the user picks a font from the datalist (or types a valid name),
 * fires a ct-font-family-changed custom event so the font-weights
 * module can update its available variant checkboxes.
 *
 * @package BS_Custom
 */

import { assert } from './utils.js';

var DEBOUNCE_MS = 300;

/**
 * Look up the data-variants string for a given family name
 * from the datalist options.
 *
 * @param {HTMLDataListElement} datalist  The datalist element.
 * @param {string}             family    Font family name.
 * @return {string}  Comma-separated variants or empty string.
 */
/**
 * Look up font data for a given family name from the datalist options.
 *
 * @param {HTMLDataListElement} datalist  The datalist element.
 * @param {string}             family    Font family name (display name).
 * @return {object}  Object with variants and apiFamily, or empty strings.
 */
function getFontDataForFamily(datalist, family) {
    assert(datalist instanceof HTMLElement, 'datalist must be an HTMLElement');
    assert(typeof family === 'string', 'family must be a string');

    if (!family) {
        return { variants: '', apiFamily: '' };
    }

    var options = datalist.options || datalist.querySelectorAll('option');
    var max = 2000;

    for (var i = 0; i < options.length && i < max; i++) {
        if (options[i].value === family) {
            return {
                variants: options[i].getAttribute('data-variants') || '',
                apiFamily: options[i].getAttribute('data-api-family') || family
            };
        }
    }

    return null;
}

/**
 * Set up the family change handler on the datalist input.
 *
 * @param {HTMLElement} container  The .ct-font-family-control element.
 */
function setupFamilyInput(container) {
    assert(container instanceof HTMLElement, 'container must be an HTMLElement');

    var inputEl = container.querySelector('.ct-font-family-control__input');
    if (!inputEl) {
        return;
    }

    var listId = inputEl.getAttribute('list');
    var datalist = listId ? document.getElementById(listId) : null;

    assert(inputEl instanceof HTMLInputElement, 'input must be an HTMLInputElement');

    var timer = null;
    var lastFamily = inputEl.value || '';

    inputEl.addEventListener('input', function () {
        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(function () {
            var value = inputEl.value.trim();

            if (value === lastFamily) {
                return;
            }

            /* Only dispatch when value matches a real font in the datalist */
            var fontData = datalist
                ? getFontDataForFamily(datalist, value)
                : null;

            if (!fontData) {
                return;
            }

            lastFamily = value;

            document.dispatchEvent(new CustomEvent('ct-font-family-changed', {
                detail: {
                    family: value,
                    apiFamily: fontData.apiFamily,
                    variants: fontData.variants
                }
            }));
        }, DEBOUNCE_MS);
    });
}

/**
 * Bootstrap — runs in the customizer panel.
 */
export function init() {
    if (typeof wp === 'undefined' || !wp.customize) {
        return;
    }

    wp.customize.control('ct_font_family', function (control) {
        control.deferred.embedded.done(function () {
            var container = control.container.find('.ct-font-family-control')[0];
            if (container) {
                setupFamilyInput(container);
            }
        });
    });
}
