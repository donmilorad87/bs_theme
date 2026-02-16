import '../scss/app.scss';
import Admin_CT_Custom from './classes/Admin_CT_Custom.js';
import Admin_Languages from './classes/Admin_Languages.js';
import Toast from './classes/Toast.js';

class App {
    constructor() {
        window.ctToast = new Toast();
        this.admin     = new Admin_CT_Custom();
        this.languages = new Admin_Languages();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new App();
});
