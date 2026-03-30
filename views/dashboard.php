<?php
// views/dashboard.php — Admin Panel (Admin only)
require_once __DIR__ . '/../includes/functions.php'; // must come first — sets ini_set() cookie params before session_start()
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /TiranaSolidare/views/login.php");
    exit();
}

// Redirect volunteers to their own panel
if (!in_array(ts_normalize_value($_SESSION['roli'] ?? ''), ['admin', 'super_admin'], true)) {
    header("Location: /TiranaSolidare/views/volunteer_panel.php");
    exit();
}

$isSuperAdmin = ts_normalize_value($_SESSION['roli'] ?? '') === 'super_admin';

$isAdmin = true;
$userEmri = htmlspecialchars($_SESSION['emri'] ?? 'Përdorues');
$userRoli = htmlspecialchars($_SESSION['roli'] ?? 'volunteer');
$userEmail = htmlspecialchars($_SESSION['email'] ?? '');
$userInitial = mb_strtoupper(mb_substr($_SESSION['emri'] ?? 'P', 0, 1));
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?= csrf_meta() ?>
  <title>Paneli — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=<?= filemtime(__DIR__.'/../public/assets/styles/main.css') ?>">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/dashboard.css?v=<?= filemtime(__DIR__.'/../public/assets/styles/dashboard.css') ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/TiranaSolidare/assets/css/map.css?v=<?= filemtime(__DIR__.'/../assets/css/map.css') ?>">
  <script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/TiranaSolidare/sw.js');
  }
  </script>
  <!-- Emergency panel visibility override – cannot be cached -->
  <style>
    .db-panel { display: none !important; }
    .db-panel.active { display: block !important; opacity: 1 !important; visibility: visible !important; }
  </style>
</head>
<body class="db-body">

<!-- ═══════════════════════════════════════════════════════════
     SIDEBAR NAVIGATION
     ═══════════════════════════════════════════════════════════ -->
<aside class="db-sidebar" id="db-sidebar">
  <div class="db-sidebar__header">
    <a href="/TiranaSolidare/public/" class="db-sidebar__logo">
      <img src="/TiranaSolidare/public/assets/images/logo.png" alt="Tirana Solidare" style="width:32px;height:32px;object-fit:contain;">
      <span>Tirana Solidare</span>
    </a>
    <button class="db-sidebar__close" onclick="toggleSidebar()" aria-label="Close sidebar">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
  </div>

  <nav class="db-sidebar__nav">
    <button class="db-nav-item active" data-panel="overview" onclick="switchPanel('overview', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
      <span>Përmbledhje</span>
    </button>

    <?php if ($isAdmin): ?>
    <button class="db-nav-item" data-panel="events" onclick="switchPanel('events', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
      <span>Eventet</span>
    </button>
    <button class="db-nav-item" data-panel="users" onclick="switchPanel('users', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Përdoruesit</span>
    </button>
    <button class="db-nav-item" data-panel="requests" onclick="switchPanel('requests', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      <span>Kërkesat</span>
    </button>
    <button class="db-nav-item" data-panel="reports" onclick="switchPanel('reports', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg>
      <span>Raportet</span>
    </button>
    <button class="db-nav-item" data-panel="categories" onclick="switchPanel('categories', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
      <span>Kategoritë</span>
    </button>
    <?php if ($isSuperAdmin): ?>
    <button class="db-nav-item" data-panel="audit" onclick="switchPanel('audit', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
      <span>Auditimi</span>
    </button>
    <?php endif; ?>
    <?php endif; ?>

    <button class="db-nav-item" data-panel="messages" onclick="switchPanel('messages', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
      <span>Mesazhet</span>
      <span class="db-nav-badge" id="msg-badge" style="display:none"></span>
    </button>

    <button class="db-nav-item" data-panel="notifications" onclick="switchPanel('notifications', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
      <span>Njoftimet</span>
      <span class="db-nav-badge" id="notif-badge" style="display:none"></span>
    </button>

    <button class="db-nav-item" data-panel="profile" onclick="switchPanel('profile', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
      <span>Profili im</span>
    </button>
  </nav>

  <!-- Sidebar user card -->
  <div class="db-sidebar__user">
    <div class="db-sidebar__avatar" onclick="switchPanel('profile', document.querySelector('[data-panel=profile]'))" style="cursor:pointer;">
      <?= $userInitial ?>
    </div>
    <div class="db-sidebar__user-info">
      <strong><?= $userEmri ?></strong>
      <span><?= $userRoli ?></span>
    </div>
    <form method="POST" action="/TiranaSolidare/src/actions/logout.php" style="display:inline;">
      <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <button type="submit" class="db-sidebar__logout" title="Dil">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
      </button>
    </form>
  </div>
