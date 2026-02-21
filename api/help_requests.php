<?php
/**
 * api/help_requests.php
 * ---------------------------------------------------
 * Aid / Help Request Management API (Kerkesa_per_Ndihme)
 *
 * GET    ?action=list              – List requests (filterable)
 * GET    ?action=get&id=<id>       – Single request detail
 * POST   ?action=create            – Submit a new request/offer
 * PUT    ?action=update&id=<id>    – Update a request
 * PUT    ?action=close&id=<id>     – Close a request (owner / Admin)
 * DELETE ?action=delete&id=<id>    – Delete a request (Admin)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/api_helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── LIST HELP REQUESTS ─────────────────────────
    case 'list':
        require_method('GET');
        $pagination = get_pagination();

        // Optional filters
        $tipi    = $_GET['tipi'] ?? null;     // Kërkesë | Ofertë
        $statusi = $_GET['statusi'] ?? null;   // Open | Closed
        $userId  = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;

        $where  = [];
        $params = [];

        if ($tipi) {
            $where[]  = 'kn.tipi = ?';
            $params[] = $tipi;
        }
        if ($statusi) {
            $where[]  = 'kn.statusi = ?';
            $params[] = $statusi;
        }
        if ($userId) {
            $where[]  = 'kn.id_perdoruesi = ?';
            $params[] = $userId;
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM Kerkesa_per_Ndihme kn $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT kn.*, p.emri AS krijuesi_emri
                FROM Kerkesa_per_Ndihme kn
                JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                $whereSQL
                ORDER BY kn.krijuar_me DESC
                LIMIT ? OFFSET ?";

        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        json_success([
            'requests'    => $requests,
            'total'       => $total,
            'page'        => $pagination['page'],
            'limit'       => $pagination['limit'],
            'total_pages' => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── GET SINGLE REQUEST ─────────────────────────
    case 'get':
        require_method('GET');
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            "SELECT kn.*, p.emri AS krijuesi_emri, p.email AS krijuesi_email
             FROM Kerkesa_per_Ndihme kn
             JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
             WHERE kn.id_kerkese_ndihme = ?"
        );
        $stmt->execute([$id]);
        $request = $stmt->fetch();

        if (!$request) {
            json_error('Kërkesa nuk u gjet.', 404);
        }

        json_success($request);
        break;

    // ── CREATE HELP REQUEST ────────────────────────
    case 'create':
        require_method('POST');
        $user   = require_auth();
        $body   = get_json_body();
        $errors = [];

        $titulli    = required_field($body, 'titulli', $errors);
        $pershkrimi = $body['pershkrimi'] ?? '';
        $tipi       = $body['tipi'] ?? '';

        if (!in_array($tipi, ['Kërkesë', 'Ofertë'], true)) {
            $errors[] = "Tipi duhet të jetë 'Kërkesë' ose 'Ofertë'.";
        }

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO Kerkesa_per_Ndihme (id_perdoruesi, tipi, titulli, pershkrimi, statusi)
             VALUES (?, ?, ?, ?, 'Open')"
        );
        $stmt->execute([$user['id'], $tipi, $titulli, $pershkrimi]);

        json_success([
            'id_kerkese_ndihme' => (int) $pdo->lastInsertId(),
            'message'           => 'Kërkesa u krijua me sukses.',
        ], 201);
        break;

    // ── UPDATE HELP REQUEST ────────────────────────
    case 'update':
        require_method('PUT');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);
        $body = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        // Check ownership (or admin)
        $check = $pdo->prepare('SELECT * FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
        $check->execute([$id]);
        $existing = $check->fetch();

        if (!$existing) {
            json_error('Kërkesa nuk u gjet.', 404);
        }

        if ($existing['id_perdoruesi'] != $user['id'] && $user['roli'] !== 'Admin') {
            json_error('Nuk keni leje për të ndryshuar këtë kërkesë.', 403);
        }

        $allowed = ['titulli', 'pershkrimi', 'tipi'];
        $sets    = [];
        $params  = [];

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
        $pdo->prepare("UPDATE Kerkesa_per_Ndihme SET " . implode(', ', $sets) . " WHERE id_kerkese_ndihme = ?")
            ->execute($params);

        json_success(['message' => 'Kërkesa u përditësua.']);
        break;

    // ── CLOSE REQUEST ──────────────────────────────
    case 'close':
        require_method('PUT');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        $check = $pdo->prepare('SELECT * FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
        $check->execute([$id]);
        $existing = $check->fetch();

        if (!$existing) {
            json_error('Kërkesa nuk u gjet.', 404);
        }

        if ($existing['id_perdoruesi'] != $user['id'] && $user['roli'] !== 'Admin') {
            json_error('Nuk keni leje.', 403);
        }

        $pdo->prepare("UPDATE Kerkesa_per_Ndihme SET statusi = 'Closed' WHERE id_kerkese_ndihme = ?")
            ->execute([$id]);

        json_success(['message' => 'Kërkesa u mbyll.']);
        break;

    // ── DELETE REQUEST ─────────────────────────────
    case 'delete':
        require_method('DELETE');
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare('DELETE FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            json_error('Kërkesa nuk u gjet.', 404);
        }

        json_success(['message' => 'Kërkesa u fshi.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, get, create, update, close, delete.', 400);
}
