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
    if (w) w.style.display = w.style.display === 'none' ? 'block' : 'none';
}


// ── Category dropdown loader ─────────────────────────

async function loadCategoryDropdown() {
    const sel = document.getElementById('event-category-select');
    if (!sel) return;
    try {
        const json = await apiCall('categories.php?action=list');
        if (json.success) {
            json.data.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_kategoria;
                opt.textContent = c.emri;
                sel.appendChild(opt);
            });
        }
    } catch (e) { /* silent */ }
}


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Dashboard Stats Renderer
// ═══════════════════════════════════════════════════════

// Override the loadDashboardStats from main.js
const _origLoadDashboardStats = typeof loadDashboardStats === 'function' ? loadDashboardStats : null;

window.loadDashboardStats = async function () {
    const container = document.getElementById('dashboard-stats');
    const subContainer = document.getElementById('dashboard-substats');
    if (!container) return;

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

    // Sub-stats grid
    if (subContainer) {
        subContainer.innerHTML = `
            <div class="db-overview-card">
                <h4>Aplikime sipas Statusit</h4>
                <ul class="db-progress-list">
                    <li class="db-progress-item">
                        <span class="db-progress-item__label">Në pritje</span>
                        <span class="db-badge db-badge--pending">${d.applications.ne_pritje || 0}</span>
                    </li>
                    <li class="db-progress-item">
                        <span class="db-progress-item__label">Pranuar</span>
                        <span class="db-badge db-badge--active">${d.applications.pranuar || 0}</span>
                    </li>
                    <li class="db-progress-item">
                        <span class="db-progress-item__label">Refuzuar</span>
                        <span class="db-badge db-badge--blocked">${d.applications.refuzuar || 0}</span>
                    </li>
                </ul>
            </div>
            <div class="db-overview-card">
                <h4>Top Kategoritë</h4>
                <ul class="db-category-list">
                    ${(d.top_categories || []).map(c => `
                        <li class="db-category-item">
                            <span class="db-category-item__name">${escapeHtml(c.emri)}</span>
                            <span class="db-category-item__count">${c.event_count}</span>
                        </li>`).join('')}
                </ul>
            </div>`;
    }
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Admin Event List
// ═══════════════════════════════════════════════════════

window.loadAdminEvents = async function (page = 1) {
    const container = document.getElementById('admin-event-list');
    if (!container) return;

    const json = await apiCall(`events.php?action=list&page=${page}&limit=10`);
    if (!json.success) return;

    const { events, total, total_pages } = json.data;

    let html = `<div class="db-table-count">Gjithsej: <strong>${total}</strong> evente</div>`;
    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>ID</th><th>Titulli</th><th>Kategoria</th><th>Data</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    events.forEach(ev => {
        html += `<tr>
            <td><strong>#${ev.id_eventi}</strong></td>
            <td>${escapeHtml(ev.titulli)}</td>
            <td>${ev.kategoria_emri ? `<span class="db-badge db-badge--vol">${escapeHtml(ev.kategoria_emri)}</span>` : '<span style="color:#b0b8c4">—</span>'}</td>
            <td>${formatDate(ev.data)}</td>
            <td>
                <div class="db-table__actions">
                    <button class="db-btn db-btn--warning db-btn--sm" onclick="editEventPrompt(${ev.id_eventi}, '${escapeHtml(ev.titulli).replace(/'/g, "\\'")}')">Ndrysho</button>
                    <button class="db-btn db-btn--danger db-btn--sm" onclick="deleteEvent(${ev.id_eventi})">Fshi</button>
                    <button class="db-btn db-btn--info db-btn--sm" onclick="viewEventApps(${ev.id_eventi})">Aplikime</button>
                </div>
            </td>
        </tr>`;
    });
    html += '</tbody></table></div>';

    if (total_pages > 1) {
        html += dbPagination(page, total_pages, 'loadAdminEvents');
    }

    container.innerHTML = html;
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Event Applications
// ═══════════════════════════════════════════════════════

window.viewEventApps = async function (eventId) {
    const container = document.getElementById('event-applications');
    const card = document.getElementById('event-applications-card');
    if (!container) return;
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

window.loadUsers = async function (page = 1) {
    const container = document.getElementById('admin-user-list');
    if (!container) return;

    const json = await apiCall(`users.php?action=list&page=${page}&limit=15`);
    if (!json.success) return;

    const { users, total, total_pages } = json.data;

    let html = `<div class="db-table-count">Gjithsej: <strong>${total}</strong> përdorues</div>`;
    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>ID</th><th>Emri</th><th>Email</th><th>Roli</th><th>Statusi</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    users.forEach(u => {
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
                        ? `<button class="db-btn db-btn--success db-btn--sm" onclick="toggleBlock(${u.id_perdoruesi}, 'unblock')">
                             Zhblloko</button>`
                        : isDeactivated
                        ? `<button class="db-btn db-btn--success db-btn--sm" onclick="reactivateUser(${u.id_perdoruesi})">
                             Riaktivizo</button>`
                        : `<button class="db-btn db-btn--warning db-btn--sm" onclick="toggleBlock(${u.id_perdoruesi}, 'block')">
                             Blloko</button>`}
                </div>
            </td>
        </tr>`;
    });
    html += '</tbody></table></div>';

    if (total_pages > 1) {
        html += dbPagination(page, total_pages, 'loadUsers');
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
            <div class="ud-stat-card">
                <div class="ud-stat-card__value">${u.total_aplikime}</div>
                <div class="ud-stat-card__label">Aplikime</div>
            </div>
            <div class="ud-stat-card">
                <div class="ud-stat-card__value">${u.total_kerkesa}</div>
                <div class="ud-stat-card__label">Kërkesa</div>
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

            <!-- Reset Password Card -->
            <div class="ud-card">
                <div class="ud-card__header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <h4>Rivendos Fjalëkalimin</h4>
                </div>
                <p class="ud-card__desc">Vendosni fjalëkalim të ri nëse përdoruesi ka harruar fjalëkalimin.</p>
                <div class="ud-card__body">
                    <div class="ud-password-wrap">
                        <input type="password" id="ud-new-password" class="ud-input" placeholder="Fjalëkalimi i ri (min. 6 karaktere)" minlength="6">
                        <button class="ud-password-toggle" onclick="togglePasswordVisibility()" type="button" title="Shfaq/Fshih">
                            <svg id="ud-eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                    <button class="db-btn db-btn--primary" onclick="adminResetPassword(${u.id_perdoruesi})">
                        Rivendos Fjalëkalimin
                    </button>
                </div>
            </div>

            <!-- Account Status Card -->
            <div class="ud-card ud-card--full">
                <div class="ud-card__header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                    <h4>Statusi i Llogarisë</h4>
                </div>
                <p class="ud-card__desc">Menaxhoni statusin e llogarisë. Çaktivizimi (soft-delete) ruan të dhënat si në Facebook/Instagram.</p>
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


// Toggle password field visibility
window.togglePasswordVisibility = function () {
    const inp = document.getElementById('ud-new-password');
    if (!inp) return;
    inp.type = inp.type === 'password' ? 'text' : 'password';
};

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

// Admin password reset
window.adminResetPassword = async function (userId) {
    const inp = document.getElementById('ud-new-password');
    if (!inp) return;
    const pw = inp.value.trim();

    if (pw.length < 6) {
        dbToast('Fjalëkalimi duhet të ketë të paktën 6 karaktere.', 'danger');
        return;
    }

    if (!confirm('Jeni të sigurt që doni të rivendosni fjalëkalimin e këtij përdoruesi?')) return;

    const json = await apiCall(`users.php?action=reset_password&id=${userId}`, 'PUT', { password: pw });
    dbToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');

    if (json.success) {
        inp.value = '';
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

window.loadHelpRequests = async function (page = 1, filters = {}) {
    const container = document.getElementById('help-request-list');
    if (!container) return;

    const params = new URLSearchParams({ action: 'list', page, limit: 10, ...filters });
    const json = await apiCall(`help_requests.php?${params}`);
    if (!json.success) return;

    const { requests, total, total_pages } = json.data;

    let html = `<div class="db-table-count">Gjithsej: <strong>${total}</strong> kërkesa</div>`;

    if (requests.length === 0) {
        html += '<div class="db-loading">Nuk ka kërkesa për momentin.</div>';
        container.innerHTML = html;
        return;
    }

    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>Titulli</th><th>Tipi</th><th>Statusi</th><th>Nga</th><th>Data</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    requests.forEach(r => {
        const tipClass = r.tipi === 'Kërkesë' ? 'request' : 'offer';
        const statClass = r.statusi === 'Open' ? 'open' : 'closed';

        html += `<tr>
            <td><strong>${escapeHtml(r.titulli)}</strong></td>
            <td><span class="db-badge db-badge--${tipClass}">${escapeHtml(r.tipi)}</span></td>
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

    if (total_pages > 1) {
        html += dbPagination(page, total_pages, 'loadHelpRequests');
    }

    container.innerHTML = html;
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

window.showToast = function (message, type = 'info') {
    dbToast(message, type);
};

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
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="db-pagination__btn ${i === current ? 'active' : ''}"
                    onclick="${callbackName}(${i})">${i}</button>`;
    }
    html += '</div>';
    return html;
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
});
