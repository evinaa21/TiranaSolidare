<?php
// views/volunteer_panel.php — Volunteer Personal Panel (public-style layout)
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/status_labels.php';
check_login();

// Dashboard roles should use the shared dashboard instead of the volunteer panel.
if (ts_is_dashboard_role_value($_SESSION['roli'] ?? '')) {
    header("Location: /TiranaSolidare/views/dashboard.php");
    exit();
}

$userId    = $_SESSION['user_id'];
$userEmri  = htmlspecialchars($_SESSION['emri'] ?? 'Përdorues');
$userRoli  = status_label($_SESSION['roli'] ?? 'volunteer');
$userEmail = htmlspecialchars($_SESSION['email'] ?? '');
$userInitial = mb_strtoupper(mb_substr($_SESSION['emri'] ?? 'P', 0, 1));

// Active tab
$tab = $_GET['tab'] ?? 'profile';

// Fetch user profile
$stmtUser = $pdo->prepare("SELECT * FROM Perdoruesi WHERE id_perdoruesi = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

$colorResolved = ts_resolve_profile_color($user['profile_color'] ?? 'emerald');
$profileColorKey = $colorResolved['key'];
$profileColorTheme = $colorResolved['theme'];
$profileColorPalette = $colorResolved['palette'];

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
$myApps = ts_normalize_rows($stmtApps->fetchAll(PDO::FETCH_ASSOC));

// Fetch my help request applications
$stmtHelpApps = $pdo->prepare(
    "SELECT ak.*, kn.titulli AS kerkesa_titulli, kn.tipi AS kerkesa_tipi,
      kn.statusi AS kerkesa_statusi, kn.krijuar_me AS kerkesa_krijuar_me,
      kn.matching_mode, kn.capacity_total,
            p.emri AS postuesi_emri
     FROM Aplikimi_Kerkese ak
     JOIN Kerkesa_per_Ndihme kn ON kn.id_kerkese_ndihme = ak.id_kerkese_ndihme
     JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
     WHERE ak.id_perdoruesi = ?
     ORDER BY ak.aplikuar_me DESC"
);
try {
  $stmtHelpApps->execute([$userId]);
  $myHelpApps = ts_normalize_rows($stmtHelpApps->fetchAll(PDO::FETCH_ASSOC));
} catch (Throwable $e) {
  // Backward compatibility if DB schema is not yet migrated.
  $myHelpApps = [];
}

// Fetch my help requests
$stmtReqs = $pdo->prepare(
    "SELECT * FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ? ORDER BY krijuar_me DESC"
);
$stmtReqs->execute([$userId]);
$myRequests = ts_normalize_rows($stmtReqs->fetchAll(PDO::FETCH_ASSOC));

$helpRequestIds = array_values(array_unique(array_merge(
  array_map(static fn (array $row): int => (int) ($row['id_kerkese_ndihme'] ?? 0), $myHelpApps),
  array_map(static fn (array $row): int => (int) ($row['id_kerkese_ndihme'] ?? 0), $myRequests)
)));
$helpRequestCounts = ts_help_request_application_counts_by_request_ids($pdo, $helpRequestIds);

foreach ($myHelpApps as &$helpApp) {
  $helpAppId = (int) ($helpApp['id_kerkese_ndihme'] ?? 0);
  $helpApp['_matching'] = ts_help_request_matching_details([
    'statusi' => $helpApp['kerkesa_statusi'] ?? 'open',
    'matching_mode' => $helpApp['matching_mode'] ?? 'open',
    'capacity_total' => $helpApp['capacity_total'] ?? null,
  ], $helpRequestCounts[$helpAppId] ?? []);
  $helpApp['kerkesa_statusi'] = $helpApp['_matching']['resolved_status'];
}
unset($helpApp);

foreach ($myRequests as &$myRequest) {
  $requestId = (int) ($myRequest['id_kerkese_ndihme'] ?? 0);
  $myRequest['_matching'] = ts_help_request_matching_details($myRequest, $helpRequestCounts[$requestId] ?? []);
  $myRequest['statusi'] = $myRequest['_matching']['resolved_status'];
}
unset($myRequest);

// Stats
$totalApps     = count($myApps);
$acceptedApps  = count(array_filter($myApps, fn($a) => $a['statusi'] === 'approved'));
$pendingApps   = count(array_filter($myApps, fn($a) => $a['statusi'] === 'pending'));
$totalRequests = count($myRequests);
$openRequests  = count(array_filter($myRequests, fn($r) => in_array(($r['statusi'] ?? ''), TS_HELP_REQUEST_ACTIVE_STATUSES, true)));
$score        = ($acceptedApps * 5) + ($totalApps * 1) + ($totalRequests * 2);
$scoreMax     = 150;
$scorePercent = min(100, round(($score / $scoreMax) * 100));
$profileBadgeInfo = ts_get_user_profile_badges($pdo, (int) $userId);
$earnedBadges = $profileBadgeInfo['badges'];
$earnedBadgeCount = count($earnedBadges);
$publicProfileUrl = ts_public_profile_url((int) $userId, (string) ($user['emri'] ?? ($_SESSION['emri'] ?? 'Përdorues')));

$badgeIcons = [
  'seedling' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M10 20c5.5-2.5 7-7.8 7-12-4.2 0-8 2.7-9.5 6.8"/><path d="M7 20c-2.5-2.2-4-5.6-4-9.5 3.2 0 5.9 1.3 7.9 3.6"/></svg>',
  'calendar-check' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9.5 16.5 2 2 4-4"/></svg>',
  'hands-helping' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 11.5 10.5 9a2.1 2.1 0 0 1 3 3L12 13.5"/><path d="M2 15c1.6-1.7 3.1-2.5 4.8-2.5h2.7"/><path d="M22 9c-1.6 1.7-3.1 2.5-4.8 2.5h-2.7"/><path d="M3 20h5l2.5-2.5"/><path d="M21 4h-5l-2.5 2.5"/></svg>',
  'megaphone' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 11-5v12L3 13v-2Z"/><path d="M11 19a4 4 0 0 1-4 4"/><path d="M14 8a7 7 0 0 1 0 8"/><path d="M18 6a11 11 0 0 1 0 12"/></svg>',
  'heart-handshake' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.5-1.5 3-3.2 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.7 0-3 .5-4.5 2C10.5 3.5 9.2 3 7.5 3A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4 3 5.5l7 7 7-7Z"/><path d="m8.5 12.5 2.3 2.3L15.5 10"/></svg>',
  'shield' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>',
  'sparkles' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.1L18 9l-4.1 1.9L12 15l-1.9-4.1L6 9l4.1-1.9L12 3Z"/><path d="M5 16l.9 2.1L8 19l-2.1.9L5 22l-.9-2.1L2 19l2.1-.9L5 16Z"/><path d="M19 13l.9 2.1L22 16l-2.1.9L19 19l-.9-2.1L16 16l2.1-.9L19 13Z"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Paneli im — Tirana Solidare</title>
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css?v=20260401a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/auth.css?v=20260401a">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/volunteer-panel.css?v=20260401b">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/dashboard.css?v=20260401a">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <link rel="stylesheet" href="/TiranaSolidare/assets/css/map.css?v=20260401a">
  <?= csrf_meta() ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main>

<!-- ─── HERO ─── -->
<section class="vp-hero" style="--vp-accent: <?= htmlspecialchars($profileColorTheme['from']) ?>; --vp-accent-mid: <?= htmlspecialchars($profileColorTheme['mid']) ?>; --vp-accent-end: <?= htmlspecialchars($profileColorTheme['to']) ?>;">
  <div class="vp-hero__orb vp-hero__orb--one" aria-hidden="true"></div>
  <div class="vp-hero__orb vp-hero__orb--two" aria-hidden="true"></div>
  <div class="vp-hero__inner">
    <div class="vp-hero__identity">
      <?php if (!empty($user['profile_picture'])): ?>
        <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= $userEmri ?>" class="vp-hero__avatar vp-hero__avatar--img">
      <?php else: ?>
        <div class="vp-hero__avatar vp-hero__avatar--letter"><?= $userInitial ?></div>
      <?php endif; ?>
      <div class="vp-hero__text">
        <span class="vp-hero__eyebrow">Profili yt në komunitet</span>
        <div class="vp-hero__headline">
          <h2 class="vp-hero__name"><?= $userEmri ?></h2>
          <span class="vp-hero__role-badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
            <?= htmlspecialchars($userRoli) ?>
          </span>
        </div>
        <?php if (!empty($user['email'])): ?>
          <p class="vp-hero__email"><?= htmlspecialchars($user['email']) ?></p>
        <?php endif; ?>
        <p class="vp-hero__summary">
          Ke ndërtuar <?= $score ?> pikë nga kontributet e tua, me <?= $acceptedApps ?> aplikime të pranuara,
          <?= $totalRequests ?> kërkesa të krijuara dhe <?= $earnedBadgeCount ?> badge të fituara deri tani.
        </p>
        <div class="vp-hero__meta">
          <span>Anëtar që nga <?= date('m/Y', strtotime($user['krijuar_me'])) ?></span>
          <span><?= $pendingApps ?> aplikime në pritje</span>
          <span><?= $openRequests ?> kërkesa aktive</span>
        </div>
        <?php if ($earnedBadgeCount > 0): ?>
          <div class="vp-hero__badge-strip">
            <?php foreach (array_slice($earnedBadges, 0, 3) as $badge): ?>
              <span class="vp-hero__badge-pill" title="<?= htmlspecialchars($badge['name']) ?>">
                <?= $badgeIcons[$badge['icon']] ?? '' ?>
                <?= htmlspecialchars($badge['name']) ?>
              </span>
            <?php endforeach; ?>
            <?php if ($earnedBadgeCount > 3): ?>
              <span class="vp-hero__badge-pill vp-hero__badge-pill--count">+<?= $earnedBadgeCount - 3 ?> të tjera</span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="vp-hero__actions">
          <a href="<?= htmlspecialchars($publicProfileUrl) ?>" target="_blank" rel="noopener" class="btn_primary vp-hero__cta">Shiko profilin publik</a>
          <a href="?tab=settings" class="vp-hero__ghost">Personalizo profilin</a>
        </div>
      </div>
    </div>
    <div class="vp-hero__side">
      <div class="vp-hero__score-card">
        <span class="vp-hero__score-label">Ritmi yt i kontributit</span>
        <div class="vp-hero__score-value">
          <?= $score ?>
          <small>/<?= $scoreMax ?> pikë</small>
        </div>
        <div class="vp-hero__progress" aria-hidden="true">
          <span style="width: <?= $scorePercent ?>%"></span>
        </div>
        <div class="vp-hero__score-foot">
          <span><?= $scorePercent ?>% e objektivit të profilit</span>
          <span><?= $earnedBadgeCount ?> badge</span>
        </div>
      </div>
      <div class="vp-hero__stats-inline">
        <div class="vp-hi-stat">
          <strong><?= $totalApps ?></strong><span>Aplikime</span>
        </div>
        <div class="vp-hi-stat">
          <strong><?= $acceptedApps ?></strong><span>Pranuar</span>
        </div>
        <div class="vp-hi-stat">
          <strong><?= $totalRequests ?></strong><span>Kërkesa</span>
        </div>
        <div class="vp-hi-stat">
          <strong><?= $earnedBadgeCount ?></strong><span>Badge</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ─── STATS BAR (mobile-only fallback, hidden on desktop since inline in hero) ─── -->
<section class="vp-stats-section vp-stats-section--mobile">
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
    <a href="?tab=score" class="vp-tab <?= $tab === 'score' ? 'active' : '' ?>">
  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
  Pikët e mia
</a>
    <a href="?tab=notifications" class="vp-tab <?= $tab === 'notifications' ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
      Njoftimet
      <span class="vp-tab-badge" id="notif-tab-badge" style="display:none"></span>
    </a>
    <a href="?tab=messages" class="vp-tab <?= $tab === 'messages' ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
      Mesazhet
      <span class="vp-tab-badge" id="msg-tab-badge" style="display:none"></span>
    </a>
    <a href="?tab=settings" class="vp-tab <?= $tab === 'settings' ? 'active' : '' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Cilësimet
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
          <div class="vp-avatar-uploader">
            <button type="button" class="vp-avatar-edit-btn" id="vp-avatar-click-target" aria-label="Ndrysho foton e profilit" title="Kliko për të ndryshuar foton e profilit">
              <?php if (!empty($user['profile_picture'])): ?>
                <img id="vp-profile-avatar-display" src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="<?= $userEmri ?>" class="vp-avatar vp-avatar--img">
              <?php else: ?>
                <div id="vp-profile-avatar-display" class="vp-avatar" style="background:linear-gradient(135deg, <?= htmlspecialchars($profileColorTheme['mid']) ?>, <?= htmlspecialchars($profileColorTheme['to']) ?>)"><?= $userInitial ?></div>
              <?php endif; ?>
              <span class="vp-avatar-edit-btn__hint" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/></svg>
              </span>
            </button>
            <?php if (!empty($user['profile_picture'])): ?>
              <button type="button" class="vp-avatar-delete-btn__hint" id="vp-avatar-delete-btn" aria-label="Fshi foton e profilit" title="Fshi foton e profilit">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
              </button>
            <?php endif; ?>
            <input type="file" id="vp-avatar-upload-input" accept="image/*" style="display:none;">
          </div>
          <div class="vp-profile-info">
            <strong id="vp-profile-emri"><?= htmlspecialchars($user['emri'] ?? '—') ?></strong>
            <span id="vp-profile-email"><?= htmlspecialchars($user['email'] ?? '—') ?></span>
            <div class="vp-profile-badges">
              <span class="vp-badge vp-badge--role"><?= $userRoli ?></span>
              <span class="vp-badge vp-badge--status"><?= htmlspecialchars(status_label($user['statusi_llogarise'] ?? '')) ?></span>
            </div>
          </div>
        </div>
        <div id="vp-avatar-status" class="vp-status" style="display:none"></div>
      </div>
    </div>

    <div class="vp-card" style="grid-column: 1 / -1">
      <div class="vp-card__header">
        <h3>Profili publik</h3>
      </div>
      <div class="vp-card__body">
        <form id="vp-profile-form" class="vp-form">
          <input type="hidden" id="vp-current-picture" value="<?= htmlspecialchars($user['profile_picture'] ?? '') ?>">
          <div class="vp-field">
            <label for="vp-bio">Bio</label>
            <textarea id="vp-bio" name="bio" rows="3" maxlength="500" placeholder="Shkruaj diçka për veten..." class="vp-input"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
          </div>
          <div class="vp-field">
            <label>Ngjyra e profilit</label>
            <div class="vp-color-dropdown">
              <button type="button" class="vp-color-dropdown__trigger" id="vp-color-trigger" aria-haspopup="listbox" aria-expanded="false">
                <span class="vp-color-dropdown__swatch" id="vp-color-swatch" style="background-color: <?= htmlspecialchars($profileColorTheme['mid']) ?>"></span>
                <span class="vp-color-dropdown__label"><?= htmlspecialchars($profileColorPalette[$profileColorKey]['label'] ?? 'Emerald') ?></span>
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
              </button>
              <div class="vp-color-dropdown__menu" id="vp-color-menu" hidden>
                <div class="vp-color-dropdown__grid">
                  <?php foreach ($profileColorPalette as $colorKey => $theme): ?>
                    <button type="button" class="vp-color-dropdown__option <?= $profileColorKey === $colorKey ? 'active' : '' ?>" data-color="<?= htmlspecialchars($colorKey) ?>" aria-label="<?= htmlspecialchars($theme['label']) ?>" title="<?= htmlspecialchars($theme['label']) ?>">
                      <span class="vp-color-dropdown__color" style="background: linear-gradient(135deg, <?= htmlspecialchars($theme['from']) ?>, <?= htmlspecialchars($theme['to']) ?>)"></span>
                      <span class="vp-color-dropdown__name"><?= htmlspecialchars($theme['label']) ?></span>
                    </button>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <input type="hidden" id="vp-profile-color" name="profile_color" value="<?= htmlspecialchars($profileColorKey) ?>">
          </div>
          <button type="submit" class="btn_primary">Ruaj profilin publik</button>
        </form>
        <div id="vp-profile-form-status" class="vp-status" style="display:none"></div>
      </div>
    </div>

    <div class="vp-card" style="grid-column: 1 / -1">
      <div class="vp-card__header">
        <h3>Badge të fituara</h3>
      </div>
      <div class="vp-card__body">
        <?php if (empty($earnedBadges)): ?>
          <p class="vp-muted" style="margin:0;">Nuk ke badge ende. Kontribuo në evente dhe kërkesa për t'i fituar.</p>
        <?php else: ?>
          <div class="vp-earned-badges-grid">
            <?php foreach ($earnedBadges as $badge): ?>
              <?php $iconSvg = $badgeIcons[$badge['icon'] ?? ''] ?? $badgeIcons['sparkles']; ?>
              <div class="vp-earned-badge" title="<?= htmlspecialchars($badge['description']) ?>">
                <span class="vp-earned-badge__icon" aria-hidden="true"><?= $iconSvg ?></span>
                <div class="vp-earned-badge__text">
                  <strong><?= htmlspecialchars($badge['name']) ?></strong>
                  <span><?= htmlspecialchars($badge['description']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<?php elseif ($tab === 'settings'): ?>
<!-- ════════════ SETTINGS TAB ════════════ -->
<div class="vp-panel">
  <div class="vp-settings-layout">
    <aside class="vp-settings-nav" role="tablist" aria-label="Kategoritë e cilësimeve">
      <button type="button" class="vp-settings-nav__item active" data-settings-target="account" role="tab" aria-selected="true">Llogaria</button>
      <button type="button" class="vp-settings-nav__item" data-settings-target="public-profile" role="tab" aria-selected="false">Profili publik</button>
      <button type="button" class="vp-settings-nav__item" data-settings-target="email" role="tab" aria-selected="false">Email</button>
      <button type="button" class="vp-settings-nav__item" data-settings-target="notifications" role="tab" aria-selected="false">Njoftimet</button>
      <button type="button" class="vp-settings-nav__item" data-settings-target="security" role="tab" aria-selected="false">Siguria</button>
    </aside>

    <div class="vp-settings-content">
      <section class="vp-settings-panel" id="vp-settings-public-profile" data-settings-panel="public-profile" role="tabpanel">
        <div class="vp-card">
          <div class="vp-card__header">
            <h3>Dukshmëria e profilit</h3>
          </div>
          <div class="vp-card__body">
            <form id="vp-visibility-form" class="vp-form">
              <div class="vp-field" style="display:flex;align-items:center;
    flex-direction: row;justify-content:space-between">
                <label for="vp-public" style="margin:0;cursor:pointer">Profili im është publik</label>
                <div class="vp-toggle-wrapper">
                  <input type="checkbox" id="vp-public" name="profile_public" class="vp-toggle-input" <?= ($user['profile_public'] ?? 0) ? 'checked' : '' ?>>
                  <label for="vp-public" class="vp-toggle-label"></label>
                </div>
              </div>
              <p class="vp-muted" style="margin:0;">Kur profili është privat, përmbajtja e aktivitetit shfaqet vetëm për ju.</p>
              <button type="submit" class="btn_primary">Ruaj</button>
            </form>
            <div id="vp-visibility-status" class="vp-status" style="display:none"></div>
          </div>
        </div>
      </section>

      <section class="vp-settings-panel active" id="vp-settings-account" data-settings-panel="account" role="tabpanel" hidden>
        <div class="vp-card">
          <div class="vp-card__header">
            <h3>Profili i llogarisë</h3>
            <p>Ndrysho emrin që shfaqet në platformë.</p>
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
      </section>

      <section class="vp-settings-panel" id="vp-settings-email" data-settings-panel="email" role="tabpanel" hidden>
        <div class="vp-card">
          <div class="vp-card__header">
            <h3>Ndrysho email-in</h3>
            <p>Për arsye sigurie, konfirmo me fjalëkalimin aktual.</p>
          </div>
          <div class="vp-card__body">
            <form id="vp-email-form" class="vp-form">
              <div class="vp-field">
                <label for="vp-new-email">Email i ri</label>
                <input type="email" id="vp-new-email" name="new_email" required placeholder="emer@shembull.com" class="vp-input">
              </div>
              <div class="vp-field">
                <label for="vp-confirm-email">Konfirmo email-in e ri</label>
                <input type="email" id="vp-confirm-email" name="confirm_email" required placeholder="emer@shembull.com" class="vp-input">
              </div>
              <div class="vp-field">
                <label for="vp-email-current-pw">Fjalëkalimi aktual</label>
                <input type="password" id="vp-email-current-pw" name="current_password" required placeholder="********" class="vp-input">
              </div>
              <button type="submit" class="btn_primary">Përditëso email-in</button>
            </form>
            <div id="vp-email-status" class="vp-status" style="display:none"></div>
          </div>
        </div>
      </section>

      <section class="vp-settings-panel" id="vp-settings-notifications" data-settings-panel="notifications" role="tabpanel" hidden>
        <div class="vp-card">
          <div class="vp-card__header">
            <h3>Preferencat e njoftimeve</h3>
            <p>Zgjidh nëse dëshiron të marrësh njoftime me email për aktivitetet në platformë.</p>
          </div>
          <div class="vp-card__body">
            <form id="vp-notifications-form" class="vp-form">
              <div class="vp-field" style="display:flex;align-items:center;flex-direction:row;justify-content:space-between">
                <label for="vp-email-notif" style="margin:0;cursor:pointer">Merr njoftime me email</label>
                <div class="vp-toggle-wrapper">
                  <input type="checkbox" id="vp-email-notif" name="email_notifications" class="vp-toggle-input" <?= ($user['email_notifications'] ?? 1) ? 'checked' : '' ?>>
                  <label for="vp-email-notif" class="vp-toggle-label"></label>
                </div>
              </div>
              <p class="vp-muted" style="margin:0;">Kur njoftimet me email janë çaktivizuar, do të merrni vetëm njoftime brenda platformës. Email-et e verifikimit dhe rivendosjes së fjalëkalimit do të dërgohen gjithmonë.</p>
              <button type="submit" class="btn_primary">Ruaj preferencat</button>
            </form>
            <div id="vp-notifications-status" class="vp-status" style="display:none"></div>

            <!-- Push Notification Subscription -->
            <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">
            <div class="vp-field" style="display:flex;align-items:center;flex-direction:row;justify-content:space-between;gap:12px">
              <div>
                <strong style="display:block;margin-bottom:4px;">Njoftime në telefon / browser</strong>
                <p class="vp-muted" style="margin:0;">Merr njoftime direkte në pajisjen tuaj edhe kur nuk jeni të kyçur në faqe.</p>
              </div>
              <button id="vp-push-btn" class="btn_primary" type="button" style="white-space:nowrap;flex-shrink:0" disabled>Duke u ngarkuar…</button>
            </div>
            <div id="vp-push-status" style="margin-top:8px;font-size:0.85rem;color:#64748b;display:none"></div>
          </div>
        </div>
      </section>

      <section class="vp-settings-panel" id="vp-settings-security" data-settings-panel="security" role="tabpanel" hidden>
        <div class="vp-card">
          <div class="vp-card__header">
            <h3>Ndrysho fjalëkalimin</h3>
            <p>Përdor një fjalëkalim të fortë dhe unik.</p>
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

        <!-- Danger Zone: Delete Account -->
        <div class="vp-card" style="border:1px solid #fca5a5;margin-top:1.5rem;">
          <div class="vp-card__header" style="background-color:#fff1f2;">
            <h3 style="color:#dc2626;">Fshi llogarinë</h3>
            <p style="color:#991b1b;">Kjo veprim është i pakthyeshëm. Të gjitha të dhënat tuaja (profili, aplikimet, mesazhet) do të fshihen përgjithmonë.</p>
          </div>
          <div class="vp-card__body">
            <button type="button" id="vp-delete-account-btn" class="btn_primary" style="background:#dc2626;border-color:#dc2626;">Fshi llogarinë tënde</button>
          </div>
        </div>
      </section>

      <!-- Delete Account Confirmation Modal -->
      <div id="vp-delete-account-modal" role="dialog" aria-modal="true" aria-labelledby="vp-delete-modal-title" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:2rem;max-width:440px;width:90%;box-shadow:0 20px 40px rgba(0,0,0,.3);">
          <h3 id="vp-delete-modal-title" style="color:#dc2626;margin:0 0 .75rem;">Konfirmo fshirjen e llogarisë</h3>
          <p style="color:#374151;margin:0 0 1.25rem;line-height:1.6;">Kjo veprim është <strong>i pakthyeshëm</strong>. Profili, aplikimet, mesazhet dhe të gjitha të dhënat tuaja do të fshihen përgjithmonë. Futni fjalëkalimin tuaj aktual për të konfirmuar.</p>
          <div class="vp-field" style="margin-bottom:1rem;">
            <label for="vp-delete-pw" style="font-weight:600;display:block;margin-bottom:.375rem;">Fjalëkalimi aktual</label>
            <input type="password" id="vp-delete-pw" class="vp-input" placeholder="••••••••" autocomplete="current-password">
          </div>
          <div id="vp-delete-status" style="min-height:1.2em;font-size:.875rem;color:#dc2626;margin-bottom:.75rem;"></div>
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <button id="vp-delete-confirm-btn" class="btn_primary" style="background:#dc2626;border-color:#dc2626;flex:1;">Fshi llogarinë</button>
            <button id="vp-delete-cancel-btn" class="btn_secondary" style="flex:1;" type="button">Anulo</button>
          </div>
        </div>
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
      <?php if (empty($myApps) && empty($myHelpApps)): ?>
        <div class="vp-empty">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
          <p>Nuk keni aplikime ende. <a href="/TiranaSolidare/views/events.php">Zbuloni eventet</a> ose <a href="/TiranaSolidare/views/help_requests.php">kërkesat për ndihmë</a> dhe aplikoni!</p>
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
                  <span class="vp-badge vp-badge--<?= $app['statusi'] === 'approved' ? 'success' : ($app['statusi'] === 'rejected' ? 'danger' : 'pending') ?>">
                     <?= htmlspecialchars(status_label($app['statusi'] ?? '')) ?>
                  </span>
                </td>
                <td><?= date('d M Y', strtotime($app['aplikuar_me'])) ?></td>
                <td>
                  <?php if ($app['statusi'] === 'pending'): ?>
                    <button class="btn_secondary vp-btn-sm vp-btn-danger" onclick="withdrawApp(<?= $app['id_aplikimi'] ?>)">Tërhiq</button>
                  <?php elseif ($app['statusi'] === 'approved'): ?>
                      <span class="vp-muted">—</span>
                  <?php else: ?>
                    <span class="vp-muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="vp-table-wrap" style="margin-top:18px;">
          <table class="vp-table">
            <thead>
              <tr>
                <th>Kërkesa</th>
                <th>Tipi</th>
                <th>Postuar nga</th>
                <th>Statusi i kërkesës</th>
                <th>Aplikimi im</th>
                <th>Veprim</th>
                <th>Aplikuar më</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($myHelpApps)): ?>
                <tr>
                  <td colspan="7" class="vp-muted">Nuk keni aplikime për kërkesa ndihme ende.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($myHelpApps as $app): ?>
                  <?php
                    $appMatching = $app['_matching'] ?? ts_help_request_matching_details([
                      'statusi' => $app['kerkesa_statusi'] ?? 'open',
                      'matching_mode' => $app['matching_mode'] ?? 'open',
                      'capacity_total' => $app['capacity_total'] ?? null,
                    ]);
                    $appStatusClass = match ($app['statusi'] ?? 'pending') {
                      'approved' => 'success',
                      'completed' => 'completed',
                      'rejected', 'withdrawn' => 'danger',
                      'waitlisted' => 'waitlisted',
                      default => 'pending',
                    };
                  ?>
                  <tr>
                    <td>
                      <a href="/TiranaSolidare/views/help_requests.php?id=<?= (int) $app['id_kerkese_ndihme'] ?>" class="vp-link">
                        <?= htmlspecialchars($app['kerkesa_titulli']) ?>
                      </a>
                    </td>
                    <td>
                      <span class="vp-badge vp-badge--<?= $app['kerkesa_tipi'] === 'offer' ? 'offer' : 'request' ?>">
                        <?= $app['kerkesa_tipi'] === 'request' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($app['postuesi_emri']) ?></td>
                    <td>
                      <span class="vp-badge vp-badge--<?= strtolower($app['kerkesa_statusi']) ?>">
                        <?= htmlspecialchars(status_label($app['kerkesa_statusi'])) ?>
                      </span>
                      <div class="vp-help-meta-text"><?= htmlspecialchars(($appMatching['has_capacity_limit'] ?? false) ? (($appMatching['progress_count'] ?? 0) . ' / ' . ($appMatching['capacity_total'] ?? 0) . ' përputhje') : 'Kapacitet i hapur') ?></div>
                    </td>
                    <td>
                      <span class="vp-badge vp-badge--<?= $appStatusClass ?>">
                         <?= htmlspecialchars(status_label($app['statusi'] ?? '')) ?>
                      </span>
                    </td>
                    <td>
                      <?php if (in_array(($app['statusi'] ?? ''), ['pending', 'approved', 'waitlisted'], true)): ?>
                        <button type="button" class="btn_secondary vp-btn-sm" onclick="withdrawHelpRequestApplication(<?= (int) $app['id_aplikimi_kerkese'] ?>)">Tërhiq</button>
                      <?php else: ?>
                        <span class="vp-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td><?= date('d M Y', strtotime($app['aplikuar_me'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
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
    <div class="vp-card__header vp-card__header--requests">
      <h3>Kërkesat e mia</h3>
      <a href="?tab=new-request" class="btn_secondary vp-btn-sm vp-card__action">+ Krijo kërkesë</a>
    </div>
    <div class="vp-card__body">
      <?php if (empty($myRequests)): ?>
        <div class="vp-empty">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
          <p>Nuk keni krijuar kërkesa ende. <a href="?tab=new-request">Krijo një tani!</a></p>
        </div>
      <?php else: ?>
        <div class="vp-request-grid">
          <?php foreach ($myRequests as $req): ?>
            <?php
              $reqMatching = $req['_matching'] ?? ts_help_request_matching_details($req);
              $reqSummary = ($reqMatching['has_capacity_limit'] ?? false)
                ? (($reqMatching['progress_count'] ?? 0) . ' / ' . ($reqMatching['capacity_total'] ?? 0) . ' përputhje')
                : 'Kapacitet i hapur';
              $reqQueue = ($reqMatching['counts']['waitlisted'] ?? 0) > 0
                ? (($reqMatching['counts']['waitlisted'] ?? 0) . ' në listë pritjeje')
                : (($reqMatching['counts']['pending'] ?? 0) > 0 ? (($reqMatching['counts']['pending'] ?? 0) . ' në shqyrtim') : (($reqMatching['total_applications'] ?? 0) . ' aplikime'));
            ?>
            <a href="/TiranaSolidare/views/help_requests.php?id=<?= $req['id_kerkese_ndihme'] ?>" class="vp-request-card">
              <div class="vp-request-card__header">
                <div class="vp-request-card__badges">
                  <span class="vp-badge vp-badge--<?= $req['tipi'] === 'offer' ? 'offer' : 'request' ?>"><?= $req['tipi'] === 'request' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?></span>
                  <?php
                    $vpModStatus = $req['moderation_status'] ?? 'approved';
                    if ($vpModStatus === 'pending_review'): ?>
                    <span class="vp-badge" style="background:#fef3c7;color:#92400e;">&#9203; <?= status_label('pending_review') ?></span>
                  <?php elseif ($vpModStatus === 'rejected'): ?>
                    <span class="vp-badge" style="background:#fee2e2;color:#991b1b;">&#10007; <?= status_label('rejected') ?></span>
                  <?php else: ?>
                    <span class="vp-badge vp-badge--<?= htmlspecialchars($req['statusi']) ?>"><?= htmlspecialchars(status_label($req['statusi'])) ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="vp-request-card__content">
                <h4><?= htmlspecialchars($req['titulli']) ?></h4>
                <div class="vp-request-card__summary">
                  <span><?= htmlspecialchars($reqSummary) ?></span>
                  <span><?= htmlspecialchars($reqQueue) ?></span>
                </div>
                <div class="vp-request-card__footer">
                  <span class="vp-request-card__time"><?= koheParapake($req['krijuar_me']) ?></span>
                  <span class="vp-request-card__arrow">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                  </span>
                </div>
              </div>
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
      <h3>Krijo kërkesë për ndihmë</h3>
      <p>Posto kërkesën ose kontributin tënd dhe komuniteti do të përgjigjet.</p>
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
              <option value="request">Kërkoj ndihmë</option>
              <option value="offer">Dua të ndihmoj</option>
            </select>
          </div>
        </div>
        <div class="vp-form-row">
          <div class="vp-field">
            <label for="vp-req-matching-mode">Modaliteti i përputhjes</label>
            <select id="vp-req-matching-mode" name="matching_mode" class="vp-input">
              <option value="open">Kapacitet i hapur</option>
              <option value="single">Një përputhje</option>
              <option value="limited">Kapacitet i kufizuar</option>
            </select>
            <small class="vp-help-text">Zgjidhni nëse postimi ka një përputhje, disa vende të kufizuara, ose pa kufi.</small>
          </div>
          <div class="vp-field" id="vp-req-capacity-wrap" style="display:none">
            <label for="vp-req-capacity">Kapaciteti</label>
            <input type="number" id="vp-req-capacity" name="capacity_total" min="2" max="100" placeholder="p.sh. 3" class="vp-input">
            <small class="vp-help-text">Kjo përdoret vetëm për modalitetin me kapacitet të kufizuar.</small>
          </div>
        </div>
        <div class="vp-field">
          <label for="vp-req-category">Kategoria</label>
          <select id="vp-req-category" name="id_kategoria" class="vp-input">
            <option value="">Zgjidh kategorinë (opsionale)</option>
            <?php
            $catStmt = $pdo->query("SELECT id_kategoria, emri FROM Kategoria ORDER BY emri");
            while ($cat = $catStmt->fetch(PDO::FETCH_ASSOC)):
            ?>
            <option value="<?= (int) $cat['id_kategoria'] ?>"><?= htmlspecialchars($cat['emri']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="vp-field">
          <label for="vp-req-desc">Përshkrimi</label>
          <textarea id="vp-req-desc" name="pershkrimi" rows="4" placeholder="Përshkruani kërkesën tuaj në detaje..." class="vp-input"></textarea>
        </div>
        <div class="vp-field" style="position:relative;">
          <label for="vp-req-location">Vendndodhja — shkruani adresën dhe zgjidhni nga sugjerimet</label>
          <input type="text" id="vp-req-location" name="vendndodhja" placeholder="p.sh. Bulevardi Dëshmorët e Kombit, Tiranë" autocomplete="off" class="vp-input">
          <div id="request-location-suggestions" style="position:absolute;top:100%;left:0;right:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:9999;display:none;max-height:240px;overflow-y:auto;margin-top:2px;"></div>
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
        <button type="submit" class="btn_primary">Krijo kërkesën</button>
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
             <span>Kërkesa krijuar (<?= $totalRequests ?>)</span>
             <strong><?= $totalRequests * 2 ?> pikë</strong>
        </div>
      </div>
      <a href="/TiranaSolidare/views/leaderboard.php" class="btn_secondary vp-btn-sm" style="margin-top: 20px; display: inline-flex; align-items: center; gap: 6px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
        Shiko Renditjen e Vullnetarëve
      </a>
    </div>
  </div>
</div>

<?php elseif ($tab === 'notifications'): ?>
<!-- ════════════ NOTIFICATIONS TAB ════════════ -->
<div class="vp-panel">
  <div class="vp-card">
    <div class="vp-card__header">
      <h3>Njoftimet</h3>
      <button class="vp-notif-toolbar-btn" id="vp-mark-all-read">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a2 2 0 0 1-2.06 0L2 7"/><path d="m15.5 13.5 2 2 4-4"/></svg>
        <span>Shëno të gjitha si të lexuara</span>
      </button>
    </div>
    <div class="vp-card__body">
      <div id="vp-notification-list">
        <div class="vp-loading">Duke ngarkuar njoftimet…</div>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'messages'): ?>
<!-- ════════════ MESSAGES TAB ════════════ -->
<div class="vp-panel">
  <div class="vp-card">
    <div class="vp-card__header" style="display:flex;justify-content:space-between;align-items:center;">
      <h3 id="vp-msg-title">Mesazhet</h3>
      <div id="vp-msg-actions">
        <button class="vp-notif-toolbar-btn" onclick="vpShowNewConversation()">
          <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
          <span>Mesazh i ri</span>
        </button>
      </div>
    </div>
    <div class="vp-card__body">
      <div id="vp-msg-content">
        <div class="vp-loading">Duke ngarkuar bisedat…</div>
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
// Live getter — always reads the current meta tag (auto-refreshed after mutations)
function getCSRF() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }
// Auto-update meta tag from API response
function refreshCSRF(json) { if (json && json.csrf_token) { const m = document.querySelector('meta[name="csrf-token"]'); if (m) m.content = json.csrf_token; } return json; }
// Compat alias so existing code like headers: { 'X-CSRF-Token': csrfToken } still works
let csrfToken = getCSRF();
// Wrap fetch to auto-refresh CSRF from mutation responses
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
      const emriEl = document.getElementById('vp-profile-emri');
      if (emriEl) emriEl.textContent = emri;
      const heroNameEl = document.querySelector('.vp-hero__name');
      if (heroNameEl) heroNameEl.textContent = emri;
      const headerUser = document.querySelector('.header-user-name');
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
    if (new_password.length < 8) { vpStatus('vp-pw-status', 'error', 'Fjalëkalimi duhet të ketë të paktën 8 karaktere.'); return; }
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

// ── Email form ──
const emailForm = document.getElementById('vp-email-form');
if (emailForm) {
  emailForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const new_email = document.getElementById('vp-new-email').value.trim();
    const confirm_email = document.getElementById('vp-confirm-email').value.trim();
    const current_password = document.getElementById('vp-email-current-pw').value;

    if (!new_email || !confirm_email || !current_password) {
      vpStatus('vp-email-status', 'error', 'Plotësoni të gjitha fushat.');
      return;
    }
    if (new_email.toLowerCase() !== confirm_email.toLowerCase()) {
      vpStatus('vp-email-status', 'error', 'Email-et nuk përputhen.');
      return;
    }

    try {
      const res = await fetch(API + '/auth.php?action=change_email', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({ new_email, confirm_email, current_password }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      const updatedEmail = json?.data?.email || new_email;
      const profileEmail = document.getElementById('vp-profile-email');
      if (profileEmail) profileEmail.textContent = updatedEmail;
      emailForm.reset();
      vpStatus('vp-email-status', 'success', 'Email-i u përditësua me sukses.');
    } catch (err) {
      vpStatus('vp-email-status', 'error', err.message);
    }
  });
}

// ── Delete account ──
const deleteAccountBtn = document.getElementById('vp-delete-account-btn');
const deleteAccountModal = document.getElementById('vp-delete-account-modal');
if (deleteAccountBtn && deleteAccountModal) {
  deleteAccountBtn.addEventListener('click', () => {
    document.getElementById('vp-delete-pw').value = '';
    document.getElementById('vp-delete-status').textContent = '';
    deleteAccountModal.style.display = 'flex';
  });
  document.getElementById('vp-delete-cancel-btn')?.addEventListener('click', () => {
    deleteAccountModal.style.display = 'none';
  });
  deleteAccountModal.addEventListener('click', (e) => {
    if (e.target === deleteAccountModal) deleteAccountModal.style.display = 'none';
  });
  document.getElementById('vp-delete-confirm-btn')?.addEventListener('click', async () => {
    const current_password = document.getElementById('vp-delete-pw').value;
    const statusEl = document.getElementById('vp-delete-status');
    const confirmBtn = document.getElementById('vp-delete-confirm-btn');
    if (!current_password) { statusEl.textContent = 'Futni fjalëkalimin tuaj.'; return; }
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Duke fshirë…';
    try {
      const res = await fetch(API + '/users.php?action=delete_account', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({ current_password }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      deleteAccountModal.style.display = 'none';
      alert('Llogaria juaj u fshi me sukses. Do të ridrejtoheni.');
      window.location.href = '/TiranaSolidare/public/';
    } catch (err) {
      statusEl.textContent = err.message;
      confirmBtn.disabled = false;
      confirmBtn.textContent = 'Fshi llogarinë';
    }
  });
}

// ── Help Request form ──
const reqForm = document.getElementById('vp-request-form');
const reqMatchingMode = document.getElementById('vp-req-matching-mode');
const reqCapacityWrap = document.getElementById('vp-req-capacity-wrap');
const reqCapacityInput = document.getElementById('vp-req-capacity');

function syncHelpRequestCapacityField() {
  if (!reqMatchingMode || !reqCapacityWrap || !reqCapacityInput) return;
  const mode = reqMatchingMode.value;
  const showCapacity = mode === 'limited';
  reqCapacityWrap.style.display = showCapacity ? '' : 'none';
  reqCapacityInput.required = showCapacity;
  reqCapacityInput.min = showCapacity ? '2' : '1';
  if (!showCapacity) {
    reqCapacityInput.value = '';
  }
}

if (reqMatchingMode) {
  reqMatchingMode.addEventListener('change', syncHelpRequestCapacityField);
  syncHelpRequestCapacityField();
}

if (reqForm) {
  reqForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(reqForm);

    // Build request body
    const body = Object.fromEntries(fd);
    // Convert lat/lng to numbers if present
    if (body.latitude) body.latitude = parseFloat(body.latitude);
    else delete body.latitude;
    if (body.longitude) body.longitude = parseFloat(body.longitude);
    else delete body.longitude;
    // Convert category to int or remove if empty
    if (body.id_kategoria) body.id_kategoria = parseInt(body.id_kategoria, 10);
    else delete body.id_kategoria;
    if (body.capacity_total) body.capacity_total = parseInt(body.capacity_total, 10);
    else delete body.capacity_total;
    
    delete body.image;

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
      vpStatus('vp-req-status', 'success', 'Kërkesa u krijua me sukses! Kërkesat tuaja do të shfaqen në faqen e kërkesave pas aprovimit.');
    } catch (err) { vpStatus('vp-req-status', 'error', err.message); }
  });
}

