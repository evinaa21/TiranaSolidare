<?php
// views/volunteer_panel.php — Volunteer Personal Panel (public-style layout)
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /TiranaSolidare/views/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// If admin, redirect to admin dashboard
if (($_SESSION['roli'] ?? '') === 'Admin') {
    header("Location: /TiranaSolidare/views/dashboard.php");
    exit();
}

$userId    = $_SESSION['user_id'];
$userEmri  = htmlspecialchars($_SESSION['emri'] ?? 'Përdorues');
$userRoli  = htmlspecialchars($_SESSION['roli'] ?? 'Vullnetar');
$userEmail = htmlspecialchars($_SESSION['email'] ?? '');
$userInitial = mb_strtoupper(mb_substr($_SESSION['emri'] ?? 'P', 0, 1));

// Active tab
$tab = $_GET['tab'] ?? 'profile';

// Fetch user profile
$stmtUser = $pdo->prepare("SELECT * FROM Perdoruesi WHERE id_perdoruesi = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

// Fetch my applications
$stmtApps = $pdo->prepare(
    "SELECT a.*, e.titulli AS eventi_titulli, e.data AS eventi_data, e.vendndodhja AS eventi_vendndodhja,
            k.emri AS kategoria_emri
     FROM Aplikimi a
     JOIN Eventi e ON e.id_eventi = a.id_eventi
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     WHERE a.id_perdoruesi = ?
     ORDER BY a.aplikuar_me DESC"
);
$stmtApps->execute([$userId]);
$myApps = $stmtApps->fetchAll(PDO::FETCH_ASSOC);

// Fetch my help requests
$stmtReqs = $pdo->prepare(
    "SELECT * FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ? ORDER BY krijuar_me DESC"
);
$stmtReqs->execute([$userId]);
$myRequests = $stmtReqs->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalApps     = count($myApps);
$acceptedApps  = count(array_filter($myApps, fn($a) => $a['statusi'] === 'Pranuar'));
$pendingApps   = count(array_filter($myApps, fn($a) => $a['statusi'] === 'Në pritje'));
$totalRequests = count($myRequests);
$openRequests  = count(array_filter($myRequests, fn($r) => $r['statusi'] === 'Open'));
$score        = ($acceptedApps * 5) + ($totalApps * 1) + ($totalRequests * 2);
$scoreMax     = 150;
$scorePercent = min(100, round(($score / $scoreMax) * 100));
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paneli im — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/auth.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/volunteer-panel.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/TiranaSolidare/assets/css/map.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<!-- ─── HERO ─── -->
<section class="page-hero page-hero--green">
  <div class="page-hero__inner">
    <span class="page-badge">Vullnetar</span>
    <h1>Mirë se vini, <?= $userEmri ?>!</h1>
    <p>Menaxhoni profilin, aplikimet dhe kërkesat tuaja nga paneli personal.</p>
  </div>
</section>

<!-- ─── STATS BAR ─── -->
<section class="vp-stats-section">
  <div class="vp-stats">
    <div class="vp-stat">
      <strong><?= $totalApps ?></strong>
      <span>Aplikime</span>
    </div>
    <div class="vp-stat">
      <strong><?= $acceptedApps ?></strong>
      <span>Pranuar</span>
    </div>
    <div class="vp-stat">
      <strong><?= $pendingApps ?></strong>
      <span>Në pritje</span>
    </div>
    <div class="vp-stat">
      <strong><?= $totalRequests ?></strong>
      <span>Kërkesat e mia</span>
    </div>
    <div class="vp-stat">
      <strong><?= $openRequests ?></strong>
      <span>Të hapura</span>
    </div>
  </div>
</section>

<!-- ─── TAB NAVIGATION ─── -->
<section class="vp-tabs-section">
  <div class="vp-tabs">
    <a href="?tab=profile" class="vp-tab <?= $tab === 'profile' ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Profili
    </a>
    <a href="?tab=applications" class="vp-tab <?= $tab === 'applications' ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
      Aplikimet e mia
    </a>
    <a href="?tab=requests" class="vp-tab <?= $tab === 'requests' ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      Kërkesat e mia
    </a>
    <a href="?tab=new-request" class="vp-tab <?= $tab === 'new-request' ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
      Dërgo kërkesë
    </a><a href="?tab=score" class="vp-tab <?= $tab === 'score' ? 'active' : '' ?>">
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  Pikët e mia
</a>
    <a href="?tab=notifications" class="vp-tab <?= $tab === 'notifications' ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
      Njoftimet
      <span class="vp-tab-badge" id="notif-tab-badge" style="display:none"></span>
    </a>
  </div>
</section>

<!-- ─── TAB CONTENT ─── -->
<section class="vp-content">

<?php if ($tab === 'profile'): ?>
<!-- ════════════ PROFILE TAB ════════════ -->
<div class="vp-panel">
  <div class="vp-profile-grid">
    <!-- Profile Info Card -->
    <div class="vp-card">
      <div class="vp-card__header">
        <h3>Informacioni i profilit</h3>
      </div>
      <div class="vp-card__body">
        <div class="vp-profile-avatar">
          <div class="vp-avatar"><?= $userInitial ?></div>
          <div class="vp-profile-info">
            <strong id="vp-profile-emri"><?= htmlspecialchars($user['emri'] ?? '—') ?></strong>
            <span id="vp-profile-email"><?= htmlspecialchars($user['email'] ?? '—') ?></span>
            <div class="vp-profile-badges">
              <span class="vp-badge vp-badge--role"><?= $userRoli ?></span>
              <span class="vp-badge vp-badge--status"><?= htmlspecialchars($user['statusi_llogarise'] ?? '—') ?></span>
            </div>
          </div>
        </div>
        <div class="vp-profile-meta-grid">
          <div class="vp-meta-item">
            <span>Regjistruar</span>
            <strong><?= date('d/m/Y', strtotime($user['krijuar_me'])) ?></strong>
          </div>
          <div class="vp-meta-item">
            <span>Total aplikime</span>
            <strong><?= $totalApps ?></strong>
          </div>
          <div class="vp-meta-item">
            <span>Kërkesa dërguar</span>
            <strong><?= $totalRequests ?></strong>
          </div>
          <div class="vp-meta-item">
            <span>Aplikime pranuar</span>
            <strong><?= $acceptedApps ?></strong>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Name Card -->
    <div class="vp-card">
      <div class="vp-card__header">
        <h3>Ndrysho emrin</h3>
      </div>
      <div class="vp-card__body">
        <form id="vp-name-form" class="vp-form">
          <div class="vp-field">
            <label for="vp-emri">Emri i plotë</label>
            <input type="text" id="vp-emri" name="emri" value="<?= htmlspecialchars($user['emri'] ?? '') ?>" required placeholder="Emri Mbiemri" class="vp-input">
          </div>
          <button type="submit" class="btn_primary">Ruaj emrin</button>
        </form>
        <div id="vp-name-status" class="vp-status" style="display:none"></div>
      </div>
    </div>

    <!-- Change Password Card -->
    <div class="vp-card">
      <div class="vp-card__header">
        <h3>Ndrysho fjalëkalimin</h3>
      </div>
      <div class="vp-card__body">
        <form id="vp-password-form" class="vp-form">
          <div class="vp-field">
            <label for="vp-current-pw">Fjalëkalimi aktual</label>
            <input type="password" id="vp-current-pw" name="current_password" required placeholder="********" class="vp-input">
          </div>
          <div class="vp-field">
            <label for="vp-new-pw">Fjalëkalimi i ri</label>
            <input type="password" id="vp-new-pw" name="new_password" required placeholder="********" minlength="6" class="vp-input">
          </div>
          <div class="vp-field">
            <label for="vp-confirm-pw">Konfirmo fjalëkalimin</label>
            <input type="password" id="vp-confirm-pw" name="confirm_password" required placeholder="********" class="vp-input">
          </div>
          <button type="submit" class="btn_primary">Përditëso fjalëkalimin</button>
        </form>
        <div id="vp-pw-status" class="vp-status" style="display:none"></div>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'applications'): ?>
