/**
 * main.js
 * ---------------------------------------------------
 * Core JS utilities and admin-panel interactions
 * for Tirana Solidare.
 * ---------------------------------------------------
 */

const API = '/TiranaSolidare/api';

// ── Generic API caller ──────────────────────────────

async function apiCall(endpoint, method = 'GET', body = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json' },
    };
    if (body) opts.body = JSON.stringify(body);

    const res = await fetch(`${API}/${endpoint}`, opts);
    return res.json();
}

// ══════════════════════════════════════════════════════
//  ADMIN: Event Management
// ══════════════════════════════════════════════════════

async function loadAdminEvents(page = 1) {
    const container = document.getElementById('admin-event-list');
    if (!container) return;

    const json = await apiCall(`get_events.php?action=list&page=${page}&limit=10`);
    if (!json.success) return;

    const { events, total, total_pages } = json.data;

    let html = `<p class="text-muted">Gjithsej: ${total} evente</p>`;
    html += '<table class="table table-hover"><thead><tr>'
        + '<th>ID</th><th>Titulli</th><th>Kategoria</th><th>Data</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    events.forEach(ev => {
        html += `<tr>
            <td>${ev.id_eventi}</td>
            <td>${escapeHtml(ev.titulli)}</td>
            <td>${escapeHtml(ev.kategoria_emri || '—')}</td>
            <td>${formatDate(ev.data)}</td>
            <td>
                <button class="btn btn-sm btn-warning" onclick="editEventPrompt(${ev.id_eventi}, '${escapeHtml(ev.titulli)}')">Ndrysho</button>
                <button class="btn btn-sm btn-danger" onclick="deleteEvent(${ev.id_eventi})">Fshi</button>
                <button class="btn btn-sm btn-info" onclick="viewEventApps(${ev.id_eventi})">Aplikime</button>
            </td>
        </tr>`;
    });
    html += '</tbody></table>';

    if (total_pages > 1) {
        html += renderPagination(page, total_pages, 'loadAdminEvents');
    }

    container.innerHTML = html;
}

async function deleteEvent(id) {
    if (!confirm('Fshi këtë event?')) return;
    const json = await apiCall(`get_events.php?action=delete&id=${id}`, 'DELETE');
    showToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    loadAdminEvents();
}

async function editEventPrompt(id, currentTitle) {
    const newTitle = prompt('Titulli i ri:', currentTitle);
    if (!newTitle || newTitle === currentTitle) return;

    const json = await apiCall(`get_events.php?action=update&id=${id}`, 'PUT', { titulli: newTitle });
    showToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    loadAdminEvents();
}

// ── Create Event Form Handler ───────────────────────

function initCreateEventForm() {
    const form = document.getElementById('create-event-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const body = Object.fromEntries(fd);

        const json = await apiCall('get_events.php?action=create', 'POST', body);

        if (json.success) {
            showToast('Eventi u krijua!', 'success');
            form.reset();
            loadAdminEvents();
        } else {
            showToast(json.message || 'Gabim.', 'danger');
        }
    });
}

// ══════════════════════════════════════════════════════
//  ADMIN: Application Status Updates
// ══════════════════════════════════════════════════════

