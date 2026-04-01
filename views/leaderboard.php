<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isLoggedIn = isset($_SESSION['user_id']);

// Fetch top 20 volunteers by score
$stmt = $pdo->query(
    "SELECT
        p.id_perdoruesi,
        p.emri,
        p.profile_color,
        p.krijuar_me AS anetaresuar_me,
        COALESCE(apps.total_apps, 0) AS total_apps,
        COALESCE(apps.accepted_apps, 0) AS accepted_apps,
        COALESCE(reqs.total_requests, 0) AS total_requests,
        (COALESCE(apps.accepted_apps, 0) * 5
         + COALESCE(apps.total_apps, 0) * 1
         + COALESCE(reqs.total_requests, 0) * 2) AS score
     FROM Perdoruesi p
     LEFT JOIN (
        SELECT id_perdoruesi,
               COUNT(*) AS total_apps,
               SUM(CASE WHEN statusi = 'approved' THEN 1 ELSE 0 END) AS accepted_apps
        FROM Aplikimi
        GROUP BY id_perdoruesi
     ) apps ON apps.id_perdoruesi = p.id_perdoruesi
     LEFT JOIN (
        SELECT id_perdoruesi, COUNT(*) AS total_requests
        FROM Kerkesa_per_Ndihme
        GROUP BY id_perdoruesi
     ) reqs ON reqs.id_perdoruesi = p.id_perdoruesi
     WHERE p.roli = 'volunteer'
       AND p.statusi_llogarise = 'active'
       AND p.verified = 1
     ORDER BY score DESC, p.emri ASC
     LIMIT 20"
);
$leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-fetch badges for all leaderboard users
$badgeMap = [];
foreach ($leaderboard as $entry) {
    $result = ts_get_user_profile_badges($pdo, (int) $entry['id_perdoruesi']);
    $badgeMap[$entry['id_perdoruesi']] = $result['earned'] ?? [];
}