<!-- ════════════ APPLICATIONS TAB ════════════ -->
<div class="vp-panel">
  <div class="vp-card">
    <div class="vp-card__header">
      <h3>Aplikimet e mia</h3>
      <a href="/TiranaSolidare/views/events.php" class="btn_secondary vp-btn-sm">Zbulo evente</a>
    </div>
    <div class="vp-card__body">
      <?php if (empty($myApps)): ?>
        <div class="vp-empty">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
          <p>Nuk keni aplikime ende. <a href="/TiranaSolidare/views/events.php">Zbuloni eventet</a> dhe aplikoni!</p>
        </div>
      <?php else: ?>
        <div class="vp-table-wrap">
          <table class="vp-table">
            <thead>
              <tr>
                <th>Eventi</th>
                <th>Kategoria</th>
                <th>Data e eventit</th>
                <th>Statusi</th>
                <th>Aplikuar më</th>
                <th>Veprime</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($myApps as $app): ?>
              <tr>
                <td>
                  <a href="/TiranaSolidare/views/events.php?id=<?= $app['id_eventi'] ?>" class="vp-link">
                    <?= htmlspecialchars($app['eventi_titulli']) ?>
                  </a>
                </td>
                <td>
                  <?php if (!empty($app['kategoria_emri'])): ?>
                    <span class="vp-badge vp-badge--category"><?= htmlspecialchars($app['kategoria_emri']) ?></span>
                  <?php else: ?>
                    <span class="vp-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= date('d M Y, H:i', strtotime($app['eventi_data'])) ?></td>
                <td>
                  <span class="vp-badge vp-badge--<?= $app['statusi'] === 'Pranuar' ? 'success' : ($app['statusi'] === 'Refuzuar' ? 'danger' : 'pending') ?>">
                    <?= htmlspecialchars($app['statusi']) ?>
                  </span>
                </td>
                <td><?= date('d M Y', strtotime($app['aplikuar_me'])) ?></td>
                <td>
                  <?php if ($app['statusi'] === 'Në pritje'): ?>
                    <button class="btn_secondary vp-btn-sm vp-btn-danger" onclick="withdrawApp(<?= $app['id_aplikimi'] ?>)">Tërhiq</button>
                  <?php else: ?>
                    <span class="vp-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'requests'): ?>