async function withdrawHelpRequestApplication(id) {
  if (!confirm('Jeni të sigurt që doni të tërhiqni këtë aplikim?')) return;
  try {
    const res = await fetch(API + '/help_requests.php?action=withdraw_application&id=' + id, {
      method: 'DELETE',
      headers: { 'X-CSRF-Token': csrfToken },
      credentials: 'same-origin'
    });
    const json = await res.json();
    if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
    location.reload();
  } catch (err) {
    alert(err.message || 'Gabim rrjeti.');
  }
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
      const markReadBtn = '<button class="vp-notif-icon-btn vp-notif-icon-btn--read" title="Shëno si të lexuar" aria-label="Shëno si të lexuar" onclick="markNotifRead(' + n.id_njoftimi + ')"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></button>';
      const deleteBtn = '<button class="vp-notif-icon-btn vp-notif-icon-btn--delete" title="Fshi njoftimin" aria-label="Fshi njoftimin" onclick="deleteNotif(' + n.id_njoftimi + ')"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>';
      html += '<div class="vp-notif ' + (unread ? 'vp-notif--unread' : '') + '">'
        + '<div class="vp-notif__dot"></div>'
        + '<div class="vp-notif__body">'
        + (n.linku ? '<a href="' + escapeHtml(n.linku) + '" style="text-decoration:none;color:inherit;">' : '')
        + '<p>' + escapeHtml(n.mesazhi) + '</p>'
        + (n.linku ? '</a>' : '')
        + '<span>' + formatDate(n.krijuar_me) + '</span>'
        + '</div>'
        + '<div class="vp-notif__actions">'
        + (unread ? markReadBtn : '')
        + deleteBtn
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
    const badge = document.getElementById('notif-tab-badge');
    const headerBadge = document.getElementById('notif-badge');
    if (json.success) {
      const count = json.data.unread;
      if (badge) { badge.textContent = ''; badge.style.display = count > 0 ? 'inline-block' : 'none'; }
      if (headerBadge) { headerBadge.textContent = ''; headerBadge.style.display = count > 0 ? 'flex' : 'none'; }
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

const PROFILE_COLOR_THEME = <?= json_encode($profileColorPalette, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function getProfileTheme(colorKey) {
  return PROFILE_COLOR_THEME[colorKey] || PROFILE_COLOR_THEME.emerald;
}

function applyHeaderAvatarProfileColor(colorKey) {
  const theme = getProfileTheme(colorKey);
  if (!theme) return;

  const headerAvatar = document.querySelector('.header-user-avatar');
  if (headerAvatar) {
    headerAvatar.style.setProperty('--avatar-accent', theme.mid || '#00715D');
  }

  const headerFallback = document.querySelector('.header-user-fallback');
  if (headerFallback) {
    headerFallback.style.setProperty('--avatar-from', theme.from || '#003229');
    headerFallback.style.setProperty('--avatar-to', theme.to || '#009e7e');
  }
}

function applyVolunteerProfileTheme(colorKey) {
  const theme = getProfileTheme(colorKey);
  if (!theme) return;

  const hero = document.querySelector('.vp-hero');
  if (hero) {
    hero.style.setProperty('--vp-accent', theme.from || '#003229');
    hero.style.setProperty('--vp-accent-mid', theme.mid || '#00715D');
    hero.style.setProperty('--vp-accent-end', theme.to || '#009e7e');
  }

  const heroFallback = document.querySelector('.vp-hero__avatar--letter');
  if (heroFallback && heroFallback.tagName === 'DIV') {
    heroFallback.style.background = `linear-gradient(135deg, ${theme.from || '#003229'}, ${theme.to || '#009e7e'})`;
  }

  const profileFallback = document.getElementById('vp-profile-avatar-display');
  if (profileFallback && profileFallback.tagName === 'DIV') {
    profileFallback.style.background = `linear-gradient(135deg, ${theme.mid || '#00715D'}, ${theme.to || '#009e7e'})`;
  }

  applyHeaderAvatarProfileColor(colorKey);
}

function initSettingsNavigation() {
  const navItems = document.querySelectorAll('.vp-settings-nav__item');
  const panels = document.querySelectorAll('.vp-settings-panel');
  if (!navItems.length || !panels.length) return;

  navItems.forEach((btn) => {
    btn.addEventListener('click', () => {
      const target = btn.getAttribute('data-settings-target');

      navItems.forEach((item) => {
        item.classList.toggle('active', item === btn);
        item.setAttribute('aria-selected', item === btn ? 'true' : 'false');
      });

      panels.forEach((panel) => {
        const isActive = panel.getAttribute('data-settings-panel') === target;
        panel.classList.toggle('active', isActive);
        panel.hidden = !isActive;
      });
    });
  });
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  initSettingsNavigation();
  loadVPNotifications();
  updateNotifBadge();
  setInterval(updateNotifBadge, 15000);
  initPushSubscription();
  applyVolunteerProfileTheme('<?= htmlspecialchars($profileColorKey) ?>');
});

// ── Web Push Subscription ─────────────────────────────────────────────────────
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  const output = new Uint8Array(raw.length);
  for (let i = 0; i < raw.length; ++i) output[i] = raw.charCodeAt(i);
  return output;
}

async function initPushSubscription() {
  const btn    = document.getElementById('vp-push-btn');
  const status = document.getElementById('vp-push-status');
  if (!btn) return;
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    btn.textContent = 'Nuk mbështetet nga ky browser';
    return;
  }

  const reg = await navigator.serviceWorker.ready;

  async function refreshBtn() {
    const sub = await reg.pushManager.getSubscription();
    if (sub) {
      btn.textContent = 'Çaktivizo njoftimet';
      btn.dataset.state = 'subscribed';
    } else {
      btn.textContent = 'Aktivizo njoftimet';
      btn.dataset.state = 'unsubscribed';
    }
    btn.disabled = false;
  }
  await refreshBtn();

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    const showStatus = (msg, ok = true) => {
      status.textContent = msg;
      status.style.color = ok ? '#16a34a' : '#dc2626';
      status.style.display = 'block';
      setTimeout(() => { status.style.display = 'none'; }, 4000);
    };

    try {
      if (btn.dataset.state === 'subscribed') {
        const sub = await reg.pushManager.getSubscription();
        if (sub) {
          await fetch(API + '/push.php?action=unsubscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin',
            body: JSON.stringify({ endpoint: sub.endpoint }),
          });
          await sub.unsubscribe();
        }
        showStatus('Njoftimet u çaktivizuan.');
      } else {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
          showStatus('Leja u refuzua. Aktivizoni njoftimet nga cilësimet e browser-it.', false);
          btn.disabled = false;
          return;
        }
        const keyRes  = await fetch(API + '/push.php?action=vapid_public_key', { credentials: 'same-origin' });
        const keyJson = await keyRes.json();
        const sub = await reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(keyJson.data.public_key),
        });
        const subObj = sub.toJSON();
        await fetch(API + '/push.php?action=subscribe', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
          credentials: 'same-origin',
          body: JSON.stringify(subObj),
        });
        showStatus('Njoftimet u aktivizuan!');
      }
    } catch (err) {
      showStatus('Gabim: ' + (err.message || err), false);
    }
    await refreshBtn();
  });
}
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/TiranaSolidare/assets/js/map-component.js?v=20260401a"></script>
<script src="/TiranaSolidare/public/assets/scripts/main.js?v=20260401a"></script>
<script>
// Initialize map picker for help request form
document.addEventListener('DOMContentLoaded', function() {
  const mapContainer = document.getElementById('request-map-picker');
  if (mapContainer) {
    const vendInput = document.getElementById('vp-req-location');
    const suggestBox = document.getElementById('request-location-suggestions');
    let autocompleteResults = [];
    let autocompleteAbort = null;
    let autocompleteSeq = 0;
    let geocodeTimeout = null;

    function updateCoordDisplay(lat, lng) {
      const coordDisplay = document.getElementById('req-coord-display');
      const coordText = document.getElementById('req-coord-text');
      if (coordDisplay && coordText) {
        coordDisplay.style.display = 'flex';
        coordText.textContent = Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5);
      }
    }

    function hideSuggestions() {
      if (!suggestBox) return;
      suggestBox.style.display = 'none';
      suggestBox.innerHTML = '';
      autocompleteResults = [];
    }

    const reqMap = TSMap.picker('request-map-picker', {
      latInput: 'req-lat-input',
      lngInput: 'req-lng-input',
      addressInput: 'vp-req-location',
      showSearch: false,
      onSelect: function(lat, lng) {
        updateCoordDisplay(lat, lng);
      }
    });

    if (vendInput && suggestBox && reqMap) {
      suggestBox.addEventListener('mousedown', function(e) {
        e.preventDefault();
      });

      suggestBox.addEventListener('click', function(e) {
        const item = e.target.closest('[data-result-index]');
        if (!item) return;
        const result = autocompleteResults[Number(item.dataset.resultIndex)];
        if (!result) return;

        vendInput.value = result.label;
        hideSuggestions();
        reqMap.setPosition(parseFloat(result.lat), parseFloat(result.lon));
        updateCoordDisplay(parseFloat(result.lat), parseFloat(result.lon));
      });

      vendInput.addEventListener('input', function() {
        clearTimeout(geocodeTimeout);
        const query = this.value.trim();

        if (autocompleteAbort) {
          autocompleteAbort.abort();
          autocompleteAbort = null;
        }

        if (query.length < 3) {
          hideSuggestions();
          return;
        }

        const requestSeq = ++autocompleteSeq;
        geocodeTimeout = setTimeout(async () => {
          autocompleteAbort = new AbortController();
          try {
            const res = await fetch(
              `/TiranaSolidare/api/geocode.php?action=search&q=${encodeURIComponent(query)}`,
              {
                headers: { 'Accept': 'application/json' },
                signal: autocompleteAbort.signal,
              }
            );
            const json = await res.json();
            const data = json.success ? (json.data.results || []) : [];
            if (requestSeq !== autocompleteSeq) return;
            if (!Array.isArray(data) || data.length === 0) {
              hideSuggestions();
              return;
            }

            autocompleteResults = data.map(function(item) {
              return {
                lat: item.lat,
                lon: item.lon,
                label: item.short_name || item.display_name || ''
              };
            });

            suggestBox.innerHTML = '';
            autocompleteResults.forEach(function(result, index) {
              const row = document.createElement('div');
              row.dataset.resultIndex = String(index);
              row.style.padding = '10px 14px';
              row.style.cursor = 'pointer';
              row.style.borderBottom = '1px solid #f1f5f9';
              row.style.fontSize = '0.84rem';
              row.style.color = '#334155';
              row.style.transition = 'background .15s';
              row.textContent = result.label;
              row.addEventListener('mouseenter', function() { row.style.background = '#f8fafc'; });
              row.addEventListener('mouseleave', function() { row.style.background = ''; });
              suggestBox.appendChild(row);
            });
            suggestBox.style.display = 'block';
          } catch (e) {
            if (e.name !== 'AbortError') hideSuggestions();
          } finally {
            autocompleteAbort = null;
          }
        }, 90);
      });

      vendInput.addEventListener('focus', function() {
        if (this.value.trim().length >= 3) {
          this.dispatchEvent(new Event('input'));
        }
      });

      vendInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') hideSuggestions();
      });

      vendInput.addEventListener('blur', function() {
        setTimeout(hideSuggestions, 180);
      });
    }
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

