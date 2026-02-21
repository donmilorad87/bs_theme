import ThemeSwitcher from './theme-switcher.js';
import Navigation from './navigation.js';
import BackToTop from './back-to-top.js';
import AuthHeader from './auth-header.js';
import ContactForm from './contact-form.js';
import LanguageSwitcher from './language-switcher.js';
import './translator.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        const themeToggleEnabled = document.body && document.body.getAttribute('data-theme-toggle') !== 'off';
        if (themeToggleEnabled) {
            new ThemeSwitcher();
        }
        new Navigation();
        new BackToTop();
        new AuthHeader();
        new ContactForm();
        new LanguageSwitcher();
    });
} else {
    const themeToggleEnabled = document.body && document.body.getAttribute('data-theme-toggle') !== 'off';
    if (themeToggleEnabled) {
        new ThemeSwitcher();
    }
    new Navigation();
    new BackToTop();
    new AuthHeader();
    new ContactForm();
    new LanguageSwitcher();
}
