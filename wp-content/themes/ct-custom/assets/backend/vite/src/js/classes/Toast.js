/**
 * Toast notification system.
 *
 * Displays stacking toast messages (success, warning, error) with
 * auto-dismiss, progress bar, and manual close.
 *
 * @package BS_Custom
 */
export default class Toast {

    static MAX_TOASTS = 5;
    static DURATION_MS = 4000;

    static ICONS = {
        success: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="10" fill="currentColor" opacity="0.15"/>
            <path d="M6 10.5L8.5 13L14 7.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>`,
        warning: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M10 2L1 18H19L10 2Z" fill="currentColor" opacity="0.15" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M10 8V12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <circle cx="10" cy="15" r="1" fill="currentColor"/>
        </svg>`,
        error: `<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="10" cy="10" r="10" fill="currentColor" opacity="0.15"/>
            <path d="M7 7L13 13M13 7L7 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>`,
    };

    constructor() {
        this.container = null;
        this.toasts = [];
        this.ensureContainer();
    }

    ensureContainer() {
        if (this.container) { return; }

        this.container = document.createElement('div');
        this.container.className = 'ct-toast-container';
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('role', 'status');
        document.body.appendChild(this.container);
    }

    /**
     * Show a toast notification.
     *
     * @param {string} message - Text to display.
     * @param {string} type    - 'success' | 'warning' | 'error'.
     */
    show(message, type) {
        assert(typeof message === 'string', 'Toast message must be a string');
        assert(['success', 'warning', 'error'].includes(type), 'Toast type must be success, warning, or error');

        this.ensureContainer();

        /* Enforce bounded queue */
        if (this.toasts.length >= Toast.MAX_TOASTS) {
            this.dismiss(this.toasts[0]);
        }

        const toast = document.createElement('div');
        toast.className = `ct-toast ct-toast--${type}`;

        const icon = document.createElement('span');
        icon.className = 'ct-toast__icon';
        icon.innerHTML = Toast.ICONS[type] || Toast.ICONS.error;

        const text = document.createElement('span');
        text.className = 'ct-toast__message';
        text.textContent = message;

        const closeBtn = document.createElement('button');
        closeBtn.className = 'ct-toast__close';
        closeBtn.type = 'button';
        closeBtn.setAttribute('aria-label', 'Close notification');
        closeBtn.innerHTML = `<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 3L11 11M11 3L3 11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>`;
        closeBtn.addEventListener('click', () => this.dismiss(toast));

        const progress = document.createElement('div');
        progress.className = 'ct-toast__progress';

        const progressBar = document.createElement('div');
        progressBar.className = 'ct-toast__progress-bar';
        progress.appendChild(progressBar);

        toast.appendChild(icon);
        toast.appendChild(text);
        toast.appendChild(closeBtn);
        toast.appendChild(progress);

        this.container.appendChild(toast);
        this.toasts.push(toast);

        /* Trigger slide-in */
        requestAnimationFrame(() => {
            toast.classList.add('ct-toast--visible');
            progressBar.style.transition = `width ${Toast.DURATION_MS}ms linear`;
            progressBar.style.width = '0%';
        });

        /* Auto-dismiss */
        toast._dismissTimer = setTimeout(() => {
            this.dismiss(toast);
        }, Toast.DURATION_MS);
    }

    dismiss(toast) {
        if (!toast || !toast.parentNode) { return; }

        if (toast._dismissTimer) {
            clearTimeout(toast._dismissTimer);
            toast._dismissTimer = null;
        }

        toast.classList.remove('ct-toast--visible');
        toast.classList.add('ct-toast--exiting');

        const idx = this.toasts.indexOf(toast);
        if (idx !== -1) {
            this.toasts.splice(idx, 1);
        }

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }
}

/**
 * Simple assert for development.
 *
 * @param {boolean} condition
 * @param {string}  message
 */
function assert(condition, message) {
    if (!condition) {
        console.warn('[Toast assert]', message);
    }
}
