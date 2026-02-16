import ThemeSwitcher from './theme-switcher.js';
import Navigation from './navigation.js';
import BackToTop from './back-to-top.js';
import AuthHeader from './auth-header.js';
import ContactForm from './contact-form.js';
import LanguageSwitcher from './language-switcher.js';
import './translator.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new ThemeSwitcher();
        new Navigation();
        new BackToTop();
        new AuthHeader();
        new ContactForm();
        new LanguageSwitcher();
    });
} else {
    new ThemeSwitcher();
    new Navigation();
    new BackToTop();
    new AuthHeader();
    new ContactForm();
    new LanguageSwitcher();
}
