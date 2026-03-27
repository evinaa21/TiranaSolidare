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

switch ($action) {

    // ── LIST / FILTER EVENTS ───────────────────────
    case 'list':
        require_method('GET');
        $pagination = get_pagination();

        // Optional filters
        $categoryId = isset($_GET['category']) ? (int) $_GET['category'] : null;
        $search     = isset($_GET['search']) ? trim($_GET['search']) : '';
        $dateFrom   = $_GET['date_from'] ?? null;
        $dateTo     = $_GET['date_to'] ?? null;

        $where  = ['e.is_archived = 0'];
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
        $sql = "SELECT e.*, k.emri AS kategoria_emri, p.emri AS krijuesi_emri
                FROM Eventi e
                LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
                LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
                $whereSQL
                ORDER BY 
                CASE WHEN e.data >= NOW() THEN 0 ELSE 1 END ASC,
                CASE WHEN e.data >= NOW() THEN e.data ELSE e.data END ASC
                LIMIT ? OFFSET ?";

        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $events = $stmt->fetchAll();

        json_success([
            'events'      => $events,
            'total'        => $total,
            'page'         => $pagination['page'],
            'limit'        => $pagination['limit'],
            'total_pages'  => (int) ceil($total / $pagination['limit']),
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
            "SELECT e.*, k.emri AS kategoria_emri, p.emri AS krijuesi_emri,
                    (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi) AS total_aplikime,
                    (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi AND a.statusi = 'approved') AS pranuar_count
             FROM Eventi e
             LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
             LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
             WHERE e.id_eventi = ? AND e.is_archived = 0"
        );
        $stmt->execute([$id]);
        $event = $stmt->fetch();

        if (!$event) {
            json_error('Eventi nuk u gjet.', 404);
        }

        json_success($event);
        break;

    // ── CREATE EVENT ───────────────────────────────
    case 'create':
        require_method('POST');
        $admin = require_admin();
        $body  = get_json_body();
        $errors = [];

        $titulli      = required_field($body, 'titulli', $errors);
        $pershkrimi   = $body['pershkrimi'] ?? '';
        $data_eventi  = required_field($body, 'data', $errors);
        $vendndodhja  = required_field($body, 'vendndodhja', $errors);
        $id_kategoria = isset($body['id_kategoria']) ? (int) $body['id_kategoria'] : null;
        $banner       = $body['banner'] ?? null;
        $latitude     = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $longitude    = isset($body['longitude']) ? (float) $body['longitude'] : null;
        $kapaciteti   = isset($body['kapaciteti']) && $body['kapaciteti'] !== '' ? (int) $body['kapaciteti'] : null;

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
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

        $stmt = $pdo->prepare(
            "INSERT INTO Eventi (id_perdoruesi, id_kategoria, titulli, pershkrimi, kapaciteti, data, vendndodhja, latitude, longitude, banner)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $admin['id'], $id_kategoria, $titulli,
            $pershkrimi, $kapaciteti, $data_eventi, $vendndodhja, $latitude, $longitude, $banner,
        ]);

        $newId = (int) $pdo->lastInsertId();

        log_admin_action($admin['id'], 'create_event', 'event', $newId, ['titulli' => $titulli]);

        json_success(['id_eventi' => $newId, 'message' => 'Eventi u krijua me sukses.'], 201);
        break;

    // ── UPDATE EVENT ───────────────────────────────
    case 'update':
        require_method('PUT');
        require_admin();
        $id   = (int) ($_GET['id'] ?? 0);
        $body = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        // Check existence (exclude archived)
        $check = $pdo->prepare('SELECT id_eventi, data, is_archived FROM Eventi WHERE id_eventi = ?');
        $check->execute([$id]);
        $event = $check->fetch();
        if (!$event) {
            json_error('Eventi nuk u gjet.', 404);
        }

        // Block editing archived events
        if (!empty($event['is_archived'])) {
            json_error('Eventet e arkivuara nuk mund të ndryshohen.', 422);
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

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE Eventi SET " . implode(', ', $sets) . " WHERE id_eventi = ?");
        $stmt->execute($params);

        log_admin_action($_SESSION['user_id'], 'update_event', 'event', $id);

        json_success(['message' => 'Eventi u përditësua me sukses.']);
        break;

    // ── DELETE / ARCHIVE EVENT ─────────────────────
    case 'delete':
        require_method('DELETE');
        $admin = require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

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

            // Notify all applicants that the event was cancelled
            $eventStmt = $pdo->prepare('SELECT titulli FROM Eventi WHERE id_eventi = ?');
            $eventStmt->execute([$id]);
            $eventTitle = $eventStmt->fetchColumn();

            $applicants = $pdo->prepare(
                "SELECT DISTINCT id_perdoruesi FROM Aplikimi WHERE id_eventi = ? AND statusi IN ('approved', 'pending')"
            );
            $applicants->execute([$id]);
            $notifStmt = $pdo->prepare(
                'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $msg = "Eventi \"{$eventTitle}\" u anulua.";
            $eventLink = "/TiranaSolidare/views/events.php";
            foreach ($applicants as $app) {
                $notifStmt->execute([$app['id_perdoruesi'], $msg, 'admin_veprim', 'event', $id, $eventLink]);
            }

            log_admin_action($admin['id'], 'archive_event', 'event', $id, ['titulli' => $eventTitle]);
            json_success(['message' => 'Eventi u arkivua (soft-delete) me sukses.']);
        } else {
            // Hard-delete: no applications
            $stmt = $pdo->prepare('DELETE FROM Eventi WHERE id_eventi = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                json_error('Eventi nuk u gjet.', 404);
            }

            log_admin_action($admin['id'], 'delete_event', 'event', $id);
            json_success(['message' => 'Eventi u fshi me sukses.']);
        }
        break;

    // ── COMPLETE EVENT ─────────────────────────────
    case 'complete':
        require_method('PUT');
        $admin = require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $check = $pdo->prepare('SELECT id_eventi, titulli, data, statusi FROM Eventi WHERE id_eventi = ? AND is_archived = 0');
        $check->execute([$id]);
        $event = $check->fetch();

        if (!$event) {
            json_error('Eventi nuk u gjet.', 404);
        }
        if ($event['statusi'] !== 'active') {
            json_error('Vetëm eventet aktive mund të shënohen si të përfunduara.', 422);
        }
        if (strtotime($event['data']) > time()) {
            json_error('Eventi nuk ka përfunduar ende — data është në të ardhmen.', 422);
        }

        $pdo->prepare("UPDATE Eventi SET statusi = 'completed' WHERE id_eventi = ?")->execute([$id]);

        log_admin_action($admin['id'], 'complete_event', 'event', $id, ['titulli' => $event['titulli']]);
        json_success(['message' => 'Eventi u shënua si i përfunduar.']);
        break;

    // ── CANCEL EVENT ──────────────────────────────
    case 'cancel':
        require_method('PUT');
        $admin = require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $check = $pdo->prepare('SELECT id_eventi, titulli, statusi FROM Eventi WHERE id_eventi = ? AND is_archived = 0');
        $check->execute([$id]);
        $event = $check->fetch();

        if (!$event) {
            json_error('Eventi nuk u gjet.', 404);
        }
        if ($event['statusi'] === 'cancelled') {
            json_error('Eventi është tashmë i anuluar.', 422);
        }

        $pdo->prepare("UPDATE Eventi SET statusi = 'cancelled' WHERE id_eventi = ?")->execute([$id]);

        // Notify all applicants
        $applicants = $pdo->prepare(
            "SELECT DISTINCT id_perdoruesi FROM Aplikimi WHERE id_eventi = ? AND statusi IN ('approved', 'pending')"
        );
        $applicants->execute([$id]);
        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $msg = "Eventi \"{$event['titulli']}\" u anulua.";
        $link = "/TiranaSolidare/views/events.php?id={$id}";
        foreach ($applicants as $app) {
            $notifStmt->execute([$app['id_perdoruesi'], $msg, 'admin_veprim', 'event', $id, $link]);
        }

        log_admin_action($admin['id'], 'cancel_event', 'event', $id, ['titulli' => $event['titulli']]);
        json_success(['message' => 'Eventi u anulua me sukses.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, get, create, update, delete, complete, cancel.', 400);
}
