<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$userId = (int) ($_GET['id'] ?? 0);

if ($userId <= 0) {
    header('Location: /TiranaSolidare/views/404.php');
    exit();
}

$isLoggedIn = isset($_SESSION['user_id']);
$isOwner = $isLoggedIn && (int) $_SESSION['user_id'] === $userId;

// Fetch profile data
$stmt = $pdo->prepare(
    "SELECT id_perdoruesi, emri, roli, bio, profile_picture, profile_public, krijuar_me
     FROM Perdoruesi
     WHERE id_perdoruesi = ? AND statusi_llogarise = 'Aktiv'"
);
$stmt->execute([$userId]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profile) {
    header('Location: /TiranaSolidare/views/404.php');
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
$eventCount = $requestCount = $helpAppCount = 0;

if (!$privateProfile) {
    // Stats
    $acceptedEvents = $pdo->prepare("SELECT COUNT(*) FROM Aplikimi WHERE id_perdoruesi = ? AND statusi = 'Pranuar'");
    $acceptedEvents->execute([$userId]);
    $eventCount = (int) $acceptedEvents->fetchColumn();

    $helpRequests = $pdo->prepare('SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?');
    $helpRequests->execute([$userId]);
    $requestCount = (int) $helpRequests->fetchColumn();

    $helpApps = $pdo->prepare("SELECT COUNT(*) FROM Aplikimi_Kerkese WHERE id_perdoruesi = ? AND statusi = 'Pranuar'");
    $helpApps->execute([$userId]);
    $helpAppCount = (int) $helpApps->fetchColumn();

    // Recent accepted events
    $recentStmt = $pdo->prepare(
        "SELECT e.id_eventi, e.titulli, e.data, e.vendndodhja, k.emri AS kategoria_emri
         FROM Aplikimi a
         JOIN Eventi e ON e.id_eventi = a.id_eventi
         LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
         WHERE a.id_perdoruesi = ? AND a.statusi = 'Pranuar'
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
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profile['emri']) ?> — Tirana Solidare</title>
    <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/main.css">
    <link rel="stylesheet" href="/TiranaSolidare/public/assets/styles/pages.css">
    <style>
        .pp-shell { max-width: 720px; margin: 120px auto 60px; padding: 0 20px; }
        .pp-card { background: #fff; border-radius: 20px; box-shadow: 0 8px 32px rgba(0,0,0,.08); overflow: hidden; }
        .pp-header { display: flex; align-items: flex-start; gap: 20px; padding: 32px 32px 24px; border-bottom: 1px solid #f0f2f5; }
        .pp-avatar { width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, #00715D, #34d399); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.8rem; font-weight: 700; flex-shrink: 0; }
        .pp-avatar--img { object-fit: cover; background: none; }
        .pp-info h1 { margin: 0; font-size: 1.4rem; font-weight: 700; color: #1a1a2e; }
        .pp-info .pp-meta { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 6px; font-size: 0.85rem; color: #6b7280; }
        .pp-bio { margin: 10px 0 0; font-size: 0.9rem; color: #374151; line-height: 1.5; }
        .pp-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: #e0f5ef; color: #00715D; }
        .pp-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; padding: 24px 32px; }
        .pp-stat { text-align: center; padding: 16px; background: #f9fafb; border-radius: 12px; }
        .pp-stat__value { font-size: 1.5rem; font-weight: 700; color: #00715D; }
        .pp-stat__label { font-size: 0.8rem; color: #6b7280; margin-top: 2px; }
        .pp-section { padding: 24px 32px; }
        .pp-section h3 { font-size: 1rem; font-weight: 700; color: #1a1a2e; margin: 0 0 14px; }
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
        @media (max-width: 600px) {
            .pp-header { flex-direction: column; text-align: center; }
            .pp-info .pp-meta { justify-content: center; }
            .pp-stats { grid-template-columns: 1fr; }
            .pp-table { font-size: 0.78rem; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../public/components/header.php'; ?>

<main class="pp-shell">
    <div class="pp-card">
        <?php if ($privateProfile): ?>
        <div class="pp-header">
            <div class="pp-avatar"><?= htmlspecialchars($initial) ?></div>
            <div class="pp-info">
                <h1><?= htmlspecialchars($profile['emri']) ?></h1>
                <div class="pp-meta">
                    <span class="pp-badge"><?= htmlspecialchars($profile['roli']) ?></span>
                </div>
            </div>
        </div>
        <div class="pp-section">
            <p class="pp-empty">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:6px"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Ky profil është privat.
            </p>
        </div>
        <?php else: ?>
        <div class="pp-header">
            <?php if (!empty($profile['profile_picture'])): ?>
                <img src="<?= htmlspecialchars($profile['profile_picture']) ?>" alt="<?= htmlspecialchars($profile['emri']) ?>" class="pp-avatar pp-avatar--img" onerror="this.outerHTML='<div class=\'pp-avatar\'><?= htmlspecialchars($initial) ?></div>'">
            <?php else: ?>
                <div class="pp-avatar"><?= htmlspecialchars($initial) ?></div>
            <?php endif; ?>
            <div class="pp-info">
                <h1><?= htmlspecialchars($profile['emri']) ?></h1>
                <div class="pp-meta">
                    <span class="pp-badge"><?= htmlspecialchars($profile['roli']) ?></span>
                    <span>Anëtar që nga <?= $memberSince ?></span>
                </div>
                <?php if (!empty($profile['bio'])): ?>
                    <p class="pp-bio"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
                <?php endif; ?>
            </div>
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
            <h3>Historiku i eventeve</h3>
            <div class="pp-table-wrap">
                <table class="pp-table">
                    <thead>
                        <tr><th>Titulli</th><th>Kategoria</th><th>Vendndodhja</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentEvents as $ev): ?>
                        <tr>
                            <td><a href="/TiranaSolidare/views/events.php?id=<?= (int) $ev['id_eventi'] ?>"><?= htmlspecialchars($ev['titulli']) ?></a></td>
                            <td><?= htmlspecialchars($ev['kategoria_emri'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($ev['vendndodhja'] ?? '—') ?></td>
                            <td><?= date('d/m/Y', strtotime($ev['data'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($recentRequests)): ?>
        <div class="pp-section">
            <h3>Historiku i kërkesave</h3>
            <div class="pp-table-wrap">
                <table class="pp-table">
                    <thead>
                        <tr><th>Titulli</th><th>Tipi</th><th>Statusi</th><th>Krijuar</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentRequests as $req): ?>
                        <tr>
                            <td><a href="/TiranaSolidare/views/help_requests.php?id=<?= (int) $req['id_kerkese_ndihme'] ?>"><?= htmlspecialchars($req['titulli']) ?></a></td>
                            <td><?= $req['tipi'] === 'Kërkesë' ? 'Kërkoj ndihmë' : 'Dua të ndihmoj' ?></td>
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
<script src="/TiranaSolidare/assets/js/main.js"></script>
</body>
</html>
