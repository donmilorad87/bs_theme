/**
 * Navigation - Mobile menu toggle, keyboard nav, touch submenus, overflow flip
 *
 * Handles toggling the navigation menu for small screens, enables TAB key
 * navigation support for dropdown menus, and flips nested sub-menus that
 * would overflow the viewport.
 *
 * @package BS_Custom
 */

const MAX_MENU_ITEMS = 100;

function assert(condition, message) {
    if (!condition) {
        throw new Error('Assertion failed: ' + (message || ''));
    }
}

export default class Navigation {
    constructor() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof window !== 'undefined', 'window must exist');

        this._container = document.getElementById('site-navigation');
        if (!this._container) {
            return;
        }

        this._button = this._container.getElementsByTagName('button')[0];
        if (typeof this._button === 'undefined') {
            return;
        }

        this._menu = this._container.getElementsByTagName('ul')[0];

        if (typeof this._menu === 'undefined') {
            this._button.style.display = 'none';
            return;
        }

        this._menu.classList.add('nav-menu');

        this._bindMenuToggle();
        this._bindKeyboardFocus();
        this._bindTouchSubmenus();
        this._bindOverflowFlip();
    }

    /**
     * Toggle mobile menu open/closed on button click.
     * Uses event delegation so replaced DOM elements still work.
     */
    _bindMenuToggle() {
        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof document.addEventListener === 'function', 'addEventListener must exist');

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('.menu-toggle');
            if (!btn) { return; }
            const container = btn.closest('#site-navigation');
            if (!container) { return; }
            const isOpen = container.classList.toggle('toggled');
            btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    }

    /**
     * Attach focus/blur listeners via delegation on #site-navigation.
     * Walks up the DOM toggling .focus on ancestor li elements.
     */
    _bindKeyboardFocus() {
        assert(this._container instanceof HTMLElement, 'container must be an HTMLElement');
        assert(typeof this._container.addEventListener === 'function', 'addEventListener must exist');

        this._container.addEventListener('focusin', this._handleFocusToggle, true);
        this._container.addEventListener('focusout', this._handleFocusToggle, true);
    }

    /**
     * Delegated focus/blur handler.
     * Toggles .focus class on ancestor li elements of the focused link.
     */
    _handleFocusToggle(e) {
        let el = e.target;
        const MAX_DEPTH = 20;
        let depth = 0;

        while (el && !el.classList.contains('nav-menu') && depth < MAX_DEPTH) {
            if (el.tagName && el.tagName.toLowerCase() === 'li') {
                el.classList.toggle('focus');
            }
            el = el.parentElement;
            depth++;
        }
    }

    /**
     * Touch-based submenu toggle for tablets.
     * Uses event delegation so replaced DOM elements still work.
     */
    _bindTouchSubmenus() {
        if (!('ontouchstart' in window)) {
            return;
        }

        assert(typeof document !== 'undefined', 'document must exist');
        assert(typeof document.addEventListener === 'function', 'addEventListener must exist');

        document.addEventListener('touchstart', (e) => {
            const link = e.target.closest('.menu-item-has-children > a, .page_item_has_children > a');
            if (!link) { return; }

            const menuItem = link.parentNode;
            if (!menuItem) { return; }

            if (!menuItem.classList.contains('focus')) {
                e.preventDefault();
                const siblings = menuItem.parentNode.children;
                const sibLen = siblings.length < MAX_MENU_ITEMS ? siblings.length : MAX_MENU_ITEMS;

                for (let i = 0; i < sibLen; i++) {
                    if (menuItem !== siblings[i]) {
                        siblings[i].classList.remove('focus');
                    }
                }
                menuItem.classList.add('focus');
            } else {
                menuItem.classList.remove('focus');
            }
        }, false);
    }

    /**
     * Flip submenus that would overflow the viewport.
     *
     * - First-level dropdowns: adds .sub-menu--right to the parent li
     *   so the panel aligns to the right edge instead of left.
     * - Nested submenus: adds .sub-menu--left so the panel opens leftward.
     *
     * Pre-checks on load, re-checks on resize, and on mouseenter.
     */
    _bindOverflowFlip() {
        this._topLevelItems = this._container.querySelectorAll(
            '.menu > .menu-item-has-children'
        );
        this._nestedItems = this._container.querySelectorAll(
            '.sub-menu .menu-item-has-children'
        );

        assert(this._topLevelItems !== null, 'top-level items must be a NodeList');
        assert(this._nestedItems !== null, 'nested items must be a NodeList');
        assert(this._topLevelItems.length <= MAX_MENU_ITEMS, 'top-level count within bounds');
        assert(this._nestedItems.length <= MAX_MENU_ITEMS, 'nested count within bounds');

        const nestedLen = this._nestedItems.length < MAX_MENU_ITEMS
            ? this._nestedItems.length
            : MAX_MENU_ITEMS;

        for (let i = 0; i < nestedLen; i++) {
            this._nestedItems[i].addEventListener('mouseenter', this._checkNestedOverflow, false);
        }

        this._resizeTimer = 0;
        this._preCheckOverflow();
        window.addEventListener('resize', () => {
            clearTimeout(this._resizeTimer);
            this._resizeTimer = setTimeout(() => this._preCheckOverflow(), 100);
        });
    }

    /**
     * Pre-check all submenus for overflow on load/resize.
     *
     * Temporarily shows hidden sub-menus (visibility: hidden)
     * so getBoundingClientRect returns real positions, then restores them.
     */
    _preCheckOverflow() {
        assert(this._container instanceof HTMLElement, 'container must be an HTMLElement');

        this._preCheckTopLevel();
        this._preCheckNested();
    }

    /**
     * Pre-check first-level dropdowns for right-edge overflow.
     * Batches DOM writes and reads to avoid forced reflow.
     */
    _preCheckTopLevel() {
        assert(this._topLevelItems !== null, 'top-level items must exist');

        const items = this._topLevelItems;
        const len = items.length < MAX_MENU_ITEMS ? items.length : MAX_MENU_ITEMS;
        const viewportWidth = document.documentElement.clientWidth;

        const entries = [];
        for (let i = 0; i < len; i++) {
            const li = items[i];
            const subMenu = li.querySelector(':scope > .sub-menu');
            if (!subMenu) { continue; }
            entries.push({ li: li, subMenu: subMenu });
        }

        if (entries.length === 0) { return; }

        const entryLen = entries.length < MAX_MENU_ITEMS ? entries.length : MAX_MENU_ITEMS;

        /* WRITE: show all hidden sub-menus, reset flip classes. */
        const saved = [];
        for (let i = 0; i < entryLen; i++) {
            const sub = entries[i].subMenu;
            saved.push({
                display: sub.style.display,
                visibility: sub.style.visibility,
                pointerEvents: sub.style.pointerEvents,
            });
            sub.style.display = 'block';
            sub.style.visibility = 'hidden';
            sub.style.pointerEvents = 'none';
            entries[i].li.classList.remove('sub-menu--right');
        }

        /* READ: measure all positions in one pass. */
        const rects = [];
        for (let i = 0; i < entryLen; i++) {
            rects.push(entries[i].subMenu.getBoundingClientRect());
        }

        /* WRITE: apply flip classes and restore styles. */
        for (let i = 0; i < entryLen; i++) {
            if (rects[i].right > viewportWidth) {
                entries[i].li.classList.add('sub-menu--right');
            }
            entries[i].subMenu.style.display = saved[i].display;
            entries[i].subMenu.style.visibility = saved[i].visibility;
            entries[i].subMenu.style.pointerEvents = saved[i].pointerEvents;
        }
    }

    /**
     * Pre-check nested submenus for overflow.
     * Batches DOM reads and writes to avoid forced reflow.
     */
    _preCheckNested() {
        assert(this._nestedItems !== null, 'nested items must exist');

        const items = this._nestedItems;
        const len = items.length < MAX_MENU_ITEMS ? items.length : MAX_MENU_ITEMS;
        const MAX_DEPTH = 10;
        const viewportWidth = document.documentElement.clientWidth;

        const entries = [];
        for (let i = 0; i < len; i++) {
            const item = items[i];
            const subMenu = item.querySelector(':scope > .sub-menu');
            if (!subMenu) { continue; }
            if (!item.parentElement || !item.parentElement.classList.contains('sub-menu')) { continue; }
            entries.push({ item: item, subMenu: subMenu });
        }

        if (entries.length === 0) { return; }

        const entryLen = entries.length < MAX_MENU_ITEMS ? entries.length : MAX_MENU_ITEMS;

        /* READ: find all hidden ancestors via getComputedStyle. */
        const hiddenMap = new Map();
        for (let i = 0; i < entryLen; i++) {
            let el = entries[i].subMenu;
            let depth = 0;
            while (el && el !== this._container && depth < MAX_DEPTH) {
                if (el.classList && el.classList.contains('sub-menu') && !hiddenMap.has(el)) {
                    const style = window.getComputedStyle(el);
                    if (style.display === 'none') {
                        hiddenMap.set(el, {
                            display: el.style.display,
                            visibility: el.style.visibility,
                            pointerEvents: el.style.pointerEvents,
                        });
                    }
                }
                el = el.parentElement;
                depth++;
            }
        }

        /* WRITE: reset flip classes, show all hidden ancestors. */
        for (let i = 0; i < entryLen; i++) {
            entries[i].item.classList.remove('sub-menu--left');
        }
        hiddenMap.forEach(function (saved, el) {
            el.style.display = 'block';
            el.style.visibility = 'hidden';
            el.style.pointerEvents = 'none';
        });

        /* READ: measure all sub-menus in one pass. */
        const rects = [];
        for (let i = 0; i < entryLen; i++) {
            rects.push(entries[i].subMenu.getBoundingClientRect());
        }

        /* WRITE: apply flip classes, restore hidden ancestors. */
        for (let i = 0; i < entryLen; i++) {
            if (rects[i].right > viewportWidth) {
                entries[i].item.classList.add('sub-menu--left');
            }
        }
        hiddenMap.forEach(function (saved, el) {
            el.style.display = saved.display;
            el.style.visibility = saved.visibility;
            el.style.pointerEvents = saved.pointerEvents;
        });
    }

    /**
     * Check if a nested item's sub-menu overflows the viewport on hover.
     *
     * Uses function (not arrow) because `this` must be the event target element.
     */
    _checkNestedOverflow() {
        const subMenu = this.querySelector(':scope > .sub-menu');

        if (!subMenu) {
            return;
        }

        /* Only flip nested sub-menus, not top-level dropdowns. */
        if (!this.parentElement || !this.parentElement.classList.contains('sub-menu')) {
            return;
        }

        /* Reset before measuring. */
        this.classList.remove('sub-menu--left');

        const rect = subMenu.getBoundingClientRect();
        const viewportWidth = document.documentElement.clientWidth;

        if (rect.right > viewportWidth) {
            this.classList.add('sub-menu--left');
        } else if (rect.left < 0) {
            this.classList.remove('sub-menu--left');
        }
    }
}
