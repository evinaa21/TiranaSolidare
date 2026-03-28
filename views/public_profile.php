<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$publicHandle = trim((string) ($_GET['u'] ?? ''));
$userId = ts_parse_public_profile_id($publicHandle);

if ($userId <= 0) {
    $userId = (int) ($_GET['id'] ?? 0);
}

if ($userId <= 0) {
    header('Location: /TiranaSolidare/views/404.php');
    exit();
}

$isLoggedIn = isset($_SESSION['user_id']);
$isOwner = $isLoggedIn && (int) $_SESSION['user_id'] === $userId;

// Fetch profile data
$stmt = $pdo->prepare(
    "SELECT id_perdoruesi, emri, roli, bio, profile_picture, profile_public, profile_color, krijuar_me
     FROM Perdoruesi
     WHERE id_perdoruesi = ? AND statusi_llogarise = 'active'"
);
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    header('Location: /TiranaSolidare/views/404.php');
    exit();
}

$canonicalProfileUrl = ts_public_profile_url((int) $profile['id_perdoruesi'], (string) ($profile['emri'] ?? ''));
if ($publicHandle === '' && isset($_GET['id'])) {
    header('Location: ' . $canonicalProfileUrl, true, 302);
    exit();
}

// Privacy check: if profile is private and viewer is not the owner
if (!(int) $profile['profile_public'] && !$isOwner) {
    $privateProfile = true;
} else {
    $privateProfile = false;
}

$recentEvents = [];
$recentRequests = [];
$earnedBadges = [];
$eventCount = $requestCount = $helpAppCount = 0;

if (!$privateProfile) {
    // Stats and earned badges
    $badgeInfo = ts_get_user_profile_badges($pdo, $userId);
    $eventCount = (int) ($badgeInfo['metrics']['accepted_events'] ?? 0);
    $requestCount = (int) ($badgeInfo['metrics']['total_requests'] ?? 0);
    $helpAppCount = (int) ($badgeInfo['metrics']['accepted_help_applications'] ?? 0);
    $earnedBadges = $badgeInfo['badges'] ?? [];

    // Recent accepted events
    $recentStmt = $pdo->prepare(
        "SELECT e.id_eventi, e.titulli, e.pershkrimi, e.banner, e.data, e.vendndodhja, k.emri AS kategoria_emri
         FROM Aplikimi a
         JOIN Eventi e ON e.id_eventi = a.id_eventi
         LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
         WHERE a.id_perdoruesi = ? AND a.statusi = 'approved'
         ORDER BY e.data DESC
         LIMIT 10"
    );
    $recentStmt->execute([$userId]);
    $recentEvents = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent requests created
    $reqStmt = $pdo->prepare(
        "SELECT id_kerkese_ndihme, titulli, tipi, statusi, krijuar_me
         FROM Kerkesa_per_Ndihme
         WHERE id_perdoruesi = ?
         ORDER BY krijuar_me DESC
         LIMIT 10"
    );
    $reqStmt->execute([$userId]);
    $recentRequests = $reqStmt->fetchAll(PDO::FETCH_ASSOC);
}