</aside>

<!-- ═══════════════════════════════════════════════════════════
     MAIN CONTENT AREA
     ═══════════════════════════════════════════════════════════ -->
<main class="db-main" id="db-main">

  <!-- Top bar (mobile) -->
  <header class="db-topbar">
    <button class="db-topbar__menu" onclick="toggleSidebar()" aria-label="Open menu">
      <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
    </button>
    <h1 class="db-topbar__title">Paneli</h1>
    <div class="db-topbar__right">
      <span class="db-topbar__role db-topbar__role--<?= $isAdmin ? 'admin' : 'vol' ?>"><?= $isSuperAdmin ? 'Super Admin' : e($userRoli) ?></span>
    </div>
  </header>

  <!-- ═══════════════ PANEL: OVERVIEW ═══════════════ -->
  <div class="db-panel active" id="panel-overview">
    <section class="db-welcome">
      <svg class="db-welcome__blob" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(0,113,93,0.06)" d="M44.7,-76.4C58.8,-69.2,71.8,-58.7,79.6,-45.1C87.4,-31.5,90.1,-15.7,88.5,-0.9C86.9,13.9,81.1,27.8,72.6,39.6C64.1,51.4,52.9,61.2,40.1,68.4C27.3,75.6,13.7,80.3,-0.8,81.7C-15.3,83.1,-30.5,81.3,-43.4,74.2C-56.2,67.2,-66.7,55,-73.8,41.2C-80.8,27.3,-84.4,11.7,-83.5,-3.5C-82.6,-18.7,-77.2,-33.4,-68,-45.1C-58.8,-56.8,-45.9,-65.4,-32.3,-72.8C-18.7,-80.3,-9.3,-86.5,3.2,-91.9C15.7,-97.4,30.5,-83.6,44.7,-76.4Z" transform="translate(100 100)"/></svg>
      <div class="db-welcome__text">
        <h2>Mirësevini, <?= $userEmri ?>!</h2>
        <p><?= $isAdmin ? 'Menaxhoni platformën nga paneli juaj admin.' : 'Shikoni eventet, aplikimet dhe kërkesat tuaja.' ?></p>
      </div>
    </section>
    <div class="db-stats" id="dashboard-stats">
      <div class="db-stat db-stat--loading"><div class="db-stat__shimmer"></div></div>
      <div class="db-stat db-stat--loading"><div class="db-stat__shimmer"></div></div>
      <div class="db-stat db-stat--loading"><div class="db-stat__shimmer"></div></div>
      <div class="db-stat db-stat--loading"><div class="db-stat__shimmer"></div></div>
    </div>
    <!-- Sub-stats (injected by JS) -->
    <div class="db-overview-grid" id="dashboard-substats"></div>
  </div>

  <?php if ($isAdmin): ?>
  <!-- ═══════════════ PANEL: EVENTS (Admin) ═══════════════ -->
  <div class="db-panel" id="panel-events">
    <div class="db-panel__header">
      <h3>Menaxho Eventet</h3>
        <div style="display:flex; gap:10px;">
            <button class="db-btn db-btn--primary" onclick="window.openQrScanner()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect width="5" height="5" x="7" y="7" rx="1"/><rect width="5" height="5" x="12" y="12" rx="1"/></svg>
              Skano QR
            </button>
            <button class="db-btn db-btn--primary" onclick="toggleCreateEvent()">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
              Krijo Event
            </button>
        </div>
    </div>
    <div class="db-create-form" id="create-event-wrapper" style="display:none">
      <form id="create-event-form" class="db-form">
        <div class="db-form__row">
          <div class="db-form__group">
            <label>Titulli</label>
            <input type="text" name="titulli" required placeholder="Emri i eventit">
          </div>
          <div class="db-form__group" style="position:relative;">
            <label>Vendndodhja</label>
            <input type="text" name="vendndodhja" id="event-vendndodhja" required placeholder="Shkruaj vendndodhjen&hellip;" autocomplete="off">
            <div id="event-location-suggestions" style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;box-shadow:0 4px 16px rgba(0,0,0,0.1);z-index:9999;display:none;max-height:220px;overflow-y:auto;margin-top:2px;"></div>
          </div>
          <div class="db-form__group">
            <label>Data</label>
            <input type="datetime-local" name="data" required>
          </div>
          <div class="db-form__group">
            <label>Kategoria</label>
            <select name="id_kategoria" id="event-category-select">
              <option value="">Pa kategori</option>
            </select>
          </div>
        </div>
        <div class="db-form__group">
          <label>Përshkrimi</label>
          <textarea name="pershkrimi" rows="2" placeholder="Përshkrim i shkurtër..."></textarea>
        </div>
        <div class="db-form__group">
          <label>Banner</label>
          <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label class="db-btn db-btn--ghost" style="cursor:pointer;font-size:0.85rem;padding:8px 14px;display:inline-flex;align-items:center;gap:6px;">
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
              Ngarko imazh
              <input type="file" id="event-banner-file" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="previewEventBanner(this)">
            </label>
            <span id="event-banner-filename" style="font-size:0.82rem;color:#64748b;"></span>
          </div>
          <img id="event-banner-preview" src="" alt="" style="display:none;max-height:90px;border-radius:8px;margin-top:8px;object-fit:cover;max-width:220px;">
        </div>
        <div class="db-form__group">
          <div class="ts-map-wrapper">
            <label>Vendndodhja në hartë (opsionale)</label>
            <div id="event-map-picker" class="ts-map-picker"></div>
            <input type="hidden" name="latitude" id="event-lat-input">
            <input type="hidden" name="longitude" id="event-lng-input">
            <div class="ts-map-coord-display" id="event-coord-display" style="display:none">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
              <span id="event-coord-text"></span>
            </div>
          </div>
        </div>
        <div class="db-form__actions">
          <button type="submit" class="db-btn db-btn--primary">Krijo</button>
          <button type="button" class="db-btn db-btn--ghost" onclick="toggleCreateEvent()">Anulo</button>
        </div>
      </form>
    </div>
    <div class="db-table-wrap" id="admin-event-list">
      <div class="db-loading">Duke ngarkuar eventet…</div>
    </div>

    <!-- Application details (toggled) -->
    <div class="db-table-wrap" id="event-applications-card" style="display:none">
      <div class="db-panel__header">
        <h4>Aplikime për Eventin</h4>
        <button class="db-btn db-btn--ghost" onclick="document.getElementById('event-applications-card').style.display='none'">Mbyll</button>
      </div>
      <div id="event-applications"></div>
    </div>
  </div>

  <!-- ═══════════════ PANEL: USERS (Admin) ═══════════════ -->
  <div class="db-panel" id="panel-users">
    <div class="db-panel__header">
      <div>
        <h3>Menaxho Përdoruesit</h3>
        <p class="db-panel__subtitle">Shikoni, bllokoni ose menaxhoni llogaritë e përdoruesve.</p>
      </div>
      <a href="/TiranaSolidare/api/export.php?type=users" target="_blank" class="db-btn db-btn--success">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
        Eksporto CSV
      </a>
    </div>
    <div class="db-table-wrap" id="admin-user-list">
      <div class="db-loading">Duke ngarkuar përdoruesit…</div>
    </div>
  </div>

  <!-- ═══════════════ PANEL: USER DETAIL (Admin) ═══════════════ -->
  <div class="db-panel" id="panel-user-detail">
    <div class="db-panel__header">
      <button class="db-btn db-btn--ghost" onclick="switchPanel('users', document.querySelector('[data-panel=users]'))">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        Kthehu te Lista
      </button>
    </div>

    <!-- User detail content (injected by JS) -->
    <div id="user-detail-content">
      <div class="db-loading">Duke ngarkuar…</div>
    </div>
  </div>

  <!-- ═══════════════ PANEL: REQUESTS (Admin) ═══════════════ -->
  <div class="db-panel" id="panel-requests">
    <div class="db-panel__header">
      <h3>Kërkesat për Ndihmë</h3>
    </div>
    <div class="db-table-wrap" id="help-request-list">
      <div class="db-loading">Duke ngarkuar kërkesat…</div>
    </div>
  </div>

  <!-- ═══════════════ PANEL: REPORTS (Admin) ═══════════════ -->
  <div class="db-panel" id="panel-reports">
    <div class="db-panel__header">
      <h3>Raportet & Statistikat</h3>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/TiranaSolidare/api/export.php?type=events" target="_blank" class="db-btn db-btn--ghost">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
          CSV Evente
        </a>
        <a href="/TiranaSolidare/api/export.php?type=applications" target="_blank" class="db-btn db-btn--ghost">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
          CSV Aplikime
        </a>
        <button class="db-btn db-btn--primary" onclick="generateReport()">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
          Gjenero raport
        </button>
        <a href="/TiranaSolidare/api/export.php?type=report_html" target="_blank" class="db-btn db-btn--accent">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          Raport PDF
        </a>
      </div>
    </div>
    <div class="reports-grid" id="reports-charts">
      <div class="db-loading" style="grid-column:1/-1;">Duke ngarkuar grafikët…</div>
    </div>
    <div style="margin-top:24px;">
      <h4 style="margin-bottom:12px;">Raportet e gjeneruara</h4>
      <div id="reports-list">
        <div class="db-loading">Duke ngarkuar…</div>
      </div>
    </div>
  </div>

  <!-- ═══════════════ PANEL: CATEGORIES (Admin) ═══════════════ -->
  <div class="db-panel" id="panel-categories">
    <div class="db-panel__header">
      <div>
        <h3>Menaxho Kategoritë</h3>
        <p class="db-panel__subtitle">Krijoni, riemërtoni ose fshini kategoritë e eventeve.</p>
      </div>
      <button class="db-btn db-btn--primary" onclick="toggleCategoryForm()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Krijo Kategori
      </button>
    </div>

    <div id="category-create-form" class="db-create-form" style="display:none">
      <div class="db-form__group" style="max-width:360px;">
        <label>Emri i Kategorisë</label>
        <input type="text" id="new-category-name" class="ud-input" placeholder="p.sh. Mjedisi, Ushqimi…" maxlength="60">
        <div style="display:flex;gap:8px;margin-top:10px;">
          <button class="db-btn db-btn--primary" onclick="createCategory()">Shto</button>
          <button class="db-btn db-btn--ghost" onclick="toggleCategoryForm()">Anulo</button>
        </div>
        <div id="cat-create-status" style="font-size:13px;min-height:16px;margin-top:6px;"></div>
      </div>
    </div>

    <div id="category-list">
      <div class="db-loading">Duke ngarkuar kategoritë…</div>
    </div>
  </div>

  <?php if ($isSuperAdmin): ?>
  <!-- ═══════════════ PANEL: AUDIT LOG (Super Admin) ═══════════════ -->
  <div class="db-panel" id="panel-audit">
    <div class="db-panel__header">
      <div>
        <h3>Regjistri i Auditimit</h3>
        <p class="db-panel__subtitle">Të gjitha veprimet administrative të regjistruara.</p>
      </div>
    </div>

    <!-- Audit filters -->
    <div class="db-filter-bar" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center;">
      <input type="date" id="audit-date-from" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadAuditLog(1)">
      <input type="date" id="audit-date-to" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;" onchange="loadAuditLog(1)">
      <input type="text" id="audit-filter-action" placeholder="Filtro sipas veprimit…" style="padding:8px 12px;border:1.5px solid #e4e8ee;border-radius:8px;font-size:0.85rem;min-width:180px;" onkeydown="if(event.key==='Enter')loadAuditLog(1)">
      <button class="db-btn db-btn--primary db-btn--sm" onclick="loadAuditLog(1)">Filtro</button>
      <button class="db-btn db-btn--sm" onclick="document.getElementById('audit-date-from').value='';document.getElementById('audit-date-to').value='';document.getElementById('audit-filter-action').value='';loadAuditLog(1)" style="background:#f3f4f6;border:1px solid #e4e8ee;border-radius:8px;padding:8px 12px;cursor:pointer;">Pastro</button>
    </div>

    <div id="audit-log-container">
      <div class="db-loading">Duke ngarkuar regjistrin…</div>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>

  <!-- ═══════════════ PANEL: MESSAGES ═══════════════ -->
  <div class="db-panel" id="panel-messages">
    <div class="db-panel__header">
      <h3 id="msg-panel-title">Mesazhet</h3>
      <div id="msg-header-actions">
        <button class="db-btn db-btn--primary" onclick="showNewConversation()">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          Mesazh i ri
        </button>
      </div>
    </div>
    <div id="msg-content">
      <div class="db-loading">Duke ngarkuar bisedat…</div>
    </div>
  </div>

  <!-- ═══════════════ PANEL: NOTIFICATIONS ═══════════════ -->
  <div class="db-panel" id="panel-notifications">
    <div class="db-panel__header">
      <h3>Njoftimet</h3>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($isSuperAdmin): ?>
        <button class="db-btn db-btn--accent" onclick="toggleBroadcastForm()">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>
          Dërgo Njoftim
        </button>
        <?php endif; ?>
        <button class="db-btn db-btn--ghost" onclick="markAllRead()">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
          Shëno të gjitha
        </button>
      </div>
    </div>

    <?php if ($isSuperAdmin): ?>
    <!-- Broadcast form (super_admin only) -->
    <div id="broadcast-form" class="db-broadcast-form" style="display:none">
      <h4 class="db-broadcast-form__title">Dërgo njoftim masiv</h4>
      <div class="db-form__row" style="grid-template-columns:1fr auto;gap:12px;align-items:end;">
        <div class="db-form__group" style="margin-bottom:0">
          <label>Mesazhi</label>
          <textarea id="broadcast-msg" class="ud-input" rows="2" maxlength="300" placeholder="Shkruaj njoftimin për të gjithë…" style="resize:vertical;"></textarea>
        </div>
        <div class="db-form__group" style="margin-bottom:0">
          <label>Destinacioni</label>
          <select id="broadcast-role" class="ud-input" style="padding:8px 12px;">
            <option value="all">Të gjithë</option>
            <option value="volunteer">Vetëm Vullnetarët</option>
            <option value="admin">Vetëm Adminët</option>
          </select>
        </div>
      </div>
      <div class="db-form__group" style="margin-top:10px;">
        <label>Link (opsional)</label>
        <input type="text" id="broadcast-link" class="ud-input" placeholder="https://… ose /TiranaSolidare/…">
      </div>
      <div style="display:flex;gap:8px;margin-top:12px;">
        <button class="db-btn db-btn--primary" onclick="sendBroadcast()">Dërgo Njoftimin</button>
        <button class="db-btn db-btn--ghost" onclick="toggleBroadcastForm()">Anulo</button>
      </div>
      <div id="broadcast-status" style="font-size:13px;min-height:16px;margin-top:8px;"></div>
    </div>
    <?php endif; ?>

    <div id="notification-list">
      <div class="db-loading">Duke ngarkuar njoftimet…</div>
    </div>
  </div>

