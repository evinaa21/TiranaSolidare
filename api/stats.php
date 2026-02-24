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

        // User counts
        $userStats = $pdo->query(
            "SELECT
                COUNT(*) AS total_perdorues,
                SUM(CASE WHEN roli = 'Admin' THEN 1 ELSE 0 END) AS admin_count,
                SUM(CASE WHEN roli = 'Vullnetar' THEN 1 ELSE 0 END) AS vullnetar_count,
                SUM(CASE WHEN statusi_llogarise = 'Bllokuar' THEN 1 ELSE 0 END) AS bllokuar_count
             FROM Perdoruesi"
        )->fetch();

        // Event counts
        $eventStats = $pdo->query(
            "SELECT
                COUNT(*) AS total_evente,
                SUM(CASE WHEN data >= NOW() THEN 1 ELSE 0 END) AS evente_te_ardhshme,
                SUM(CASE WHEN data < NOW() THEN 1 ELSE 0 END) AS evente_te_kaluara
             FROM Eventi"
        )->fetch();

        // Application counts
        $appStats = $pdo->query(
            "SELECT
                COUNT(*) AS total_aplikime,
                SUM(CASE WHEN statusi = 'Në pritje' THEN 1 ELSE 0 END) AS ne_pritje,
                SUM(CASE WHEN statusi = 'Pranuar' THEN 1 ELSE 0 END) AS pranuar,
                SUM(CASE WHEN statusi = 'Refuzuar' THEN 1 ELSE 0 END) AS refuzuar
             FROM Aplikimi"
        )->fetch();

        // Help request counts
        $helpStats = $pdo->query(
            "SELECT
                COUNT(*) AS total_kerkesa,
                SUM(CASE WHEN statusi = 'Open' THEN 1 ELSE 0 END) AS te_hapura,
                SUM(CASE WHEN statusi = 'Closed' THEN 1 ELSE 0 END) AS te_mbyllura,
                SUM(CASE WHEN tipi = 'Kërkesë' THEN 1 ELSE 0 END) AS kerkesa,
                SUM(CASE WHEN tipi = 'Ofertë' THEN 1 ELSE 0 END) AS oferta
             FROM Kerkesa_per_Ndihme"
        )->fetch();

        // Top categories by event count
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
        )->fetchAll();

        json_success([
            'users'              => $userStats,
            'events'             => $eventStats,
            'applications'       => $appStats,
            'help_requests'      => $helpStats,
            'top_categories'     => $topCategories,
            'recent_applications' => $recentApps,
        ]);
        break;

    // ── MY STATS (Volunteer) ───────────────────────
    case 'my_stats':
        require_method('GET');
        $user = require_auth();

        $appStats = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_aplikime,
                SUM(CASE WHEN statusi = 'Në pritje' THEN 1 ELSE 0 END) AS ne_pritje,
                SUM(CASE WHEN statusi = 'Pranuar' THEN 1 ELSE 0 END) AS pranuar,
                SUM(CASE WHEN statusi = 'Refuzuar' THEN 1 ELSE 0 END) AS refuzuar
             FROM Aplikimi
             WHERE id_perdoruesi = ?"
        );
        $appStats->execute([$user['id']]);

        $helpStats = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_kerkesa,
                SUM(CASE WHEN statusi = 'Open' THEN 1 ELSE 0 END) AS te_hapura,
                SUM(CASE WHEN statusi = 'Closed' THEN 1 ELSE 0 END) AS te_mbyllura
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
             WHERE a.id_perdoruesi = ? AND a.statusi = 'Pranuar' AND e.data >= NOW()
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
                    "SELECT COUNT(DISTINCT id_perdoruesi) FROM Aplikimi WHERE statusi = 'Pranuar'"
                )->fetchColumn();
                $content = "Vullnetarë aktivë (me të paktën 1 aplikim të pranuar): $activeVols";
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

    default:
        json_error('Veprim i panjohur. Përdorni: overview, my_stats, reports, generate.', 400);
}