$initial = mb_strtoupper(mb_substr($profile['emri'], 0, 1));
$memberSince = date('d M Y', strtotime($profile['krijuar_me']));
$colorResolved = ts_resolve_profile_color($profile['profile_color'] ?? 'emerald');
$profileColorTheme = $colorResolved['theme'];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['emri']) ?> — Tirana Solidare</title>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalProfileUrl) ?>">
    <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
    <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
    <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/requests.css">
    <style>
        .pp-shell { max-width: 1180px; margin: 96px auto 60px; padding: 0 24px; }
        .pp-card { background: transparent; border-radius: 0; box-shadow: none; overflow: visible; }

        /* ── Cover Banner ── */
        .pp-cover {
            position: relative;
            height: 220px;
            background: linear-gradient(135deg, var(--pp-color-from, #003229) 0%, var(--pp-color-mid, #00715D) 40%, var(--pp-color-to, #34d399) 100%);
            overflow: hidden;
            border-radius: 22px;
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.10);
        }
        .pp-cover::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 260px; height: 260px;
            border: 2px solid rgba(255,255,255,0.08);
            border-radius: 50%;
            pointer-events: none;
        }
        .pp-cover::after {
            content: '';
            position: absolute;
            bottom: -40px; left: 30%;
            width: 200px; height: 200px;
            border: 2px solid rgba(255,255,255,0.06);
            border-radius: 50%;
            pointer-events: none;
        }
        .pp-cover__pattern {
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(255,255,255,0.04) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255,255,255,0.06) 0%, transparent 40%);
        }

        /* ── Avatar overlapping the banner ── */
        .pp-avatar-wrap {
            position: relative;
            margin-top: -48px;
            padding: 0 32px;
            display: flex;
            align-items: flex-end;
            gap: 20px;
            z-index: 2;
        }
        .pp-avatar {
            width: 96px; height: 96px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--pp-color-mid, #00715D), var(--pp-color-to, #34d399));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 2.2rem;
            font-weight: 700;
            flex-shrink: 0;
            border: 4px solid #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }
        .pp-avatar--img {
            object-fit: cover;
            background: #fff;
        }

        /* ── Info area below avatar ── */
        .pp-header-body {
            margin-top: 12px;
            padding: 20px 32px 24px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 5px 24px rgba(15, 23, 42, 0.08);
        }
        .pp-name-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pp-name-row h1 {
            margin: 0;
            font-family: 'Bitter', serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a2e;
        }
        .pp-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #e0f5ef;
            color: #00715D;
        }
        .pp-badge svg { width: 13px; height: 13px; }
        .pp-earned-badges {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
        }
        .pp-earned-badge {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border-radius: 14px;
            background: linear-gradient(145deg, #f0fdf8 0%, #dcfce7 100%);
            color: #064e3b;
            border: 1px solid #86efac;
            box-shadow: 0 6px 16px rgba(4, 120, 87, 0.14);
        }
        .pp-earned-badge__icon {
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #047857;
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(4, 120, 87, 0.28);
        }
        .pp-earned-badge__icon svg {
            width: 20px;
            height: 20px;
        }
        .pp-earned-badge__body {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }
        .pp-earned-badge__name {
            font-size: 0.95rem;
            font-weight: 800;
            line-height: 1.2;
            color: #064e3b;
        }
        .pp-earned-badge__desc {
            font-size: 0.8rem;
            line-height: 1.45;
            color: #166534;
        }
        .pp-meta {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-top: 8px;
            font-size: 0.84rem;
            color: #6b7280;
        }
        .pp-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .pp-meta svg { width: 14px; height: 14px; opacity: 0.5; }
        .pp-bio {
            margin: 14px 0 0;
            font-size: 0.9rem;
            color: #374151;
            line-height: 1.6;
        }

        /* ── Stats ── */
        .pp-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0;
            margin: 18px 0 0;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 5px 24px rgba(15, 23, 42, 0.08);
            border-top: 1px solid #f0f2f5;
            border-bottom: 1px solid #f0f2f5;
        }
        .pp-stat {
            text-align: center;
            padding: 20px 12px;
            position: relative;
        }
        .pp-stat + .pp-stat::before {
            content: '';
            position: absolute;
            left: 0; top: 20%;
            width: 1px; height: 60%;
            background: #f0f2f5;
        }
        .pp-stat__value {
            font-family: 'Bitter', serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: #00715D;
            line-height: 1;
        }
        .pp-stat__label {
            font-size: 0.76rem;
            color: #6b7280;
            margin-top: 4px;
            font-weight: 500;
        }

        /* ── Sections ── */
        .pp-section {
            margin-top: 18px;
            padding: 24px 28px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 5px 24px rgba(15, 23, 42, 0.08);
        }
        .pp-section h3 {
            font-family: 'Bitter', serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: #1a1a2e;
            margin: 0 0 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pp-section h3 svg { width: 18px; height: 18px; color: #00715D; }
        .pp-table-wrap { overflow-x: auto; }
        .pp-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .pp-table th { text-align: left; padding: 10px 12px; font-weight: 600; color: #6b7280; border-bottom: 2px solid #f0f2f5; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; }
        .pp-table td { padding: 10px 12px; border-bottom: 1px solid #f0f2f5; color: #374151; }
        .pp-table a { color: #00715D; text-decoration: none; font-weight: 600; }
        .pp-table a:hover { text-decoration: underline; }
        .pp-status { padding: 2px 8px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; }
        .pp-status--open { background: #d1fae5; color: #065f46; }
        .pp-status--closed { background: #e5e7eb; color: #6b7280; }
        .pp-empty { color: #9ca3af; font-size: 0.88rem; font-style: italic; }

        /* ── Private profile ── */
        .pp-private-msg {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding: 20px 24px;
            color: #6b7280;
            font-size: 0.9rem;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        }
        .pp-private-msg svg { flex-shrink: 0; color: #9ca3af; }

        .pp-section .rq-grid {
            margin-top: 8px;
        }

        .pp-section .rq-card {
            animation: none;
        }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .pp-shell { margin-top: 80px; }
            .pp-cover { height: 160px; }
            .pp-avatar-wrap { flex-direction: column; align-items: center; padding: 0 20px; margin-top: -40px; }
            .pp-avatar { width: 80px; height: 80px; font-size: 1.8rem; }
            .pp-header-body { text-align: center; padding: 12px 20px 20px; }
            .pp-name-row { justify-content: center; }
            .pp-meta { justify-content: center; }
            .pp-stats { margin: 14px 0 0; grid-template-columns: repeat(3, 1fr); }
            .pp-stat__value { font-size: 1.3rem; }
            .pp-section { padding: 20px; }
            .pp-table { font-size: 0.78rem; }
            .pp-earned-badges { grid-template-columns: 1fr; }
            .pp-earned-badge { padding: 12px; }
            .pp-earned-badge__icon { width: 34px; height: 34px; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main class="pp-shell">
    <div class="pp-card">
        <?php if ($privateProfile): ?>
        <div class="pp-cover" style="--pp-color-from: <?= htmlspecialchars($profileColorTheme['from']) ?>; --pp-color-mid: <?= htmlspecialchars($profileColorTheme['mid']) ?>; --pp-color-to: <?= htmlspecialchars($profileColorTheme['to']) ?>;"><div class="pp-cover__pattern"></div></div>
        <div class="pp-avatar-wrap">
            <div class="pp-avatar" style="background: linear-gradient(135deg, <?= htmlspecialchars($profileColorTheme['mid']) ?>, <?= htmlspecialchars($profileColorTheme['to']) ?>)"><?= htmlspecialchars($initial) ?></div>
        </div>
        <div class="pp-header-body">
            <div class="pp-name-row">
                <h1><?= htmlspecialchars($profile['emri']) ?></h1>
                <span class="pp-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                    <?= htmlspecialchars($profile['roli']) ?>
                </span>
            </div>
        </div>
        <div class="pp-private-msg">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Ky profil është privat.
        </div>
        <?php else: ?>
        <div class="pp-cover" style="--pp-color-from: <?= htmlspecialchars($profileColorTheme['from']) ?>; --pp-color-mid: <?= htmlspecialchars($profileColorTheme['mid']) ?>; --pp-color-to: <?= htmlspecialchars($profileColorTheme['to']) ?>;"><div class="pp-cover__pattern"></div></div>
        <div class="pp-avatar-wrap">
            <?php if (!empty($profile['profile_picture'])): ?>
                <img src="<?= htmlspecialchars($profile['profile_picture']) ?>" alt="<?= htmlspecialchars($profile['emri']) ?>" class="pp-avatar pp-avatar--img" onerror="this.outerHTML='<div class=\'pp-avatar\'><?= htmlspecialchars($initial) ?></div>'">
            <?php else: ?>
                <div class="pp-avatar" style="background: linear-gradient(135deg, <?= htmlspecialchars($profileColorTheme['mid']) ?>, <?= htmlspecialchars($profileColorTheme['to']) ?>)"><?= htmlspecialchars($initial) ?></div>
            <?php endif; ?>
        </div>
        <div class="pp-header-body">
            <div class="pp-name-row">
                <h1><?= htmlspecialchars($profile['emri']) ?></h1>
                <span class="pp-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                    <?= htmlspecialchars($profile['roli']) ?>
                </span>
            </div>
            <div class="pp-meta">
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    Anëtar që nga <?= $memberSince ?>
                </span>
            </div>
            <?php if (!empty($profile['bio'])): ?>
                <p class="pp-bio"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($earnedBadges)): ?>
                <div class="pp-earned-badges">
                    <?php foreach ($earnedBadges as $badge): ?>
                        <div class="pp-earned-badge" title="<?= htmlspecialchars($badge['description']) ?>">
                            <span class="pp-earned-badge__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                            </span>
                            <span class="pp-earned-badge__body">
                                <span class="pp-earned-badge__name"><?= htmlspecialchars($badge['name']) ?></span>
                                <span class="pp-earned-badge__desc"><?= htmlspecialchars($badge['description']) ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="pp-stats">
            <div class="pp-stat">
                <div class="pp-stat__value"><?= $eventCount ?></div>
                <div class="pp-stat__label">Evente të pranuara</div>
            </div>
            <div class="pp-stat">
                <div class="pp-stat__value"><?= $requestCount ?></div>
                <div class="pp-stat__label">Kërkesa të krijuara</div>
            </div>
            <div class="pp-stat">
                <div class="pp-stat__value"><?= $helpAppCount ?></div>
                <div class="pp-stat__label">Ndihma të pranuara</div>
            </div>
        </div>

        <?php if (!empty($recentEvents)): ?>
        <div class="pp-section">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                Une kam marr pjese ne:
            </h3>
            <div class="rq-grid">
                <?php foreach ($recentEvents as $ev): ?>
                    <a href="/TiranaSolidare/views/events.php?id=<?= (int) $ev['id_eventi'] ?>" class="rq-card">
                        <div class="rq-card__visual">
                            <img src="<?= !empty($ev['banner']) ? htmlspecialchars($ev['banner']) : '/TiranaSolidare/public/assets/images/default-event.svg' ?>" alt="<?= htmlspecialchars($ev['titulli']) ?>" class="rq-card__img" onerror="this.src='/TiranaSolidare/public/assets/images/default-event.svg'">
                            <div class="rq-card__overlay">
                                <span class="rq-badge rq-badge--event"><?= htmlspecialchars($ev['kategoria_emri'] ?? 'Event') ?></span>
                                <?php if (strtotime($ev['data']) <= time()): ?>
                                    <span class="rq-badge rq-badge--past">I kaluar</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="rq-card__content">
                            <h3 class="rq-card__title"><?= htmlspecialchars($ev['titulli']) ?></h3>
                            <p class="rq-card__desc"><?= htmlspecialchars(mb_substr($ev['pershkrimi'] ?? '', 0, 110)) ?>...</p>
                            <div class="rq-card__footer">
                                <div class="rq-card__meta">
                                    <span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                                        <?= htmlspecialchars($ev['vendndodhja'] ?? 'Tiranë') ?>
                                    </span>
                                    <span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                        <?= date('d M Y', strtotime($ev['data'])) ?>
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
        </div>
        <?php endif; ?>

        <?php if (!empty($recentRequests)): ?>
        <div class="pp-section">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                Historiku i kërkesave
            </h3>
            <div class="pp-table-wrap">
                <table class="pp-table">
                    <thead>
                        <tr><th>Titulli</th><th>Tipi</th><th>Statusi</th><th>Krijuar</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentRequests as $req): ?>
                        <tr>
                            <td><a href="/TiranaSolidare/views/help_requests.php?id=<?= (int) $req['id_kerkese_ndihme'] ?>"><?= htmlspecialchars($req['titulli']) ?></a></td>
                            <td><?= $req['tipi'] === 'request' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?></td>
                            <td><span class="pp-status pp-status--<?= strtolower($req['statusi']) ?>"><?= htmlspecialchars($req['statusi']) ?></span></td>
                            <td><?= date('d/m/Y', strtotime($req['krijuar_me'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($recentEvents) && empty($recentRequests)): ?>
        <div class="pp-section">
            <p class="pp-empty">Ky vullnetar nuk ka aktivitet publik ende.</p>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js"></script>
</body>
</html>
