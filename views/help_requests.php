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
      && (($request['statusi'] ?? '') === 'Open');

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
      }

      if ($isOwner || $isAdmin) {
        $appCountStmt = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi_Kerkese WHERE id_kerkese_ndihme = ?');
        $appCountStmt->execute([(int) $request['id_kerkese_ndihme']]);
        $requestApplicantsTotal = (int) $appCountStmt->fetchColumn();

        $applicantsStmt = $pdo->prepare(
          'SELECT ak.id_aplikimi_kerkese, ak.statusi, ak.aplikuar_me,
              p.id_perdoruesi, p.emri, p.email
           FROM Aplikimi_Kerkese ak
           JOIN Perdoruesi p ON p.id_perdoruesi = ak.id_perdoruesi
           WHERE ak.id_kerkese_ndihme = ?
           ORDER BY ak.aplikuar_me DESC'
        );
        $applicantsStmt->execute([(int) $request['id_kerkese_ndihme']]);
        $requestApplicants = $applicantsStmt->fetchAll(PDO::FETCH_ASSOC);
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
$statOpen         = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'Open'")->fetchColumn();
$statClosed       = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE statusi = 'Closed'")->fetchColumn();
$statVullnetare   = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'Vullnetar'")->fetchColumn();
$statOferta       = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'Ofertë'")->fetchColumn();
$statKerkesa      = (int) $pdo->query("SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE tipi = 'Kërkesë'")->fetchColumn();} // end if (!isset($_GET['id']))
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($request) ? htmlspecialchars($request['titulli']) . ' — ' : '' ?>Kërkesat — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260318a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/requests.css?v=20260320b">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/TiranaSolidare/assets/css/map.css">
  <?= csrf_meta() ?>
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
        <?= $request['tipi'] === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?>
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
      <div class="rq-detail-banner">
        <?php
          $detailImgSrc = '/TiranaSolidare/public/assets/images/default-request.svg';
          if (!empty($request['imazhi'])) {
              $detailImgSrc = strpos($request['imazhi'], '/') === 0 ? $request['imazhi'] : '/TiranaSolidare/public/assets/uploads/' . $request['imazhi'];
          }
        ?>
        <img src="<?= htmlspecialchars($detailImgSrc) ?>" alt="<?= htmlspecialchars($request['titulli']) ?>" onerror="this.src='/TiranaSolidare/public/assets/images/default-request.svg'">
      </div>

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
            <div><span>Tipi</span><strong><?= $request['tipi'] === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?></strong></div>
          </li>
          <li>
            <div class="rq-info-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
            </div>
            <div><span>Statusi</span><strong><?= htmlspecialchars($request['statusi']) ?></strong></div>
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
              Kyçu për të aplikuar
            </a>
            <p class="rq-sidebar-hint">Duhet të jeni i kyçur për të aplikuar. <a href="/TiranaSolidare/views/login.php?redirect=<?= urlencode('/TiranaSolidare/views/help_requests.php?id=' . $request['id_kerkese_ndihme']) ?>" class="rq-hint-link">Kyçu këtu &rarr;</a></p>
          <?php elseif ($canApplyToRequest && !$myRequestApplication): ?>
            <button type="button" class="rq-btn-full" id="rq-apply-btn" data-request-id="<?= (int) $request['id_kerkese_ndihme'] ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
              Apliko për këtë kërkesë
            </button>
            <p class="rq-sidebar-hint">Postuesi do të njoftohet menjëherë dhe do të mund t'ju kontaktojë me email.</p>
            <div class="rq-inline-status" id="rq-apply-status" style="display:none"></div>
          <?php elseif ($myRequestApplication): ?>
            <div class="rq-applied-box">
              <strong>Ju keni aplikuar për këtë kërkesë.</strong>
              <span>Statusi: <?= htmlspecialchars($myRequestApplication['statusi'] ?? 'Në pritje') ?></span>
              <span>Aplikuar: <?= date('d/m/Y H:i', strtotime($myRequestApplication['aplikuar_me'] ?? 'now')) ?></span>
            </div>
            <p class="rq-sidebar-hint">Këtë kërkesë e gjeni edhe te paneli juaj në seksionin “Aplikimet e mia”.</p>
          <?php elseif ($isOwner || $isAdmin): ?>
            <p class="rq-sidebar-hint">Më poshtë mund të shihni të gjithë aplikantët dhe t'i kontaktoni individualisht.</p>
          <?php elseif ($isLoggedIn): ?>
            <p class="rq-sidebar-hint">Kjo kërkesë nuk është e disponueshme për aplikim.</p>
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
                        <strong><?= htmlspecialchars($applicant['emri'] ?? 'Vullnetar') ?></strong>
                        <span><?= date('d/m/Y H:i', strtotime($applicant['aplikuar_me'])) ?></span>
                        <span class="rq-applicant-status rq-applicant-status--<?= strtolower($applicant['statusi'] ?? 'në-pritje') ?>" data-app-id="<?= (int) $applicant['id_aplikimi_kerkese'] ?>">
                          <?= htmlspecialchars($applicant['statusi'] ?? 'Në pritje') ?>
                        </span>
                      </div>
                      <svg class="rq-applicant-chevron" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="rq-applicant-content">
                      <div class="rq-applicant-email"><?= htmlspecialchars($applicant['email'] ?? '—') ?></div>
                      <div class="rq-applicant-actions" data-app-id="<?= (int) $applicant['id_aplikimi_kerkese'] ?>">
                        <?php if (($applicant['statusi'] ?? 'Në pritje') === 'Në pritje'): ?>
                          <button type="button" class="rq-btn-accept rq-btn-sm" data-action="Pranuar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                            Prano
                          </button>
                          <button type="button" class="rq-btn-reject rq-btn-sm" data-action="Refuzuar">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            Refuzo
                          </button>
                        <?php else: ?>
                          <span class="rq-applicant-decided"><?= $applicant['statusi'] === 'Pranuar' ? 'I pranuar' : 'I refuzuar' ?></span>
                        <?php endif; ?>
                      </div>
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
  <!-- Warm animated orbs -->
  <div class="rq-orb rq-orb--1"></div>
  <div class="rq-orb rq-orb--2"></div>
  <div class="rq-orb rq-orb--3"></div>
  <div class="rq-orb rq-orb--4"></div>
  <img class="rq-hero__illustration" src="/TiranaSolidare/public/assets/images/hero-message.svg" alt="" aria-hidden="true">

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
        <div><strong><?= $statClosed ?></strong><span>Plotësuara</span></div>
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
        <option value="Open" <?= $statusi === 'Open' ? 'selected' : '' ?>>Open</option>
        <option value="Closed" <?= $statusi === 'Closed' ? 'selected' : '' ?>>Closed</option>
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
      <?php foreach ($requests as $i => $req): ?>
        <a href="/TiranaSolidare/views/help_requests.php?id=<?= $req['id_kerkese_ndihme'] ?>" class="rq-card rq-card--typed" data-type="<?= $req['tipi'] === 'Ofertë' ? 'offer' : 'request' ?>" style="animation-delay: <?= $i * 0.05 ?>s">
          <div class="rq-card__type-bar rq-card__type-bar--<?= $req['tipi'] === 'Ofertë' ? 'offer' : 'request' ?>"></div>
          <div class="rq-card__visual">
            <?php
              $cardImgSrc = '/TiranaSolidare/public/assets/images/default-request.svg';
              if (!empty($req['imazhi'])) {
                  $cardImgSrc = strpos($req['imazhi'], '/') === 0 ? $req['imazhi'] : '/TiranaSolidare/public/assets/uploads/' . $req['imazhi'];
              }
            ?>
            <img src="<?= htmlspecialchars($cardImgSrc) ?>" alt="<?= htmlspecialchars($req['titulli']) ?>" class="rq-card__img" onerror="this.src='/TiranaSolidare/public/assets/images/default-request.svg'">
            <div class="rq-card__overlay">
              <span class="rq-badge rq-badge--<?= $req['tipi'] === 'Ofertë' ? 'offer' : 'request' ?>"><?= $req['tipi'] === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?></span>
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
<script src="/TiranaSolidare/assets/js/map-component.js"></script>
<script>
const API = '/TiranaSolidare/api';
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