<!-- ════════════ MY REQUESTS TAB ════════════ -->
<div class="vp-panel">
  <div class="vp-card">
    <div class="vp-card__header">
      <h3>Kërkesat e mia</h3>
      <a href="?tab=new-request" class="btn_primary vp-btn-sm">+ Dërgo kërkesë</a>
    </div>
    <div class="vp-card__body">
      <?php if (empty($myRequests)): ?>
        <div class="vp-empty">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
          <p>Nuk keni dërguar kërkesa ende. <a href="?tab=new-request">Dërgoni një tani!</a></p>
        </div>
      <?php else: ?>
        <div class="vp-request-grid">
          <?php foreach ($myRequests as $req): ?>
            <a href="/TiranaSolidare/views/help_requests.php?id=<?= $req['id_kerkese_ndihme'] ?>" class="vp-request-card">
              <div class="vp-request-card__top">
                <span class="vp-badge vp-badge--<?= $req['tipi'] === 'Ofertë' ? 'offer' : 'request' ?>"><?= htmlspecialchars($req['tipi']) ?></span>
                <span class="vp-badge vp-badge--<?= strtolower($req['statusi']) ?>"><?= htmlspecialchars($req['statusi']) ?></span>
              </div>
              <?php if (!empty($req['imazhi'])): ?>
                <img src="<?= htmlspecialchars($req['imazhi']) ?>" alt="" class="vp-request-card__img">
              <?php endif; ?>
              <h4><?= htmlspecialchars($req['titulli']) ?></h4>
              <p><?= htmlspecialchars(mb_substr($req['pershkrimi'] ?? '', 0, 120)) ?>...</p>
              <span class="vp-request-card__time"><?= koheParapake($req['krijuar_me']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php elseif ($tab === 'new-request'): ?>
<!-- ════════════ NEW REQUEST TAB ════════════ -->
<div class="vp-panel">
  <div class="vp-card">
    <div class="vp-card__header">
      <h3>Dërgo kërkesë për ndihmë</h3>
      <p>Posto kërkesën ose ofertën tënde dhe komuniteti do të përgjigjet.</p>
    </div>
    <div class="vp-card__body">
      <form id="vp-request-form" class="vp-form">
        <div class="vp-form-row">
          <div class="vp-field vp-field--wide">
            <label for="vp-req-title">Titulli</label>
            <input type="text" id="vp-req-title" name="titulli" required placeholder="Titulli i kërkesës" class="vp-input">
          </div>
          <div class="vp-field">
            <label for="vp-req-type">Tipi</label>
            <select id="vp-req-type" name="tipi" required class="vp-input">
              <option value="Kërkesë">Kërkesë</option>
              <option value="Ofertë">Ofertë</option>
            </select>
          </div>
        </div>
        <div class="vp-field">
          <label for="vp-req-desc">Përshkrimi</label>
          <textarea id="vp-req-desc" name="pershkrimi" rows="4" placeholder="Përshkruani kërkesën tuaj në detaje..." class="vp-input"></textarea>
        </div>
        <div class="vp-form-row">
          <div class="vp-field">
            <label for="vp-req-location">Vendndodhja</label>
            <input type="text" id="vp-req-location" name="vendndodhja" placeholder="Vendndodhja (opsionale)" class="vp-input">
          </div>
          <div class="vp-field">
            <label for="vp-req-image">Imazhi (Ngarkoni skedarin)</label>
            <input type="file" id="vp-req-image" name="image" accept="image/*" class="vp-input">
            <small style="color: #999;">Maksimumi 5MB. Formatet: JPG, PNG, GIF, WEBP</small>
          </div>
        </div>
        <div class="vp-field">
          <div class="ts-map-wrapper">
            <label>Zgjidh vendndodhjen në hartë</label>
            <div id="request-map-picker" class="ts-map-picker"></div>
            <input type="hidden" name="latitude" id="req-lat-input">
            <input type="hidden" name="longitude" id="req-lng-input">
            <div class="ts-map-coord-display" id="req-coord-display" style="display:none">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
              <span id="req-coord-text"></span>
            </div>
          </div>
        </div>
        <button type="submit" class="btn_primary">Dërgo kërkesën</button>
        <div id="vp-req-status" class="vp-status" style="display:none"></div>
      </form>
    </div>
  </div>
</div>
<?php elseif ($tab === 'score'): ?>
<div class="vp-panel">
  <div class="vp-card vp-score-card">
    <div class="vp-card__header"><h3>Rezultati im si Vullnetar</h3></div>
    <div class="vp-card__body vp-score-body">
      <div class="vp-score-chart-wrap">
        <canvas id="vp-score-chart" width="180" height="180"></canvas>
        <div class="vp-score-overlay">
          <strong><?= $score ?></strong>
          <span>pikë</span>
        </div>
      </div>
      <div class="vp-score-details">
      <div class="vp-score-item">
            <span>Aplikime të pranuara (<?= $acceptedApps ?>)</span>
            <strong><?= $acceptedApps * 5 ?> pikë</strong>
        </div>
        <div class="vp-score-item">
            <span>Total aplikime (<?= $totalApps ?>)</span>
            <strong><?= $totalApps * 1 ?> pikë</strong>
        </div>
        <div class="vp-score-item">
             <span>Kërkesa dërguar (<?= $totalRequests ?>)</span>
             <strong><?= $totalRequests * 2 ?> pikë</strong>
        </div>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'notifications'): ?>