<!-- ═══════════════ PANEL: PROFILE (Admin) ═══════════════ -->
<div class="db-panel" id="panel-profile">
  <div class="db-panel__header">
    <h3>Llogaria ime</h3>
    <a href="/TiranaSolidare/views/events.php" target="_blank" class="db-btn db-btn--ghost">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>
      Shiko faqen publike
    </a>
  </div>

  <!-- Avatar + Identity header -->
  <div class="ud-header" style="margin-bottom:2rem;">
    <div class="db-profile-avatar-wrap" id="profile-avatar-wrap">
      <div class="ud-avatar ud-avatar--active" id="profile-avatar-initials"><?= $userInitial ?></div>
      <img id="profile-avatar-img" src="" alt="" style="display:none;width:64px;height:64px;border-radius:16px;object-fit:cover;">
      <label class="db-avatar-upload-btn" title="Ndrysho foton">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <input type="file" id="profile-pic-input" accept="image/*" style="display:none" onchange="adminUploadPicture(this)">
      </label>
    </div>
    <div class="ud-header__info">
      <h2 class="ud-header__name"><?= $userEmri ?></h2>
      <p class="ud-header__email"><?= $userEmail ?></p>
      <div class="ud-header__badges">
        <span class="db-badge db-badge--admin"><?= $isSuperAdmin ? 'Super Admin' : e($userRoli) ?></span>
        <span class="db-badge db-badge--vol" style="font-size:0.75rem;">Bashkia Tiranë</span>
      </div>
      <div id="profile-avatar-status" style="font-size:12px;min-height:14px;margin-top:4px;color:var(--db-primary);"></div>
    </div>
  </div>

  <div class="ud-actions-grid">

    <!-- Name -->
    <div class="ud-card">
      <div class="ud-card__header">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <h4>Emri i llogarisë</h4>
      </div>
      <p class="ud-card__desc">Emri i administratorit që shfaqet brenda panelit dhe te njoftimet.</p>
      <div class="ud-card__body">
        <input type="text" id="admin-emri" class="ud-input" placeholder="Emri Mbiemri">
        <button class="db-btn db-btn--primary" onclick="adminSaveName()">Ruaj emrin</button>
        <div id="admin-name-status" style="font-size:13px;min-height:16px"></div>
      </div>
    </div>

    <!-- Password -->
    <div class="ud-card">
      <div class="ud-card__header">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <h4>Fjalëkalimi</h4>
      </div>
      <p class="ud-card__desc">Vendos një fjalëkalim të fortë dhe unik. Ndryshimi do të çkyçë pajisjet e tjera.</p>
      <div class="ud-card__body">
        <input type="password" id="admin-current-pw" class="ud-input" placeholder="Fjalëkalimi aktual" autocomplete="new-password">
        <input type="password" id="admin-new-pw" class="ud-input" placeholder="Fjalëkalimi i ri">
        <input type="password" id="admin-confirm-pw" class="ud-input" placeholder="Konfirmo fjalëkalimin">
        <button class="db-btn db-btn--primary" onclick="adminSavePassword()">Përditëso fjalëkalimin</button>
        <div id="admin-pw-status" style="font-size:13px;min-height:16px"></div>
      </div>
    </div>

    <!-- Email / Notifications -->
    <div class="ud-card">
      <div class="ud-card__header">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        <h4>Preferencat e Njoftimeve</h4>
      </div>
      <p class="ud-card__desc">Zgjidhni nëse dëshironi njoftime me email për veprime administrative.</p>
      <div class="ud-card__body">
        <label class="db-toggle-row">
          <span>Njoftime me email</span>
          <label class="db-toggle">
            <input type="checkbox" id="admin-email-notif" onchange="adminSaveNotifPrefs()">
            <span class="db-toggle__slider"></span>
          </label>
        </label>
        <div id="admin-notif-status" style="font-size:13px;min-height:16px;margin-top:8px;"></div>

        <hr style="margin:20px 0;border:none;border-top:1px solid #e2e8f0;">
        <label class="db-toggle-row" style="align-items:flex-start;gap:12px">
          <div>
            <strong style="display:block;margin-bottom:4px;font-size:0.9rem;">Njoftime në telefon / browser</strong>
            <span style="font-size:0.82rem;color:#64748b;">Merrni njoftime direkte në pajisjen tuaj.</span>
          </div>
          <button id="admin-push-btn" type="button" class="btn_primary" style="flex-shrink:0;white-space:nowrap;font-size:0.82rem;padding:6px 14px" disabled>Duke u ngarkuar…</button>
        </label>
        <div id="admin-push-status" style="font-size:13px;min-height:16px;margin-top:4px;"></div>
      </div>
    </div>

    <!-- Organization info note -->
    <div class="ud-card" style="background:linear-gradient(135deg,rgba(0,113,93,0.05) 0%,rgba(0,113,93,0.02) 100%);border-color:rgba(0,113,93,0.2);">
      <div class="ud-card__header">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <h4>Informacion për Organizatën</h4>
      </div>
      <p class="ud-card__desc" style="font-size:0.88rem;line-height:1.6;">
        Eventet publikohen si iniciativë e <strong>Bashkisë Tiranë</strong> — organizatës që qëndron pas platformës Tirana Solidare.
        Profili personal i administratorit nuk është i dukshëm për vullnetarët. Çdo event tregon vetëm emrin e organizatës dhe kategorinë.
      </p>
      <div style="margin-top:12px;padding:12px;background:rgba(0,113,93,0.06);border-radius:10px;font-size:0.82rem;color:#00715D;display:flex;gap:8px;align-items:flex-start;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        <span>Vullnetarët e shohin platformën si "Tirana Solidare" dhe bashkinë si organizatorin e çdo eventi — jo emrin tuaj personal.</span>
      </div>
    </div>

  </div>
