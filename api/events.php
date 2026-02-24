<?php
/**
 * api/events.php
 * ---------------------------------------------------
 * Events REST API – full CRUD
 *
 * GET    ?action=list              – List / filter events (public)
 * GET    ?action=get&id=<id>       – Single event detail (public)
 * POST   ?action=create            – Create event  (Admin)
 * PUT    ?action=update&id=<id>    – Update event  (Admin)
 * DELETE ?action=delete&id=<id>    – Delete event  (Admin)
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

        $where  = [];
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
                ORDER BY e.data DESC
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
                    (SELECT COUNT(*) FROM Aplikimi a WHERE a.id_eventi = e.id_eventi) AS total_aplikime
             FROM Eventi e
             LEFT JOIN Kategoria k ON k.id_kategoria = e.id_kategoria
             LEFT JOIN Perdoruesi p ON p.id_perdoruesi = e.id_perdoruesi
             WHERE e.id_eventi = ?"
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

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO Eventi (id_perdoruesi, id_kategoria, titulli, pershkrimi, data, vendndodhja, banner)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $admin['id'], $id_kategoria, $titulli,
            $pershkrimi, $data_eventi, $vendndodhja, $banner,
        ]);

        $newId = (int) $pdo->lastInsertId();

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

        // Check existence
        $check = $pdo->prepare('SELECT id_eventi FROM Eventi WHERE id_eventi = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            json_error('Eventi nuk u gjet.', 404);
        }

        // Build dynamic SET clause
        $allowed = ['titulli', 'pershkrimi', 'data', 'vendndodhja', 'id_kategoria', 'banner'];
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

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE Eventi SET " . implode(', ', $sets) . " WHERE id_eventi = ?");
        $stmt->execute($params);

        json_success(['message' => 'Eventi u përditësua me sukses.']);
        break;

    // ── DELETE EVENT ───────────────────────────────
    case 'delete':
        require_method('DELETE');
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        // Delete related applications first (cascade)
        $pdo->prepare('DELETE FROM Aplikimi WHERE id_eventi = ?')->execute([$id]);
        $stmt = $pdo->prepare('DELETE FROM Eventi WHERE id_eventi = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            json_error('Eventi nuk u gjet.', 404);
        }

        json_success(['message' => 'Eventi u fshi me sukses.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, get, create, update, delete.', 400);
}