<!-- ════════════ NOTIFICATIONS TAB ════════════ -->
<div class="vp-panel">
  <div class="vp-card">
    <div class="vp-card__header">
      <h3>Njoftimet</h3>
      <button class="btn_secondary vp-btn-sm" id="vp-mark-all-read">Shëno të gjitha si të lexuara</button>
    </div>
    <div class="vp-card__body">
      <div id="vp-notification-list">
        <div class="vp-loading">Duke ngarkuar njoftimet…</div>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

</section>

</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>

<!-- Volunteer Panel JavaScript -->
<script>
const API = '/TiranaSolidare/api';
const csrfToken = '<?= csrf_token() ?>';

function vpStatus(elId, type, message) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.style.display = 'block';
  el.className = 'vp-status vp-status--' + type;
  el.textContent = message;
  setTimeout(() => { el.style.display = 'none'; }, 5000);
}

// ── Name form ──
const nameForm = document.getElementById('vp-name-form');
if (nameForm) {
  nameForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const emri = document.getElementById('vp-emri').value.trim();
    if (!emri) { vpStatus('vp-name-status', 'error', 'Ju lutem shkruani emrin.'); return; }
    try {
      const res = await fetch(API + '/users.php?action=update_profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({ emri }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      document.getElementById('vp-profile-emri').textContent = emri;
      const headerUser = document.querySelector('.header-user');
      if (headerUser) headerUser.textContent = emri;
      vpStatus('vp-name-status', 'success', 'Emri u përditësua me sukses.');
    } catch (err) { vpStatus('vp-name-status', 'error', err.message); }
  });
}

