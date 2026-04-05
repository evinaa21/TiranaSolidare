/**
 * dashboard-ui.js
 * ---------------------------------------------------
 * Premium Dashboard UI renderer.
 * Works with existing main.js + ajax-polling.js APIs.
 * Replaces the default Bootstrap table rendering with
 * styled components matching the new admin panel design.
 * ---------------------------------------------------
 */

// ── English→Albanian label map (mirrors PHP status_labels.php) ──
const STATUS_LABELS = {
    pending: 'Në pritje', approved: 'Pranuar', rejected: 'Refuzuar',
    waitlisted: 'Në listë pritjeje', withdrawn: 'Tërhequr',
    present: 'Prezent', absent: 'Munguar',
    active: 'Aktiv', blocked: 'Bllokuar', deactivated: 'Çaktivizuar',
    admin: 'Admin', super_admin: 'Super Admin', volunteer: 'Vullnetar',
    request: 'Kërkesë', offer: 'Ofertë',
    open: 'Hapur', filled: 'Mbushur', closed: 'Mbyllur', completed: 'Përfunduar', cancelled: 'Anuluar',
    pending_review: 'Në shqyrtim'
};
function statusLabel(v) { return STATUS_LABELS[(v || '').toLowerCase()] || v; }

// ── Panel switching ─────────────────────────────────

// Track which panels have had their data loaded
const _loadedPanels = new Set();

// Map each panel to its data-loader function(s)
function _loadPanelData(panelId) {
    switch (panelId) {
        case 'overview':
            loadDashboardStats();
            break;
        case 'events':
            loadAdminEvents();
            break;
        case 'users':
            loadUsers();
            break;
        case 'requests':
            loadHelpRequests();
            break;
        case 'reports':
            loadReportsPanel();
            break;
        case 'messages': {
            // Open a specific thread if the URL has ?with=<userId> (from notification deep-link)
            const _withId = parseInt(new URLSearchParams(location.search).get('with') || '0', 10);
            loadConversations().then(convos => {
                if (_withId > 0) {
                    const match = (convos || []).find(c => c.other_id === _withId);
                    openThread(_withId, match ? match.other_emri : 'Bisedë');
                }
            });
            loadUnreadBadge();
            break;
        }
        case 'notifications':
            loadNotifications();
            break;
        case 'categories':
            loadCategories();
            break;
        case 'audit':
            loadAuditLog();
            break;
        case 'profile':
            loadAdminProfile();
            break;
    }
    _loadedPanels.add(panelId);
}

function switchPanel(panelId, btn) {
    // Hide all panels
    document.querySelectorAll('.db-panel').forEach(p => {
        p.classList.remove('active');
        p.style.display = '';
        p.style.opacity = '';
    });
    // Deactivate all nav items
    document.querySelectorAll('.db-nav-item').forEach(n => n.classList.remove('active'));

    // Show selected - belt AND suspenders: class + inline style
    const panel = document.getElementById(`panel-${panelId}`);
    if (panel) {
        panel.classList.add('active');
        panel.style.display = 'block';
        panel.style.opacity = '1';
    } else {
        console.error('[switchPanel] Panel not found: panel-' + panelId);
    }
    if (btn) btn.classList.add('active');

    // Load panel data if not loaded yet (lazy loading)
    if (!_loadedPanels.has(panelId)) {
        _loadPanelData(panelId);
    }

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
        if (isOpening) loadCategoryDropdown();
    }
}


// ── Category dropdown loader ─────────────────────────

