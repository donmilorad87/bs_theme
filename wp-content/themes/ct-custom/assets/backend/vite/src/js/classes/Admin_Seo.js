/**
 * Admin SEO settings management.
 *
 * Handles Global, Social, Sitemap, LLMs.txt, and Breadcrumbs sub-tabs
 * within the admin SEO panel.
 *
 * @package BS_Custom
 */
import Admin_Seo_Dashboard from './Admin_Seo_Dashboard.js';
import Admin_Seo_Redirects from './Admin_Seo_Redirects.js';
import Admin_Seo_Sitemap from './Admin_Seo_Sitemap.js';
import Admin_Seo_Llms from './Admin_Seo_Llms.js';

/** Maximum iterations for bounded loops. */
const MAX_FIELDS = 50;

export default class Admin_Seo {

    constructor() {
        this.section = document.querySelector('.ct-seo-section');

        if (!this.section) {
            return;
        }

        this.nonce   = this.section.dataset.nonce || '';
        this.ajaxUrl = this.section.dataset.ajaxUrl || '';

        /* Sub-controllers */
        this.dashboard   = new Admin_Seo_Dashboard(this.nonce, this.ajaxUrl);
        this.redirects   = new Admin_Seo_Redirects(this.nonce, this.ajaxUrl);
        this.sitemapCtrl = new Admin_Seo_Sitemap(this.nonce, this.ajaxUrl);
        this.llmsCtrl    = new Admin_Seo_Llms(this.nonce, this.ajaxUrl);

        this.initGlobal();
        this.initSocial();
        this.initBreadcrumbs();
    }

    /* ═══ A) Global Settings ═══ */