</div>
</main>

<!-- Toast container -->
<div id="db-toast-container"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/TiranaSolidare/assets/js/map-component.js?v=<?= filemtime(__DIR__.'/../assets/js/map-component.js') ?>"></script>
<script src="/TiranaSolidare/assets/js/main.js?v=<?= filemtime(__DIR__.'/../assets/js/main.js') ?>"></script>
<script src="/TiranaSolidare/assets/js/ajax-polling.js?v=<?= filemtime(__DIR__.'/../assets/js/ajax-polling.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const CURRENT_USER_ID = <?= (int) $_SESSION['user_id'] ?>;
const IS_SUPER_ADMIN = <?= $isSuperAdmin ? 'true' : 'false' ?>;
</script>
<script src="/TiranaSolidare/assets/js/dashboard-ui.js?v=<?= filemtime(__DIR__.'/../assets/js/dashboard-ui.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  let eventMapPicker = null;
  const createWrapper = document.getElementById('create-event-wrapper');
  if (createWrapper) {

    // Inicializo hartën kur hapet forma
    const observer = new MutationObserver(function() {
      if (createWrapper.style.display !== 'none' && !eventMapPicker) {
        setTimeout(() => {
          eventMapPicker = TSMap.picker('event-map-picker', {
            latInput: 'event-lat-input',
            lngInput: 'event-lng-input',
            addressInput: 'event-vendndodhja',
            onSelect: function(lat, lng) {
              const coordDisplay = document.getElementById('event-coord-display');
              const coordText = document.getElementById('event-coord-text');
              if (coordDisplay && coordText) {
                coordDisplay.style.display = 'flex';
                coordText.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
              }
            }
          });
          if (eventMapPicker && eventMapPicker.map) {
            eventMapPicker.map.invalidateSize();
          }
        }, 200);
      }
    });
    observer.observe(createWrapper, { attributes: true, attributeFilter: ['style'] });

    const vendInput = document.getElementById('event-vendndodhja');
    if (vendInput) {
      let geocodeTimeout = null;
      const suggestBox = document.getElementById('event-location-suggestions');

      function hideSuggestions() { if (suggestBox) suggestBox.style.display = 'none'; }

      vendInput.addEventListener('input', function() {
        clearTimeout(geocodeTimeout);
        const q = this.value.trim();
        if (q.length < 3) { hideSuggestions(); return; }
        geocodeTimeout = setTimeout(async () => {
          try {
            const res = await fetch(
              `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q + ', Albania')}&limit=5`,
              { headers: { 'Accept-Language': 'sq,en' } }
            );
            const data = await res.json();
            if (!data.length) { hideSuggestions(); return; }
            if (suggestBox) {
              suggestBox.innerHTML = data.map((item, i) => {
                const label = item.display_name.replace(/, Albania$/, '');
                return `<div style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;font-size:0.84rem;color:#334155;transition:background .15s;" 
                  onmouseenter="this.style.background='#f8fafc'" onmouseleave="this.style.background=''" 
                  onclick="selectEventLocation(${parseFloat(item.lat)},${parseFloat(item.lon)},${JSON.stringify(label)})">${label}</div>`;
              }).join('');
              suggestBox.style.display = 'block';
            }
          } catch (e) { hideSuggestions(); }
        }, 450);
      });

      vendInput.addEventListener('blur', function() { setTimeout(hideSuggestions, 200); });

      window.selectEventLocation = function(lat, lng, label) {
        vendInput.value = label;
        hideSuggestions();
        if (!eventMapPicker) return;
        eventMapPicker.setPosition(lat, lng);
        const coordDisplay = document.getElementById('event-coord-display');
        const coordText = document.getElementById('event-coord-text');
        if (coordDisplay && coordText) {
          coordDisplay.style.display = 'flex';
          coordText.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
        }
        // Fill hidden lat/lng inputs
        const latIn = document.getElementById('event-lat-input');
        const lngIn = document.getElementById('event-lng-input');
        if (latIn) latIn.value = lat;
        if (lngIn) lngIn.value = lng;
      };

      vendInput._skipNextGeocode = () => {}; // kept for API compat
    }
  }
});
</script>
<script>
function previewEventBanner(input) {
  const prev = document.getElementById('event-banner-preview');
  const fname = document.getElementById('event-banner-filename');
  if (input.files && input.files[0]) {
    if (prev) { prev.src = URL.createObjectURL(input.files[0]); prev.style.display = 'block'; }
    if (fname) fname.textContent = input.files[0].name;
  }
}
const adminCsrf_meta = document.querySelector('meta[name="csrf-token"]');
function getAdminCSRF() { return adminCsrf_meta?.content || ''; }
function refreshCSRF(json) { if (json && json.csrf_token && adminCsrf_meta) adminCsrf_meta.content = json.csrf_token; return json; }
let adminCsrf = getAdminCSRF();
(function() {
  const _fetch = window.fetch;
  window.fetch = async function(...args) {
    const res = await _fetch.apply(this, args);
    // Handle session expiry / unauthorized for ALL fetch calls on this page
    if (res.status === 401 || res.status === 403) {
      if (typeof handleSessionExpired === 'function') handleSessionExpired();
    }
    const m = (args[1]?.method || 'GET').toUpperCase();
    if (['POST','PUT','DELETE'].includes(m)) {
      try { refreshCSRF(await res.clone().json()); adminCsrf = getAdminCSRF(); } catch(e) {}
    }
    return res;
  };
})();

