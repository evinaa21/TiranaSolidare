<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$isLoggedIn = isset($_SESSION['user_id']);

// ── Single event detail view ──
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $stmt = $pdo->prepare(
        "SELECT e.*, k.emri AS kategoria_emri, p.emri AS krijuesi_emri,
                (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi) AS total_aplikime
         FROM Eventi e
         LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
         LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
         WHERE e.id_eventi = ?"
    );
    $stmt->execute([$id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if current user already applied
    $alreadyApplied = false;
    if ($isLoggedIn && $event) {
        $chk = $pdo->prepare("SELECT id_aplikimi, statusi FROM Aplikimi WHERE id_perdoruesi = ? AND id_eventi = ?");
        $chk->execute([$_SESSION['user_id'], $id]);
        $existingApp = $chk->fetch(PDO::FETCH_ASSOC);
        $alreadyApplied = $existingApp ? true : false;
    }
}

// ── List view: paginated & filterable ──
$page   = max(1, (int) ($_GET['page'] ?? 1));
$limit  = 12;
$offset = ($page - 1) * $limit;
$search   = trim($_GET['search'] ?? '');
$category = (int) ($_GET['category'] ?? 0);

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT * FROM Kategoria ORDER BY emri")->fetchAll(PDO::FETCH_ASSOC);

$where  = [];
$params = [];
if ($search !== '') {
    $where[]  = '(e.titulli LIKE ? OR e.pershkrimi LIKE ? OR e.vendndodhja LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category > 0) {
    $where[]  = 'e.id_kategoria = ?';
    $params[] = $category;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM Eventi e $whereSQL");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();
$totalPages = (int) ceil($total / $limit);

$sql = "SELECT e.*, k.emri AS kategoria_emri
        FROM Eventi e
        LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
        $whereSQL
        ORDER BY e.data DESC
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
  <title><?= isset($event) ? htmlspecialchars($event['titulli']) . ' — ' : '' ?>Evente — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<?php if (isset($event) && $event): ?>
<!-- ═══════════════════════════════════════════════
     SINGLE EVENT DETAIL
     ═══════════════════════════════════════════════ -->
<section class="page-hero page-hero--green">
  <div class="page-hero__inner">
    <a href="/TiranaSolidare/views/events.php" class="page-back-link">&larr; Kthehu te eventet</a>
    <span class="page-badge"><?= htmlspecialchars($event['kategoria_emri'] ?? 'Event') ?></span>
    <h1><?= htmlspecialchars($event['titulli']) ?></h1>
    <div class="page-meta">
      <span><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg> <?= date('d M Y, H:i', strtotime($event['data'])) ?></span>
      <span><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg> <?= htmlspecialchars($event['vendndodhja'] ?? 'Tiranë') ?></span>
      <span><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> <?= $event['total_aplikime'] ?> aplikime</span>
    </div>
  </div>
</section>

<section class="page-content">
  <div class="page-content__body">
    <?php if (!empty($event['banner'])): ?>
      <img src="<?= htmlspecialchars($event['banner']) ?>" alt="<?= htmlspecialchars($event['titulli']) ?>" class="page-content__banner">
    <?php endif; ?>
    
    <div class="page-content__text">
      <h2>Përshkrimi</h2>
      <p><?= nl2br(htmlspecialchars($event['pershkrimi'] ?? 'Nuk ka përshkrim.')) ?></p>
    </div>

    <div class="page-content__info-grid">
      <div class="info-card">
        <h4>Organizuesi</h4>
        <p><?= htmlspecialchars($event['krijuesi_emri'] ?? 'N/A') ?></p>
      </div>
      <div class="info-card">
        <h4>Kategoria</h4>
        <p><?= htmlspecialchars($event['kategoria_emri'] ?? 'Pa kategori') ?></p>
      </div>
      <div class="info-card">
        <h4>Data & Ora</h4>
        <p><?= date('d/m/Y — H:i', strtotime($event['data'])) ?></p>
      </div>
      <div class="info-card">
        <h4>Vendndodhja</h4>
        <p><?= htmlspecialchars($event['vendndodhja'] ?? 'Tiranë') ?></p>
      </div>
    </div>

    <div class="page-content__actions">
      <?php if (!$isLoggedIn): ?>
        <a href="/TiranaSolidare/views/login.php" class="btn_primary">Kyçu për të aplikuar</a>
      <?php elseif ($alreadyApplied): ?>
        <span class="page-badge page-badge--status"><?= htmlspecialchars($existingApp['statusi']) ?></span>
        <p class="text-muted">Ju keni aplikuar tashmë për këtë event.</p>
      <?php else: ?>
        <button class="btn_primary" id="apply-btn" data-event="<?= $event['id_eventi'] ?>">Apliko si Vullnetar</button>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php elseif (isset($_GET['id']) && !$event): ?>
<!-- Event not found -->
<section class="page-hero">
  <div class="page-hero__inner">
    <h1>Eventi nuk u gjet</h1>
    <p>Kjo faqe nuk ekziston ose eventi është fshirë.</p>
    <a href="/TiranaSolidare/views/events.php" class="btn_primary">Kthehu te eventet</a>
  </div>
</section>

<?php else: ?>
<!-- ═══════════════════════════════════════════════
     EVENTS LIST / BROWSE
     ═══════════════════════════════════════════════ -->
<section class="page-hero page-hero--green">
  <div class="page-hero__inner">
    <h1>Eventet</h1>
    <p>Zbulo mundësitë e vullnetarizmit dhe kontribuo në komunitetin tënd.</p>
  </div>
</section>

<section class="page-content">
  <!-- Filters -->
  <form class="page-filters" method="GET" action="">
    <div class="page-filters__search">
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Kërko evente...">
      <button type="submit" class="btn_primary">Kërko</button>
    </div>
    <div class="page-filters__options">
      <select name="category" onchange="this.form.submit()">
        <option value="0">Të gjitha kategoritë</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id_kategoria'] ?>" <?= $category === (int)$cat['id_kategoria'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['emri']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <!-- Results -->
  <?php if (empty($events)): ?>
    <div class="page-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" x2="9.01" y1="9" y2="9"/><line x1="15" x2="15.01" y1="9" y2="9"/></svg>
      <p>Nuk u gjetën evente<?= $search ? ' për "' . htmlspecialchars($search) . '"' : '' ?>.</p>
    </div>
  <?php else: ?>
    <p class="page-results-count"><?= $total ?> evente u gjetën</p>
    <div class="page-grid">
      <?php foreach ($events as $ev): ?>
        <a href="/TiranaSolidare/views/events.php?id=<?= $ev['id_eventi'] ?>" class="page-card">
          <?php if (!empty($ev['banner'])): ?>
            <img src="<?= htmlspecialchars($ev['banner']) ?>" alt="<?= htmlspecialchars($ev['titulli']) ?>" class="page-card__img">
          <?php else: ?>
            <div class="page-card__img page-card__img--placeholder">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            </div>
          <?php endif; ?>
          <div class="page-card__body">
            <span class="page-card__badge"><?= htmlspecialchars($ev['kategoria_emri'] ?? 'Event') ?></span>
            <h3 class="page-card__title"><?= htmlspecialchars($ev['titulli']) ?></h3>
            <p class="page-card__desc"><?= htmlspecialchars(mb_substr($ev['pershkrimi'] ?? '', 0, 120)) ?></p>
            <div class="page-card__meta">
              <span><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg> <?= htmlspecialchars($ev['vendndodhja'] ?? 'Tiranë') ?></span>
              <span><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg> <?= date('d M Y', strtotime($ev['data'])) ?></span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <nav class="page-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
          <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>"
             class="page-pagination__btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php endif; ?>

</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>

<script>
// Apply for event (AJAX)
document.addEventListener('DOMContentLoaded', function() {
  const btn = document.getElementById('apply-btn');
  if (!btn) return;
  
  btn.addEventListener('click', async function() {
    const eventId = this.dataset.event;
    try {
      const res = await fetch('/TiranaSolidare/api/applications.php?action=apply', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_eventi: parseInt(eventId) })
      });
      const json = await res.json();
      if (json.success) {
        btn.textContent = 'Aplikimi u dërgua!';
        btn.disabled = true;
        btn.style.opacity = '0.6';
      } else {
        alert(json.message || 'Gabim gjatë aplikimit.');
      }
    } catch (err) {
      alert('Gabim rrjeti.');
    }
  });
});
</script>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