// ── Color Dropdown Initialization ──
function initColorDropdown() {
  const trigger = document.getElementById('vp-color-trigger');
  const menu = document.getElementById('vp-color-menu');
  const hiddenInput = document.getElementById('vp-profile-color');
  const swatchTrigger = document.getElementById('vp-color-swatch');
  const labelTrigger = document.querySelector('.vp-color-dropdown__label');
  const options = document.querySelectorAll('.vp-color-dropdown__option');

  if (!trigger || !menu) return;

  // Toggle menu visibility
  trigger.addEventListener('click', () => {
    const isOpen = !menu.hidden;
    menu.hidden = isOpen;
    trigger.setAttribute('aria-expanded', !isOpen);
  });

  // Handle color selection
  options.forEach(option => {
    option.addEventListener('click', (e) => {
      e.preventDefault();
      const colorKey = option.getAttribute('data-color');
      const colorLabel = option.getAttribute('aria-label');
      const colorGradient = option.querySelector('.vp-color-dropdown__color').style.background;
      const colorMid = option.getAttribute('data-color-mid');

      // Update hidden input
      hiddenInput.value = colorKey;

      // Update trigger button
      swatchTrigger.style.backgroundColor = getComputedStyle(option.querySelector('.vp-color-dropdown__color')).backgroundColor;
      if (labelTrigger) labelTrigger.textContent = colorLabel;

      // Update active state
      options.forEach(opt => opt.classList.remove('active'));
      option.classList.add('active');

      applyVolunteerProfileTheme(colorKey);

      // Close menu
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    });
  });

  // Close menu when clicking outside
  document.addEventListener('click', (e) => {
    if (!trigger.contains(e.target) && !menu.contains(e.target)) {
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }
  });

  // Close menu on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !menu.hidden) {
      menu.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }
  });
}

