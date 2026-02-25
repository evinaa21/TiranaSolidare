<?php
// views/dashboard.php — Premium Admin Panel
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /TiranaSolidare/views/login.php");
    exit();
}

$isAdmin = ($_SESSION['roli'] ?? '') === 'Admin';
$userEmri = htmlspecialchars($_SESSION['emri'] ?? 'Përdorues');
$userRoli = htmlspecialchars($_SESSION['roli'] ?? 'Vullnetar');
$userEmail = htmlspecialchars($_SESSION['email'] ?? '');
$userInitial = mb_strtoupper(mb_substr($_SESSION['emri'] ?? 'P', 0, 1));
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paneli — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/dashboard.css">
</head>
<body class="db-body">

<!-- ═══════════════════════════════════════════════════════════
     SIDEBAR NAVIGATION
     ═══════════════════════════════════════════════════════════ -->
<aside class="db-sidebar" id="db-sidebar">
  <div class="db-sidebar__header">
    <a href="/TiranaSolidare/public/" class="db-sidebar__logo">
      <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
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
    <?php else: ?>
    <button class="db-nav-item" data-panel="browse-events" onclick="switchPanel('browse-events', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
      <span>Eventet</span>
    </button>
    <button class="db-nav-item" data-panel="my-apps" onclick="switchPanel('my-apps', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><rect width="20" height="14" x="2" y="6" rx="2"/></svg>
      <span>Aplikimet e Mia</span>
    </button>
    <button class="db-nav-item" data-panel="submit-request" onclick="switchPanel('submit-request', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      <span>Dërgo Kërkesë</span>
    </button>
    <?php endif; ?>

    <button class="db-nav-item" data-panel="notifications" onclick="switchPanel('notifications', this)">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
      <span>Njoftimet</span>
      <span class="db-nav-badge" id="notif-badge" style="display:none"></span>
    </button>
  </nav>

  <!-- Sidebar user card -->
  <div class="db-sidebar__user">
    <div class="db-sidebar__avatar"><?= $userInitial ?></div>
    <div class="db-sidebar__user-info">
      <strong><?= $userEmri ?></strong>
      <span><?= $userRoli ?></span>
    </div>
    <a href="/TiranaSolidare/src/actions/logout.php" class="db-sidebar__logout" title="Dil">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
    </a>
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
      <span class="db-topbar__role db-topbar__role--<?= $isAdmin ? 'admin' : 'vol' ?>"><?= $userRoli ?></span>
    </div>
  </header>

  <!-- ─── WELCOME HEADER ─── -->
  <section class="db-welcome">
    <svg class="db-welcome__blob" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(0,113,93,0.06)" d="M44.7,-76.4C58.8,-69.2,71.8,-58.7,79.6,-45.1C87.4,-31.5,90.1,-15.7,88.5,-0.9C86.9,13.9,81.1,27.8,72.6,39.6C64.1,51.4,52.9,61.2,40.1,68.4C27.3,75.6,13.7,80.3,-0.8,81.7C-15.3,83.1,-30.5,81.3,-43.4,74.2C-56.2,67.2,-66.7,55,-73.8,41.2C-80.8,27.3,-84.4,11.7,-83.5,-3.5C-82.6,-18.7,-77.2,-33.4,-68,-45.1C-58.8,-56.8,-45.9,-65.4,-32.3,-72.8C-18.7,-80.3,-9.3,-86.5,3.2,-91.9C15.7,-97.4,30.5,-83.6,44.7,-76.4Z" transform="translate(100 100)"/></svg>
    <div class="db-welcome__text">
      <h2>Mirësevini, <?= $userEmri ?>! 👋</h2>
      <p><?= $isAdmin ? 'Menaxhoni platformën nga paneli juaj admin.' : 'Shikoni eventet, aplikimet dhe kërkesat tuaja.' ?></p>
    </div>
  </section>


  <!-- ═══════════════ PANEL: OVERVIEW ═══════════════ -->
  <div class="db-panel active" id="panel-overview">
    <!-- Stats cards (injected by JS) -->
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
      <button class="db-btn db-btn--primary" onclick="toggleCreateEvent()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        Krijo Event
      </button>
    </div>

    <!-- Create event form -->
    <div class="db-create-form" id="create-event-wrapper" style="display:none">
      <form id="create-event-form" class="db-form">
        <div class="db-form__row">
          <div class="db-form__group">
            <label>Titulli</label>
            <input type="text" name="titulli" required placeholder="Emri i eventit">
          </div>
          <div class="db-form__group">
            <label>Vendndodhja</label>
            <input type="text" name="vendndodhja" required placeholder="Vendi">
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
          <label>Banner URL</label>
          <input type="text" name="banner" placeholder="https://images.unsplash.com/...">
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
      <h3>Menaxho Përdoruesit</h3>
      <p class="db-panel__subtitle">Bllokoni, zhbllokoni ose ndryshoni rolin e përdoruesve.</p>
    </div>
    <div class="db-table-wrap" id="admin-user-list">
      <div class="db-loading">Duke ngarkuar përdoruesit…</div>
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


  <?php else: ?>
  <!-- ═══════════════ PANEL: BROWSE EVENTS (Volunteer) ═══════════════ -->
  <div class="db-panel" id="panel-browse-events">
    <div class="db-panel__header">
      <h3>Eventet e Hapura</h3>
    </div>
    <div id="event-list">
      <div class="db-loading">Duke ngarkuar eventet…</div>
    </div>
  </div>


  <!-- ═══════════════ PANEL: MY APPLICATIONS (Volunteer) ═══════════════ -->
  <div class="db-panel" id="panel-my-apps">
    <div class="db-panel__header">
      <h3>Aplikimet e Mia</h3>
    </div>
    <div class="db-table-wrap" id="application-list">
      <div class="db-loading">Duke ngarkuar aplikimet…</div>
    </div>
  </div>


  <!-- ═══════════════ PANEL: SUBMIT REQUEST (Volunteer) ═══════════════ -->
  <div class="db-panel" id="panel-submit-request">
    <div class="db-panel__header">
      <h3>Dërgo Kërkesë për Ndihmë</h3>
      <p class="db-panel__subtitle">Posto kërkesën ose ofertën tënde dhe komuniteti do të përgjigjet.</p>
    </div>
    <div class="db-create-form db-create-form--visible">
      <form id="help-request-form" class="db-form">
        <div class="db-form__row">
          <div class="db-form__group db-form__group--wide">
            <label>Titulli</label>
            <input type="text" name="titulli" required placeholder="Titulli i kërkesës">
          </div>
          <div class="db-form__group">
            <label>Tipi</label>
            <select name="tipi" required>
              <option value="Kërkesë">Kërkesë</option>
              <option value="Ofertë">Ofertë</option>
            </select>
          </div>
          <div class="db-form__group">
            <label>Vendndodhja</label>
            <input type="text" name="vendndodhja" placeholder="Vendndodhja (opsionale)">
          </div>
        </div>
        <div class="db-form__group">
          <label>Përshkrimi</label>
          <textarea name="pershkrimi" rows="3" placeholder="Përshkruani kërkesën tuaj në detaje..."></textarea>
        </div>
        <div class="db-form__actions">
          <button type="submit" class="db-btn db-btn--primary">Dërgo Kërkesën</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>


  <!-- ═══════════════ PANEL: NOTIFICATIONS ═══════════════ -->
  <div class="db-panel" id="panel-notifications">
    <div class="db-panel__header">
      <h3>Njoftimet</h3>
      <button class="db-btn db-btn--ghost" onclick="markAllRead()">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
        Shëno të gjitha si të lexuara
      </button>
    </div>
    <div id="notification-list">
      <div class="db-loading">Duke ngarkuar njoftimet…</div>
    </div>
  </div>

</main>

<!-- Toast container -->
<div id="db-toast-container"></div>

<script src="/TiranaSolidare/assets/js/main.js"></script>
<script src="/TiranaSolidare/assets/js/ajax-polling.js"></script>
<script src="/TiranaSolidare/assets/js/dashboard-ui.js"></script>
</body>
</html>