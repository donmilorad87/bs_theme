/**
 * Language Switcher.
 *
 * Handles toggle, outside-click close, keyboard navigation
 * (Enter/Space open, Escape close, Arrow keys navigate).
 *
 * @package CT_Custom
 */
export default class LanguageSwitcher {

    constructor() {
        this.switcher = document.querySelector('.ct-lang-switcher');
        if (!this.switcher) { return; }

        this.toggle   = this.switcher.querySelector('.ct-lang-switcher__toggle');
        this.dropdown = this.switcher.querySelector('.ct-lang-switcher__dropdown');
        this.items    = this.switcher.querySelectorAll('.ct-lang-switcher__link');
        this.isOpen   = false;

        this.bindEvents();
    }

    bindEvents() {
        if (!this.toggle) { return; }

        this.toggle.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggleDropdown();
        });

        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.switcher.contains(e.target)) {
                this.close();
            }
        });

        this.toggle.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.toggleDropdown();
            } else if (e.key === 'Escape') {
                this.close();
            }
        });

        if (this.dropdown) {
            this.dropdown.addEventListener('keydown', (e) => {
                this.handleDropdownKeydown(e);
            });
        }
    }

    toggleDropdown() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.isOpen = true;
        this.switcher.classList.add('ct-lang-switcher--open');
        if (this.toggle) { this.toggle.setAttribute('aria-expanded', 'true'); }

        if (this.items.length > 0) {
            this.items[0].focus();
        }
    }

    close() {
        this.isOpen = false;
        this.switcher.classList.remove('ct-lang-switcher--open');
        if (this.toggle) { this.toggle.setAttribute('aria-expanded', 'false'); }
    }

    handleDropdownKeydown(e) {
        const focusable = Array.from(this.items);
        const current   = document.activeElement;
        const idx       = focusable.indexOf(current);

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = (idx + 1) % focusable.length;
            focusable[next].focus();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const prev = (idx - 1 + focusable.length) % focusable.length;
            focusable[prev].focus();
        } else if (e.key === 'Escape') {
            this.close();
            if (this.toggle) { this.toggle.focus(); }
        }
    }
}
