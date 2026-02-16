/**
 * Share - Web Share API "Share with a friend" buttons.
 *
 * Buttons are rendered by PHP (SSR). If the browser does not support
 * navigator.share(), this class removes the buttons from the DOM.
 * If supported, attaches click handlers to all .share-with-friend elements.
 *
 * @package BS_Custom
 */

const MAX_BUTTONS = 20;
const SELECTOR = '.share-with-friend';

function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

class Share {
    constructor() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof navigator !== 'undefined', 'navigator must exist');

        this._buttons = document.querySelectorAll(SELECTOR);

        if (!this._buttons.length) {
            return;
        }

        this._supportsShare = typeof navigator.share === 'function';
        this._init();
    }

    _init() {
        assert(this._buttons instanceof NodeList, 'buttons must be a NodeList');
        assert(this._buttons.length > 0, 'buttons must have at least one element');

        let count = 0;

        for (let i = 0; i < this._buttons.length; i++) {
            if (count >= MAX_BUTTONS) {
                break;
            }
            count++;

            const btn = this._buttons[i];

            if (!this._supportsShare) {
                btn.remove();
                continue;
            }

            btn.addEventListener('click', (e) => this._handleClick(e));
        }
    }

    _handleClick(e) {
        assert(e instanceof Event, 'e must be an Event');
        assert(e.currentTarget instanceof HTMLElement, 'target must be an HTMLElement');

        const btn = e.currentTarget;
        const url = btn.dataset.url || window.location.href;
        const title = btn.dataset.title || document.title;
        const text = btn.dataset.text || '';

        navigator.share({ title, text, url }).catch(() => {});
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { new Share(); });
} else {
    new Share();
}