    initGlobal() {
        const saveBtn = document.getElementById('bs_seo_global_save');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveGlobal());
        }

        /* Placeholder buttons insert text into the title template input */
        const placeholderBtns = this.section.querySelectorAll('.ct-seo-placeholder-btn');
        const maxBtns = 10;
        let btnCount = 0;

        for (const btn of placeholderBtns) {
            if (btnCount >= maxBtns) { break; }
            btnCount++;

            btn.addEventListener('click', () => {
                const input = document.getElementById('bs_seo_title_template');
                if (!input) { return; }

                const placeholder = btn.dataset.placeholder || '';
                const pos = input.selectionStart || input.value.length;
                const before = input.value.substring(0, pos);
                const after = input.value.substring(pos);

                input.value = before + placeholder + after;
                input.focus();
                input.setSelectionRange(pos + placeholder.length, pos + placeholder.length);
            });
        }

        /* Logo media picker */
        const logoBtn = document.getElementById('bs_seo_kg_logo_btn');
        if (logoBtn) {
            logoBtn.addEventListener('click', () => this.openMediaPicker('bs_seo_kg_logo', 'bs_seo_kg_logo_preview', 'bs_seo_kg_logo_remove'));
        }

        const logoRemove = document.getElementById('bs_seo_kg_logo_remove');
        if (logoRemove) {
            logoRemove.addEventListener('click', () => this.clearMediaPicker('bs_seo_kg_logo', 'bs_seo_kg_logo_preview', 'bs_seo_kg_logo_remove'));
        }

        /* Load current settings */
        this.loadGlobal();
    }

    async loadGlobal() {
        const result = await this.ajaxPostSimple('admin_load_seo_global');

        if (!result.success || !result.data) {
            return;
        }

        const data = result.data;

        this.setInputValue('bs_seo_title_template', data.title_template);
        this.setInputValue('bs_seo_separator', data.separator);
        this.setInputValue('bs_seo_default_description', data.default_description);
        this.setInputValue('bs_seo_default_keywords', data.default_keywords);
        this.setInputValue('bs_seo_kg_name', data.kg_name);
        this.setInputValue('bs_seo_kg_url', data.kg_url);

        const kgTypeSelect = document.getElementById('bs_seo_kg_type');
        if (kgTypeSelect && data.kg_type) {
            kgTypeSelect.value = data.kg_type;
        }

        if (data.kg_logo) {
            document.getElementById('bs_seo_kg_logo').value = data.kg_logo;
            const preview = document.getElementById('bs_seo_kg_logo_preview');
            const removeBtn = document.getElementById('bs_seo_kg_logo_remove');
            if (preview) { preview.src = data.kg_logo; preview.style.display = 'block'; }
            if (removeBtn) { removeBtn.style.display = 'inline-block'; }
        }
    }

    async saveGlobal() {
        const data = {
            title_template:      this.getInputValue('bs_seo_title_template'),
            separator:           this.getInputValue('bs_seo_separator'),
            default_description: this.getInputValue('bs_seo_default_description'),
            default_keywords:    this.getInputValue('bs_seo_default_keywords'),
            kg_type:             this.getInputValue('bs_seo_kg_type'),
            kg_name:             this.getInputValue('bs_seo_kg_name'),
            kg_logo:             this.getInputValue('bs_seo_kg_logo'),
            kg_url:              this.getInputValue('bs_seo_kg_url'),
        };

        const result = await this.ajaxPost('admin_save_seo_global', JSON.stringify(data));

        if (result.success) {
            window.ctToast.show(result.data.message || 'Global SEO settings saved.', 'success');
        } else {
            window.ctToast.show(result.data?.message || 'Error saving settings.', 'error');
        }
    }

    /* ═══ B) Social Defaults ═══ */

    initSocial() {
        const saveBtn = document.getElementById('bs_seo_social_save');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveSocial());
        }

        /* OG image media picker */
        const ogBtn = document.getElementById('bs_seo_og_image_btn');
        if (ogBtn) {
            ogBtn.addEventListener('click', () => this.openMediaPicker('bs_seo_og_image', 'bs_seo_og_image_preview', 'bs_seo_og_image_remove'));
        }

        const ogRemove = document.getElementById('bs_seo_og_image_remove');
        if (ogRemove) {
            ogRemove.addEventListener('click', () => this.clearMediaPicker('bs_seo_og_image', 'bs_seo_og_image_preview', 'bs_seo_og_image_remove'));
        }

        this.loadSocial();
    }

    async loadSocial() {
        const result = await this.ajaxPostSimple('admin_load_seo_global');

        if (!result.success || !result.data) {
            return;
        }

        const data = result.data;

        this.setInputValue('bs_seo_og_sitename', data.og_sitename);
        this.setInputValue('bs_seo_twitter_username', data.twitter_username);
        this.setInputValue('bs_seo_pinterest_verify', data.pinterest_verify);
        this.setInputValue('bs_seo_social_profiles', data.social_profiles);

        const cardType = document.getElementById('bs_seo_twitter_card_type');
        if (cardType && data.twitter_card_type) {
            cardType.value = data.twitter_card_type;
        }

        if (data.og_image) {
            document.getElementById('bs_seo_og_image').value = data.og_image;
            const preview = document.getElementById('bs_seo_og_image_preview');
            const removeBtn = document.getElementById('bs_seo_og_image_remove');
            if (preview) { preview.src = data.og_image; preview.style.display = 'block'; }
            if (removeBtn) { removeBtn.style.display = 'inline-block'; }
        }
    }

    async saveSocial() {
        const data = {
            og_image:            this.getInputValue('bs_seo_og_image'),
            og_sitename:         this.getInputValue('bs_seo_og_sitename'),
            twitter_username:    this.getInputValue('bs_seo_twitter_username'),
            twitter_card_type:   this.getInputValue('bs_seo_twitter_card_type'),
            pinterest_verify:    this.getInputValue('bs_seo_pinterest_verify'),
            social_profiles:     this.getInputValue('bs_seo_social_profiles'),
        };

        const result = await this.ajaxPost('admin_save_seo_social', JSON.stringify(data));

        if (result.success) {
            window.ctToast.show(result.data.message || 'Social settings saved.', 'success');
        } else {
            window.ctToast.show(result.data?.message || 'Error saving social settings.', 'error');
        }
    }

    /* ═══ F) Breadcrumbs ═══ */

    initBreadcrumbs() {
        const saveBtn = document.getElementById('bs_seo_breadcrumbs_save');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveBreadcrumbs());
        }

        this.loadBreadcrumbs();
    }

    async loadBreadcrumbs() {
        const result = await this.ajaxPostSimple('admin_load_seo_global');

        if (!result.success || !result.data) {
            return;
        }

        const data = result.data;

        const enabledToggle = document.getElementById('bs_seo_breadcrumbs_enabled');
        if (enabledToggle) { enabledToggle.checked = !!data.breadcrumbs_enabled; }

        this.setInputValue('bs_seo_breadcrumbs_separator', data.breadcrumbs_separator);
        this.setInputValue('bs_seo_breadcrumbs_home_label', data.breadcrumbs_home_label);

        const pagesToggle = document.getElementById('bs_seo_breadcrumbs_pages');
        if (pagesToggle && data.breadcrumbs_pages !== undefined) {
            pagesToggle.checked = !!data.breadcrumbs_pages;
        }

        const postsToggle = document.getElementById('bs_seo_breadcrumbs_posts');
        if (postsToggle && data.breadcrumbs_posts !== undefined) {
            postsToggle.checked = !!data.breadcrumbs_posts;
        }
    }

    async saveBreadcrumbs() {
        const data = {
            breadcrumbs_enabled:   !!document.getElementById('bs_seo_breadcrumbs_enabled')?.checked,
            breadcrumbs_separator: this.getInputValue('bs_seo_breadcrumbs_separator'),
            breadcrumbs_home_label: this.getInputValue('bs_seo_breadcrumbs_home_label'),
            breadcrumbs_pages:     !!document.getElementById('bs_seo_breadcrumbs_pages')?.checked,
            breadcrumbs_posts:     !!document.getElementById('bs_seo_breadcrumbs_posts')?.checked,
        };

        const result = await this.ajaxPost('admin_save_seo_breadcrumbs', JSON.stringify(data));

        if (result.success) {
            window.ctToast.show(result.data.message || 'Breadcrumb settings saved.', 'success');
        } else {
            window.ctToast.show(result.data?.message || 'Error saving breadcrumb settings.', 'error');
        }
    }

    /* ═══ Media Picker Helpers ═══ */

    openMediaPicker(hiddenId, previewId, removeBtnId) {
        if (typeof wp === 'undefined' || !wp.media) { return; }

        const frame = wp.media({
            title: 'Choose Image',
            multiple: false,
            library: { type: 'image' },
        });

        frame.on('select', () => {
            const attachment = frame.state().get('selection').first().toJSON();
            const url = attachment.url || '';

            const hidden = document.getElementById(hiddenId);
            if (hidden) { hidden.value = url; }

            const preview = document.getElementById(previewId);
            if (preview) {
                preview.src = url;
                preview.style.display = url ? 'block' : 'none';
            }

            const removeBtn = document.getElementById(removeBtnId);
            if (removeBtn) {
                removeBtn.style.display = url ? 'inline-block' : 'none';
            }
        });

        frame.open();
    }

    clearMediaPicker(hiddenId, previewId, removeBtnId) {
        const hidden = document.getElementById(hiddenId);
        if (hidden) { hidden.value = ''; }

        const preview = document.getElementById(previewId);
        if (preview) { preview.style.display = 'none'; }

        const removeBtn = document.getElementById(removeBtnId);
        if (removeBtn) { removeBtn.style.display = 'none'; }
    }

    /* ═══ Form Value Helpers ═══ */

    getInputValue(id) {
        const el = document.getElementById(id);
        if (!el) { return ''; }
        return el.value || '';
    }

    setInputValue(id, value) {
        const el = document.getElementById(id);
        if (el && value !== undefined && value !== null) {
            el.value = value;
        }
    }

    /* ═══ AJAX Helpers ═══ */

    async ajaxPost(action, inputJson) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.nonce);
        formData.append('input', inputJson);

        return this.ajaxPostForm(formData);
    }

    async ajaxPostSimple(action) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('nonce', this.nonce);

        return this.ajaxPostForm(formData);
    }

    async ajaxPostForm(formData) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData,
            });
            return await response.json();
        } catch (err) {
            return { success: false, data: { message: err.message } };
        }
    }
}
