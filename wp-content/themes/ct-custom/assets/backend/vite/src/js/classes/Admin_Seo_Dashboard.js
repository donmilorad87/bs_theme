/**
 * Admin SEO Dashboard sub-tab.
 *
 * Renders overview cards and a paginated table of all pages/posts
 * with their SEO scores and meta status.
 *
 * @package BS_Custom
 */

/** Maximum rows per page. */
const MAX_PER_PAGE = 30;

/** Maximum card count. */
const MAX_CARDS = 10;

export default class Admin_Seo_Dashboard {

    /**
     * @param {string} nonce   WP AJAX nonce.
     * @param {string} ajaxUrl Admin AJAX URL.
     */
    constructor(nonce, ajaxUrl) {
        this.nonce   = nonce;
        this.ajaxUrl = ajaxUrl;
        this.currentPage = 1;
        this.currentType = 'all';
        this.currentLang = '';
        this.searchQuery = '';
        this.debounceTimer = null;

        this.bindEvents();

        /* Read the language select's initial value (default language pre-selected in PHP). */
        const langFilterInit = document.getElementById('bs_seo_dashboard_lang_filter');
        if (langFilterInit) { this.currentLang = langFilterInit.value; }

        this.load(1);
    }

    bindEvents() {
        const typeFilter = document.getElementById('bs_seo_dashboard_type_filter');
        if (typeFilter) {
            typeFilter.addEventListener('change', () => {
                this.currentType = typeFilter.value;
                this.currentPage = 1;
                this.load(1);
            });
        }

        const langFilter = document.getElementById('bs_seo_dashboard_lang_filter');
        if (langFilter) {
            langFilter.addEventListener('change', () => {
                this.currentLang = langFilter.value;
                this.currentPage = 1;
                this.load(1);
            });
        }

        const searchInput = document.getElementById('bs_seo_dashboard_search');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                if (this.debounceTimer) { clearTimeout(this.debounceTimer); }
                this.debounceTimer = setTimeout(() => {
                    this.searchQuery = searchInput.value.trim();
                    this.currentPage = 1;
                    this.load(1);
                }, 400);
            });
        }

        const bulkBtn = document.getElementById('bs_seo_bulk_analyze');
        if (bulkBtn) {
            bulkBtn.addEventListener('click', () => this.bulkAnalyze());
        }

        const pingBtn = document.getElementById('bs_seo_ping_engines');
        if (pingBtn) {
            pingBtn.addEventListener('click', () => this.pingEngines());
        }
    }

    async load(page) {
        this.currentPage = page || 1;

        const formData = new FormData();
        formData.append('action', 'admin_get_seo_dashboard');
        formData.append('nonce', this.nonce);
        formData.append('page', String(this.currentPage));
        formData.append('post_type', this.currentType);
        formData.append('lang', this.currentLang);
        formData.append('search', this.searchQuery);

        const result = await this.ajaxPostForm(formData);

        if (!result.success) {
            return;
        }

        const data = result.data || {};
        this.renderCards(data.cards || {});
        this.renderTable(data.items || []);
        this.renderPagination(data.total_pages || 1);
    }

    renderCards(cards) {
        const container = document.getElementById('bs_seo_dashboard_cards');
        if (!container) { return; }

        container.innerHTML = '';

        const cardDefs = [
            { key: 'total_pages', label: 'Total Pages', icon: 'file' },
            { key: 'scored_above_70', label: 'Score > 70', icon: 'check' },
            { key: 'missing_meta', label: 'Missing Meta', icon: 'warning' },
            { key: 'noindex', label: 'Noindex', icon: 'lock' },
        ];

        let cardCount = 0;

        for (const def of cardDefs) {
            if (cardCount >= MAX_CARDS) { break; }
            cardCount++;

            const card = document.createElement('div');
            card.className = 'ct-seo-dashboard__card';

            const value = document.createElement('span');
            value.className = 'ct-seo-dashboard__card-value';
            value.textContent = String(cards[def.key] || 0);

            const label = document.createElement('span');
            label.className = 'ct-seo-dashboard__card-label';
            label.textContent = def.label;

            card.appendChild(value);
            card.appendChild(label);
            container.appendChild(card);
        }
    }

    renderTable(items) {
        const container = document.getElementById('bs_seo_dashboard_table');
        if (!container) { return; }

        if (!items || items.length === 0) {
            container.innerHTML = '<p class="ct-seo-dashboard__empty">No items found.</p>';
            return;
        }

        const table = document.createElement('table');
        table.className = 'ct-seo-dashboard__data-table';

        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        const columns = ['Title', 'Type', 'Score', 'Meta Title', 'Meta Desc', 'Focus KW', 'Robots'];
        const maxCols = 10;
        let colCount = 0;

        for (const col of columns) {
            if (colCount >= maxCols) { break; }
            colCount++;

            const th = document.createElement('th');
            th.textContent = col;
            headRow.appendChild(th);
        }

        thead.appendChild(headRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');
        let rowCount = 0;

        for (const item of items) {
            if (rowCount >= MAX_PER_PAGE) { break; }
            rowCount++;

            const tr = document.createElement('tr');

            /* Title */
            const tdTitle = document.createElement('td');
            const titleLink = document.createElement('a');
            titleLink.href = item.edit_url || '#';
            titleLink.target = '_blank';
            titleLink.textContent = item.title || '(no title)';
            tdTitle.appendChild(titleLink);
            tr.appendChild(tdTitle);

            /* Type */
            const tdType = document.createElement('td');
            tdType.textContent = item.post_type || '';
            tr.appendChild(tdType);

            /* Score */
            const tdScore = document.createElement('td');
            const scoreBadge = document.createElement('span');
            const score = parseInt(item.score, 10) || 0;
            scoreBadge.className = 'ct-seo-score-badge';

            if (score >= 70) {
                scoreBadge.classList.add('ct-seo-score-badge--good');
            } else if (score >= 40) {
                scoreBadge.classList.add('ct-seo-score-badge--ok');
            } else {
                scoreBadge.classList.add('ct-seo-score-badge--poor');
            }

            scoreBadge.textContent = String(score);
            tdScore.appendChild(scoreBadge);
            tr.appendChild(tdScore);

            /* Meta Title */
            const tdMetaTitle = document.createElement('td');
            tdMetaTitle.textContent = item.meta_title ? 'Yes' : '-';
            tdMetaTitle.className = item.meta_title ? 'ct-seo-dash-yes' : 'ct-seo-dash-no';
            tr.appendChild(tdMetaTitle);

            /* Meta Description */
            const tdMetaDesc = document.createElement('td');
            tdMetaDesc.textContent = item.meta_description ? 'Yes' : '-';
            tdMetaDesc.className = item.meta_description ? 'ct-seo-dash-yes' : 'ct-seo-dash-no';
            tr.appendChild(tdMetaDesc);

            /* Focus Keyword */
            const tdFocus = document.createElement('td');
            tdFocus.textContent = item.focus_keyword || '-';
            tr.appendChild(tdFocus);

            /* Robots */
            const tdRobots = document.createElement('td');
            tdRobots.textContent = item.robots || 'index, follow';
            tr.appendChild(tdRobots);

            tbody.appendChild(tr);
        }

        table.appendChild(tbody);
        container.innerHTML = '';
        container.appendChild(table);
    }

    renderPagination(totalPages) {
        const container = document.getElementById('bs_seo_dashboard_pagination');
        if (!container) { return; }

        container.innerHTML = '';

        if (totalPages <= 1) { return; }

        const maxPages = 20;
        let pageCount = 0;

        for (let i = 1; i <= totalPages; i++) {
            if (pageCount >= maxPages) { break; }
            pageCount++;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ct-seo-dashboard__page-btn';

            if (i === this.currentPage) {
                btn.classList.add('ct-seo-dashboard__page-btn--active');
            }

            btn.textContent = String(i);
            btn.addEventListener('click', () => this.load(i));
            container.appendChild(btn);
        }
    }

    async bulkAnalyze() {
        const btn = document.getElementById('bs_seo_bulk_analyze');
        if (btn) { btn.disabled = true; btn.textContent = 'Analyzing...'; }

        const formData = new FormData();
        formData.append('action', 'admin_bulk_analyze_seo');
        formData.append('nonce', this.nonce);

        const result = await this.ajaxPostForm(formData);

        if (btn) { btn.disabled = false; btn.textContent = 'Bulk Analyze'; }

        if (result.success) {
            window.ctToast.show(result.data.message || 'Analysis complete.', 'success');
            this.load(this.currentPage);
        } else {
            window.ctToast.show(result.data?.message || 'Analysis failed.', 'error');
        }
    }

    async pingEngines() {
        const btn = document.getElementById('bs_seo_ping_engines');
        if (btn) { btn.disabled = true; btn.textContent = 'Pinging...'; }

        const formData = new FormData();
        formData.append('action', 'admin_ping_search_engines');
        formData.append('nonce', this.nonce);

        const result = await this.ajaxPostForm(formData);

        if (btn) { btn.disabled = false; btn.textContent = 'Ping Search Engines'; }

        if (result.success) {
            window.ctToast.show(result.data.message || 'Search engines pinged.', 'success');
        } else {
            window.ctToast.show(result.data?.message || 'Ping failed.', 'error');
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
