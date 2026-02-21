/**
 * Admin SEO LLMs.txt sub-tab.
 *
 * Binds the LLMs.txt enable/disable toggle, preview load, and save.
 *
 * @package BS_Custom
 */
export default class Admin_Seo_Llms {

    /**
     * @param {string} nonce   WP AJAX nonce.
     * @param {string} ajaxUrl Admin AJAX URL.
     */
    constructor(nonce, ajaxUrl) {
        this.nonce   = nonce;
        this.ajaxUrl = ajaxUrl;

        this.bindEvents();
        this.loadSettings();
    }

    bindEvents() {
        const saveBtn = document.getElementById('bs_seo_llms_save');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.save());
        }

        /* Re-generate preview when toggle changes */
        const toggle = document.getElementById('bs_seo_llms_enabled');
        if (toggle) {
            toggle.addEventListener('change', () => this.loadPreview());
        }
    }

    async loadSettings() {
        const formData = new FormData();
        formData.append('action', 'admin_load_seo_global');
        formData.append('nonce', this.nonce);

        const result = await this.ajaxPostForm(formData);

        if (!result.success || !result.data) {
            return;
        }

        const data = result.data;

        const toggle = document.getElementById('bs_seo_llms_enabled');
        if (toggle && data.llms_enabled !== undefined) {
            toggle.checked = !!data.llms_enabled;
        }

        const customArea = document.getElementById('bs_seo_llms_custom');
        if (customArea && data.llms_custom) {
            customArea.value = data.llms_custom;
        }

        /* Load preview text */
        if (data.llms_preview) {
            const previewEl = document.getElementById('bs_seo_llms_preview');
            if (previewEl) {
                previewEl.textContent = data.llms_preview;
            }
        } else {
            this.loadPreview();
        }
    }

    async loadPreview() {
        const formData = new FormData();
        formData.append('action', 'admin_load_seo_global');
        formData.append('nonce', this.nonce);

        const result = await this.ajaxPostForm(formData);

        if (result.success && result.data && result.data.llms_preview) {
            const previewEl = document.getElementById('bs_seo_llms_preview');
            if (previewEl) {
                previewEl.textContent = result.data.llms_preview;
            }
        }
    }

    async save() {
        const data = {
            llms_enabled: !!document.getElementById('bs_seo_llms_enabled')?.checked,
            llms_custom:  document.getElementById('bs_seo_llms_custom')?.value || '',
        };

        const formData = new FormData();
        formData.append('action', 'admin_save_seo_llms');
        formData.append('nonce', this.nonce);
        formData.append('input', JSON.stringify(data));

        const result = await this.ajaxPostForm(formData);

        if (result.success) {
            window.ctToast.show(result.data.message || 'LLMs.txt settings saved.', 'success');
            this.loadPreview();
        } else {
            window.ctToast.show(result.data?.message || 'Error saving LLMs.txt settings.', 'error');
        }
    }

    /* ═══ AJAX Helper ═══ */

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
