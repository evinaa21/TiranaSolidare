<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = ($isLoggedIn && ($_SESSION['roli'] ?? '') === 'Admin');
$currentUserId = $_SESSION['user_id'] ?? null;

// ── Single help request detail ──
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare(
        "SELECT k.*, p.emri AS krijuesi_emri, p.email AS krijuesi_email
         FROM Kerkesa_per_Ndihme k
         LEFT JOIN Perdoruesi p ON p.id_perdoruesi = k.id_perdoruesi
         WHERE k.id_kerkese_ndihme = ?"
    );
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── List view: paginated & filterable (only when not viewing detail) ──
$page = $limit = $offset = $total = $totalPages = 0;
$requests = [];
$search = $tipi = $statusi = '';
$statTotalKerkesa = $statClosed = $statVullnetare = $statOferta = 0;

if (!isset($_GET['id'])) {
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$tipi   = trim($_GET['tipi'] ?? '');
$statusi = trim($_GET['statusi'] ?? '');

$where  = [];
$params = [];
if ($search !== '') {
    $where[]  = '(k.titulli LIKE ? OR k.pershkrimi LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($tipi !== '') {
    $where[]  = 'k.tipi = ?';
    $params[] = $tipi;
}
if ($statusi !== '') {
    $where[] = 'k.statusi = ?';
    $params[] = $statusi;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Kerkesa_per_Ndihme k $whereSQL");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($total / $limit);

$sql = "SELECT k.*, p.emri AS krijuesi_emri
        FROM Kerkesa_per_Ndihme k
        LEFT JOIN Perdoruesi p ON p.id_perdoruesi = k.id_perdoruesi
        $whereSQL
        ORDER BY 
        CASE WHEN k.statusi = 'Open' THEN 0 ELSE 1 END ASC,
        k.krijuar_me DESC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Trust stats
$statTotalKerkesa = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme")->fetchColumn();
$statClosed       = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'Closed'")->fetchColumn();
$statVullnetare   = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'Vullnetar'")->fetchColumn();
$statOferta       = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'Ofertë'")->fetchColumn();} // end if (!isset($_GET['id']))
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($request) ? htmlspecialchars($request['titulli']) . ' — ' : '' ?>Kërkesat — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260308f">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/requests.css?v=20260308V">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/TiranaSolidare/assets/css/map.css">
</head>
<body class="page-requests">
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<?php if (isset($request) && $request): ?>
<!-- ═══════════════════════════════════════════════════════════
     SINGLE HELP REQUEST — DETAIL VIEW (Premium)
     ═══════════════════════════════════════════════════════════ -->
<section class="rq-detail-hero <?= $request['tipi'] === 'Ofertë' ? 'rq-detail-hero--offer' : 'rq-detail-hero--request' ?>">
  <!-- Decorative blobs -->
  <svg class="rq-blob rq-blob--hero-1" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,255,255,0.08)" d="M44.7,-76.4C58.8,-69.2,71.8,-58.7,79.6,-45.1C87.4,-31.5,90.1,-15.7,88.5,-0.9C86.9,13.9,81.1,27.8,72.6,39.6C64.1,51.4,52.9,61.2,40.1,68.4C27.3,75.6,13.7,80.3,-0.8,81.7C-15.3,83.1,-30.5,81.3,-43.4,74.2C-56.2,67.2,-66.7,55,-73.8,41.2C-80.8,27.3,-84.4,11.7,-83.5,-3.5C-82.6,-18.7,-77.2,-33.4,-68,-45.1C-58.8,-56.8,-45.9,-65.4,-32.3,-72.8C-18.7,-80.3,-9.3,-86.5,3.2,-91.9C15.7,-97.4,30.5,-83.6,44.7,-76.4Z" transform="translate(100 100)"/></svg>
  <svg class="rq-blob rq-blob--hero-2" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,215,0,0.06)" d="M39.5,-51.2C52.9,-46.3,66.8,-37.9,71.4,-25.7C76.1,-13.5,71.5,2.6,66,17.3C60.6,31.9,54.3,45.1,44,54.7C33.6,64.3,19.3,70.2,3.4,73.7C-12.6,77.2,-30.3,78.4,-42.2,70.1C-54,61.7,-60,43.8,-65.3,27.3C-70.6,10.8,-75.2,-4.2,-72.3,-18.2C-69.5,-32.1,-59.2,-45,-46.1,-50C-33.1,-55,-16.5,-52.2,-1.4,-50.2C13.7,-48.3,26.1,-56.1,39.5,-51.2Z" transform="translate(100 100)"/></svg>

  <div class="rq-detail-hero__inner">
    <a href="/TiranaSolidare/views/help_requests.php" class="rq-back-link">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
      Kthehu te kërkesat
    </a>
    <div class="rq-detail-hero__badges">
      <span class="rq-badge rq-badge--<?= $request['tipi'] === 'Ofertë' ? 'offer' : 'request' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        <?= htmlspecialchars($request['tipi']) ?>
      </span>
      <span class="rq-badge rq-badge--<?= strtolower($request['statusi']) ?>"><?= htmlspecialchars($request['statusi']) ?></span>
    </div>
    <h1><?= htmlspecialchars($request['titulli']) ?></h1>
    <div class="rq-detail-hero__meta">
      <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= htmlspecialchars($request['krijuesi_emri'] ?? 'Anonim') ?></span>
      <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?= koheParapake($request['krijuar_me']) ?></span>
    </div>
  </div>
