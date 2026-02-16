/**
 * CustomizerRange - Live value display for range slider controls
 * in the WordPress Customizer panel.
 *
 * Updates the numeric label next to each range input as the user drags.
 * Uses event delegation on the panel container so it works with
 * deferred/lazy-loaded Customizer controls.
 *
 * @package CT_Custom
 */

import { assert } from './utils.js';

var VALUE_CLASS = '.ct-range-value';

/**
 * Handle range input events via delegation.
 * Updates the numeric value display next to the slider.
 *
 * @param {Event} e Input event.
 */
function handleInput(e) {
    assert(e instanceof Event, 'e must be an Event');

    var target = e.target;

    if (!target || target.type !== 'range') {
        return;
    }

    var display = target.parentElement
        ? target.parentElement.querySelector(VALUE_CLASS)
        : null;

    if (display) {
        display.textContent = target.value;
    }
}

/**
 * Bootstrap — runs in the customizer panel.
 *
 * Uses event delegation on the customize-controls container
 * so all range inputs (including deferred ones) get handled.
 */
export function init() {
    var container = document.getElementById('customize-controls')
        || document.body;

    assert(container instanceof HTMLElement, 'container must be an HTMLElement');

    container.addEventListener('input', handleInput);
}
