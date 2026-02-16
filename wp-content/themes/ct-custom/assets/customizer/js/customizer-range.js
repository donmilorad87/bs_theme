/**
 * CustomizerRange - Live value display for range slider controls
 * in the WordPress Customizer panel.
 *
 * Updates the numeric label next to each range input as the user drags.
 *
 * @package BS_Custom
 */

(function () {
    'use strict';

    var MAX_RANGES = 200;
    var SELECTOR = 'input[type="range"]';
    var VALUE_CLASS = '.ct-range-value';

    function assert(condition, message) {
        if (!condition) {
            throw new Error('Assertion failed: ' + (message || ''));
        }
    }

    function init() {
        var ranges = document.querySelectorAll(SELECTOR);

        assert(ranges instanceof NodeList, 'ranges must be a NodeList');

        var count = 0;

        for (var i = 0; i < ranges.length; i++) {
            if (count >= MAX_RANGES) {
                break;
            }
            count++;

            var range = ranges[i];
            var valueDisplay = range.parentElement.querySelector(VALUE_CLASS);

            if (!valueDisplay) {
                continue;
            }

            range.addEventListener('input', handleInput);
        }
    }

    function handleInput(e) {
        assert(e instanceof Event, 'e must be an Event');
        assert(e.target instanceof HTMLElement, 'target must be an HTMLElement');

        var display = e.target.parentElement.querySelector(VALUE_CLASS);
        if (display) {
            display.textContent = e.target.value;
        }
    }

    /* Bootstrap — runs in the customizer panel */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
