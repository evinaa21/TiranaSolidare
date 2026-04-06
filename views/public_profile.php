<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/status_labels.php';
if (session_status() === PHP_SESSION_NONE) session_start();

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

// Admins don't have public profiles
if (in_array(ts_normalize_value($profile['roli'] ?? ''), ['admin', 'super_admin'], true)) {
    if ($isOwner) {
        header('Location: /TiranaSolidare/views/dashboard.php');
    } else {
        header('Location: /TiranaSolidare/views/404.php');
    }
    exit();
}

$canonicalProfileUrl = ts_public_profile_url((int) $profile['id_perdoruesi'], (string) ($profile['emri'] ?? ''));
if ($publicHandle === '' && isset($_GET['id'])) {
    header('Location: ' . $canonicalProfileUrl, true, 302);
    exit();
}

// Require login to view any profile
if (!$isLoggedIn) {
    header("Location: /TiranaSolidare/views/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Privacy check: if profile is private and viewer is not the owner or the admin
$isAdmin = $isLoggedIn && in_array(ts_normalize_value($_SESSION['roli'] ?? ''), ['admin', 'super_admin'], true);

if (!(int) $profile['profile_public'] && !$isOwner && !$isAdmin) {
    $privateProfile = true;
} else {
    $privateProfile = false;
}

$isBlocked = false;
if ($isLoggedIn && !$isOwner && !$isAdmin) {
    if (isUserBlocked($pdo, (int) $_SESSION['user_id'], $userId)) {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Profil i padisponueshëm</title><link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css"></head><body>';
        include __DIR__ . '/../public/components/header.php';
        echo '<main style="display:flex;align-items:center;justify-content:center;min-height:60vh;"><div style="text-align:center"><h2>Ky profil nuk është i disponueshëm.</h2><a href="/TiranaSolidare/public/" class="btn_primary" style="margin-top:16px;display:inline-block;">Kthehu në faqe kryesore</a></div></main>';
        include __DIR__ . '/../public/components/footer.php';
        echo '</body></html>';
        exit();
    }
}

$recentEvents = [];
$recentRequests = [];
$earnedBadges = [];
$eventCount = $requestCount = $helpAppCount = 0;

if (!$privateProfile && !$isBlocked) {
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
         WHERE id_perdoruesi = ? AND moderation_status = 'approved'
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
$roleLabel = status_label($profile['roli']);
$displayBio = trim((string) ($profile['bio'] ?? ''));
$displayBioShort = $displayBio;
$badgeCount = count($earnedBadges);
$totalVisibleActivity = $eventCount + $requestCount + $helpAppCount;
$badgeChipLabel = $badgeCount === 1 ? '1 badge e fituar' : $badgeCount . ' badge të fituara';
$activityChipLabel = $totalVisibleActivity === 0
    ? 'Aktiviteti i parë pritet'
    : ($totalVisibleActivity === 1 ? '1 sinjal aktiviteti' : $totalVisibleActivity . ' sinjale aktiviteti');
$headerLead = 'Profili ruan privatësinë dhe nuk shfaq aktivitet ose arritje publike për vizitorët.';

if ($displayBioShort !== '' && mb_strlen($displayBioShort) > 180) {
    $displayBioShort = rtrim(mb_substr($displayBioShort, 0, 177)) . '...';
}

$heroTitle = 'Ky profil është privat';
$heroSummary = 'Detajet e aktivitetit publik dhe badge-t nuk shfaqen për vizitorët kur profili mbahet privat.';
$heroSpotlightLabel = 'Qasje e kufizuar';
$heroSpotlightTitle = 'Aktiviteti publik nuk shfaqet';
$heroSpotlightMeta = 'Vetëm pronari i profilit mund të shohë historikun e plotë dhe arritjet e ruajtura këtu.';

if (!$privateProfile) {
    $heroTitle = $totalVisibleActivity > 0
        ? 'Një profil që tregon kontributin real në komunitet'
        : 'Profili publik është gati për historinë e parë të kontributit';

    if ($displayBioShort !== '') {
        $heroSummary = $displayBioShort;
    } elseif ($totalVisibleActivity > 0) {
        $heroSummary = $profile['emri'] . ' ka ndërtuar një profil që përmbledh eventet e pranuara, kërkesat e krijuara dhe ndihmat e pranuara në Tirana Solidare.';
    } else {
        $heroSummary = 'Sapo të nisë pjesëmarrja në evente ose kërkesa, ky profil do të mbushet me badge, aktivitet dhe momentet kryesore të kontributit në komunitet.';
    }

    if (!empty($recentEvents)) {
        $heroSpotlightLabel = 'Në fokus tani';
        $heroSpotlightTitle = (string) ($recentEvents[0]['titulli'] ?? 'Event i komunitetit');
        $heroSpotlightMeta = date('d M Y', strtotime((string) $recentEvents[0]['data'])) . ' • ' . ($recentEvents[0]['vendndodhja'] ?: 'Tiranë');
    } elseif (!empty($recentRequests)) {
        $heroSpotlightLabel = 'Kërkesa më e fundit';
        $heroSpotlightTitle = (string) ($recentRequests[0]['titulli'] ?? 'Kërkesë për ndihmë');
        $heroSpotlightMeta = status_label((string) ($recentRequests[0]['statusi'] ?? 'open')) . ' • ' . date('d M Y', strtotime((string) $recentRequests[0]['krijuar_me']));
    } else {
        $heroSpotlightLabel = 'Profili po ndërtohet';
        $heroSpotlightTitle = 'Ende pa aktivitet publik';
        $heroSpotlightMeta = 'Kur të nisë angazhimi i parë, këtu do të shfaqet momenti më i fundit i dukshëm në platformë.';
    }

    if ($displayBio !== '') {
        $headerLead = $displayBio;
    } elseif ($totalVisibleActivity > 0) {
        $headerLead = $profile['emri'] . ' po ndërton një histori publike me pjesëmarrje në evente, kërkesa dhe kontribute të dukshme në komunitet.';
    } elseif ($badgeCount > 0) {
        $headerLead = 'Badge-t e fituara janë shenja e para e prezencës në platformë. Sapo të nisë aktiviteti publik, këtu do të shfaqen eventet dhe kërkesat përkatëse.';
    } else {
        $headerLead = 'Ky profil është gati të mbledhë aktivitetin e parë publik. Pasi të nisë angazhimi, kjo zonë do të lidhet me historikun dhe arritjet e përdoruesit.';
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['emri']) ?> — Tirana Solidare</title>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalProfileUrl) ?>">
    <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css?v=20260401a">
    <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css?v=20260401a">
    <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/requests.css?v=20260401a">
    <style>
        .pp-shell { max-width: 1180px; margin: 96px auto 60px; padding: 0 24px; }
        .pp-card { background: transparent; border-radius: 0; box-shadow: none; overflow: visible; }

        /* ── Cover Banner ── */
        .pp-cover {
            position: relative;
            min-height: 280px;
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
        .pp-cover__inner {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.7fr) minmax(280px, 0.95fr);
            gap: 24px;
            min-height: 280px;
            padding: 30px 32px 86px;
        }
        .pp-cover__content {
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            min-width: 0;
            color: #fff;
        }
        .pp-cover__eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.16);
            color: rgba(255,255,255,0.92);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            backdrop-filter: blur(12px);
        }
        .pp-cover__title {
            margin: 18px 0 0;
            max-width: 640px;
            font-family: 'Bitter', serif;
            font-size: 2rem;
            line-height: 1.08;
            color: #fff;
        }
        .pp-cover__summary {
            max-width: 640px;
            margin: 14px 0 0;
            font-size: 0.98rem;
            line-height: 1.7;
            color: rgba(255,255,255,0.88);
        }
        .pp-cover__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .pp-cover__chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.16);
            color: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        .pp-cover__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 20px;
            position: relative;
            z-index: 4;
        }
        .pp-cover__cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 18px;
            border-radius: 999px;
            background: #fff;
            color: var(--pp-color-from, #003229);
            text-decoration: none;
            font-size: 0.88rem;
            font-weight: 700;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.16);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .pp-cover__cta:hover {
            transform: translateY(-1px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.18);
        }
        .pp-cover__spotlight {
            align-self: stretch;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 18px;
            padding: 20px;
            border-radius: 20px;
            background: linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.10));
            border: 1px solid rgba(255,255,255,0.18);
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.14), 0 14px 28px rgba(15, 23, 42, 0.14);
            backdrop-filter: blur(16px);
            color: #fff;
        }
        .pp-cover__spotlight-label {
            font-size: 0.74rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.74);
        }
        .pp-cover__spotlight-title {
            margin-top: 6px;
            font-family: 'Bitter', serif;
            font-size: 1.2rem;
            line-height: 1.3;
            color: #fff;
        }
        .pp-cover__spotlight-meta {
            margin-top: 8px;
            font-size: 0.86rem;
            line-height: 1.55;
            color: rgba(255,255,255,0.80);
        }
        .pp-cover__mini-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .pp-cover__mini-stat {
            padding: 12px 10px;
            border-radius: 16px;
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.10);
            text-align: center;
        }
        .pp-cover__mini-value {
            font-family: 'Bitter', serif;
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1;
            color: #fff;
        }
        .pp-cover__mini-label {
            margin-top: 6px;
            font-size: 0.72rem;
            line-height: 1.3;
            color: rgba(255,255,255,0.72);
        }

        /* ── Avatar overlapping the banner ── */
        .pp-avatar-wrap {
            position: relative;
            margin-top: -34px;
            padding: 0 32px;
            display: flex;
            align-items: flex-end;
            gap: 20px;
            z-index: 3;
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
            position: relative;
            z-index: 1;
            margin-top: -14px;
            padding: 24px 32px 24px 148px;
            min-height: 118px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 5px 24px rgba(15, 23, 42, 0.08);
        }
        .pp-name-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            min-height: 42px;
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
            background: linear-gradient(135deg, var(--pp-color-from, #003229), var(--pp-color-mid, #00715D));
            color: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.10);
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
            background: #fff;
            color: #0f172a;
            border: 1px solid #e5e7eb;
            border-top: 3px solid var(--pp-color-mid, #00715D);
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
        }
        .pp-earned-badge__icon {
            flex-shrink: 0;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--pp-color-from, #003229), var(--pp-color-to, #34d399));
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.18);
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
            color: var(--pp-color-from, #003229);
        }
        .pp-earned-badge__desc {
            font-size: 0.8rem;
            line-height: 1.45;
            color: #475569;
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
            max-width: 70ch;
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
            color: var(--pp-color-mid, #00715D);
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
        .pp-section h3 svg { width: 18px; height: 18px; color: var(--pp-color-mid, #00715D); }
        .pp-table-wrap { overflow-x: auto; }
        .pp-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .pp-table th { text-align: left; padding: 10px 12px; font-weight: 600; color: #6b7280; border-bottom: 2px solid #f0f2f5; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.3px; }
        .pp-table td { padding: 10px 12px; border-bottom: 1px solid #f0f2f5; color: #374151; }
        .pp-table a { color: var(--pp-color-mid, #00715D); text-decoration: none; font-weight: 600; }
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

        .pp-empty-state {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 16px;
            align-items: start;
        }
        .pp-empty-state__icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--pp-color-from, #003229), var(--pp-color-to, #34d399));
            color: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.14);
        }
        .pp-empty-state__icon svg {
            width: 24px;
            height: 24px;
        }
        .pp-empty-state__title {
            margin: 0;
            font-family: 'Bitter', serif;
            font-size: 1.08rem;
            color: #1a1a2e;
        }
        .pp-empty-state__text {
            margin: 8px 0 0;
            font-size: 0.92rem;
            line-height: 1.65;
            color: #64748b;
        }
        .pp-empty-state__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }
        .pp-empty-state__chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 12px;
            border-radius: 999px;
            background: #f8fafc;
            border: 1px solid rgba(15, 23, 42, 0.08);
            color: #334155;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .pp-section .rq-grid {
            margin-top: 8px;
        }

        .pp-section .rq-card {
            animation: none;
        }

        @media (max-width: 900px) {
            .pp-cover {
                min-height: 0;
            }
            .pp-cover__inner {
                grid-template-columns: 1fr;
                min-height: 0;
            }
            .pp-cover__title {
                font-size: 1.8rem;
            }
            .pp-cover__spotlight {
                min-height: 0;
            }
            .pp-header-body {
                padding-left: 32px;
                margin-top: 10px;
                min-height: 0;
            }
        }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .pp-shell { margin-top: 80px; }
            .pp-cover {
                min-height: 0;
            }
            .pp-cover__inner {
                padding: 22px 20px 34px;
                gap: 18px;
            }
            .pp-cover__content {
                text-align: center;
            }
            .pp-cover__title {
                font-size: 1.55rem;
            }
            .pp-cover__summary {
                font-size: 0.9rem;
            }
            .pp-cover__chips,
            .pp-cover__actions {
                justify-content: center;
            }
            .pp-cover__spotlight {
                text-align: left;
                padding: 18px;
            }
            .pp-avatar-wrap { flex-direction: column; align-items: center; padding: 0 20px; margin-top: -30px; }
            .pp-avatar { width: 80px; height: 80px; font-size: 1.8rem; }
            .pp-header-body { text-align: center; padding: 12px 20px 20px; margin-top: 12px; }
            .pp-name-row { justify-content: center; }
            .pp-stats { margin: 14px 0 0; grid-template-columns: repeat(3, 1fr); }
            .pp-stat__value { font-size: 1.3rem; }
            .pp-section { padding: 20px; }
            .pp-table { font-size: 0.78rem; }
            .pp-earned-badges { grid-template-columns: 1fr; }
            .pp-earned-badge { padding: 12px; }
            .pp-earned-badge__icon { width: 34px; height: 34px; }
            .pp-bio { max-width: none; }
            .pp-empty-state {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .pp-empty-state__icon {
                margin: 0 auto;
            }
            .pp-empty-state__chips {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main class="pp-shell">
    <div class="pp-card" style="--pp-color-from: <?= htmlspecialchars($profileColorTheme['from']) ?>; --pp-color-mid: <?= htmlspecialchars($profileColorTheme['mid']) ?>; --pp-color-to: <?= htmlspecialchars($profileColorTheme['to']) ?>;">
        <?php if ($privateProfile || $isBlocked): ?>
        <div class="pp-cover">
            <div class="pp-cover__pattern"></div>
            <div class="pp-cover__inner">
                <div class="pp-cover__content">
                    <div>
                        <span class="pp-cover__eyebrow">Profil privat</span>
                        <h2 class="pp-cover__title"><?= htmlspecialchars($heroTitle) ?></h2>
                        <p class="pp-cover__summary"><?= htmlspecialchars($heroSummary) ?></p>
                       <div class="pp-cover__chips">
                            <span class="pp-cover__chip"><?= htmlspecialchars($roleLabel) ?></span>
                            <span class="pp-cover__chip">Anëtar që nga <?= htmlspecialchars($memberSince) ?></span>
                        </div>
                        <?php if ($isLoggedIn && !$isOwner): ?>
                        <div class="pp-cover__actions">
                            <a href="/TiranaSolidare/views/volunteer_panel.php?tab=messages&with=<?= (int) $profile['id_perdoruesi'] ?>" class="pp-cover__cta">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                Dërgo mesazh
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <aside class="pp-cover__spotlight">
                    <div>
                        <div class="pp-cover__spotlight-label"><?= htmlspecialchars($heroSpotlightLabel) ?></div>
                        <div class="pp-cover__spotlight-title"><?= htmlspecialchars($heroSpotlightTitle) ?></div>
                        <div class="pp-cover__spotlight-meta"><?= htmlspecialchars($heroSpotlightMeta) ?></div>
                    </div>
                </aside>
            </div>
        </div>
        <div class="pp-avatar-wrap">
            <div class="pp-avatar"><?= htmlspecialchars($initial) ?></div>
        </div>
        <div class="pp-header-body">
            <div class="pp-name-row">
                <h1><?= htmlspecialchars($profile['emri']) ?></h1>
                <span class="pp-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                    <?= htmlspecialchars($roleLabel) ?>
                </span>
            </div>
            <p class="pp-bio"><?= nl2br(htmlspecialchars($headerLead)) ?></p>
        </div>
        <div class="pp-private-msg">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <?php if ($isBlocked): ?>
                Ky profil nuk është i disponueshëm.
            <?php else: ?>
                Ky profil është privat.
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="pp-cover">
            <div class="pp-cover__pattern"></div>
            <div class="pp-cover__inner">
                <div class="pp-cover__content">
                    <div>
                        <span class="pp-cover__eyebrow">Profili publik i vullnetarit</span>
                        <h2 class="pp-cover__title"><?= htmlspecialchars($heroTitle) ?></h2>
                        <p class="pp-cover__summary"><?= nl2br(htmlspecialchars($heroSummary)) ?></p>
                        <div class="pp-cover__chips">
                            <span class="pp-cover__chip">Anëtar që nga <?= htmlspecialchars($memberSince) ?></span>
                            <span class="pp-cover__chip"><?= htmlspecialchars($badgeChipLabel) ?></span>
                            <span class="pp-cover__chip"><?= htmlspecialchars($activityChipLabel) ?></span>
                        </div>
                    </div>
                    <?php if ($isLoggedIn && !$isOwner): ?>
                    <div class="pp-cover__actions">
                        <a href="/TiranaSolidare/views/volunteer_panel.php?tab=messages&with=<?= (int) $profile['id_perdoruesi'] ?>" class="pp-cover__cta">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            Dërgo mesazh
                        </a>
                    </div>
                    <?php elseif ($isOwner): ?>
                    <div class="pp-cover__actions">
                        <a href="/TiranaSolidare/views/volunteer_panel.php?tab=profile" class="pp-cover__cta">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            Ndrysho profilin
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <aside class="pp-cover__spotlight">
                    <div>
                        <div class="pp-cover__spotlight-label"><?= htmlspecialchars($heroSpotlightLabel) ?></div>
                        <div class="pp-cover__spotlight-title"><?= htmlspecialchars($heroSpotlightTitle) ?></div>
                        <div class="pp-cover__spotlight-meta"><?= htmlspecialchars($heroSpotlightMeta) ?></div>
                    </div>
                    <div class="pp-cover__mini-stats">
                        <div class="pp-cover__mini-stat">
                            <div class="pp-cover__mini-value"><?= $eventCount ?></div>
                            <div class="pp-cover__mini-label">Evente të pranuara</div>
                        </div>
                        <div class="pp-cover__mini-stat">
                            <div class="pp-cover__mini-value"><?= $requestCount ?></div>
                            <div class="pp-cover__mini-label">Kërkesa të krijuara</div>
                        </div>
                        <div class="pp-cover__mini-stat">
                            <div class="pp-cover__mini-value"><?= $helpAppCount ?></div>
                            <div class="pp-cover__mini-label">Ndihma të pranuara</div>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
        <div class="pp-avatar-wrap">
            <?php if (!empty($profile['profile_picture'])): ?>
                <img src="<?= htmlspecialchars($profile['profile_picture']) ?>" alt="<?= htmlspecialchars($profile['emri']) ?>" class="pp-avatar pp-avatar--img" onerror="this.outerHTML='<div class=\'pp-avatar\'><?= htmlspecialchars($initial) ?></div>'">
            <?php else: ?>
                <div class="pp-avatar"><?= htmlspecialchars($initial) ?></div>
            <?php endif; ?>
        </div>
        <div class="pp-header-body">
            <div class="pp-name-row">
                <h1><?= htmlspecialchars($profile['emri']) ?></h1>
                <span class="pp-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                    <?= htmlspecialchars($roleLabel) ?>
                </span>
            </div>
            <p class="pp-bio"><?= nl2br(htmlspecialchars($headerLead)) ?></p>
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

        <?php if (!empty($recentEvents)): ?>
        <div class="pp-section">
            <h3>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                Kam marrë pjesë në:
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
                            <td><?= ts_help_request_type_label($req) ?></td>
                            <td><span class="pp-status pp-status--<?= strtolower($req['statusi']) ?>"><?= htmlspecialchars(status_label($req['statusi'])) ?></span></td>
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
            <div class="pp-empty-state">
                <div class="pp-empty-state__icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20v-6"/><path d="M18 20V10"/><path d="M6 20v-2"/></svg>
                </div>
                <div>
                    <h3 class="pp-empty-state__title">Kur të nisë aktiviteti, historiku do të shfaqet këtu</h3>
                    <p class="pp-empty-state__text">Kjo zonë është rezervuar për eventet e pranuara dhe kërkesat publike. Sapo të ketë lëvizje në platformë, faqja do të mbushet automatikisht me momentet dhe lidhjet përkatëse.</p>
                    <div class="pp-empty-state__chips">
                        <span class="pp-empty-state__chip">Badge-t do të shfaqen sapo të fitohen</span>
                        <span class="pp-empty-state__chip">Eventet e para do të listohen këtu</span>
                        <span class="pp-empty-state__chip">Kërkesat publike do të duken këtu</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../public/components/footer.php'; ?>
<script src="/TiranaSolidare/public/assets/scripts/main.js?v=20260401a"></script>
</body>
</html>