// Initialize color dropdown when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initColorDropdown);
} else {
  initColorDropdown();
}

// ── Profili publik (Bio + Ngjyra) ──
const profileForm = document.getElementById('vp-profile-form');
if (profileForm) {
  profileForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const bio = document.getElementById('vp-bio').value.trim();
    const profile_color = document.getElementById('vp-profile-color').value;

    if (bio.length > 500) {
      vpStatus('vp-profile-form-status', 'error', 'Bio nuk mund të kalojë 500 karaktere.');
      return;
    }

    try {
      const res = await fetch(API + '/users.php?action=update_profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({ bio, profile_color }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      applyVolunteerProfileTheme(profile_color);
      vpStatus('vp-profile-form-status', 'success', 'Profili publik u përditësua me sukses.');
    } catch (err) {
      vpStatus('vp-profile-form-status', 'error', err.message);
    }
  });
}

// ── Dukshmëria e profilit (vetëm publik/privat) ──
const visibilityForm = document.getElementById('vp-visibility-form');
if (visibilityForm) {
  visibilityForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const profile_public = document.getElementById('vp-public').checked ? 1 : 0;

    try {
      const res = await fetch(API + '/users.php?action=update_profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({ profile_public }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      vpStatus('vp-visibility-status', 'success', 'Dukshmëria e profilit u përditësua me sukses.');
    } catch (err) {
      vpStatus('vp-visibility-status', 'error', err.message);
    }
  });
}

