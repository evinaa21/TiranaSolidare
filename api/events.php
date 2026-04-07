<?php
/**
 * api/events.php
 * ---------------------------------------------------
 * Events REST API – full CRUD + lifecycle
 *
 * GET    ?action=list              – List / filter events (public)
 * GET    ?action=get&id=<id>       – Single event detail (public)
 * POST   ?action=create            – Create event  (Admin)
 * PUT    ?action=update&id=<id>    – Update event  (Admin)
 * DELETE ?action=delete&id=<id>    – Delete/archive event (Admin)
 * PUT    ?action=complete&id=<id>  – Mark event completed (Admin)
 * PUT    ?action=cancel&id=<id>    – Cancel event + notify (Admin)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

function ts_event_creator_display_sql(): string
{
    return "CASE
        WHEN p.roli = 'organizer' AND COALESCE(p.organization_name, '') <> '' THEN p.organization_name
        WHEN p.roli IN ('admin', 'super_admin') THEN 'Tirana Solidare'
        ELSE p.emri
    END AS krijuesi_emri";
}

function ts_load_managed_event(PDO $pdo, int $eventId, array $manager): array
{
    $stmt = $pdo->prepare('SELECT id_eventi, id_perdoruesi, titulli, data, statusi, is_archived FROM Eventi WHERE id_eventi = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        json_error('Eventi nuk u gjet.', 404);
    }

    if (!ts_can_manage_event($manager, $event)) {
        json_error('Nuk keni leje për të menaxhuar këtë event.', 403);
    }

    return $event;
}

switch ($action) {

    // ── LIST / FILTER EVENTS ───────────────────────
    case 'list':
        require_method('GET');
        release_session();
        $pagination = get_pagination();

        // Optional filters
        $categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
        $search     = isset($_GET['search']) ? trim($_GET['search']) : '';
        $dateFrom   = $_GET['date_from'] ?? null;
        $dateTo     = $_GET['date_to'] ?? null;

        $where  = [ts_public_event_filter_sql('e')];
        $params = [];

        if ($categoryId) {
            $where[]  = 'e.id_kategoria = ?';
            $params[] = $categoryId;
        }
        if ($search !== '') {
            $where[]  = '(e.titulli LIKE ? OR e.pershkrimi LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($dateFrom) {
            $where[]  = 'e.data >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[]  = 'e.data <= ?';
            $params[] = $dateTo;
        }

        // Date range preset filter
        $dateRange = $_GET['dateRange'] ?? null;
        if ($dateRange === 'week') {
            $where[] = 'e.data >= DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY) AND e.data < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY), INTERVAL 7 DAY)';
        } elseif ($dateRange === 'month') {
            $where[] = 'e.data >= DATE_FORMAT(CURDATE(), "%Y-%m-01") AND e.data < DATE_ADD(DATE_FORMAT(CURDATE(), "%Y-%m-01"), INTERVAL 1 MONTH)';
        } elseif ($dateRange === 'past3') {
            $where[] = 'e.data >= DATE_SUB(DATE_FORMAT(CURDATE(), "%Y-%m-01"), INTERVAL 3 MONTH) AND e.data <= CURDATE()';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM Eventi e $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch page
        $sql = "SELECT e.*, k.emri AS kategoria_emri,
            " . ts_event_creator_display_sql() . "
                FROM Eventi e
                LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
                LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
                $whereSQL
                ORDER BY 
                CASE WHEN e.data >= NOW() THEN 0 ELSE 1 END ASC,
                CASE WHEN e.data >= NOW() THEN e.data ELSE NULL END ASC,
                CASE WHEN e.data <  NOW() THEN e.data ELSE NULL END DESC
                LIMIT ? OFFSET ?";

        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $events = ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

        json_success([
            'events'      => $events,
            'total'        => $total,
            'page'         => $pagination['page'],
            'limit'        => $pagination['limit'],
            'total_pages'  => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── DASHBOARD LIST / MANAGE EVENTS ───────────
    case 'manage_list':
        require_method('GET');
        $manager = require_event_manager();
        release_session();
        $pagination = get_pagination(20, 100);

        $where = ['e.is_archived = 0'];
        $params = [];
        $status = trim((string) ($_GET['status'] ?? ''));
        $search = trim((string) ($_GET['search'] ?? ''));
        $categoryId = isset($_GET['category']) ? (int) $_GET['category'] : 0;
        $dateRange = $_GET['dateRange'] ?? null;

        if (ts_is_organizer_role_value($manager['roli'] ?? null)) {
            $where[] = 'e.id_perdoruesi = ?';
            $params[] = $manager['id'];
        }
if ($status === 'past') {
    $where[] = "e.data < NOW() AND e.statusi NOT IN ('cancelled','completed')";
} elseif ($status === 'filled') {
    $where[] = "e.data >= NOW() AND e.statusi NOT IN ('cancelled','completed') AND e.kapaciteti > 0 AND (SELECT COUNT(*) FROM Aplikimi af2 WHERE af2.id_eventi = e.id_eventi AND af2.statusi = 'approved') >= e.kapaciteti";
} elseif ($status !== '') {
    $where[] = 'e.statusi = ?';
    $params[] = $status;
}
        if ($search !== '') {
            $where[] = '(e.titulli LIKE ? OR e.vendndodhja LIKE ? OR e.pershkrimi LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where[] = 'e.id_kategoria = ?';
            $params[] = $categoryId;
        }
        if ($dateRange === 'week') {
            $where[] = 'e.data >= DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY) AND e.data < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY), INTERVAL 7 DAY)';
        } elseif ($dateRange === 'month') {
            $where[] = 'e.data >= DATE_FORMAT(CURDATE(), "%Y-%m-01") AND e.data < DATE_ADD(DATE_FORMAT(CURDATE(), "%Y-%m-01"), INTERVAL 1 MONTH)';
        } elseif ($dateRange === 'past3') {
            $where[] = 'e.data >= DATE_SUB(DATE_FORMAT(CURDATE(), "%Y-%m-01"), INTERVAL 3 MONTH) AND e.data <= CURDATE()';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM Eventi e $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT e.*, k.emri AS kategoria_emri,
            " . ts_event_creator_display_sql() . ",
            (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi) AS total_aplikime,
            CASE
                WHEN e.statusi IN ('cancelled','completed') THEN e.statusi
                WHEN e.data < NOW() THEN 'past'
                WHEN e.kapaciteti > 0 AND (SELECT COUNT(*) FROM Aplikimi af WHERE af.id_eventi = e.id_eventi AND af.statusi = 'approved') >= e.kapaciteti THEN 'filled'
                ELSE e.statusi
            END AS display_status
     FROM Eventi e
     LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
     LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
     $whereSql
     ORDER BY
        CASE WHEN e.data >= NOW() THEN 0 ELSE 1 END ASC,
        CASE WHEN e.data >= NOW() THEN e.data END ASC,
        CASE WHEN e.data <  NOW() THEN e.data END DESC
     LIMIT ? OFFSET ?"
);
        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];
        $stmt->execute($params);

        json_success([
            'events' => ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
            'page' => $pagination['page'],
            'limit' => $pagination['limit'],
            'total_pages' => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── GET SINGLE EVENT ───────────────────────────
    case 'get':
        require_method('GET');
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            "SELECT e.*, k.emri AS kategoria_emri,
                " . ts_event_creator_display_sql() . ",
                    (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi) AS total_aplikime,
                    (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi AND a.statusi = 'approved') AS pranuar_count
             FROM Eventi e
             LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
             LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
            WHERE e.id_eventi = ? AND " . ts_public_event_filter_sql('e')
        );
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event && isset($_SESSION['user_id']) && ts_is_event_manager_role_value($_SESSION['roli'] ?? '')) {
            $manager = require_event_manager();
            $managerStmt = $pdo->prepare(
                "SELECT e.*, k.emri AS kategoria_emri,
                    " . ts_event_creator_display_sql() . ",
                    (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi) AS total_aplikime,
                    (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi AND a.statusi = 'approved') AS pranuar_count
                 FROM Eventi e
                 LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
                 LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
                 WHERE e.id_eventi = ? AND e.is_archived = 0"
            );
            $managerStmt->execute([$id]);
            $managedEvent = $managerStmt->fetch(PDO::FETCH_ASSOC);
            if ($managedEvent && ts_can_manage_event($manager, $managedEvent)) {
                $event = $managedEvent;
            }
        }

        if (!$event) {
            json_error('Eventi nuk u gjet.', 404);
        }
        $event = ts_normalize_row($event);

        json_success($event);
        break;

    case 'create':
        require_method('POST');
        $manager = require_event_manager();
        $body  = get_json_body();
        $errors = [];

        $titulli      = required_field($body, 'titulli', $errors);
        $pershkrimi   = $body['pershkrimi'] ?? '';
        $data_eventi  = required_field($body, 'data', $errors);
        $vendndodhja  = required_field($body, 'vendndodhja', $errors);
        $id_kategoria = isset($body['id_kategoria']) && $body['id_kategoria'] !== '' ? (int) $body['id_kategoria'] : null;
        $banner       = $body['banner'] ?? null;
        $latitude     = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $longitude    = isset($body['longitude']) ? (float) $body['longitude'] : null;
        $kapaciteti   = isset($body['kapaciteti']) && $body['kapaciteti'] !== '' ? (int) $body['kapaciteti'] : null;

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        // Capacity must be a positive integer if supplied
        if ($kapaciteti !== null && $kapaciteti < 1) {
            json_error('Kapaciteti duhet të jetë të paktën 1.', 422);
        }

        // Validate geographic coordinates are within real-world bounds
        if ($latitude !== null && ($latitude < -90.0 || $latitude > 90.0)) {
            json_error('Gjerësia gjeografike (latitude) duhet të jetë ndërmjet -90 dhe 90.', 422);
        }
        if ($longitude !== null && ($longitude < -180.0 || $longitude > 180.0)) {
            json_error('Gjatësia gjeografike (longitude) duhet të jetë ndërmjet -180 dhe 180.', 422);
        }

        // Validate event date is in the future (L-02)
        if ($data_eventi && strtotime($data_eventi) <= time()) {
            json_error('Data e eventit duhet të jetë në të ardhmen.', 422);
        }

        // Validate banner URL (H-11)
        if ($banner && !validate_image_url($banner)) {
            json_error('URL-ja e banner-it nuk është e vlefshme.', 422);
        }

        // Validate input lengths
        if ($lenErr = validate_length($titulli, 3, 200, 'titulli')) {
            json_error($lenErr, 422);
        }
        if ($pershkrimi && ($lenErr = validate_length($pershkrimi, 0, 5000, 'pershkrimi'))) {
            json_error($lenErr, 422);
        }

        $initialStatus = ts_is_organizer_role_value($manager['roli'] ?? null) ? 'pending_review' : 'active';

        $stmt = $pdo->prepare(
            "INSERT INTO Eventi (id_perdoruesi, id_kategoria, titulli, pershkrimi, kapaciteti, data, vendndodhja, latitude, longitude, banner, statusi)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $manager['id'], $id_kategoria, $titulli,
            $pershkrimi, $kapaciteti, $data_eventi, $vendndodhja, $latitude, $longitude, $banner, $initialStatus,
        ]);

        $newId = (int) $pdo->lastInsertId();

        log_admin_action($manager['id'], 'create_event', 'event', $newId, ['titulli' => $titulli, 'statusi' => $initialStatus]);

        if ($initialStatus === 'pending_review') {
            $reviewers = $pdo->query(
                "SELECT id_perdoruesi, emri, email
                 FROM Perdoruesi
                 WHERE roli IN ('admin', 'super_admin') AND statusi_llogarise = 'active'"
            )->fetchAll(PDO::FETCH_ASSOC);
            $notifStmt = $pdo->prepare(
                'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $message = ($manager['organization_name'] ?: $manager['emri']) . ' krijoi një event të ri në pritje të miratimit: "' . $titulli . '".';
            foreach ($reviewers as $reviewer) {
                $notifStmt->execute([$reviewer['id_perdoruesi'], $message, 'event_review', 'event', $newId, ts_app_path('views/dashboard.php')]);
                if (filter_var($reviewer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    send_notification_email(
                        $reviewer['email'],
                        $reviewer['emri'] ?? 'Administrator',
                        'Event i ri në pritje të miratimit — Tirana Solidare',
                        $message,
                        [
                            'action_url' => '/views/dashboard.php',
                            'action_label' => 'Shiko në panel',
                        ]
                    );
                }
            }
        }

        json_success([
            'id_eventi' => $newId,
            'statusi' => $initialStatus,
            'message' => $initialStatus === 'pending_review'
                ? 'Eventi u krijua dhe po pret miratimin e administratorit.'
                : 'Eventi u krijua me sukses.',
        ], 201);
        break;

    // ── UPDATE EVENT ───────────────────────────────
    case 'update':
        require_method('PUT');
        $manager = require_event_manager();
        $id   = (int) ($_GET['id'] ?? 0);
        $body = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $event = ts_load_managed_event($pdo, $id, $manager);

        // Block editing archived events
        if (!empty($event['is_archived'])) {
            json_error('Eventet e arkivuara nuk mund të ndryshohen.', 422);
        }

        // Block editing cancelled events
        if ($event['statusi'] === 'cancelled') {
            json_error('Eventet e anuluara nuk mund të ndryshohen.', 422);
        }

        // Block editing past events
        if (!empty($event['data']) && strtotime($event['data']) < time()) {
            json_error('Eventet e kaluara nuk mund të ndryshohen.', 403);
        }

        // Build dynamic SET clause
        $allowed = ['titulli', 'pershkrimi', 'data', 'vendndodhja', 'latitude', 'longitude', 'id_kategoria', 'banner', 'kapaciteti'];
        $sets   = [];
        $params = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $body)) {
                $sets[]   = "$field = ?";
                $params[] = $body[$field];
            }
        }

        if (empty($sets)) {
            json_error('Asnjë fushë për të përditësuar.', 400);
        }

        // Validate date is in the future if being updated
        if (isset($body['data']) && strtotime($body['data']) <= time()) {
            json_error('Data e eventit duhet të jetë në të ardhmen.', 422);
        }

        // Validate banner URL if provided
        if (isset($body['banner']) && $body['banner'] && !validate_image_url($body['banner'])) {
            json_error('URL-ja e banner-it nuk është e vlefshme.', 422);
        }

        // Validate geographic coordinates are within real-world bounds
        if (isset($body['latitude']) && $body['latitude'] !== null) {
            $latVal = (float) $body['latitude'];
            if ($latVal < -90.0 || $latVal > 90.0) {
                json_error('Gjerësia gjeografike (latitude) duhet të jetë ndërmjet -90 dhe 90.', 422);
            }
        }
        if (isset($body['longitude']) && $body['longitude'] !== null) {
            $lngVal = (float) $body['longitude'];
            if ($lngVal < -180.0 || $lngVal > 180.0) {
                json_error('Gjatësia gjeografike (longitude) duhet të jetë ndërmjet -180 dhe 180.', 422);
            }
        }

        // Guard against setting capacity below current approved count
        if (array_key_exists('kapaciteti', $body) && $body['kapaciteti'] !== null) {
            $approvedCntStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ? AND statusi = 'approved'"
            );
            $approvedCntStmt->execute([$id]);
            $approvedCount = (int) $approvedCntStmt->fetchColumn();
            if ((int) $body['kapaciteti'] < $approvedCount) {
                json_error("Kapaciteti nuk mund të jetë më i vogël se numri i aprovimeve aktuale ({$approvedCount}).", 422);
            }
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE Eventi SET " . implode(', ', $sets) . " WHERE id_eventi = ?");
        $stmt->execute($params);

        // If key scheduling fields changed, notify all approved applicants
        $changedFields = array_intersect(array_keys($body), ['data', 'vendndodhja', 'latitude', 'longitude']);
        if (!empty($changedFields)) {
            $approvedVols = $pdo->prepare(
                "SELECT DISTINCT a.id_perdoruesi, p.emri, p.email
                 FROM Aplikimi a
                 JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
                 WHERE a.id_eventi = ? AND a.statusi = 'approved'"
            );
            $approvedVols->execute([$id]);

            $updEvt = $pdo->prepare('SELECT titulli, data, vendndodhja FROM Eventi WHERE id_eventi = ?');
            $updEvt->execute([$id]);
            $updData = $updEvt->fetch();

            $changeNames = implode(', ', array_unique(array_map(fn($f) => match($f) {
                'data' => 'data/ora', 'vendndodhja' => 'vendndodhja',
                'latitude', 'longitude' => 'vendndodhja', default => $f
            }, $changedFields)));

            $updateNotifMsg  = "Detajet e eventit \"{$updData['titulli']}\" u ndryshuan ({$changeNames}). Kontrolloni informacionin e ri.";
            $updateNotifLink = ts_app_path('views/events.php?id=' . $id);
            $updNotifStmt = $pdo->prepare(
                'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($approvedVols as $vol) {
                $updNotifStmt->execute([$vol['id_perdoruesi'], $updateNotifMsg, 'admin_veprim', 'event', $id, $updateNotifLink]);
                if (filter_var($vol['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    send_notification_email(
                        $vol['email'],
                        $vol['emri'],
                        "Ndryshim në eventin \"{$updData['titulli']}\" — Tirana Solidare",
                        $updateNotifMsg
                    );
                }
            }
        }

        log_admin_action($manager['id'], 'update_event', 'event', $id);

        json_success(['message' => 'Eventi u përditësua me sukses.']);
        break;

    // ── DELETE / ARCHIVE EVENT ─────────────────────
    case 'delete':
        require_method('DELETE');
        $manager = require_event_manager();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $eventMeta = ts_load_managed_event($pdo, $id, $manager);

        // Check if event has applications
        $appCount = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ?');
        $appCount->execute([$id]);
        $hasApps = (int) $appCount->fetchColumn() > 0;

        if ($hasApps) {
            // Soft-delete: archive the event
            $stmt = $pdo->prepare('UPDATE Eventi SET is_archived = 1, statusi = ? WHERE id_eventi = ?');
            $stmt->execute(['cancelled', $id]);

            if ($stmt->rowCount() === 0) {
                json_error('Eventi nuk u gjet.', 404);
            }

            // Notify all applicants that the event was cancelled (in-app + email)
            $eventStmt = $pdo->prepare('SELECT titulli FROM Eventi WHERE id_eventi = ?');
            $eventStmt->execute([$id]);
            $eventTitle = $eventStmt->fetchColumn();

            // Dërgo njoftim vetëm nëse eventi nuk ka kaluar ende.
            if (strtotime($eventMeta['data']) > time()) {
                $applicants = $pdo->prepare(
                    "SELECT DISTINCT a.id_perdoruesi, p.emri, p.email
                     FROM Aplikimi a
                     JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
                     WHERE a.id_eventi = ? AND a.statusi IN ('approved', 'pending')"
                );
                $applicants->execute([$id]);
                $notifStmt = $pdo->prepare(
                    'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
                );
                $msg = "Eventi \"{$eventTitle}\" u anulua.";
                $eventLink = ts_app_path('views/events.php');
                foreach ($applicants as $app) {
                    $notifStmt->execute([$app['id_perdoruesi'], $msg, 'admin_veprim', 'event', $id, $eventLink]);
                    if (filter_var($app['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                        send_notification_email(
                            $app['email'],
                            $app['emri'],
                            'Eventi u anulua — Tirana Solidare',
                            $msg
                        );
                    }
                }
            }

            // Withdraw aplikimet pasi njoftimet u dërguan.
            $pdo->prepare(
                "UPDATE Aplikimi SET statusi = 'withdrawn'
                 WHERE id_eventi = ? AND statusi IN ('approved', 'pending')"
            )->execute([$id]);

            log_admin_action($manager['id'], 'archive_event', 'event', $id, ['titulli' => $eventTitle]);
            json_success(['message' => 'Eventi u fshi me sukses.']);
        } else {
            // Hard-delete: no applications
            $stmt = $pdo->prepare('DELETE FROM Eventi WHERE id_eventi = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                json_error('Eventi nuk u gjet.', 404);
            }

            log_admin_action($manager['id'], 'delete_event', 'event', $id, ['titulli' => $eventMeta['titulli'] ?? null]);
            json_success(['message' => 'Eventi u fshi me sukses.']);
        }
        break;

    // ── COMPLETE EVENT ─────────────────────────────
    case 'complete':
        require_method('PUT');
        $manager = require_event_manager();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $event = ts_load_managed_event($pdo, $id, $manager);
        if (!empty($event['is_archived'])) {
            json_error('Eventi nuk u gjet.', 404);
        }
        if ($event['statusi'] !== 'active') {
            json_error('Vetëm eventet aktive mund të shënohen si të përfunduara.', 422);
        }
        if (strtotime($event['data']) > time()) {
            json_error('Eventi nuk ka përfunduar ende — data është në të ardhmen.', 422);
        }

        $pdo->prepare("UPDATE Eventi SET statusi = 'completed' WHERE id_eventi = ?")->execute([$id]);

        // Notify and thank all approved volunteers
        $volunteers = $pdo->prepare(
            "SELECT DISTINCT a.id_perdoruesi, p.emri, p.email
             FROM Aplikimi a
             JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
             WHERE a.id_eventi = ? AND a.statusi = 'approved'"
        );
        $volunteers->execute([$id]);
        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $completeMsg  = "Faleminderit për pjesëmarrjen në eventin \"{$event['titulli']}\"! Kontributi juaj bëri ndryshimin.";
        $completeLink = ts_app_path('views/events.php?id=' . $id);
        foreach ($volunteers as $vol) {
            $notifStmt->execute([$vol['id_perdoruesi'], $completeMsg, 'admin_veprim', 'event', $id, $completeLink]);
            if (filter_var($vol['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                send_notification_email(
                    $vol['email'],
                    $vol['emri'],
                    "Faleminderit për evt. \"{$event['titulli']}\" — Tirana Solidare",
                    $completeMsg
                );
            }
        }

        log_admin_action($manager['id'], 'complete_event', 'event', $id, ['titulli' => $event['titulli']]);
        json_success(['message' => 'Eventi u shënua si i përfunduar.']);
        break;

    // ── CANCEL EVENT ──────────────────────────────
    case 'cancel':
        require_method('PUT');
        $manager = require_event_manager();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $event = ts_load_managed_event($pdo, $id, $manager);
        if (!empty($event['is_archived'])) {
            json_error('Eventi nuk u gjet.', 404);
        }
        if (in_array($event['statusi'], ['cancelled', 'completed'], true)) {
            json_error('Eventet e përfunduara ose të anuluara nuk mund të anulohen.', 422);
        }

        $pdo->prepare("UPDATE Eventi SET statusi = 'cancelled' WHERE id_eventi = ?")->execute([$id]);

        // Notify all applicants (in-app + email)
        $applicants = $pdo->prepare(
            "SELECT DISTINCT a.id_perdoruesi, p.emri, p.email
             FROM Aplikimi a
             JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
             WHERE a.id_eventi = ? AND a.statusi IN ('approved', 'pending')"
        );
        $applicants->execute([$id]);
        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $msg  = "Eventi \"{$event['titulli']}\" u anulua.";
        $link = ts_app_path('views/events.php?id=' . $id);
        foreach ($applicants as $app) {
            $notifStmt->execute([$app['id_perdoruesi'], $msg, 'admin_veprim', 'event', $id, $link]);
            if (filter_var($app['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                send_notification_email(
                    $app['email'],
                    $app['emri'],
                    'Eventi u anulua — Tirana Solidare',
                    $msg
                );
            }
        }

        log_admin_action($manager['id'], 'cancel_event', 'event', $id, ['titulli' => $event['titulli']]);
        json_success(['message' => 'Eventi u anulua me sukses.']);
        break;

    // ── APPROVE ORGANIZER EVENT ───────────────────
    case 'approve':
        require_method('PUT');
        $admin = require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            'SELECT e.id_eventi, e.titulli, e.statusi, e.id_perdoruesi, p.emri, p.email, p.organization_name
             FROM Eventi e
             LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
             WHERE e.id_eventi = ? AND e.is_archived = 0'
        );
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$event) {
            json_error('Eventi nuk u gjet.', 404);
        }
        if (($event['statusi'] ?? '') !== 'pending_review') {
            json_error('Vetëm eventet në pritje të miratimit mund të aktivizohen.', 422);
        }

        $pdo->prepare("UPDATE Eventi SET statusi = 'active' WHERE id_eventi = ?")->execute([$id]);

        $message = 'Eventi juaj "' . ($event['titulli'] ?? '') . '" u miratua dhe tani është publik.';
        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $notifStmt->execute([$event['id_perdoruesi'], $message, 'event_review', 'event', $id, ts_app_path('views/events.php?id=' . $id)]);

        if (filter_var($event['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            send_notification_email(
                $event['email'],
                $event['organization_name'] ?: ($event['emri'] ?? 'Organizator'),
                'Eventi u miratua — Tirana Solidare',
                $message
            );
        }

        log_admin_action($admin['id'], 'approve_event', 'event', $id, ['titulli' => $event['titulli']]);
        json_success(['message' => 'Eventi u miratua me sukses.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, manage_list, get, create, update, delete, complete, cancel, approve.', 400);
}