function setInlineStatus(el, type, message) {
  if (!el) return;
  el.style.display = 'block';
  el.className = 'rq-inline-status rq-inline-status--' + type;
  el.textContent = message;
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
        this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>Apliko për këtë kërkesë';
      }
    });
  }

  document.querySelectorAll('.rq-contact-form').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const statusEl = form.querySelector('.rq-inline-status');
      const requestId = parseInt(form.dataset.requestId || '0', 10);
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
        const label = newStatus === 'Pranuar' ? 'I pranuar' : 'I refuzuar';
        actionsWrap.innerHTML = '<span class="rq-applicant-decided">' + label + '</span>';
        const statusBadge = document.querySelector('.rq-applicant-status[data-app-id="' + appId + '"]');
        if (statusBadge) {
          statusBadge.textContent = newStatus;
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
      lat: <?= json_encode($request['latitude'] ?? null) ?>,
      lng: <?= json_encode($request['longitude'] ?? null) ?>,
      label: <?= json_encode($request['titulli'] ?? '') ?>,
      type: <?= json_encode(($request['tipi'] ?? '') === 'Ofertë' ? 'offer' : 'request') ?>
    });
  }

  // ─── Tab filtering (client-side, no page refresh) ───
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
        if (filter === 'request') tipiSelect.value = 'Kërkesë';
        else if (filter === 'offer') tipiSelect.value = 'Ofertë';
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
</script>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
