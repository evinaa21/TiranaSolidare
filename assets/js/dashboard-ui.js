/**
 * dashboard-ui.js
 * ---------------------------------------------------
 * Premium Dashboard UI renderer.
 * Works with existing main.js + ajax-polling.js APIs.
 * Replaces the default Bootstrap table rendering with
 * styled components matching the new admin panel design.
 * ---------------------------------------------------
 */

// ── Panel switching ─────────────────────────────────

function switchPanel(panelId, btn) {
    // Hide all panels
    document.querySelectorAll('.db-panel').forEach(p => p.classList.remove('active'));
    // Deactivate all nav items
    document.querySelectorAll('.db-nav-item').forEach(n => n.classList.remove('active'));

    // Show selected
    const panel = document.getElementById(`panel-${panelId}`);
    if (panel) {
        panel.classList.add('active');
        // Re-trigger animation
        panel.style.animation = 'none';
        panel.offsetHeight; // reflow
        panel.style.animation = '';
    }
    if (btn) btn.classList.add('active');

      // Ruaj tab-in aktiv në URL
      location.hash = panelId;
}


// ── Sidebar toggle (mobile) ─────────────────────────

function toggleSidebar() {
    const sidebar = document.getElementById('db-sidebar');
    let backdrop = document.getElementById('db-backdrop');

    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.id = 'db-backdrop';
        backdrop.className = 'db-backdrop';
        backdrop.onclick = toggleSidebar;
        document.body.appendChild(backdrop);
    }

    sidebar.classList.toggle('open');
    backdrop.classList.toggle('active');
}


// ── Toggle create-event form ─────────────────────────

function toggleCreateEvent() {
    const w = document.getElementById('create-event-wrapper');
    const table = document.getElementById('admin-event-list');
    const appCard = document.getElementById('event-applications-card');
    
    if (w) {
        const isOpening = w.style.display === 'none';
        w.style.display = isOpening ? 'block' : 'none';
        if (table) table.style.display = isOpening ? 'none' : 'block';
        if (appCard) appCard.style.display = 'none';
    }
}


// ── Category dropdown loader ─────────────────────────

async function loadCategoryDropdown() {
    const sel = document.getElementById('event-category-select');
    if (!sel) return;
    try {
        const json = await apiCall('categories.php?action=list');
        if (json.success && json.data.categories) {
            json.data.categories.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_kategoria;
                opt.textContent = c.emri;
                sel.appendChild(opt);
            });
        } else {
            console.warn('[Dashboard] Category load failed:', json.message || 'Unknown error');
            const opt = document.createElement('option');
            opt.disabled = true;
            opt.textContent = 'Gabim gjatë ngarkimit të kategorive';
            sel.appendChild(opt);
        }
    } catch (e) {
        console.warn('[Dashboard] Category dropdown error:', e);
        const opt = document.createElement('option');
        opt.disabled = true;
        opt.textContent = 'Gabim rrjeti';
        sel.appendChild(opt);
    }
}


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Dashboard Stats Renderer
// ═══════════════════════════════════════════════════════

// Override the loadDashboardStats from main.js
const _origLoadDashboardStats = typeof loadDashboardStats === 'function' ? loadDashboardStats : null;

