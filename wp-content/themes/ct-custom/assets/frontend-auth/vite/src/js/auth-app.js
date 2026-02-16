/**
 * Auth App — Entry point for the login/register page.
 *
 * Standalone Vite bundle loaded only on the login-register.php template.
 * Duplicated auth modules from frontend/vite/src/js/auth/.
 *
 * @package CT_Custom
 */

import AuthPage from './auth-page.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new AuthPage();
    });
} else {
    new AuthPage();
}