</section>

<section class="rq-detail-body">
  <div class="rq-detail-layout">
    <!-- Main content -->
    <div class="rq-detail-main">
      <?php if (!empty($request['imazhi'])): ?>
        <div class="rq-detail-banner">
          <img src="<?= htmlspecialchars(strpos($request['imazhi'], '/') === 0 ? $request['imazhi'] : '/TiranaSolidare/public/assets/uploads/' . $request['imazhi']) ?>" alt="<?= htmlspecialchars($request['titulli']) ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
          <div class="rq-detail-banner--placeholder" style="display:none;">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            <p>Nuk ka imazh të ngarkuar</p>
          </div>
        </div>
      <?php else: ?>
        <div class="rq-detail-banner rq-detail-banner--placeholder">
          <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
          <p>Nuk ka imazh të ngarkuar</p>
        </div>
      <?php endif; ?>

      <div class="rq-detail-text">
        <h2>Përshkrimi i kërkesës</h2>
        <p><?= nl2br(htmlspecialchars($request['pershkrimi'] ?? 'Nuk ka përshkrim.')) ?></p>
      </div>

      <?php if (!empty($request['latitude']) && !empty($request['longitude'])): ?>
      <div class="map-detail-card">
        <div class="map-detail-card__header">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
          Vendndodhja në hartë
        </div>
        <div id="request-detail-map" class="ts-map-display"></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <aside class="rq-detail-sidebar">
      <div class="rq-sidebar-card">
        <h3>Informacione</h3>
        <ul class="rq-sidebar-info">
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div><span>Postuar nga</span><strong><?= htmlspecialchars($request['krijuesi_emri'] ?? 'N/A') ?></strong></div>
          </li>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            </div>
            <div><span>Tipi</span><strong><?= htmlspecialchars($request['tipi']) ?></strong></div>
          </li>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <div><span>Statusi</span><strong><?= htmlspecialchars($request['statusi']) ?></strong></div>
          </li>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </div>
            <div><span>Krijuar</span><strong><?= date('d/m/Y — H:i', strtotime($request['krijuar_me'])) ?></strong></div>
          </li>
          <?php if (!empty($request['vendndodhja'])): ?>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <div><span>Vendndodhja</span><strong><?= htmlspecialchars($request['vendndodhja']) ?></strong></div>
          </li>
          <?php endif; ?>
        </ul>

        <div class="rq-sidebar-cta">
          <?php if (!$isLoggedIn): ?>
            <a href="/TiranaSolidare/views/login.php?redirect=<?= urlencode('/TiranaSolidare/views/help_requests.php?id=' . $request['id_kerkese_ndihme']) ?>" class="rq-btn-full rq-btn-login">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
              Kyçu për të kontaktuar
            </a>
            <p class="rq-sidebar-hint">Duhet të jeni i kyçur për të kontaktuar postuesin. <a href="/TiranaSolidare/views/login.php?redirect=<?= urlencode('/TiranaSolidare/views/help_requests.php?id=' . $request['id_kerkese_ndihme']) ?>" class="rq-hint-link">Kyçu këtu &rarr;</a></p>
          <?php elseif (($isAdmin || (int)$request['id_perdoruesi'] === (int)$currentUserId) && !empty($request['krijuesi_email'])): ?>
            <a href="mailto:<?= htmlspecialchars($request['krijuesi_email']) ?>" class="btn_primary rq-btn-full">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              Kontakto përmes email
            </a>
          <?php elseif ($isLoggedIn): ?>
            <p class="rq-sidebar-hint">Kontakti i postuesit është i disponueshëm vetëm për pronarin e kërkesës.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Trust box -->
      <div class="rq-sidebar-trust">
        <div class="rq-sidebar-trust__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
        </div>
        <p><strong>Platformë komunitare</strong></p>
        <p class="rq-sidebar-trust__sub">Kjo platformë mundëson lidhje direkte midis vullnetarëve dhe atyre që kanë nevojë.</p>
      </div>
    </aside>
  </div>
