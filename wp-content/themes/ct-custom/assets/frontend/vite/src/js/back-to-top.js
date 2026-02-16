/**
 * BackToTop - Scroll-to-top button visibility and click handler
 *
 * Shows a fixed button when the user scrolls past a threshold,
 * then smooth-scrolls to the top on click. Scroll events are
 * throttled with requestAnimationFrame (~60 fps).
 *
 * @package CT_Custom
 */

const SCROLL_THRESHOLD = 300;
const MAX_SCROLL_Y = 999999;
const VISIBLE_CLASS = 'ct-back-to-top--visible';

function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

export default class BackToTop {
    constructor() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof window !== 'undefined', 'window must exist');

        this._button = document.getElementById('ct-back-to-top');
        this._ticking = false;

        if (!this._button) {
            return;
        }

        assert(this._button instanceof HTMLElement, 'Button must be an HTMLElement');
        assert(typeof this._button.classList !== 'undefined', 'classList must exist');

        this._bindScroll();
        this._bindClick();
        this._onScroll();
    }

    _bindScroll() {
        assert(typeof window.addEventListener === 'function', 'addEventListener must exist');
        assert(typeof this._ticking === 'boolean', 'ticking must be boolean');

        window.addEventListener('scroll', () => {
            if (!this._ticking) {
                this._ticking = true;
                window.requestAnimationFrame(() => {
                    this._onScroll();
                    this._ticking = false;
                });
            }
        });
    }

    _bindClick() {
        assert(this._button !== null, 'Button must exist');
        assert(typeof this._button.addEventListener === 'function', 'addEventListener must exist');

        this._button.addEventListener('click', () => {
            this._scrollToTop();
        });
    }

    _onScroll() {
        assert(typeof window.scrollY === 'number', 'scrollY must be a number');

        const scrollY = Math.min(window.scrollY, MAX_SCROLL_Y);

        assert(scrollY >= 0 && scrollY <= MAX_SCROLL_Y, 'scrollY must be in range');

        if (scrollY > SCROLL_THRESHOLD) {
            this._button.classList.add(VISIBLE_CLASS);
        } else {
            this._button.classList.remove(VISIBLE_CLASS);
        }
    }

    _scrollToTop() {
        assert(typeof window.scrollTo === 'function', 'scrollTo must exist');
        assert(typeof window !== 'undefined', 'window must exist');

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}
