/**
 * Admin SEO Redirects sub-tab.
 *
 * Manages redirect rules: add, delete, load, export, import.
 *
 * @package BS_Custom
 */

/** Maximum redirects displayed. */
const MAX_REDIRECTS = 500;

export default class Admin_Seo_Redirects {

    /**
     * @param {string} nonce   WP AJAX nonce.
     * @param {string} ajaxUrl Admin AJAX URL.
     */
    constructor(nonce, ajaxUrl) {
        this.nonce   = nonce;
        this.ajaxUrl = ajaxUrl;

        this.bindEvents();
        this.load();
    }

    bindEvents() {
        const addBtn = document.getElementById('bs_seo_redirect_add');
        if (addBtn) {
            addBtn.addEventListener('click', () => this.handleAdd());
        }

        const exportBtn = document.getElementById('bs_seo_redirects_export');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportRedirects());
        }

        const importBtn = document.getElementById('bs_seo_redirects_import');
        if (importBtn) {
            importBtn.addEventListener('click', () => {
                document.getElementById('bs_seo_redirects_import_file')?.click();
            });
        }

        const importFile = document.getElementById('bs_seo_redirects_import_file');
        if (importFile) {
            importFile.addEventListener('change', (e) => this.importRedirects(e));
        }
    }

    async load() {
        const formData = new FormData();
        formData.append('action', 'admin_load_seo_redirects');
        formData.append('nonce', this.nonce);

        const result = await this.ajaxPostForm(formData);

        if (result.success && result.data.redirects) {
            this.renderTable(result.data.redirects);
        } else {
            this.renderTable([]);
        }
    }

    renderTable(redirects) {
        const tbody = document.querySelector('#bs_seo_redirects_table tbody');
        if (!tbody) { return; }

        tbody.innerHTML = '';

        if (!redirects || redirects.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 5;
            td.textContent = 'No redirects configured.';
            td.className = 'ct-seo-redirects__empty';
            tr.appendChild(td);
            tbody.appendChild(tr);
            return;
        }

        let count = 0;

        for (const redirect of redirects) {
            if (count >= MAX_REDIRECTS) { break; }
            count++;

            const tr = document.createElement('tr');

            /* From */
            const tdFrom = document.createElement('td');
            tdFrom.textContent = redirect.from || '';
            tdFrom.className = 'ct-seo-redirects__cell-url';
            tr.appendChild(tdFrom);

            /* To */
            const tdTo = document.createElement('td');
            tdTo.textContent = redirect.to || '';
            tdTo.className = 'ct-seo-redirects__cell-url';
            tr.appendChild(tdTo);

            /* Type */
            const tdType = document.createElement('td');
            const typeBadge = document.createElement('span');
            typeBadge.className = 'ct-seo-redirects__type-badge';
            typeBadge.textContent = String(redirect.type || 301);
            tdType.appendChild(typeBadge);
            tr.appendChild(tdType);

            /* Hits */
            const tdHits = document.createElement('td');
            tdHits.textContent = String(redirect.hits || 0);
            tr.appendChild(tdHits);

            /* Actions */
            const tdActions = document.createElement('td');
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'button button-small ct-seo-redirects__delete-btn';
            deleteBtn.textContent = 'Delete';
            deleteBtn.addEventListener('click', () => this.deleteRedirect(redirect.from));
            tdActions.appendChild(deleteBtn);
            tr.appendChild(tdActions);

            tbody.appendChild(tr);
        }
    }

    async handleAdd() {
        const fromInput = document.getElementById('bs_seo_redirect_from');
        const toInput   = document.getElementById('bs_seo_redirect_to');
        const typeSelect = document.getElementById('bs_seo_redirect_type');

        const from = fromInput ? fromInput.value.trim() : '';
        const to   = toInput ? toInput.value.trim() : '';
        const type = typeSelect ? typeSelect.value : '301';

        if (!from || !to) {
            window.ctToast.show('Both From and To fields are required.', 'error');
            return;
        }

        await this.addRedirect(from, to, type);

        if (fromInput) { fromInput.value = ''; }
        if (toInput) { toInput.value = ''; }
    }

    async addRedirect(from, to, type) {
        const formData = new FormData();
        formData.append('action', 'admin_save_seo_redirect');
        formData.append('nonce', this.nonce);
        formData.append('input', JSON.stringify({ action_type: 'add', from, to, type }));

        const result = await this.ajaxPostForm(formData);

        if (result.success) {
            window.ctToast.show(result.data.message || 'Redirect added.', 'success');
            this.load();
        } else {
            window.ctToast.show(result.data?.message || 'Failed to add redirect.', 'error');
        }
    }

    async deleteRedirect(from) {
        const formData = new FormData();
        formData.append('action', 'admin_save_seo_redirect');
        formData.append('nonce', this.nonce);
        formData.append('input', JSON.stringify({ action_type: 'delete', from }));

        const result = await this.ajaxPostForm(formData);

        if (result.success) {
            window.ctToast.show(result.data.message || 'Redirect removed.', 'success');
            this.load();
        } else {
            window.ctToast.show(result.data?.message || 'Failed to remove redirect.', 'error');
        }
    }

    async exportRedirects() {
        const formData = new FormData();
        formData.append('action', 'admin_load_seo_redirects');
        formData.append('nonce', this.nonce);

        const result = await this.ajaxPostForm(formData);

        if (result.success && result.data.redirects) {
            const blob = new Blob([JSON.stringify(result.data.redirects, null, 2)], { type: 'application/json' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = 'seo-redirects-export.json';
            a.click();
            URL.revokeObjectURL(url);
        }
    }

    async importRedirects(e) {
        const file = e.target.files[0];
        if (!file) { return; }

        const text = await file.text();

        const formData = new FormData();
        formData.append('action', 'admin_save_seo_redirect');
        formData.append('nonce', this.nonce);
        formData.append('input', JSON.stringify({ action_type: 'import', data: text }));

        const result = await this.ajaxPostForm(formData);

        if (result.success) {
            window.ctToast.show(result.data.message || 'Redirects imported.', 'success');
            this.load();
        } else {
            window.ctToast.show(result.data?.message || 'Import failed.', 'error');
        }

        e.target.value = '';
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
