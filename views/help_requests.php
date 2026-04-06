<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/status_labels.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = ($isLoggedIn && in_array(ts_normalize_value($_SESSION['roli'] ?? ''), ['admin', 'super_admin'], true));
$currentUserId = $_SESSION['user_id'] ?? null;
$request = null;
$requestMatching = null;
$requestMatchingModeLabel = '';
$requestCapacitySummary = '';
$requestCapacityDetail = '';
$requestQueueSummary = '';
$listMatchingById = [];
$requestLocationUnlockedIds = [];
$canViewRequestLocation = false;

if ($isLoggedIn && !$isAdmin) {
  try {
    $locationStmt = $pdo->prepare(
      'SELECT id_kerkese_ndihme, statusi FROM Aplikimi_Kerkese WHERE id_perdoruesi = ?'
    );
    $locationStmt->execute([(int) $currentUserId]);
    foreach ($locationStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      if (ts_help_request_application_unlocks_location($row['statusi'] ?? null)) {
        $requestLocationUnlockedIds[] = (int) $row['id_kerkese_ndihme'];
      }
    }
    $requestLocationUnlockedIds = array_values(array_unique($requestLocationUnlockedIds));
  } catch (Throwable $e) {
    error_log('help_requests view location ids: ' . $e->getMessage());
  }
}

