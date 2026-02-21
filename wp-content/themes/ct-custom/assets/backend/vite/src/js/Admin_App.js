import '../scss/app.scss';
import Admin_CT_Custom from './classes/Admin_CT_Custom.js';
import Admin_Languages from './classes/Admin_Languages.js';
import Admin_Seo from './classes/Admin_Seo.js';
import Toast from './classes/Toast.js';

class App {
    constructor() {
        window.ctToast = new Toast();
        this.admin     = new Admin_CT_Custom();
        this.languages = new Admin_Languages();
        this.seo       = new Admin_Seo();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new App();
});