</section>

<?php elseif (isset($_GET['id']) && !$request): ?>
<!-- ── NOT FOUND ── -->
<section class="rq-hero">
  <div class="rq-hero__inner">
    <h1>Kërkesa nuk u gjet</h1>
    <p>Kjo faqe nuk ekziston ose kërkesa është fshirë.</p>
    <a href="/TiranaSolidare/views/help_requests.php" class="btn_primary" style="margin-top:24px;display:inline-block;">Kthehu te kërkesat</a>
  </div>
</section>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════
     HELP REQUESTS — BROWSE / LIST VIEW
     ═══════════════════════════════════════════════════════════ -->

<!-- ─── HERO ─── -->
<section class="rq-hero rq-hero--requests">
  <!-- Animated SVG blobs -->
  <svg class="rq-blob rq-blob--1" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,255,255,0.15)" d="M44.7,-76.4C58.8,-69.2,71.8,-58.7,79.6,-45.1C87.4,-31.5,90.1,-15.7,88.5,-0.9C86.9,13.9,81.1,27.8,72.6,39.6C64.1,51.4,52.9,61.2,40.1,68.4C27.3,75.6,13.7,80.3,-0.8,81.7C-15.3,83.1,-30.5,81.3,-43.4,74.2C-56.2,67.2,-66.7,55,-73.8,41.2C-80.8,27.3,-84.4,11.7,-83.5,-3.5C-82.6,-18.7,-77.2,-33.4,-68,-45.1C-58.8,-56.8,-45.9,-65.4,-32.3,-72.8C-18.7,-80.3,-9.3,-86.5,3.2,-91.9C15.7,-97.4,30.5,-83.6,44.7,-76.4Z" transform="translate(100 100)"/></svg>
  <svg class="rq-blob rq-blob--2" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,215,0,0.15)" d="M39.5,-51.2C52.9,-46.3,66.8,-37.9,71.4,-25.7C76.1,-13.5,71.5,2.6,66,17.3C60.6,31.9,54.3,45.1,44,54.7C33.6,64.3,19.3,70.2,3.4,73.7C-12.6,77.2,-30.3,78.4,-42.2,70.1C-54,61.7,-60,43.8,-65.3,27.3C-70.6,10.8,-75.2,-4.2,-72.3,-18.2C-69.5,-32.1,-59.2,-45,-46.1,-50C-33.1,-55,-16.5,-52.2,-1.4,-50.2C13.7,-48.3,26.1,-56.1,39.5,-51.2Z" transform="translate(100 100)"/></svg>
  <svg class="rq-blob rq-blob--3" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,255,255,0.1)" d="M47.7,-73.2C60.9,-67.5,70,-53.1,76.3,-38C82.6,-22.8,86.2,-6.9,83.4,7.6C80.6,22.2,71.5,35.4,60.7,47.1C49.9,58.8,37.5,69,23.3,74.3C9.1,79.6,-6.9,80,-21.4,75.4C-35.9,70.8,-48.9,61.3,-58.8,49.1C-68.7,36.9,-75.5,22,-77.2,6.3C-78.9,-9.4,-75.5,-25.9,-67,-38.7C-58.5,-51.5,-44.9,-60.5,-31,-66.3C-17.1,-72.1,-3,-74.7,8.8,-71.1C20.5,-67.5,34.5,-78.9,47.7,-73.2Z" transform="translate(100 100)"/></svg>
  <div class="rq-grid-overlay"></div>

  <div class="rq-hero__inner">
    <span class="rq-hero__label">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      Komuniteti në veprim
    </span>
    <h1>Kërkesat për Ndihmë</h1>
    <p class="rq-hero__subtitle">Gjej ku mund të ndihmosh — ose posto kërkesën tënde dhe lejo komunitetin të të mbështesë.</p>

    <!-- Trust stats -->
    <div class="rq-trust-bar">
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <div><strong><?= $statVullnetare ?></strong><span>Vullnetarë</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        <div><strong><?= $statTotalKerkesa ?></strong><span>Kërkesa</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
        <div><strong><?= $statClosed ?></strong><span>Të zgjidhura</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
        <div><strong><?= $statOferta ?></strong><span>Oferta</span></div>
      </div>
    </div>
  </div>
