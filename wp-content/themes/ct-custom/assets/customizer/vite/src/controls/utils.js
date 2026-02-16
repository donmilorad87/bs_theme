/**
 * Shared utilities for Customizer control modules.
 *
 * @package CT_Custom
 */

/**
 * Assertion check — throws on failure.
 *
 * @param {boolean} condition
 * @param {string}  message
 */
export function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

/**
 * Escape a string for safe insertion into HTML.
 *
 * @param {string} text
 * @return {string}
 */
export function escapeHtml(text) {
    assert(typeof text === 'string', 'escapeHtml expects a string');

    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