window.loadDashboardStats = async function () {
    if (window._dashboardStatsLoading) return;
    window._dashboardStatsLoading = true;

    const container = document.getElementById('dashboard-stats');
    const subContainer = document.getElementById('dashboard-substats');
    if (!container) {
        window._dashboardStatsLoading = false;
        return;
    }

    const json = await apiCall('stats.php?action=overview');
    if (!json.success) return;

    const d = json.data;

    container.innerHTML = `
        <div class="db-stat" style="animation-delay: 0s">
            <div class="db-stat__icon db-stat__icon--users">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="db-stat__info">
                <div class="db-stat__value">${d.users.total_perdorues}</div>
                <div class="db-stat__label">Përdorues</div>
            </div>
        </div>
        <div class="db-stat" style="animation-delay: 0.05s">
            <div class="db-stat__icon db-stat__icon--events">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </div>
            <div class="db-stat__info">
                <div class="db-stat__value">${d.events.total_evente}</div>
                <div class="db-stat__label">Evente</div>
            </div>
        </div>
        <div class="db-stat" style="animation-delay: 0.1s">
            <div class="db-stat__icon db-stat__icon--apps">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/></svg>
            </div>
            <div class="db-stat__info">
                <div class="db-stat__value">${d.applications.total_aplikime}</div>
                <div class="db-stat__label">Aplikime</div>
            </div>
        </div>
        <div class="db-stat" style="animation-delay: 0.15s">
            <div class="db-stat__icon db-stat__icon--requests">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            </div>
            <div class="db-stat__info">
                <div class="db-stat__value">${d.help_requests.total_kerkesa}</div>
                <div class="db-stat__label">Kërkesa</div>
            </div>
        </div>`;

 


// Render charts after sub-stats
    if (!document.getElementById('dashboard-charts')) {
        setTimeout(() => renderDashboardCharts(d), 50);
    } else {
        document.getElementById('dashboard-charts').remove();
        setTimeout(() => renderDashboardCharts(d), 50);
    }
    setTimeout(() => { window._dashboardStatsLoading = false; }, 500);
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Admin Event List
// ═══════════════════════════════════════════════════════

window._eventFilters = window._eventFilters || {
    status: 'all',
    kategoria: 'all',
    dateRange: 'all',
    search: '',
    location: ''
};

window.loadAdminEvents = async function (page = 1) {
    const container = document.getElementById('admin-event-list');
    if (!container) return;

    const filters = window._eventFilters;

    // Merr të gjitha eventet për filtrim client-side
    const json = await apiCall(`events.php?action=list&page=1&limit=1000`);
    if (!json.success) return;

    let events = json.data.events;
    const allEvents = [...events];

    // ── Filter: status ──
    if (filters.status === 'active') {
        events = events.filter(ev => new Date(ev.data) >= new Date());
    } else if (filters.status === 'past') {
        events = events.filter(ev => new Date(ev.data) < new Date());
    }

    // ── Filter: kategoria ──
    if (filters.kategoria !== 'all') {
        events = events.filter(ev => String(ev.id_kategoria) === String(filters.kategoria));
    }

    // ── Filter: date range ──
const now = new Date();
if (filters.dateRange === 'week') {
    // Java aktuale — nga e hëna deri të dielën
    const dayOfWeek = now.getDay(); // 0=diel, 1=hënë...
    const monday = new Date(now);
    monday.setDate(now.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
    monday.setHours(0, 0, 0, 0);
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    sunday.setHours(23, 59, 59, 999);
    events = events.filter(ev => new Date(ev.data) >= monday && new Date(ev.data) <= sunday);
} else if (filters.dateRange === 'month') {
    // Muaji aktual — nga 1 i muajit deri në fund të muajit
    const monthStart = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0, 0);
    const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59, 999);
    events = events.filter(ev => new Date(ev.data) >= monthStart && new Date(ev.data) <= monthEnd);
} else if (filters.dateRange === 'past3') {
    // 3 muajt e fundit — nga 1 i muajit 3 muaj më parë deri sot
    const past3Start = new Date(now.getFullYear(), now.getMonth() - 3, 1, 0, 0, 0, 0);
    const todayEnd = new Date(now); todayEnd.setHours(23, 59, 59, 999);
    events = events.filter(ev => new Date(ev.data) >= past3Start && new Date(ev.data) <= todayEnd);
}

    // ── Filter: search ──
    if (filters.search.trim() !== '') {
        const q = filters.search.toLowerCase();
        events = events.filter(ev => ev.titulli.toLowerCase().includes(q));
    }

    // ── Filter: location ──
    if (filters.location.trim() !== '') {
        const q = filters.location.toLowerCase();
        events = events.filter(ev => (ev.vendndodhja || '').toLowerCase().includes(q));
    }

    // ── Pagination client-side ──
    const limit = 10;
    const totalFiltered = events.length;
    const totalPages = Math.ceil(totalFiltered / limit) || 1;
    const offset = (page - 1) * limit;
    const pageEvents = events.slice(offset, offset + limit);

    // ── Kategoritë për dropdown ──
    const categories = [...new Map(
        allEvents.filter(e => e.kategoria_emri).map(e => [e.id_kategoria, e.kategoria_emri])
    ).entries()];

    // ── Vendndodhjet për dropdown ──
    const locations = [...new Set(
        allEvents.filter(e => e.vendndodhja).map(e => e.vendndodhja.trim())
    )].sort();

    let html = `
    <div class="db-filter-bar">
        <div class="db-filter-group">
            <label>Statusi</label>
            <select class="db-filter-select" onchange="window._eventFilters.status = this.value; loadAdminEvents(1)">
                <option value="all"    ${filters.status === 'all'    ? 'selected' : ''}>Të gjitha</option>
                <option value="active" ${filters.status === 'active' ? 'selected' : ''}>Aktive</option>
                <option value="past"   ${filters.status === 'past'   ? 'selected' : ''}>Të kaluara</option>
            </select>
        </div>
        <div class="db-filter-group">
            <label>Kategoria</label>
            <select class="db-filter-select" onchange="window._eventFilters.kategoria = this.value; loadAdminEvents(1)">
                <option value="all">Të gjitha</option>
                ${categories.map(([id, name]) => `<option value="${id}" ${filters.kategoria === String(id) ? 'selected' : ''}>${escapeHtml(name)}</option>`).join('')}
            </select>
        </div>
        <div class="db-filter-group">
            <label>Periudha</label>
            <select class="db-filter-select" onchange="window._eventFilters.dateRange = this.value; loadAdminEvents(1)">
                <option value="all"   ${filters.dateRange === 'all'   ? 'selected' : ''}>Të gjitha</option>
                <option value="week"  ${filters.dateRange === 'week'  ? 'selected' : ''}>Kjo javë</option>
                <option value="month" ${filters.dateRange === 'month' ? 'selected' : ''}>Ky muaj</option>
                <option value="past3" ${filters.dateRange === 'past3' ? 'selected' : ''}>3 muajt e fundit</option>
            </select>
        </div>
        <div class="db-filter-group">
    <input
        type="text"
        class="db-filter-select"
        placeholder="Kërko vendndodhje..."
        value="${escapeHtml(filters.location)}"
        oninput="window._eventFilters.location = this.value; clearTimeout(window._locSearchTimeout); window._locSearchTimeout = setTimeout(() => { loadAdminEvents(1); }, 350)"
        style="min-width:160px;"
    >
</div>
        <div class="db-filter-group">
            <input
                type="text"
                class="db-filter-select"
                placeholder="Kërko titull..."
                value="${escapeHtml(filters.search)}"
                oninput="window._eventFilters.search = this.value; clearTimeout(window._searchTimeout); window._searchTimeout = setTimeout(() => { loadAdminEvents(1); }, 350)"
                style="min-width:160px;"
            >
        </div>
        <span class="db-filter-count"><strong>${totalFiltered}</strong> evente</span>
    </div>`;

    if (pageEvents.length === 0) {
        html += '<div class="db-loading">Nuk ka evente për këto filtra.</div>';
        container.innerHTML = html;
        return;
    }

    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>ID</th><th>Titulli</th><th>Kategoria</th><th>Data</th><th>Vendndodhja</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    pageEvents.forEach(ev => {
        const isPast = new Date(ev.data) < new Date();
        html += `<tr ${isPast ? 'style="opacity:0.65"' : ''}>
            <td><strong>#${ev.id_eventi}</strong></td>
            <td>${escapeHtml(ev.titulli)}</td>
            <td>${ev.kategoria_emri ? `<span class="db-badge db-badge--vol">${escapeHtml(ev.kategoria_emri)}</span>` : '<span style="color:#b0b8c4">—</span>'}</td>
            <td>${formatDate(ev.data)}</td>
            <td>${escapeHtml(ev.vendndodhja || '—')}</td>
            <td>
                <div class="db-table__actions">
    ${!isPast 
        ? `<button class="db-btn db-btn--warning db-btn--sm" onclick="editEventPrompt(${ev.id_eventi}, this)">Ndrysho</button>` 
        : `<button class="db-btn db-btn--sm" style="visibility:hidden;pointer-events:none;">Ndrysho</button>`}
    <button class="db-btn db-btn--danger db-btn--sm" onclick="deleteEvent(${ev.id_eventi})">Fshi</button>
    <button class="db-btn db-btn--info db-btn--sm" onclick="viewEventApps(${ev.id_eventi})">Aplikime</button>
</div>
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';

    if (totalPages > 1) {
        html += dbPagination(page, totalPages, 'loadAdminEvents');
    }
const existingFilterBar = container.querySelector('.db-filter-bar');
const wasSearchFocused = document.activeElement?.classList.contains('db-filter-select') && 
                         document.activeElement?.type === 'text';

container.innerHTML = html;

// Rifokuso search input pas render
if (wasSearchFocused) {
    const inp = container.querySelector('input[type="text"]');
    if (inp) {
        inp.focus();
        inp.setSelectionRange(inp.value.length, inp.value.length);
    }
}
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Event Applications
// ═══════════════════════════════════════════════════════

window.viewEventApps = async function (eventId) {
    const container = document.getElementById('event-applications');
    const card = document.getElementById('event-applications-card');
    if (!container) return;
    container.dataset.eventId = eventId;
    if (card) card.style.display = 'block';

    const json = await apiCall(`applications.php?action=by_event&id=${eventId}`);
    if (!json.success) return;

    const { applications, summary } = json.data;

    let html = `<div style="display:flex;gap:10px;padding:16px;flex-wrap:wrap;">
        <span class="db-badge db-badge--pending">Në pritje: ${summary.ne_pritje}</span>
        <span class="db-badge db-badge--active">Pranuar: ${summary.pranuar}</span>
        <span class="db-badge db-badge--blocked">Refuzuar: ${summary.refuzuar}</span>
    </div>`;

    if (applications.length === 0) {
        html += '<div class="db-loading">Nuk ka aplikime për këtë event.</div>';
        container.innerHTML = html;
        return;
    }

    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>Emri</th><th>Email</th><th>Statusi</th><th>Veprime</th></tr></thead><tbody>';

    applications.forEach(app => {
        const statusClass = app.statusi === 'Pranuar' ? 'active' : app.statusi === 'Refuzuar' ? 'blocked' : 'pending';
        html += `<tr>
            <td><strong>${escapeHtml(app.vullnetari_emri)}</strong></td>
            <td>${escapeHtml(app.vullnetari_email)}</td>
            <td><span class="db-badge db-badge--${statusClass}">${escapeHtml(app.statusi)}</span></td>
            <td>
                <div class="db-table__actions">
                    <button class="db-btn db-btn--success db-btn--sm" onclick="updateAppStatus(${app.id_aplikimi}, 'Pranuar')">Prano</button>
                    <button class="db-btn db-btn--danger db-btn--sm" onclick="updateAppStatus(${app.id_aplikimi}, 'Refuzuar')">Refuzo</button>
                </div>
            </td>
        </tr>`;
    });
    html += '</tbody></table></div>';
    container.innerHTML = html;
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: User Management (Detail Panel approach)
// ═══════════════════════════════════════════════════════

window._userFilters = window._userFilters || {
    roli: 'all',
    statusi: 'all'
};

window.loadUsers = async function (page = 1) {
    const container = document.getElementById('admin-user-list');
    if (!container) return;

    const filters = window._userFilters;

    // Merr të gjithë përdoruesit për filtrim client-side
    const json = await apiCall(`users.php?action=list&page=1&limit=1000`);
    if (!json.success) return;

    let users = json.data.users;

    // ── Filter: roli ──
    if (filters.roli !== 'all') {
        users = users.filter(u => u.roli === filters.roli);
    }

    // ── Filter: statusi ──
    if (filters.statusi !== 'all') {
        users = users.filter(u => u.statusi_llogarise === filters.statusi);
    }

    // ── Pagination client-side ──
    const limit = 15;
    const totalFiltered = users.length;
    const totalPages = Math.ceil(totalFiltered / limit) || 1;
    const offset = (page - 1) * limit;
    const pageUsers = users.slice(offset, offset + limit);

    let html = `
    <div class="db-filter-bar">
        <div class="db-filter-group">
            <label>Roli</label>
            <select class="db-filter-select" onchange="window._userFilters.roli = this.value; loadUsers(1)">
                <option value="all"       ${filters.roli === 'all'       ? 'selected' : ''}>Të gjitha</option>
                <option value="Admin"     ${filters.roli === 'Admin'     ? 'selected' : ''}>Admin</option>
                <option value="Vullnetar" ${filters.roli === 'Vullnetar' ? 'selected' : ''}>Vullnetar</option>
            </select>
        </div>
        <div class="db-filter-group">
            <label>Statusi</label>
            <select class="db-filter-select" onchange="window._userFilters.statusi = this.value; loadUsers(1)">
                <option value="all"         ${filters.statusi === 'all'         ? 'selected' : ''}>Të gjitha</option>
                <option value="Aktiv"       ${filters.statusi === 'Aktiv'       ? 'selected' : ''}>Aktiv</option>
                <option value="Bllokuar"    ${filters.statusi === 'Bllokuar'    ? 'selected' : ''}>Bllokuar</option>
                <option value="Çaktivizuar" ${filters.statusi === 'Çaktivizuar' ? 'selected' : ''}>Çaktivizuar</option>
            </select>
        </div>
        <span class="db-filter-count"><strong>${totalFiltered}</strong> përdorues</span>
    </div>`;

    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>ID</th><th>Emri</th><th>Email</th><th>Roli</th><th>Statusi</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    pageUsers.forEach(u => {
        const isBlocked = u.statusi_llogarise === 'Bllokuar';
        const isDeactivated = u.statusi_llogarise === 'Çaktivizuar';
        const roleClass = u.roli === 'Admin' ? 'admin' : 'vol';
        const statusClass = isBlocked ? 'blocked' : isDeactivated ? 'deactivated' : 'active';

        html += `<tr class="${isBlocked ? 'db-row--blocked' : ''} ${isDeactivated ? 'db-row--deactivated' : ''}">
            <td><strong>#${u.id_perdoruesi}</strong></td>
            <td>${escapeHtml(u.emri)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td><span class="db-badge db-badge--${roleClass}">${u.roli}</span></td>
            <td><span class="db-badge db-badge--${statusClass}">${u.statusi_llogarise}</span></td>
            <td>
                <div class="db-table__actions">
                    <button class="db-btn db-btn--info db-btn--sm" onclick="openUserDetail(${u.id_perdoruesi})">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Shiko
                    </button>
                    ${isBlocked
                        ? `<button class="db-btn db-btn--success db-btn--sm" onclick="toggleBlock(${u.id_perdoruesi}, 'unblock')">Zhblloko</button>`
                        : isDeactivated
                        ? `<button class="db-btn db-btn--success db-btn--sm" onclick="reactivateUser(${u.id_perdoruesi})">Riaktivizo</button>`
                        : `<button class="db-btn db-btn--warning db-btn--sm" onclick="toggleBlock(${u.id_perdoruesi}, 'block')">Blloko</button>`}
                </div>
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';

    if (totalPages > 1) {
        html += dbPagination(page, totalPages, 'loadUsers');
    }

    container.innerHTML = html;
};
// ═══════════════════════════════════════════════════════
//  User Detail Panel
// ═══════════════════════════════════════════════════════

window.openUserDetail = async function (userId) {
    // Switch to user-detail panel
    document.querySelectorAll('.db-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('panel-user-detail');
    if (panel) {
        panel.classList.add('active');
        panel.style.animation = 'none';
        panel.offsetHeight;
        panel.style.animation = '';
    }
    // Keep users nav item highlighted
    document.querySelectorAll('.db-nav-item').forEach(n => n.classList.remove('active'));
    const usersNav = document.querySelector('[data-panel="users"]');
    if (usersNav) usersNav.classList.add('active');

    const container = document.getElementById('user-detail-content');
    if (!container) return;
    container.innerHTML = '<div class="db-loading">Duke ngarkuar detajet…</div>';

    const json = await apiCall(`users.php?action=get&id=${userId}`);
    if (!json.success) {
        container.innerHTML = '<div class="db-loading">Gabim gjatë ngarkimit.</div>';
        return;
    }

    const u = json.data;
    const isBlocked = u.statusi_llogarise === 'Bllokuar';
    const isDeactivated = u.statusi_llogarise === 'Çaktivizuar';
    const isActive = u.statusi_llogarise === 'Aktiv';
    const roleClass = u.roli === 'Admin' ? 'admin' : 'vol';
    const statusClass = isBlocked ? 'blocked' : isDeactivated ? 'deactivated' : 'active';
    const initial = (u.emri || 'P').charAt(0).toUpperCase();

    container.innerHTML = `
        <!-- User Profile Header -->
        <div class="ud-header">
            <div class="ud-avatar ud-avatar--${statusClass}">${escapeHtml(initial)}</div>
            <div class="ud-header__info">
                <h2 class="ud-header__name">${escapeHtml(u.emri)}</h2>
                <p class="ud-header__email">${escapeHtml(u.email)}</p>
                <div class="ud-header__badges">
                    <span class="db-badge db-badge--${roleClass}">${u.roli}</span>
                    <span class="db-badge db-badge--${statusClass}">${u.statusi_llogarise}</span>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
<div class="ud-stats">
    <div class="ud-stat-card ud-stat-card--clickable" onclick="loadUserApplications(${u.id_perdoruesi}, '${escapeHtml(u.emri)}')">
        <div class="ud-stat-card__value">${u.total_aplikime}</div>
        <div class="ud-stat-card__label">Aplikime</div>
        <div class="ud-stat-card__hint">Shiko →</div>
    </div>
    <div class="ud-stat-card ud-stat-card--clickable" onclick="loadUserRequests(${u.id_perdoruesi}, '${escapeHtml(u.emri)}')">
        <div class="ud-stat-card__value">${u.total_kerkesa}</div>
        <div class="ud-stat-card__label">Kërkesa</div>
        <div class="ud-stat-card__hint">Shiko →</div>
    </div>
    <div class="ud-stat-card">
        <div class="ud-stat-card__value">${u.total_evente || 0}</div>
        <div class="ud-stat-card__label">Evente</div>
    </div>
    <div class="ud-stat-card">
        <div class="ud-stat-card__value">${formatDate(u.krijuar_me)}</div>
        <div class="ud-stat-card__label">Regjistruar më</div>
    </div>
</div>

<!-- User Activity Section (injected by click) -->


        ${isDeactivated && u.deaktivizuar_me ? `
            <div class="ud-deactivation-notice">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                <span>Llogaria u çaktivizua më <strong>${formatDate(u.deaktivizuar_me)}</strong></span>
            </div>` : ''}

        <!-- Action Cards Grid -->
        <div class="ud-actions-grid">

            <!-- Change Role Card -->
            <div class="ud-card">
                <div class="ud-card__header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <h4>Ndrysho Rolin</h4>
                </div>
                <p class="ud-card__desc">Roli aktual: <strong>${u.roli}</strong></p>
                <div class="ud-card__body">
                    <select id="ud-role-select" class="ud-select">
                        <option value="Admin" ${u.roli === 'Admin' ? 'selected' : ''}>Admin</option>
                        <option value="Vullnetar" ${u.roli === 'Vullnetar' ? 'selected' : ''}>Vullnetar</option>
                    </select>
                    <button class="db-btn db-btn--primary" onclick="changeUserRoleFromDetail(${u.id_perdoruesi})">
                        Ruaj Rolin
                    </button>
                </div>
            </div>

            <!-- Account Status Card -->
            <div class="ud-card ud-card--full">
                <div class="ud-card__header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                    <h4>Statusi i Llogarisë</h4>
                </div>
                <p class="ud-card__desc">Menaxhoni statusin e llogarisë.</p>
                <div class="ud-card__body ud-card__body--row">
                    ${isActive ? `
                        <button class="db-btn db-btn--warning" onclick="toggleBlock(${u.id_perdoruesi}, 'block'); setTimeout(() => openUserDetail(${u.id_perdoruesi}), 500)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                            Blloko
                        </button>
                        <button class="db-btn db-btn--danger" onclick="deactivateUser(${u.id_perdoruesi})">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                            Çaktivizo Llogarinë
                        </button>
                    ` : isBlocked ? `
                        <button class="db-btn db-btn--success" onclick="toggleBlock(${u.id_perdoruesi}, 'unblock'); setTimeout(() => openUserDetail(${u.id_perdoruesi}), 500)">
                            Zhblloko
                        </button>
                        <button class="db-btn db-btn--danger" onclick="deactivateUser(${u.id_perdoruesi})">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                            Çaktivizo Llogarinë
                        </button>
                    ` : `
                        <button class="db-btn db-btn--success" onclick="reactivateUser(${u.id_perdoruesi})">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                            Riaktivizo Llogarinë
                        </button>
                    `}
                </div>
            </div>
        </div>
    `;
};
// ── User Activity: shfaq aplikimet ose kërkesat e një përdoruesi kur shtypen stat cards ──

window.loadUserApplications = async function(userId, userName) {
    showUserActivityModal('Duke ngarkuar aplikimet…');

    const json = await apiCall(`applications.php?action=list&page=1&limit=100`);
    if (!json.success) return;

    const apps = json.data.applications.filter(a => a.id_perdoruesi == userId);

    let body = '';
    if (apps.length === 0) {
        body = '<p style="color:#6b7a8d;padding:20px 0;text-align:center;">Nuk ka aplikime.</p>';
    } else {
        body = '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>Eventi</th><th>Data e Eventit</th><th>Statusi</th><th>Aplikuar më</th></tr></thead><tbody>';
        apps.forEach(a => {
            const statusClass = a.statusi === 'Pranuar' ? 'active' : a.statusi === 'Refuzuar' ? 'blocked' : 'pending';
            body += `<tr>
                <td><strong>${escapeHtml(a.eventi_titulli)}</strong></td>
                <td>${formatDate(a.eventi_data)}</td>
                <td><span class="db-badge db-badge--${statusClass}">${escapeHtml(a.statusi)}</span></td>
                <td>${formatDate(a.aplikuar_me)}</td>
            </tr>`;
        });
        body += '</tbody></table></div>';
    }

    updateUserActivityModal(`Aplikimet e ${escapeHtml(userName)}`, body);
};

window.loadUserRequests = async function(userId, userName) {
    showUserActivityModal('Duke ngarkuar kërkesat…');

    const json = await apiCall(`help_requests.php?action=list&user_id=${userId}&limit=100`);
    if (!json.success) return;

    const requests = json.data.requests;

    let body = '';
    if (requests.length === 0) {
        body = '<p style="color:#6b7a8d;padding:20px 0;text-align:center;">Nuk ka kërkesa.</p>';
    } else {
        body = '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>Titulli</th><th>Tipi</th><th>Statusi</th><th>Data</th></tr></thead><tbody>';
        requests.forEach(r => {
            const tipClass = r.tipi === 'Kërkesë' ? 'request' : 'offer';
            const statClass = r.statusi === 'Open' ? 'open' : 'closed';
            body += `<tr>
                <td><a href="/TiranaSolidare/views/help_requests.php?id=${r.id_kerkese_ndihme}" target="_blank" style="color:var(--db-primary);font-weight:600;">${escapeHtml(r.titulli)}</a></td>
                <td><span class="db-badge db-badge--${tipClass}">${r.tipi === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj'}</span></td>
                <td><span class="db-badge db-badge--${statClass}">${r.statusi}</span></td>
                <td>${formatDate(r.krijuar_me)}</td>
            </tr>`;
        });
        body += '</tbody></table></div>';
    }

    updateUserActivityModal(`Kërkesat e ${escapeHtml(userName)}`, body);
};

function showUserActivityModal(loadingText) {
    const existing = document.getElementById('ud-activity-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'ud-activity-modal';
    modal.style.cssText = `position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);`;
    modal.innerHTML = `
        <div id="ud-activity-modal-inner" style="background:#fff;border-radius:16px;width:100%;max-width:800px;max-height:80vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2);display:flex;flex-direction:column;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid #e4e8ee;position:sticky;top:0;background:#fff;z-index:1;">
                <h3 id="ud-activity-modal-title" style="margin:0;font-family:'Bitter',serif;font-size:1.1rem;font-weight:700;color:#003229;">Duke ngarkuar…</h3>
                <button onclick="document.getElementById('ud-activity-modal').remove()" style="background:none;border:none;cursor:pointer;font-size:1.4rem;color:#6b7280;line-height:1;">×</button>
            </div>
            <div id="ud-activity-modal-body" style="padding:20px 24px;">
                <div class="db-loading">${loadingText}</div>
            </div>
        </div>`;

    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
    document.body.appendChild(modal);
}

function updateUserActivityModal(title, bodyHtml) {
    const titleEl = document.getElementById('ud-activity-modal-title');
    const bodyEl = document.getElementById('ud-activity-modal-body');
    if (titleEl) titleEl.textContent = title;
    if (bodyEl) bodyEl.innerHTML = bodyHtml;
}



// Change role from detail panel
window.changeUserRoleFromDetail = async function (userId) {
    const sel = document.getElementById('ud-role-select');
    if (!sel) return;
    const newRole = sel.value;

    const json = await apiCall(`users.php?action=change_role&id=${userId}`, 'PUT', { roli: newRole });
    dbToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');

    if (json.success) {
        // Refresh detail
        setTimeout(() => openUserDetail(userId), 300);
    }
};

// Deactivate (soft-delete)
window.deactivateUser = async function (userId) {
    if (!confirm('Çaktivizo këtë llogari? (Soft-delete — të dhënat do të ruhen si në Facebook/Instagram)')) return;

    const json = await apiCall(`users.php?action=deactivate&id=${userId}`, 'PUT');
    dbToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');

    if (json.success) {
        setTimeout(() => openUserDetail(userId), 300);
    }
};

// Reactivate
window.reactivateUser = async function (userId) {
    if (!confirm('Riaktivizo këtë llogari?')) return;

    const json = await apiCall(`users.php?action=reactivate&id=${userId}`, 'PUT');
    dbToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');

    if (json.success) {
        setTimeout(() => {
            openUserDetail(userId);
            loadUsers();
        }, 300);
    }
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Help Requests
// ═══════════════════════════════════════════════════════

window._requestFilters = window._requestFilters || {
    status: 'all',
    tipi: 'all',
    search: ''
};

window.loadHelpRequests = async function (page = 1) {
    const container = document.getElementById('help-request-list');
    if (!container) return;

    const filters = window._requestFilters;
    const wasSearchFocused = document.activeElement?.classList.contains('db-filter-select') &&
                             document.activeElement?.type === 'text';

    const json = await apiCall(`help_requests.php?action=list&page=1&limit=1000`);
    if (!json.success) return;

    let requests = json.data.requests;

    // ── Rendit: Open para, Closed pas ──
    requests.sort((a, b) => {
        if (a.statusi === 'Open' && b.statusi !== 'Open') return -1;
        if (a.statusi !== 'Open' && b.statusi === 'Open') return 1;
        return new Date(b.krijuar_me) - new Date(a.krijuar_me);
    });

    // ── Filter: statusi ──
    if (filters.status === 'open') {
        requests = requests.filter(r => r.statusi === 'Open');
    } else if (filters.status === 'closed') {
        requests = requests.filter(r => r.statusi === 'Closed');
    }

    // ── Filter: tipi ──
    if (filters.tipi !== 'all') {
        requests = requests.filter(r => r.tipi === filters.tipi);
    }

    // ── Filter: search ──
    if (filters.search.trim() !== '') {
        const q = filters.search.toLowerCase();
        requests = requests.filter(r => r.titulli.toLowerCase().includes(q));
    }

    // ── Pagination client-side ──
    const limit = 10;
    const totalFiltered = requests.length;
    const totalPages = Math.ceil(totalFiltered / limit) || 1;
    const offset = (page - 1) * limit;
    const pageRequests = requests.slice(offset, offset + limit);

    let html = `
    <div class="db-filter-bar">
        <div class="db-filter-group">
            <label>Statusi</label>
            <select class="db-filter-select" onchange="window._requestFilters.status = this.value; loadHelpRequests(1)">
                <option value="all"    ${filters.status === 'all'    ? 'selected' : ''}>Të gjitha</option>
                <option value="open"   ${filters.status === 'open'   ? 'selected' : ''}>Të hapura</option>
                <option value="closed" ${filters.status === 'closed' ? 'selected' : ''}>Të mbyllura</option>
            </select>
        </div>
        <div class="db-filter-group">
            <label>Tipi</label>
            <select class="db-filter-select" onchange="window._requestFilters.tipi = this.value; loadHelpRequests(1)">
                <option value="all"     ${filters.tipi === 'all'     ? 'selected' : ''}>Të gjitha</option>
                <option value="Kërkesë" ${filters.tipi === 'Kërkesë' ? 'selected' : ''}>Kërkoj ndihmë</option>
                <option value="Ofertë"  ${filters.tipi === 'Ofertë'  ? 'selected' : ''}>Dua të ndihmoj</option>
            </select>
        </div>
        <div class="db-filter-group">
            <input
                type="text"
                class="db-filter-select"
                placeholder="Kërko titull..."
                value="${escapeHtml(filters.search)}"
                oninput="window._requestFilters.search = this.value; clearTimeout(window._reqSearchTimeout); window._reqSearchTimeout = setTimeout(() => { loadHelpRequests(1); }, 350)"
                style="min-width:180px;"
            >
        </div>
        <span class="db-filter-count"><strong>${totalFiltered}</strong> kërkesa</span>
    </div>`;

    if (pageRequests.length === 0) {
        html += '<div class="db-loading">Nuk ka kërkesa për këto filtra.</div>';
        container.innerHTML = html;
        return;
    }

    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>Titulli</th><th>Tipi</th><th>Statusi</th><th>Nga</th><th>Data</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    pageRequests.forEach(r => {
        const tipClass = r.tipi === 'Kërkesë' ? 'request' : 'offer';
        const statClass = r.statusi === 'Open' ? 'open' : 'closed';

        html += `<tr>
            <td><strong>${escapeHtml(r.titulli)}</strong></td>
            <td><span class="db-badge db-badge--${tipClass}">${r.tipi === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj'}</span></td>
            <td><span class="db-badge db-badge--${statClass}">${r.statusi}</span></td>
            <td>${escapeHtml(r.krijuesi_emri || '—')}</td>
            <td>${formatDate(r.krijuar_me)}</td>
            <td>
                <div class="db-table__actions">
                    <a href="/TiranaSolidare/views/help_requests.php?id=${r.id_kerkese_ndihme}" class="db-btn db-btn--info db-btn--sm" target="_blank">Shiko</a>
                    ${r.statusi === 'Open' ?
                        `<button class="db-btn db-btn--warning db-btn--sm" onclick="closeRequest(${r.id_kerkese_ndihme})">Mbyll</button>` : ''}
                </div>
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';

    if (totalPages > 1) {
        html += dbPagination(page, totalPages, 'loadHelpRequests');
    }

    container.innerHTML = html;

    if (wasSearchFocused) {
        const inp = container.querySelector('input[type="text"]');
        if (inp) {
            inp.focus();
            inp.setSelectionRange(inp.value.length, inp.value.length);
        }
    }
};

// Close request action
window.closeRequest = async function (id) {
    if (!confirm('Mbyll këtë kërkesë?')) return;
    const json = await apiCall(`help_requests.php?action=close&id=${id}`, 'PUT');
    dbToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    loadHelpRequests();
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Notifications
// ═══════════════════════════════════════════════════════

window.loadNotifications = async function () {
    const container = document.getElementById('notification-list');
    if (!container) return;

    const json = await apiCall('notifications.php?action=list&limit=20');
    if (!json.success) return;

    const notifs = json.data.notifications;

    if (!notifs || notifs.length === 0) {
        container.innerHTML = `
            <div class="db-notif-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                <p>Nuk keni njoftime.</p>
            </div>`;
        return;
    }

    let html = '<div class="db-notif-list">';
    notifs.forEach(n => {
        const unread = !n.is_read;
        html += `<div class="db-notif ${unread ? 'db-notif--unread' : 'db-notif--read'}">
            <div class="db-notif__dot"></div>
            <div class="db-notif__body">
                <p class="db-notif__msg">${escapeHtml(n.mesazhi)}</p>
                <span class="db-notif__time">${formatDate(n.krijuar_me)}</span>
            </div>
            <div class="db-notif__actions">
                ${unread ? `<button class="db-btn db-btn--success db-btn--sm" onclick="markRead(${n.id_njoftimi})" title="Shëno si të lexuar">✓</button>` : ''}
                <button class="db-btn db-btn--danger db-btn--sm" onclick="deleteNotif(${n.id_njoftimi})" title="Fshi">✕</button>
            </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Volunteer Event List
// ═══════════════════════════════════════════════════════

window.renderEventList = function (data) {
    const container = document.getElementById('event-list');
    if (!container) return;

    if (data.events.length === 0) {
        container.innerHTML = '<div class="db-loading">Nuk ka evente për momentin.</div>';
        return;
    }

    let html = '<div class="db-event-grid">';
    data.events.forEach((ev, i) => {
        html += `<div class="db-event-card" style="animation-delay:${i * 0.04}s">
            ${ev.banner ? `<img src="${escapeHtml(ev.banner)}" class="db-event-card__img" alt="">` : ''}
            <div class="db-event-card__body">
                <h4 class="db-event-card__title">${escapeHtml(ev.titulli)}</h4>
                <p class="db-event-card__desc">${escapeHtml((ev.pershkrimi || '').substring(0, 120))}...</p>
                <div class="db-event-card__meta">
                    <span>📅 ${formatDate(ev.data)}</span>
                    <span>📍 ${escapeHtml(ev.vendndodhja)}</span>
                </div>
                ${ev.kategoria_emri ? `<span class="db-badge db-badge--vol">${escapeHtml(ev.kategoria_emri)}</span>` : ''}
            </div>
            <div class="db-event-card__footer">
                <button class="db-btn db-btn--primary" style="width:100%" onclick="applyForEvent(${ev.id_eventi})">Apliko</button>
            </div>
        </div>`;
    });
    html += '</div>';

    if (data.total_pages > 1) {
        html += dbPagination(data.page, data.total_pages, 'fetchEvents');
    }

    container.innerHTML = html;
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Application List (Volunteer)
// ═══════════════════════════════════════════════════════

window.renderApplicationList = function (data) {
    const container = document.getElementById('application-list');
    if (!container) return;

    if (data.applications.length === 0) {
        container.innerHTML = '<div class="db-loading">Nuk keni aplikime ende.</div>';
        return;
    }

    let html = `<div class="db-table-count">Gjithsej: <strong>${data.applications.length}</strong> aplikime</div>`;
    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>Eventi</th><th>Data</th><th>Statusi</th><th>Aplikuar më</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    data.applications.forEach(app => {
        const statusClass = app.statusi === 'Pranuar' ? 'active'
            : app.statusi === 'Refuzuar' ? 'blocked' : 'pending';

        html += `<tr>
            <td><strong>${escapeHtml(app.eventi_titulli)}</strong></td>
            <td>${formatDate(app.eventi_data)}</td>
            <td><span class="db-badge db-badge--${statusClass}">${escapeHtml(app.statusi)}</span></td>
            <td>${formatDate(app.aplikuar_me)}</td>
            <td>${app.statusi === 'Në pritje'
                ? `<button class="db-btn db-btn--danger db-btn--sm" onclick="withdrawApplication(${app.id_aplikimi})">Tërhiq</button>`
                : '<span style="color:#b0b8c4">—</span>'}</td>
        </tr>`;
    });
    html += '</tbody></table></div>';

    if (data.total_pages > 1) {
        html += dbPagination(data.page, data.total_pages, 'fetchMyApplications');
    }

    container.innerHTML = html;
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Toast (styled)
// ═══════════════════════════════════════════════════════

function dbToast(message, type = 'info') {
    const container = document.getElementById('db-toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `db-toast db-toast--${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        toast.style.transition = '0.3s';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}


// ═══════════════════════════════════════════════════════
//  SHARED: Pagination renderer
// ═══════════════════════════════════════════════════════

function dbPagination(current, totalPages, callbackName) {
    let html = '<div class="db-pagination">';

    // Limit visible page buttons to a window of 7
    const maxVisible = 7;
    let start = Math.max(1, current - Math.floor(maxVisible / 2));
    let end = Math.min(totalPages, start + maxVisible - 1);
    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    if (current > 1) {
        html += `<button class="db-pagination__btn" onclick="${callbackName}(${current - 1})">&laquo;</button>`;
    }
    if (start > 1) {
        html += `<button class="db-pagination__btn" onclick="${callbackName}(1)">1</button>`;
        if (start > 2) html += '<span class="db-pagination__ellipsis">&hellip;</span>';
    }
    for (let i = start; i <= end; i++) {
        html += `<button class="db-pagination__btn ${i === current ? 'active' : ''}"
                    onclick="${callbackName}(${i})">${i}</button>`;
    }
    if (end < totalPages) {
        if (end < totalPages - 1) html += '<span class="db-pagination__ellipsis">&hellip;</span>';
        html += `<button class="db-pagination__btn" onclick="${callbackName}(${totalPages})">${totalPages}</button>`;
    }
    if (current < totalPages) {
        html += `<button class="db-pagination__btn" onclick="${callbackName}(${current + 1})">&raquo;</button>`;
    }
    html += '</div>';
    return html;
};

//CHARTS
async function renderDashboardCharts(overviewData) {
    if (typeof Chart === 'undefined') return;

    const anchor = document.getElementById('dashboard-substats');
    if (!anchor) return;

    const existing = document.getElementById('dashboard-charts');
    if (existing) existing.remove();

    const PRIMARY = '#00715D';
    const ACCENT = '#E17254';
    const PRIMARY_SOFT = 'rgba(0,113,93,0.15)';
    const ACCENT_SOFT = 'rgba(225,114,84,0.16)';
    const PRIMARY_TINT = '#e8f5f1';
    const ACCENT_TINT = '#fdf0eb';
    const TEXT = '#0F172A';
    const MUTED = '#64748B';
    const GRID = 'rgba(15,23,42,0.08)';

    const monthKey = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    const lastNMonths = (n = 6) => {
        const out = [];
        const now = new Date();
        for (let i = n - 1; i >= 0; i--) out.push(monthKey(new Date(now.getFullYear(), now.getMonth() - i, 1)));
        return out;
    };
    const toMap = (rows = []) => {
        const m = new Map();
        rows.forEach(r => m.set(r.muaji, Number.parseInt(r.total, 10) || 0));
        return m;
    };
    const sum = (arr = []) => arr.reduce((a, b) => a + (Number(b) || 0), 0);

    const g = (ctx, area, c1, c2) => {
        const grad = ctx.createLinearGradient(0, area.top, 0, area.bottom);
        grad.addColorStop(0, c1);
        grad.addColorStop(1, c2);
        return grad;
    };

    let monthly = {};
    try {
        const monthlyJson = await apiCall('stats.php?action=monthly');
        monthly = monthlyJson?.success ? (monthlyJson.data || {}) : {};
    } catch (_) {
        monthly = {};
    }

    const appsMap = toMap(monthly.monthly_apps);
    const reqMap = toMap(monthly.monthly_requests);
    const eventsMap = toMap(monthly.monthly_events);

    const allMonthKeys = Array.from(new Set([
        ...appsMap.keys(),
        ...reqMap.keys(),
        ...eventsMap.keys()
    ])).sort();

    const months = allMonthKeys.length ? allMonthKeys : lastNMonths(6);
    const labels = months.map(k => {
        const [y, m] = k.split('-').map(Number);
        return new Date(y, m - 1, 1).toLocaleDateString('sq-AL', { month: 'short', year: '2-digit' });
    });

    const appsData = months.map(k => appsMap.get(k) || 0);
    const reqData = months.map(k => reqMap.get(k) || 0);
    const eventsData = months.map(k => eventsMap.get(k) || 0);
    const categoryRows = monthly.apps_by_category || [];

    const appStats = overviewData?.applications || {};
    const statusData = [
        Number.parseInt(appStats.ne_pritje, 10) || 0,
        Number.parseInt(appStats.pranuar, 10) || 0,
        Number.parseInt(appStats.refuzuar, 10) || 0
    ];

    const totals = {
        aplikime: sum(appsData),
        kerkesa: sum(reqData),
        evente: sum(eventsData),
        statuse: sum(statusData),
        kategori: sum(categoryRows.map(c => Number.parseInt(c.total, 10) || 0))
    };

    const wrapper = document.createElement('section');
    wrapper.id = 'dashboard-charts';
    wrapper.style.cssText = `
        display:grid;
        grid-template-columns:minmax(0,1.45fr) minmax(0,1fr);
        gap:20px;
        margin-top:20px;
        padding:0 24px 28px;
    `;

    const createCard = (title, totalText, chipBg) => {
        const card = document.createElement('article');
        card.className = 'db-overview-card';
        card.style.cssText = `
            display:flex;
            flex-direction:column;
            min-height:320px;
            padding:16px;
            border-radius:16px;
        `;

        const head = document.createElement('div');
        head.style.cssText = `
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:10px;
            gap:10px;
        `;

        const h = document.createElement('h4');
        h.textContent = title;
        h.style.cssText = `margin:0;color:${TEXT};font-size:14px;font-weight:700;letter-spacing:.2px;`;
        h.style.borderBottom = 'none';

        const total = document.createElement('span');
        total.textContent = totalText;
        total.style.cssText = `
            font-size:12px;
            font-weight:600;
            color:${MUTED};
            background:${chipBg};
            border:1px solid rgba(15,23,42,.08);
            border-radius:999px;
            padding:4px 10px;
            white-space:nowrap;
        `;

        const body = document.createElement('div');
        body.style.cssText = 'position:relative;flex:1;min-height:240px;';
        const canvas = document.createElement('canvas');
        body.appendChild(canvas);

        head.appendChild(h);
        head.appendChild(total);
        card.appendChild(head);
        card.appendChild(body);

        return { card, canvas };
    };

    const tooltip = {
        backgroundColor: '#0B1220',
        titleColor: '#F8FAFC',
        bodyColor: '#E2E8F0',
        borderColor: 'rgba(255,255,255,0.14)',
        borderWidth: 1,
        cornerRadius: 12,
        padding: 12,
        displayColors: true,
        titleFont: { weight: '700', size: 12 },
        bodyFont: { size: 12 }
    };

    const axis = {
        x: {
            grid: { display: false, drawBorder: false },
            ticks: { color: MUTED, font: { size: 11, weight: '600' } },
            border: { display: false }
        },
        y: {
            beginAtZero: true,
            grid: { color: GRID, drawBorder: false },
            ticks: { color: MUTED, precision: 0, font: { size: 11, weight: '600' } },
            border: { display: false }
        }
    };

    Chart.defaults.font.family = "Inter, Segoe UI, Roboto, Arial, sans-serif";
    Chart.defaults.color = MUTED;

    // 1) Aplikime Mujore 
    const c1 = createCard('Aplikime Mujore', `Totali: ${totals.aplikime}`, PRIMARY_TINT);
    c1.card.style.gridColumn = '1';
    c1.card.style.gridRow = '1';
    wrapper.appendChild(c1.card);

    new Chart(c1.canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Aplikime',
                data: appsData,
                borderColor: PRIMARY,
                backgroundColor: (ctx) => {
                    const ca = ctx.chart.chartArea;
                    if (!ca) return PRIMARY_SOFT;
                    return g(ctx.chart.ctx, ca, 'rgba(0,113,93,0.30)', 'rgba(0,113,93,0.03)');
                },
                fill: true,
                tension: 0.42,
                borderWidth: 2.8,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBackgroundColor: PRIMARY,
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip },
            scales: axis
        }
    });

    // 2) Statuset e Aplikimeve 
    const c2 = createCard('Statuset e Aplikimeve', `Totali: ${totals.statuse}`, ACCENT_TINT);
    c2.card.style.gridColumn = '2';
    c2.card.style.gridRow = '1';
    wrapper.appendChild(c2.card);

    new Chart(c2.canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Në pritje', 'Pranuar', 'Refuzuar'],
            datasets: [{
                data: statusData,
                backgroundColor: [
                    'rgba(225,114,84,0.75)',
                    'rgba(0,113,93,0.90)',
                    'rgba(225,114,84,0.50)'
                ],
                borderColor: '#fff',
                borderWidth: 3,
                spacing: 3,
                hoverOffset: 8
            }]
        },
        options: {
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                tooltip,
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        padding: 14,
                        color: TEXT,
                        font: { size: 11, weight: '600' }
                    }
                }
            }
        }
    });

    // 3) Kërkesa për Ndihmë vs Evente (poshtë majtas)
    const c3 = createCard('Kërkesa vs Evente', `Kërkesa: ${totals.kerkesa} • Evente: ${totals.evente}`, ACCENT_TINT);
    c3.card.style.gridColumn = '1';
    c3.card.style.gridRow = '2';
    wrapper.appendChild(c3.card);

    new Chart(c3.canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Kërkesa',
                    data: reqData,
                    borderColor: ACCENT,
                    backgroundColor: (ctx) => {
                        const ca = ctx.chart.chartArea;
                        if (!ca) return ACCENT_SOFT;
                        return g(ctx.chart.ctx, ca, 'rgba(225,114,84,0.24)', 'rgba(225,114,84,0.02)');
                    },
                    fill: true,
                    tension: 0.38,
                    borderWidth: 2.4,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: ACCENT,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                },
                {
                    label: 'Evente',
                    data: eventsData,
                    borderColor: PRIMARY,
                    backgroundColor: (ctx) => {
                        const ca = ctx.chart.chartArea;
                        if (!ca) return PRIMARY_SOFT;
                        return g(ctx.chart.ctx, ca, 'rgba(0,113,93,0.20)', 'rgba(0,113,93,0.02)');
                    },
                    fill: true,
                    tension: 0.38,
                    borderWidth: 2.4,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: PRIMARY,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                tooltip,
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        padding: 12,
                        color: TEXT,
                        font: { size: 11, weight: '600' }
                    }
                }
            },
            scales: axis
        }
    });

    // 4) Aplikime sipas Kategorisë (poshtë djathtas)
    const c4 = createCard('Aplikime sipas Kategorisë', `Totali: ${totals.kategori}`, PRIMARY_TINT);
    c4.card.style.gridColumn = '2';
    c4.card.style.gridRow = '2';
    wrapper.appendChild(c4.card);

    new Chart(c4.canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: categoryRows.map(c => c.emri),
            datasets: [{
                label: 'Aplikime',
                data: categoryRows.map(c => Number.parseInt(c.total, 10) || 0),
                backgroundColor: categoryRows.map((_, i) => i % 2 === 0 ? 'rgba(0,113,93,0.88)' : 'rgba(225,114,84,0.82)'),
                borderColor: categoryRows.map((_, i) => i % 2 === 0 ? PRIMARY : ACCENT),
                borderWidth: 1,
                borderRadius: 12,
                borderSkipped: false,
                maxBarThickness: 36
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip },
            scales: {
                ...axis,
                x: {
                    ...axis.x,
                    ticks: { ...axis.x.ticks, maxRotation: 0, minRotation: 0, autoSkip: true }
                }
            }
        }
    });

    anchor.insertAdjacentElement('afterend', wrapper);
}

// ═══════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    // Load category dropdown for event creation form
    loadCategoryDropdown();

    // Re-trigger data loads with new renderers
    // (main.js DOMContentLoaded already fired, so these are
    //  re-invoked with our overridden functions)
    setTimeout(() => {
        loadDashboardStats();
        loadAdminEvents();
        loadUsers();
        loadNotifications();
        loadHelpRequests();
    }, 100);

    // Rikthe tab-in nga URL pas refresh
    if (location.hash) {
        const panelId = location.hash.replace('#', '');
        const navBtn = document.querySelector(`[data-panel="${panelId}"]`);
        if (document.getElementById(`panel-${panelId}`)) {
            switchPanel(panelId, navBtn);
        }
     }
});