</section>

<!-- ─── FILTERS ─── -->
<section class="rq-filters-section">
  <svg class="rq-blob rq-blob--filters" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(225,114,84,0.05)" d="M44.7,-76.4C58.8,-69.2,71.8,-58.7,79.6,-45.1C87.4,-31.5,90.1,-15.7,88.5,-0.9C86.9,13.9,81.1,27.8,72.6,39.6C64.1,51.4,52.9,61.2,40.1,68.4C27.3,75.6,13.7,80.3,-0.8,81.7C-15.3,83.1,-30.5,81.3,-43.4,74.2C-56.2,67.2,-66.7,55,-73.8,41.2C-80.8,27.3,-84.4,11.7,-83.5,-3.5C-82.6,-18.7,-77.2,-33.4,-68,-45.1C-58.8,-56.8,-45.9,-65.4,-32.3,-72.8C-18.7,-80.3,-9.3,-86.5,3.2,-91.9C15.7,-97.4,30.5,-83.6,44.7,-76.4Z" transform="translate(100 100)"/></svg>

  <form class="rq-filters" method="GET" action="">
    <div class="rq-filters__search">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Kërko sipas titullit ose përshkrimit...">
    </div>
    <div class="rq-filters__pills">
      <select name="tipi" onchange="this.form.submit()">
        <option value="">Të gjitha tipet</option>
        <option value="Kërkesë" <?= $tipi === 'Kërkesë' ? 'selected' : '' ?>>Kërkesë</option>
        <option value="Ofertë" <?= $tipi === 'Ofertë' ? 'selected' : '' ?>>Ofertë</option>
      </select>
      <select name="statusi" onchange="this.form.submit()">
        <option value="">Të gjitha statuset</option>
        <option value="Open" <?= $statusi === 'Open' ? 'selected' : '' ?>>Open</option>
        <option value="Closed" <?= $statusi === 'Closed' ? 'selected' : '' ?>>Closed</option>
      </select>
      <button type="submit" class="rq-filters__btn">Kërko</button>
    </div>
  </form>
</section>

