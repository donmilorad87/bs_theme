/**
 * Admin SEO — Sitemap tab controller.
 *
 * General sub-panel : enable toggle, post-type toggles, save/regenerate.
 * Sitemap Index sub-panel : language → type → item tree with:
 *   - per-item inline priority/changefreq/exclude editing (auto-saved, 600 ms debounce)
 *   - HTML5 drag-and-drop row reordering (order saved automatically, 800 ms debounce)
 *
 * @package BS_Custom
 */
export default class Admin_Seo_Sitemap {

    static PRIORITIES  = ['auto','0.1','0.2','0.3','0.4','0.5','0.6','0.7','0.8','0.9','1.0'];
    static CHANGEFREQS = ['auto','always','hourly','daily','weekly','monthly','yearly','never'];

    constructor(nonce, ajaxUrl) {
        this.nonce   = nonce;
        this.ajaxUrl = ajaxUrl;

        /** @type {Map<number, ReturnType<typeof setTimeout>>} */
        this._saveTimers  = new Map();
        this._orderTimers = new Map();

        this._bindEvents();
        this.loadSettings();
    }

    /* ─── Event binding ──────────────────────────────────────── */

    _bindEvents() {
        document.getElementById('bs_seo_sitemap_save')
            ?.addEventListener('click', () => this.save());

        document.getElementById('bs_seo_sitemap_regenerate')
            ?.addEventListener('click', () => this.regenerate());

        const enabledToggle = document.getElementById('bs_seo_sitemap_enabled');
        if (enabledToggle) {
            enabledToggle.addEventListener('change', () => {
                this._applySitemapEnabledState(enabledToggle.checked);
                this.save();
            });
        }
    }

    /* ─── Load settings → populate type toggles → load tree ─── */

    async loadSettings() {
        const fd = new FormData();
        fd.append('action', 'admin_load_seo_global');
        fd.append('nonce', this.nonce);

        const result = await this.ajaxPostForm(fd);
        if (!result.success || !result.data) { return; }

        const data = result.data;

        const toggle = document.getElementById('bs_seo_sitemap_enabled');
        if (toggle) {
            toggle.checked = (data.sitemap_enabled === 'on' || data.sitemap_enabled === true);
            this._applySitemapEnabledState(toggle.checked);
        }

        let enabledTypes = [];
        if (data.sitemap_post_types) {
            try { enabledTypes = JSON.parse(data.sitemap_post_types); } catch (_e) { /**/ }
        }
        this._enabledTypes = Array.isArray(enabledTypes) ? enabledTypes : [];

        await this._loadTreeTypes();
    }

    /* ─── Fetch type/lang data, render toggles + tree ─────────── */

    async _loadTreeTypes() {
        const fd = new FormData();
        fd.append('action', 'admin_get_sitemap_tree_types');
        fd.append('nonce', this.nonce);

        const result = await this.ajaxPostForm(fd);
        if (!result.success || !result.data) { return; }

        this._treeData = result.data;
        this.renderTypeToggles(result.data.types);
        this.loadTree(result.data);
    }

    /* ─── Post-type toggle grid (exclude attachment) ────────────  */

    renderTypeToggles(types) {
        const grid = document.getElementById('bs_sitemap_type_toggles');
        if (!grid) { return; }

        grid.innerHTML = '';

        const maxTypes = 20;
        let rendered   = 0;

        for (const type of types) {
            if (rendered >= maxTypes) { break; }
            rendered++;

            /* attachment is excluded server-side; skip defensively on client too */
            if (type.slug === 'attachment') { continue; }

            const isEnabled = this._enabledTypes.length === 0
                ? type.enabled
                : this._enabledTypes.includes(type.slug);

            const label = document.createElement('label');
            label.className = 'ct-sitemap-type-toggle';

            const cb = document.createElement('input');
            cb.type    = 'checkbox';
            cb.value   = type.slug;
            cb.checked = isEnabled;
            cb.setAttribute('data-type-toggle', type.slug);
            cb.addEventListener('change', () => this.save());

            label.appendChild(cb);
            label.appendChild(document.createTextNode(` ${type.label} (${type.count})`));
            grid.appendChild(label);
        }
    }

    /* ─── Save general form ──────────────────────────────────── */

