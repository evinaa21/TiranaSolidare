/**
 * main.js
 * ---------------------------------------------------
 * Core JS utilities and admin-panel interactions
 * for Tirana Solidare.
 * ---------------------------------------------------
 */

const API = '/TiranaSolidare/api';

// ── CSRF token from meta tag ────────────────────────
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// ── Generic API caller ──────────────────────────────

async function apiCall(endpoint, method = 'GET', body = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
    };
    // Include CSRF token for state-changing requests
    if (method !== 'GET') {
        opts.headers['X-CSRF-Token'] = getCsrfToken();
    }
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

    const json = await apiCall(`events.php?action=list&page=${page}&limit=10`);
    if (!json.success) return;

    const { events, total, total_pages } = json.data;

    let html = `<p class="text-muted">Gjithsej: ${total} evente</p>`;
    html += '<table class="table table-hover"><thead><tr>'
        + '<th>ID</th><th>Titulli</th><th>Kategoria</th><th>Data</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

  events.forEach(ev => {
    const isPast = new Date(ev.data) < new Date();
    html += `<tr>
        <td>${ev.id_eventi}</td>
        <td>${escapeHtml(ev.titulli)}</td>
        <td>${escapeHtml(ev.kategoria_emri || '—')}</td>
        <td>${formatDate(ev.data)}</td>
        <td>
            ${!isPast ? `<button class="btn btn-sm btn-warning" onclick="editEventPrompt(${ev.id_eventi}, this)">Ndrysho</button>` : ''}
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
    const json = await apiCall(`events.php?action=delete&id=${id}`, 'DELETE');
    showToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    loadAdminEvents();
}

async function editEventPrompt(id, btnEl) {
    const json = await apiCall(`events.php?action=get&id=${id}`);
    if (!json.success) return;
    const ev = json.data;

    const existing = document.getElementById('edit-event-modal');
    if (existing) existing.remove();

    const catsJson = await apiCall('categories.php?action=list');
    const cats = catsJson.success ? catsJson.data.categories : [];
    const catOptions = cats.map(c =>
        `<option value="${c.id_kategoria}" ${c.id_kategoria == ev.id_kategoria ? 'selected' : ''}>${escapeHtml(c.emri)}</option>`
    ).join('');

    const dataVal = ev.data ? ev.data.replace(' ', 'T').substring(0, 16) : '';

    const modal = document.createElement('div');
    modal.id = 'edit-event-modal';
    modal.style.cssText = `position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);`;
    modal.innerHTML = `
        <div style="background:#fff;border-radius:16px;padding:32px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.2);max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
                <h3 style="margin:0;font-size:1.2rem;font-weight:700;">Ndrysho Eventin</h3>
                <button onclick="document.getElementById('edit-event-modal').remove()" style="background:none;border:none;cursor:pointer;font-size:1.5rem;color:#6b7280;">×</button>
            </div>
            <div style="display:flex;flex-direction:column;gap:14px;">
                <div>
                    <label style="font-size:0.8rem;font-weight:600;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:6px;">Titulli</label>
                    <input id="edit-ev-titulli" type="text" value="${escapeHtml(ev.titulli || '')}" style="width:100%;padding:10px 14px;border:1.5px solid #e4e8ee;border-radius:10px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:6px;">Përshkrimi</label>
                    <textarea id="edit-ev-pershkrimi" rows="3" style="width:100%;padding:10px 14px;border:1.5px solid #e4e8ee;border-radius:10px;font-size:0.9rem;outline:none;resize:vertical;box-sizing:border-box;">${escapeHtml(ev.pershkrimi || '')}</textarea>
                </div>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:6px;">Data</label>
                    <input id="edit-ev-data" type="datetime-local" value="${dataVal}" style="width:100%;padding:10px 14px;border:1.5px solid #e4e8ee;border-radius:10px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:6px;">Vendndodhja</label>
                    <input id="edit-ev-vendndodhja" type="text" value="${escapeHtml(ev.vendndodhja || '')}" style="width:100%;padding:10px 14px;border:1.5px solid #e4e8ee;border-radius:10px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:6px;">Kategoria</label>
                    <select id="edit-ev-kategoria" style="width:100%;padding:10px 14px;border:1.5px solid #e4e8ee;border-radius:10px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                        ${catOptions}
                    </select>
                </div>
                <div>
                    <label style="font-size:0.8rem;font-weight:600;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:6px;">Banner URL</label>
                    <input id="edit-ev-banner" type="url" value="${escapeHtml(ev.banner || '')}" placeholder="https://example.com/image.jpg" style="width:100%;padding:10px 14px;border:1.5px solid #e4e8ee;border-radius:10px;font-size:0.9rem;outline:none;box-sizing:border-box;">
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:24px;justify-content:flex-end;">
                <button onclick="document.getElementById('edit-event-modal').remove()" style="padding:10px 20px;border:1.5px solid #e4e8ee;border-radius:10px;background:transparent;cursor:pointer;font-size:0.88rem;font-weight:600;">Anulo</button>
                <button onclick="saveEventEdit(${id})" style="padding:10px 20px;background:#00715D;color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:0.88rem;font-weight:600;">Ruaj Ndryshimet</button>
            </div>
        </div>`;

    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
}

async function saveEventEdit(id) {
    const titulli = document.getElementById('edit-ev-titulli')?.value.trim();
    const pershkrimi = document.getElementById('edit-ev-pershkrimi')?.value.trim();
    const data = document.getElementById('edit-ev-data')?.value;
    const vendndodhja = document.getElementById('edit-ev-vendndodhja')?.value.trim();
    const id_kategoria = document.getElementById('edit-ev-kategoria')?.value;
    const banner = document.getElementById('edit-ev-banner')?.value.trim() || null;

    if (!titulli) { showToast('Titulli është i detyrueshëm.', 'danger'); return; }
    if (!data) { showToast('Data është e detyrueshme.', 'danger'); return; }
    if (!vendndodhja) { showToast('Vendndodhja është e detyrueshme.', 'danger'); return; }

    const json = await apiCall(`events.php?action=update&id=${id}`, 'PUT', {
        titulli, pershkrimi, data, vendndodhja, id_kategoria, banner
    });

    showToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    if (json.success) {
        document.getElementById('edit-event-modal')?.remove();
        loadAdminEvents();
    }
}

// ── Create Event Form Handler ───────────────────────

function initCreateEventForm() {
    const form = document.getElementById('create-event-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const body = Object.fromEntries(fd);

        // Convert lat/lng to numbers if present
        if (body.latitude) body.latitude = parseFloat(body.latitude);
        else delete body.latitude;
        if (body.longitude) body.longitude = parseFloat(body.longitude);
        else delete body.longitude;

        const json = await apiCall('events.php?action=create', 'POST', body);

        if (json.success) {
            showToast('Eventi u krijua!', 'success');
            form.reset();
            // Clear map coordinate display
            const coordDisplay = document.getElementById('event-coord-display');
            if (coordDisplay) coordDisplay.style.display = 'none';
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

    // Show the card wrapper
    const card = document.getElementById('event-applications-card');
    if (card) card.style.display = 'block';

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
    // Refresh the application list for the current event
    const container = document.getElementById('event-applications');
    if (container && container.dataset.eventId) {
        viewEventApps(parseInt(container.dataset.eventId));
    }
}

// ══════════════════════════════════════════════════════
//  ADMIN: User Management
// ══════════════════════════════════════════════════════

async function loadUsers(page = 1) {
    const container = document.getElementById('admin-user-list');
    if (!container) return;

    const json = await apiCall(`users.php?action=list&page=${page}&limit=15`);
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
    let payload = null;

    if (action === 'block') {
        const reasonInput = await openBlockReasonModal();
        if (reasonInput === null) return;

        const reason = reasonInput.trim();
        payload = reason ? { arsye_bllokimi: reason } : {};
    }

    const json = await apiCall(`users.php?action=${action}&id=${userId}`, 'PUT', payload);
    showToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    loadUsers();
}

function openBlockReasonModal() {
    return new Promise((resolve) => {
        const existing = document.getElementById('block-reason-modal');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'block-reason-modal';
        overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,0.45);backdrop-filter:blur(3px);';

        overlay.innerHTML = `
            <div style="width:100%;max-width:560px;background:#fff;border-radius:14px;box-shadow:0 22px 55px rgba(0,0,0,0.22);overflow:hidden;">
                <div style="padding:16px 18px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <h3 style="margin:0;font-size:1.05rem;color:#0f172a;font-family:'Bitter',serif;">Blloko përdoruesin</h3>
                    <button type="button" id="block-reason-close" style="border:0;background:transparent;cursor:pointer;font-size:1.4rem;line-height:1;color:#64748b;">&times;</button>
                </div>
                <div style="padding:16px 18px;">
                    <p style="margin:0 0 10px;color:#475569;font-size:0.9rem;">Shkruani arsyen e bllokimit (opsionale). Ky tekst do t'i dërgohet përdoruesit me email.</p>
                    <textarea id="block-reason-input" maxlength="1000" placeholder="P.sh. Sjellje e papërshtatshme / spam i përsëritur..." style="width:100%;min-height:120px;resize:vertical;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;font-family:inherit;font-size:0.92rem;outline:none;"></textarea>
                    <div style="margin-top:6px;font-size:0.78rem;color:#94a3b8;text-align:right;"><span id="block-reason-counter">0</span>/1000</div>
                </div>
                <div style="padding:14px 18px;border-top:1px solid #e5e7eb;display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" id="block-reason-cancel" style="padding:9px 14px;border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:8px;cursor:pointer;font-weight:600;">Anulo</button>
                    <button type="button" id="block-reason-confirm" style="padding:9px 14px;border:0;background:#e17254;color:#fff;border-radius:8px;cursor:pointer;font-weight:700;">Konfirmo bllokimin</button>
                </div>
            </div>
        `;

        const textarea = overlay.querySelector('#block-reason-input');
        const counter = overlay.querySelector('#block-reason-counter');
        const closeBtn = overlay.querySelector('#block-reason-close');
        const cancelBtn = overlay.querySelector('#block-reason-cancel');
        const confirmBtn = overlay.querySelector('#block-reason-confirm');

        let settled = false;
        const finish = (value) => {
            if (settled) return;
            settled = true;
            document.removeEventListener('keydown', handleEsc);
            overlay.remove();
            resolve(value);
        };

        const handleEsc = (e) => {
            if (e.key === 'Escape') finish(null);
        };

        textarea.addEventListener('input', () => {
            counter.textContent = String(textarea.value.length);
        });

        closeBtn.addEventListener('click', () => finish(null));
        cancelBtn.addEventListener('click', () => finish(null));
        confirmBtn.addEventListener('click', () => finish(textarea.value));
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) finish(null);
        });

        document.addEventListener('keydown', handleEsc);
        document.body.appendChild(overlay);
        textarea.focus();
    });
}

async function deleteUser(userId) {
    if (!confirm('\u00c7aktivizo k\u00ebt\u00eb p\u00ebrdorues? (Soft-delete \u2014 t\u00eb dh\u00ebnat do t\u00eb ruhen)')) return;
    const json = await apiCall(`users.php?action=deactivate&id=${userId}`, 'PUT');
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

    const json = await apiCall('notifications.php?action=list&limit=20');
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
    await apiCall(`notifications.php?action=mark_read&id=${id}`, 'PUT');
    loadNotifications();
    fetchUnreadCount();
}

async function markAllRead() {
    await apiCall('notifications.php?action=mark_all_read', 'PUT');
    loadNotifications();
    fetchUnreadCount();
}

async function deleteNotif(id) {
    await apiCall(`notifications.php?action=delete&id=${id}`, 'DELETE');
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
                    <span class="badge bg-${r.tipi === 'Kërkesë' ? 'primary' : 'success'}">${r.tipi === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj'}</span>
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
window.escapeHtml = function(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
};

window.formatDate = function(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('sq-AL', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });
};

window.renderPagination = function(current, totalPages, callbackName) {
    if (typeof dbPagination === 'function') {
        return dbPagination(current, totalPages, callbackName);
    }
    return '';
};

window.showToast = function(message, type = 'info') {
    if (typeof dbToast === 'function') {
        dbToast(message, type);
    }
};
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
