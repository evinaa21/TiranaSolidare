<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isLoggedIn = isset($_SESSION['user_id']);

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

// ── List view: paginated & filterable ──
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
        ORDER BY k.krijuar_me DESC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper: time-ago in Albanian
function koheParapake(string $datetime): string {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->y > 0)  return $diff->y . ' vit më parë';
    if ($diff->m > 0)  return $diff->m . ' muaj më parë';
    if ($diff->d > 0)  return $diff->d . ' ditë më parë';
    if ($diff->h > 0)  return $diff->h . ' orë më parë';
    if ($diff->i > 0)  return $diff->i . ' min më parë';
    return 'tani';
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($request) ? htmlspecialchars($request['titulli']) . ' — ' : '' ?>Kërkesat — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<?php if (isset($request) && $request): ?>
<!-- ═══════════════════════════════════════════════
     SINGLE HELP REQUEST DETAIL
     ═══════════════════════════════════════════════ -->
<section class="page-hero <?= $request['tipi'] === 'Ofertë' ? 'page-hero--green' : 'page-hero--warm' ?>">
  <div class="page-hero__inner">
    <a href="/TiranaSolidare/views/help_requests.php" class="page-back-link">&larr; Kthehu te kërkesat</a>
    <div class="page-hero__badges">
      <span class="page-badge page-badge--<?= $request['tipi'] === 'Ofertë' ? 'offer' : 'request' ?>"><?= htmlspecialchars($request['tipi']) ?></span>
      <span class="page-badge page-badge--status page-badge--<?= strtolower($request['statusi']) ?>"><?= htmlspecialchars($request['statusi']) ?></span>
    </div>
    <h1><?= htmlspecialchars($request['titulli']) ?></h1>
    <div class="page-meta">
      <span><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= htmlspecialchars($request['krijuesi_emri'] ?? 'Anonim') ?></span>
      <span><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?= koheParapake($request['krijuar_me']) ?></span>
    </div>
  </div>
</section>

<section class="page-content">
  <div class="page-content__body">
    <?php if (!empty($request['imazhi'])): ?>
      <img src="<?= htmlspecialchars($request['imazhi']) ?>" alt="<?= htmlspecialchars($request['titulli']) ?>" class="page-content__banner">
    <?php endif; ?>

    <div class="page-content__text">
      <h2>Përshkrimi</h2>
      <p><?= nl2br(htmlspecialchars($request['pershkrimi'] ?? 'Nuk ka përshkrim.')) ?></p>
    </div>

    <div class="page-content__info-grid">
      <div class="info-card">
        <h4>Postuar nga</h4>
        <p><?= htmlspecialchars($request['krijuesi_emri'] ?? 'N/A') ?></p>
      </div>
      <div class="info-card">
        <h4>Tipi</h4>
        <p><?= htmlspecialchars($request['tipi']) ?></p>
      </div>
      <div class="info-card">
        <h4>Statusi</h4>
        <p><?= htmlspecialchars($request['statusi']) ?></p>
      </div>
      <div class="info-card">
        <h4>Krijuar</h4>
        <p><?= date('d/m/Y — H:i', strtotime($request['krijuar_me'])) ?></p>
      </div>
    </div>

    <?php if ($isLoggedIn && !empty($request['krijuesi_email'])): ?>
    <div class="page-content__actions">
      <a href="mailto:<?= htmlspecialchars($request['krijuesi_email']) ?>" class="btn_primary">Kontakto përmes email</a>
    </div>
    <?php elseif (!$isLoggedIn): ?>
    <div class="page-content__actions">
      <a href="/TiranaSolidare/views/login.php" class="btn_primary">Kyçu për të kontaktuar</a>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php elseif (isset($_GET['id']) && !$request): ?>
<!-- Request not found -->
<section class="page-hero">
  <div class="page-hero__inner">
    <h1>Kërkesa nuk u gjet</h1>
    <p>Kjo faqe nuk ekziston ose kërkesa është fshirë.</p>
    <a href="/TiranaSolidare/views/help_requests.php" class="btn_primary">Kthehu te kërkesat</a>
  </div>
</section>

<?php else: ?>
<!-- ═══════════════════════════════════════════════
     HELP REQUESTS LIST / BROWSE
     ═══════════════════════════════════════════════ -->
<section class="page-hero page-hero--warm">
  <div class="page-hero__inner">
    <h1>Kërkesat për Ndihmë</h1>
    <p>Shiko kërkesat dhe ofertat e komunitetit — gjej ku mund të ndihmosh ose merr ndihmë.</p>
  </div>
</section>

<section class="page-content">
  <!-- Filters -->
  <form class="page-filters" method="GET" action="">
    <div class="page-filters__search">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Kërko kërkesa...">
      <button type="submit" class="btn_primary">Kërko</button>
    </div>
    <div class="page-filters__options">
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
    </div>
  </form>

  <!-- Results -->
  <?php if (empty($requests)): ?>
    <div class="page-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>
      <p>Nuk u gjetën kërkesa<?= $search ? ' për "' . htmlspecialchars($search) . '"' : '' ?>.</p>
    </div>
  <?php else: ?>
    <p class="page-results-count"><?= $total ?> kërkesa u gjetën</p>
    <div class="page-grid">
      <?php foreach ($requests as $req): ?>
        <a href="/TiranaSolidare/views/help_requests.php?id=<?= $req['id_kerkese_ndihme'] ?>" class="page-card page-card--request">
          <?php if (!empty($req['imazhi'])): ?>
            <img src="<?= htmlspecialchars($req['imazhi']) ?>" alt="<?= htmlspecialchars($req['titulli']) ?>" class="page-card__img">
          <?php else: ?>
            <div class="page-card__img page-card__img--placeholder page-card__img--placeholder-warm">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
            </div>
          <?php endif; ?>
          <div class="page-card__header page-card__header--overlay">
            <span class="page-card__badge page-card__badge--<?= $req['tipi'] === 'Ofertë' ? 'offer' : 'request' ?>">
              <?= htmlspecialchars($req['tipi']) ?>
            </span>
            <span class="page-card__badge page-card__badge--<?= strtolower($req['statusi']) ?>">
              <?= htmlspecialchars($req['statusi']) ?>
            </span>
          </div>
          <div class="page-card__body">
            <h3 class="page-card__title"><?= htmlspecialchars($req['titulli']) ?></h3>
            <p class="page-card__desc"><?= htmlspecialchars(mb_substr($req['pershkrimi'] ?? '', 0, 120)) ?></p>
            <div class="page-card__meta">
              <span><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> <?= htmlspecialchars($req['krijuesi_emri'] ?? 'Anonim') ?></span>
              <span><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?= koheParapake($req['krijuar_me']) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <nav class="page-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&tipi=<?= urlencode($tipi) ?>&statusi=<?= urlencode($statusi) ?>"
             class="page-pagination__btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php endif; ?>

</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