async function adminSaveName() {
  const emri = document.getElementById('admin-emri').value.trim();
  const st = document.getElementById('admin-name-status');
  if (!emri) { st.style.color='red'; st.textContent='Shkruaj emrin.'; return; }
  try {
    const res = await fetch('/TiranaSolidare/api/users.php?action=update_profile', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminCsrf },
      credentials: 'same-origin',
      body: JSON.stringify({ emri }),
    });
    const json = await res.json();
    st.style.color = json.success ? 'green' : 'red';
    st.textContent = json.success ? 'Emri u ruajt.' : (json.message || 'Gabim.');
  } catch(e) { st.style.color='red'; st.textContent='Gabim rrjeti.'; }
}

async function adminSavePassword() {
  const current_password = document.getElementById('admin-current-pw').value;
  const new_password = document.getElementById('admin-new-pw').value;
  const confirm_password = document.getElementById('admin-confirm-pw').value;
  const st = document.getElementById('admin-pw-status');
  if (new_password !== confirm_password) { st.style.color='red'; st.textContent='Fjalëkalimet nuk përputhen.'; return; }
  try {
    const res = await fetch('/TiranaSolidare/api/auth.php?action=change_password', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminCsrf },
      credentials: 'same-origin',
      body: JSON.stringify({ current_password, new_password, confirm_password }),
    });
    const json = await res.json();
    st.style.color = json.success ? 'green' : 'red';
    st.textContent = json.success ? 'Fjalëkalimi u përditësua.' : (json.message || 'Gabim.');
    if (json.success) {
      document.getElementById('admin-current-pw').value='';
      document.getElementById('admin-new-pw').value='';
      document.getElementById('admin-confirm-pw').value='';
    }
  } catch(e) { st.style.color='red'; st.textContent='Gabim rrjeti.'; }
}