// ── Notifications form ──
const notifForm = document.getElementById('vp-notifications-form');
if (notifForm) {
  notifForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const email_notifications = document.getElementById('vp-email-notif').checked ? 1 : 0;
    try {
      const res = await fetch(API + '/users.php?action=update_profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({ email_notifications }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) throw new Error(json.message || 'Gabim.');
      vpStatus('vp-notifications-status', 'success', 'Preferencat e njoftimeve u ruajtën.');
    } catch (err) {
      vpStatus('vp-notifications-status', 'error', err.message);
    }
  });
}

const avatarClickTarget = document.getElementById('vp-avatar-click-target');
const avatarUploadInput = document.getElementById('vp-avatar-upload-input');
const avatarDeleteBtn = document.getElementById('vp-avatar-delete-btn');

if (avatarClickTarget && avatarUploadInput) {
  avatarClickTarget.addEventListener('click', () => {
    avatarUploadInput.click();
  });

  avatarUploadInput.addEventListener('change', async () => {
    const file = avatarUploadInput.files?.[0];
    if (!file) return;

    if (file.size > 6 * 1024 * 1024) {
      vpStatus('vp-avatar-status', 'error', 'Foto duhet të jetë më e vogël se 6MB.');
      avatarUploadInput.value = '';
      return;
    }

    try {
      const uploadForm = new FormData();
      uploadForm.append('image', file);

      const uploadRes = await fetch(API + '/users.php?action=upload_profile_picture', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: uploadForm,
      });
      const uploadJson = await uploadRes.json();
      if (!uploadRes.ok || !uploadJson.success) throw new Error(uploadJson.message || 'Ngarkimi i fotos dështoi.');

      const profile_picture = uploadJson.data?.url || '';
      if (!profile_picture) throw new Error('URL e fotos nuk u kthye nga serveri.');

      const saveRes = await fetch(API + '/users.php?action=update_profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
        body: JSON.stringify({ profile_picture }),
      });
      const saveJson = await saveRes.json();
      if (!saveRes.ok || !saveJson.success) throw new Error(saveJson.message || 'Gabim gjatë ruajtjes së fotos.');

      const currentPictureInput = document.getElementById('vp-current-picture');
      if (currentPictureInput) currentPictureInput.value = profile_picture;

      const profileAvatar = document.getElementById('vp-profile-avatar-display');
      if (profileAvatar) {
        profileAvatar.outerHTML = '<img id="vp-profile-avatar-display" src="' + escapeHtml(profile_picture) + '" alt="Avatar" class="vp-avatar vp-avatar--img">';
      }

      const heroAvatar = document.querySelector('.vp-hero__avatar');
      if (heroAvatar) {
        heroAvatar.outerHTML = '<img src="' + escapeHtml(profile_picture) + '" alt="Avatar" class="vp-hero__avatar vp-hero__avatar--img">';
      }

      const headerAvatarImg = document.querySelector('.header-user-avatar > img');
      const headerAvatarFallback = document.querySelector('.header-user-fallback');
      const headerAvatarButton = document.querySelector('.header-user-avatar');
      if (headerAvatarImg) {
        headerAvatarImg.setAttribute('src', profile_picture);
        headerAvatarImg.style.display = '';
      }
      if (!headerAvatarImg && headerAvatarButton) {
        headerAvatarButton.insertAdjacentHTML('afterbegin', '<img src="' + escapeHtml(profile_picture) + '" alt="Avatar">');
      }
      if (headerAvatarFallback) headerAvatarFallback.style.display = 'none';
      if (headerAvatarButton) headerAvatarButton.classList.remove('has-fallback');

      // Show delete button if it doesn't exist
      if (!document.getElementById('vp-avatar-delete-btn') && avatarClickTarget) {
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'vp-avatar-delete-btn__hint';
        deleteBtn.id = 'vp-avatar-delete-btn';
        deleteBtn.setAttribute('aria-label', 'Fshi foton e profilit');
        deleteBtn.setAttribute('title', 'Fshi foton e profilit');
        deleteBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>';
        avatarClickTarget.parentElement.appendChild(deleteBtn);
        initDeleteButton();
      }

      vpStatus('vp-avatar-status', 'success', 'Fotoja e profilit u përditësua me sukses.');
    } catch (err) {
      vpStatus('vp-avatar-status', 'error', err.message || 'Gabim gjatë përditësimit të fotos.');
    } finally {
      avatarUploadInput.value = '';
    }
  });
}

