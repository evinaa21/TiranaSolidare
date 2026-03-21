<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = ($isLoggedIn && ($_SESSION['roli'] ?? '') === 'Admin');

// ── Fetch real data for the showcase ──
$statTotalEvents = (int) $pdo->query("SELECT COUNT(*) FROM Eventi")->fetchColumn();
$statUpcoming    = (int) $pdo->query("SELECT COUNT(*) FROM Eventi WHERE data >= NOW()")->fetchColumn();
$statVullnetare  = (int) $pdo->query("SELECT COUNT(*) FROM Perdoruesi WHERE roli = 'Vullnetar'")->fetchColumn();
$statApplications = (int) $pdo->query("SELECT COUNT(*) FROM Aplikimi")->fetchColumn();

// Fetch categories
$categories = $pdo->query("SELECT * FROM Kategoria ORDER BY emri")->fetchAll(PDO::FETCH_ASSOC);

// Featured event (upcoming, most applications)
$featuredStmt = $pdo->query(
    "SELECT e.*, k.emri AS kategoria_emri, p.emri AS krijuesi_emri,
            (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi) AS total_aplikime
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
     WHERE e.data >= NOW()
     ORDER BY total_aplikime DESC, e.data ASC
     LIMIT 1"
);
$featured = $featuredStmt->fetch(PDO::FETCH_ASSOC);

// Remaining upcoming events
$upcomingStmt = $pdo->query(
    "SELECT e.*, k.emri AS kategoria_emri, p.emri AS krijuesi_emri
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
     WHERE e.data >= NOW()" . ($featured ? " AND e.id_eventi != " . (int)$featured['id_eventi'] : "") . "
     ORDER BY e.data ASC
     LIMIT 9"
);
$upcomingEvents = $upcomingStmt->fetchAll(PDO::FETCH_ASSOC);

// Past events (recent)
$pastStmt = $pdo->query(
    "SELECT e.*, k.emri AS kategoria_emri
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     WHERE e.data < NOW()
     ORDER BY e.data DESC
     LIMIT 6"
);
$pastEvents = $pastStmt->fetchAll(PDO::FETCH_ASSOC);

// Albanian month names
$months_sq = [1=>'Janar','Shkurt','Mars','Prill','Maj','Qershor','Korrik','Gusht','Shtator','Tetor','Nëntor','Dhjetor'];
$days_sq = ['Hën','Mar','Mër','Enj','Pre','Sht','Die'];

// Generate current week days
$today = new DateTime();
$monday = clone $today;
$monday->modify('monday this week');
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = clone $monday;
    $d->modify("+$i days");
    // Check if any event is on this day
    $dayStr = $d->format('Y-m-d');
    $hasEvent = false;
    foreach ($upcomingEvents as $ev) {
        if (date('Y-m-d', strtotime($ev['data'])) === $dayStr) {
            $hasEvent = true;
            break;
        }
    }
    if ($featured && date('Y-m-d', strtotime($featured['data'])) === $dayStr) {
        $hasEvent = true;
    }
    $weekDays[] = [
        'name' => $days_sq[$i],
        'num' => $d->format('d'),
        'isToday' => $d->format('Y-m-d') === $today->format('Y-m-d'),
        'hasEvents' => $hasEvent,
        'date' => $d,
    ];
}
$currentMonth = $months_sq[(int)$today->format('n')] . ' ' . $today->format('Y');