async function loadCategoryDropdown() {
    const sel = document.getElementById('event-category-select');
    if (!sel) return;
    const currentValue = sel.value;
    sel.innerHTML = '<option value="">Pa kategori</option>';
    try {
        const json = await apiCall('categories.php?action=list');
        if (json.success && json.data.categories) {
            const categories = json.data.categories || [];
            if (categories.length === 0) {
                const opt = document.createElement('option');
                opt.disabled = true;
                opt.textContent = 'Nuk ka kategori ende';
                sel.appendChild(opt);
                return;
            }
            categories.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_kategoria;
                opt.textContent = c.emri;
                sel.appendChild(opt);
            });
            if (currentValue && categories.some(c => String(c.id_kategoria) === String(currentValue))) {
                sel.value = currentValue;
            }
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


// ── Nominatim Address Autocomplete (vendndodhja) ────────

function initVendndodhjaAutocomplete() {
    const input  = document.getElementById('event-vendndodhja');
    const sugBox = document.getElementById('event-location-suggestions');
    if (!input || !sugBox) return;

    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (q.length < 3) { sugBox.style.display = 'none'; return; }
        timer = setTimeout(async () => {
            try {
                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=6&countrycodes=al&addressdetails=1`;
                const res  = await fetch(url, { headers: { 'Accept-Language': 'sq' } });
                const data = await res.json();
                if (!data.length) { sugBox.style.display = 'none'; return; }
                sugBox.innerHTML = data.map(r => `
                    <div class="loc-sug-item" onclick="selectNominatimResult(${r.lat}, ${r.lon}, ${JSON.stringify(r.display_name)})">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                        ${escapeHtml(r.display_name)}
                    </div>`).join('');
                sugBox.style.display = 'block';
            } catch (e) { /* network error — silently ignore */ }
        }, 400);
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !sugBox.contains(e.target)) {
            sugBox.style.display = 'none';
        }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { sugBox.style.display = 'none'; }
    });
}

window.selectNominatimResult = function(lat, lon, displayName) {
    const input  = document.getElementById('event-vendndodhja');
    const sugBox = document.getElementById('event-location-suggestions');
    const latEl  = document.getElementById('event-lat-input');
    const lngEl  = document.getElementById('event-lng-input');
    if (input)  input.value  = displayName;
    if (latEl)  latEl.value  = lat;
    if (lngEl)  lngEl.value  = lon;
    if (sugBox) sugBox.style.display = 'none';

    // Pan the map picker if it's already initialised
    if (window._eventMapPicker) {
        window._eventMapPicker.setView([parseFloat(lat), parseFloat(lon)], 16);
        if (window._eventMapMarker) {
            window._eventMapMarker.setLatLng([parseFloat(lat), parseFloat(lon)]);
        } else {
            window._eventMapMarker = L.marker([parseFloat(lat), parseFloat(lon)], { draggable: true })
                .addTo(window._eventMapPicker);
        }
        const coordDisplay = document.getElementById('event-coord-display');
        const coordText    = document.getElementById('event-coord-text');
        if (coordText)    coordText.textContent   = `${parseFloat(lat).toFixed(5)}, ${parseFloat(lon).toFixed(5)}`;
        if (coordDisplay) coordDisplay.style.display = 'flex';
    }
};


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
    if (!json.success) {
        container.innerHTML = `<div class="db-loading" style="color:#dc3545;">${escapeHtml(json.message || 'Gabim gjatë ngarkimit të statistikave.')}</div>`;
        return;
    }

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

    // Sub-stats grid — premium redesign
    if (subContainer) {
        // Inject styles once
        if (!document.getElementById('db-ov-sty')) {
            const sty = document.createElement('style');
            sty.id = 'db-ov-sty';
            sty.textContent = `
.db-ov-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;padding:20px 36px 0;}
@media(max-width:1100px){.db-ov-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:640px){.db-ov-grid{grid-template-columns:1fr;padding:16px 20px 0;}}
.db-ov-card{background:#fff;border:1px solid #f0f4f8;border-radius:18px;padding:22px 24px;box-shadow:0 1px 4px rgba(0,0,0,0.04),0 4px 20px rgba(0,0,0,0.03);transition:box-shadow .22s,transform .22s;}
.db-ov-card:hover{box-shadow:0 2px 8px rgba(0,0,0,0.07),0 8px 28px rgba(0,113,93,0.07);}
.db-ov-card--wide{grid-column:1/-1;}
.db-ov-card__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.db-ov-card__title{font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#6b7280;}
.db-ov-card__link{font-size:0.76rem;color:#00715D;text-decoration:none;font-weight:600;}
.db-ov-funnel{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;}
.db-ov-fitem{text-align:center;padding:14px 8px;border-radius:14px;cursor:pointer;transition:transform .15s,box-shadow .15s;}
.db-ov-fitem:hover{transform:translateY(-2px);box-shadow:0 4px 16px rgba(0,0,0,0.1);}
.db-ov-fitem__val{font-size:1.6rem;font-weight:800;line-height:1;font-variant-numeric:tabular-nums;}
.db-ov-fitem__lbl{font-size:0.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-top:5px;opacity:.8;}
.db-ov-pbar{height:7px;background:#f0f4f8;border-radius:999px;overflow:hidden;margin-top:12px;}
.db-ov-pbar__fill{height:100%;border-radius:999px;transition:width 1s cubic-bezier(.22,1,.36,1);}
.db-ov-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f8fafc;cursor:pointer;}
.db-ov-row:last-child{border-bottom:none;}
.db-ov-row__lbl{font-size:0.83rem;color:#374151;display:flex;align-items:center;gap:8px;}
.db-ov-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.db-ov-row__val{font-size:0.9rem;font-weight:700;font-variant-numeric:tabular-nums;}
.db-ov-cat{display:flex;flex-direction:column;gap:11px;}
.db-ov-cat-item__head{display:flex;justify-content:space-between;font-size:0.82rem;margin-bottom:5px;}
.db-ov-cat-item__name{font-weight:600;color:#374151;}
.db-ov-cat-item__cnt{font-weight:700;color:#00715D;font-variant-numeric:tabular-nums;}
.db-ov-catbar{height:5px;background:#f1f5f9;border-radius:999px;overflow:hidden;}
.db-ov-catbar__fill{height:100%;background:linear-gradient(90deg,#00715D,#26a898);border-radius:999px;}
.db-ov-rtbl{width:100%;border-collapse:collapse;font-size:0.82rem;}
.db-ov-rtbl th{font-size:0.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#94a3b8;padding:0 10px 10px;text-align:left;}
.db-ov-rtbl td{padding:9px 10px;border-top:1px solid #f1f5f9;vertical-align:middle;}
.db-ov-rtbl tr:first-child td{border-top:none;}
.db-ov-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#00715D,#26a898);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.74rem;font-weight:700;flex-shrink:0;}
            `;
            document.head.appendChild(sty);
        }

        const totalApps = (d.applications.ne_pritje || 0) + (d.applications.pranuar || 0) + (d.applications.refuzuar || 0);
        const approvedPct = totalApps > 0 ? Math.round((d.applications.pranuar || 0) / totalApps * 100) : 0;
        const maxCat = Math.max(...(d.top_categories || []).map(c => parseInt(c.event_count) || 0), 1);

        const statusColors = { approved: '#10b981', pending: '#f59e0b', rejected: '#ef4444' };
        const statusBg = { approved: '#dcfce7', pending: '#fef3c7', rejected: '#fee2e2' };

        const recentAppsHtml = (d.recent_applications || []).length === 0
            ? '<p style="color:#94a3b8;font-size:0.84rem;text-align:center;padding:20px 0;">Nuk ka aplikime ende.</p>'
            : `<table class="db-ov-rtbl">
                <thead><tr><th>Vullnetari</th><th>Eventi</th><th>Statusi</th><th>Data</th></tr></thead>
                <tbody>${(d.recent_applications || []).map(a => {
                    const sl = (a.statusi || '').toLowerCase();
                    const ini = (a.vullnetari_emri || '?').charAt(0).toUpperCase();
                    return `<tr>
                        <td><div style="display:flex;align-items:center;gap:8px;"><div class="db-ov-avatar">${escapeHtml(ini)}</div><strong>${escapeHtml(a.vullnetari_emri || '')}</strong></div></td>
                        <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#64748b;" title="${escapeHtml(a.eventi_titulli||'')}">${escapeHtml(a.eventi_titulli || '')}</td>
                        <td><span style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:0.69rem;font-weight:700;background:${statusBg[sl]||'#f3f4f6'};color:${statusColors[sl]||'#6b7280'};">${statusLabel(a.statusi)}</span></td>
                        <td style="color:#94a3b8;white-space:nowrap;font-size:0.79rem;">${(a.aplikuar_me||'').substring(0,10)}</td>
                    </tr>`;
                }).join('')}
                </tbody></table>`;

        subContainer.innerHTML = `<div class="db-ov-grid">

            <div class="db-ov-card">
                <div class="db-ov-card__head"><span class="db-ov-card__title">Aplikimet sipas Statusit</span></div>
                <div class="db-ov-funnel">
                    <div class="db-ov-fitem" style="background:#fef3c7;cursor:default;">
                        <div class="db-ov-fitem__val" style="color:#92400e;">${d.applications.ne_pritje || 0}</div>
                        <div class="db-ov-fitem__lbl" style="color:#a16207;">Pritje</div>
                    </div>
                    <div class="db-ov-fitem" style="background:#dcfce7;cursor:default;">
                        <div class="db-ov-fitem__val" style="color:#14532d;">${d.applications.pranuar || 0}</div>
                        <div class="db-ov-fitem__lbl" style="color:#166534;">Pranuar</div>
                    </div>
                    <div class="db-ov-fitem" style="background:#fee2e2;cursor:default;">
                        <div class="db-ov-fitem__val" style="color:#7f1d1d;">${d.applications.refuzuar || 0}</div>
                        <div class="db-ov-fitem__lbl" style="color:#991b1b;">Refuzuar</div>
                    </div>
                </div>
                <div>
                    <div style="font-size:0.76rem;color:#6b7280;margin-bottom:5px;display:flex;justify-content:space-between;"><span>Norma e pranimit</span><strong style="color:#10b981;">${approvedPct}%</strong></div>
                    <div class="db-ov-pbar"><div class="db-ov-pbar__fill" style="width:${approvedPct}%;background:linear-gradient(90deg,#10b981,#059669);"></div></div>
                </div>
            </div>

            <div class="db-ov-card">
                <div class="db-ov-card__head"><span class="db-ov-card__title">Gjendja e Platformës</span></div>
                <div class="db-ov-row" onclick="switchPanel('events',document.querySelector('[data-panel=events]'))">
                    <span class="db-ov-row__lbl"><div class="db-ov-dot" style="background:#3b82f6;"></div>Evente të ardhshme</span>
                    <span class="db-ov-row__val" style="color:#3b82f6;">${d.events.evente_te_ardhshme || 0}</span>
                </div>
                <div class="db-ov-row" onclick="switchPanel('users',document.querySelector('[data-panel=users]'))">
                    <span class="db-ov-row__lbl"><div class="db-ov-dot" style="background:#00715D;"></div>Vullnetarë aktivë</span>
                    <span class="db-ov-row__val" style="color:#00715D;">${d.users.vullnetar_count || 0}</span>
                </div>
                <div class="db-ov-row" onclick="switchPanel('requests',document.querySelector('[data-panel=requests]'))">
                    <span class="db-ov-row__lbl"><div class="db-ov-dot" style="background:#f59e0b;"></div>Kërkesa të hapura</span>
                    <span class="db-ov-row__val" style="color:#f59e0b;">${d.help_requests.kerkese_open || 0}</span>
                </div>
                ${(d.users.bllokuar_count || 0) > 0
                    ? `<div class="db-ov-row" onclick="switchPanel('users',document.querySelector('[data-panel=users]'))">
                        <span class="db-ov-row__lbl"><div class="db-ov-dot" style="background:#ef4444;"></div><span style="color:#ef4444;">⚠ Llogari të bllokuara</span></span>
                        <span class="db-ov-row__val" style="color:#ef4444;">${d.users.bllokuar_count}</span>
                       </div>`
                    : `<div class="db-ov-row" style="cursor:default;">
                        <span class="db-ov-row__lbl"><div class="db-ov-dot" style="background:#10b981;"></div>Asnjë problem i detektuar</span>
                        <span style="font-size:0.85rem;color:#10b981;">✓</span>
                       </div>`}
            </div>

            <div class="db-ov-card">
                <div class="db-ov-card__head"><span class="db-ov-card__title">Top Kategoritë</span></div>
                <div class="db-ov-cat">
                    ${(d.top_categories || []).slice(0, 5).map(c => {
                        const pct = Math.round((parseInt(c.event_count) || 0) / maxCat * 100);
                        return `<div>
                            <div class="db-ov-cat-item__head">
                                <span class="db-ov-cat-item__name">${escapeHtml(c.emri)}</span>
                                <span class="db-ov-cat-item__cnt">${c.event_count}</span>
                            </div>
                            <div class="db-ov-catbar"><div class="db-ov-catbar__fill" style="width:${pct}%;"></div></div>
                        </div>`;
                    }).join('')}
                    ${(d.top_categories || []).length === 0 ? '<p style="color:#94a3b8;font-size:0.84rem;">Nuk ka kategori ende.</p>' : ''}
                </div>
            </div>

            <div class="db-ov-card db-ov-card--wide">
                <div class="db-ov-card__head">
                    <span class="db-ov-card__title">Aplikimet e Fundit</span>
                </div>
                <div style="overflow-x:auto;">${recentAppsHtml}</div>
            </div>

        </div>`;
    }
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Admin Event List
// ═══════════════════════════════════════════════════════

window.loadAdminEvents = async function (page = 1) {
    const container = document.getElementById('admin-event-list');
    if (!container) return;

    // Gather filter values
    const filterSearch = document.getElementById('admin-ev-filter-search')?.value.trim() || '';
    const filterCategory = document.getElementById('admin-ev-filter-category')?.value || '';
    const filterDateRange = document.getElementById('admin-ev-filter-daterange')?.value || '';

    const params = new URLSearchParams({ action: 'list', page, limit: 10 });
    if (filterSearch) params.set('search', filterSearch);
    if (filterCategory) params.set('category', filterCategory);
    if (filterDateRange) params.set('dateRange', filterDateRange);

    const json = await apiCall(`events.php?${params}`);
    if (!json.success) {
        container.innerHTML = `<div class="db-loading" style="color:#dc3545;">${escapeHtml(json.message || 'Gabim gjatë ngarkimit të eventeve.')}</div>`;
        return;
    }

    const { events, total, total_pages } = json.data;

    // Render filter bar (once per load, preserve values)
    let filterHtml = `<div class="db-filter-bar" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <input id="admin-ev-filter-search" type="text" placeholder="Kërko titull…" value="${escapeHtml(filterSearch)}" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;min-width:160px;" onkeydown="if(event.key==='Enter')loadAdminEvents(1)">
        <select id="admin-ev-filter-category" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadAdminEvents(1)">
            <option value="">Të gjitha kategorite</option>
        </select>
        <select id="admin-ev-filter-daterange" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadAdminEvents(1)">
            <option value=""${!filterDateRange ? ' selected' : ''}>Periudha</option>
            <option value="week"${filterDateRange === 'week' ? ' selected' : ''}>Kjo javë</option>
            <option value="month"${filterDateRange === 'month' ? ' selected' : ''}>Ky muaj</option>
            <option value="past3"${filterDateRange === 'past3' ? ' selected' : ''}>3 muajt e fundit</option>
        </select>
        <button class="db-btn db-btn--primary db-btn--sm" onclick="loadAdminEvents(1)">Filtro</button>
        <button class="db-btn db-btn--sm" onclick="document.getElementById('admin-ev-filter-search').value='';document.getElementById('admin-ev-filter-category').value='';document.getElementById('admin-ev-filter-daterange').value='';loadAdminEvents(1)" style="background:#f3f4f6;border:1px solid #e4e8ee;border-radius:8px;padding:8px 12px;cursor:pointer;">Pastro</button>
    </div>`;

    let html = filterHtml;
    html += `<div class="db-table-count">Gjithsej: <strong>${total}</strong> evente</div>`;
    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>Titulli</th><th>Kategoria</th><th>Data</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    events.forEach(ev => {
        const isPast = ev.data && new Date(ev.data) < new Date();
        html += `<tr${isPast ? ' style="opacity:0.6"' : ''}>
            
            <td>${escapeHtml(ev.titulli)}</td>
            <td>${ev.kategoria_emri ? `<span class="db-badge db-badge--vol">${escapeHtml(ev.kategoria_emri)}</span>` : '<span style="color:#b0b8c4">—</span>'}</td>
            <td>${formatDate(ev.data)}</td>
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

    if (total_pages > 1) {
        html += dbPagination(page, total_pages, 'loadAdminEvents');
    }

    container.innerHTML = html;

    // Populate category filter dropdown
    const catSel = document.getElementById('admin-ev-filter-category');
    if (catSel && catSel.options.length <= 1) {
        try {
            const catsJson = await apiCall('categories.php?action=list');
            if (catsJson.success && catsJson.data.categories) {
                catsJson.data.categories.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id_kategoria;
                    opt.textContent = c.emri;
                    if (String(c.id_kategoria) === filterCategory) opt.selected = true;
                    catSel.appendChild(opt);
                });
            }
        } catch (e) { /* ignore */ }
    }
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: Event Applications
// ═══════════════════════════════════════════════════════

window.viewEventApps = async function (eventId) {
    const json = await apiCall(`applications.php?action=by_event&id=${eventId}&limit=100`);
    if (!json.success) return;

    const { applications, summary, event_data, event_title, capacity_total, confirmed_applicants } = json.data;
    const isPast = new Date(event_data) < new Date();
    const confirmedApplicants = Array.isArray(confirmed_applicants) ? confirmed_applicants : [];
    const confirmedCountLabel = capacity_total && capacity_total > 0
        ? `${confirmedApplicants.length}/${capacity_total}`
        : `${confirmedApplicants.length}`;

    let body = `<div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
        <span class="db-badge db-badge--pending">Në pritje: ${summary.ne_pritje}</span>
        <span class="db-badge db-badge--active">Pranuar: ${summary.pranuar}</span>
        <span class="db-badge db-badge--blocked">Refuzuar: ${summary.refuzuar}</span>
        <span class="db-badge" style="background:#ecfdf5;color:#065f46;">Të konfirmuar: ${confirmedCountLabel}</span>
        ${isPast ? `<span class="db-badge" style="background:#d1fae5;color:#065f46;">Prezent: ${summary.prezent || 0}</span>
        <span class="db-badge" style="background:#fee2e2;color:#991b1b;">Munguar: ${summary.munguar || 0}</span>` : ''}
    </div>`;

    body += `<div style="margin-bottom:16px;padding:14px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap;${confirmedApplicants.length ? 'margin-bottom:12px;' : ''}">
            <div>
                <div style="font-size:0.95rem;font-weight:700;color:#166534;">Vullnetarët e konfirmuar</div>
                <div style="font-size:0.82rem;color:#166534;">Këtu shfaqen menjëherë aplikantët e pranuar për këtë event.</div>
            </div>
            <span class="db-badge db-badge--active">${capacity_total && capacity_total > 0 ? `${confirmedApplicants.length}/${capacity_total} vende të zëna` : `${confirmedApplicants.length} të konfirmuar`}</span>
        </div>
        ${confirmedApplicants.length > 0
            ? `<div style="display:grid;gap:8px;">${confirmedApplicants.map(app => {
                const statusClass = app.statusi === 'absent' ? 'blocked' : 'active';
                const statusStyle = app.statusi === 'present'
                    ? 'background:#d1fae5;color:#065f46;'
                    : app.statusi === 'absent'
                    ? 'background:#fee2e2;color:#991b1b;'
                    : '';
                const badgeExtra = statusStyle ? ` style="${statusStyle}"` : '';
                return `<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:10px 12px;background:#ffffff;border:1px solid #dcfce7;border-radius:10px;">
                    <div>
                        <div style="font-weight:700;color:#0f172a;">${escapeHtml(app.vullnetari_emri)}</div>
                        <div style="font-size:0.82rem;color:#475569;">${escapeHtml(app.vullnetari_email)}</div>
                    </div>
                    <span class="db-badge db-badge--${statusClass}"${badgeExtra}>${escapeHtml(statusLabel(app.statusi))}</span>
                </div>`;
            }).join('')}</div>`
            : '<div style="font-size:0.85rem;color:#475569;">Asnjë vullnetar i pranuar ende.</div>'}
    </div>`;

    // Bulk-action toolbar — shown when there are pending applications
    if (parseInt(summary.ne_pritje) > 0) {
        body += `<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#166534" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            <span style="font-size:0.875rem;font-weight:600;color:#166534;flex:1;">${summary.ne_pritje} aplikime në pritje</span>
            <button class="db-btn db-btn--success db-btn--sm" onclick="bulkApprove(${eventId})">✓ Prano të gjitha</button>
            <button class="db-btn db-btn--danger db-btn--sm" onclick="bulkReject(${eventId})">✗ Refuzo të gjitha</button>
        </div>`;
    }

    if (applications.length === 0) {
        body += '<div class="db-loading">Nuk ka aplikime për këtë event.</div>';
    } else {
        body += '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>Emri</th><th>Email</th><th>Statusi</th><th>Veprime</th></tr></thead><tbody>';
        applications.forEach(app => {
            const statusClass = app.statusi === 'approved' ? 'active'
                : app.statusi === 'rejected' ? 'blocked'
                : app.statusi === 'present' ? 'active'
                : app.statusi === 'absent' ? 'blocked'
                : 'pending';
            const presenceBadgeStyle = app.statusi === 'present'
                ? 'background:#d1fae5;color:#065f46;'
                : app.statusi === 'absent'
                ? 'background:#fee2e2;color:#991b1b;'
                : '';
            const badgeExtra = presenceBadgeStyle ? ` style="${presenceBadgeStyle}"` : '';

            let actions = '';
if (app.statusi === 'pending') {
    if (!isPast) {
        actions = `<button class="db-btn db-btn--success db-btn--sm" onclick="updateAppStatus(${app.id_aplikimi}, 'approved', ${eventId})">Prano</button>
            <button class="db-btn db-btn--danger db-btn--sm" onclick="updateAppStatus(${app.id_aplikimi}, 'rejected', ${eventId})">Refuzo</button>`;
    } else {
        actions = '<span style="color:#94a3b8;font-size:0.8rem;">Eventi kaloi</span>';
    }
} else if (app.statusi === 'approved' && isPast) {
    actions = `<button class="db-btn db-btn--success db-btn--sm" onclick="markPresence(${app.id_aplikimi}, 'present', ${eventId})">Prezent</button>
        <button class="db-btn db-btn--danger db-btn--sm" onclick="markPresence(${app.id_aplikimi}, 'absent', ${eventId})">Munguar</button>`;
} else if (app.statusi === 'approved' && !isPast) {
    actions = `<button class="db-btn db-btn--danger db-btn--sm" onclick="updateAppStatus(${app.id_aplikimi}, 'rejected', ${eventId})">Refuzo</button>`;
} else if (app.statusi === 'present' || app.statusi === 'absent') {
    actions = '<span style="color:#94a3b8;font-size:0.8rem;">Prezenca e shënuar</span>';
} else if (app.statusi === 'rejected' && !isPast) {
    actions = `<button class="db-btn db-btn--success db-btn--sm" onclick="updateAppStatus(${app.id_aplikimi}, 'approved', ${eventId})">Prano</button>`;
} else {
    actions = '<span style="color:#94a3b8;font-size:0.8rem;">—</span>';
}

            body += `<tr>
                <td><strong>${escapeHtml(app.vullnetari_emri)}</strong></td>
                <td>${escapeHtml(app.vullnetari_email)}</td>
                <td><span class="db-badge db-badge--${statusClass}"${badgeExtra}>${escapeHtml(statusLabel(app.statusi))}</span></td>
                <td><div class="db-table__actions">${actions}</div></td>
            </tr>`;
        });
        body += '</tbody></table></div>';
    }

    showUserActivityModal('Duke ngarkuar…');
    updateUserActivityModal(event_title ? `Aplikimet për ${event_title}` : 'Aplikimet për Eventin', body);
};

// Override main.js updateAppStatus to refresh the modal with the correct eventId
window.updateAppStatus = async function (appId, status, eventId) {
    const json = await apiCall(`applications.php?action=update_status&id=${appId}`, 'PUT', { statusi: status });
    dbToast(json.message || json.data?.message || 'U krëe.', json.success ? 'success' : 'danger');
    if (json.success && eventId) {
        viewEventApps(eventId);
    }
};

window.markPresence = async function (appId, status, eventId) {
    const json = await apiCall(`applications.php?action=mark_presence&id=${appId}`, 'PUT', { statusi: status });
    if (json.success) {
        if (eventId) {
            viewEventApps(eventId);
        }
    }
};

window.bulkApprove = async function (eventId) {
    if (!confirm('Prano të gjitha aplikimet në pritje për këtë event?')) return;
    const json = await apiCall(`applications.php?action=bulk_approve&event_id=${eventId}`, 'PUT');
    dbToast(json.message || 'U krye.', json.success ? 'success' : 'danger');
    if (json.success) viewEventApps(eventId);
};

window.bulkReject = async function (eventId) {
    if (!confirm('Refuzo të gjitha aplikimet në pritje për këtë event?')) return;
    const json = await apiCall(`applications.php?action=bulk_reject&event_id=${eventId}`, 'PUT');
    dbToast(json.message || 'U krye.', json.success ? 'success' : 'danger');
    if (json.success) viewEventApps(eventId);
};


// ═══════════════════════════════════════════════════════
//  OVERRIDE: User Management (Detail Panel approach)
// ═══════════════════════════════════════════════════════

window.loadUsers = async function (page = 1) {
    const container = document.getElementById('admin-user-list');
    if (!container) return;

    // Gather filter values
    const filterSearch = document.getElementById('admin-usr-filter-search')?.value.trim() || '';
    const filterRole = document.getElementById('admin-usr-filter-role')?.value || '';
    const filterStatus = document.getElementById('admin-usr-filter-status')?.value || '';

    const params = new URLSearchParams({ action: 'list', page, limit: 15 });
    if (filterSearch) params.set('search', filterSearch);
    if (filterRole) params.set('roli', filterRole);
    if (filterStatus) params.set('statusi', filterStatus);

    const json = await apiCall(`users.php?${params}`);
    if (!json.success) {
        container.innerHTML = `<div class="db-loading" style="color:#dc3545;">${escapeHtml(json.message || 'Gabim gjatë ngarkimit të përdoruesve.')}</div>`;
        return;
    }

    const { users, total, total_pages } = json.data;

    let html = `<div class="db-filter-bar" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <input id="admin-usr-filter-search" type="text" placeholder="Kërko emër ose email…" value="${escapeHtml(filterSearch)}" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;min-width:160px;" onkeydown="if(event.key==='Enter')loadUsers(1)">
        <select id="admin-usr-filter-role" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadUsers(1)">
            <option value=""${!filterRole ? ' selected' : ''}>Të gjitha rolet</option>
            <option value="admin"${filterRole === 'admin' ? ' selected' : ''}>Admin</option>
            <option value="super_admin"${filterRole === 'super_admin' ? ' selected' : ''}>Super Admin</option>
            <option value="volunteer"${filterRole === 'volunteer' ? ' selected' : ''}>Vullnetar</option>
        </select>
        <select id="admin-usr-filter-status" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadUsers(1)">
            <option value=""${!filterStatus ? ' selected' : ''}>Të gjitha statuset</option>
            <option value="active"${filterStatus === 'active' ? ' selected' : ''}>Aktiv</option>
            <option value="blocked"${filterStatus === 'blocked' ? ' selected' : ''}>Bllokuar</option>
            <option value="deactivated"${filterStatus === 'deactivated' ? ' selected' : ''}>Çaktivizuar</option>
        </select>
        <button class="db-btn db-btn--primary db-btn--sm" onclick="loadUsers(1)">Filtro</button>
        <button class="db-btn db-btn--sm" onclick="document.getElementById('admin-usr-filter-search').value='';document.getElementById('admin-usr-filter-role').value='';document.getElementById('admin-usr-filter-status').value='';loadUsers(1)" style="background:#f3f4f6;border:1px solid #e4e8ee;border-radius:8px;padding:8px 12px;cursor:pointer;">Pastro</button>
    </div>`;
    html += `<div class="db-table-count">Gjithsej: <strong>${total}</strong> përdorues</div>`;
    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>Anëtarësuar</th><th>Emri</th><th>Email</th><th>Roli</th><th>Statusi</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    users.forEach(u => {
        const isBlocked = u.statusi_llogarise === 'blocked';
        const isDeactivated = u.statusi_llogarise === 'deactivated';
        const roleClass = (u.roli === 'admin' || u.roli === 'super_admin') ? 'admin' : 'vol';
        const statusClass = isBlocked ? 'blocked' : isDeactivated ? 'deactivated' : 'active';
        
        // Role change button (super_admin only, cannot change self or other super_admins)
        const canChangeRole = typeof IS_SUPER_ADMIN !== 'undefined' && IS_SUPER_ADMIN
            && u.id_perdoruesi !== CURRENT_USER_ID && u.roli !== 'super_admin';
        
        let createdDate = '—';
        if (u.krijuar_me) {
            const d = new Date(u.krijuar_me);
            createdDate = `${d.getDate().toString().padStart(2, '0')}/${(d.getMonth()+1).toString().padStart(2, '0')}/${d.getFullYear()} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`;
        }

        html += `<tr class="${isBlocked ? 'db-row--blocked' : ''} ${isDeactivated ? 'db-row--deactivated' : ''}">
            <td><span style="color:#64748b;font-size:0.85rem;">${createdDate}</span></td>
            <td><strong>${escapeHtml(u.emri)}</strong></td>
            <td>${escapeHtml(u.email)}</td>
            <td><span class="db-badge db-badge--${roleClass}">${statusLabel(u.roli)}</span></td>
            <td><span class="db-badge db-badge--${statusClass}">${statusLabel(u.statusi_llogarise)}</span></td>
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
                    ${canChangeRole
                        ? `<button class="db-btn db-btn--sm" style="background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;" onclick="changeUserRole(${u.id_perdoruesi}, '${u.roli}')">
                             Ndrysho Rolin</button>`
                        : ''}
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
    const isBlocked = u.statusi_llogarise === 'blocked';
    const isDeactivated = u.statusi_llogarise === 'deactivated';
    const isActive = u.statusi_llogarise === 'active';
    const roleClass = (u.roli === 'admin' || u.roli === 'super_admin') ? 'admin' : 'vol';
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
               <span class="db-badge db-badge--${roleClass}">${statusLabel(u.roli)}</span>
               <span class="db-badge db-badge--${statusClass}">${statusLabel(u.statusi_llogarise)}</span>
            </div>
         <div style="margin-top:8px;">
                 <a href="/TiranaSolidare/views/public_profile.php?id=${u.id_perdoruesi}" target="_blank" class="db-btn db-btn--info db-btn--sm">
                   <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        Shiko Profilin Publik
                     </a>
                  </div>
                </div>
            </div>
        </div>

<!-- Stats Row -->
<div class="ud-stats">
    <div class="ud-stat-card ud-stat-card--clickable" onclick="loadUserApplications(${u.id_perdoruesi}, '${escapeHtml(u.emri)}')">
        <div class="ud-stat-card__value">${u.total_aplikime}</div>
        <div class="ud-stat-card__label">Aplikimet</div>
        <div class="ud-stat-card__hint">Shiko →</div>
    </div>
    <div class="ud-stat-card ud-stat-card--clickable" onclick="loadUserRequests(${u.id_perdoruesi}, '${escapeHtml(u.emri)}')">
        <div class="ud-stat-card__value">${u.total_kerkesa}</div>
        <div class="ud-stat-card__label">Kërkesa</div>
        <div class="ud-stat-card__hint">Shiko →</div>
    </div>
<div class="ud-stat-card">
    <div class="ud-stat-card__value">${formatDate(u.krijuar_me)}</div>
    <div class="ud-stat-card__label">Regjistruar më</div>
</div>
</div>

<!-- User Activity Section (injected by click) -->
<div id="ud-activity-section"></div>

        ${isDeactivated && u.deaktivizuar_me ? `
            <div class="ud-deactivation-notice">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
                <span>Llogaria u çaktivizua më <strong>${formatDate(u.deaktivizuar_me)}</strong></span>
            </div>` : ''}

        <!-- Action Cards Grid -->
        <div class="ud-actions-grid">



            <!-- Account Status Card -->
            <div class="ud-card ud-card--full">
                <div class="ud-card__header">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                    <h4>Statusi i Llogarisë</h4>
                </div>
                <p class="ud-card__desc">Menaxhoni statusin e llogarisë. </p>
                <div class="ud-card__body ud-card__body--row">
                    ${isActive ? `
                       <button class="db-btn db-btn--warning" onclick="toggleBlock(${u.id_perdoruesi}, 'block')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                            Blloko
                        </button>
                        <button class="db-btn db-btn--danger" onclick="deactivateUser(${u.id_perdoruesi})">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                            Çaktivizo Llogarinë
                        </button>
                    ` : isBlocked ? `
                        <button class="db-btn db-btn--success" onclick="toggleBlock(${u.id_perdoruesi}, 'unblock')">
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

    const [eventAppsJson, requestAppsJson] = await Promise.all([
        apiCall(`applications.php?action=by_user&id=${userId}`),
        apiCall(`help_requests.php?action=by_user&id=${userId}`)
    ]);

    const eventApps = eventAppsJson.success ? eventAppsJson.data.applications : [];
    const requestApps = requestAppsJson.success ? requestAppsJson.data.applications : [];

    let body = '';

    // Aplikimet e Eventeve
    body += `<h4 style="margin:0 0 12px;font-family:'Bitter',serif;color:#003229;font-size:1rem;">Aplikimet e Eventeve (${eventApps.length})</h4>`;
    if (eventApps.length === 0) {
        body += '<p style="color:#6b7a8d;padding:8px 0 20px;">Nuk ka aplikime për evente.</p>';
    } else {
        body += '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>Eventi</th><th>Data e Eventit</th><th>Statusi</th><th>Aplikuar më</th></tr></thead><tbody>';
        eventApps.forEach(a => {
            const statusClass = a.statusi === 'approved' ? 'active' : a.statusi === 'rejected' ? 'blocked' : 'pending';
            body += `<tr>
                <td><strong>${escapeHtml(a.eventi_titulli)}</strong></td>
                <td>${formatDate(a.eventi_data)}</td>
                <td><span class="db-badge db-badge--${statusClass}">${escapeHtml(statusLabel(a.statusi))}</span></td>
                <td>${formatDate(a.aplikuar_me)}</td>
            </tr>`;
        });
        body += '</tbody></table></div>';
    }

    // Aplikimet e Kërkesave
    body += `<h4 style="margin:24px 0 12px;font-family:'Bitter',serif;color:#003229;font-size:1rem;">Aplikimet e Kërkesave (${requestApps.length})</h4>`;
    if (requestApps.length === 0) {
        body += '<p style="color:#6b7a8d;padding:8px 0;">Nuk ka aplikime për kërkesa.</p>';
    } else {
        body += '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>Kërkesa</th><th>Tipi</th><th>Pronari</th><th>Statusi</th><th>Aplikuar më</th></tr></thead><tbody>';
        requestApps.forEach(a => {
            const statusClass = a.aplikimi_statusi === 'approved' ? 'active' : a.aplikimi_statusi === 'rejected' ? 'blocked' : 'pending';
            const tipClass = a.tipi === 'request' ? 'request' : a.tipi === 'offer' ? 'offer' : 'vol';
            body += `<tr>
                <td><strong>${escapeHtml(a.titulli)}</strong></td>
                <td><span class="db-badge db-badge--${tipClass}">${escapeHtml(statusLabel(a.tipi))}</span></td>
                <td>${escapeHtml(a.pronari_emri)}</td>
                <td><span class="db-badge db-badge--${statusClass}">${escapeHtml(statusLabel(a.aplikimi_statusi))}</span></td>
                <td>${formatDate(a.aplikuar_me)}</td>
            </tr>`;
        });
        body += '</tbody></table></div>';
    }

    updateUserActivityModal(`Aplikimet e ${escapeHtml(userName)}`, body);
};

window.loadUserRequestApplications = async function(userId, userName) {
    showUserActivityModal('Duke ngarkuar aplikimet e kërkesave…');

    const json = await apiCall(`help_requests.php?action=by_user&id=${userId}`);
    if (!json.success) return;
    const apps = json.data.applications;

    let body = '';
    if (apps.length === 0) {
        body = '<p style="color:#6b7a8d;padding:20px 0;text-align:center;">Nuk ka aplikime për kërkesa.</p>';
    } else {
        body = '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>Kërkesa</th><th>Tipi</th><th>Pronari</th><th>Statusi</th><th>Aplikuar më</th></tr></thead><tbody>';
        apps.forEach(a => {
            const statusClass = a.aplikimi_statusi === 'approved' ? 'active' : a.aplikimi_statusi === 'rejected' ? 'blocked' : 'pending';
            const tipClass = a.tipi === 'request' ? 'request' : a.tipi === 'offer' ? 'offer' : 'vol';
            body += `<tr>
                <td><strong>${escapeHtml(a.titulli)}</strong></td>
                <td><span class="db-badge db-badge--${tipClass}">${escapeHtml(statusLabel(a.tipi))}</span></td>
                <td>${escapeHtml(a.pronari_emri)}</td>
                <td><span class="db-badge db-badge--${statusClass}">${escapeHtml(statusLabel(a.aplikimi_statusi))}</span></td>
                <td>${formatDate(a.aplikuar_me)}</td>
            </tr>`;
        });
        body += '</tbody></table></div>';
    }

    updateUserActivityModal(`Aplikimet e kërkesave — ${escapeHtml(userName)}`, body);
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
            const tipClass = r.tipi === 'request' ? 'request' : 'offer';
            const statClass = ['open', 'filled', 'Open', 'Filled'].includes(r.statusi) ? 'open' : 'closed';
            body += `<tr>
                <td><a href="/TiranaSolidare/views/help_requests.php?id=${r.id_kerkese_ndihme}" target="_blank" style="color:var(--db-primary);font-weight:600;">${escapeHtml(r.titulli)}</a></td>
                <td><span class="db-badge db-badge--${tipClass}">${escapeHtml(statusLabel(r.tipi))}</span></td>
                <td><span class="db-badge db-badge--${statClass}">${escapeHtml(statusLabel(r.statusi))}</span></td>
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

    // Double confirmation for role changes
    const msg = newRole === 'admin'
        ? 'Jeni të sigurt? Ky vullnetar do të fitojë akses admin të plotë.'
        : 'Jeni të sigurt? Ky admin do të humbasë privilegjet administrative.';

    if (!confirm(msg)) return;
    if (!confirm('Konfirmoni përsëri: Dëshironi vërtet të ndryshoni rolin?')) return;

    const json = await apiCall(`users.php?action=change_role&id=${userId}`, 'PUT', { roli: newRole });
    dbToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');

    if (json.success) {
        // Refresh detail
        setTimeout(() => openUserDetail(userId), 300);
    }
};

// Deactivate (soft-delete)
window.deactivateUser = async function (userId) {
    if (!confirm('Çaktivizo këtë llogari? Të dhënat ruhen dhe mund të rikthehen.')) return;

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

window.loadHelpRequests = async function (page = 1) {
    const container = document.getElementById('help-request-list');
    if (!container) return;

    // Gather filter values
    const filterSearch = document.getElementById('admin-req-filter-search')?.value.trim() || '';
    const filterStatus = document.getElementById('admin-req-filter-status')?.value || '';
    const filterType = document.getElementById('admin-req-filter-type')?.value || '';
    const filterFlagged = document.getElementById('admin-req-filter-flagged')?.checked ? '1' : '';
    const filterModeration = document.getElementById('admin-req-filter-moderation')?.value || '';

    const filters = {};
    if (filterSearch) filters.search = filterSearch;
    if (filterStatus) filters.statusi = filterStatus;
    if (filterType) filters.tipi = filterType;
    if (filterFlagged) filters.flagged = filterFlagged;
    if (filterModeration) filters.moderation_status = filterModeration;

    const params = new URLSearchParams({ action: 'list', page, limit: 10, ...filters });
    const json = await apiCall(`help_requests.php?${params}`);
    if (!json.success) {
        container.innerHTML = `<div class="db-loading" style="color:#dc3545;">${escapeHtml(json.message || 'Gabim gjatë ngarkimit të kërkesave.')}</div>`;
        return;
    }

    const { requests, total, total_pages } = json.data;

    // Filter bar
    let html = `<div class="db-filter-bar" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <input id="admin-req-filter-search" type="text" placeholder="Kërko titull…" value="${escapeHtml(filterSearch)}" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;min-width:160px;" onkeydown="if(event.key==='Enter')loadHelpRequests(1)">
        <select id="admin-req-filter-status" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadHelpRequests(1)">
            <option value=""${!filterStatus ? ' selected' : ''}>Të gjitha statuset</option>
            <option value="open"${filterStatus === 'open' ? ' selected' : ''}>Hapur</option>
            <option value="filled"${filterStatus === 'filled' ? ' selected' : ''}>Mbushur</option>
            <option value="completed"${filterStatus === 'completed' ? ' selected' : ''}>Përfunduar</option>
            <option value="cancelled"${filterStatus === 'cancelled' ? ' selected' : ''}>Anuluar</option>
        </select>
        <select id="admin-req-filter-type" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadHelpRequests(1)">
            <option value=""${!filterType ? ' selected' : ''}>Të gjitha tipet</option>
            <option value="request"${filterType === 'request' ? ' selected' : ''}>Kërkesë</option>
            <option value="offer"${filterType === 'offer' ? ' selected' : ''}>Ofertë</option>
        </select>
        <select id="admin-req-filter-moderation" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadHelpRequests(1)">
            <option value=""${!filterModeration ? ' selected' : ''}>Të gjitha moderimi</option>
            <option value="pending_review"${filterModeration === 'pending_review' ? ' selected' : ''}>Në shqyrtim</option>
            <option value="approved"${filterModeration === 'approved' ? ' selected' : ''}>Miratuar</option>
            <option value="rejected"${filterModeration === 'rejected' ? ' selected' : ''}>Refuzuar</option>
        </select>
        <label style="display:flex;align-items:center;gap:6px;font-size:0.85rem;font-weight:600;color:#ef4444;">
             <input type="checkbox" id="admin-req-filter-flagged" onchange="loadHelpRequests(1)" ${filterFlagged ? 'checked' : ''}>
             Vetëm të Raportuarat
        </label>
        <button class="db-btn db-btn--primary db-btn--sm" onclick="loadHelpRequests(1)">Filtro</button>
        <button class="db-btn db-btn--sm" onclick="document.getElementById('admin-req-filter-search').value='';document.getElementById('admin-req-filter-status').value='';document.getElementById('admin-req-filter-type').value='';document.getElementById('admin-req-filter-moderation').value='';document.getElementById('admin-req-filter-flagged').checked=false;loadHelpRequests(1)" style="background:#f3f4f6;border:1px solid #e4e8ee;border-radius:8px;padding:8px 12px;cursor:pointer;">Pastro</button>
    </div>`;

    html += `<div class="db-table-count">Gjithsej: <strong>${total}</strong> kërkesa</div>`;

    if (requests.length === 0) {
        html += '<div class="db-loading">Nuk ka kërkesa për momentin.</div>';
        container.innerHTML = html;
        return;
    }

    html += '<div class="db-table-responsive"><table class="db-table"><thead><tr>'
        + '<th>Titulli</th><th>Tipi</th><th>Statusi</th><th>Moderimi</th><th>Nga</th><th>Raportime</th><th>Data</th><th>Veprime</th>'
        + '</tr></thead><tbody>';

    requests.forEach(r => {
        const tipClass = r.tipi === 'request' ? 'request' : 'offer';
        const statClass = ['open', 'filled'].includes(r.statusi) ? 'open' : 'closed';
        const modStatus = r.moderation_status || 'approved';
        const modBadgeStyle = modStatus === 'pending_review'
            ? 'background:#fef3c7;color:#92400e;'
            : modStatus === 'rejected'
                ? 'background:#fee2e2;color:#991b1b;'
                : 'background:#dcfce7;color:#14532d;';
        const flagIndicator = r.flags > 0 ? `<span style="color:#dc2626;font-weight:700;display:block;text-align:center;">${r.flags}</span>` : '';

        html += `<tr ${['completed', 'cancelled', 'closed'].includes(r.statusi) ? 'style="opacity:0.65"' : ''}>
            <td><strong>${escapeHtml(r.titulli)}</strong></td>
            <td><span class="db-badge db-badge--${tipClass}">${escapeHtml(statusLabel(r.tipi))}</span></td>
            <td><span class="db-badge db-badge--${statClass}">${escapeHtml(statusLabel(r.statusi))}</span></td>
            <td><span class="db-badge" style="${modBadgeStyle}">${escapeHtml(statusLabel(modStatus))}</span></td>
            <td>${escapeHtml(r.krijuesi_emri || '—')}</td>
            <td>${flagIndicator}</td>
            <td>${formatDate(r.krijuar_me)}</td>
            <td>
                <div class="db-table__actions">
    <a href="/TiranaSolidare/views/help_requests.php?id=${r.id_kerkese_ndihme}" class="db-btn db-btn--info db-btn--sm" target="_blank">Shiko</a>
${modStatus === 'pending_review' ? `<button class="db-btn db-btn--sm" style="background:#10b981;color:#fff;border-color:#10b981;" onclick="approveRequest(${r.id_kerkese_ndihme})">Mirato</button><button class="db-btn db-btn--danger db-btn--sm" onclick="rejectRequest(${r.id_kerkese_ndihme})">Refuzo</button>` : ''}
${modStatus === 'rejected' ? `<button class="db-btn db-btn--sm" style="background:#10b981;color:#fff;border-color:#10b981;" onclick="approveRequest(${r.id_kerkese_ndihme})">Mirato</button>` : ''}
${r.flags > 0 ? `<button class="db-btn db-btn--sm" onclick="viewRequestFlags(${r.id_kerkese_ndihme})">Raportime</button>` : ''}
<button class="db-btn db-btn--danger db-btn--sm" onclick="deleteRequest(${r.id_kerkese_ndihme})">Fshi</button>
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

// Approve a pending-review help request
window.approveRequest = async function (id) {
    if (!confirm('Miratoni këtë postim? Do të bëhet publik.')) return;
    const json = await apiCall(`help_requests.php?action=approve_request&id=${id}`, 'PUT');
    dbToast(json.message || 'U krye.', json.success ? 'success' : 'danger');
    if (json.success) loadHelpRequests();
};

// Reject a pending-review help request
window.rejectRequest = async function (id) {
    if (!confirm('Refuzoni këtë postim? Nuk do të shfaqet publikisht.')) return;
    const json = await apiCall(`help_requests.php?action=reject_request&id=${id}`, 'PUT');
    dbToast(json.message || 'U krye.', json.success ? 'success' : 'danger');
    if (json.success) loadHelpRequests();
};

// Close request action
window.closeRequest = async function (id) {
    if (!confirm('Shënoje këtë postim si të përfunduar?')) return;
    const json = await apiCall(`help_requests.php?action=complete&id=${id}`, 'PUT');
    dbToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    loadHelpRequests();
};

window.deleteRequest = async function (id) {
    if (!confirm('Fshi këtë kërkesë?')) return;
    const json = await apiCall(`help_requests.php?action=delete&id=${id}`, 'DELETE');
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
    if (!json.success) {
        container.innerHTML = `<div class="db-loading" style="color:#dc3545;">${escapeHtml(json.message || 'Gabim gjatë ngarkimit të njoftimeve.')}</div>`;
        return;
    }

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
        const hasLink = n.linku && n.linku.trim() !== '';
        const linkOpen = hasLink ? `<a href="${escapeHtml(n.linku)}" class="db-notif__link" style="text-decoration:none;color:inherit;">` : '';
        const linkClose = hasLink ? '</a>' : '';
        html += `<div class="db-notif ${unread ? 'db-notif--unread' : 'db-notif--read'}">
            <div class="db-notif__dot"></div>
            ${linkOpen}<div class="db-notif__body">
                <p class="db-notif__msg">${escapeHtml(n.mesazhi)}</p>
                <span class="db-notif__time">${formatDate(n.krijuar_me)}</span>
            </div>${linkClose}
            <div class="db-notif__actions">
                ${unread ? `<button class="db-btn db-btn--success db-btn--sm" onclick="markRead(${n.id_njoftimi})" title="Shëno si të lexuar">✓</button>` : ''}
                <button class="db-btn db-btn--danger db-btn--sm" onclick="deleteNotif(${n.id_njoftimi})" title="Fshi">✕</button>
            </div>
        </div>`;
    });
    html += '</div>';
    container.innerHTML = html;

    // ── Update notification bell badge ──────────────────
    const notifBadge = document.getElementById('notif-badge');
    if (notifBadge) {
        const unreadCount = notifs.filter(n => !n.is_read).length;
        if (unreadCount > 0) {
            notifBadge.textContent   = unreadCount > 99 ? '99+' : String(unreadCount);
            notifBadge.style.display  = 'inline-flex';
        } else {
            notifBadge.style.display  = 'none';
        }
    }
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
            <img src="${ev.banner ? escapeHtml(ev.banner) : '/TiranaSolidare/public/assets/images/default-event.svg'}" class="db-event-card__img" alt="" onerror="this.src='/TiranaSolidare/public/assets/images/default-event.svg'">
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
        const statusClass = app.statusi === 'approved' ? 'active'
            : app.statusi === 'rejected' ? 'blocked' : 'pending';

        html += `<tr>
            <td><strong>${escapeHtml(app.eventi_titulli)}</strong></td>
            <td>${formatDate(app.eventi_data)}</td>
            <td><span class="db-badge db-badge--${statusClass}">${escapeHtml(statusLabel(app.statusi))}</span></td>
            <td>${formatDate(app.aplikuar_me)}</td>
            <td>${app.statusi === 'pending'
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
}


// ═══════════════════════════════════════════════════════
//  INIT
// ═══════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
    // Load category dropdown for event creation form
    loadCategoryDropdown();
    // NOTE: event location autocomplete is handled inline in dashboard.php

    // Determine the initial panel (from URL hash or default 'overview')
    let initialPanel = 'overview';
    if (location.hash) {
        const hashPanel = location.hash.replace('#', '');
        if (document.getElementById(`panel-${hashPanel}`)) {
            initialPanel = hashPanel;
        }
    }

    // Activate the initial panel via switchPanel (which will also load its data)
    const navBtn = document.querySelector(`[data-panel="${initialPanel}"]`);
    switchPanel(initialPanel, navBtn);

    // Pre-load overview stats (always needed for badge counts etc.)
    if (initialPanel !== 'overview') {
        _loadPanelData('overview');
    }

    // Load notification badge count
    loadNotifications();
    _loadedPanels.add('notifications');

    // Load message badge count (lightweight)
    loadUnreadBadge();

    // ── Session heartbeat ──────────────────────────────
    // Ping auth every 10 min so the PHP session doesn't expire while the
    // admin tab is open.  apiCall's 401 handler will redirect if it does.
    setInterval(() => apiCall('auth.php?action=me'), 10 * 60 * 1000);
});


// ═══════════════════════════════════════════════════════
//  ADMIN REPORTS & CHARTS
// ═══════════════════════════════════════════════════════

let _reportsChartsLoaded = false;

async function loadReportsPanel() {
    if (_reportsChartsLoaded) return;
    const container = document.getElementById('reports-charts');
    if (!container) return;

    try {
        const json = await apiCall('stats.php?action=monthly');
        if (!json.success) { container.innerHTML = '<div class="db-loading">Gabim gjatë ngarkimit.</div>'; return; }

        const { monthly_apps, monthly_requests, monthly_events, apps_by_category } = json.data;

        // Albanian month names
        const monthNames = ['Jan', 'Shk', 'Mar', 'Pri', 'Maj', 'Qer', 'Kor', 'Gus', 'Sht', 'Tet', 'Nën', 'Dhj'];
        const allMonths = new Set();
        [monthly_apps, monthly_requests, monthly_events].forEach(arr =>
            arr.forEach(r => allMonths.add(r.muaji))
        );
        const sortedMonths = [...allMonths].sort().slice(-6);
        const labels = sortedMonths.map(m => {
            const [y, mo] = m.split('-');
            return monthNames[parseInt(mo) - 1] + ' ' + y.substring(2);
        });

        function getValues(arr) {
            return sortedMonths.map(m => {
                const found = arr.find(r => r.muaji === m);
                return found ? parseInt(found.total) : 0;
            });
        }

        const appsVals   = getValues(monthly_apps);
        const eventsVals = getValues(monthly_events);
        const reqVals    = getValues(monthly_requests);

        // KPI values from last month in each dataset
        const lastApps   = appsVals[appsVals.length - 1]     || 0;
        const lastEvents = eventsVals[eventsVals.length - 1] || 0;
        const lastReqs   = reqVals[reqVals.length - 1]       || 0;
        const totalCat   = apps_by_category.reduce((s, c) => s + parseInt(c.total || 0), 0);

        // Fill KPI strip
        const kpiStrip = document.getElementById('reports-kpi-strip');
        if (kpiStrip) {
            kpiStrip.innerHTML = `
                <div class="reports-kpi-item">
                    <div class="reports-kpi-item__val">${lastApps}</div>
                    <div class="reports-kpi-item__lbl">Aplikime</div>
                    <div class="reports-kpi-item__sub">Muaji i fundit</div>
                </div>
                <div class="reports-kpi-item">
                    <div class="reports-kpi-item__val">${lastEvents}</div>
                    <div class="reports-kpi-item__lbl">Evente</div>
                    <div class="reports-kpi-item__sub">Muaji i fundit</div>
                </div>
                <div class="reports-kpi-item">
                    <div class="reports-kpi-item__val">${lastReqs}</div>
                    <div class="reports-kpi-item__lbl">Kërkesa</div>
                    <div class="reports-kpi-item__sub">Muaji i fundit</div>
                </div>
                <div class="reports-kpi-item">
                    <div class="reports-kpi-item__val">${apps_by_category.length}</div>
                    <div class="reports-kpi-item__lbl">Kategori aktive</div>
                    <div class="reports-kpi-item__sub">${totalCat} aplikime gjithsej</div>
                </div>
            `;
        }

        // Premium card structure
        container.innerHTML = `
            <div class="report-card">
                <div class="report-card__hdr">
                    <h4>Aplikime Mujore</h4>
                    <span class="report-card__total">${lastApps} ky muaj</span>
                </div>
                <div class="report-card__body">
                    <canvas id="chart-monthly-apps" height="200"></canvas>
                </div>
            </div>
            <div class="report-card">
                <div class="report-card__hdr">
                    <h4>Evente Mujore</h4>
                    <span class="report-card__total">${lastEvents} ky muaj</span>
                </div>
                <div class="report-card__body">
                    <canvas id="chart-monthly-events" height="200"></canvas>
                </div>
            </div>
            <div class="report-card">
                <div class="report-card__hdr">
                    <h4>Kërkesa Mujore</h4>
                    <span class="report-card__total">${lastReqs} ky muaj</span>
                </div>
                <div class="report-card__body">
                    <canvas id="chart-monthly-requests" height="200"></canvas>
                </div>
            </div>
            <div class="report-card">
                <div class="report-card__hdr">
                    <h4>Aplikime sipas Kategorisë</h4>
                    <span class="report-card__total">${apps_by_category.length} kategori</span>
                </div>
                <div class="report-card__body">
                    <canvas id="chart-category" height="200"></canvas>
                </div>
            </div>
        `;

        // Shared base options
        const baseOpts = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 10,
                    cornerRadius: 8,
                    titleFont: { size: 11 },
                    bodyFont: { size: 13, weight: '700' }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, font: { size: 11 }, color: '#94a3b8' },
                    grid: { color: '#f1f5f9' },
                    border: { display: false }
                },
                x: {
                    ticks: { font: { size: 11 }, color: '#94a3b8' },
                    grid: { display: false },
                    border: { display: false }
                }
            }
        };

        // Applications bar chart — teal gradient
        const appsCtx  = document.getElementById('chart-monthly-apps').getContext('2d');
        const appsGrad = appsCtx.createLinearGradient(0, 0, 0, 220);
        appsGrad.addColorStop(0, 'rgba(0,113,93,0.90)');
        appsGrad.addColorStop(1, 'rgba(0,113,93,0.22)');
        new Chart(appsCtx, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Aplikime', data: appsVals, backgroundColor: appsGrad, borderRadius: 7, borderSkipped: false }] },
            options: { ...baseOpts }
        });

        // Events line chart — warm gradient fill
        const evCtx  = document.getElementById('chart-monthly-events').getContext('2d');
        const evGrad = evCtx.createLinearGradient(0, 0, 0, 220);
        evGrad.addColorStop(0, 'rgba(225,114,84,0.40)');
        evGrad.addColorStop(1, 'rgba(225,114,84,0.02)');
        new Chart(evCtx, {
            type: 'line',
            data: { labels, datasets: [{ label: 'Evente', data: eventsVals, borderColor: '#E17254', backgroundColor: evGrad, fill: true, tension: 0.45, pointBackgroundColor: '#E17254', pointRadius: 4, pointHoverRadius: 6, borderWidth: 2.5 }] },
            options: { ...baseOpts }
        });

        // Requests bar chart — blue gradient
        const reqCtx  = document.getElementById('chart-monthly-requests').getContext('2d');
        const reqGrad = reqCtx.createLinearGradient(0, 0, 0, 220);
        reqGrad.addColorStop(0, 'rgba(59,130,246,0.90)');
        reqGrad.addColorStop(1, 'rgba(59,130,246,0.22)');
        new Chart(reqCtx, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'Kërkesa', data: reqVals, backgroundColor: reqGrad, borderRadius: 7, borderSkipped: false }] },
            options: { ...baseOpts }
        });

        // Category doughnut
        const catLabels = apps_by_category.map(c => c.emri || 'Pa kategori');
        const catValues = apps_by_category.map(c => parseInt(c.total));
        const catColors = ['#00715D', '#E17254', '#3b82f6', '#f59e0b', '#8b5cf6', '#06b6d4'];
        new Chart(document.getElementById('chart-category'), {
            type: 'doughnut',
            data: { labels: catLabels, datasets: [{ data: catValues, backgroundColor: catColors.slice(0, catLabels.length), borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 14, font: { size: 11 }, color: '#374151', usePointStyle: true } },
                    tooltip: { backgroundColor: '#1e293b', padding: 10, cornerRadius: 8 }
                }
            }
        });

        _reportsChartsLoaded = true;
        loadReportsList();
    } catch (e) {
        container.innerHTML = '<div class="db-loading">Gabim rrjeti.</div>';
    }
}

async function loadReportsList() {
    const container = document.getElementById('reports-list');
    if (!container) return;

    try {
        const json = await apiCall('stats.php?action=reports');
        if (!json.success) { container.innerHTML = '<div class="db-loading">Gabim.</div>'; return; }

        const reports = json.data.reports;
        if (!reports.length) {
            container.innerHTML = '<div style="color:#94a3b8;font-size:0.9rem;">Nuk ka raporte ende. Klikoni "Gjenero raport" për të krijuar një.</div>';
            return;
        }

        let html = '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>ID</th><th>Tipi</th><th>Gjeneruar nga</th><th>Data</th><th>Veprime</th></tr></thead><tbody>';
        reports.forEach(r => {
            html += `<tr>
                <td><strong>#${r.id_raporti}</strong></td>
                <td>${escapeHtml(r.tipi_raportit)}</td>
                <td>${escapeHtml(r.gjeneruesi_emri || '')}</td>
                <td>${formatDate(r.gjeneruar_me)}</td>
                <td><button class="db-btn db-btn--info db-btn--sm" onclick="viewReport(${r.id_raporti}, this)">Shiko</button></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    } catch (e) {}
}

window.viewReport = function(id, btn) {
    const row = btn.closest('tr');
    const next = row.nextElementSibling;
    if (next && next.classList.contains('report-detail-row')) {
        next.remove();
        return;
    }
    // Find the report content from the already loaded data
    apiCall('stats.php?action=reports').then(json => {
        if (!json.success) return;
        const r = json.data.reports.find(r => r.id_raporti == id);
        if (!r) return;
        const detail = document.createElement('tr');
        detail.className = 'report-detail-row';
        detail.innerHTML = `<td colspan="5" style="background:#f8fafc;padding:16px;white-space:pre-wrap;font-size:0.85rem;border-left:3px solid var(--db-primary);">${escapeHtml(r.permbajtja)}</td>`;
        row.after(detail);
    });
};

async function generateReport() {
    openGenerateReportModal();
}

window.openGenerateReportModal = function() {
    const backdrop = document.getElementById('rpt-gen-modal-backdrop');
    if (backdrop) backdrop.style.display = 'flex';
};

window.closeGenerateReportModal = function() {
    const backdrop = document.getElementById('rpt-gen-modal-backdrop');
    if (backdrop) backdrop.style.display = 'none';
};

window.submitGenerateReport = async function(tipi) {
    closeGenerateReportModal();
    try {
        const json = await apiCall('stats.php?action=generate', 'POST', { tipi_raportit: tipi });
        if (json.success) {
            showToast('Raporti u gjenerua me sukses!', 'success');
            loadReportsList();
        } else {
            showToast(json.message || 'Gabim gjatë gjenerimit.', 'error');
        }
    } catch (e) {
        showToast('Gabim rrjeti.', 'error');
    }
};


// ═══════════════════════════════════════════════════════
//  MESSAGING SYSTEM
// ═══════════════════════════════════════════════════════

let _msgCurrentThread = null;
let _msgPollingTimer = null;

async function loadConversations() {
    const container = document.getElementById('msg-content');
    if (!container) return;

    try {
        const json = await apiCall('messages.php?action=conversations');
        if (!json.success) { container.innerHTML = '<div class="db-loading">Gabim.</div>'; return; }

        const convos = json.data.conversations || [];
        if (!convos.length) {
            container.innerHTML = `
                <div style="text-align:center;padding:3rem 1rem;color:#94a3b8;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:1rem;opacity:0.4;"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
                    <p style="font-size:1rem;font-weight:500;">Nuk keni biseda ende</p>
                    <p style="font-size:0.85rem;">Filloni një bisedë duke klikuar "Mesazh i ri".</p>
                </div>`;
            return;
        }

        let html = '<div class="msg-conversation-list">';
        convos.forEach(c => {
            const unread = c.unread_count > 0 ? `<span class="db-nav-badge" style="display:inline-flex;margin-left:auto;width:8px;height:8px;padding:0;font-size:0;min-width:0;border-radius:99px;"></span>` : '';
            const initial = (c.other_emri || 'P').charAt(0).toUpperCase();
            const preview = c.last_message ? escapeHtml(c.last_message.substring(0, 60)) + (c.last_message.length > 60 ? '…' : '') : '';
            const timeAgo = formatDate(c.last_time);
            html += `<div class="msg-convo-item${c.unread_count > 0 ? ' msg-convo-item--unread' : ''}" onclick="openThread(${c.other_id}, '${escapeHtml(c.other_emri).replace(/'/g, "\\'")}')">
                <div class="ud-avatar ud-avatar--active" style="width:40px;height:40px;font-size:1rem;flex-shrink:0;">${escapeHtml(initial)}</div>
                <div class="msg-convo-info">
                    <div class="msg-convo-name">${escapeHtml(c.other_emri)} ${unread}</div>
                    <div class="msg-convo-preview">${preview}</div>
                </div>
                <div class="msg-convo-time">${timeAgo}</div>
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
        return convos; // expose for deep-link caller
    } catch (e) {
        container.innerHTML = '<div class="db-loading">Gabim rrjeti.</div>';
    }
}

async function openThread(userId, userName) {
    _msgCurrentThread = userId;
    const container = document.getElementById('msg-content');
    const title = document.getElementById('msg-panel-title');
    const actions = document.getElementById('msg-header-actions');

    title.innerHTML = `<button class="db-btn db-btn--ghost" onclick="closeThread()" style="margin-right:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </button> ${escapeHtml(userName)}`;
    actions.style.display = 'none';

    container.innerHTML = '<div class="db-loading">Duke ngarkuar…</div>';

    try {
        const json = await apiCall(`messages.php?action=thread&with=${userId}&limit=50`);
        if (!json.success) { container.innerHTML = '<div class="db-loading">Gabim.</div>'; return; }

        const messages = json.data.messages; // already oldest first from API
        let html = `<div class="msg-thread" id="msg-thread-list">`;

        if (!messages.length) {
            html += '<div style="text-align:center;color:#94a3b8;padding:2rem;">Nuk ka mesazhe ende. Shkruaj mesazhin e parë!</div>';
        }

        messages.forEach(m => {
            const isMine = m.derguesi_id == CURRENT_USER_ID;
            html += `<div class="msg-bubble ${isMine ? 'msg-bubble--mine' : 'msg-bubble--theirs'}">
                <div class="msg-bubble__text">${escapeHtml(m.mesazhi)}</div>
                <div class="msg-bubble__time">${formatDate(m.krijuar_me)}</div>
            </div>`;
        });

        html += `</div>
        <div class="msg-compose">
            <textarea id="msg-input" class="msg-compose__input" placeholder="Shkruaj mesazhin…" rows="2" maxlength="2000" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMessage(${userId});}"></textarea>
            <button class="db-btn db-btn--primary msg-compose__send" onclick="sendMessage(${userId})">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            </button>
        </div>`;

        container.innerHTML = html;
        scrollThreadToBottom();
        startThreadPolling(userId);
        loadUnreadBadge();
    } catch (e) {
        container.innerHTML = '<div class="db-loading">Gabim rrjeti.</div>';
    }
}

function closeThread() {
    _msgCurrentThread = null;
    stopThreadPolling();
    const title = document.getElementById('msg-panel-title');
    const actions = document.getElementById('msg-header-actions');
    if (title) title.textContent = 'Mesazhet';
    if (actions) actions.style.display = '';
    loadConversations();
}

async function sendMessage(toUserId) {
    const input = document.getElementById('msg-input');
    const text = (input?.value || '').trim();
    if (!text) return;

    input.value = '';
    input.focus();

    try {
        const json = await apiCall('messages.php?action=send', 'POST', { marruesi_id: toUserId, mesazhi: text });
        if (json.success) {
            refreshThread(toUserId);
        } else {
            showToast(json.message || 'Gabim gjatë dërgimit.', 'error');
        }
    } catch (e) {
        showToast('Gabim rrjeti.', 'error');
    }
}

async function refreshThread(userId) {
    const list = document.getElementById('msg-thread-list');
    if (!list) return;

    try {
        const json = await apiCall(`messages.php?action=thread&with=${userId}&limit=50`);
        if (!json.success) return;

        const messages = json.data.messages;
        let html = '';
        if (!messages.length) {
            html = '<div style="text-align:center;color:#94a3b8;padding:2rem;">Nuk ka mesazhe ende.</div>';
        }
        messages.forEach(m => {
            const isMine = m.derguesi_id == CURRENT_USER_ID;
            html += `<div class="msg-bubble ${isMine ? 'msg-bubble--mine' : 'msg-bubble--theirs'}">
                <div class="msg-bubble__text">${escapeHtml(m.mesazhi)}</div>
                <div class="msg-bubble__time">${formatDate(m.krijuar_me)}</div>
            </div>`;
        });
        list.innerHTML = html;
        scrollThreadToBottom();
        loadUnreadBadge();
    } catch (e) {}
}

function scrollThreadToBottom() {
    const list = document.getElementById('msg-thread-list');
    if (list) setTimeout(() => { list.scrollTop = list.scrollHeight; }, 50);
}

function startThreadPolling(userId) {
    stopThreadPolling();
    _msgPollingTimer = setInterval(() => {
        if (_msgCurrentThread === userId) refreshThread(userId);
    }, 5000);
}

function stopThreadPolling() {
    if (_msgPollingTimer) { clearInterval(_msgPollingTimer); _msgPollingTimer = null; }
}

async function showNewConversation() {
    const container = document.getElementById('msg-content');
    const title = document.getElementById('msg-panel-title');
    const actions = document.getElementById('msg-header-actions');

    title.innerHTML = `<button class="db-btn db-btn--ghost" onclick="closeThread()" style="margin-right:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </button> Mesazh i ri`;
    actions.style.display = 'none';

    container.innerHTML = `
        <div style="padding:1rem;">
            <input type="text" id="msg-user-search" class="ud-input" placeholder="Kërko përdorues sipas emrit…"
                   oninput="searchUsersForMsg(this.value)" style="margin-bottom:1rem;">
            <div id="msg-user-results"></div>
        </div>`;
}

let _msgSearchTimeout = null;
async function searchUsersForMsg(query) {
    clearTimeout(_msgSearchTimeout);
    const results = document.getElementById('msg-user-results');
    if (!results) return;

    if (query.trim().length < 2) { results.innerHTML = '<div style="color:#94a3b8;font-size:0.85rem;">Shkruaj të paktën 2 shkronja…</div>'; return; }

    _msgSearchTimeout = setTimeout(async () => {
        try {
            const json = await apiCall(`messages.php?action=search_users&q=${encodeURIComponent(query.trim())}`);
            if (!json.success) { results.innerHTML = '<div style="color:#dc3545;">Gabim.</div>'; return; }

            const users = json.data.users || [];
            if (!users.length) { results.innerHTML = '<div style="color:#94a3b8;">Asnjë rezultat.</div>'; return; }

            let html = '';
            users.forEach(u => {
                const initial = (u.emri || 'P').charAt(0).toUpperCase();
                html += `<div class="msg-convo-item" onclick="openThread(${u.id_perdoruesi}, '${escapeHtml(u.emri).replace(/'/g, "\\'")}')">
                    <div class="ud-avatar ud-avatar--active" style="width:36px;height:36px;font-size:0.9rem;flex-shrink:0;">${escapeHtml(initial)}</div>
                    <div class="msg-convo-info">
                        <div class="msg-convo-name">${escapeHtml(u.emri)}</div>
                    </div>
                </div>`;
            });
            results.innerHTML = html;
        } catch (e) { results.innerHTML = '<div style="color:#dc3545;">Gabim rrjeti.</div>'; }
    }, 300);
}

async function loadUnreadBadge() {
    try {
        const json = await apiCall('messages.php?action=conversations');
        if (!json.success) return;
        const total = json.data.total_unread ?? (json.data.conversations || []).reduce((sum, c) => sum + (c.unread_count || 0), 0);
        const badge = document.getElementById('msg-badge');
        if (badge) {
            if (total > 0) {
                badge.textContent = total > 99 ? '99+' : String(total);
                badge.style.display = 'inline-flex';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (e) {}
}


window.toggleBlock = async function(userId, action) {
    let payload = null;

    if (action === 'block') {
        const reasonInput = await openBlockReasonModal();
        if (reasonInput === null) return;
        payload = reasonInput.trim() ? { arsye_bllokimi: reasonInput.trim() } : {};
    }

    if (action === 'unblock') {
        if (!confirm('Jeni të sigurt që doni të zhbllokoni këtë përdorues?')) return;
    }

    const json = await apiCall(`users.php?action=${action}&id=${userId}`, 'PUT', payload);
    dbToast(json.message || json.data?.message || 'U krye.', json.success ? 'success' : 'danger');
    
    if (json.success) {
        loadUsers();
        const detailPanel = document.getElementById('panel-user-detail');
        if (detailPanel && detailPanel.classList.contains('active')) {
            setTimeout(() => openUserDetail(userId), 300);
        }
    }
};

window.viewRequestFlags = async function(requestId) {
    showUserActivityModal('Duke ngarkuar raportimet…');
    const json = await apiCall(`help_requests.php?action=get_flags&id=${requestId}`);
    if (!json.success) { dbToast('Gabim gjatë ngarkimit.', 'danger'); return; }

    const flags = json.data.flags;
    let body = '';
    if (!flags.length) {
        body = '<p style="color:#94a3b8;padding:20px 0;text-align:center;">Nuk ka raporte aktive.</p>';
    } else {
        body = '<div class="db-table-responsive"><table class="db-table"><thead><tr><th>Raportuesi</th><th>Arsyeja</th><th>Data</th></tr></thead><tbody>';
        flags.forEach(f => {
            body += `<tr>
                <td><strong>${escapeHtml(f.raportuesi_emri)}</strong></td>
                <td>${f.arsye ? escapeHtml(f.arsye) : '<span style="color:#94a3b8;">Pa arsye</span>'}</td>
                <td>${formatDate(f.krijuar_me)}</td>
            </tr>`;
        });
        body += '</tbody></table></div>';
    }
    updateUserActivityModal('Raportimet e kërkesës', body);
};

// ── Change User Role (Super Admin only) ──
window.changeUserRole = async function(userId, currentRole) {
    const newRole = currentRole === 'admin' ? 'volunteer' : 'admin';
    const label = STATUS_LABELS[newRole] || newRole;
    if (!confirm(`Ndryshoni rolin e përdoruesit në "${label}"?`)) return;

    const json = await apiCall(`users.php?action=change_role&id=${userId}`, 'PUT', { roli: newRole });
    if (json.success) {
        loadUsers();
    } else {
        alert(json.message || 'Gabim gjatë ndryshimit të rolit.');
    }
};


/* ═══════════════════════════════════════════════════════════
   PROFILE PANEL
   ═══════════════════════════════════════════════════════════ */

// Color palette (mirrors PHP ts_profile_color_palette())
const PROFILE_COLORS = {
    emerald: { label: 'Emerald', from: '#003229', mid: '#00715D', to: '#009e7e' },
    ocean:   { label: 'Ocean',   from: '#0b2a52', mid: '#1d4ed8', to: '#2563eb' },
    sunset:  { label: 'Sunset',  from: '#7c2d12', mid: '#ea580c', to: '#f97316' },
    rose:    { label: 'Rose',    from: '#881337', mid: '#be185d', to: '#e11d48' },
    violet:  { label: 'Violet',  from: '#3b0764', mid: '#7e22ce', to: '#9333ea' },
    slate:   { label: 'Slate',   from: '#1e293b', mid: '#334155', to: '#475569' },
    teal:    { label: 'Teal',    from: '#134e4a', mid: '#0d9488', to: '#14b8a6' },
    amber:   { label: 'Amber',   from: '#78350f', mid: '#d97706', to: '#f59e0b' },
    indigo:  { label: 'Indigo',  from: '#312e81', mid: '#4f46e5', to: '#6366f1' },
    pink:    { label: 'Pink',    from: '#831843', mid: '#ec4899', to: '#f472b6' },
    lime:    { label: 'Lime',    from: '#365314', mid: '#84cc16', to: '#a3e635' },
    cyan:    { label: 'Cyan',    from: '#082f49', mid: '#0891b2', to: '#06b6d4' },
};

let _selectedColor = 'emerald';

function getAdminProfileTheme(key) {
    return PROFILE_COLORS[key] || PROFILE_COLORS.emerald;
}

function applyAdminProfileColor(key) {
    const theme = getAdminProfileTheme(key);
    const body = document.body;
    if (body) {
        body.style.setProperty('--db-profile-from', theme.from);
        body.style.setProperty('--db-profile-mid', theme.mid);
        body.style.setProperty('--db-profile-to', theme.to);
    }

    const initialsAvatar = document.getElementById('profile-avatar-initials');
    if (initialsAvatar) {
        initialsAvatar.style.background = `linear-gradient(135deg, ${theme.from}, ${theme.to})`;
    }

    const sidebarAvatar = document.getElementById('db-sidebar-avatar');
    if (sidebarAvatar) {
        sidebarAvatar.style.background = `linear-gradient(135deg, ${theme.from}, ${theme.to})`;
    }

    const colorLabel = document.getElementById('admin-color-label');
    if (colorLabel) {
        colorLabel.textContent = theme.label;
    }
}

function initColorGrid(selectedKey) {
    const grid = document.getElementById('profile-color-grid');
    if (!grid) return;
    _selectedColor = selectedKey || 'emerald';
    let html = '';
    for (const [key, val] of Object.entries(PROFILE_COLORS)) {
        const active = key === _selectedColor ? ' active' : '';
        html += `<button type="button" class="db-color-swatch${active}" title="${val.label}"
                    style="background:${val.mid};"
                    onclick="selectColorSwatch('${key}')"></button>`;
    }
    grid.innerHTML = html;
    applyAdminProfileColor(_selectedColor);
}

window.selectColorSwatch = function(key) {
    _selectedColor = key;
    document.querySelectorAll('.db-color-swatch').forEach(el => {
        el.classList.toggle('active', el.getAttribute('onclick') === `selectColorSwatch('${key}')`);
    });
    applyAdminProfileColor(key);
};

window.loadAdminProfile = async function() {
    try {
        const json = await apiCall('auth.php?action=me');
        if (!json.success) return;
        const d = json.data;

        const emriEl = document.getElementById('admin-emri');
        if (emriEl) emriEl.value = d.emri || '';

        const bioEl = document.getElementById('admin-bio');
        if (bioEl) {
            bioEl.value = d.bio || '';
            const counter = document.getElementById('admin-bio-counter');
            if (counter) counter.textContent = `${(d.bio || '').length}/300`;
            bioEl.addEventListener('input', function() {
                const counter2 = document.getElementById('admin-bio-counter');
                if (counter2) counter2.textContent = `${this.value.length}/300`;
            });
        }

        const emailNotif = document.getElementById('admin-email-notif');
        if (emailNotif) emailNotif.checked = !!parseInt(d.email_notifications ?? 1);

        const profilePublic = document.getElementById('admin-profile-public');
        if (profilePublic) profilePublic.checked = !!parseInt(d.profile_public ?? 1);

        initColorGrid(d.profile_color || 'emerald');
        applyAdminProfileColor(d.profile_color || 'emerald');

        setAdminProfileAvatar(d.profile_picture || '');
    } catch (e) {
        console.error('[loadAdminProfile]', e);
    }
};

function setAdminProfileAvatar(profilePicture) {
    const imgEl = document.getElementById('profile-avatar-img');
    const initEl = document.getElementById('profile-avatar-initials');
    const deleteBtn = document.getElementById('profile-avatar-delete-btn');

    if (!imgEl || !initEl) return;

    if (profilePicture) {
        imgEl.src = profilePicture;
        imgEl.style.display = 'block';
        initEl.style.display = 'none';
        if (deleteBtn) deleteBtn.style.display = 'flex';
        imgEl.onerror = () => {
            imgEl.removeAttribute('src');
            imgEl.style.display = 'none';
            initEl.style.display = '';
            if (deleteBtn) deleteBtn.style.display = 'none';
        };
        return;
    }

    imgEl.removeAttribute('src');
    imgEl.style.display = 'none';
    initEl.style.display = '';
    if (deleteBtn) deleteBtn.style.display = 'none';
}

window.adminSaveName = async function() {
    const emri = (document.getElementById('admin-emri')?.value || '').trim();
    const st = document.getElementById('admin-name-status');
    if (!emri) {
        if (st) { st.style.color = '#dc2626'; st.textContent = 'Emri nuk mund t\u00eb jet\u00eb bosh.'; }
        return;
    }
    try {
        const json = await apiCall('users.php?action=update_profile', 'PUT', { emri });
        if (st) {
            st.style.color = json.success ? '#16a34a' : '#dc2626';
            st.textContent = json.success ? 'Emri u ruajt.' : (json.message || 'Gabim.');
        }
    } catch (e) {
        if (st) { st.style.color = '#dc2626'; st.textContent = 'Gabim rrjeti.'; }
    }
};

window.adminSaveBio = async function() {
    const bio = (document.getElementById('admin-bio')?.value || '').trim();
    const st = document.getElementById('admin-bio-status');
    try {
        const json = await apiCall('users.php?action=update_profile', 'PUT', { bio });
        if (st) { st.style.color = json.success ? '#16a34a' : '#dc2626'; st.textContent = json.success ? 'Bio u ruajt.' : (json.message || 'Gabim.'); }
    } catch (e) { if (st) { st.style.color = '#dc2626'; st.textContent = 'Gabim rrjeti.'; } }
};

window.adminSaveColor = async function() {
    const st = document.getElementById('admin-color-status');
    try {
        const json = await apiCall('users.php?action=update_profile', 'PUT', { profile_color: _selectedColor });
        if (json.success) {
            applyAdminProfileColor(_selectedColor);
        }
        if (st) { st.style.color = json.success ? '#16a34a' : '#dc2626'; st.textContent = json.success ? 'Ngjyra u ruajt.' : (json.message || 'Gabim.'); }
    } catch (e) { if (st) { st.style.color = '#dc2626'; st.textContent = 'Gabim rrjeti.'; } }
};

window.adminSaveNotifPrefs = async function() {
    const checked = document.getElementById('admin-email-notif')?.checked ? 1 : 0;
    const st = document.getElementById('admin-notif-status');
    try {
        const json = await apiCall('users.php?action=update_profile', 'PUT', { email_notifications: checked });
        if (st) { st.style.color = json.success ? '#16a34a' : '#dc2626'; st.textContent = json.success ? 'Preferencat u ruajtën.' : (json.message || 'Gabim.'); }
    } catch (e) { if (st) { st.style.color = '#dc2626'; st.textContent = 'Gabim rrjeti.'; } }
};

window.adminSaveVisibility = async function() {
    const checked = document.getElementById('admin-profile-public')?.checked ? 1 : 0;
    const st = document.getElementById('admin-visibility-status');
    try {
        const json = await apiCall('users.php?action=update_profile', 'PUT', { profile_public: checked });
        if (st) { st.style.color = json.success ? '#16a34a' : '#dc2626'; st.textContent = json.success ? 'Dukshmëria u ruajt.' : (json.message || 'Gabim.'); }
    } catch (e) { if (st) { st.style.color = '#dc2626'; st.textContent = 'Gabim rrjeti.'; } }
};

window.adminUploadPicture = async function(input) {
    if (!input.files || !input.files[0]) return;
    const st = document.getElementById('profile-avatar-status');
    if (st) { st.textContent = 'Duke ngarkuar…'; st.style.color = ''; }

    const formData = new FormData();
    formData.append('image', input.files[0]);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    try {
        const res = await fetch('/TiranaSolidare/api/users.php?action=upload_profile_picture', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf },
            credentials: 'same-origin',
            body: formData,
        });
        const json = await res.json();
        if (json.success) {
            // API returns data.url — already a root-relative path starting with /TiranaSolidare/
            const path = json.data?.url;
            if (path) setAdminProfileAvatar(path + '?t=' + Date.now());
            if (st) { st.style.color = '#16a34a'; st.textContent = 'Foto u ngarkua me sukses!'; }
        } else {
            if (st) { st.style.color = '#dc2626'; st.textContent = json.message || 'Ngarkimi dështoi.'; }
        }
    } catch (e) {
        if (st) { st.style.color = '#dc2626'; st.textContent = 'Gabim rrjeti.'; }
    }
    input.value = '';
};

window.adminDeletePicture = async function() {
    const st = document.getElementById('profile-avatar-status');
    if (!confirm('Jeni i sigurt që doni të fshini foton e profilit?')) return;

    try {
        const json = await apiCall('users.php?action=delete_profile_picture', 'DELETE');
        if (json.success) {
            setAdminProfileAvatar('');
            if (st) {
                st.style.color = '#16a34a';
                st.textContent = json.message || 'Fotoja u fshi.';
            }
        } else if (st) {
            st.style.color = '#dc2626';
            st.textContent = json.message || 'Gabim gjatë fshirjes së fotos.';
        }
    } catch (e) {
        if (st) {
            st.style.color = '#dc2626';
            st.textContent = 'Gabim rrjeti.';
        }
    }
};


/* ═══════════════════════════════════════════════════════════
   BROADCAST NOTIFICATIONS
   ═══════════════════════════════════════════════════════════ */

window.toggleBroadcastForm = function() {
    const form = document.getElementById('broadcast-form');
    if (!form) return;
    const isHidden = form.style.display === 'none' || !form.style.display;
    form.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        const msgEl = document.getElementById('broadcast-msg');
        if (msgEl) msgEl.focus();
    }
};

window.sendBroadcast = async function() {
    const mesazhi = (document.getElementById('broadcast-msg')?.value || '').trim();
    const roli = document.getElementById('broadcast-role')?.value || 'all';
    const linku = (document.getElementById('broadcast-link')?.value || '').trim() || null;
    const st = document.getElementById('broadcast-status');

    if (!mesazhi) {
        if (st) { st.style.color = '#dc2626'; st.textContent = 'Shkruaj mesazhin.'; }
        return;
    }
    if (st) { st.textContent = 'Duke dërguar…'; st.style.color = ''; }

    try {
        const json = await apiCall('notifications.php?action=broadcast', 'POST', { mesazhi, roli, linku });
        if (json.success) {
            if (st) { st.style.color = '#16a34a'; st.textContent = json.data?.message || 'Njoftimi u dërgua.'; }
            document.getElementById('broadcast-msg').value = '';
            if (document.getElementById('broadcast-link')) document.getElementById('broadcast-link').value = '';
            setTimeout(() => toggleBroadcastForm(), 2500);
        } else {
            if (st) { st.style.color = '#dc2626'; st.textContent = json.message || 'Gabim.'; }
        }
    } catch (e) {
        if (st) { st.style.color = '#dc2626'; st.textContent = 'Gabim rrjeti.'; }
    }
};


/* ═══════════════════════════════════════════════════════════
   CATEGORIES PANEL
   ═══════════════════════════════════════════════════════════ */

// Color palette for category letter-avatar tiles (cycles by category ID)
const _CAT_PALETTE = [
    { bg: '#e0f2fe', text: '#0369a1' },
    { bg: '#dcfce7', text: '#15803d' },
    { bg: '#fef9c3', text: '#92400e' },
    { bg: '#fce7f3', text: '#9d174d' },
    { bg: '#ede9fe', text: '#6d28d9' },
    { bg: '#ffedd5', text: '#9a3412' },
    { bg: '#f0fdf4', text: '#065f46' },
];

window.loadCategories = async function() {
    const container = document.getElementById('category-list');
    if (!container) return;
    container.innerHTML = '<div class="db-loading">Duke ngarkuar kategoritë…</div>';

    try {
        const json = await apiCall('categories.php?action=list');
        if (!json.success) { container.innerHTML = `<div class="db-empty">${escapeHtml(json.message || 'Gabim.')}</div>`; return; }

        const cats = json.data.categories || [];
        window._catList = cats; // store for delete modal

        if (cats.length === 0) {
            container.innerHTML = '<div class="db-cat-empty">Nuk ka kategori. Krijoni të parën duke klikuar "+ Krijo Kategori".</div>';
            return;
        }

        let html = '<div class="db-cat-grid">';
        cats.forEach(c => {
            const pal = _CAT_PALETTE[c.id_kategoria % _CAT_PALETTE.length];
            const ltr = escapeHtml((c.emri || '?')[0].toUpperCase());
            const cnt = parseInt(c.event_count) || 0;
            const bannerPath = (c.banner_path || '').trim();
            const mediaHtml = bannerPath
                ? `<div class="db-cat-media"><img src="${escapeHtml(bannerPath)}" alt="${escapeHtml(c.emri || 'Kategori')}" loading="lazy"></div>`
                : `<div class="db-cat-avatar" style="background:${pal.bg};color:${pal.text};">${ltr}</div>`;
            html += `<div class="db-cat-card" id="cat-card-${c.id_kategoria}">
                ${mediaHtml}
                <div class="db-cat-card__body">
                    <span class="db-cat-card__name" id="cat-name-${c.id_kategoria}">${escapeHtml(c.emri)}</span>
                    <span class="db-cat-card__count">${cnt} event${cnt === 1 ? '' : 'e'}</span>
                </div>
                <div class="db-cat-card__actions">
                    <button class="db-cat-btn db-cat-btn--edit" title="Riemërto" onclick="renameCategoryInline(${c.id_kategoria})">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                    </button>
                    <button class="db-cat-btn db-cat-btn--del" title="Fshi" onclick="deleteCategoryModal(${c.id_kategoria})">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="m19 6-.867 13A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.867L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                    </button>
                </div>
            </div>`;
        });
        html += `</div><p style="margin-top:12px;font-size:0.82rem;color:var(--db-text-muted);">${cats.length} kategori gjithsej</p>`;
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="db-empty">Gabim rrjeti.</div>';
    }
};

function resetCategoryCreateForm() {
    const nameInput = document.getElementById('new-category-name');
    const bannerInput = document.getElementById('new-category-banner');
    const bannerPreview = document.getElementById('cat-banner-preview');
    const bannerFilename = document.getElementById('cat-banner-filename');
    const status = document.getElementById('cat-create-status');

    if (nameInput) nameInput.value = '';
    if (bannerInput) bannerInput.value = '';
    if (bannerPreview) {
        bannerPreview.src = '';
        bannerPreview.style.display = 'none';
    }
    if (bannerFilename) bannerFilename.textContent = '';
    if (status) status.textContent = '';
}

window.toggleCategoryForm = function() {
    const form = document.getElementById('category-create-form');
    if (!form) return;
    const isHidden = form.style.display === 'none' || !form.style.display;
    form.style.display = isHidden ? 'block' : 'none';
    if (isHidden) {
        resetCategoryCreateForm();
        const inp = document.getElementById('new-category-name');
        if (inp) inp.focus();
    } else {
        resetCategoryCreateForm();
    }
};

window.createCategory = async function() {
    const emri = (document.getElementById('new-category-name')?.value || '').trim();
    const bannerInput = document.getElementById('new-category-banner');
    const st = document.getElementById('cat-create-status');
    if (!emri) { if (st) { st.style.color = '#dc2626'; st.textContent = 'Shkruaj emrin.'; } return; }
    try {
        let bannerPath = '';
        if (bannerInput && bannerInput.files.length > 0) {
            const uploadFd = new FormData();
            uploadFd.append('image', bannerInput.files[0]);
            const csrfToken = typeof getAdminCSRF === 'function'
                ? getAdminCSRF()
                : (document.querySelector('meta[name="csrf-token"]')?.content || '');
            const upRes = await fetch('/TiranaSolidare/api/upload.php', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin',
                body: uploadFd
            });
            const upJson = await upRes.json();
            if (!upJson.success) {
                if (st) { st.style.color = '#dc2626'; st.textContent = upJson.message || 'Gabim gjatë ngarkimit të bannerit.'; }
                return;
            }
            bannerPath = upJson.data?.url || '';
        }

        const payload = { emri };
        if (bannerPath) payload.banner_path = bannerPath;

        const json = await apiCall('categories.php?action=create', 'POST', payload);
        if (json.success) {
            if (st) { st.style.color = '#16a34a'; st.textContent = 'Kategoria u krijua!'; }
            setTimeout(() => { toggleCategoryForm(); loadCategories(); loadCategoryDropdown(); }, 800);
        } else {
            if (st) { st.style.color = '#dc2626'; st.textContent = json.message || 'Gabim.'; }
        }
    } catch (e) {
        if (st) { st.style.color = '#dc2626'; st.textContent = 'Gabim rrjeti.'; }
    }
};

// Inline rename — transforms the name <span> into an <input> right in place
window.renameCategoryInline = function(id) {
    const nameEl = document.getElementById(`cat-name-${id}`);
    if (!nameEl || nameEl.tagName === 'INPUT') return;
    const currentName = nameEl.textContent.trim();

    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentName;
    input.className = 'db-cat-inline-input';
    input.id = `cat-name-${id}`;
    nameEl.replaceWith(input);
    input.focus();
    input.select();

    const restore = (name) => {
        const span = document.createElement('span');
        span.className = 'db-cat-card__name';
        span.id = `cat-name-${id}`;
        span.textContent = name;
        const cur = document.getElementById(`cat-name-${id}`);
        if (cur) cur.replaceWith(span);
    };

    const save = () => {
        const newName = (input.value || '').trim();
        if (!newName || newName === currentName) { restore(currentName); return; }
        restore(newName); // optimistic
        renameCategory(id, newName, currentName);
    };

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); save(); }
        if (e.key === 'Escape') { restore(currentName); }
    });
    input.addEventListener('blur', save);
};

window.renameCategory = async function(id, emri, fallbackName) {
    try {
        const json = await apiCall(`categories.php?action=update&id=${id}`, 'PUT', { emri });
        if (json.success) {
            const az = document.querySelector(`#cat-card-${id} .db-cat-avatar`);
            if (az) az.textContent = (emri[0] || '?').toUpperCase();
            if (window._catList) {
                const item = window._catList.find(c => c.id_kategoria == id);
                if (item) item.emri = emri;
            }
            loadCategoryDropdown();
        } else {
            const nameEl = document.getElementById(`cat-name-${id}`);
            if (nameEl) nameEl.textContent = fallbackName || emri;
            dbToast(json.message || 'Gabim gjatë riemërtimit.', 'danger');
        }
    } catch (e) {
        const nameEl = document.getElementById(`cat-name-${id}`);
        if (nameEl) nameEl.textContent = fallbackName || emri;
        dbToast('Gabim rrjeti.', 'danger');
    }
};

let _catDelId = null;

// Delete category — opens a proper modal with optional event reassignment
window.deleteCategoryModal = function(id) {
    _catDelId = id;
    const cats   = window._catList || [];
    const target = cats.find(c => c.id_kategoria == id);
    if (!target) return;

    const name   = target.emri || '';
    const count  = parseInt(target.event_count) || 0;
    const others = cats.filter(c => c.id_kategoria != id);
    const otherOpts = others.length > 0
        ? others.map(c => `<option value="${c.id_kategoria}">${escapeHtml(c.emri)}</option>`).join('')
        : `<option value="" disabled>Nuk ka kategori të tjera</option>`;

    const reassignBlock = count > 0 ? `
        <label class="db-radio-option">
            <input type="radio" name="cat-del-action" value="orphan" checked>
            <div class="db-radio-option__text">
                <strong>Lëri eventet pa kategori</strong>
                <p>Eventet e kësaj kategorie do të shfaqen si "Pa Kategori".</p>
            </div>
        </label>
        <label class="db-radio-option">
            <input type="radio" name="cat-del-action" value="reassign">
            <div class="db-radio-option__text">
                <strong>Zhvendos eventet tek kategori tjetër</strong>
                <p>Zgjidhni ku do të shkojnë:</p>
                <select id="cat-del-reassign-target" ${others.length === 0 ? 'disabled' : ''}>
                    <option value="">-- Zgjidhni kategorinë --</option>
                    ${otherOpts}
                </select>
            </div>
        </label>` : '';

    const modal = document.createElement('div');
    modal.className = 'db-overlay';
    modal.id = 'cat-del-modal';
    modal.innerHTML = `
        <div class="db-dialog">
            <div class="db-dialog__header">
                <h4>Fshi kategorinë?</h4>
                <button class="db-dialog__close" onclick="closeCategoryDeleteModal()" aria-label="Mbyll">&times;</button>
            </div>
            <div class="db-dialog__body">
                <div class="db-dialog__warning">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>
                    <div class="db-dialog__warning-text">
                        <strong>"${escapeHtml(name)}"</strong>
                        <p>${count > 0 ? `Ka <b>${count}</b> event${count === 1 ? '' : 'e'} të lidhura.` : 'Nuk ka evente — fshirja është e sigurt.'}</p>
                    </div>
                </div>
                ${reassignBlock}
            </div>
            <div class="db-dialog__footer">
                <button class="db-btn db-btn--ghost" onclick="closeCategoryDeleteModal()">Anulo</button>
                <button class="db-btn db-btn--danger" onclick="executeCategoryDelete()">Fshi kategorinë</button>
            </div>
        </div>`;
    document.body.appendChild(modal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeCategoryDeleteModal(); });
};

window.closeCategoryDeleteModal = function() {
    const modal = document.getElementById('cat-del-modal');
    if (modal) modal.remove();
    _catDelId = null;
};

window.executeCategoryDelete = async function() {
    if (!_catDelId) return;
    const id     = _catDelId;
    const cats   = window._catList || [];
    const target = cats.find(c => c.id_kategoria == id);
    const count  = parseInt(target?.event_count) || 0;

    let url = `categories.php?action=delete&id=${id}`;

    if (count > 0) {
        const action = document.querySelector('input[name="cat-del-action"]:checked')?.value || 'orphan';
        if (action === 'reassign') {
            const reassignTo = document.getElementById('cat-del-reassign-target')?.value;
            if (!reassignTo) {
                dbToast('Zgjidhni kategorinë ku do të zhvendosen eventet.', 'danger');
                return;
            }
            url += `&reassign_to=${reassignTo}`;
        }
    }

    closeCategoryDeleteModal();

    try {
        const json = await apiCall(url, 'DELETE');
        if (json.success) {
            const card = document.getElementById(`cat-card-${id}`);
            if (card) {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.88)';
                card.style.transition = 'opacity 0.25s, transform 0.25s';
                setTimeout(() => { card.remove(); }, 260);
            }
            if (window._catList) window._catList = window._catList.filter(c => c.id_kategoria != id);
            loadCategoryDropdown();
            dbToast(json.message || 'Kategoria u fshi.', 'success');
        } else {
            dbToast(json.message || 'Gabim gjatë fshirjes.', 'danger');
        }
    } catch (e) {
        dbToast('Gabim rrjeti.', 'danger');
    }
};


/* ═══════════════════════════════════════════════════════════
   AUDIT LOG PANEL
   ═══════════════════════════════════════════════════════════ */

window.loadAuditLog = async function(page = 1) {
    const container = document.getElementById('audit-log-container');
    if (!container) return;
    container.innerHTML = '<div class="db-loading">Duke ngarkuar regjistrin…</div>';

    const dateFrom = document.getElementById('audit-date-from')?.value || '';
    const dateTo   = document.getElementById('audit-date-to')?.value   || '';
    const action   = document.getElementById('audit-filter-action')?.value?.trim() || '';

    let url = `stats.php?action=admin_log&page=${page}`;
    if (dateFrom) url += `&date_from=${encodeURIComponent(dateFrom)}`;
    if (dateTo)   url += `&date_to=${encodeURIComponent(dateTo)}`;
    if (action)   url += `&veprim=${encodeURIComponent(action)}`;

    try {
        const json = await apiCall(url);
        if (!json.success) { container.innerHTML = `<div class="db-empty">${escapeHtml(json.message || 'Gabim.')}</div>`; return; }

        const { logs = [], total = 0, total_pages = 1 } = json.data;

        if (!logs.length) {
            container.innerHTML = '<div class="db-empty" style="padding:40px;text-align:center;color:var(--db-text-muted);">Nuk ka regjistrime për filtrat e zgjedhura.</div>';
            return;
        }

        const actionBadgeClass = (v) => {
            const k = (v || '').toLowerCase();
            if (k.includes('block'))     return 'db-log-action--block';
            if (k.includes('unblock'))   return 'db-log-action--unblock';
            if (k.includes('delete') || k.includes('fshi')) return 'db-log-action--delete';
            if (k.includes('create') || k.includes('krijoi')) return 'db-log-action--create';
            if (k.includes('update') || k.includes('përdit')) return 'db-log-action--update';
            if (k.includes('approv') || k.includes('pranoi')) return 'db-log-action--approve';
            if (k.includes('reject') || k.includes('refuzoi')) return 'db-log-action--reject';
            if (k.includes('broadcast')) return 'db-log-action--broadcast';
            if (k.includes('role') || k.includes('rol'))  return 'db-log-action--role';
            return '';
        };

        let html = `<div class="db-table-wrap"><table class="db-table">
            <thead><tr>
                <th>#</th>
                <th>Admin</th>
                <th>Veprimi</th>
                <th>Objekti</th>
                <th>Detaje</th>
                <th>Data</th>
            </tr></thead><tbody>`;

        logs.forEach(l => {
            const cls = actionBadgeClass(l.veprim);
            const target = l.target_type ? `${escapeHtml(l.target_type)} #${l.target_id || ''}` : '—';
            const detaje = l.detaje ? escapeHtml(l.detaje) : '—';
            html += `<tr>
                <td style="color:var(--db-text-muted);font-size:0.8rem;">${l.id}</td>
                <td><strong>${escapeHtml(l.admin_emri || '')} <span style="font-size:0.72rem;color:var(--db-text-muted)">#${l.admin_id}</span></strong></td>
                <td><span class="db-log-action ${cls}">${escapeHtml(l.veprim)}</span></td>
                <td style="font-size:0.83rem;">${target}</td>
                <td><span class="db-audit-detail" title="${detaje}">${detaje}</span></td>
                <td style="font-size:0.82rem;white-space:nowrap;">${formatDate(l.krijuar_me)}</td>
            </tr>`;
        });

        html += `</tbody></table></div>`;
        html += `<div style="margin-top:10px;font-size:0.82rem;color:var(--db-text-muted);">${total} regjistrime gjithsej</div>`;

        // Pagination
        if (total_pages > 1) {
            html += `<div class="db-audit-pagination">`;
            if (page > 1) html += `<button class="db-audit-page-btn" onclick="loadAuditLog(${page - 1})">← Para</button>`;
            for (let p = Math.max(1, page - 2); p <= Math.min(total_pages, page + 2); p++) {
                html += `<button class="db-audit-page-btn${p === page ? ' active' : ''}" onclick="loadAuditLog(${p})">${p}</button>`;
            }
            if (page < total_pages) html += `<button class="db-audit-page-btn" onclick="loadAuditLog(${page + 1})">Pas →</button>`;
            html += `</div>`;
        }

        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = '<div class="db-empty">Gabim rrjeti.</div>';
    }
};