    async save() {
        const enabledToggle = document.getElementById('bs_seo_sitemap_enabled');
        const typeBoxes     = document.querySelectorAll('[data-type-toggle]');

        const enabledPostTypes = [];
        const maxBoxes         = 20;
        let   boxCount         = 0;

        for (const cb of typeBoxes) {
            if (boxCount >= maxBoxes) { break; }
            boxCount++;
            if (cb.checked) { enabledPostTypes.push(cb.value); }
        }

        const payload = {
            sitemap_enabled:    enabledToggle?.checked ? 'on' : 'off',
            sitemap_excluded:   '',
            sitemap_post_types: enabledPostTypes,
        };

        const fd = new FormData();
        fd.append('action', 'admin_save_seo_sitemap');
        fd.append('nonce',  this.nonce);
        fd.append('input',  JSON.stringify(payload));

        const result = await this.ajaxPostForm(fd);

        if (result.success) {
            window.ctToast?.show(result.data.message || 'Sitemap settings saved.', 'success');
            this._enabledTypes = enabledPostTypes;
        } else {
            window.ctToast?.show(result.data?.message || 'Error saving sitemap settings.', 'error');
        }
    }

    /* ─── Regenerate cache ───────────────────────────────────── */

    async regenerate() {
        const btn = document.getElementById('bs_seo_sitemap_regenerate');
        if (btn) { btn.disabled = true; btn.classList.add('is-regenerating'); }

        const fd = new FormData();
        fd.append('action', 'admin_regenerate_sitemap');
        fd.append('nonce',  this.nonce);

        const result = await this.ajaxPostForm(fd);

        if (btn) { btn.disabled = false; btn.classList.remove('is-regenerating'); }

        if (result.success) {
            window.ctToast?.show(result.data.message || 'Sitemap regenerated.', 'success');
            this._saveTimers.clear();
            this._orderTimers.clear();
        } else {
            window.ctToast?.show(result.data?.message || 'Error regenerating sitemap.', 'error');
        }
    }

    /* ─── Sitemap enabled/disabled visibility ────────────────── */

    _applySitemapEnabledState(enabled) {
        const typesSection = document.querySelector('section.ct-sitemap-types');
        const regenBtn     = document.getElementById('bs_seo_sitemap_regenerate');
        const indexLabel   = document.querySelector(
            '.ct-sitemap-subtabs__label[for="bs_sitemap_subtab_index"]'
        );
        const indexPanel   = document.querySelector('.ct-sitemap-subpanel--index');

        if (typesSection) { typesSection.hidden = !enabled; }
        if (regenBtn)     { regenBtn.disabled   = !enabled; }

        if (indexLabel) {
            indexLabel.classList.toggle('ct-sitemap-subtab--disabled', !enabled);
        }

        if (indexPanel) {
            indexPanel.classList.toggle('ct-sitemap-subpanel--disabled', !enabled);
        }
    }

    /* ─── Tree: languages → types → items ──────────────────────── */

    loadTree(treeData) {
        const root = document.getElementById('bs_sitemap_tree_root');
        if (!root) { return; }

        root.innerHTML = '';

        const { langs, types } = treeData;
        if (!langs || langs.length === 0) {
            root.textContent = 'No languages configured.';
            return;
        }

        const realLangs = langs.filter(l => l.iso2 !== '');

        /* Single (or no) language: skip language level, show types directly */
        if (realLangs.length <= 1) {
            const iso2    = realLangs.length === 1 ? realLangs[0].iso2 : '';
            const maxTypes = 20;
            let ti         = 0;

            for (const type of types) {
                if (ti >= maxTypes) { break; }
                ti++;
                root.appendChild(this._buildTypeAccordion(type, iso2));
            }

            return;
        }

        /* Multiple languages: render language accordions directly */
        const maxLangs = 50;
        let li          = 0;

        for (const lang of realLangs) {
            if (li >= maxLangs) { break; }
            li++;
            root.appendChild(this._buildLangAccordion(lang, types));
        }
    }