// ── Delete Profile Picture Handler ──
function initDeleteButton() {
  const deleteBtnElement = document.getElementById('vp-avatar-delete-btn');
  if (!deleteBtnElement) return;

  deleteBtnElement.addEventListener('click', async (e) => {
    e.stopPropagation();
    if (!confirm('Jeni i sigurt që doni të fshini foton e profilit?')) return;

    try {
      const deleteRes = await fetch(API + '/users.php?action=delete_profile_picture', {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': csrfToken },
        credentials: 'same-origin',
      });
      const deleteJson = await deleteRes.json();
      if (!deleteRes.ok || !deleteJson.success) throw new Error(deleteJson.message || 'Gabim gjatë fshirjes së fotos.');

      const currentPictureInput = document.getElementById('vp-current-picture');
      if (currentPictureInput) currentPictureInput.value = '';

      // Keep the fallback avatar aligned with the currently selected profile color.
      const selectedColorKey = document.getElementById('vp-profile-color')?.value || Object.keys(PROFILE_COLOR_THEME)[0];
      const colorTheme = PROFILE_COLOR_THEME[selectedColorKey] || PROFILE_COLOR_THEME[Object.keys(PROFILE_COLOR_THEME)[0]];
      const fallbackGradient = colorTheme ? `linear-gradient(135deg, ${colorTheme.mid}, ${colorTheme.to})` : 'linear-gradient(135deg, #00715D, #00a88a)';
      const initialSource = document.querySelector('.header-user-name')?.textContent || document.getElementById('vp-profile-emri')?.textContent || 'P';
      const initial = initialSource.trim().charAt(0).toUpperCase() || 'P';

      const profileAvatar = document.getElementById('vp-profile-avatar-display');
      if (profileAvatar) {
        profileAvatar.outerHTML = '<div id="vp-profile-avatar-display" class="vp-avatar" style="background:' + fallbackGradient + '">' + initial + '</div>';
      }

      const heroAvatar = document.querySelector('.vp-hero__avatar');
      if (heroAvatar) {
        heroAvatar.outerHTML = '<div class="vp-hero__avatar vp-hero__avatar--letter" style="background:' + fallbackGradient + '">' + initial + '</div>';
      }

      const headerAvatarImg = document.querySelector('.header-user-avatar > img');
      const headerAvatarFallback = document.querySelector('.header-user-fallback');
      const headerAvatarButton = document.querySelector('.header-user-avatar');
      if (headerAvatarImg) {
        headerAvatarImg.remove();
      }
      if (headerAvatarFallback) {
        headerAvatarFallback.style.display = '';
      }
      if (headerAvatarButton) {
        headerAvatarButton.classList.add('has-fallback');
      }

      // Remove delete button
      deleteBtnElement.remove();

      vpStatus('vp-avatar-status', 'success', 'Fotoja e profilit u fshi me sukses.');
    } catch (err) {
      vpStatus('vp-avatar-status', 'error', err.message || 'Gabim gjatë fshirjes së fotos.');
    }
  });
}