async function viewEventApps(eventId) {
    const container = document.getElementById('event-applications');
    if (!container) {
        // Fall-back: open in modal or alert
        const json = await apiCall(`applications.php?action=by_event&id=${eventId}`);
        if (!json.success) { alert(json.message); return; }
        alert(`Aplikime: ${json.data.summary.total} (Pranuar: ${json.data.summary.pranuar}, Në pritje: ${json.data.summary.ne_pritje})`);
        return;
    }

    const json = await apiCall(`applications.php?action=by_event&id=${eventId}`);
    if (!json.success) return;

    const { applications, summary } = json.data;

    let html = `<h5>Aplikime – Gjithsej: ${summary.total}</h5>
        <div class="mb-2">
            <span class="badge bg-warning">Në pritje: ${summary.ne_pritje}</span>
            <span class="badge bg-success">Pranuar: ${summary.pranuar}</span>
            <span class="badge bg-danger">Refuzuar: ${summary.refuzuar}</span>
        </div>`;

    html += '<table class="table"><thead><tr><th>Emri</th><th>Email</th><th>Statusi</th><th>Veprime</th></tr></thead><tbody>';

    applications.forEach(app => {
        html += `<tr>
            <td>${escapeHtml(app.vullnetari_emri)}</td>
            <td>${escapeHtml(app.vullnetari_email)}</td>
            <td>${escapeHtml(app.statusi)}</td>
            <td>
                <button class="btn btn-sm btn-success" onclick="updateAppStatus(${app.id_aplikimi}, 'Pranuar')">Prano</button>
                <button class="btn btn-sm btn-danger" onclick="updateAppStatus(${app.id_aplikimi}, 'Refuzuar')">Refuzo</button>
            </td>
        </tr>`;
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

async function updateAppStatus(appId, status) {
    const json = await apiCall(`applications.php?action=update_status&id=${appId}`, 'PUT', { statusi: status });
    showToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
}

// ══════════════════════════════════════════════════════
//  ADMIN: User Management
// ══════════════════════════════════════════════════════

async function loadUsers(page = 1) {
    const container = document.getElementById('admin-user-list');
    if (!container) return;

    const json = await apiCall(`update_status.php?action=list&page=${page}&limit=15`);
    if (!json.success) return;

    const { users, total, total_pages } = json.data;

    let html = `<p class="text-muted">Gjithsej: ${total} përdorues</p>`;
    html += '<table class="table"><thead><tr><th>ID</th><th>Emri</th><th>Email</th><th>Roli</th><th>Statusi</th><th>Veprime</th></tr></thead><tbody>';

    users.forEach(u => {
        const blocked = u.statusi_llogarise === 'Bllokuar';
        html += `<tr class="${blocked ? 'table-danger' : ''}">
            <td>${u.id_perdoruesi}</td>
            <td>${escapeHtml(u.emri)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td>${u.roli}</td>
            <td>${u.statusi_llogarise}</td>
            <td>
                ${blocked
                    ? `<button class="btn btn-sm btn-success" onclick="toggleBlock(${u.id_perdoruesi}, 'unblock')">Zhblloko</button>`
                    : `<button class="btn btn-sm btn-warning" onclick="toggleBlock(${u.id_perdoruesi}, 'block')">Blloko</button>`}
                <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.id_perdoruesi})">Fshi</button>
            </td>
        </tr>`;
    });
    html += '</tbody></table>';

    if (total_pages > 1) {
        html += renderPagination(page, total_pages, 'loadUsers');
    }

    container.innerHTML = html;
}

async function toggleBlock(userId, action) {
    const json = await apiCall(`update_status.php?action=${action}&id=${userId}`, 'PUT');
    showToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    loadUsers();
}

async function deleteUser(userId) {
    if (!confirm('Fshi këtë përdorues?')) return;
    const json = await apiCall(`update_status.php?action=delete&id=${userId}`, 'DELETE');
    showToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    loadUsers();
}

// ══════════════════════════════════════════════════════
//  ADMIN: Dashboard Stats
// ══════════════════════════════════════════════════════

async function loadDashboardStats() {
    const container = document.getElementById('dashboard-stats');
    if (!container) return;

    const json = await apiCall('stats.php?action=overview');
    if (!json.success) return;

    const d = json.data;

    container.innerHTML = `
        <div class="row text-center mb-4">
            <div class="col-md-3"><div class="card p-3 shadow-sm"><h3>${d.users.total_perdorues}</h3><small>Përdorues</small></div></div>
            <div class="col-md-3"><div class="card p-3 shadow-sm"><h3>${d.events.total_evente}</h3><small>Evente</small></div></div>
            <div class="col-md-3"><div class="card p-3 shadow-sm"><h3>${d.applications.total_aplikime}</h3><small>Aplikime</small></div></div>
            <div class="col-md-3"><div class="card p-3 shadow-sm"><h3>${d.help_requests.total_kerkesa}</h3><small>Kërkesa</small></div></div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <h6>Aplikime sipas Statusit</h6>
                <ul class="list-group">
                    <li class="list-group-item d-flex justify-content-between">Në pritje <span class="badge bg-warning">${d.applications.ne_pritje || 0}</span></li>
                    <li class="list-group-item d-flex justify-content-between">Pranuar <span class="badge bg-success">${d.applications.pranuar || 0}</span></li>
                    <li class="list-group-item d-flex justify-content-between">Refuzuar <span class="badge bg-danger">${d.applications.refuzuar || 0}</span></li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Top Kategoritë</h6>
                <ul class="list-group">
                    ${(d.top_categories || []).map(c => `<li class="list-group-item d-flex justify-content-between">${escapeHtml(c.emri)} <span class="badge bg-primary">${c.event_count}</span></li>`).join('')}
                </ul>
            </div>
        </div>`;
}

// ══════════════════════════════════════════════════════
//  Notification Panel
// ══════════════════════════════════════════════════════

async function loadNotifications() {
    const container = document.getElementById('notification-list');
    if (!container) return;

    const json = await apiCall('check_notifs.php?action=list&limit=20');
    if (!json.success) return;

    const notifs = json.data.notifications;

    if (notifs.length === 0) {
        container.innerHTML = '<p class="text-muted">Nuk keni njoftime.</p>';
        return;
    }

    let html = '<div class="list-group">';
    notifs.forEach(n => {
        const unread = !n.is_read;
        html += `<div class="list-group-item ${unread ? 'list-group-item-info' : ''} d-flex justify-content-between align-items-start">
            <div>
                <p class="mb-1">${escapeHtml(n.mesazhi)}</p>
                <small class="text-muted">${formatDate(n.krijuar_me)}</small>
            </div>
            <div>
                ${unread ? `<button class="btn btn-sm btn-outline-primary" onclick="markRead(${n.id_njoftimi})">✓</button>` : ''}
                <button class="btn btn-sm btn-outline-danger" onclick="deleteNotif(${n.id_njoftimi})">✕</button>
            </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

async function markRead(id) {
    await apiCall(`check_notifs.php?action=mark_read&id=${id}`, 'PUT');
    loadNotifications();
    fetchUnreadCount();
}

async function markAllRead() {
    await apiCall('check_notifs.php?action=mark_all_read', 'PUT');
    loadNotifications();
    fetchUnreadCount();
}

async function deleteNotif(id) {
    await apiCall(`check_notifs.php?action=delete&id=${id}`, 'DELETE');
    loadNotifications();
}

// ══════════════════════════════════════════════════════
//  Help Requests
// ══════════════════════════════════════════════════════

async function loadHelpRequests(page = 1, filters = {}) {
    const container = document.getElementById('help-request-list');
    if (!container) return;

    const params = new URLSearchParams({ action: 'list', page, limit: 10, ...filters });
    const json = await apiCall(`help_requests.php?${params}`);
    if (!json.success) return;

    const { requests, total, total_pages } = json.data;

    let html = `<p class="text-muted">Gjithsej: ${total} kërkesa</p>`;
    html += '<div class="list-group">';

    requests.forEach(r => {
        const isOpen = r.statusi === 'Open';
        html += `<div class="list-group-item">
            <div class="d-flex justify-content-between">
                <h6>${escapeHtml(r.titulli)}</h6>
                <div>
                    <span class="badge bg-${r.tipi === 'Kërkesë' ? 'primary' : 'success'}">${escapeHtml(r.tipi)}</span>
                    <span class="badge bg-${isOpen ? 'warning' : 'secondary'}">${r.statusi}</span>
                </div>
            </div>
            <p class="mb-1 text-muted">${escapeHtml(r.pershkrimi || '')}</p>
            <small>Nga: ${escapeHtml(r.krijuesi_emri)} • ${formatDate(r.krijuar_me)}</small>
        </div>`;
    });
    html += '</div>';

    if (total_pages > 1) {
        html += renderPagination(page, total_pages, 'loadHelpRequests');
    }

    container.innerHTML = html;
}

function initHelpRequestForm() {
    const form = document.getElementById('help-request-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const body = Object.fromEntries(fd);

        const json = await apiCall('help_requests.php?action=create', 'POST', body);

        if (json.success) {
            showToast('Kërkesa u dërgua!', 'success');
            form.reset();
            loadHelpRequests();
        } else {
            showToast(json.message || 'Gabim.', 'danger');
        }
    });
}

// ── DOM Ready ───────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initCreateEventForm();
    initHelpRequestForm();

    // Auto-load admin panels if elements exist
    loadAdminEvents();
    loadUsers();
    loadDashboardStats();
    loadNotifications();
    loadHelpRequests();
});