async function adminLoadProfile() {
  // Full implementation is in dashboard-ui.js → window.loadAdminProfile
  if (typeof loadAdminProfile === 'function') await loadAdminProfile();
}

async function deleteAccount() {
  const st = document.getElementById('delete-account-status');
  const pw = document.getElementById('delete-account-pw')?.value;
  if (!pw) { st.textContent = 'Shkruani fjalëkalimin.'; return; }
  if (!confirm('KUJDES: Kjo do të fshijë llogarinë tuaj përfundimisht. Jeni të sigurt?')) return;
  try {
    const res = await fetch('/TiranaSolidare/api/auth.php?action=delete_account', {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminCsrf },
      credentials: 'same-origin',
      body: JSON.stringify({ password: pw }),
    });
    const json = await res.json();
    if (json.success) {
      alert('Llogaria u fshi. Do të ridrejtoheni.');
      window.location.href = '/TiranaSolidare/public/';
    } else {
      st.textContent = json.message || 'Gabim.';
    }
  } catch(e) { st.textContent = 'Gabim rrjeti.'; }
}

document.addEventListener('DOMContentLoaded', adminLoadProfile);
document.addEventListener('DOMContentLoaded', initAdminPushSubscription);

// ── Admin Web Push Subscription ───────────────────────────────────────────────
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
  return output;
}

