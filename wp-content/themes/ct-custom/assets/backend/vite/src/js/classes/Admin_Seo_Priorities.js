/**
 * Admin SEO Priorities sub-panel (inside Sitemap tab).
 *
 * Paginated table for editing per-page sitemap priority and change frequency.
 * Changes are saved automatically (debounced 600 ms) — no Save button needed.
 *
 * @package BS_Custom
 */

/** Maximum rows rendered per page. */
const MAX_PER_PAGE = 20;

/** Maximum pagination buttons shown. */
const MAX_PAGES = 20;

/** Maximum columns rendered in table head. */
const MAX_COLS = 6;

export default class Admin_Seo_Priorities {

    /**
     * @param {string} nonce   WP AJAX nonce.
     * @param {string} ajaxUrl Admin AJAX URL.
     */
    constructor(nonce, ajaxUrl) {
        this.nonce        = nonce;
        this.ajaxUrl      = ajaxUrl;
        this.currentPage  = 1;
        this.currentType  = 'all';
        this.searchQuery  = '';
        this.debounceTimer = null;

        /** @type {Map<number, ReturnType<typeof setTimeout>>} */
        this._rowTimers = new Map();

        this.bindEvents();
        this.load(1);
    }

    bindEvents() {
        const typeFilter = document.getElementById('bs_seo_priorities_type_filter');
        if (typeFilter) {
            typeFilter.addEventListener('change', () => {
                this.currentType = typeFilter.value;
                this.currentPage = 1;
                this.load(1);
            });
        }

        const searchInput = document.getElementById('bs_seo_priorities_search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (this.debounceTimer) {
                    clearTimeout(this.debounceTimer);
                }
                this.debounceTimer = setTimeout(() => {
                    this.searchQuery = searchInput.value.trim();
                    this.currentPage = 1;
                    this.load(1);
                }, 400);
            });
        }
    }

    async load(page) {
        this.currentPage = page || 1;

        const tableEl = document.getElementById('bs_seo_priorities_table');
        if (tableEl) {
            tableEl.innerHTML = '<p class="ct-seo-priorities__loading">Loading\u2026</p>';
        }

        const formData = new FormData();
        formData.append('action',    'admin_get_sitemap_priorities');
        formData.append('nonce',     this.nonce);
        formData.append('page',      String(this.currentPage));
        formData.append('post_type', this.currentType);
        formData.append('search',    this.searchQuery);

        const result = await this.ajaxPostForm(formData);

        if (!result.success) {
            if (tableEl) {
                tableEl.innerHTML = '<p class="ct-seo-priorities__empty">Failed to load data.</p>';
            }
            return;
        }

        const data = result.data || {};
        this.renderTable(data.items || []);
        this.renderPagination(data.total_pages || 1);
    }

    renderTable(items) {
        const container = document.getElementById('bs_seo_priorities_table');
        if (!container) {
            return;
        }

        if (!items || items.length === 0) {
            container.innerHTML = '<p class="ct-seo-priorities__empty">No pages found.</p>';
            return;
        }

        const table   = document.createElement('table');
        table.className = 'ct-seo-priorities__data-table';

        /* Head */
        const thead   = document.createElement('thead');
        const headRow = document.createElement('tr');
        const cols    = ['Title', 'Type', 'Priority', 'Change Freq', 'Status'];
        let colCount  = 0;

        for (const col of cols) {
            if (colCount >= MAX_COLS) {
                break;
            }
            colCount++;

            const th = document.createElement('th');
            th.textContent = col;
            headRow.appendChild(th);
        }

        thead.appendChild(headRow);
        table.appendChild(thead);

        /* Body */
        const tbody  = document.createElement('tbody');
        let rowCount = 0;

        for (const item of items) {
            if (rowCount >= MAX_PER_PAGE) {
                break;
            }
            rowCount++;

            const tr = document.createElement('tr');
            tr.dataset.id = String(item.id);

            /* Title */
            const tdTitle = document.createElement('td');
            tdTitle.className = 'ct-seo-priorities__title-cell';
            const link = document.createElement('a');
            link.href        = item.url || '#';
            link.target      = '_blank';
            link.rel         = 'noopener';
            link.textContent = item.title || '(no title)';
            tdTitle.appendChild(link);
            tr.appendChild(tdTitle);

            /* Post Type */
            const tdType = document.createElement('td');
            const badge  = document.createElement('span');
            badge.className   = 'ct-seo-priorities__type-badge';
            badge.textContent = item.post_type || '';
            tdType.appendChild(badge);
            tr.appendChild(tdType);

            /* Priority */
            const tdPriority    = document.createElement('td');
            const selPriority   = this.buildPrioritySelect(item.id, item.priority);
            tdPriority.appendChild(selPriority);
            tr.appendChild(tdPriority);

            /* Change Freq */
            const tdFreq  = document.createElement('td');
            const selFreq = this.buildFreqSelect(item.id, item.changefreq);
            tdFreq.appendChild(selFreq);
            tr.appendChild(tdFreq);

            /* Status */
            const tdStatus = document.createElement('td');
            tdStatus.className = 'ct-sitemap-item-status';
            tr.appendChild(tdStatus);

            /* Wire up auto-save on change */
            selPriority.addEventListener('change', () => {
                this.scheduleRowSave(item.id, selPriority, selFreq, tdStatus);
            });
            selFreq.addEventListener('change', () => {
                this.scheduleRowSave(item.id, selPriority, selFreq, tdStatus);
            });

            tbody.appendChild(tr);
        }

        table.appendChild(tbody);
        container.innerHTML = '';
        container.appendChild(table);
    }

    /**
     * Debounce a single-row save by 600 ms.
     *
     * @param {number}           id
     * @param {HTMLSelectElement} selPriority
     * @param {HTMLSelectElement} selFreq
     * @param {HTMLElement}       statusTd
     */
    scheduleRowSave(id, selPriority, selFreq, statusTd) {
        const existing = this._rowTimers.get(id);
        if (existing !== undefined) {
            clearTimeout(existing);
        }

        statusTd.textContent = 'Saving\u2026';
        statusTd.className   = 'ct-sitemap-item-status ct-sitemap-item-status--saving';

        const timer = setTimeout(
            () => this.saveRow(id, selPriority.value, selFreq.value, statusTd),
            600
        );

        this._rowTimers.set(id, timer);
    }

    /**
     * Persist a single row via admin_save_sitemap_priorities.
     *
     * @param {number}      id
     * @param {string}      priority
     * @param {string}      changefreq
     * @param {HTMLElement} statusTd
     */
    async saveRow(id, priority, changefreq, statusTd) {
        const payload = { items: [ { id, priority, changefreq } ] };

        const formData = new FormData();
        formData.append('action', 'admin_save_sitemap_priorities');
        formData.append('nonce',  this.nonce);
        formData.append('input',  JSON.stringify(payload));

        const result = await this.ajaxPostForm(formData);

        this._rowTimers.delete(id);

        if (result.success) {
            statusTd.textContent = '\u2713 Saved';
            statusTd.className   = 'ct-sitemap-item-status ct-sitemap-item-status--saved';
            setTimeout(() => { statusTd.textContent = ''; }, 2500);
        } else {
            statusTd.textContent = '\u2717 Error';
            statusTd.className   = 'ct-sitemap-item-status ct-sitemap-item-status--error';
        }
    }

    buildPrioritySelect(id, current) {
        const select = document.createElement('select');
        select.className     = 'ct-seo-form__select ct-seo-priorities__select';
        select.dataset.id    = String(id);
        select.dataset.field = 'priority';

        const options = [
            { value: 'auto', label: 'Auto' },
            { value: '1.0',  label: '1.0 \u2014 Highest' },
            { value: '0.9',  label: '0.9' },
            { value: '0.8',  label: '0.8 \u2014 High' },
            { value: '0.7',  label: '0.7' },
            { value: '0.6',  label: '0.6' },
            { value: '0.5',  label: '0.5 \u2014 Normal' },
            { value: '0.4',  label: '0.4' },
            { value: '0.3',  label: '0.3' },
            { value: '0.2',  label: '0.2' },
            { value: '0.1',  label: '0.1 \u2014 Lowest' },
        ];

        const maxOpts = 15;
        let optCount  = 0;

        for (const opt of options) {
            if (optCount >= maxOpts) {
                break;
            }
            optCount++;

            const el = document.createElement('option');
            el.value       = opt.value;
            el.textContent = opt.label;
            if (opt.value === current) {
                el.selected = true;
            }
            select.appendChild(el);
        }

        return select;
    }

    buildFreqSelect(id, current) {
        const select = document.createElement('select');
        select.className     = 'ct-seo-form__select ct-seo-priorities__select';
        select.dataset.id    = String(id);
        select.dataset.field = 'changefreq';

        const options = [
            { value: 'auto',    label: 'Auto' },
            { value: 'always',  label: 'Always' },
            { value: 'hourly',  label: 'Hourly' },
            { value: 'daily',   label: 'Daily' },
            { value: 'weekly',  label: 'Weekly' },
            { value: 'monthly', label: 'Monthly' },
            { value: 'yearly',  label: 'Yearly' },
            { value: 'never',   label: 'Never' },
        ];

        const maxOpts = 10;
        let optCount  = 0;

        for (const opt of options) {
            if (optCount >= maxOpts) {
                break;
            }
            optCount++;

            const el = document.createElement('option');
            el.value       = opt.value;
            el.textContent = opt.label;
            if (opt.value === current) {
                el.selected = true;
            }
            select.appendChild(el);
        }

        return select;
    }

    renderPagination(totalPages) {
        const container = document.getElementById('bs_seo_priorities_pagination');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        let pageCount = 0;

        for (let i = 1; i <= totalPages; i++) {
            if (pageCount >= MAX_PAGES) {
                break;
            }
            pageCount++;

            const btn = document.createElement('button');
            btn.type        = 'button';
            btn.className   = 'ct-seo-dashboard__page-btn';
            btn.textContent = String(i);

            if (i === this.currentPage) {
                btn.classList.add('ct-seo-dashboard__page-btn--active');
            }

            btn.addEventListener('click', () => this.load(i));
            container.appendChild(btn);
        }
    }

    /* ═══ AJAX Helper ═══ */

    async ajaxPostForm(formData) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body:   formData,
            });
            return await response.json();
        } catch (err) {
            return { success: false, data: { message: String(err.message) } };
        }
    }
}