    _buildLangAccordion(lang, types) {
        const wrap = document.createElement('div');
        wrap.className    = 'ct-sitemap-lang';
        wrap.dataset.lang = lang.iso2;

        const btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'ct-sitemap-lang__header';
        btn.setAttribute('aria-expanded', 'false');

        const chevron = document.createElement('span');
        chevron.className   = 'ct-sitemap-lang__chevron';
        chevron.textContent = '▶';

        const isoBadge = document.createElement('span');
        isoBadge.className   = 'ct-sitemap-lang__iso';
        isoBadge.textContent = lang.iso2.toUpperCase();

        btn.appendChild(chevron);
        btn.appendChild(document.createTextNode(' ' + lang.label + ' '));
        btn.appendChild(isoBadge);

        const body = document.createElement('div');
        body.className = 'ct-sitemap-lang__body';
        body.hidden    = true;

        btn.addEventListener('click', () => {
            const open = !body.hidden;
            body.hidden = open;
            btn.setAttribute('aria-expanded', String(!open));
            chevron.style.transform = open ? '' : 'rotate(90deg)';

            if (!open && body.childElementCount === 0) {
                this.openLang(lang.iso2, types, body);
            }
        });

        wrap.appendChild(btn);
        wrap.appendChild(body);
        return wrap;
    }

    async openLang(iso2, types, body) {
        body.innerHTML = '';

        const maxTypes = 20;
        let ti          = 0;

        for (const type of types) {
            if (ti >= maxTypes) { break; }
            ti++;
            body.appendChild(this._buildTypeAccordion(type, iso2));
        }

        /* Fetch real language-filtered counts and update the count badges.
         * Badges show global totals until this resolves, then switch to accurate numbers. */
        const fd = new FormData();
        fd.append('action', 'admin_get_sitemap_lang_counts');
        fd.append('nonce',  this.nonce);
        fd.append('lang',   iso2);

        const result = await this.ajaxPostForm(fd);

        if (!result.success || !result.data || !result.data.counts) { return; }

        const { counts } = result.data;
        const maxBadges  = 20;
        let bi            = 0;

        for (const typeWrap of body.querySelectorAll('.ct-sitemap-type')) {
            if (bi >= maxBadges) { break; }
            bi++;

            const slug  = typeWrap.dataset.type;
            const badge = typeWrap.querySelector('.ct-sitemap-count');

            if (badge && slug !== undefined && counts[slug] !== undefined) {
                badge.textContent = counts[slug];
            }
        }
    }

    /* ─── Tree: post-type level ──────────────────────────────── */

    _buildTypeAccordion(type, iso2) {
        const wrap = document.createElement('div');
        wrap.className    = 'ct-sitemap-type';
        wrap.dataset.type = type.slug;
        wrap.dataset.lang = iso2;

        const btn = document.createElement('button');
        btn.type      = 'button';
        btn.className = 'ct-sitemap-type__header';
        btn.setAttribute('aria-expanded', 'false');

        const chevron = document.createElement('span');
        chevron.className   = 'ct-sitemap-type__chevron';
        chevron.textContent = '▶';

        const countBadge = document.createElement('span');
        countBadge.className   = 'ct-sitemap-count';
        countBadge.textContent = type.count;

        btn.appendChild(chevron);
        btn.appendChild(document.createTextNode(' ' + type.label + ' '));
        btn.appendChild(countBadge);

        const body = document.createElement('div');
        body.className = 'ct-sitemap-type__body';
        body.hidden    = true;

        btn.addEventListener('click', () => {
            const open = !body.hidden;
            body.hidden = open;
            btn.setAttribute('aria-expanded', String(!open));
            chevron.style.transform = open ? '' : 'rotate(90deg)';

            if (!open && body.childElementCount === 0) {
                this.openType(type.slug, iso2, body);
            }
        });

        wrap.appendChild(btn);
        wrap.appendChild(body);
        return wrap;
    }

    /* ─── Tree: item list ────────────────────────────────────── */

    async openType(type, lang, body) {
        body.innerHTML = '<p class="ct-sitemap-spinner">Loading&#8230;</p>';

        /* Show a loading indicator in the count badge while fetching */
        const _headerBtn = body.previousElementSibling;
        const _badge     = _headerBtn ? _headerBtn.querySelector('.ct-sitemap-count') : null;
        if (_badge) { _badge.textContent = '\u2026'; }

        const fd = new FormData();
        fd.append('action', 'admin_get_sitemap_tree_items');
        fd.append('nonce',  this.nonce);
        fd.append('type',   type);
        fd.append('lang',   lang);

        const result = await this.ajaxPostForm(fd);

        if (!result.success || !result.data) {
            body.innerHTML = '<p class="ct-sitemap-error">Failed to load items.</p>';
            if (_badge) { _badge.textContent = '?'; }
            return;
        }

        const { items } = result.data;
        body.innerHTML = '';

        /* Update badge with the real language-filtered count */
        if (_badge) { _badge.textContent = items ? items.length : 0; }

        if (!items || items.length === 0) {
            body.innerHTML = '<p class="ct-sitemap-empty">No items found.</p>';
            return;
        }

        body.appendChild(this._buildItemTable(items, type, lang));
    }