async function initAdminPushSubscription() {
  const btn    = document.getElementById('admin-push-btn');
  const status = document.getElementById('admin-push-status');
  if (!btn) return;
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    btn.textContent = 'Nuk mbështetet';
    return;
  }

  const reg = await navigator.serviceWorker.ready;

  async function refreshBtn() {
    const sub = await reg.pushManager.getSubscription();
    btn.textContent = sub ? 'Çaktivizo njoftimet' : 'Aktivizo njoftimet';
    btn.dataset.state = sub ? 'subscribed' : 'unsubscribed';
    btn.disabled = false;
  }
  await refreshBtn();

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    const show = (msg, ok = true) => {
      status.textContent = msg;
      status.style.color = ok ? '#16a34a' : '#dc2626';
      setTimeout(() => { status.textContent = ''; }, 4000);
    };
    try {
      if (btn.dataset.state === 'subscribed') {
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
          await fetch('/TiranaSolidare/api/push.php?action=unsubscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminCsrf },
            credentials: 'same-origin',
            body: JSON.stringify({ endpoint: sub.endpoint }),
          });
          await sub.unsubscribe();
        }
        show('Njoftimet u çaktivizuan.');
      } else {
        const perm = await Notification.requestPermission();
        if (perm !== 'granted') {
          show('Leja u refuzua. Aktivizoni njoftimet nga cilësimet e browser-it.', false);
          btn.disabled = false;
          return;
        }
        const keyRes  = await fetch('/TiranaSolidare/api/push.php?action=vapid_public_key', { credentials: 'same-origin' });
        const keyJson = await keyRes.json();
        const sub = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(keyJson.data.public_key),
        });
        await fetch('/TiranaSolidare/api/push.php?action=subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': adminCsrf },
          credentials: 'same-origin',
          body: JSON.stringify(sub.toJSON()),
        });
        show('Njoftimet u aktivizuan!');
      }
    } catch (err) {
      show('Gabim: ' + (err.message || err), false);
    }
    await refreshBtn();
  });
}
</script>
</body>
</html>