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
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── LIST HELP REQUESTS ─────────────────────────
    case 'list':
        require_method('GET');
        $pagination = get_pagination();

        try {
            // Optional filters
            $tipi    = $_GET['tipi'] ?? null;     // Kërkesë | Ofertë
            $statusi = $_GET['statusi'] ?? null;   // Open | Closed
            $userId  = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
            $search  = isset($_GET['search']) ? trim($_GET['search']) : '';

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
            if ($search !== '') {
                $where[]  = '(kn.titulli LIKE ? OR kn.pershkrimi LIKE ?)';
                $params[] = "%$search%";
                $params[] = "%$search%";
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
        } catch (\Exception $e) {
            error_log('help_requests list: ' . $e->getMessage());
            json_error('Gabim gjatë marrjes të kërkesave.', 500);
        }
        break;

    // ── GET SINGLE REQUEST ─────────────────────────
    case 'get':
        require_method('GET');
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
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
        } catch (\Exception $e) {
            error_log('help_requests get: ' . $e->getMessage());
            json_error('Gabim gjatë marrjes të kërkesës.', 500);
        }
        break;

    // ── CREATE HELP REQUEST ────────────────────────
    case 'create':
        require_method('POST');
        $user   = require_auth();
        $body   = get_json_body();
        $errors = [];

        $titulli      = required_field($body, 'titulli', $errors);
        $pershkrimi   = $body['pershkrimi'] ?? '';
        $tipi         = $body['tipi'] ?? '';
        $imazhi       = $body['imazhi'] ?? null;
        $vendndodhja  = $body['vendndodhja'] ?? null;
        $latitude     = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $longitude    = isset($body['longitude']) ? (float) $body['longitude'] : null;

        if (!in_array($tipi, ['Kërkesë', 'Ofertë'], true)) {
            $errors[] = "Tipi duhet të jetë 'Kërkesë' ose 'Ofertë'.";
        }

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        // Validate input lengths
        if ($lenErr = validate_length($titulli, 3, 200, 'titulli')) {
            json_error($lenErr, 422);
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO Kerkesa_per_Ndihme (id_perdoruesi, tipi, titulli, pershkrimi, statusi, imazhi, vendndodhja, latitude, longitude)
                 VALUES (?, ?, ?, ?, 'Open', ?, ?, ?, ?)"
            );
            $stmt->execute([$user['id'], $tipi, $titulli, $pershkrimi, $imazhi, $vendndodhja, $latitude, $longitude]);

            json_success([
                'id_kerkese_ndihme' => (int) $pdo->lastInsertId(),
                'message'           => 'Kërkesa u krijua me sukses.',
            ], 201);
        } catch (\Exception $e) {
            error_log('help_requests create: ' . $e->getMessage());
            json_error('Gabim gjatë ruajtjes të kërkesës.', 500);
        }
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

        $allowed = ['titulli', 'pershkrimi', 'tipi', 'vendndodhja', 'latitude', 'longitude', 'imazhi'];
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
        try {
            $pdo->prepare("UPDATE Kerkesa_per_Ndihme SET " . implode(', ', $sets) . " WHERE id_kerkese_ndihme = ?")
                ->execute($params);

            json_success(['message' => 'Kërkesa u përditësua.']);
        } catch (\Exception $e) {
            error_log('help_requests update: ' . $e->getMessage());
            json_error('Gabim gjatë përditësimit të kërkesës.', 500);
        }
        break;

    // ── CLOSE REQUEST ──────────────────────────────
    case 'close':
        require_method('PUT');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
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

            // Notify the owner if closed by admin (A-02)
            if ($existing['id_perdoruesi'] != $user['id']) {
                $message = "Kërkesa juaj \"{$existing['titulli']}\" u mbyll nga një administrator.";
                $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi) VALUES (?, ?)');
                $notifStmt->execute([$existing['id_perdoruesi'], $message]);

                $userContact = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
                $userContact->execute([$existing['id_perdoruesi']]);
                $recipient = $userContact->fetch();
                if ($recipient && filter_var($recipient['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    send_notification_email(
                        $recipient['email'],
                        $recipient['emri'] ?? 'Vullnetar',
                        'Njoftim i ri nga Tirana Solidare',
                        $message
                    );
                }
            }

            json_success(['message' => 'Kërkesa u mbyll.']);
        } catch (\Exception $e) {
            error_log('help_requests close: ' . $e->getMessage());
            json_error('Gabim gjatë mbylljes të kërkesës.', 500);
        }
        break;

    // ── REOPEN REQUEST (A-03) ─────────────────────
    case 'reopen':
        require_method('PUT');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
            $check = $pdo->prepare('SELECT * FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
            $check->execute([$id]);
            $existing = $check->fetch();

            if (!$existing) {
                json_error('Kërkesa nuk u gjet.', 404);
            }

            if ($existing['id_perdoruesi'] != $user['id'] && $user['roli'] !== 'Admin') {
                json_error('Nuk keni leje.', 403);
            }

            if ($existing['statusi'] !== 'Closed') {
                json_error('Kërkesa është tashmë e hapur.', 400);
            }

            $pdo->prepare("UPDATE Kerkesa_per_Ndihme SET statusi = 'Open' WHERE id_kerkese_ndihme = ?")
                ->execute([$id]);

            json_success(['message' => 'Kërkesa u rihap.']);
        } catch (\Exception $e) {
            error_log('help_requests reopen: ' . $e->getMessage());
            json_error('Gabim gjatë rihapjes të kërkesës.', 500);
        }
        break;

    // ── DELETE REQUEST ─────────────────────────────
    case 'delete':
        require_method('DELETE');
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
            $stmt = $pdo->prepare('DELETE FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                json_error('Kërkesa nuk u gjet.', 404);
            }

            json_success(['message' => 'Kërkesa u fshi.']);
        } catch (\Exception $e) {
            error_log('help_requests delete: ' . $e->getMessage());
            json_error('Gabim gjatë fshirjes të kërkesës.', 500);
        }
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, get, create, update, close, reopen, delete.', 400);
}