// ── Password form ──
const pwForm = document.getElementById('vp-password-form');
if (pwForm) {
  pwForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const current_password = document.getElementById('vp-current-pw').value;
    const new_password = document.getElementById('vp-new-pw').value;
    const confirm_password = document.getElementById('vp-confirm-pw').value;
    if (new_password !== confirm_password) { vpStatus('vp-pw-status', 'error', 'Fjalëkalimet nuk përputhen.'); return; }
    if (new_password.length < 6) { vpStatus('vp-pw-status', 'error', 'Fjalëkalimi duhet të ketë të paktën 6 karaktere.'); return; }
    try {
      const res = await fetch(API + '/auth.php?action=change_password', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({ current_password, new_password, confirm_password }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      pwForm.reset();
      vpStatus('vp-pw-status', 'success', 'Fjalëkalimi u përditësua me sukses.');
    } catch (err) { vpStatus('vp-pw-status', 'error', err.message); }
  });
}

// ── Help Request form ──
const reqForm = document.getElementById('vp-request-form');
if (reqForm) {
  reqForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(reqForm);
    const imageFile = fd.get('image');
    let imazhiValue = null;

    // Upload image if provided
    if (imageFile && imageFile.size > 0) {
      try {
        const uploadFd = new FormData();
        uploadFd.append('image', imageFile);
        const uploadRes = await fetch(API + '/upload.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': csrfToken },
          body: uploadFd,
        });
        const uploadJson = await uploadRes.json();
        if (!uploadRes.ok || !uploadJson.success) throw new Error(uploadJson.message || 'Gabim në ngarkimin e imazhit.');
        imazhiValue = uploadJson.data.filename;
      } catch (err) {
        vpStatus('vp-req-status', 'error', 'Gabim në ngarkimin e imazhit: ' + err.message);
        return;
      }
    }

    // Build request body with filename only
    const body = Object.fromEntries(fd);
    // Convert lat/lng to numbers if present
    if (body.latitude) body.latitude = parseFloat(body.latitude);
    else delete body.latitude;
    if (body.longitude) body.longitude = parseFloat(body.longitude);
    else delete body.longitude;
    
    delete body.image;
    if (imazhiValue) {
      body.imazhi = imazhiValue;
    }

    try {
      const res = await fetch(API + '/help_requests.php?action=create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify(body),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      reqForm.reset();
      const coordDisplay = document.getElementById('req-coord-display');
      if (coordDisplay) coordDisplay.style.display = 'none';
      vpStatus('vp-req-status', 'success', 'Kërkesa u dërgua me sukses! Kërkesat tuaja do të shfaqen në faqen e kërkesave.');
    } catch (err) { vpStatus('vp-req-status', 'error', err.message); }
  });
}

// ── Withdraw application ──
async function withdrawApp(id) {
  if (!confirm('Jeni i sigurt që doni të tërhiqni këtë aplikim?')) return;
  try {
    const res = await fetch(API + '/applications.php?action=withdraw&id=' + id, { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken }, credentials: 'same-origin' });
    const json = await res.json();
    if (json.success) { location.reload(); }
    else { alert(json.message || 'Gabim.'); }
  } catch (err) { alert('Gabim rrjeti.'); }
}