    _buildItemTable(items, type, lang) {
        const table  = document.createElement('table');
        table.className = 'ct-sitemap-items';

        const thead = document.createElement('thead');
        thead.innerHTML = `<tr>
            <th class="ct-sitemap-items__handle-col" aria-label="Drag to reorder"></th>
            <th>Title</th>
            <th>URL</th>
            <th>Priority</th>
            <th>Changefreq</th>
            <th>Exclude</th>
            <th>Last Modified</th>
            <th></th>
        </tr>`;
        table.appendChild(thead);

        const tbody    = document.createElement('tbody');
        const maxItems = 200;
        let rowCount    = 0;

        for (const item of items) {
            if (rowCount >= maxItems) { break; }
            rowCount++;
            tbody.appendChild(this.renderItemRow(item, type));
        }

        this._bindDragAndDrop(tbody, type, lang);
        table.appendChild(tbody);
        return table;
    }

    renderItemRow(item, type) {
        const tr = document.createElement('tr');
        tr.dataset.itemId = String(item.id);
        tr.draggable      = true;

        if (item.excluded) { tr.classList.add('ct-sitemap-item--excluded'); }

        /* ── Drag handle ── */
        const tdHandle = document.createElement('td');
        tdHandle.className   = 'ct-sitemap-items__handle';
        tdHandle.textContent = '⠿';
        tdHandle.setAttribute('aria-hidden', 'true');

        tr.addEventListener('dragstart', e => {
            e.dataTransfer.setData('text/plain', String(item.id));
            e.dataTransfer.effectAllowed = 'move';
            tr.classList.add('ct-sitemap-item--dragging');
        });
        tr.addEventListener('dragend', () => {
            tr.classList.remove('ct-sitemap-item--dragging');
        });

        /* ── Title ── */
        const tdTitle = document.createElement('td');
        tdTitle.className = 'ct-sitemap-items__title';
        const editLink = document.createElement('a');
        editLink.href        = item.edit_url || '#';
        editLink.target      = '_blank';
        editLink.rel         = 'noopener';
        editLink.textContent = item.title || '(no title)';
        tdTitle.appendChild(editLink);

        /* ── URL ── */
        const tdUrl  = document.createElement('td');
        tdUrl.className = 'ct-sitemap-items__url-cell';
        const urlWrap   = document.createElement('div');
        urlWrap.className = 'ct-sitemap-items__url';

        const urlText = document.createElement('span');
        urlText.className   = 'ct-sitemap-items__url-text';
        urlText.textContent = item.url;
        urlText.title       = item.url;

        const copyBtn = document.createElement('button');
        copyBtn.type      = 'button';
        copyBtn.className = 'ct-sitemap-items__copy-btn';
        copyBtn.title     = 'Copy URL';
        copyBtn.setAttribute('aria-label', 'Copy URL');
        copyBtn.innerHTML = '&#x2398;';
        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(item.url).then(() => {
                copyBtn.innerHTML = '&#x2713;';
                setTimeout(() => { copyBtn.innerHTML = '&#x2398;'; }, 1500);
            });
        });

        const openBtn = document.createElement('a');
        openBtn.href      = item.url;
        openBtn.target    = '_blank';
        openBtn.rel       = 'noopener';
        openBtn.className = 'ct-sitemap-items__open-btn';
        openBtn.title     = 'Open URL';
        openBtn.setAttribute('aria-label', 'Open URL');
        openBtn.innerHTML = '&#x2197;';

        urlWrap.appendChild(urlText);
        urlWrap.appendChild(copyBtn);
        urlWrap.appendChild(openBtn);
        tdUrl.appendChild(urlWrap);

        /* ── Priority ── */
        const tdPriority = document.createElement('td');
        const selPriority = this._buildSelect(Admin_Seo_Sitemap.PRIORITIES, item.priority);
        selPriority.dataset.field = 'priority';
        selPriority.addEventListener('change', () => this.scheduleAutoSave(item, type, tr));
        tdPriority.appendChild(selPriority);

        /* ── Changefreq ── */
        const tdFreq = document.createElement('td');
        const selFreq = this._buildSelect(Admin_Seo_Sitemap.CHANGEFREQS, item.changefreq);
        selFreq.dataset.field = 'changefreq';
        selFreq.addEventListener('change', () => this.scheduleAutoSave(item, type, tr));
        tdFreq.appendChild(selFreq);

        /* ── Exclude toggle ── */
        const tdExclude = document.createElement('td');
        tdExclude.className = 'ct-sitemap-items__exclude-cell';
        const toggleLabel = document.createElement('label');
        toggleLabel.className = 'ct-seo-toggle';
        const toggleInput = document.createElement('input');
        toggleInput.type      = 'checkbox';
        toggleInput.checked   = item.excluded;
        toggleInput.className = 'ct-seo-toggle__input';
        toggleInput.dataset.field = 'excluded';
        toggleInput.addEventListener('change', () => {
            tr.classList.toggle('ct-sitemap-item--excluded', toggleInput.checked);
            this.scheduleAutoSave(item, type, tr);
        });
        const toggleSlider = document.createElement('span');
        toggleSlider.className = 'ct-seo-toggle__slider';
        toggleLabel.appendChild(toggleInput);
        toggleLabel.appendChild(toggleSlider);
        tdExclude.appendChild(toggleLabel);

        /* ── Last modified ── */
        const tdLastmod = document.createElement('td');
        tdLastmod.className   = 'ct-sitemap-items__lastmod';
        tdLastmod.textContent = item.lastmod || '—';

        /* ── Status ── */
        const tdStatus = document.createElement('td');
        tdStatus.className = 'ct-sitemap-item-status';

        tr.appendChild(tdHandle);
        tr.appendChild(tdTitle);
        tr.appendChild(tdUrl);
        tr.appendChild(tdPriority);
        tr.appendChild(tdFreq);
        tr.appendChild(tdExclude);
        tr.appendChild(tdLastmod);
        tr.appendChild(tdStatus);

        /* Store live field references for the auto-save reader */
        tr._selPriority = selPriority;
        tr._selFreq     = selFreq;
        tr._toggleInput = toggleInput;

        return tr;
    }

    /* ─── Drag-and-drop ──────────────────────────────────────── */

    _bindDragAndDrop(tbody, type, lang) {
        tbody.addEventListener('dragover', e => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const dragging = tbody.querySelector('.ct-sitemap-item--dragging');
            if (!dragging) { return; }

            /* Remove previous drop-line indicators */
            for (const r of tbody.querySelectorAll('.ct-sitemap-item--drop-before')) {
                r.classList.remove('ct-sitemap-item--drop-before');
            }

            const afterEl = this._getDragAfterElement(tbody, e.clientY);
            if (afterEl) {
                afterEl.classList.add('ct-sitemap-item--drop-before');
                tbody.insertBefore(dragging, afterEl);
            } else {
                tbody.appendChild(dragging);
            }
        });

        tbody.addEventListener('dragleave', e => {
            if (!tbody.contains(e.relatedTarget)) {
                for (const r of tbody.querySelectorAll('.ct-sitemap-item--drop-before')) {
                    r.classList.remove('ct-sitemap-item--drop-before');
                }
            }
        });

        tbody.addEventListener('drop', e => {
            e.preventDefault();
            for (const r of tbody.querySelectorAll('.ct-sitemap-item--drop-before')) {
                r.classList.remove('ct-sitemap-item--drop-before');
            }
            this._scheduleOrderSave(tbody, type, lang);
        });
    }

    /**
     * Return the element directly AFTER the cursor position (or null for end).
     *
     * @param {HTMLElement} container
     * @param {number}      y  clientY from dragover event
     * @return {HTMLElement|null}
     */
    _getDragAfterElement(container, y) {
        const rows = [...container.querySelectorAll('tr:not(.ct-sitemap-item--dragging)')];

        let closest       = null;
        let closestOffset = Number.NEGATIVE_INFINITY;

        const maxRows = 500;
        let ri         = 0;

        for (const row of rows) {
            if (ri >= maxRows) { break; }
            ri++;

            const box    = row.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closestOffset) {
                closestOffset = offset;
                closest       = row;
            }
        }

        return closest;
    }

    /**
     * Debounce saving the current row order (800 ms after last drop).
     *
     * @param {HTMLElement} tbody
     * @param {string}      type
     * @param {string}      lang
     */
    _scheduleOrderSave(tbody, type, lang) {
        const key      = type + '_' + lang;
        const existing = this._orderTimers.get(key);
        if (existing !== undefined) { clearTimeout(existing); }

        const timer = setTimeout(async () => {
            const ids = [...tbody.querySelectorAll('tr[data-item-id]')]
                .map(r => parseInt(r.dataset.itemId, 10));

            const fd = new FormData();
            fd.append('action', 'admin_save_sitemap_order');
            fd.append('nonce',  this.nonce);
            fd.append('input',  JSON.stringify({ type, lang, ids }));

            await this.ajaxPostForm(fd);
            this._orderTimers.delete(key);
        }, 800);

        this._orderTimers.set(key, timer);
    }

    /* ─── Auto-save per item ─────────────────────────────────── */

    scheduleAutoSave(item, type, tr) {
        const existing = this._saveTimers.get(item.id);
        if (existing !== undefined) { clearTimeout(existing); }

        const statusCell = tr.querySelector('.ct-sitemap-item-status');
        if (statusCell) {
            statusCell.textContent = 'Saving\u2026';
            statusCell.className   = 'ct-sitemap-item-status ct-sitemap-item-status--saving';
        }

        const timer = setTimeout(() => this.saveItem(item, type, tr), 600);
        this._saveTimers.set(item.id, timer);
    }

    async saveItem(item, type, tr) {
        const priority   = tr._selPriority?.value  ?? 'auto';
        const changefreq = tr._selFreq?.value       ?? 'auto';
        const excluded   = tr._toggleInput?.checked ?? false;

        const fd = new FormData();
        fd.append('action', 'admin_save_sitemap_item');
        fd.append('nonce',  this.nonce);
        fd.append('input',  JSON.stringify({ id: item.id, type, priority, changefreq, excluded }));

        const result     = await this.ajaxPostForm(fd);
        const statusCell = tr.querySelector('.ct-sitemap-item-status');

        this._saveTimers.delete(item.id);

        if (result.success) {
            if (statusCell) {
                statusCell.textContent = '\u2713 Saved';
                statusCell.className   = 'ct-sitemap-item-status ct-sitemap-item-status--saved';
                setTimeout(() => { statusCell.textContent = ''; }, 2500);
            }
        } else {
            if (statusCell) {
                statusCell.textContent = '\u2717 Error';
                statusCell.className   = 'ct-sitemap-item-status ct-sitemap-item-status--error';
            }
        }
    }

    /* ─── Pagination ─────────────────────────────────────────── */

    _buildPagination(currentPage, totalPages, type, lang, body) {
        const wrap = document.createElement('div');
        wrap.className = 'ct-sitemap-pagination';

        const prevBtn = document.createElement('button');
        prevBtn.type        = 'button';
        prevBtn.textContent = '\u2190 Prev';
        prevBtn.disabled    = currentPage <= 1;
        prevBtn.addEventListener('click', () => this.openType(type, lang, currentPage - 1, body));

        const info = document.createElement('span');
        info.className   = 'ct-sitemap-pagination__info';
        info.textContent = `Page ${currentPage} / ${totalPages}`;

        const nextBtn = document.createElement('button');
        nextBtn.type        = 'button';
        nextBtn.textContent = 'Next \u2192';
        nextBtn.disabled    = currentPage >= totalPages;
        nextBtn.addEventListener('click', () => this.openType(type, lang, currentPage + 1, body));

        wrap.appendChild(prevBtn);
        wrap.appendChild(info);
        wrap.appendChild(nextBtn);
        return wrap;
    }

    /* ─── Helpers ────────────────────────────────────────────── */

    _buildSelect(options, selected) {
        const sel = document.createElement('select');
        sel.className = 'ct-seo-form__select ct-sitemap-select';

        const maxOpts = 20;
        let oi         = 0;

        for (const opt of options) {
            if (oi >= maxOpts) { break; }
            oi++;

            const option = document.createElement('option');
            option.value       = opt;
            option.textContent = opt;
            if (opt === selected) { option.selected = true; }
            sel.appendChild(option);
        }

        return sel;
    }

    async ajaxPostForm(formData) {
        try {
            const response = await fetch(this.ajaxUrl, { method: 'POST', body: formData });
            return await response.json();
        } catch (err) {
            return { success: false, data: { message: String(err.message) } };
        }
    }
}