// ── Single help request detail ──
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare(
        "SELECT k.*, p.emri AS krijuesi_emri, p.email AS krijuesi_email, kat.emri AS kategoria_emri
         FROM Kerkesa_per_Ndihme k
         LEFT JOIN Perdoruesi p ON p.id_perdoruesi = k.id_perdoruesi
         LEFT JOIN Kategoria kat ON kat.id_kategoria = k.id_kategoria
         WHERE k.id_kerkese_ndihme = ?"
    );
    $stmt->execute([$id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($request) {
      // Enforce moderation: non-approved posts hidden from non-owner non-admins
      $requestModStatus = $request['moderation_status'] ?? 'approved';
      $isRequestOwner = $isLoggedIn && ((int) $request['id_perdoruesi'] === (int) $currentUserId);
      if ($requestModStatus !== 'approved' && !$isAdmin && !$isRequestOwner) {
          $request = null; // Hide from unauthorized viewers
      }
    }
    if ($request) {
      $request = ts_normalize_row($request);
      $requestMatching = ts_help_request_matching_details(
        $request,
        ts_help_request_application_counts($pdo, (int) $request['id_kerkese_ndihme'])
      );
      $request['statusi'] = $requestMatching['resolved_status'];
      $requestMatchingModeLabel = match ($requestMatching['matching_mode']) {
        'single' => 'Një përputhje',
        'limited' => 'Kapacitet i kufizuar',
        default => 'Kapacitet i hapur',
      };
      $requestCapacitySummary = $requestMatching['has_capacity_limit']
        ? ($requestMatching['progress_count'] . ' / ' . $requestMatching['capacity_total'] . ' përputhje')
        : 'Pa kufi kapaciteti';
      if ($requestMatching['resolved_status'] === 'completed') {
        $requestCapacityDetail = $requestMatching['matched_total'] > 0
          ? ($requestMatching['matched_total'] . ' përputhje të përfunduara')
          : 'Postimi u mbyll pa një përputhje të regjistruar.';
      } elseif ($requestMatching['has_capacity_limit']) {
        $requestCapacityDetail = ($requestMatching['slots_remaining'] ?? 0) > 0
          ? ($requestMatching['slots_remaining'] . ' vende të lira')
          : 'Kapaciteti aktual është i plotë';
      } else {
        $requestCapacityDetail = $requestMatching['counts']['pending'] > 0
          ? ($requestMatching['counts']['pending'] . ' aplikime në shqyrtim')
          : 'Postimi pranon pa kufi aplikime të reja';
      }

      if (($requestMatching['counts']['waitlisted'] ?? 0) > 0) {
        $requestQueueSummary = $requestMatching['counts']['waitlisted'] . ' në listë pritjeje';
      } elseif (($requestMatching['counts']['pending'] ?? 0) > 0) {
        $requestQueueSummary = $requestMatching['counts']['pending'] . ' në shqyrtim';
      } elseif (($requestMatching['matched_total'] ?? 0) > 0) {
        $requestQueueSummary = $requestMatching['matched_total'] . ' të përputhura';
      } else {
        $requestQueueSummary = 'Asnjë aplikim ende';
      }
    }
}

  $isOwner = false;
  $canApplyToRequest = false;
  $myRequestApplication = null;
  $requestApplicants = [];
  $requestApplicantsTotal = 0;

  if (isset($request) && $request) {
    $isOwner = $isLoggedIn && ((int) $request['id_perdoruesi'] === (int) $currentUserId);
    $canApplyToRequest = $isLoggedIn
      && !$isAdmin
      && !$isOwner
      && !in_array(($request['statusi'] ?? ''), TS_HELP_REQUEST_TERMINAL_STATUSES, true);

    try {
      if ($isLoggedIn && !$isAdmin && !$isOwner) {
        $myApplyStmt = $pdo->prepare(
          'SELECT id_aplikimi_kerkese, statusi, aplikuar_me
           FROM Aplikimi_Kerkese
           WHERE id_kerkese_ndihme = ? AND id_perdoruesi = ?
           LIMIT 1'
        );
        $myApplyStmt->execute([(int) $request['id_kerkese_ndihme'], (int) $currentUserId]);
        $myRequestApplication = $myApplyStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($myRequestApplication) $myRequestApplication = ts_normalize_row($myRequestApplication);
      }

      $canViewRequestLocation = $isOwner
        || $isAdmin
        || ($myRequestApplication && ts_help_request_application_unlocks_location($myRequestApplication['statusi'] ?? null));

      if (!$canViewRequestLocation && !empty($request)) {
        $request = ts_strip_help_request_location($request);
      }

      if ($isOwner || $isAdmin) {
        $requestApplicantsTotal = (int) ($requestMatching['total_applications'] ?? 0);

        $applicantsStmt = $pdo->prepare(
          'SELECT ak.id_aplikimi_kerkese, ak.statusi, ak.aplikuar_me,
              p.id_perdoruesi, p.emri, p.email
           FROM Aplikimi_Kerkese ak
           JOIN Perdoruesi p ON p.id_perdoruesi = ak.id_perdoruesi
           WHERE ak.id_kerkese_ndihme = ?
           ORDER BY FIELD(LOWER(ak.statusi), "approved", "pending", "waitlisted", "completed", "rejected", "withdrawn"), ak.aplikuar_me DESC'
        );
        $applicantsStmt->execute([(int) $request['id_kerkese_ndihme']]);
        $requestApplicants = ts_normalize_rows($applicantsStmt->fetchAll(PDO::FETCH_ASSOC));
      }
    } catch (Throwable $e) {
      // Keep the page functional even if the DB table has not been migrated yet.
      error_log('help_requests view applications: ' . $e->getMessage());
    }
  }

// ── List view: paginated & filterable (only when not viewing detail) ──
$page = $limit = $offset = $total = $totalPages = 0;
$requests = [];
$search = $tipi = $statusi = '';
$statTotalKerkesa = $statCompleted = $statVullnetare = $statOferta = 0;

if (!isset($_GET['id'])) {
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$tipi   = trim($_GET['tipi'] ?? '');
$statusi = trim($_GET['statusi'] ?? '');
$kategoria = trim($_GET['kategoria'] ?? '');

// Fetch categories for filter
$categories = $pdo->query("SELECT id_kategoria, emri FROM Kategoria ORDER BY emri")->fetchAll(PDO::FETCH_ASSOC);

$where  = [];
$params = [];

// Moderation visibility: public list shows only approved posts
if (!$isAdmin) {
    $where[] = "k.moderation_status = 'approved'";
}

// Fshih kërkesat e përdoruesve të bllokuar
if ($isLoggedIn && $currentUserId) {
    $where[] = 'NOT EXISTS (
        SELECT 1 FROM user_blocks
        WHERE (blocker_id = ? AND blocked_id = k.id_perdoruesi)
           OR (blocker_id = k.id_perdoruesi AND blocked_id = ?)
    )';
    $params[] = (int) $currentUserId;
    $params[] = (int) $currentUserId;
}

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
if ($kategoria !== '') {
    $where[] = 'k.id_kategoria = ?';
    $params[] = (int) $kategoria;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Kerkesa_per_Ndihme k $whereSQL");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($total / $limit);

$sql = "SELECT k.*, p.emri AS krijuesi_emri, kat.emri AS kategoria_emri
        FROM Kerkesa_per_Ndihme k
        LEFT JOIN Perdoruesi p ON p.id_perdoruesi = k.id_perdoruesi
        LEFT JOIN Kategoria kat ON kat.id_kategoria = k.id_kategoria
        $whereSQL
        ORDER BY 
        CASE WHEN k.statusi IN ('open','Open') THEN 0 ELSE 1 END ASC,
        k.krijuar_me DESC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

$requestCountsById = ts_help_request_application_counts_by_request_ids(
  $pdo,
  array_map(static fn (array $row): int => (int) ($row['id_kerkese_ndihme'] ?? 0), $requests)
);

foreach ($requests as &$req) {
  $requestId = (int) ($req['id_kerkese_ndihme'] ?? 0);
  $listMatchingById[$requestId] = ts_help_request_matching_details($req, $requestCountsById[$requestId] ?? []);
  $req['statusi'] = $listMatchingById[$requestId]['resolved_status'];
}
unset($req);

// Trust stats (only approved posts in public counts)
$statTotalKerkesa = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE moderation_status = 'approved'")->fetchColumn();
$statOpen         = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'open' AND moderation_status = 'approved'")->fetchColumn();
$statCompleted    = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi IN ('completed', 'closed') AND moderation_status = 'approved'")->fetchColumn();
$statVullnetare   = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'volunteer'")->fetchColumn();
$statOferta       = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'offer' AND moderation_status = 'approved'")->fetchColumn();
$statKerkesa      = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'request' AND moderation_status = 'approved'")->fetchColumn();} // end if (!isset($_GET['id']))
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($request) ? htmlspecialchars($request['titulli']) . ' — ' : '' ?>Kërkesat — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/requests.css?v=20260321a">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/TiranaSolidare/assets/css/map.css?v=20260401a">
  <?= csrf_meta() ?>
</head>
<body class="page-requests">
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<?php if (isset($request) && $request): ?>
<!-- ═══════════════════════════════════════════════════════════
     SINGLE HELP REQUEST — DETAIL VIEW (Premium)
     ═══════════════════════════════════════════════════════════ -->
<section class="rq-detail-hero <?= $request['tipi'] === 'offer' ? 'rq-detail-hero--offer' : 'rq-detail-hero--request' ?>">
  <!-- Decorative blobs -->
  <svg class="rq-blob rq-blob--hero-1" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,255,255,0.08)" d="M44.7,-76.4C58.8,-69.2,71.8,-58.7,79.6,-45.1C87.4,-31.5,90.1,-15.7,88.5,-0.9C86.9,13.9,81.1,27.8,72.6,39.6C64.1,51.4,52.9,61.2,40.1,68.4C27.3,75.6,13.7,80.3,-0.8,81.7C-15.3,83.1,-30.5,81.3,-43.4,74.2C-56.2,67.2,-66.7,55,-73.8,41.2C-80.8,27.3,-84.4,11.7,-83.5,-3.5C-82.6,-18.7,-77.2,-33.4,-68,-45.1C-58.8,-56.8,-45.9,-65.4,-32.3,-72.8C-18.7,-80.3,-9.3,-86.5,3.2,-91.9C15.7,-97.4,30.5,-83.6,44.7,-76.4Z" transform="translate(100 100)"/></svg>
  <svg class="rq-blob rq-blob--hero-2" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg"><path fill="rgba(255,215,0,0.06)" d="M39.5,-51.2C52.9,-46.3,66.8,-37.9,71.4,-25.7C76.1,-13.5,71.5,2.6,66,17.3C60.6,31.9,54.3,45.1,44,54.7C33.6,64.3,19.3,70.2,3.4,73.7C-12.6,77.2,-30.3,78.4,-42.2,70.1C-54,61.7,-60,43.8,-65.3,27.3C-70.6,10.8,-75.2,-4.2,-72.3,-18.2C-69.5,-32.1,-59.2,-45,-46.1,-50C-33.1,-55,-16.5,-52.2,-1.4,-50.2C13.7,-48.3,26.1,-56.1,39.5,-51.2Z" transform="translate(100 100)"/></svg>

  <div class="rq-detail-hero__inner">
    <a href="/TiranaSolidare/views/help_requests.php" class="rq-back-link">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
      Kthehu te kërkesat
    </a>
    <div class="rq-detail-hero__badges">
      <span class="rq-badge rq-badge--<?= $request['tipi'] === 'offer' ? 'offer' : 'request' ?>">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        <?= $request['tipi'] === 'request' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?>
      </span>
     <span class="rq-badge rq-badge--<?= $request['statusi'] ?>"><?= status_label($request['statusi']) ?></span>
     <?php
       $detailModStatus = $request['moderation_status'] ?? 'approved';
       if ($detailModStatus === 'pending_review'): ?>
     <span class="rq-badge" style="background:#fef3c7;color:#92400e;">&#9203; <?= status_label('pending_review') ?></span>
     <?php elseif ($detailModStatus === 'rejected'): ?>
     <span class="rq-badge" style="background:#fee2e2;color:#991b1b;">&#10007; <?= status_label('rejected') ?></span>
     <?php endif; ?>
     <?php if (!empty($request['kategoria_emri'])): ?>
     <span class="rq-badge rq-badge--category">
       <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
       <?= htmlspecialchars($request['kategoria_emri']) ?>
     </span>
     <?php endif; ?>
    </div>
    <h1><?= htmlspecialchars($request['titulli']) ?></h1>
    <div class="rq-detail-hero__meta">
      <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <a class="rq-poster-link" href="<?= htmlspecialchars(ts_public_profile_url((int) ($request['id_perdoruesi'] ?? 0), (string) ($request['krijuesi_emri'] ?? 'Anonim'))) ?>"><?= htmlspecialchars($request['krijuesi_emri'] ?? 'Anonim') ?></a></span>
      <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?= koheParapake($request['krijuar_me']) ?></span>
    </div>
  </div>
</section>

<section class="rq-detail-body">
  <div class="rq-detail-layout">
    <!-- Main content -->
    <div class="rq-detail-main">
      <div class="rq-detail-text">
        <h2>Përshkrimi i kërkesës</h2>
        <p><?= nl2br(htmlspecialchars($request['pershkrimi'] ?? 'Nuk ka përshkrim.')) ?></p>
      </div>

      <?php if ($canViewRequestLocation && !empty($request['latitude']) && !empty($request['longitude'])): ?>
      <div class="map-detail-card">
        <div class="map-detail-card__header">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
          Vendndodhja në hartë
        </div>
        <div id="request-detail-map" class="ts-map-display"></div>
      </div>
      <?php elseif (!$canViewRequestLocation): ?>
      <div class="map-detail-card" style="background:#f8fafc;border:1px solid #dbe7e2;">
        <div class="map-detail-card__header">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12 11 14 15 10"/><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
          Vendndodhja është e mbrojtur
        </div>
        <p style="margin:0;color:#506172;line-height:1.7;">Për siguri dhe privatësi, vendndodhja e saktë shfaqet vetëm për postuesin, administratorët ose pasi të keni aplikuar në këtë postim.</p>
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
            <div><span>Postuar nga</span><strong><a class="rq-poster-link" href="<?= htmlspecialchars(ts_public_profile_url((int) ($request['id_perdoruesi'] ?? 0), (string) ($request['krijuesi_emri'] ?? 'N/A'))) ?>"><?= htmlspecialchars($request['krijuesi_emri'] ?? 'N/A') ?></a></strong></div>
          </li>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            </div>
            <div><span>Tipi</span><strong><?= $request['tipi'] === 'request' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?></strong></div>
          </li>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <div><span>Statusi</span><strong><?= htmlspecialchars(status_label($request['statusi'])) ?></strong></div>
          </li>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h18"/><path d="M3 6h18"/><path d="M3 18h18"/></svg>
            </div>
            <div><span>Modaliteti</span><strong><?= htmlspecialchars($requestMatchingModeLabel) ?></strong></div>
          </li>
          <?php if ($isOwner || $isAdmin): ?>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div><span>Aplikime</span><strong><?= (int) $requestApplicantsTotal ?></strong></div>
          </li>
          <?php endif; ?>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </div>
            <div><span>Krijuar</span><strong><?= date('d/m/Y — H:i', strtotime($request['krijuar_me'])) ?></strong></div>
          </li>
          <?php if ($canViewRequestLocation && !empty($request['vendndodhja'])): ?>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
            </div>
            <div><span>Vendndodhja</span><strong><?= htmlspecialchars($request['vendndodhja']) ?></strong></div>
          </li>
          <?php elseif (!$canViewRequestLocation && ($canApplyToRequest || !$isOwner)): ?>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
            </div>
            <div><span>Vendndodhja</span><strong>Shfaqet pasi të aplikoni</strong></div>
          </li>
          <?php endif; ?>
        </ul>

        <div class="rq-capacity-panel">
          <div class="rq-capacity-panel__head">
            <span>Gjendja e përputhjes</span>
            <strong><?= htmlspecialchars($requestCapacitySummary) ?></strong>
          </div>
          <?php if (!empty($requestMatching['has_capacity_limit'])): ?>
            <?php $capacityPercent = (int) min(100, round((($requestMatching['progress_count'] ?? 0) / max(1, (int) ($requestMatching['capacity_total'] ?? 1))) * 100)); ?>
            <div class="rq-capacity-panel__bar" aria-hidden="true"><span style="width: <?= $capacityPercent ?>%"></span></div>
          <?php endif; ?>
          <p class="rq-capacity-panel__detail"><?= htmlspecialchars($requestCapacityDetail) ?></p>
          <p class="rq-capacity-panel__detail rq-capacity-panel__detail--muted"><?= htmlspecialchars($requestQueueSummary) ?></p>
        </div>

        <?php
          $detailModStatusCta = $request['moderation_status'] ?? 'approved';
          if ($isAdmin && $detailModStatusCta !== 'approved'): ?>
        <div class="rq-sidebar-card" style="margin-top:16px;border:2px solid #f59e0b;background:#fffbeb;">
          <h3 style="color:#92400e;">Moderimi</h3>
          <p style="font-size:0.85rem;color:#78350f;margin-bottom:12px;">
            Ky postim është <strong><?= status_label($detailModStatusCta) ?></strong>. Zgjidhni një veprim:
          </p>
          <div style="display:flex;gap:8px;">
            <button onclick="moderateRequest(<?= (int) $request['id_kerkese_ndihme'] ?>, 'approve_request')" class="rq-btn-full" style="background:#10b981;color:#fff;border:none;cursor:pointer;flex:1;">
              &#10003; Mirato
            </button>
            <?php if ($detailModStatusCta !== 'rejected'): ?>
            <button onclick="moderateRequest(<?= (int) $request['id_kerkese_ndihme'] ?>, 'reject_request')" class="rq-btn-full" style="background:#ef4444;color:#fff;border:none;cursor:pointer;flex:1;">
              &#10007; Refuzo
            </button>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="rq-sidebar-cta">
          <?php if (!$isLoggedIn): ?>
            <a href="/TiranaSolidare/views/login.php?redirect=<?= urlencode('/TiranaSolidare/views/help_requests.php?id=' . $request['id_kerkese_ndihme']) ?>" class="rq-btn-full rq-btn-login">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
              Kyçu për të aplikuar
            </a>
            <p class="rq-sidebar-hint">Duhet të jeni i kyçur për të aplikuar. <a href="/TiranaSolidare/views/login.php?redirect=<?= urlencode('/TiranaSolidare/views/help_requests.php?id=' . $request['id_kerkese_ndihme']) ?>" class="rq-hint-link">Kyçu këtu &rarr;</a></p>
          <?php elseif ($canApplyToRequest && !$myRequestApplication): ?>
            <?php $applyLabel = ($requestMatching['resolved_status'] ?? 'open') === 'filled'
              ? 'Bashkohu në listën e pritjes'
              : ($request['tipi'] === 'request' ? 'Dua të ndihmoj' : 'Kam nevojë për këtë'); ?>
            <button type="button" class="rq-btn-full" id="rq-apply-btn" data-request-id="<?= (int) $request['id_kerkese_ndihme'] ?>" data-default-label="<?= htmlspecialchars($applyLabel) ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
              <?= htmlspecialchars($applyLabel) ?>
            </button>
            <p class="rq-sidebar-hint">
              <?= ($requestMatching['resolved_status'] ?? 'open') === 'filled'
                ? 'Kapaciteti aktiv është i mbushur, por mund të bashkoheni në listën e pritjes.'
                : 'Postuesi do të njoftohet menjëherë dhe do të mund t\'ju kontaktojë me email. Vendndodhja do të hapet sapo aplikimi të regjistrohet.' ?>
            </p>
            <div class="rq-inline-status" id="rq-apply-status" style="display:none"></div>
          <?php elseif ($myRequestApplication): ?>
            <div class="rq-applied-box">
              <strong>Ju keni aplikuar për këtë kërkesë.</strong>
              <span>Statusi: <?= htmlspecialchars(status_label($myRequestApplication['statusi'] ?? 'pending')) ?></span>
              <span>Aplikuar: <?= date('d/m/Y H:i', strtotime($myRequestApplication['aplikuar_me'] ?? 'now')) ?></span>
            </div>
            <?php if (in_array(($myRequestApplication['statusi'] ?? ''), ['pending', 'approved', 'waitlisted'], true)): ?>
              <button type="button" class="rq-btn-full rq-btn-cancel" id="rq-withdraw-btn" data-app-id="<?= (int) $myRequestApplication['id_aplikimi_kerkese'] ?>" style="margin-top:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                Tërhiq aplikimin
              </button>
              <div class="rq-inline-status" id="rq-withdraw-status" style="display:none"></div>
            <?php endif; ?>
            <p class="rq-sidebar-hint">Këtë kërkesë e gjeni edhe te paneli juaj në seksionin “Aplikimet e mia”.</p>
         <?php elseif ($isOwner || $isAdmin): ?>
<?php if (in_array(($request['statusi'] ?? ''), TS_HELP_REQUEST_ACTIVE_STATUSES, true)): ?>
    <button type="button" class="rq-btn-full rq-btn-close" id="rq-complete-btn" data-request-id="<?= (int) $request['id_kerkese_ndihme'] ?>" style="margin-top:12px;background:rgba(16,185,129,0.12);color:#065f46;border:1.5px solid rgba(16,185,129,0.35);">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
        Shëno si të përfunduar
    </button>
    <div class="rq-inline-status" id="rq-complete-status" style="display:none"></div>
    <button type="button" class="rq-btn-full rq-btn-cancel" id="rq-cancel-btn" data-request-id="<?= (int) $request['id_kerkese_ndihme'] ?>" style="margin-top:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        Anulo postimin
    </button>
    <div class="rq-inline-status" id="rq-cancel-status" style="display:none"></div>
    <?php if ($isOwner): ?>
    <button type="button" class="rq-btn-full" id="rq-delete-btn" data-request-id="<?= (int) $request['id_kerkese_ndihme'] ?>" style="margin-top:8px;background:rgba(239,68,68,0.08);color:#dc2626;border:1.5px solid rgba(239,68,68,0.3);">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
        Fshi kërkesën
    </button>
    <div class="rq-inline-status" id="rq-delete-status" style="display:none"></div>
<?php endif; ?>
<?php elseif (in_array(($request['statusi'] ?? ''), TS_HELP_REQUEST_TERMINAL_STATUSES, true)): ?>
    <button type="button" class="rq-btn-full" id="rq-reopen-btn" data-request-id="<?= (int) $request['id_kerkese_ndihme'] ?>" style="margin-top:12px;background:rgba(0,113,93,0.08);color:#00715D;border:1.5px solid #00715D;">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
        Rihap postimin
    </button>
    <div class="rq-inline-status" id="rq-reopen-status" style="display:none"></div>
<?php endif; ?>
<p class="rq-sidebar-hint">Më poshtë mund të shihni aplikantët, listën e pritjes dhe kontaktet e tyre.</p>
          <?php elseif ($isLoggedIn): ?>
            <p class="rq-sidebar-hint">
              <?= ($request['statusi'] ?? '') === 'completed'
                ? 'Ky postim është përfunduar dhe nuk pranon më aplikime.'
                : 'Ky postim është anuluar dhe nuk pranon më aplikime.' ?>
            </p>
          <?php endif; ?>
        </div>

        <?php if ($isOwner || $isAdmin): ?>
          <div class="rq-applicants-wrap">
            <h4>Aplikantët (<?= (int) $requestApplicantsTotal ?>)</h4>
            <?php if (empty($requestApplicants)): ?>
              <p class="rq-sidebar-hint">Nuk ka aplikime ende për këtë kërkesë.</p>
            <?php else: ?>
              <div class="rq-applicants-list">
                <?php foreach ($requestApplicants as $index => $applicant): ?>
                  <details class="rq-applicant-item rq-applicant-dropdown <?= $index >= 5 ? 'is-extra' : '' ?>">
                    <summary class="rq-applicant-summary">
                      <div class="rq-applicant-meta">
                        <strong><?= htmlspecialchars($applicant['emri'] ?? 'Volunteer') ?></strong>
                        <span><?= date('d/m/Y H:i', strtotime($applicant['aplikuar_me'])) ?></span>
                        <span class="rq-applicant-status rq-applicant-status--<?= strtolower($applicant['statusi'] ?? 'pending') ?>" data-app-id="<?= (int) $applicant['id_aplikimi_kerkese'] ?>">
                          <?= htmlspecialchars(status_label($applicant['statusi'] ?? 'pending')) ?>
                        </span>
                      </div>
                      <svg class="rq-applicant-chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="rq-applicant-content">
                      <div class="rq-applicant-email"><?= htmlspecialchars($applicant['email'] ?? '—') ?></div>
                      <div class="rq-applicant-actions" data-app-id="<?= (int) $applicant['id_aplikimi_kerkese'] ?>">
                        <?php if (in_array(($applicant['statusi'] ?? 'pending'), ['pending', 'waitlisted'], true) && in_array(($request['statusi'] ?? 'open'), TS_HELP_REQUEST_ACTIVE_STATUSES, true)): ?>
                          <button type="button" class="rq-btn-accept rq-btn-sm" data-action="approved">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            Prano
                          </button>
                          <button type="button" class="rq-btn-reject rq-btn-sm" data-action="rejected">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            Refuzo
                          </button>
                        <?php else: ?>
                          <span class="rq-applicant-decided"><?= htmlspecialchars(status_label($applicant['statusi'] ?? 'pending')) ?></span>
                        <?php endif; ?>
                      </div>
                      <?php if (!in_array(($applicant['statusi'] ?? ''), ['rejected', 'withdrawn'], true)): ?>
                      <form class="rq-contact-form"
                            data-request-id="<?= (int) $request['id_kerkese_ndihme'] ?>"
                            data-applicant-id="<?= (int) $applicant['id_perdoruesi'] ?>">
                        <input type="text" name="subjekti" value="Kontakt për kërkesën: <?= htmlspecialchars($request['titulli']) ?>" maxlength="180" required>
                        <textarea name="mesazhi" rows="3" maxlength="2000" required placeholder="Përshëndetje, jam postuesi i kërkesës. Ja si mund të lidhemi..."></textarea>
                        <button type="submit" class="rq-btn-full rq-btn-sm">
                          Dërgo email aplikantit
                        </button>
                        <div class="rq-inline-status" style="display:none"></div>
                      </form>
                      <?php endif; ?>
                    </div>
                  </details>
                <?php endforeach; ?>
              </div>
              <?php if (count($requestApplicants) > 5): ?>
                <button type="button"
                        class="rq-btn-full rq-btn-sm rq-btn-more-applicants"
                        id="rq-show-more-applicants"
                        data-hidden-count="<?= count($requestApplicants) - 5 ?>">
                  Shfaq më shumë aplikantë (<?= count($requestApplicants) - 5 ?>)
                </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Trust box & Reporting -->
      <div style="display: flex; flex-direction: column; gap: 16px;">
        <div class="rq-sidebar-trust">
          <div class="rq-sidebar-trust__icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
          </div>
          <p><strong>Platformë komunitare</strong></p>
          <p class="rq-sidebar-trust__sub">Kjo platformë mundëson lidhje direkte midis vullnetarëve dhe atyre që kanë nevojë.</p>
        </div>

          <?php if ($currentUserId && !$isOwner): ?>
        <button class="rq-btn-full rq-btn-sm" style="background:#fff;color:#ef4444;border:1.5px solid #fee2e2" onclick="reportHelpRequest(<?= (int) $request['id_kerkese_ndihme'] ?>)">
          <svg style="margin-right:8px;vertical-align:middle" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>
          Raporto këtë kërkesë
        </button>
        <?php endif; ?>
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
  <!-- Warm animated orbs -->
  <div class="rq-orb rq-orb--1"></div>
  <div class="rq-orb rq-orb--2"></div>
  <div class="rq-orb rq-orb--3"></div>
  <div class="rq-orb rq-orb--4"></div>

  <div class="rq-hero__inner">
    <span class="rq-hero__label rq-hero__label--warm">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      Qytetarë për qytetarë
    </span>
    <h1>Kërkesa për <span class="rq-hero__highlight">Ndihmë</span></h1>
    <p class="rq-hero__subtitle">Shiko kush ka nevojë për ndihmë pranë teje dhe ofro dorën tënde. Çdo akt i vogël mirësie ka fuqinë të ndryshojë jetën e dikujt.</p>

    <!-- Trust stats -->
    <div class="rq-trust-bar">
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <div><strong><?= $statVullnetare ?></strong><span>Vullnetarë</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
        <div><strong><?= $statOpen ?></strong><span><span class="rq-live-dot"></span>Të hapura</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
        <div><strong><?= $statCompleted ?></strong><span>Përfunduara</span></div>
      </div>
      <div class="rq-trust-divider"></div>
      <div class="rq-trust-item">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
        <div><strong><?= $statOferta ?></strong><span>Kontribute</span></div>
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
      <select name="statusi" onchange="this.form.submit()">
        <option value="">Të gjitha statuset</option>
        <option value="open" <?= $statusi === 'open' ? 'selected' : '' ?>>Hapur</option>
        <option value="filled" <?= $statusi === 'filled' ? 'selected' : '' ?>>Mbushur</option>
        <option value="completed" <?= $statusi === 'completed' ? 'selected' : '' ?>>Përfunduar</option>
        <option value="cancelled" <?= $statusi === 'cancelled' ? 'selected' : '' ?>>Anuluar</option>
      </select>
      <select name="kategoria" onchange="this.form.submit()">
        <option value="">Të gjitha kategoritë</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= (int) $cat['id_kategoria'] ?>" <?= $kategoria === (string) $cat['id_kategoria'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['emri']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="rq-filters__btn">Kërko</button>
    </div>
  </form>
</section>

<!-- ─── TABS STRIP ─── -->
<div class="rq-tabs-strip rq-tabs-strip--below">
  <div class="rq-tabs-strip__inner">
    <button type="button" class="rq-tab rq-tab--all rq-tab--active" data-filter="all">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
      Të gjitha
      <span class="rq-tab__count"><?= $statTotalKerkesa ?></span>
    </button>
    <button type="button" class="rq-tab rq-tab--request" data-filter="request">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      Kërkoj Ndihmë
      <span class="rq-tab__count"><?= $statKerkesa ?></span>
    </button>
    <button type="button" class="rq-tab rq-tab--offer" data-filter="offer">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      Ofroj Ndihmë
      <span class="rq-tab__count"><?= $statOferta ?></span>
    </button>
  </div>
</div>

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
      <div class="rq-empty rq-empty--filter" id="rq-filter-empty" style="display:none">
        <div class="rq-empty__icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
        </div>
        <h3>Asnjë rezultat për këtë filtrim</h3>
        <p>Provo të zgjedhish një tab tjetër ose hiq filtrat.</p>
      </div>
      <?php foreach ($requests as $i => $req):
        $cardType = $req['tipi'] === 'offer' ? 'offer' : 'request';
        $isFeatured = ($i === 0 && $page <= 1);
        $matching = $listMatchingById[(int) $req['id_kerkese_ndihme']] ?? ts_help_request_matching_details($req);
        $cardCapacitySummary = $matching['has_capacity_limit']
          ? ($matching['progress_count'] . ' / ' . $matching['capacity_total'] . ' përputhje')
          : (($matching['matched_total'] ?? 0) > 0 ? ($matching['matched_total'] . ' përputhje') : 'Kapacitet i hapur');
        $cardQueueSummary = ($matching['counts']['waitlisted'] ?? 0) > 0
          ? ($matching['counts']['waitlisted'] . ' në listë pritjeje')
          : (($matching['counts']['pending'] ?? 0) > 0 ? ($matching['counts']['pending'] . ' në shqyrtim') : (($matching['total_applications'] ?? 0) . ' aplikime'));
      ?>
        <a href="/TiranaSolidare/views/help_requests.php?id=<?= $req['id_kerkese_ndihme'] ?>" class="rq-card rq-card--typed<?= $isFeatured ? ' rq-card--featured' : '' ?>" data-type="<?= $cardType ?>" style="animation-delay: <?= $i * 0.05 ?>s">
          <div class="rq-card__type-bar rq-card__type-bar--<?= $cardType ?>"></div>
          <?php if ($isFeatured): ?>
          <div class="rq-card__featured-label">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Më e reja
          </div>
          <?php endif; ?>
          <div class="rq-card__header">
            <div class="rq-card__badges">
              <span class="rq-badge rq-badge--<?= $cardType ?>"><?= $req['tipi'] === 'request' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?></span>
              <span class="rq-badge rq-badge--<?= $req['statusi'] ?>"><?= status_label($req['statusi']) ?></span>
              <?php if (!empty($req['kategoria_emri'])): ?>
              <span class="rq-badge rq-badge--category"><?= htmlspecialchars($req['kategoria_emri']) ?></span>
              <?php endif; ?>
            </div>
            <div class="rq-card__type-icon rq-card__type-icon--<?= $cardType ?>">
              <?php if ($req['tipi'] === 'request'): ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
              <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14"/><path d="m7 18 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 13 6 6"/></svg>
              <?php endif; ?>
            </div>
          </div>
          <div class="rq-card__content">
            <h3 class="rq-card__title"><?= htmlspecialchars($req['titulli']) ?></h3>
            <p class="rq-card__desc"><?= htmlspecialchars(mb_substr($req['pershkrimi'] ?? '', 0, $isFeatured ? 220 : 110)) ?><?= mb_strlen($req['pershkrimi'] ?? '') > ($isFeatured ? 220 : 110) ? '...' : '' ?></p>
            <?php $cardShowsLocation = ts_can_view_help_request_location($req, $currentUserId !== null ? (int) $currentUserId : null, $isAdmin ? 'admin' : 'volunteer', $requestLocationUnlockedIds); ?>
            <?php if ($isFeatured && $cardShowsLocation && !empty($req['vendndodhja'])): ?>
            <div class="rq-card__location">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
              <?= htmlspecialchars($req['vendndodhja']) ?>
            </div>
            <?php elseif ($isFeatured): ?>
            <div class="rq-card__location" style="color:#64748b;">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
              Vendndodhja e saktë shfaqet pas aplikimit
            </div>
            <?php endif; ?>
            <div class="rq-card__matching-meta">
              <span><?= htmlspecialchars($cardCapacitySummary) ?></span>
              <span><?= htmlspecialchars($cardQueueSummary) ?></span>
            </div>
            <div class="rq-card__footer">
              <div class="rq-card__meta">
                <span class="rq-card__poster js-profile-link" role="link" tabindex="0" data-profile-url="<?= htmlspecialchars(ts_public_profile_url((int) ($req['id_perdoruesi'] ?? 0), (string) ($req['krijuesi_emri'] ?? 'Anonim'))) ?>" aria-label="Hap profilin publik të <?= htmlspecialchars($req['krijuesi_emri'] ?? 'Anonim') ?>">
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
          <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&tipi=<?= urlencode($tipi) ?>&statusi=<?= urlencode($statusi) ?>&kategoria=<?= urlencode($kategoria) ?>" class="rq-pagination__btn rq-pagination__btn--nav">&larr; Para</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tipi=<?= urlencode($tipi) ?>&statusi=<?= urlencode($statusi) ?>&kategoria=<?= urlencode($kategoria) ?>"
             class="rq-pagination__btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&tipi=<?= urlencode($tipi) ?>&statusi=<?= urlencode($statusi) ?>&kategoria=<?= urlencode($kategoria) ?>" class="rq-pagination__btn rq-pagination__btn--nav">Tjetër &rarr;</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<!-- ─── DUAL CTA ─── -->
<section class="rq-dual-cta">
  <div class="rq-dual-cta__inner">
    <div class="rq-dual-cta__card rq-dual-cta__card--request">
      <div class="rq-dual-cta__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      </div>
      <h3>Ke nevojë për ndihmë?</h3>
      <p>Posto një kërkesë dhe komuniteti i Tiranës do të dëgjojë. Është falas dhe i shpejtë.</p>
      <?php if ($isLoggedIn): ?>
        <a href="/TiranaSolidare/views/volunteer_panel.php?tab=new-request" class="rq-dual-cta__btn rq-dual-cta__btn--warm">
          Posto kërkesën
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      <?php else: ?>
        <a href="/TiranaSolidare/views/register.php" class="rq-dual-cta__btn rq-dual-cta__btn--warm">
          Regjistrohu për të postuar
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      <?php endif; ?>
    </div>

    <div class="rq-dual-cta__card rq-dual-cta__card--offer">
      <div class="rq-dual-cta__icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
      </div>
      <h3>Dëshiron të ndihmosh?</h3>
      <p>Ofroi aftesitë ose kohën tënde. Dikush pranë teje mund ta ketë shumë nevojë.</p>
      <?php if ($isLoggedIn): ?>
        <a href="/TiranaSolidare/views/volunteer_panel.php?tab=new-request" class="rq-dual-cta__btn rq-dual-cta__btn--green">
          Ofroj ndihmën time
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      <?php else: ?>
        <a href="/TiranaSolidare/views/register.php" class="rq-dual-cta__btn rq-dual-cta__btn--green">
          Regjistrohu për të ofruar
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php endif; ?>

</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/TiranaSolidare/assets/js/map-component.js?v=20260401a"></script>
<script>
const API = '/TiranaSolidare/api';
const helpRequestStatusLabels = {
  pending: 'Në pritje',
  approved: 'Pranuar',
  rejected: 'Refuzuar',
  waitlisted: 'Në listë pritjeje',
  withdrawn: 'Tërhequr',
  completed: 'Përfunduar',
};
function getCSRF() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }
function refreshCSRF(json) { if (json && json.csrf_token) { const m = document.querySelector('meta[name="csrf-token"]'); if (m) m.content = json.csrf_token; } return json; }
let csrfToken = getCSRF();
(function() {
  const _fetch = window.fetch;
  window.fetch = async function(...args) {
    const res = await _fetch.apply(this, args);
    const m = (args[1]?.method || 'GET').toUpperCase();
    if (['POST','PUT','DELETE'].includes(m)) {
      try { refreshCSRF(await res.clone().json()); csrfToken = getCSRF(); } catch(e) {}
    }
    return res;
  };
})();