// Group upcoming events by date for display
$eventsByDate = [];
foreach ($upcomingEvents as $ev) {
    $dateKey = date('Y-m-d', strtotime($ev['data']));
    $eventsByDate[$dateKey][] = $ev;
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agjenda e Tiranës — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260320">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/showcase-events.css?v=20260320">
</head>
<body class="page-showcase-events">
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<!-- ═══════════════════════════════════════════════════════════
     HERO — Institutional City Agenda
     ═══════════════════════════════════════════════════════════ -->
<section class="ev-hero">
  <div class="ev-hero__shape ev-hero__shape--1"></div>
  <div class="ev-hero__shape ev-hero__shape--2"></div>
  <div class="ev-hero__shape ev-hero__shape--3"></div>

  <div class="ev-hero__inner">
    <div class="ev-hero__badge">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><rect width="10" height="8" x="7" y="8" rx="1"/><path d="M7 12h10"/><path d="M11 8v8"/></svg>
      Bashkia Tiranë
    </div>
    <h1>Agjenda e <span>Tiranës</span></h1>
    <p class="ev-hero__subtitle">Evente të organizuara nga Bashkia e Tiranës dhe organizata partnere. Zbulo mundësi për të kontribuar në komunitetin tënd.</p>

    <div class="ev-stats-strip">
      <div class="ev-stat">
        <span class="ev-stat__number"><?= $statVullnetare ?></span>
        <span class="ev-stat__label">Vullnetarë</span>
      </div>
      <div class="ev-stat">
        <span class="ev-stat__number"><?= $statTotalEvents ?></span>
        <span class="ev-stat__label">Evente</span>
      </div>
      <div class="ev-stat">
        <span class="ev-stat__number"><?= $statUpcoming ?></span>
        <span class="ev-stat__label">Të ardhshme</span>
      </div>
      <div class="ev-stat">
        <span class="ev-stat__number"><?= $statApplications ?></span>
        <span class="ev-stat__label">Aplikime</span>
      </div>
    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     WEEK STRIP — Calendar Quick Navigation
     ═══════════════════════════════════════════════════════════ -->
<div class="ev-week-strip">
  <div class="ev-week-strip__inner">
    <button class="ev-week-strip__nav" aria-label="Java e kaluar">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
    </button>

    <?php foreach ($weekDays as $wd): ?>
      <div class="ev-week-day <?= $wd['isToday'] ? 'ev-week-day--active' : '' ?> <?= $wd['hasEvents'] ? 'ev-week-day--has-events' : '' ?>">
        <span class="ev-week-day__name"><?= $wd['name'] ?></span>
        <span class="ev-week-day__num"><?= $wd['num'] ?></span>
        <span class="ev-week-day__dot"></span>
      </div>
    <?php endforeach; ?>

    <button class="ev-week-strip__nav" aria-label="Java e ardhshme">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
    </button>

    <span class="ev-week-strip__month"><?= $currentMonth ?></span>
  </div>
</div>


<?php if ($featured): ?>
<!-- ═══════════════════════════════════════════════════════════
     FEATURED EVENT — Large Hero Card
     ═══════════════════════════════════════════════════════════ -->
<section class="ev-featured-section">
  <div class="ev-featured-section__inner">
    <div class="ev-section-header">
      <h2>Eventi <span>Kryesor</span></h2>
      <div class="ev-section-header__line"></div>
    </div>

    <a href="/TiranaSolidare/views/events.php?id=<?= $featured['id_eventi'] ?>" class="ev-featured-card">
      <div class="ev-featured-card__img">
        <img src="<?= !empty($featured['banner']) ? htmlspecialchars($featured['banner']) : '/TiranaSolidare/public/assets/images/default-event.svg' ?>" alt="<?= htmlspecialchars($featured['titulli']) ?>" onerror="this.src='/TiranaSolidare/public/assets/images/default-event.svg'">
        <?php
          $eventDate = new DateTime($featured['data']);
          $now = new DateTime();
          $diff = $now->diff($eventDate);
          $daysLeft = $diff->days;
          $countdown = '';
          if ($daysLeft === 0) {
              $countdown = 'Sot!';
          } elseif ($daysLeft === 1) {
              $countdown = 'Nesër';
          } else {
              $countdown = "Pas {$daysLeft} ditësh";
          }
        ?>
        <div class="ev-featured-card__countdown">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <?= $countdown ?>
        </div>
      </div>

      <div class="ev-featured-card__content">
        <div class="ev-featured-card__badges">
          <span class="ev-badge ev-badge--category"><?= htmlspecialchars($featured['kategoria_emri'] ?? 'Event') ?></span>
          <span class="ev-badge ev-badge--official">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
            Verifikuar
          </span>
        </div>

        <h3 class="ev-featured-card__title"><?= htmlspecialchars($featured['titulli']) ?></h3>
        <p class="ev-featured-card__desc"><?= htmlspecialchars(mb_substr($featured['pershkrimi'] ?? '', 0, 200)) ?>...</p>

        <div class="ev-featured-card__meta">
          <div class="ev-meta-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            <strong><?= date('d', strtotime($featured['data'])) ?> <?= $months_sq[(int)date('n', strtotime($featured['data']))] ?> <?= date('Y', strtotime($featured['data'])) ?></strong> — <?= date('H:i', strtotime($featured['data'])) ?>
          </div>
          <div class="ev-meta-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
            <strong><?= htmlspecialchars($featured['vendndodhja'] ?? 'Tiranë') ?></strong>
          </div>
          <div class="ev-meta-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <strong><?= $featured['total_aplikime'] ?></strong> vullnetarë kanë aplikuar
          </div>
        </div>
      </div>
    </a>
  </div>
</section>
<?php endif; ?>


<!-- ═══════════════════════════════════════════════════════════
     EVENTS GRID — Chronological Feed
     ═══════════════════════════════════════════════════════════ -->
<section class="ev-grid-section">
  <div class="ev-grid-section__inner">

    <div class="ev-section-header">
      <h2>Të gjitha <span>eventet</span></h2>
      <div class="ev-section-header__line"></div>
    </div>

    <!-- Filters -->
    <div class="ev-filters">
      <div class="ev-filters__search">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" placeholder="Kërko evente...">
      </div>
      <select>
        <option value="">Të gjitha kategoritë</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat['id_kategoria'] ?>"><?= htmlspecialchars($cat['emri']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="ev-filters__view-toggle">
        <button class="ev-view-btn ev-view-btn--active" title="Pamje listë" data-view="grid">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
        </button>
        <button class="ev-view-btn" title="Pamje kalendari" data-view="calendar">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        </button>
      </div>
    </div>

    <!-- Card Grid View -->
    <div id="ev-grid-view">
      <?php if (!empty($upcomingEvents)): ?>
        <?php
          // Group by date
          $grouped = [];
          foreach ($upcomingEvents as $ev) {
              $dk = date('Y-m-d', strtotime($ev['data']));
              $grouped[$dk][] = $ev;
          }
        ?>
        <?php $groupIndex = 0; foreach ($grouped as $dateKey => $dayEvents): ?>
          <div class="ev-date-group" style="animation-delay: <?= $groupIndex * 0.1 ?>s">
            <div class="ev-date-group__header">
              <div class="ev-date-group__icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
              </div>
              <div class="ev-date-group__text">
                <?php
                  $dateObj = new DateTime($dateKey);
                  $dayNum = $dateObj->format('d');
                  $monthNum = (int)$dateObj->format('n');
                  $yearNum = $dateObj->format('Y');
                  $dayOfWeek = (int)$dateObj->format('N') - 1; // 0=Monday
                  $dayNames = ['E Hënë','E Martë','E Mërkurë','E Enjte','E Premte','E Shtunë','E Diel'];
                ?>
                <h3><?= $dayNames[$dayOfWeek] ?>, <?= $dayNum ?> <?= $months_sq[$monthNum] ?></h3>
                <span><?= count($dayEvents) ?> event<?= count($dayEvents) > 1 ? 'e' : '' ?></span>
              </div>
              <div class="ev-date-group__line"></div>
            </div>

            <div class="ev-card-grid">
              <?php foreach ($dayEvents as $i => $ev): ?>
                <a href="/TiranaSolidare/views/events.php?id=<?= $ev['id_eventi'] ?>" class="ev-card" style="animation-delay: <?= ($groupIndex * 0.1) + ($i * 0.05) ?>s">
                  <div class="ev-card__visual">
                    <img src="<?= !empty($ev['banner']) ? htmlspecialchars($ev['banner']) : '/TiranaSolidare/public/assets/images/default-event.svg' ?>" alt="<?= htmlspecialchars($ev['titulli']) ?>" onerror="this.src='/TiranaSolidare/public/assets/images/default-event.svg'">
                    <div class="ev-card__date-badge">
                      <span class="ev-card__date-badge__day"><?= date('d', strtotime($ev['data'])) ?></span>
                      <span class="ev-card__date-badge__month"><?= $months_sq[(int)date('n', strtotime($ev['data']))] ?></span>
                    </div>
                    <?php if (!empty($ev['kategoria_emri'])): ?>
                      <div class="ev-card__overlay-badges">
                        <span class="ev-badge ev-badge--category"><?= htmlspecialchars($ev['kategoria_emri']) ?></span>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="ev-card__body">
                    <span class="ev-card__category"><?= htmlspecialchars($ev['kategoria_emri'] ?? 'Event') ?></span>
                    <h3 class="ev-card__title"><?= htmlspecialchars($ev['titulli']) ?></h3>
                    <p class="ev-card__excerpt"><?= htmlspecialchars(mb_substr($ev['pershkrimi'] ?? '', 0, 100)) ?>...</p>
                    <div class="ev-card__footer">
                      <div class="ev-card__info">
                        <span class="ev-card__info-item">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                          <?= htmlspecialchars($ev['vendndodhja'] ?? 'Tiranë') ?>
                        </span>
                        <span class="ev-card__info-item">
                          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                          <?= date('H:i', strtotime($ev['data'])) ?>
                        </span>
                      </div>
                      <span class="ev-card__cta">
                        Shiko
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                      </span>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php $groupIndex++; endforeach; ?>

      <?php else: ?>
        <div style="text-align:center; padding: 60px 20px; color: var(--ev-text-muted);">
          <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom:16px; opacity:0.4"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
          <h3 style="margin-bottom:8px;">Nuk ka evente të ardhshme</h3>
          <p>Kërko më vonë ose shiko eventet e kaluara.</p>
        </div>
      <?php endif; ?>

      <?php if (!empty($pastEvents)): ?>
        <div class="ev-date-group" style="margin-top: 50px;">
          <div class="ev-date-group__header">
            <div class="ev-date-group__icon" style="background: linear-gradient(135deg, #6b7280, #9ca3af);">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="ev-date-group__text">
              <h3>Evente të kaluara</h3>
              <span>Shiko çfarë ka ndodhur</span>
            </div>
            <div class="ev-date-group__line"></div>
          </div>
          <div class="ev-card-grid">
            <?php foreach ($pastEvents as $i => $ev): ?>
              <a href="/TiranaSolidare/views/events.php?id=<?= $ev['id_eventi'] ?>" class="ev-card" style="animation-delay: <?= $i * 0.05 ?>s; opacity: 0.7;">
                <div class="ev-card__visual">
                  <img src="<?= !empty($ev['banner']) ? htmlspecialchars($ev['banner']) : '/TiranaSolidare/public/assets/images/default-event.svg' ?>" alt="<?= htmlspecialchars($ev['titulli']) ?>" onerror="this.src='/TiranaSolidare/public/assets/images/default-event.svg'" style="filter: grayscale(0.4);">
                  <div class="ev-card__date-badge">
                    <span class="ev-card__date-badge__day"><?= date('d', strtotime($ev['data'])) ?></span>
                    <span class="ev-card__date-badge__month"><?= $months_sq[(int)date('n', strtotime($ev['data']))] ?></span>
                  </div>
                </div>
                <div class="ev-card__body">
                  <span class="ev-card__category"><?= htmlspecialchars($ev['kategoria_emri'] ?? 'Event') ?></span>
                  <h3 class="ev-card__title"><?= htmlspecialchars($ev['titulli']) ?></h3>
                  <p class="ev-card__excerpt"><?= htmlspecialchars(mb_substr($ev['pershkrimi'] ?? '', 0, 100)) ?>...</p>
                  <div class="ev-card__footer">
                    <div class="ev-card__info">
                      <span class="ev-card__info-item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                        <?= date('d M Y', strtotime($ev['data'])) ?>
                      </span>
                    </div>
                    <span class="ev-card__cta">
                      Shiko
                      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </span>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Calendar View (hidden by default) -->
    <div id="ev-calendar-view" style="display:none;">
      <?php
        // Build a simple calendar grid for current month
        $calYear  = (int) $today->format('Y');
        $calMonth = (int) $today->format('n');
        $firstDay = new DateTime("$calYear-$calMonth-01");
        $daysInMonth = (int) $firstDay->format('t');
        $startDow = ((int) $firstDay->format('N')); // 1=Mon 7=Sun

        // Collect event days for the month
        $monthEventStmt = $pdo->prepare(
            "SELECT id_eventi, titulli, data, id_kategoria FROM Eventi 
             WHERE YEAR(data) = ? AND MONTH(data) = ? ORDER BY data ASC"
        );
        $monthEventStmt->execute([$calYear, $calMonth]);
        $monthEvents = $monthEventStmt->fetchAll(PDO::FETCH_ASSOC);
        $calEventsByDay = [];
        foreach ($monthEvents as $me) {
            $d = (int) date('j', strtotime($me['data']));
            $calEventsByDay[$d][] = $me;
        }
      ?>

      <div class="ev-calendar-grid">
        <?php foreach (['Hën','Mar','Mër','Enj','Pre','Sht','Die'] as $dh): ?>
          <div class="ev-calendar-header"><?= $dh ?></div>
        <?php endforeach; ?>

        <?php
          // Empty cells before start
          for ($e = 1; $e < $startDow; $e++): ?>
            <div class="ev-calendar-cell ev-calendar-cell--other"></div>
        <?php endfor; ?>

        <?php for ($day = 1; $day <= $daysInMonth; $day++):
          $isToday = ($day === (int)$today->format('j') && $calMonth === (int)$today->format('n') && $calYear === (int)$today->format('Y'));
        ?>
          <div class="ev-calendar-cell <?= $isToday ? 'ev-calendar-cell--today' : '' ?>">
            <span class="ev-calendar-cell__day"><?= $day ?></span>
            <?php if (isset($calEventsByDay[$day])): ?>
              <?php foreach (array_slice($calEventsByDay[$day], 0, 3) as $idx => $ce):
                $colorClasses = ['ev-calendar-event--green','ev-calendar-event--gold','ev-calendar-event--blue'];
              ?>
                <div class="ev-calendar-event <?= $colorClasses[$idx % 3] ?>" title="<?= htmlspecialchars($ce['titulli']) ?>">
                  <?= htmlspecialchars(mb_substr($ce['titulli'], 0, 18)) ?>
                </div>
              <?php endforeach; ?>
              <?php if (count($calEventsByDay[$day]) > 3): ?>
                <div class="ev-calendar-event ev-calendar-event--green">+<?= count($calEventsByDay[$day]) - 3 ?> më shumë</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endfor; ?>

        <?php
          // Remaining cells
          $totalCells = ($startDow - 1) + $daysInMonth;
          $remaining = (7 - ($totalCells % 7)) % 7;
          for ($r = 0; $r < $remaining; $r++): ?>
            <div class="ev-calendar-cell ev-calendar-cell--other"></div>
        <?php endfor; ?>
      </div>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     CTA BANNER
     ═══════════════════════════════════════════════════════════ -->
<section class="ev-cta">
  <div class="ev-cta__inner">
    <div class="ev-cta__icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
    </div>
    <div class="ev-cta__text">
      <h2>Bëhu pjesë e ndryshimit</h2>
      <p>Regjistrohu si vullnetar dhe merr pjesë në eventet e ardhshme të Bashkisë së Tiranës.</p>
    </div>
    <?php if ($isLoggedIn): ?>
      <a href="/TiranaSolidare/views/volunteer_panel.php" class="ev-cta__btn">
        Shko te paneli
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </a>
    <?php else: ?>
      <a href="/TiranaSolidare/views/register.php" class="ev-cta__btn">
        Regjistrohu tani
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
      </a>
    <?php endif; ?>
  </div>
</section>

</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>

<script>
// ── View Toggle: Grid / Calendar ──
document.addEventListener('DOMContentLoaded', function() {
  const viewBtns = document.querySelectorAll('.ev-view-btn');
  const gridView = document.getElementById('ev-grid-view');
  const calView  = document.getElementById('ev-calendar-view');

  viewBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      viewBtns.forEach(b => b.classList.remove('ev-view-btn--active'));
      this.classList.add('ev-view-btn--active');
      const view = this.dataset.view;
      if (view === 'calendar') {
        gridView.style.display = 'none';
        calView.style.display  = 'block';
      } else {
        gridView.style.display = 'block';
        calView.style.display  = 'none';
      }
    });
  });
});
</script>
</body>
</html>
