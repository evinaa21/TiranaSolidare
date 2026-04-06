<?php
/**
 * api/stats.php
 * ---------------------------------------------------
 * Dashboard Statistics API
 *
 * GET ?action=overview     – Platform-wide stats (Admin)
 * GET ?action=my_stats     – Personal stats (Volunteer)
 * GET ?action=reports      – List reports (Admin)
 * POST ?action=generate    – Generate a simple report (Admin)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'overview';

switch ($action) {

    // ── ADMIN OVERVIEW ─────────────────────────────
    case 'overview':
        require_method('GET');
        require_admin();
        release_session();

        // User counts
        $userStats = $pdo->query(
            "SELECT
                COUNT(*) AS total_perdorues,
                SUM(CASE WHEN roli IN ('admin', 'super_admin') THEN 1 ELSE 0 END) AS admin_count,
                SUM(CASE WHEN roli = 'volunteer' THEN 1 ELSE 0 END) AS vullnetar_count,
                SUM(CASE WHEN statusi_llogarise = 'blocked' THEN 1 ELSE 0 END) AS bllokuar_count
            FROM Perdoruesi"
        )->fetch();

        // Event counts (A-04: COALESCE to prevent NULL)
        $eventStats = $pdo->query(
            "SELECT
                COUNT(*) AS total_evente,
                COALESCE(SUM(CASE WHEN data >= NOW() THEN 1 ELSE 0 END), 0) AS evente_te_ardhshme,
                COALESCE(SUM(CASE WHEN data < NOW() THEN 1 ELSE 0 END), 0) AS evente_te_kaluara
            FROM Eventi"
        )->fetch();

        // Application counts (A-04: COALESCE)
        $appStats = $pdo->query(
            "SELECT
                COUNT(*) AS total_aplikime,
                COALESCE(SUM(CASE WHEN LOWER(statusi) IN ('pending', 'në pritje', 'ne pritje') THEN 1 ELSE 0 END), 0) AS ne_pritje,
                COALESCE(SUM(CASE WHEN LOWER(statusi) IN ('approved', 'pranuar') THEN 1 ELSE 0 END), 0) AS pranuar,
                COALESCE(SUM(CASE WHEN LOWER(statusi) IN ('rejected', 'refuzuar') THEN 1 ELSE 0 END), 0) AS refuzuar
            FROM Aplikimi"
        )->fetch();

        $helpStats = $pdo->query(
            "SELECT
                COUNT(*) AS total_kerkesa,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('request', 'kërkesë', 'kerkese') AND LOWER(statusi) = 'open' THEN 1 ELSE 0 END), 0) AS kerkese_open,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('request', 'kërkesë', 'kerkese') AND LOWER(statusi) = 'filled' THEN 1 ELSE 0 END), 0) AS kerkese_filled,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('request', 'kërkesë', 'kerkese') AND LOWER(statusi) IN ('completed', 'closed') THEN 1 ELSE 0 END), 0) AS kerkese_completed,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('request', 'kërkesë', 'kerkese') AND LOWER(statusi) = 'cancelled' THEN 1 ELSE 0 END), 0) AS kerkese_cancelled,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('request', 'kërkesë', 'kerkese') AND LOWER(statusi) IN ('completed', 'closed', 'cancelled') THEN 1 ELSE 0 END), 0) AS kerkese_closed,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('offer', 'ofertë', 'oferte') AND LOWER(statusi) = 'open' THEN 1 ELSE 0 END), 0) AS oferte_open,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('offer', 'ofertë', 'oferte') AND LOWER(statusi) = 'filled' THEN 1 ELSE 0 END), 0) AS oferte_filled,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('offer', 'ofertë', 'oferte') AND LOWER(statusi) IN ('completed', 'closed') THEN 1 ELSE 0 END), 0) AS oferte_completed,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('offer', 'ofertë', 'oferte') AND LOWER(statusi) = 'cancelled' THEN 1 ELSE 0 END), 0) AS oferte_cancelled,
                COALESCE(SUM(CASE WHEN LOWER(tipi) IN ('offer', 'ofertë', 'oferte') AND LOWER(statusi) IN ('completed', 'closed', 'cancelled') THEN 1 ELSE 0 END), 0) AS oferte_closed,
                COALESCE(SUM(CASE WHEN moderation_status = 'pending_review' THEN 1 ELSE 0 END), 0) AS pending_moderation
             FROM Kerkesa_per_Ndihme"
        )->fetch(PDO::FETCH_ASSOC);

        $topCategories = $pdo->query(
            "SELECT k.emri, COUNT(e.id_eventi) AS event_count
             FROM Kategoria k
             LEFT JOIN Eventi e ON e.id_kategoria = k.id_kategoria
             GROUP BY k.id_kategoria
             ORDER BY event_count DESC
             LIMIT 5"
        )->fetchAll();

        // Recent applications (last 10)
        $recentApps = $pdo->query(
            "SELECT a.id_aplikimi, a.statusi, a.aplikuar_me,
                    p.emri AS vullnetari_emri, e.titulli AS eventi_titulli
             FROM Aplikimi a
             JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             ORDER BY a.aplikuar_me DESC
             LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC);
        
        // Include functions for normalizer
        // NOTE: functions.php is already loaded transitively via helpers.php
        // (the require_once here is a no-op but left for clarity).

        json_success([
            'users'              => $userStats,
            'events'             => $eventStats,
            'applications'       => $appStats,
            'help_requests'      => $helpStats,
            'top_categories'     => $topCategories,
            'recent_applications' => ts_normalize_rows($recentApps),
        ]);
        break;

    // ── MY STATS (Volunteer) ───────────────────────
    case 'my_stats':
        require_method('GET');
        $user = require_auth();

        $appStats = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_aplikime,
                SUM(CASE WHEN statusi = 'pending' THEN 1 ELSE 0 END) AS ne_pritje,
                SUM(CASE WHEN statusi = 'approved' THEN 1 ELSE 0 END) AS pranuar,
                SUM(CASE WHEN statusi = 'rejected' THEN 1 ELSE 0 END) AS refuzuar
             FROM Aplikimi
             WHERE id_perdoruesi = ?"
        );
        $appStats->execute([$user['id']]);

        $helpStats = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_kerkesa,
                SUM(CASE WHEN statusi IN ('open', 'filled') THEN 1 ELSE 0 END) AS te_aktive,
                SUM(CASE WHEN statusi = 'open' THEN 1 ELSE 0 END) AS te_hapura,
                SUM(CASE WHEN statusi = 'filled' THEN 1 ELSE 0 END) AS te_mbushura,
                SUM(CASE WHEN statusi IN ('completed', 'closed') THEN 1 ELSE 0 END) AS te_perfunduara,
                SUM(CASE WHEN statusi = 'cancelled' THEN 1 ELSE 0 END) AS te_anuluara,
                SUM(CASE WHEN statusi IN ('completed', 'closed', 'cancelled') THEN 1 ELSE 0 END) AS te_mbyllura
             FROM Kerkesa_per_Ndihme
             WHERE id_perdoruesi = ?"
        );
        $helpStats->execute([$user['id']]);

        $unreadNotifs = $pdo->prepare(
            'SELECT COUNT(*) FROM Njoftimi WHERE id_perdoruesi = ? AND is_read = 0'
        );
        $unreadNotifs->execute([$user['id']]);

        // Upcoming events the user is accepted to
        $upcomingEvents = $pdo->prepare(
            "SELECT e.titulli, e.data, e.vendndodhja
             FROM Aplikimi a
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             WHERE a.id_perdoruesi = ? AND a.statusi = 'approved' AND e.data >= NOW()
             ORDER BY e.data ASC
             LIMIT 5"
        );
        $upcomingEvents->execute([$user['id']]);

        json_success([
            'applications'      => $appStats->fetch(),
            'help_requests'     => $helpStats->fetch(),
            'unread_notifications' => (int) $unreadNotifs->fetchColumn(),
            'upcoming_events'   => $upcomingEvents->fetchAll(),
        ]);
        break;
// ── MONTHLY STATS (Charts) ─────────────────────────
case 'monthly':
    require_method('GET');
    require_admin();
    release_session();

    $monthly_apps = $pdo->query(
        "SELECT DATE_FORMAT(aplikuar_me, '%Y-%m') AS muaji, COUNT(*) AS total
         FROM Aplikimi
         GROUP BY muaji
         ORDER BY muaji ASC"
    )->fetchAll();

    $monthly_requests = $pdo->query(
        "SELECT DATE_FORMAT(krijuar_me, '%Y-%m') AS muaji, COUNT(*) AS total
         FROM Kerkesa_per_Ndihme
         GROUP BY muaji
         ORDER BY muaji ASC"
    )->fetchAll();

    $monthly_events = $pdo->query(
        "SELECT DATE_FORMAT(krijuar_me, '%Y-%m') AS muaji, COUNT(*) AS total
         FROM Eventi
         GROUP BY muaji
         ORDER BY muaji ASC"
    )->fetchAll();

    $apps_by_category = $pdo->query(
        "SELECT k.emri, COUNT(a.id_aplikimi) AS total
         FROM Kategoria k
         LEFT JOIN Eventi e ON e.id_kategoria = k.id_kategoria
         LEFT JOIN Aplikimi a ON a.id_eventi = e.id_eventi
         GROUP BY k.id_kategoria
         ORDER BY total DESC
         LIMIT 5"
    )->fetchAll();

    json_success([
        'monthly_apps'      => $monthly_apps,
        'monthly_requests'  => $monthly_requests,
        'monthly_events'    => $monthly_events,
        'apps_by_category'  => $apps_by_category,
    ]);
    break;
    // ── LIST REPORTS ───────────────────────────────
    case 'reports':
        require_method('GET');
        require_admin();
        $pagination = get_pagination();

        $countStmt = $pdo->query('SELECT COUNT(*) FROM Raporti');
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT r.*, p.emri AS gjeneruesi_emri
             FROM Raporti r
             JOIN Perdoruesi p ON p.id_perdoruesi = r.id_perdoruesi
             ORDER BY r.gjeneruar_me DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$pagination['limit'], $pagination['offset']]);

        json_success([
            'reports'     => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $pagination['page'],
            'limit'       => $pagination['limit'],
            'total_pages' => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── GENERATE REPORT ────────────────────────────
    case 'generate':
        require_method('POST');
        $admin = require_admin();
        $body  = get_json_body();
        $errors = [];

        $tipiRaportit = required_field($body, 'tipi_raportit', $errors);

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        $validReportTypes = ['Përmbledhje Mujore', 'Vullnetarë Aktivë', 'Analiza e Eventeve'];
        if (!in_array($tipiRaportit, $validReportTypes, true)) {
            json_error('Tipi i raportit nuk është i vlefshëm. Zgjidhni: ' . implode(', ', $validReportTypes), 422);
        }

        // Auto-generate report content based on type
        $content = '';

        switch ($tipiRaportit) {
            case 'Përmbledhje Mujore':
                $month = date('Y-m');
                $eventsThisMonth = $pdo->prepare(
                    "SELECT COUNT(*) FROM Eventi WHERE DATE_FORMAT(krijuar_me, '%Y-%m') = ?"
                );
                $eventsThisMonth->execute([$month]);

                $appsThisMonth = $pdo->prepare(
                    "SELECT COUNT(*) FROM Aplikimi WHERE DATE_FORMAT(aplikuar_me, '%Y-%m') = ?"
                );
                $appsThisMonth->execute([$month]);

                $content = "Raporti Mujor - $month\n"
                    . "Evente të reja: " . $eventsThisMonth->fetchColumn() . "\n"
                    . "Aplikime të reja: " . $appsThisMonth->fetchColumn();
                break;

            case 'Vullnetarë Aktivë':
                $activeVols = $pdo->query(
                    "SELECT COUNT(DISTINCT id_perdoruesi) FROM Aplikimi WHERE statusi = 'approved'"
                )->fetchColumn();
                $content = "Vullnetarë aktivë (me të paktën 1 aplikim të pranuar): $activeVols";
                break;

            case 'Analiza e Eventeve':
                $totalEvents    = $pdo->query("SELECT COUNT(*) FROM Eventi WHERE is_archived = 0")->fetchColumn();
                $activeEvents   = $pdo->query("SELECT COUNT(*) FROM Eventi WHERE is_archived = 0 AND statusi = 'active'")->fetchColumn();
                $completedEvs   = $pdo->query("SELECT COUNT(*) FROM Eventi WHERE statusi = 'completed'")->fetchColumn();
                $cancelledEvs   = $pdo->query("SELECT COUNT(*) FROM Eventi WHERE statusi = 'cancelled'")->fetchColumn();
                $totalApps      = $pdo->query("SELECT COUNT(*) FROM Aplikimi")->fetchColumn();
                $approvedApps   = $pdo->query("SELECT COUNT(*) FROM Aplikimi WHERE statusi = 'approved'")->fetchColumn();
                $avgAppsPerEv   = $totalEvents > 0 ? round((int)$totalApps / (int)$totalEvents, 1) : 0;

                $content = "Analiza e Eventeve\n"
                    . "Evente aktive: {$activeEvents}\n"
                    . "Evente të përfunduara: {$completedEvs}\n"
                    . "Evente të anuluara: {$cancelledEvs}\n"
                    . "Total eventeve (pa arkivuar): {$totalEvents}\n"
                    . "Total aplikimeve: {$totalApps}\n"
                    . "Aplikime të pranuara: {$approvedApps}\n"
                    . "Mesatare aplikimesh / event: {$avgAppsPerEv}";
                break;

            default:
                $content = $body['permbajtja'] ?? 'Raport i përgjithshëm i gjeneruar automatikisht.';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO Raporti (id_perdoruesi, tipi_raportit, permbajtja) VALUES (?, ?, ?)'
        );
        $stmt->execute([$admin['id'], $tipiRaportit, $content]);

        json_success([
            'id_raporti' => (int) $pdo->lastInsertId(),
            'message'    => 'Raporti u gjenerua me sukses.',
            'content'    => $content,
        ], 201);
        break;

    // ── PUBLIC LEADERBOARD ─────────────────────────
    case 'leaderboard':
        require_method('GET');
        $limit = min(max((int) ($_GET['limit'] ?? 20), 1), 50);

        // Get current viewer ID (for block filtering)
        $viewerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        // Build block exclusion clause if user is logged in
        $blockExclusionClause = '';
        if ($viewerId > 0) {
            $blockExclusionClause = "AND NOT EXISTS (
                SELECT 1 FROM user_blocks
                WHERE (blocker_id = ? AND blocked_id = p.id_perdoruesi)
                   OR (blocker_id = p.id_perdoruesi AND blocked_id = ?)
            )";
        }

        $query = "SELECT
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
                   AND p.profile_public = 1
                   {$blockExclusionClause}
                 ORDER BY score DESC, p.emri ASC
                 LIMIT {$limit}";

        if ($viewerId > 0) {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$viewerId, $viewerId]);
        } else {
            $stmt = $pdo->query($query);
        }

        $volunteers = ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

        json_success(['leaderboard' => $volunteers]);
        break;

    // ── ADMIN AUDIT LOG (Super Admin) ─────────────────
    case 'admin_log':
        require_method('GET');
        require_super_admin();
        release_session();
        $pagination  = get_pagination(30);
        $adminFilter = (int) ($_GET['admin_id'] ?? 0);
        $actionFilter = trim($_GET['veprim'] ?? '');
        $dateFrom    = trim($_GET['date_from'] ?? '');
        $dateTo      = trim($_GET['date_to'] ?? '');

        $where  = [];
        $params = [];
        if ($adminFilter > 0) {
            $where[]  = 'l.admin_id = ?';
            $params[] = $adminFilter;
        }
        if ($actionFilter) {
            $where[]  = 'l.veprim LIKE ?';
            $params[] = '%' . $actionFilter . '%';
        }
        if ($dateFrom) {
            $where[]  = 'l.krijuar_me >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo) {
            $where[]  = 'l.krijuar_me <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_log l $whereClause");
        $cntStmt->execute($params);
        $total = (int) $cntStmt->fetchColumn();

        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];
        $stmt = $pdo->prepare(
            "SELECT l.id, l.veprim, l.target_type, l.target_id, l.detaje, l.krijuar_me,
                    p.emri AS admin_emri, p.roli AS admin_roli
             FROM admin_log l
             JOIN Perdoruesi p ON p.id_perdoruesi = l.admin_id
             $whereClause
             ORDER BY l.krijuar_me DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute($params);
        json_success([
            'logs'        => $stmt->fetchAll(),
            'total'       => $total,
            'page'        => $pagination['page'],
            'total_pages' => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── DELETE REPORT ──────────────────────────────
    case 'delete_report':
        require_method('DELETE');
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) json_error('ID-ja e raportit është e pavlefshme.', 400);

        $stmt = $pdo->prepare('DELETE FROM Raporti WHERE id_raporti = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_error('Raporti nuk u gjet.', 404);
        json_success(['message' => 'Raporti u fshi.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: overview, my_stats, reports, generate, monthly, admin_log, delete_report.', 400);
}