function setInlineStatus(el, type, message) {
  if (!el) return;
  el.style.display = 'block';
  el.className = 'rq-inline-status rq-inline-status--' + type;
  el.textContent = message;
}

// Admin moderation action
async function moderateRequest(id, action) {
  const labels = { approve_request: 'miratoni', reject_request: 'refuzoni' };
  if (!confirm('Jeni i sigurt që doni të ' + (labels[action] || action) + ' këtë postim?')) return;
  try {
    const res = await fetch(`${API}/help_requests.php?action=${action}&id=${id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRF() },
      body: JSON.stringify({})
    });
    const json = await res.json();
    refreshCSRF(json);
    alert(json.message || (json.success ? 'U krye.' : 'Gabim.'));
    if (json.success) location.reload();
  } catch (e) {
    alert('Gabim gjatë veprimit. Provoni përsëri.');
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const applyBtn = document.getElementById('rq-apply-btn');
  const applyStatus = document.getElementById('rq-apply-status');

  if (applyBtn) {
    applyBtn.addEventListener('click', async function() {
      const requestId = parseInt(this.dataset.requestId || '0', 10);
      if (!requestId) return;

      this.disabled = true;
      this.textContent = 'Duke dërguar aplikimin...';

      try {
        const res = await fetch(API + '/help_requests.php?action=apply', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          credentials: 'same-origin',
          body: JSON.stringify({ id_kerkese_ndihme: requestId })
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'Gabim gjatë aplikimit.');
        setInlineStatus(applyStatus, 'success', json.data.message || 'Aplikimi u dërgua me sukses.');
        window.setTimeout(() => window.location.reload(), 900);
      } catch (err) {
        setInlineStatus(applyStatus, 'error', err.message || 'Gabim gjatë aplikimit.');
        this.disabled = false;
        const fallbackLabel = this.dataset.defaultLabel || 'Apliko';
        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>' + fallbackLabel;
      }
    });
  }

  document.querySelectorAll('.rq-contact-form').forEach((form) => {
    form.addEventListener('submit', async (e) => {
        setInlineStatus(applyStatus, 'success', (json.data.message || 'Aplikimi u dërgua me sukses.') + ' Po hapim detajet e vendndodhjes...');
      const statusEl = form.querySelector('.rq-inline-status');
      const requestId = parseInt(form.dataset.requestId || '0', 10);
        setTimeout(() => window.location.reload(), 900);
      const applicantId = parseInt(form.dataset.applicantId || '0', 10);
      const subjekti = (form.querySelector('input[name="subjekti"]')?.value || '').trim();
      const mesazhi = (form.querySelector('textarea[name="mesazhi"]')?.value || '').trim();

      if (!requestId || !applicantId) {
        setInlineStatus(statusEl, 'error', 'Kërkesa ose aplikuesi është i pavlefshëm.');
        return;
      }
      if (!mesazhi) {
        setInlineStatus(statusEl, 'error', 'Mesazhi është i detyrueshëm.');
        return;
      }

      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Duke dërguar...';
      }

      try {
        const res = await fetch(API + '/help_requests.php?action=contact_applicant', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          credentials: 'same-origin',
          body: JSON.stringify({
            id_kerkese_ndihme: requestId,
            id_aplikuesi: applicantId,
            subjekti,
            mesazhi
          })
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'Gabim gjatë dërgimit të email-it.');
        setInlineStatus(statusEl, 'success', json.data.message || 'Email-i u dërgua me sukses.');
      } catch (err) {
        setInlineStatus(statusEl, 'error', err.message || 'Gabim gjatë dërgimit të email-it.');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Dërgo email aplikantit';
        }
      }
    });
  });

  const showMoreApplicantsBtn = document.getElementById('rq-show-more-applicants');
  if (showMoreApplicantsBtn) {
    showMoreApplicantsBtn.addEventListener('click', () => {
      document.querySelectorAll('.rq-applicant-item.is-extra').forEach((item) => {
        item.classList.remove('is-extra');
      });
      showMoreApplicantsBtn.remove();
    });
  }

  // Accept / Reject applicant buttons
  document.querySelectorAll('.rq-btn-accept, .rq-btn-reject').forEach((btn) => {
    btn.addEventListener('click', async function() {
      const actionsWrap = this.closest('.rq-applicant-actions');
      const appId = parseInt(actionsWrap?.dataset.appId || '0', 10);
      const newStatus = this.dataset.action;
      if (!appId || !newStatus) return;

      this.disabled = true;
      const siblingBtn = actionsWrap.querySelector(this.classList.contains('rq-btn-accept') ? '.rq-btn-reject' : '.rq-btn-accept');
      if (siblingBtn) siblingBtn.disabled = true;

      try {
        const res = await fetch(API + '/help_requests.php?action=update_applicant_status', {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          credentials: 'same-origin',
          body: JSON.stringify({ id_aplikimi_kerkese: appId, statusi: newStatus })
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
        const label = helpRequestStatusLabels[newStatus] || newStatus;
        actionsWrap.innerHTML = '<span class="rq-applicant-decided">' + label + '</span>';
        const statusBadge = document.querySelector('.rq-applicant-status[data-app-id="' + appId + '"]');
        if (statusBadge) {
          statusBadge.textContent = label;
          statusBadge.className = 'rq-applicant-status rq-applicant-status--' + newStatus.toLowerCase();
        }
      } catch (err) {
        alert(err.message);
        this.disabled = false;
        if (siblingBtn) siblingBtn.disabled = false;
      }
    });
  });

  const mapEl = document.getElementById('request-detail-map');
  if (mapEl) {
    TSMap.display('request-detail-map', {
      lat: <?= json_encode($canViewRequestLocation ? ($request['latitude'] ?? null) : null) ?>,
      lng: <?= json_encode($canViewRequestLocation ? ($request['longitude'] ?? null) : null) ?>,
      label: <?= json_encode($request['titulli'] ?? '') ?>,
      type: <?= json_encode(($request['tipi'] ?? '') === 'offer' ? 'offer' : 'request') ?>
    });
  }

  // ─── Tab filtering (client-side, no page refresh) ───
  // Clickable poster names inside full-card links.
  document.querySelectorAll('.js-profile-link[data-profile-url]').forEach((el) => {
    const openProfile = (event) => {
      event.preventDefault();
      event.stopPropagation();
      const profileUrl = el.getAttribute('data-profile-url') || '';
      if (profileUrl) {
        window.location.href = profileUrl;
      }
    };

    el.addEventListener('click', openProfile);
    el.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        openProfile(event);
      }
    });
  });

  const tabBtns = document.querySelectorAll('.rq-tabs-strip [data-filter]');
  const cards   = document.querySelectorAll('.rq-grid .rq-card[data-type]');
  const countEl = document.querySelector('.rq-results__count');
  const filterEmpty = document.getElementById('rq-filter-empty');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const filter = btn.dataset.filter;

      // Update active tab
      tabBtns.forEach(b => b.classList.remove('rq-tab--active'));
      btn.classList.add('rq-tab--active');

      // Filter cards with animation
      let visible = 0;
      cards.forEach(card => {
        const match = filter === 'all' || card.dataset.type === filter;
        if (match) {
          card.style.display = '';
          card.style.animation = 'rqCardIn 0.35s ease forwards';
          card.style.animationDelay = (visible * 0.04) + 's';
          visible++;
        } else {
          card.style.animation = 'rqCardOut 0.25s ease forwards';
          setTimeout(() => { card.style.display = 'none'; }, 250);
        }
      });

      // Show/hide empty state
      if (filterEmpty) {
        filterEmpty.style.display = visible === 0 ? '' : 'none';
      }

      // Update visible count text
      if (countEl) {
        const totalCards = cards.length;
        countEl.textContent = (filter === 'all' ? totalCards : visible) + ' kërkesa u gjetën';
      }

      // Sync the <select> for tipi so form still works if they search
      const tipiSelect = document.querySelector('.rq-filters select[name="tipi"]');
      if (tipiSelect) {
        if (filter === 'request') tipiSelect.value = 'request';
        else if (filter === 'offer') tipiSelect.value = 'offer';
        else tipiSelect.value = '';
      }

      // Smooth scroll to results
      const results = document.querySelector('.rq-results');
      if (results) {
        results.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
});

const completeBtn = document.getElementById('rq-complete-btn');
if (completeBtn) {
  completeBtn.addEventListener('click', async function() {
    if (!confirm('Jeni të sigurt që dëshironi ta shënoni këtë postim si të përfunduar?')) return;
        const requestId = parseInt(this.dataset.requestId || '0', 10);
        this.disabled = true;
    this.textContent = 'Duke përfunduar...';
        try {
      const res = await fetch(API + '/help_requests.php?action=complete&id=' + requestId, {
                method: 'PUT',
                headers: { 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      setInlineStatus(document.getElementById('rq-complete-status'), 'success', json.data.message || 'Postimi u shënua si i përfunduar.');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
      setInlineStatus(document.getElementById('rq-complete-status'), 'error', err.message);
            this.disabled = false;
      this.textContent = 'Shëno si të përfunduar';
        }
    });
}

const cancelBtn = document.getElementById('rq-cancel-btn');
if (cancelBtn) {
  cancelBtn.addEventListener('click', async function() {
    if (!confirm('Jeni të sigurt që dëshironi ta anuloni këtë postim?')) return;
    const requestId = parseInt(this.dataset.requestId || '0', 10);
    this.disabled = true;
    this.textContent = 'Duke anuluar...';
    try {
      const res = await fetch(API + '/help_requests.php?action=cancel&id=' + requestId, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({})
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      setInlineStatus(document.getElementById('rq-cancel-status'), 'success', json.data.message || 'Postimi u anulua.');
      setTimeout(() => window.location.reload(), 900);
    } catch (err) {
      setInlineStatus(document.getElementById('rq-cancel-status'), 'error', err.message);
      this.disabled = false;
      this.textContent = 'Anulo postimin';
    }
  });
}

const reopenBtn = document.getElementById('rq-reopen-btn');
if (reopenBtn) {
    reopenBtn.addEventListener('click', async function() {
    if (!confirm('Jeni të sigurt që dëshironi ta rihapni këtë postim?')) return;
        const requestId = parseInt(this.dataset.requestId || '0', 10);
        this.disabled = true;
        this.textContent = 'Duke rihapur...';
        try {
            const res = await fetch(API + '/help_requests.php?action=reopen&id=' + requestId, {
                method: 'PUT',
                headers: { 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
            setInlineStatus(document.getElementById('rq-reopen-status'), 'success', 'Kërkesa u rihap me sukses.');
            setTimeout(() => window.location.reload(), 900);
        } catch (err) {
            setInlineStatus(document.getElementById('rq-reopen-status'), 'error', err.message);
            this.disabled = false;
            this.textContent = 'Rihap kërkesën';
        }
    });
}

const deleteBtn = document.getElementById('rq-delete-btn');
if (deleteBtn) {
    deleteBtn.addEventListener('click', async function() {
        const requestId = parseInt(this.dataset.requestId || '0', 10);
        
        <?php 
        $hasAccepted = false;
        foreach ($requestApplicants as $app) {
      if (in_array(($app['statusi'] ?? ''), ['approved', 'completed'], true)) { $hasAccepted = true; break; }
        }
        ?>
        const hasAccepted = <?= $hasAccepted ? 'true' : 'false' ?>;
        
        if (hasAccepted) {
            if (!confirm('Kjo kërkesë ka aplikime të pranuara. Jeni të sigurt që dëshironi ta fshini?')) return;
        } else {
            if (!confirm('Jeni të sigurt që dëshironi ta fshini këtë kërkesë?')) return;
        }
        
        this.disabled = true;
        this.textContent = 'Duke fshirë...';
        try {
            const res = await fetch(API + '/help_requests.php?action=delete&id=' + requestId, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
            window.location.href = '/TiranaSolidare/views/help_requests.php';
        } catch (err) {
            setInlineStatus(document.getElementById('rq-delete-status'), 'error', err.message);
            this.disabled = false;
            this.textContent = 'Fshi kërkesën';
        }
    });
}

  const withdrawBtn = document.getElementById('rq-withdraw-btn');
  if (withdrawBtn) {
    withdrawBtn.addEventListener('click', async function() {
      if (!confirm('Jeni të sigurt që dëshironi ta tërhiqni aplikimin tuaj?')) return;
      const appId = parseInt(this.dataset.appId || '0', 10);
      this.disabled = true;
      this.textContent = 'Duke tërhequr...';
      try {
        const res = await fetch(API + '/help_requests.php?action=withdraw_application&id=' + appId, {
          method: 'DELETE',
          headers: { 'X-CSRF-Token': csrfToken },
          credentials: 'same-origin'
        });
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
        setInlineStatus(document.getElementById('rq-withdraw-status'), 'success', json.data.message || 'Aplikimi u tërhoq.');
        setTimeout(() => window.location.reload(), 900);
      } catch (err) {
        setInlineStatus(document.getElementById('rq-withdraw-status'), 'error', err.message);
        this.disabled = false;
        this.textContent = 'Tërhiq aplikimin';
      }
    });
  }

// ─── Animated count-up for trust bar numbers ───
(function() {
  const trustItems = document.querySelectorAll('.rq-trust-item strong');
  if (!trustItems.length) return;
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const target = parseInt(el.textContent, 10);
      if (isNaN(target) || el.dataset.counted) return;
      el.dataset.counted = '1';
      const duration = 1200;
      const start = performance.now();
      function step(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.round(target * eased);
        if (progress < 1) requestAnimationFrame(step);
      }
      el.textContent = '0';
      requestAnimationFrame(step);
      observer.unobserve(el);
    });
  }, { threshold: 0.3 });
  trustItems.forEach(el => observer.observe(el));
})();

function reportHelpRequest(requestId) {
    const existing = document.getElementById('rq-report-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'rq-report-modal';
    modal.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);';
    modal.innerHTML = `
        <div style="background:#fff;border-radius:16px;padding:28px;max-width:460px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
            <h3 style="margin:0 0 8px;color:#dc2626;font-size:1.1rem;">Raporto këtë kërkesë</h3>
            <p style="margin:0 0 16px;font-size:0.87rem;color:#6b7280;">Zgjidh arsyen e raportimit. Admini do ta shqyrtojë.</p>
            
            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:14px;">
                ${[
                    'Përmbajtje e rreme ose mashtruese',
                    'Përmbajtje e papërshtatshme',
                    'Postim i dubluar',
                    'Spam',
                    'Tjetër'
                ].map(reason => `
                    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid #e4e8ee;border-radius:10px;cursor:pointer;font-size:0.88rem;color:#374151;transition:border-color .15s;"
                           onmouseover="this.style.borderColor='#ef4444'" onmouseout="this.style.borderColor=this.querySelector('input').checked?'#ef4444':'#e4e8ee'">
                        <input type="radio" name="rq-report-reason" value="${reason}" style="accent-color:#ef4444;">
                        ${reason}
                    </label>`).join('')}
            </div>

            <textarea id="rq-report-custom" maxlength="300" rows="3" placeholder="Shpjego shkurtimisht (opsionale)..."
                style="width:100%;padding:10px 12px;border:1.5px solid #e4e8ee;border-radius:10px;font-size:0.88rem;resize:none;box-sizing:border-box;outline:none;display:none;"></textarea>

            <div id="rq-report-status" style="min-height:1.2em;font-size:0.85rem;margin:8px 0;"></div>
            <div style="display:flex;gap:10px;margin-top:16px;">
                <button id="rq-report-confirm" style="flex:1;padding:10px;background:#dc2626;color:#fff;border:none;border-radius:10px;cursor:pointer;font-weight:700;font-size:0.9rem;">Dërgo raportin</button>
                <button id="rq-report-cancel" style="flex:1;padding:10px;background:#f3f4f6;color:#374151;border:1px solid #e4e8ee;border-radius:10px;cursor:pointer;font-weight:600;font-size:0.9rem;">Anulo</button>
            </div>
        </div>`;

    document.body.appendChild(modal);

    const customTextarea = modal.querySelector('#rq-report-custom');
    const statusEl = modal.querySelector('#rq-report-status');
    const confirmBtn = modal.querySelector('#rq-report-confirm');
    const cancelBtn = modal.querySelector('#rq-report-cancel');

    // Shfaq textarea vetëm kur zgjedh "Tjetër"
    modal.querySelectorAll('input[name="rq-report-reason"]').forEach(radio => {
        radio.addEventListener('change', () => {
            customTextarea.style.display = radio.value === 'Tjetër' ? 'block' : 'none';
            // Ngjyros borderin e zgjedhur
            modal.querySelectorAll('label').forEach(l => {
                l.style.borderColor = l.querySelector('input')?.checked ? '#ef4444' : '#e4e8ee';
            });
        });
    });

    cancelBtn.addEventListener('click', () => modal.remove());
    modal.addEventListener('click', (e) => { if (e.target === modal) modal.remove(); });

    confirmBtn.addEventListener('click', async () => {
        const selected = modal.querySelector('input[name="rq-report-reason"]:checked');
        if (!selected) {
            statusEl.style.color = '#dc2626';
            statusEl.textContent = 'Ju lutem zgjidhni një arsye.';
            return;
        }

        const arsye = selected.value === 'Tjetër'
            ? (customTextarea.value.trim() || 'Tjetër')
            : selected.value;

        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Duke dërguar...';
        statusEl.textContent = '';

        try {
            const res = await fetch(API + '/help_requests.php?action=flag&id=' + requestId, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin',
                body: JSON.stringify({ arsye })
            });
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.message || 'Gabim gjatë raportimit.');
            statusEl.style.color = '#16a34a';
            statusEl.textContent = 'Raporti u dërgua. Faleminderit!';
            setTimeout(() => modal.remove(), 1500);
        } catch (err) {
            statusEl.style.color = '#dc2626';
            statusEl.textContent = err.message;
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Dërgo raportin';
        }
    });
}

</script>
<script src="/TiranaSolidare/public/assets/scripts/main.js?v=20260401a"></script>
</body>
</html>
