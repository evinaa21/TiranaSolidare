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

    // ── APPLY TO HELP REQUEST ─────────────────────
    case 'apply':
        require_method('POST');
        $user = require_auth();

        if ($user['roli'] === 'admin') {
            json_error('Administratorët nuk mund të aplikojnë për kërkesa ndihme.', 403);
        }

        $body = get_json_body();
        $requestId = isset($body['id_kerkese_ndihme']) ? (int) $body['id_kerkese_ndihme'] : 0;

        if ($requestId <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 422);
        }

        try {
            $check = $pdo->prepare(
                "SELECT kn.id_kerkese_ndihme, kn.id_perdoruesi, kn.titulli, kn.statusi, p.emri AS krijuesi_emri, p.email AS krijuesi_email
                 FROM Kerkesa_per_Ndihme kn
                 JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                 WHERE kn.id_kerkese_ndihme = ?"
            );
            $check->execute([$requestId]);
            $request = $check->fetch();

            if (!$request) {
                json_error('Kërkesa nuk u gjet.', 404);
            }

            if ($request['statusi'] !== 'open') {
                json_error('Mund të aplikoni vetëm për kërkesa të hapura.', 422);
            }

            if ((int) $request['id_perdoruesi'] === (int) $user['id']) {
                json_error('Nuk mund të aplikoni në kërkesën tuaj.', 409);
            }

            $dup = $pdo->prepare(
                'SELECT id_aplikimi_kerkese FROM Aplikimi_Kerkese WHERE id_kerkese_ndihme = ? AND id_perdoruesi = ? LIMIT 1'
            );
            $dup->execute([$requestId, $user['id']]);

            if ($dup->fetch()) {
                json_error('Ju keni aplikuar tashmë për këtë kërkesë.', 409);
            }

            $insert = $pdo->prepare(
                "INSERT INTO Aplikimi_Kerkese (id_kerkese_ndihme, id_perdoruesi, statusi)
                 VALUES (?, ?, 'pending')"
            );
            $insert->execute([$requestId, $user['id']]);
            $applicationId = (int) $pdo->lastInsertId();

            $ownerMessage = "{$user['emri']} aplikoi për kërkesën tuaj \"{$request['titulli']}\".";
            $reqLink = "/TiranaSolidare/views/help_requests.php?id={$requestId}";
            $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)');
            $notifStmt->execute([$request['id_perdoruesi'], $ownerMessage, 'aplikim_kerkese', 'help_request', $requestId, $reqLink]);

            if (filter_var($request['krijuesi_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                send_notification_email(
                    $request['krijuesi_email'],
                    $request['krijuesi_emri'] ?? 'Përdorues',
                    'Aplikim i ri për kërkesën tuaj',
                    $ownerMessage
                );
            }

            json_success([
                'id_aplikimi_kerkese' => $applicationId,
                'message' => 'Aplikimi u dërgua me sukses.',
            ], 201);
        } catch (\PDOException $e) {
            error_log('help_requests apply: ' . $e->getMessage());
            if ((int) $e->getCode() === 42) {
                json_error('Mungon tabela e aplikimeve për kërkesa. Përditësoni bazën e të dhënave.', 500);
            }
            json_error('Gabim gjatë dërgimit të aplikimit.', 500);
        }
        break;

    // ── MY HELP REQUEST APPLICATIONS ──────────────
    case 'my_applications':
        require_method('GET');
        $user = require_auth();
        $pagination = get_pagination();

        try {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi_Kerkese WHERE id_perdoruesi = ?');
            $countStmt->execute([$user['id']]);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT ak.*, kn.titulli, kn.tipi, kn.statusi AS kerkesa_statusi, kn.krijuar_me AS kerkesa_krijuar_me,
                        kn.id_perdoruesi AS pronari_id, p.emri AS pronari_emri
                 FROM Aplikimi_Kerkese ak
                 JOIN Kerkesa_per_Ndihme kn ON kn.id_kerkese_ndihme = ak.id_kerkese_ndihme
                 JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                 WHERE ak.id_perdoruesi = ?
                 ORDER BY ak.aplikuar_me DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$user['id'], $pagination['limit'], $pagination['offset']]);

            json_success([
                'applications' => $stmt->fetchAll(),
                'total' => $total,
                'page' => $pagination['page'],
                'limit' => $pagination['limit'],
                'total_pages' => (int) ceil($total / $pagination['limit']),
            ]);
        } catch (\Exception $e) {
            error_log('help_requests my_applications: ' . $e->getMessage());
            json_error('Gabim gjatë marrjes së aplikimeve.', 500);
        }
        break;

    // ── APPLICANTS FOR A REQUEST ──────────────────
    case 'applicants':
        require_method('GET');
        $user = require_auth();
        $requestId = (int) ($_GET['id'] ?? 0);
        $pagination = get_pagination();

        if ($requestId <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
            $ownerCheck = $pdo->prepare('SELECT id_perdoruesi FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
            $ownerCheck->execute([$requestId]);
            $requestRow = $ownerCheck->fetch();

            if (!$requestRow) {
                json_error('Kërkesa nuk u gjet.', 404);
            }

            if ((int) $requestRow['id_perdoruesi'] !== (int) $user['id'] && $user['roli'] !== 'admin') {
                json_error('Nuk keni leje për këtë veprim.', 403);
            }

            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi_Kerkese WHERE id_kerkese_ndihme = ?');
            $countStmt->execute([$requestId]);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT ak.id_aplikimi_kerkese, ak.statusi, ak.aplikuar_me,
                        p.id_perdoruesi, p.emri, p.email
                 FROM Aplikimi_Kerkese ak
                 JOIN Perdoruesi p ON p.id_perdoruesi = ak.id_perdoruesi
                 WHERE ak.id_kerkese_ndihme = ?
                 ORDER BY ak.aplikuar_me DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$requestId, $pagination['limit'], $pagination['offset']]);

            json_success([
                'applicants' => $stmt->fetchAll(),
                'total' => $total,
                'page' => $pagination['page'],
                'limit' => $pagination['limit'],
                'total_pages' => (int) ceil($total / $pagination['limit']),
            ]);
        } catch (\Exception $e) {
            error_log('help_requests applicants: ' . $e->getMessage());
            json_error('Gabim gjatë marrjes së aplikantëve.', 500);
        }
        break;

    // ── CONTACT AN APPLICANT BY EMAIL ─────────────
    case 'contact_applicant':
        require_method('POST');
        $user = require_auth();
        $body = get_json_body();

        $requestId = isset($body['id_kerkese_ndihme']) ? (int) $body['id_kerkese_ndihme'] : 0;
        $applicantId = isset($body['id_aplikuesi']) ? (int) $body['id_aplikuesi'] : 0;
        $subject = trim((string) ($body['subjekti'] ?? 'Kontakt për kërkesën tuaj në Tirana Solidare'));
        $message = trim((string) ($body['mesazhi'] ?? ''));

        if ($requestId <= 0 || $applicantId <= 0) {
            json_error('Kërkesa ose aplikuesi është i pavlefshëm.', 422);
        }
        if ($message === '') {
            json_error('Mesazhi është i detyrueshëm.', 422);
        }
        if (mb_strlen($message) > 2000) {
            json_error('Mesazhi nuk mund të kalojë 2000 karaktere.', 422);
        }
        if (mb_strlen($subject) > 180) {
            json_error('Subjekti nuk mund të kalojë 180 karaktere.', 422);
        }

        try {
            $requestStmt = $pdo->prepare(
                'SELECT kn.id_perdoruesi, kn.titulli, p.email AS owner_email
                 FROM Kerkesa_per_Ndihme kn
                 JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                 WHERE kn.id_kerkese_ndihme = ? LIMIT 1'
            );
            $requestStmt->execute([$requestId]);
            $request = $requestStmt->fetch();

            if (!$request) {
                json_error('Kërkesa nuk u gjet.', 404);
            }
            if ((int) $request['id_perdoruesi'] !== (int) $user['id'] && $user['roli'] !== 'admin') {
                json_error('Nuk keni leje për këtë veprim.', 403);
            }

            $appCheck = $pdo->prepare(
                'SELECT id_aplikimi_kerkese FROM Aplikimi_Kerkese
                 WHERE id_kerkese_ndihme = ? AND id_perdoruesi = ? LIMIT 1'
            );
            $appCheck->execute([$requestId, $applicantId]);
            if (!$appCheck->fetch()) {
                json_error('Aplikuesi nuk gjendet për këtë kërkesë.', 404);
            }

            $applicantStmt = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
            $applicantStmt->execute([$applicantId]);
            $applicant = $applicantStmt->fetch();

            if (!$applicant || !filter_var($applicant['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                json_error('Email-i i aplikuesit nuk është i vlefshëm.', 422);
            }

            $ownerEmail = trim((string) ($request['owner_email'] ?? ''));
            $fullMessage = $message;
            if ($ownerEmail !== '' && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $fullMessage .= "\n\nKontakti i postuesit: {$ownerEmail}";
            }

            $sent = send_notification_email(
                $applicant['email'],
                $applicant['emri'] ?? 'Volunteer',
                $subject,
                $fullMessage
            );

            if (!$sent) {
                json_error('Email-i nuk u dërgua. Kontrolloni konfigurimin e mail-it.', 500);
            }

            $notifMessage = "Postuesi i kërkesës \"{$request['titulli']}\" ju kontaktoi me email.";
            $reqLink = "/TiranaSolidare/views/help_requests.php?id={$requestId}";
            $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)');
            $notifStmt->execute([$applicantId, $notifMessage, 'aplikim_kerkese', 'help_request', $requestId, $reqLink]);

            json_success(['message' => 'Email-i u dërgua me sukses.']);
        } catch (\Exception $e) {
            error_log('help_requests contact_applicant: ' . $e->getMessage());
            json_error('Gabim gjatë dërgimit të email-it.', 500);
        }
        break;

    // ── UPDATE APPLICANT STATUS (Accept/Reject) ───
    case 'update_applicant_status':
        require_method('PUT');
        $user = require_auth();
        $body = get_json_body();

        $applicationId = isset($body['id_aplikimi_kerkese']) ? (int) $body['id_aplikimi_kerkese'] : 0;
        $newStatus = trim((string) ($body['statusi'] ?? ''));

        if ($applicationId <= 0) {
            json_error('ID-ja e aplikimit është e pavlefshme.', 422);
        }
        if (!in_array($newStatus, ['approved', 'rejected'], true)) {
            json_error("Statusi duhet të jetë 'approved' ose 'rejected'.", 422);
        }

        try {
            $appStmt = $pdo->prepare(
                'SELECT ak.id_aplikimi_kerkese, ak.id_kerkese_ndihme, ak.id_perdoruesi, ak.statusi,
                        kn.id_perdoruesi AS pronari_id, kn.titulli,
                        p.emri AS aplikuesi_emri, p.email AS aplikuesi_email
                 FROM Aplikimi_Kerkese ak
                 JOIN Kerkesa_per_Ndihme kn ON kn.id_kerkese_ndihme = ak.id_kerkese_ndihme
                 JOIN Perdoruesi p ON p.id_perdoruesi = ak.id_perdoruesi
                 WHERE ak.id_aplikimi_kerkese = ?'
            );
            $appStmt->execute([$applicationId]);
            $app = $appStmt->fetch();

            if (!$app) {
                json_error('Aplikimi nuk u gjet.', 404);
            }

            if ((int) $app['pronari_id'] !== (int) $user['id'] && $user['roli'] !== 'admin') {
                json_error('Nuk keni leje për këtë veprim.', 403);
            }

            $update = $pdo->prepare('UPDATE Aplikimi_Kerkese SET statusi = ? WHERE id_aplikimi_kerkese = ?');
            $update->execute([$newStatus, $applicationId]);

            $statusLabel = $newStatus === 'approved' ? 'pranua' : 'refuzua';
            $notifMessage = "Aplikimi juaj për \"{$app['titulli']}\" u {$statusLabel}.";
            $reqLink = "/TiranaSolidare/views/help_requests.php?id={$app['id_kerkese_ndihme']}";
            $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)');
            $notifStmt->execute([$app['id_perdoruesi'], $notifMessage, 'aplikim_kerkese', 'help_request', $app['id_kerkese_ndihme'], $reqLink]);

            if (filter_var($app['aplikuesi_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                send_notification_email(
                    $app['aplikuesi_email'],
                    $app['aplikuesi_emri'] ?? 'Volunteer',
                    "Aplikimi juaj u {$statusLabel}",
                    $notifMessage
                );
            }

            json_success(['message' => "Statusi i aplikimit u ndryshua në '{$newStatus}'."]);
        } catch (\Exception $e) {
            error_log('help_requests update_applicant_status: ' . $e->getMessage());
            json_error('Gabim gjatë përditësimit të statusit.', 500);
        }
        break;

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
                    ORDER BY 
                    CASE WHEN kn.statusi = 'open' THEN 0 ELSE 1 END ASC,
                    kn.krijuar_me DESC
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
                "SELECT kn.*, p.emri AS krijuesi_emri
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

        if (!in_array($tipi, ['request', 'offer'], true)) {
            $errors[] = "Tipi duhet të jetë 'request' ose 'offer'.";
        }

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        // Validate input lengths
        if ($lenErr = validate_length($titulli, 3, 200, 'titulli')) {
            json_error($lenErr, 422);
        }
        if ($pershkrimi !== '' && ($lenErr = validate_length($pershkrimi, 0, 5000, 'pershkrimi'))) {
            json_error($lenErr, 422);
        }

        // Validate image URL if provided
        if ($imazhi && !validate_image_url($imazhi)) {
            json_error('URL-ja e imazhit nuk është e vlefshme.', 422);
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO Kerkesa_per_Ndihme (id_perdoruesi, tipi, titulli, pershkrimi, statusi, imazhi, vendndodhja, latitude, longitude)
                 VALUES (?, ?, ?, ?, 'open', ?, ?, ?, ?)"
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

        if ($existing['id_perdoruesi'] != $user['id'] && $user['roli'] !== 'admin') {
            json_error('Nuk keni leje për të ndryshuar këtë kërkesë.', 403);
        }

        // Block updates on closed requests
        if ($existing['statusi'] === 'closed') {
            json_error('Kërkesat e mbyllura nuk mund të ndryshohen.', 422);
        }

        // Validate tipi if provided
        if (array_key_exists('tipi', $body) && !in_array($body['tipi'], ['request', 'offer'], true)) {
            json_error("Tipi duhet të jetë 'request' ose 'offer'.", 422);
        }

        // Validate pershkrimi length if provided
        if (array_key_exists('pershkrimi', $body) && $body['pershkrimi'] !== '' && ($lenErr = validate_length($body['pershkrimi'], 0, 5000, 'pershkrimi'))) {
            json_error($lenErr, 422);
        }

        // Validate titulli length if provided
        if (array_key_exists('titulli', $body) && ($lenErr = validate_length($body['titulli'], 3, 200, 'titulli'))) {
            json_error($lenErr, 422);
        }

        // Validate image URL if provided
        if (array_key_exists('imazhi', $body) && $body['imazhi'] && !validate_image_url($body['imazhi'])) {
            json_error('URL-ja e imazhit nuk është e vlefshme.', 422);
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

            if ($existing['id_perdoruesi'] != $user['id'] && $user['roli'] !== 'admin') {
                json_error('Nuk keni leje.', 403);
            }

            $pdo->prepare("UPDATE Kerkesa_per_Ndihme SET statusi = 'closed' WHERE id_kerkese_ndihme = ?")
                ->execute([$id]);

            // Notify the owner if closed by admin (A-02)
            if ($existing['id_perdoruesi'] != $user['id']) {
                $message = "Kërkesa juaj \"{$existing['titulli']}\" u mbyll nga një administrator.";
                $reqLink = "/TiranaSolidare/views/help_requests.php?id={$id}";
                $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)');
                $notifStmt->execute([$existing['id_perdoruesi'], $message, 'admin_veprim', 'help_request', $id, $reqLink]);

                $userContact = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
                $userContact->execute([$existing['id_perdoruesi']]);
                $recipient = $userContact->fetch();
                if ($recipient && filter_var($recipient['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    send_notification_email(
                        $recipient['email'],
                        $recipient['emri'] ?? 'Volunteer',
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

            if ($existing['id_perdoruesi'] != $user['id'] && $user['roli'] !== 'admin') {
                json_error('Nuk keni leje.', 403);
            }

            if ($existing['statusi'] !== 'closed') {
                json_error('Kërkesa është tashmë e hapur.', 400);
            }

            $pdo->prepare("UPDATE Kerkesa_per_Ndihme SET statusi = 'open' WHERE id_kerkese_ndihme = ?")
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
    $user = require_auth();
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_error('ID-ja e kërkesës është e pavlefshme.', 400);
    }

    try {
        $check = $pdo->prepare('SELECT id_perdoruesi FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
        $check->execute([$id]);
        $existing = $check->fetch();

        if (!$existing) {
            json_error('Kërkesa nuk u gjet.', 404);
        }

        if ($existing['id_perdoruesi'] != $user['id'] && $user['roli'] !== 'admin') {
            json_error('Nuk keni leje.', 403);
        }

        $stmt = $pdo->prepare('DELETE FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
        $stmt->execute([$id]);

        json_success(['message' => 'Kërkesa u fshi.']);
    } catch (\Exception $e) {
        error_log('help_requests delete: ' . $e->getMessage());
        json_error('Gabim gjatë fshirjes të kërkesës.', 500);
    }
    break;

    // ── APPLICATIONS BY USER (Admin) ────────────────
    case 'by_user':
        require_method('GET');
        require_admin();
        $targetId = (int) ($_GET['id'] ?? 0);

        if ($targetId <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT ak.id_aplikimi_kerkese, ak.id_kerkese_ndihme, ak.id_perdoruesi,
                        ak.statusi AS aplikimi_statusi, ak.aplikuar_me,
                        kn.titulli, kn.tipi, kn.statusi AS kerkesa_statusi,
                        kn.krijuar_me AS kerkesa_krijuar_me,
                        p.emri AS pronari_emri  
                 FROM Aplikimi_Kerkese ak
                 JOIN Kerkesa_per_Ndihme kn ON kn.id_kerkese_ndihme = ak.id_kerkese_ndihme
                 JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                 WHERE ak.id_perdoruesi = ?
                 ORDER BY ak.aplikuar_me DESC"
            );
            $stmt->execute([$targetId]);
            json_success(['applications' => $stmt->fetchAll()]);
        } catch (\Exception $e) {
            error_log('help_requests by_user: ' . $e->getMessage());
            json_error('Gabim gjatë marrjes së aplikimeve.', 500);
        }
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, get, create, update, close, reopen, delete, apply, my_applications, applicants, contact_applicant, by_user.', 400);
}
