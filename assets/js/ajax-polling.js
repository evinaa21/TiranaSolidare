/**
 * ajax-polling.js
 * ---------------------------------------------------
 * Lightweight AJAX polling for the notification engine
 * and application-status updates.
 * Uses the Fetch API (ES6+) – no jQuery dependency.
 * ---------------------------------------------------
 */

const API_BASE = '/TiranaSolidare/api';
const POLL_INTERVAL = 15000; // 15 seconds

// ── Notification Badge Polling ──────────────────────

let notifBadge = null;

function initNotificationPolling() {
    notifBadge = document.getElementById('notif-badge');
    if (!notifBadge) return;

    // First fetch immediately, then start interval
    fetchUnreadCount();
    setInterval(fetchUnreadCount, POLL_INTERVAL);
}

async function fetchUnreadCount() {
    try {
        const res = await fetch(`${API_BASE}/check_notifs.php?action=unread_count`);
        const json = await res.json();

        if (json.success && notifBadge) {
            const count = json.data.unread;
            notifBadge.textContent = count > 0 ? count : '';
            notifBadge.style.display = count > 0 ? 'inline-block' : 'none';
        }
    } catch (err) {
        console.warn('[Polling] Notification check failed:', err);
    }
}

// ── Application Status Polling ──────────────────────

let appListContainer = null;
let currentAppPage = 1;

function initApplicationPolling(containerId = 'application-list') {
    appListContainer = document.getElementById(containerId);
    if (!appListContainer) return;

    fetchMyApplications();
    setInterval(fetchMyApplications, POLL_INTERVAL);
}

async function fetchMyApplications(page = 1) {
    currentAppPage = page;
    try {
        const res = await fetch(`${API_BASE}/applications.php?action=list&page=${page}&limit=10`);
        const json = await res.json();

        if (json.success && appListContainer) {
            renderApplicationList(json.data);
        }
    } catch (err) {
        console.warn('[Polling] Application fetch failed:', err);
    }
}

function renderApplicationList(data) {
    if (!appListContainer) return;

    if (data.applications.length === 0) {
        appListContainer.innerHTML = '<p class="text-muted">Nuk keni aplikime ende.</p>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-striped">';
    html += `<thead><tr>
        <th>Eventi</th><th>Data</th><th>Statusi</th><th>Aplikuar më</th><th>Veprime</th>
    </tr></thead><tbody>`;

    data.applications.forEach(app => {
        const statusClass = app.statusi === 'Pranuar' ? 'success'
            : app.statusi === 'Refuzuar' ? 'danger' : 'warning';

        html += `<tr>
            <td>${escapeHtml(app.eventi_titulli)}</td>
            <td>${formatDate(app.eventi_data)}</td>
            <td><span class="badge bg-${statusClass}">${escapeHtml(app.statusi)}</span></td>
            <td>${formatDate(app.aplikuar_me)}</td>
            <td>${app.statusi === 'Në pritje'
                ? `<button class="btn btn-sm btn-outline-danger" onclick="withdrawApplication(${app.id_aplikimi})">Tërhiq</button>`
                : '—'}</td>
        </tr>`;
    });

    html += '</tbody></table></div>';

    // Pagination
    if (data.total_pages > 1) {
        html += renderPagination(data.page, data.total_pages, 'fetchMyApplications');
    }

    appListContainer.innerHTML = html;
}

// ── Withdraw Application ────────────────────────────

async function withdrawApplication(id) {
    if (!confirm('Jeni i sigurt që doni të tërhiqni këtë aplikim?')) return;

    try {
        const res = await fetch(`${API_BASE}/applications.php?action=withdraw&id=${id}`, {
            method: 'DELETE',
        });
        const json = await res.json();

        if (json.success) {
            showToast('Aplikimi u tërhoq.', 'success');
            fetchMyApplications(currentAppPage);
        } else {
            showToast(json.message || 'Gabim.', 'danger');
        }
    } catch (err) {
        showToast('Gabim rrjeti.', 'danger');
    }
}

// ── Event List Polling ──────────────────────────────

let eventListContainer = null;

function initEventPolling(containerId = 'event-list') {
    eventListContainer = document.getElementById(containerId);
    if (!eventListContainer) return;

    fetchEvents();
    setInterval(fetchEvents, 30000); // Every 30 seconds
}

async function fetchEvents(page = 1, filters = {}) {
    const params = new URLSearchParams({ action: 'list', page, limit: 12, ...filters });

    try {
        const res = await fetch(`${API_BASE}/get_events.php?${params}`);
        const json = await res.json();

        if (json.success && eventListContainer) {
            renderEventList(json.data);
        }
    } catch (err) {
        console.warn('[Polling] Event fetch failed:', err);
    }
}

function renderEventList(data) {
    if (!eventListContainer) return;

    if (data.events.length === 0) {
        eventListContainer.innerHTML = '<p class="text-muted">Nuk ka evente për momentin.</p>';
        return;
    }

    let html = '<div class="row">';

    data.events.forEach(ev => {
        html += `<div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm">
                ${ev.banner ? `<img src="${escapeHtml(ev.banner)}" class="card-img-top" alt="Banner">` : ''}
                <div class="card-body">
                    <h5 class="card-title">${escapeHtml(ev.titulli)}</h5>
                    <p class="card-text text-muted">${escapeHtml((ev.pershkrimi || '').substring(0, 120))}...</p>
                    <p><small><strong>📅</strong> ${formatDate(ev.data)}</small></p>
                    <p><small><strong>📍</strong> ${escapeHtml(ev.vendndodhja)}</small></p>
                    ${ev.kategoria_emri ? `<span class="badge bg-info">${escapeHtml(ev.kategoria_emri)}</span>` : ''}
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary btn-sm w-100" onclick="applyForEvent(${ev.id_eventi})">Apliko</button>
                </div>
            </div>
        </div>`;
    });

    html += '</div>';

    if (data.total_pages > 1) {
        html += renderPagination(data.page, data.total_pages, 'fetchEvents');
    }

    eventListContainer.innerHTML = html;
}

// ── Apply For Event ─────────────────────────────────

async function applyForEvent(eventId) {
    try {
        const res = await fetch(`${API_BASE}/applications.php?action=apply`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id_eventi: eventId }),
        });
        const json = await res.json();

        if (json.success) {
            showToast('Aplikimi u dërgua me sukses!', 'success');
        } else {
            showToast(json.message || 'Gabim gjatë aplikimit.', 'danger');
        }
    } catch (err) {
        showToast('Gabim rrjeti.', 'danger');
    }
}

// ── Shared Utility Functions ────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('sq-AL', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function renderPagination(current, totalPages, callbackName) {
    let html = '<nav><ul class="pagination justify-content-center mt-3">';
    for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === current ? 'active' : ''}">
            <a class="page-link" href="#" onclick="${callbackName}(${i}); return false;">${i}</a>
        </li>`;
    }
    html += '</ul></nav>';
    return html;
}

function showToast(message, type = 'info') {
    // Simple toast using a floating div
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
    toast.style.zIndex = '9999';
    toast.style.minWidth = '280px';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// ── Auto-Init on DOM Ready ──────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    initNotificationPolling();
    initApplicationPolling();
    initEventPolling();
});
