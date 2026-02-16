/**
 * Profile App — Entry point for the profile page.
 *
 * Standalone Vite bundle loaded only on the profile.php template.
 * Duplicated auth modules from frontend/vite/src/js/auth/.
 *
 * @package BS_Custom
 */

import ProfilePage from './profile-page.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new ProfilePage();
    });
} else {
    new ProfilePage();
}