// Initialize delete button if it exists on page load
if (avatarDeleteBtn) {
  initDeleteButton();
}

// Initialize delete button if it exists on page load
if (avatarDeleteBtn) {
  initDeleteButton();
}

</script>
<?php if ($tab === 'messages'): ?>
<script>
(function() {
  const CURRENT_USER_ID = <?= (int) $_SESSION['user_id'] ?>;
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  function getCSRF() { return csrfMeta?.content || ''; }

  async function vpApiCall(url, opts = {}) {
    const base = '/TiranaSolidare/api/';
    const headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': getCSRF(), ...(opts.headers || {}) };
    const res = await fetch(base + url, { credentials: 'same-origin', ...opts, headers });
    const json = await res.json();
    if (json.csrf_token && csrfMeta) csrfMeta.content = json.csrf_token;
    return json;
  }

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function fmtDate(d) { if (!d) return ''; const dt = new Date(d); return dt.toLocaleDateString('sq-AL', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' }); }

  let currentThread = null;
  let pollTimer = null;
  const vpContactPage = '/TiranaSolidare/views/contact.php';

  function vpMessagePolicyNotice(message) {
    return '<div style="padding:12px 14px;border:1px solid #dbe7e2;border-radius:12px;background:#f5fbf8;color:#33534b;font-size:0.92rem;line-height:1.55;">'
      + esc(message)
      + ' <a href="' + vpContactPage + '" style="color:var(--db-primary,#00715D);font-weight:600;">Faqja e kontaktit</a>.</div>';
  }

  window.vpLoadConversations = async function() {
    const container = document.getElementById('vp-msg-content');
    const title = document.getElementById('vp-msg-title');
    const actions = document.getElementById('vp-msg-actions');
    if (!container) return;
    if (title) title.textContent = 'Mesazhet';
    if (actions) actions.style.display = '';
    currentThread = null;
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }

    try {
      const json = await vpApiCall('messages.php?action=conversations');
      if (!json.success) { container.innerHTML = '<div class="vp-loading">Gabim.</div>'; return; }

      // API returns { conversations: [...], total_unread: N }
      const convos = (json.data && json.data.conversations) ? json.data.conversations : [];

      if (!convos.length) {
        container.innerHTML = '<div style="text-align:center;padding:2rem;color:#94a3b8;"><p>Nuk keni biseda ende.</p><p style="font-size:0.85rem;">Klikoni "Mesazh i ri" për të filluar.</p></div>';
        return;
      }

      let html = '<div class="msg-conversation-list">';
      convos.forEach(c => {
        const unread = c.unread_count > 0 ? '<span class="vp-tab-badge" style="display:inline-flex;margin-left:auto;">' + c.unread_count + '</span>' : '';
        const init = (c.other_emri || 'P').charAt(0).toUpperCase();
        const preview = c.last_message ? esc(c.last_message.substring(0, 60)) + (c.last_message.length > 60 ? '…' : '') : '';
        html += '<div class="msg-convo-item' + (c.unread_count > 0 ? ' msg-convo-item--unread' : '') + '" onclick="vpOpenThread(' + c.other_id + ',\'' + esc(c.other_emri).replace(/'/g, "\\'") + '\')">'
          + '<div style="width:40px;height:40px;border-radius:50%;background:var(--db-primary,#00715D);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">' + esc(init) + '</div>'
          + '<div class="msg-convo-info"><div class="msg-convo-name">' + esc(c.other_emri) + ' ' + unread + '</div><div class="msg-convo-preview">' + preview + '</div></div>'
          + '<div class="msg-convo-time">' + fmtDate(c.last_time) + '</div></div>';
      });
      html += '</div>';
      container.innerHTML = html;
      vpLoadUnreadBadge();
      return convos; // expose for deep-link caller
    } catch (e) { container.innerHTML = '<div class="vp-loading">Gabim rrjeti.</div>'; }
  };

  window.vpOpenThread = async function(userId, userName) {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    currentThread = userId;
    const container = document.getElementById('vp-msg-content');
    const title = document.getElementById('vp-msg-title');
    const actions = document.getElementById('vp-msg-actions');
 // Titulli pa buton fillimisht
if (title) title.innerHTML = '<a href="?tab=messages" style="margin-right:8px;color:inherit;">← </a> ' + esc(userName);
if (actions) actions.style.display = 'none';

    try {
      const json = await vpApiCall('messages.php?action=thread&with=' + userId + '&limit=50');
      if (!json.success) { container.innerHTML = vpMessagePolicyNotice(json.message || 'Gabim.'); return; }

      const messages = json.data.messages;
      // Shto butonin vetëm nëse ka mesazhe
if (title && messages.length > 0) {
    title.innerHTML += `<button onclick="vpDeleteConversation(${userId}, '${userName.replace(/'/g, "\\'")}')" 
        style="margin-left:12px;font-size:0.76rem;padding:4px 10px;border-radius:8px;border:1.5px solid #ef4444;background:#fff1f2;color:#dc2626;cursor:pointer;">
        Fshi bisedën
      </button>`;
}
      let html = '<div class="msg-thread" id="vp-msg-thread">';
      if (!messages.length) html += '<div style="text-align:center;color:#94a3b8;padding:2rem;">Shkruaj mesazhin e parë!</div>';
      messages.forEach(m => {
        const isMine = m.derguesi_id == CURRENT_USER_ID;
        html += '<div class="msg-bubble ' + (isMine ? 'msg-bubble--mine' : 'msg-bubble--theirs') + '">'
          + '<div class="msg-bubble__text">' + esc(m.mesazhi) + '</div>'
          + '<div class="msg-bubble__time">' + fmtDate(m.krijuar_me) + '</div></div>';
      });
html += '</div>'
  + '<div id="vp-msg-block-bar" style="padding:4px 0 8px;"></div>'
  + '<div class="msg-compose" id="vp-msg-compose">'
  + '<textarea id="vp-msg-input" class="msg-compose__input" placeholder="Shkruaj mesazhin…" rows="2" maxlength="2000" onkeydown="if(event.key===\'Enter\'&&!event.shiftKey){event.preventDefault();vpSendMessage(' + userId + ');}"></textarea>'
  + '<button class="vp-notif-toolbar-btn" onclick="vpSendMessage(' + userId + ')" style="padding:10px 14px;background:var(--db-primary,#00715D);color:#fff;border-radius:12px;border:none;cursor:pointer;">'
  + '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></button>'
  + '</div>';
      container.innerHTML = html;
      vpCheckAndRenderBlockBtn(userId, userName);
      const thread = document.getElementById('vp-msg-thread');
      if (thread) setTimeout(() => { thread.scrollTop = thread.scrollHeight; }, 50);
      pollTimer = setInterval(() => vpRefreshThread(userId), 5000);
      vpLoadUnreadBadge();
    } catch (e) { container.innerHTML = '<div class="vp-loading">Gabim rrjeti.</div>'; }
  };

  window.vpSendMessage = async function(toUserId) {
    const input = document.getElementById('vp-msg-input');
    const text = (input?.value || '').trim();
    if (!text) return;
    input.value = '';
    input.focus();
    try {
      const json = await vpApiCall('messages.php?action=send', { method: 'POST', body: JSON.stringify({ marruesi_id: toUserId, mesazhi: text }) });
      if (!json.success) {
        input.value = text;
        const blockBar = document.getElementById('vp-msg-block-bar');
        if (blockBar) blockBar.innerHTML = vpMessagePolicyNotice(json.message || 'Gabim gjatë dërgimit të mesazhit.');
        return;
      }
      vpRefreshThread(toUserId);
    } catch (e) {}
  };

  window.vpRefreshThread = async function(userId) {
    const thread = document.getElementById('vp-msg-thread');
    if (!thread) return;
    try {
      const json = await vpApiCall('messages.php?action=thread&with=' + userId + '&limit=50');
      if (!json.success) return;
      const messages = json.data.messages;
      let html = '';
      if (!messages.length) html = '<div style="text-align:center;color:#94a3b8;padding:2rem;">Shkruaj mesazhin e parë!</div>';
      messages.forEach(m => {
        const isMine = m.derguesi_id == CURRENT_USER_ID;
        html += '<div class="msg-bubble ' + (isMine ? 'msg-bubble--mine' : 'msg-bubble--theirs') + '">'
          + '<div class="msg-bubble__text">' + esc(m.mesazhi) + '</div>'
          + '<div class="msg-bubble__time">' + fmtDate(m.krijuar_me) + '</div></div>';
      });
      thread.innerHTML = html;
      setTimeout(() => { thread.scrollTop = thread.scrollHeight; }, 50);
      vpLoadUnreadBadge();
    } catch (e) {}
  };

  window.vpShowNewConversation = function() {
    const container = document.getElementById('vp-msg-content');
    const title = document.getElementById('vp-msg-title');
    const actions = document.getElementById('vp-msg-actions');
    if (title) title.innerHTML = '<a href="?tab=messages" style="margin-right:8px;color:inherit;">← </a> Mesazh i ri';
    if (actions) actions.style.display = 'none';
    container.innerHTML = '<div style="padding:1rem;"><p style="margin:0 0 12px;color:#5b6b79;font-size:0.92rem;line-height:1.55;">Administratorët nuk shfaqen në këtë kërkim. Për Bashkinë ose ekipin përdorni <a href="' + vpContactPage + '" style="color:var(--db-primary,#00715D);font-weight:600;">faqen e kontaktit</a>.</p><input type="text" id="vp-msg-search" class="vp-input" placeholder="Kërko përdorues sipas emrit…" oninput="vpSearchUsers(this.value)" style="margin-bottom:1rem;width:100%;padding:10px 14px;border:1.5px solid #e4e8ee;border-radius:10px;"><div id="vp-msg-search-results"></div></div>';
  };

  let searchTimer = null;
  window.vpSearchUsers = function(q) {
    clearTimeout(searchTimer);
    const results = document.getElementById('vp-msg-search-results');
    if (!results) return;
    if (q.trim().length < 2) { results.innerHTML = '<div style="color:#94a3b8;font-size:0.85rem;">Shkruaj të paktën 2 shkronja…</div>'; return; }
    searchTimer = setTimeout(async () => {
      try {
        const json = await vpApiCall('messages.php?action=search_users&q=' + encodeURIComponent(q.trim()));
        if (!json.success || !json.data.users || !json.data.users.length) { results.innerHTML = '<div style="color:#94a3b8;">Asnjë rezultat.</div>'; return; }
        let html = '';
        json.data.users.forEach(u => {
          const init = (u.emri || 'P').charAt(0).toUpperCase();
          html += '<div class="msg-convo-item" onclick="vpOpenThread(' + u.id_perdoruesi + ',\'' + esc(u.emri).replace(/'/g, "\\'") + '\')">'
            + '<div style="width:36px;height:36px;border-radius:50%;background:var(--db-primary,#00715D);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;">' + esc(init) + '</div>'
            + '<div class="msg-convo-info"><div class="msg-convo-name">' + esc(u.emri) + '</div></div></div>';
        });
        results.innerHTML = html;
      } catch (e) { results.innerHTML = '<div style="color:#dc3545;">Gabim rrjeti.</div>'; }
    }, 300);
  };

  window.vpLoadUnreadBadge = async function() {
    try {
      const json = await vpApiCall('messages.php?action=conversations');
      if (!json.success) return;
      const total = (json.data.conversations || []).reduce((sum, c) => sum + (c.unread_count || 0), 0);
      const badge = document.getElementById('msg-tab-badge');
      if (badge) {
        if (total > 0) { badge.textContent = total; badge.style.display = 'inline-flex'; }
        else { badge.style.display = 'none'; }
      }
    } catch (e) {}
  };

  // Auto-load on page ready — if ?with=<userId> is in the URL (from notification deep-link),
  // open that thread directly after loading the conversation list.
  document.addEventListener('DOMContentLoaded', async function() {
    const openWithId = <?= (int) ($_GET['with'] ?? 0) ?>;
    if (openWithId > 0) {
      try {
        const convos = await vpLoadConversations() || [];
        const conv = convos.find(c => c.other_id == openWithId);
        vpOpenThread(openWithId, conv ? conv.other_emri : 'Bisedë');
      } catch (e) {
        vpOpenThread(openWithId, 'Bisedë');
      }
    } else {
      vpLoadConversations();
    }
  });
})();

async function vpCheckAndRenderBlockBtn(userId, userName) {
    const blockBar = document.getElementById('vp-msg-block-bar');
    const compose  = document.getElementById('vp-msg-compose');
    if (!blockBar) return;

    try {
        const res  = await fetch(`${API}/messages.php?action=is_blocked&user_id=${userId}`, {
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (!json.success) return;

        const iBlockedThem  = json.data.i_blocked_them;
        const theyBlockedMe = json.data.they_blocked_me;
        const canBlock = json.data.can_block !== false;

        // Nëse tjetri është admin, mos shfaq butonin dhe lejo compose
        if (!canBlock) {
            if (compose) compose.style.display = '';
            blockBar.innerHTML = '';
            return;
        }

        blockBar.innerHTML = `
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;padding:4px 0;">
                <button onclick="${iBlockedThem ? `vpUnblockUser(${userId},'${userName.replace(/'/g,"\\'")}')` : `vpBlockUser(${userId},'${userName.replace(/'/g,"\\'")}')` }"
                    style="font-size:0.76rem;padding:4px 11px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:5px;
                           border:1.5px solid ${iBlockedThem ? '#10b981' : '#ef4444'};
                           background:${iBlockedThem ? '#f0fdf4' : '#fff1f2'};
                           color:${iBlockedThem ? '#059669' : '#dc2626'};">
                    ${iBlockedThem
                        ? '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" x2="6" y1="1" y2="4"/><line x1="10" x2="10" y1="1" y2="4"/><line x1="14" x2="14" y1="1" y2="4"/></svg> Zhblloko'
                        : '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg> Blloko'}
                </button>
            </div>
            ${theyBlockedMe && !iBlockedThem
                ? '<p style="text-align:center;font-size:0.8rem;color:#94a3b8;margin:4px 0 8px;">Ky përdorues nuk pranon mesazhe.</p>'
                : iBlockedThem
                ? '<p style="text-align:center;font-size:0.8rem;color:#94a3b8;margin:4px 0 8px;">Ke bllokuar këtë përdorues.</p>'
                : ''}`;

        if (compose) {
            compose.style.display = (iBlockedThem || theyBlockedMe) ? 'none' : '';
        }
    } catch(e) {}
}

async function vpBlockUser(userId, userName) {
    if (!confirm(`Blloko ${userName}?\nNuk do të marrësh dhe nuk do të mund të dërgosh mesazhe.`)) return;
    try {
        const res  = await fetch(`${API}/messages.php?action=block_user`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin',
            body: JSON.stringify({ blocked_id: userId })
        });
        const json = await res.json();
        if (json.success) vpCheckAndRenderBlockBtn(userId, userName);
    } catch(e) {}
}

async function vpUnblockUser(userId, userName) {
    if (!confirm(`Zhblloko ${userName}?`)) return;
    try {
        const res  = await fetch(`${API}/messages.php?action=unblock_user`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin',
            body: JSON.stringify({ blocked_id: userId })
        });
        const json = await res.json();
        if (json.success) vpCheckAndRenderBlockBtn(userId, userName);
    } catch(e) {}
}

async function vpDeleteConversation(userId, userName) {
    if (!confirm(`Fshi bisedën me ${userName}?\nBiseda do të fshihet vetëm për ty.`)) return;
    try {
        const res = await fetch(`${API}/messages.php?action=delete_conversation&with=${userId}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': csrfToken },
            credentials: 'same-origin'
        });
        const json = await res.json();
        if (json.success) {
            // Kthehu te lista e bisedave
            vpLoadConversations();
            const title = document.getElementById('vp-msg-title');
            const actions = document.getElementById('vp-msg-actions');
            if (title) title.textContent = 'Mesazhet';
            if (actions) actions.style.display = '';
        } else {
            alert(json.message || 'Gabim gjatë fshirjes.');
        }
    } catch(e) {
        alert('Gabim rrjeti.');
    }
}
</script>
<?php endif; ?>
</body>
</html>