<!-- ─── RESULTS ─── -->
<section class="rq-results">
  <?php if (empty($requests)): ?>
    <div class="rq-empty">
      <div class="rq-empty__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>
      </div>
      <h3>Nuk u gjetën kërkesa</h3>
      <p><?= $search ? 'Asnjë rezultat për "' . htmlspecialchars($search) . '". Provo me fjalë të tjera.' : 'Nuk ka kërkesa aktive për momentin.' ?></p>
    </div>
  <?php else: ?>
    <div class="rq-results__header">
      <p class="rq-results__count"><?= $total ?> kërkesa u gjetën</p>
    </div>

    <div class="rq-grid">
      <?php foreach ($requests as $i => $req): ?>
        <a href="/TiranaSolidare/views/help_requests.php?id=<?= $req['id_kerkese_ndihme'] ?>" class="rq-card" style="animation-delay: <?= $i * 0.05 ?>s">
          <div class="rq-card__visual">
            <?php if (!empty($req['imazhi'])): ?>
              <img src="<?= htmlspecialchars(strpos($req['imazhi'], '/') === 0 ? $req['imazhi'] : '/TiranaSolidare/public/assets/uploads/' . $req['imazhi']) ?>" alt="<?= htmlspecialchars($req['titulli']) ?>" class="rq-card__img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
              <div class="rq-card__img rq-card__img--placeholder <?= $req['tipi'] === 'Ofertë' ? 'rq-card__img--offer' : '' ?>" style="display:none;">
                <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
              </div>
            <?php else: ?>
              <div class="rq-card__img rq-card__img--placeholder <?= $req['tipi'] === 'Ofertë' ? 'rq-card__img--offer' : '' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
              </div>
            <?php endif; ?>
            <div class="rq-card__overlay">
              <span class="rq-badge rq-badge--<?= $req['tipi'] === 'Ofertë' ? 'offer' : 'request' ?>"><?= htmlspecialchars($req['tipi']) ?></span>
              <span class="rq-badge rq-badge--<?= strtolower($req['statusi']) ?>"><?= htmlspecialchars($req['statusi']) ?></span>
            </div>
          </div>
          <div class="rq-card__content">
            <h3 class="rq-card__title"><?= htmlspecialchars($req['titulli']) ?></h3>
            <p class="rq-card__desc"><?= htmlspecialchars(mb_substr($req['pershkrimi'] ?? '', 0, 110)) ?>...</p>
            <div class="rq-card__footer">
              <div class="rq-card__meta">
                <span>
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  <?= htmlspecialchars($req['krijuesi_emri'] ?? 'Anonim') ?>
                </span>
                <span>
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  <?= koheParapake($req['krijuar_me']) ?>
                </span>
              </div>
              <span class="rq-card__arrow">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
              </span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
      <nav class="rq-pagination">
        <?php if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&tipi=<?= urlencode($tipi) ?>&statusi=<?= urlencode($statusi) ?>" class="rq-pagination__btn rq-pagination__btn--nav">&larr; Para</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tipi=<?= urlencode($tipi) ?>&statusi=<?= urlencode($statusi) ?>"
             class="rq-pagination__btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&tipi=<?= urlencode($tipi) ?>&statusi=<?= urlencode($statusi) ?>" class="rq-pagination__btn rq-pagination__btn--nav">Tjetër &rarr;</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<!-- ─── CTA SECTION ─── -->
<section class="rq-cta">
  <svg class="rq-blob rq-blob--cta" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,255,255,0.06)" d="M39.5,-51.2C52.9,-46.3,66.8,-37.9,71.4,-25.7C76.1,-13.5,71.5,2.6,66,17.3C60.6,31.9,54.3,45.1,44,54.7C33.6,64.3,19.3,70.2,3.4,73.7C-12.6,77.2,-30.3,78.4,-42.2,70.1C-54,61.7,-60,43.8,-65.3,27.3C-70.6,10.8,-75.2,-4.2,-72.3,-18.2C-69.5,-32.1,-59.2,-45,-46.1,-50C-33.1,-55,-16.5,-52.2,-1.4,-50.2C13.7,-48.3,26.1,-56.1,39.5,-51.2Z" transform="translate(100 100)"/></svg>
  <div class="rq-cta__inner">
    <h2>Ke nevojë për ndihmë?</h2>
    <p>Posto kërkesën tënde dhe komuniteti ynë do të të mbështesë. Regjistrimi është falas dhe i shpejtë.</p>
    <?php if ($isLoggedIn): ?>
      <a href="/TiranaSolidare/views/volunteer_panel.php?tab=new-request" class="btn_primary">Shko te paneli</a>
    <?php else: ?>
      <a href="/TiranaSolidare/views/register.php" class="btn_primary">Regjistrohu tani</a>
    <?php endif; ?>
  </div>
</section>

<?php endif; ?>

</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/TiranaSolidare/assets/js/map-component.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const mapEl = document.getElementById('request-detail-map');
  if (mapEl) {
    TSMap.display('request-detail-map', {
      lat: <?= json_encode($request['latitude'] ?? null) ?>,
      lng: <?= json_encode($request['longitude'] ?? null) ?>,
      label: <?= json_encode($request['titulli'] ?? '') ?>,
      type: <?= json_encode(($request['tipi'] ?? '') === 'Ofertë' ? 'offer' : 'request') ?>
    });
  }
});
</script>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