// ── Notifications ──
async function loadVPNotifications() {
  const container = document.getElementById('vp-notification-list');
  if (!container) return;
  try {
    const res = await fetch(API + '/notifications.php?action=list&limit=30', { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.success) throw new Error(json.message);
    const notifs = json.data.notifications;
    if (!notifs || notifs.length === 0) {
      container.innerHTML = '<div class="vp-empty"><svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg><p>Nuk keni njoftime.</p></div>';
      return;
    }
    let html = '';
    notifs.forEach(n => {
      const unread = !n.is_read;
      html += '<div class="vp-notif ' + (unread ? 'vp-notif--unread' : '') + '">'
        + '<div class="vp-notif__dot"></div>'
        + '<div class="vp-notif__body">'
        + '<p>' + escapeHtml(n.mesazhi) + '</p>'
        + '<span>' + formatDate(n.krijuar_me) + '</span>'
        + '</div>'
        + '<div class="vp-notif__actions">'
        + (unread ? '<button class="btn_secondary vp-btn-sm" onclick="markNotifRead(' + n.id_njoftimi + ')">✓</button>' : '')
        + '<button class="btn_secondary vp-btn-sm vp-btn-danger" onclick="deleteNotif(' + n.id_njoftimi + ')">✕</button>'
        + '</div></div>';
    });
    container.innerHTML = html;
  } catch (err) {
    container.innerHTML = '<p class="vp-muted">Gabim gjatë ngarkimit: ' + escapeHtml(err.message) + '</p>';
  }
}

async function markNotifRead(id) {
  await fetch(API + '/notifications.php?action=mark_read&id=' + id, { method: 'PUT', headers: { 'X-CSRF-Token': csrfToken }, credentials: 'same-origin' });
  loadVPNotifications();
  updateNotifBadge();
}

async function deleteNotif(id) {
  await fetch(API + '/notifications.php?action=delete&id=' + id, { method: 'DELETE', headers: { 'X-CSRF-Token': csrfToken }, credentials: 'same-origin' });
  loadVPNotifications();
}

const markAllBtn = document.getElementById('vp-mark-all-read');
if (markAllBtn) {
  markAllBtn.addEventListener('click', async () => {
    await fetch(API + '/notifications.php?action=mark_all_read', { method: 'PUT', headers: { 'X-CSRF-Token': csrfToken }, credentials: 'same-origin' });
    loadVPNotifications();
    updateNotifBadge();
  });
}

async function updateNotifBadge() {
  try {
    const res = await fetch(API + '/notifications.php?action=unread_count');
    const json = await res.json();
    const badge = document.getElementById('vp-tab-badge');
    const headerBadge = document.getElementById('notif-badge');
    if (json.success) {
      const count = json.data.unread;
      if (badge) { badge.textContent = count > 0 ? count : ''; badge.style.display = count > 0 ? 'inline-block' : 'none'; }
      if (headerBadge) { headerBadge.textContent = count > 0 ? count : ''; headerBadge.style.display = count > 0 ? 'inline-block' : 'none'; }
    }
  } catch (e) {}
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str || '';
  return div.innerHTML;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('sq-AL', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  loadVPNotifications();
  updateNotifBadge();
  setInterval(updateNotifBadge, 15000);
});
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/TiranaSolidare/assets/js/map-component.js"></script>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
<script>
// Initialize map picker for help request form
document.addEventListener('DOMContentLoaded', function() {
  const mapContainer = document.getElementById('request-map-picker');
  if (mapContainer) {
    const reqMap = TSMap.picker('request-map-picker', {
      latInput: 'req-lat-input',
      lngInput: 'req-lng-input',
      addressInput: 'vp-req-location',
      onSelect: function(lat, lng) {
        const coordDisplay = document.getElementById('req-coord-display');
        const coordText = document.getElementById('req-coord-text');
        if (coordDisplay && coordText) {
          coordDisplay.style.display = 'flex';
          coordText.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
        }
      }
    });
  }
});
</script>
<script>
const scoreCtx = document.getElementById('vp-score-chart');
if (scoreCtx) {
  new Chart(scoreCtx.getContext('2d'), {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [<?= $scorePercent ?>, <?= 100 - $scorePercent ?>],
        backgroundColor: ['#00715D', '#e5e7eb'],
        borderWidth: 0,
      }]
    },
    options: {
      cutout: '75%',
      plugins: { legend: { display: false }, tooltip: { enabled: false } },
    }
  });
}
</script>
</body>
</html>