$badgeIconMap = [
    'seedling' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2z"/></svg>',
    'calendar-check' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>',
    'hands-helping' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 12h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 14"/><path d="m7 18 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 13 6 6"/></svg>',
    'megaphone' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
    'heart-handshake' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="m12 5 3 3-3 3-3-3 3-3z"/></svg>',
    'shield' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/></svg>',
    'sparkles' => '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?= csrf_meta() ?>
  <title>Renditja e Vullnetarëve — Tirana Solidare</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bitter:wght@400;600;700&family=Raleway:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">
  <style>
    .lb-page { min-height: 100vh; background: linear-gradient(160deg, #f4f8f6 0%, #e8f0ec 50%, #f0f5f3 100%); }
    .lb-container { max-width: 800px; margin: 0 auto; padding: 40px 20px 80px; }

    .lb-hero { text-align: center; margin-bottom: 48px; }
    .lb-hero__badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(0,113,93,0.08); color: #00715D; font-size: 0.82rem; font-weight: 600; padding: 6px 14px; border-radius: 20px; margin-bottom: 16px; }
    .lb-hero h1 { font-family: 'Bitter', serif; font-size: 2.2rem; font-weight: 700; color: #003229; margin: 0 0 12px; }
    .lb-hero p { font-family: 'Raleway', sans-serif; color: #64748b; font-size: 1rem; max-width: 500px; margin: 0 auto; }

    .lb-podium { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 40px; align-items: end; }
    .lb-podium__item { text-align: center; background: #fff; border-radius: 18px; padding: 28px 16px 24px; border: 1px solid #e8ede9; box-shadow: 0 4px 20px rgba(0,44,37,0.06); position: relative; transition: transform 0.2s ease; }
    .lb-podium__item:hover { transform: translateY(-4px); }
    .lb-podium__item--1 { order: 2; padding-top: 36px; border-color: #FFD700; box-shadow: 0 8px 30px rgba(255,215,0,0.15); }
    .lb-podium__item--2 { order: 1; }
    .lb-podium__item--3 { order: 3; }
    .lb-podium__rank { position: absolute; top: -14px; left: 50%; transform: translateX(-50%); width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: #fff; }
    .lb-podium__item--1 .lb-podium__rank { background: linear-gradient(135deg, #FFD700, #FFA500); width: 36px; height: 36px; font-size: 0.95rem; }
    .lb-podium__item--2 .lb-podium__rank { background: linear-gradient(135deg, #C0C0C0, #9ca3af); }
    .lb-podium__item--3 .lb-podium__rank { background: linear-gradient(135deg, #CD7F32, #a0522d); }
    .lb-podium__avatar { width: 56px; height: 56px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.3rem; color: #fff; margin: 0 auto 12px; }
    .lb-podium__item--1 .lb-podium__avatar { width: 68px; height: 68px; font-size: 1.5rem; }
    .lb-podium__name { font-family: 'Bitter', serif; font-weight: 600; color: #003229; font-size: 0.95rem; margin-bottom: 4px; text-decoration: none; display: block; }
    .lb-podium__name:hover { color: #00715D; text-decoration: underline; }
    .lb-podium__avatar-link { display: block; text-decoration: none; }
    .lb-podium__avatar-link:hover .lb-podium__avatar { outline: 3px solid rgba(0,113,93,0.5); outline-offset: 2px; }
    .lb-podium__score { font-family: 'Raleway', sans-serif; font-size: 1.3rem; font-weight: 700; color: #00715D; }
    .lb-podium__score small { font-size: 0.7rem; font-weight: 500; color: #64748b; }
    .lb-podium__badges { display: flex; justify-content: center; gap: 4px; margin-top: 8px; flex-wrap: wrap; }
    .lb-podium__badges svg { color: #00715D; opacity: 0.7; }

    .lb-list { display: flex; flex-direction: column; gap: 10px; }
    .lb-row { display: flex; align-items: center; gap: 16px; background: #fff; border-radius: 14px; padding: 16px 20px; border: 1px solid #e8ede9; box-shadow: 0 2px 10px rgba(0,44,37,0.04); transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .lb-row:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,44,37,0.08); }
    /* When the whole row is a link */
    a.lb-row { text-decoration: none; cursor: pointer; }
    a.lb-row .lb-row__name { color: #003229; }
    a.lb-row:hover .lb-row__name { color: #00715D; text-decoration: underline; }
    .lb-row__rank { font-family: 'Bitter', serif; font-weight: 700; color: #94a3b8; font-size: 1.1rem; min-width: 32px; text-align: center; }
    .lb-row__avatar { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; font-size: 1rem; flex-shrink: 0; }
    .lb-row__info { flex: 1; min-width: 0; }
    .lb-row__name { font-family: 'Bitter', serif; font-weight: 600; color: #003229; font-size: 0.92rem; }
    .lb-row__badges { display: flex; gap: 4px; margin-top: 3px; flex-wrap: wrap; }
    .lb-row__badges svg { color: #64748b; opacity: 0.6; }
    .lb-row__score { font-family: 'Raleway', sans-serif; font-weight: 700; color: #00715D; font-size: 1.1rem; white-space: nowrap; }
    .lb-row__score small { font-size: 0.7rem; font-weight: 500; color: #64748b; }

    .lb-empty { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .lb-empty svg { margin-bottom: 16px; opacity: 0.3; }

    @media (max-width: 640px) {
      .lb-hero h1 { font-size: 1.6rem; }
      .lb-podium { grid-template-columns: 1fr; gap: 12px; }
      .lb-podium__item--1, .lb-podium__item--2, .lb-podium__item--3 { order: unset; }
      .lb-row { padding: 12px 14px; gap: 10px; }
    }
  </style>
</head>
<body class="lb-page">
<?php include __DIR__ . '/../public/components/header.php'; ?>

<div class="lb-container">
  <div class="lb-hero">
    <div class="lb-hero__badge">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
      Renditja e Vullnetarëve
    </div>
    <h1>Top Vullnetarët</h1>
    <p>Vullnetarët më aktivë të komunitetit Tirana Solidare, renditur sipas pikëve.</p>
  </div>

  <?php if (empty($leaderboard)): ?>
    <div class="lb-empty">
      <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <p>Nuk ka vullnetarë të renditur ende.</p>
    </div>
  <?php else: ?>

    <!-- Podium: Top 3 -->
    <?php if (count($leaderboard) >= 3): ?>
    <div class="lb-podium">
      <?php foreach (array_slice($leaderboard, 0, 3) as $rank => $v):
        $pos = $rank + 1;
        $colorResolved = ts_resolve_profile_color($v['profile_color'] ?? 'emerald');
        $initial = mb_strtoupper(mb_substr($v['emri'] ?? 'V', 0, 1));
        $userBadges = $badgeMap[$v['id_perdoruesi']] ?? [];
      ?>
      <div class="lb-podium__item lb-podium__item--<?= $pos ?>">
        <div class="lb-podium__rank"><?= $pos ?></div>
        <a href="/TiranaSolidare/views/public_profile.php?id=<?= (int)$v['id_perdoruesi'] ?>" class="lb-podium__avatar-link" title="Shiko profilin e <?= htmlspecialchars($v['emri']) ?>">
          <div class="lb-podium__avatar" style="background: linear-gradient(135deg, <?= htmlspecialchars($colorResolved['theme']['from']) ?>, <?= htmlspecialchars($colorResolved['theme']['to']) ?>);">
            <?= htmlspecialchars($initial) ?>
          </div>
        </a>
        <a href="/TiranaSolidare/views/public_profile.php?id=<?= (int)$v['id_perdoruesi'] ?>" class="lb-podium__name"><?= htmlspecialchars($v['emri']) ?></a>
        <div class="lb-podium__score"><?= (int) $v['score'] ?> <small>pikë</small></div>
        <?php if (!empty($userBadges)): ?>
        <div class="lb-podium__badges">
          <?php foreach ($userBadges as $b): ?>
            <span title="<?= htmlspecialchars($b['name']) ?>"><?= $badgeIconMap[$b['icon']] ?? '' ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Rest of the list -->
    <?php $startIdx = count($leaderboard) >= 3 ? 3 : 0; ?>
    <?php if ($startIdx < count($leaderboard)): ?>
    <div class="lb-list">
      <?php foreach (array_slice($leaderboard, $startIdx) as $rank => $v):
        $pos = $startIdx + $rank + 1;
        $colorResolved = ts_resolve_profile_color($v['profile_color'] ?? 'emerald');
        $initial = mb_strtoupper(mb_substr($v['emri'] ?? 'V', 0, 1));
        $userBadges = $badgeMap[$v['id_perdoruesi']] ?? [];
      ?>
      <a href="/TiranaSolidare/views/public_profile.php?id=<?= (int)$v['id_perdoruesi'] ?>" class="lb-row lb-row--link">
        <div class="lb-row__rank"><?= $pos ?></div>
        <div class="lb-row__avatar" style="background: linear-gradient(135deg, <?= htmlspecialchars($colorResolved['theme']['from']) ?>, <?= htmlspecialchars($colorResolved['theme']['to']) ?>);">
          <?= htmlspecialchars($initial) ?>
        </div>
        <div class="lb-row__info">
          <div class="lb-row__name"><?= htmlspecialchars($v['emri']) ?></div>
          <?php if (!empty($userBadges)): ?>
          <div class="lb-row__badges">
            <?php foreach ($userBadges as $b): ?>
              <span title="<?= htmlspecialchars($b['name']) ?>"><?= $badgeIconMap[$b['icon']] ?? '' ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="lb-row__score"><?= (int) $v['score'] ?> <small>pikë</small></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
</body>
</html>
