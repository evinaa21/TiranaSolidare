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

function help_request_parse_matching_config(array $body, ?array $existing = null): array
{
    $errors = [];
    $matchingMode = $existing['matching_mode'] ?? 'open';

    if (array_key_exists('matching_mode', $body)) {
        $providedMode = strtolower(trim((string) $body['matching_mode']));
        if (!in_array($providedMode, TS_HELP_REQUEST_MATCHING_MODES, true)) {
            $errors[] = "matching_mode duhet të jetë 'single', 'limited' ose 'open'.";
        } else {
            $matchingMode = $providedMode;
        }
    } else {
        $matchingMode = ts_help_request_matching_mode($matchingMode !== '' ? (string) $matchingMode : null, isset($existing['capacity_total']) ? (int) $existing['capacity_total'] : null);
    }

    $capacitySource = array_key_exists('capacity_total', $body)
        ? $body['capacity_total']
        : ($existing['capacity_total'] ?? null);
    $capacityTotal = null;

    if ($capacitySource !== null && $capacitySource !== '') {
        $validated = filter_var($capacitySource, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100],
        ]);
        if ($validated === false) {
            $errors[] = 'capacity_total duhet të jetë një numër i plotë midis 1 dhe 100.';
        } else {
            $capacityTotal = (int) $validated;
        }
    }

    if ($matchingMode === 'open') {
        $capacityTotal = null;
    } elseif ($matchingMode === 'single') {
        $capacityTotal = 1;
    } elseif ($matchingMode === 'limited') {
        if ($capacityTotal === null) {
            $errors[] = 'Vendosni capacity_total për matching_mode limited.';
        } elseif ($capacityTotal < 2) {
            $errors[] = 'capacity_total duhet të jetë të paktën 2 për matching_mode limited.';
        }
    }

    return [
        'matching_mode' => $matchingMode,
        'capacity_total' => $capacityTotal,
        'errors' => $errors,
    ];
}

function help_request_status_filter_values(string $status): array
{
    return match (ts_help_request_normalize_status($status)) {
        'completed' => ['completed', 'closed'],
        'open' => ['open'],
        'filled' => ['filled'],
        'cancelled' => ['cancelled'],
        default => [ts_help_request_normalize_status($status)],
    };
}

function help_request_insert_notification(PDO $pdo, int $userId, string $message, int $requestId, string $type = 'aplikim_kerkese'): void
{
    $link = "/TiranaSolidare/views/help_requests.php?id={$requestId}";
    $stmt = $pdo->prepare(
        'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $message, $type, 'help_request', $requestId, $link]);
}

function help_request_email_recipient(?string $email, ?string $name, string $subject, string $message): void
{
    if (filter_var((string) $email, FILTER_VALIDATE_EMAIL)) {
        send_notification_email(
            (string) $email,
            $name !== null && $name !== '' ? $name : 'Përdorues',
            $subject,
            $message
        );
    }
}

function help_request_promote_waitlisted(PDO $pdo, int $requestId, int $limit = 1): array
{
    $limit = max(0, $limit);
    if ($limit === 0) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT ak.id_aplikimi_kerkese, ak.id_perdoruesi, p.emri, p.email
         FROM Aplikimi_Kerkese ak
         JOIN Perdoruesi p ON p.id_perdoruesi = ak.id_perdoruesi
         WHERE ak.id_kerkese_ndihme = ? AND ak.statusi = 'waitlisted'
         ORDER BY ak.aplikuar_me ASC
         LIMIT {$limit}"
    );
    $stmt->execute([$requestId]);
    $promoted = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($promoted === []) {
        return [];
    }

    $ids = array_map(static fn (array $row): int => (int) $row['id_aplikimi_kerkese'], $promoted);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $update = $pdo->prepare("UPDATE Aplikimi_Kerkese SET statusi = 'pending' WHERE id_aplikimi_kerkese IN ({$placeholders})");
    $update->execute($ids);

    return $promoted;
}

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── APPLY TO HELP REQUEST ─────────────────────
    case 'apply':
        require_method('POST');
        $user = require_auth();

        if (is_admin_role($user['roli'])) {
            json_error('Administratorët nuk mund të aplikojnë për kërkesa ndihme.', 403);
        }
        // Rate limit: max 20 help-request applications per hour per user
        if (!check_rate_limit('apply_help_' . $user['id'], 20, 3600)) {
            json_error('Po dërgoni shumë aplikime. Provoni përsëri pas një ore.', 429);
        }
        $body = get_json_body();
        $requestId = isset($body['id_kerkese_ndihme']) ? (int) $body['id_kerkese_ndihme'] : 0;

        if ($requestId <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 422);
        }

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare(
                "SELECT kn.id_kerkese_ndihme, kn.id_perdoruesi, kn.titulli, kn.statusi, kn.tipi,
                        kn.matching_mode, kn.capacity_total,
                        p.emri AS krijuesi_emri, p.email AS krijuesi_email
                 FROM Kerkesa_per_Ndihme kn
                 JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                 WHERE kn.id_kerkese_ndihme = ?
                 FOR UPDATE"
            );
            $check->execute([$requestId]);
            $request = $check->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                $pdo->rollBack();
                json_error('Kërkesa nuk u gjet.', 404);
            }

            $request = ts_normalize_row($request);

            // Block applications to non-approved (moderation) posts
            if (($request['moderation_status'] ?? 'approved') !== 'approved') {
                $pdo->rollBack();
                json_error('Kjo kërkesë nuk është akoma e miratuar.', 422);
            }

            $details = ts_help_request_matching_details($request, ts_help_request_application_counts($pdo, $requestId));

            if (in_array($details['resolved_status'], TS_HELP_REQUEST_TERMINAL_STATUSES, true)) {
                $pdo->rollBack();
                json_error('Kjo kërkesë nuk pranon më aplikime.', 422);
            }

            if (($request['statusi'] ?? '') !== $details['resolved_status'] && !in_array($request['statusi'] ?? '', TS_HELP_REQUEST_TERMINAL_STATUSES, true)) {
                $statusSync = $pdo->prepare('UPDATE Kerkesa_per_Ndihme SET statusi = ? WHERE id_kerkese_ndihme = ?');
                $statusSync->execute([$details['resolved_status'], $requestId]);
            }

            if ((int) $request['id_perdoruesi'] === (int) $user['id']) {
                $pdo->rollBack();
                json_error('Nuk mund të aplikoni në kërkesën tuaj.', 409);
            }

            $dup = $pdo->prepare(
                'SELECT id_aplikimi_kerkese FROM Aplikimi_Kerkese WHERE id_kerkese_ndihme = ? AND id_perdoruesi = ? LIMIT 1'
            );
            $dup->execute([$requestId, $user['id']]);

            if ($dup->fetch()) {
                $pdo->rollBack();
                json_error('Ju keni aplikuar tashmë për këtë kërkesë.', 409);
            }

            $applicationStatus = ($details['has_capacity_limit'] && $details['is_full'])
                ? 'waitlisted'
                : 'pending';

            $insert = $pdo->prepare(
                'INSERT INTO Aplikimi_Kerkese (id_kerkese_ndihme, id_perdoruesi, statusi)
                 VALUES (?, ?, ?)'
            );
            $insert->execute([$requestId, $user['id'], $applicationStatus]);
            $applicationId = (int) $pdo->lastInsertId();

            $pdo->commit();

            $ownerMessage = $applicationStatus === 'waitlisted'
                ? "{$user['emri']} u shtua në listën e pritjes për postimin tuaj \"{$request['titulli']}\"."
                : "{$user['emri']} aplikoi për postimin tuaj \"{$request['titulli']}\".";
            help_request_insert_notification($pdo, (int) $request['id_perdoruesi'], $ownerMessage, $requestId);

            help_request_email_recipient(
                $request['krijuesi_email'] ?? null,
                $request['krijuesi_emri'] ?? 'Përdorues',
                $applicationStatus === 'waitlisted' ? 'Aplikant i ri në listë pritjeje' : 'Aplikim i ri për postimin tuaj',
                $ownerMessage
            );

            json_success([
                'id_aplikimi_kerkese' => $applicationId,
                'statusi_aplikimit' => $applicationStatus,
                'message' => $applicationStatus === 'waitlisted'
                    ? 'Kapaciteti është i plotë. U shtuat në listën e pritjes.'
                    : 'Aplikimi u dërgua me sukses.',
            ], 201);
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('help_requests apply: ' . $e->getMessage());
            if ((int) $e->getCode() === 42) {
                json_error('Mungon tabela e aplikimeve për kërkesa. Përditësoni bazën e të dhënave.', 500);
            }
            json_error('Gabim gjatë dërgimit të aplikimit.', 500);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('help_requests apply: ' . $e->getMessage());
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
                'applications' => ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC)),
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

            if ((int) $requestRow['id_perdoruesi'] !== (int) $user['id'] && !is_admin_role($user['roli'])) {
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
                'applicants' => ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC)),
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

        // Rate limit: 10 contact emails per hour per (sender, applicant) pair.
        // Keyed per applicant so one requester cannot flood the same helper repeatedly.
        if (!check_rate_limit('contact_applicant_' . $user['id'] . '_' . $applicantId, 10, 3600)) {
            json_error('Keni dërguar shumë mesazhe për këtë aplikues. Provoni përsëri pas një ore.', 429);
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
            if ((int) $request['id_perdoruesi'] !== (int) $user['id'] && !is_admin_role($user['roli'])) {
                json_error('Nuk keni leje për këtë veprim.', 403);
            }

            $appCheck = $pdo->prepare(
                "SELECT id_aplikimi_kerkese FROM Aplikimi_Kerkese
                 WHERE id_kerkese_ndihme = ? AND id_perdoruesi = ? AND LOWER(statusi) IN ('pending', 'approved', 'waitlisted', 'completed') LIMIT 1"
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
            $pdo->beginTransaction();

            $appStmt = $pdo->prepare(
                'SELECT ak.id_aplikimi_kerkese, ak.id_kerkese_ndihme, ak.id_perdoruesi, ak.statusi,
                        kn.id_perdoruesi AS pronari_id, kn.titulli, kn.statusi AS kerkesa_statusi,
                        kn.matching_mode, kn.capacity_total,
                        p.emri AS aplikuesi_emri, p.email AS aplikuesi_email
                 FROM Aplikimi_Kerkese ak
                 JOIN Kerkesa_per_Ndihme kn ON kn.id_kerkese_ndihme = ak.id_kerkese_ndihme
                 JOIN Perdoruesi p ON p.id_perdoruesi = ak.id_perdoruesi
                 WHERE ak.id_aplikimi_kerkese = ?
                 FOR UPDATE'
            );
            $appStmt->execute([$applicationId]);
            $app = $appStmt->fetch(PDO::FETCH_ASSOC);

            if (!$app) {
                $pdo->rollBack();
                json_error('Aplikimi nuk u gjet.', 404);
            }

            $app = ts_normalize_row($app);
            $requestContext = [
                'statusi' => $app['kerkesa_statusi'] ?? 'open',
                'matching_mode' => $app['matching_mode'] ?? 'open',
                'capacity_total' => $app['capacity_total'] ?? null,
            ];
            $details = ts_help_request_matching_details($requestContext, ts_help_request_application_counts($pdo, (int) $app['id_kerkese_ndihme']));

            if (in_array($details['resolved_status'], TS_HELP_REQUEST_TERMINAL_STATUSES, true)) {
                $pdo->rollBack();
                json_error('Nuk mund të ndryshoni statusin e aplikimit për një postim joaktiv.', 422);
            }

            if ((int) $app['pronari_id'] !== (int) $user['id'] && !is_admin_role($user['roli'])) {
                $pdo->rollBack();
                json_error('Nuk keni leje për këtë veprim.', 403);
            }

            $currentStatus = ts_help_request_normalize_status((string) ($app['statusi'] ?? 'pending'));
            if (in_array($currentStatus, ['withdrawn', 'completed'], true)) {
                $pdo->rollBack();
                json_error('Ky aplikim nuk mund të ndryshohet më.', 422);
            }
            if ($currentStatus === $newStatus) {
                $pdo->rollBack();
                json_error('Aplikimi e ka tashmë këtë status.', 422);
            }

            if ($newStatus === 'approved' && $details['has_capacity_limit'] && $details['capacity_total'] !== null) {
                $approvedCount = $details['counts']['approved'];
                if ($currentStatus !== 'approved' && $approvedCount >= $details['capacity_total']) {
                    $pdo->rollBack();
                    json_error('Kapaciteti është i plotë. Lironi një vend ose rrisni kapacitetin para miratimit.', 422);
                }
            }

            $update = $pdo->prepare('UPDATE Aplikimi_Kerkese SET statusi = ? WHERE id_aplikimi_kerkese = ?');
            $update->execute([$newStatus, $applicationId]);

            $promoted = [];
            if ($newStatus === 'rejected' && $currentStatus === 'approved') {
                $afterReject = ts_help_request_sync_status($pdo, (int) $app['id_kerkese_ndihme']);
                if ($afterReject && $afterReject['has_capacity_limit'] && ($afterReject['slots_remaining'] ?? 0) > 0) {
                    $promoted = help_request_promote_waitlisted($pdo, (int) $app['id_kerkese_ndihme'], (int) $afterReject['slots_remaining']);
                }
            }

            $finalDetails = ts_help_request_sync_status($pdo, (int) $app['id_kerkese_ndihme']);
            $pdo->commit();

            $statusLabel = $newStatus === 'approved' ? 'pranua' : 'refuzua';
            $notifMessage = "Aplikimi juaj për \"{$app['titulli']}\" u {$statusLabel}.";
            help_request_insert_notification($pdo, (int) $app['id_perdoruesi'], $notifMessage, (int) $app['id_kerkese_ndihme']);

            help_request_email_recipient(
                $app['aplikuesi_email'] ?? null,
                $app['aplikuesi_emri'] ?? 'Volunteer',
                "Aplikimi juaj u {$statusLabel}",
                $notifMessage
            );

            foreach ($promoted as $promotedApplicant) {
                $promotionMessage = "Një vend u lirua për postimin \"{$app['titulli']}\". Aplikimi juaj doli nga lista e pritjes dhe është tani në shqyrtim.";
                help_request_insert_notification($pdo, (int) $promotedApplicant['id_perdoruesi'], $promotionMessage, (int) $app['id_kerkese_ndihme']);
                help_request_email_recipient(
                    $promotedApplicant['email'] ?? null,
                    $promotedApplicant['emri'] ?? 'Volunteer',
                    'Përditësim nga lista e pritjes',
                    $promotionMessage
                );
            }

            json_success([
                'message' => "Statusi i aplikimit u ndryshua në '{$newStatus}'.",
                'promoted_from_waitlist' => count($promoted),
                'kerkesa_statusi' => $finalDetails['resolved_status'] ?? $details['resolved_status'],
            ]);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('help_requests update_applicant_status: ' . $e->getMessage());
            json_error('Gabim gjatë përditësimit të statusit.', 500);
        }
        break;

    // ── LIST HELP REQUESTS ─────────────────────────
    case 'list':
        require_method('GET');
        release_session();
        $pagination = get_pagination();

        // Determine viewer context for moderation filtering
        $viewerIsAdmin = isset($_SESSION['user_id']) && is_admin();
        $viewerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;

        try {
            // Optional filters
            $tipi    = $_GET['tipi'] ?? null;     // Kërkesë | Ofertë
            $statusi = isset($_GET['statusi']) ? trim((string) $_GET['statusi']) : null;
            $userId  = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;
            $search  = isset($_GET['search']) ? trim($_GET['search']) : '';
            $kategoria = isset($_GET['kategoria']) ? (int) $_GET['kategoria'] : null;
            $flaggedOnly = isset($_GET['flagged']) && $_GET['flagged'] == '1';
            $moderationFilter = isset($_GET['moderation_status']) ? trim((string) $_GET['moderation_status']) : null;

            $where  = [];
            $params = [];

            // Moderation visibility: admins see all (or filter), non-admins see only approved + own posts
            if ($viewerIsAdmin) {
                if ($moderationFilter && in_array($moderationFilter, ['pending_review', 'approved', 'rejected'], true)) {
                    $where[] = 'kn.moderation_status = ?';
                    $params[] = $moderationFilter;
                }
            } else {
                // Non-admin: only approved, unless viewing own posts (user_id filter)
                if ($userId && $userId === $viewerId) {
                    // Owner viewing their own — no moderation filter
                } else {
                    $where[] = "(kn.moderation_status = 'approved' OR kn.id_perdoruesi = ?)";
                    $params[] = $viewerId;
                }
            }

            if ($tipi) {
                $where[]  = 'kn.tipi = ?';
                $params[] = $tipi;
            }
            if ($statusi) {
                $statusValues = help_request_status_filter_values($statusi);
                $where[] = 'LOWER(kn.statusi) IN (' . implode(',', array_fill(0, count($statusValues), '?')) . ')';
                foreach ($statusValues as $statusValue) {
                    $params[] = $statusValue;
                }
            }
            if ($userId) {
                $where[]  = 'kn.id_perdoruesi = ?';
                $params[] = $userId;
            }
            if ($kategoria) {
                $where[]  = 'kn.id_kategoria = ?';
                $params[] = $kategoria;
            }
            if ($flaggedOnly) {
                $where[]  = 'kn.flags > 0';
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

            $sql = "SELECT kn.*, p.emri AS krijuesi_emri, kat.emri AS kategoria_emri
                    FROM Kerkesa_per_Ndihme kn
                    JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                    LEFT JOIN Kategoria kat ON kat.id_kategoria = kn.id_kategoria
                    $whereSQL
                    ORDER BY 
                    CASE
                        WHEN kn.statusi = 'open' THEN 0
                        WHEN kn.statusi = 'filled' THEN 1
                        ELSE 2
                    END ASC,
                    kn.krijuar_me DESC
                    LIMIT ? OFFSET ?";

            $params[] = $pagination['limit'];
            $params[] = $pagination['offset'];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $requests = ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

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
                "SELECT kn.*, p.emri AS krijuesi_emri, kat.emri AS kategoria_emri
                 FROM Kerkesa_per_Ndihme kn
                 JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                 LEFT JOIN Kategoria kat ON kat.id_kategoria = kn.id_kategoria
                 WHERE kn.id_kerkese_ndihme = ?"
            );
            $stmt->execute([$id]);
            $request = $stmt->fetch();

            if (!$request) {
                json_error('Kërkesa nuk u gjet.', 404);
            }

            // Enforce moderation visibility: non-approved posts hidden from non-owner non-admins
            $getViewerIsAdmin = isset($_SESSION['user_id']) && is_admin();
            $getViewerId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            if (($request['moderation_status'] ?? 'approved') !== 'approved'
                && !$getViewerIsAdmin
                && (int) $request['id_perdoruesi'] !== $getViewerId) {
                json_error('Kërkesa nuk u gjet.', 404);
            }

            json_success(ts_normalize_row($request));
        } catch (\Exception $e) {
            error_log('help_requests get: ' . $e->getMessage());
            json_error('Gabim gjatë marrjes të kërkesës.', 500);
        }
        break;

    // ── CREATE HELP REQUEST ────────────────────────
    case 'create':
        require_method('POST');
        $user   = require_auth();

        // Rate limit: max 5 new help requests per hour per IP
        if (!check_rate_limit('create_help_request', 5, 3600)) {
            json_error('Po krijoni shumë kërkesa. Provoni përsëri pas një ore.', 429);
        }

        $body   = get_json_body();
        $errors = [];

        $titulli      = required_field($body, 'titulli', $errors);
        $pershkrimi   = $body['pershkrimi'] ?? '';
        $tipi         = $body['tipi'] ?? '';
        $imazhi       = $body['imazhi'] ?? null;
        $vendndodhja  = $body['vendndodhja'] ?? null;
        $latitude     = isset($body['latitude']) ? (float) $body['latitude'] : null;
        $longitude    = isset($body['longitude']) ? (float) $body['longitude'] : null;
        $idKategoria  = isset($body['id_kategoria']) && $body['id_kategoria'] !== '' ? (int) $body['id_kategoria'] : null;
        $matchingConfig = help_request_parse_matching_config($body);

        if (!in_array($tipi, ['request', 'offer'], true)) {
            $errors[] = "Tipi duhet të jetë 'request' ose 'offer'.";
        }
        if ($matchingConfig['errors'] !== []) {
            $errors = array_merge($errors, $matchingConfig['errors']);
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

        // Content/profanity filter
        if ($profErr = check_profanity($titulli, $pershkrimi)) {
            json_error($profErr, 422);
        }

        // Validate image URL if provided
        if ($imazhi && !validate_image_url($imazhi)) {
            json_error('URL-ja e imazhit nuk është e vlefshme.', 422);
        }

        // Validate category if provided
        if ($idKategoria !== null) {
            $catCheck = $pdo->prepare('SELECT COUNT(*) FROM Kategoria WHERE id_kategoria = ?');
            $catCheck->execute([$idKategoria]);
            if ((int) $catCheck->fetchColumn() === 0) {
                json_error('Kategoria e zgjedhur nuk ekziston.', 422);
            }
        }

        // Determine moderation status based on creator role
        $moderationStatus = is_admin_role($user['roli']) ? 'approved' : 'pending_review';

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO Kerkesa_per_Ndihme (
                    id_perdoruesi, id_kategoria, tipi, titulli, pershkrimi, statusi,
                    moderation_status, imazhi, vendndodhja, latitude, longitude,
                    matching_mode, capacity_total
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'],
                $idKategoria,
                $tipi,
                $titulli,
                $pershkrimi,
                'open',
                $moderationStatus,
                $imazhi,
                $vendndodhja,
                $latitude,
                $longitude,
                $matchingConfig['matching_mode'],
                $matchingConfig['capacity_total'],
            ]);

            $createdMessage = $moderationStatus === 'approved'
                ? 'Kërkesa u krijua me sukses.'
                : 'Kërkesa u krijua dhe është në shqyrtim nga administratorët.';

            json_success([
                'id_kerkese_ndihme' => (int) $pdo->lastInsertId(),
                'moderation_status' => $moderationStatus,
                'message'           => $createdMessage,
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

        if ($existing['id_perdoruesi'] != $user['id'] && !is_admin_role($user['roli'])) {
            json_error('Nuk keni leje për të ndryshuar këtë kërkesë.', 403);
        }

        $existing = ts_normalize_row($existing);

        if (in_array(ts_help_request_normalize_status((string) ($existing['statusi'] ?? 'open')), TS_HELP_REQUEST_TERMINAL_STATUSES, true)) {
            json_error('Postimet e përfunduara ose të anuluara nuk mund të ndryshohen.', 422);
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

        $matchingConfig = help_request_parse_matching_config($body, $existing);
        if ($matchingConfig['errors'] !== []) {
            json_error('Të dhëna të pavlefshme.', 422, $matchingConfig['errors']);
        }

        $appCounts = ts_help_request_application_counts($pdo, $id);
        $approvedCount = (int) ($appCounts['approved'] ?? 0);
        if ($matchingConfig['capacity_total'] !== null && $matchingConfig['capacity_total'] < $approvedCount) {
            json_error('Kapaciteti i ri nuk mund të jetë më i vogël se numri i aplikimeve tashmë të miratuara.', 422);
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

        if (array_key_exists('matching_mode', $body) || array_key_exists('capacity_total', $body)) {
            $sets[] = 'matching_mode = ?';
            $params[] = $matchingConfig['matching_mode'];
            $sets[] = 'capacity_total = ?';
            $params[] = $matchingConfig['capacity_total'];
        }

        if (empty($sets)) {
            json_error('Asnjë fushë për të përditësuar.', 400);
        }

        $params[] = $id;
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE Kerkesa_per_Ndihme SET ' . implode(', ', $sets) . ' WHERE id_kerkese_ndihme = ?')
                ->execute($params);

            $details = ts_help_request_sync_status($pdo, $id);
            $promoted = [];
            if ($details && $details['has_capacity_limit'] && ($details['slots_remaining'] ?? 0) > 0 && ($details['counts']['waitlisted'] ?? 0) > 0) {
                $promoted = help_request_promote_waitlisted($pdo, $id, (int) $details['slots_remaining']);
            }
            $details = ts_help_request_sync_status($pdo, $id);
            $pdo->commit();

            $check = $pdo->prepare('SELECT titulli FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ? LIMIT 1');
            $check->execute([$id]);
            $title = (string) ($check->fetchColumn() ?: 'postimin');

            foreach ($promoted as $promotedApplicant) {
                $promotionMessage = "Postimi \"{$title}\" ka sërish vend të lirë. Aplikimi juaj doli nga lista e pritjes dhe është tani në shqyrtim.";
                help_request_insert_notification($pdo, (int) $promotedApplicant['id_perdoruesi'], $promotionMessage, $id);
                help_request_email_recipient(
                    $promotedApplicant['email'] ?? null,
                    $promotedApplicant['emri'] ?? 'Volunteer',
                    'Përditësim i aplikimit tuaj',
                    $promotionMessage
                );
            }

            json_success([
                'message' => 'Kërkesa u përditësua.',
                'promoted_from_waitlist' => count($promoted),
                'kerkesa_statusi' => $details['resolved_status'] ?? 'open',
            ]);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('help_requests update: ' . $e->getMessage());
            json_error('Gabim gjatë përditësimit të kërkesës.', 500);
        }
        break;

    // ── COMPLETE REQUEST ───────────────────────────
    case 'complete':
    case 'close':
        require_method('PUT');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare(
                'SELECT *
                 FROM Kerkesa_per_Ndihme
                 WHERE id_kerkese_ndihme = ?
                 FOR UPDATE'
            );
            $check->execute([$id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $pdo->rollBack();
                json_error('Kërkesa nuk u gjet.', 404);
            }

            $existing = ts_normalize_row($existing);

            if ($existing['id_perdoruesi'] != $user['id'] && !is_admin_role($user['roli'])) {
                $pdo->rollBack();
                json_error('Nuk keni leje.', 403);
            }

            $currentStatus = ts_help_request_normalize_status((string) ($existing['statusi'] ?? 'open'));
            if ($currentStatus === 'completed') {
                $pdo->rollBack();
                json_error('Kërkesa është tashmë e përfunduar.', 400);
            }
            if ($currentStatus === 'cancelled') {
                $pdo->rollBack();
                json_error('Kërkesa është anuluar. Rihapeni nëse doni ta përdorni sërish.', 400);
            }

            $participantsStmt = $pdo->prepare(
                "SELECT ak.id_perdoruesi, ak.statusi, p.emri, p.email
                 FROM Aplikimi_Kerkese ak
                 JOIN Perdoruesi p ON p.id_perdoruesi = ak.id_perdoruesi
                 WHERE ak.id_kerkese_ndihme = ? AND ak.statusi IN ('pending', 'approved', 'waitlisted')"
            );
            $participantsStmt->execute([$id]);
            $participants = ts_normalize_rows($participantsStmt->fetchAll(PDO::FETCH_ASSOC));

            $pdo->prepare("UPDATE Kerkesa_per_Ndihme SET statusi = 'completed', completed_at = NOW(), cancelled_at = NULL WHERE id_kerkese_ndihme = ?")
                ->execute([$id]);
            $pdo->prepare(
                "UPDATE Aplikimi_Kerkese
                 SET statusi = CASE WHEN statusi = 'approved' THEN 'completed' ELSE 'rejected' END
                 WHERE id_kerkese_ndihme = ? AND statusi IN ('pending', 'approved', 'waitlisted')"
            )->execute([$id]);

            $pdo->commit();

            // Notify the owner if closed by admin (A-02)
            if ($existing['id_perdoruesi'] != $user['id']) {
                $message = "Postimi juaj \"{$existing['titulli']}\" u shënua si i përfunduar nga një administrator.";
                help_request_insert_notification($pdo, (int) $existing['id_perdoruesi'], $message, $id, 'admin_veprim');

                $userContact = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
                $userContact->execute([$existing['id_perdoruesi']]);
                $recipient = $userContact->fetch();
                help_request_email_recipient($recipient['email'] ?? null, $recipient['emri'] ?? 'Volunteer', 'Njoftim i ri nga Tirana Solidare', $message);
            }

            foreach ($participants as $participant) {
                $participantStatus = ts_help_request_normalize_status((string) ($participant['statusi'] ?? 'pending'));
                $message = $participantStatus === 'approved'
                    ? "Postimi \"{$existing['titulli']}\" u shënua si i përfunduar. Bashkëpunimi juaj u regjistrua si i përmbushur."
                    : "Postimi \"{$existing['titulli']}\" u shënua si i përfunduar. Aplikimi juaj nuk është më aktiv.";
                help_request_insert_notification($pdo, (int) $participant['id_perdoruesi'], $message, $id);
                help_request_email_recipient(
                    $participant['email'] ?? null,
                    $participant['emri'] ?? 'Volunteer',
                    'Postimi u përfundua — Tirana Solidare',
                    $message
                );
            }

            json_success(['message' => 'Kërkesa u shënua si e përfunduar.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('help_requests close: ' . $e->getMessage());
            json_error('Gabim gjatë përfundimit të kërkesës.', 500);
        }
        break;

    // ── CANCEL REQUEST ─────────────────────────────
    case 'cancel':
        require_method('PUT');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);
        $body = get_json_body();
        $cancelReason = trim((string) ($body['arsye'] ?? ''));

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare(
                'SELECT *
                 FROM Kerkesa_per_Ndihme
                 WHERE id_kerkese_ndihme = ?
                 FOR UPDATE'
            );
            $check->execute([$id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $pdo->rollBack();
                json_error('Kërkesa nuk u gjet.', 404);
            }

            $existing = ts_normalize_row($existing);

            if ($existing['id_perdoruesi'] != $user['id'] && !is_admin_role($user['roli'])) {
                $pdo->rollBack();
                json_error('Nuk keni leje.', 403);
            }

            $currentStatus = ts_help_request_normalize_status((string) ($existing['statusi'] ?? 'open'));
            if ($currentStatus === 'cancelled') {
                $pdo->rollBack();
                json_error('Kërkesa është tashmë e anuluar.', 400);
            }
            if ($currentStatus === 'completed') {
                $pdo->rollBack();
                json_error('Kërkesa është përfunduar dhe nuk mund të anulohet.', 400);
            }

            $participantsStmt = $pdo->prepare(
                "SELECT ak.id_perdoruesi, p.emri, p.email
                 FROM Aplikimi_Kerkese ak
                 JOIN Perdoruesi p ON p.id_perdoruesi = ak.id_perdoruesi
                 WHERE ak.id_kerkese_ndihme = ? AND ak.statusi IN ('pending', 'approved', 'waitlisted')"
            );
            $participantsStmt->execute([$id]);
            $participants = $participantsStmt->fetchAll(PDO::FETCH_ASSOC);

            $pdo->prepare(
                "UPDATE Kerkesa_per_Ndihme
                 SET statusi = 'cancelled', cancelled_at = NOW(), completed_at = NULL, closed_reason = ?
                 WHERE id_kerkese_ndihme = ?"
            )->execute([$cancelReason !== '' ? $cancelReason : null, $id]);
            $pdo->prepare(
                "UPDATE Aplikimi_Kerkese
                 SET statusi = 'rejected'
                 WHERE id_kerkese_ndihme = ? AND statusi IN ('pending', 'approved', 'waitlisted')"
            )->execute([$id]);

            $pdo->commit();

            $messageSuffix = $cancelReason !== '' ? " Arsyeja: {$cancelReason}" : '';
            foreach ($participants as $participant) {
                $message = "Postimi \"{$existing['titulli']}\" u anulua dhe aplikimi juaj nuk është më aktiv." . $messageSuffix;
                help_request_insert_notification($pdo, (int) $participant['id_perdoruesi'], $message, $id);
                help_request_email_recipient(
                    $participant['email'] ?? null,
                    $participant['emri'] ?? 'Volunteer',
                    'Postimi u anulua — Tirana Solidare',
                    $message
                );
            }

            if ($existing['id_perdoruesi'] != $user['id']) {
                $ownerMessage = "Postimi juaj \"{$existing['titulli']}\" u anulua nga një administrator." . $messageSuffix;
                help_request_insert_notification($pdo, (int) $existing['id_perdoruesi'], $ownerMessage, $id, 'admin_veprim');
            }

            json_success(['message' => 'Kërkesa u anulua.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('help_requests cancel: ' . $e->getMessage());
            json_error('Gabim gjatë anulimit të kërkesës.', 500);
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
            $pdo->beginTransaction();

            $check = $pdo->prepare('SELECT * FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ? FOR UPDATE');
            $check->execute([$id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $pdo->rollBack();
                json_error('Kërkesa nuk u gjet.', 404);
            }

            $existing = ts_normalize_row($existing);

            if ($existing['id_perdoruesi'] != $user['id'] && !is_admin_role($user['roli'])) {
                $pdo->rollBack();
                json_error('Nuk keni leje.', 403);
            }

            $currentStatus = ts_help_request_normalize_status((string) ($existing['statusi'] ?? 'open'));
            if (!in_array($currentStatus, ['completed', 'cancelled', 'closed'], true)) {
                $pdo->rollBack();
                json_error('Kërkesa është tashmë aktive.', 400);
            }

            $pdo->prepare(
                "UPDATE Kerkesa_per_Ndihme
                 SET statusi = 'open', completed_at = NULL, cancelled_at = NULL, closed_reason = NULL
                 WHERE id_kerkese_ndihme = ?"
            )->execute([$id]);

            $details = ts_help_request_sync_status($pdo, $id);
            $promoted = [];
            if ($details && $details['has_capacity_limit'] && ($details['slots_remaining'] ?? 0) > 0 && ($details['counts']['waitlisted'] ?? 0) > 0) {
                $promoted = help_request_promote_waitlisted($pdo, $id, (int) $details['slots_remaining']);
            }
            $details = ts_help_request_sync_status($pdo, $id);
            $pdo->commit();

            foreach ($promoted as $promotedApplicant) {
                $message = "Postimi u rihap dhe aplikimi juaj doli nga lista e pritjes. Është tani në shqyrtim.";
                help_request_insert_notification($pdo, (int) $promotedApplicant['id_perdoruesi'], $message, $id);
                help_request_email_recipient(
                    $promotedApplicant['email'] ?? null,
                    $promotedApplicant['emri'] ?? 'Volunteer',
                    'Postimi u rihap',
                    $message
                );
            }

            json_success([
                'message' => 'Kërkesa u rihap.',
                'kerkesa_statusi' => $details['resolved_status'] ?? 'open',
            ]);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('help_requests reopen: ' . $e->getMessage());
            json_error('Gabim gjatë rihapjes të kërkesës.', 500);
        }
        break;

    // ── WITHDRAW MY APPLICATION ───────────────────
    case 'withdraw_application':
        require_method('DELETE');
        $user = require_auth();
        $applicationId = (int) ($_GET['id'] ?? 0);

        if ($applicationId <= 0) {
            json_error('ID-ja e aplikimit është e pavlefshme.', 400);
        }

        try {
            $pdo->beginTransaction();

            $check = $pdo->prepare(
                'SELECT ak.id_aplikimi_kerkese, ak.id_kerkese_ndihme, ak.statusi, ak.id_perdoruesi,
                        kn.titulli, kn.statusi AS kerkesa_statusi, kn.matching_mode, kn.capacity_total,
                        kn.id_perdoruesi AS pronari_id,
                        p.emri AS pronari_emri, p.email AS pronari_email
                 FROM Aplikimi_Kerkese ak
                 JOIN Kerkesa_per_Ndihme kn ON kn.id_kerkese_ndihme = ak.id_kerkese_ndihme
                 JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi
                 WHERE ak.id_aplikimi_kerkese = ? AND ak.id_perdoruesi = ?
                 FOR UPDATE'
            );
            $check->execute([$applicationId, $user['id']]);
            $application = $check->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                $pdo->rollBack();
                json_error('Aplikimi nuk u gjet.', 404);
            }

            $application = ts_normalize_row($application);
            $currentStatus = ts_help_request_normalize_status((string) ($application['statusi'] ?? 'pending'));
            if (in_array($currentStatus, ['rejected', 'withdrawn', 'completed'], true)) {
                $pdo->rollBack();
                json_error('Ky aplikim nuk mund të tërhiqet më.', 422);
            }

            $pdo->prepare("UPDATE Aplikimi_Kerkese SET statusi = 'withdrawn' WHERE id_aplikimi_kerkese = ?")
                ->execute([$applicationId]);

            $promoted = [];
            if ($currentStatus === 'approved') {
                $details = ts_help_request_sync_status($pdo, (int) $application['id_kerkese_ndihme']);
                if ($details && $details['has_capacity_limit'] && ($details['slots_remaining'] ?? 0) > 0) {
                    $promoted = help_request_promote_waitlisted($pdo, (int) $application['id_kerkese_ndihme'], (int) $details['slots_remaining']);
                }
            }

            $details = ts_help_request_sync_status($pdo, (int) $application['id_kerkese_ndihme']);
            $pdo->commit();

            $ownerMessage = "{$user['emri']} tërhoqi aplikimin për postimin tuaj \"{$application['titulli']}\".";
            help_request_insert_notification($pdo, (int) $application['pronari_id'], $ownerMessage, (int) $application['id_kerkese_ndihme']);
            help_request_email_recipient(
                $application['pronari_email'] ?? null,
                $application['pronari_emri'] ?? 'Përdorues',
                'Aplikim i tërhequr',
                $ownerMessage
            );

            foreach ($promoted as $promotedApplicant) {
                $message = "Një vend u lirua për postimin \"{$application['titulli']}\". Aplikimi juaj doli nga lista e pritjes dhe është tani në shqyrtim.";
                help_request_insert_notification($pdo, (int) $promotedApplicant['id_perdoruesi'], $message, (int) $application['id_kerkese_ndihme']);
                help_request_email_recipient(
                    $promotedApplicant['email'] ?? null,
                    $promotedApplicant['emri'] ?? 'Volunteer',
                    'Përditësim i aplikimit tuaj',
                    $message
                );
            }

            json_success([
                'message' => 'Aplikimi u tërhoq.',
                'kerkesa_statusi' => $details['resolved_status'] ?? 'open',
                'promoted_from_waitlist' => count($promoted),
            ]);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('help_requests withdraw_application: ' . $e->getMessage());
            json_error('Gabim gjatë tërheqjes së aplikimit.', 500);
        }
        break;

    // ── FLAG (REPORT) REQUEST ──────────────────────
    case 'flag':
    require_method('POST');
    $user = require_auth();
    $id = (int) ($_GET['id'] ?? 0);
    $body = get_json_body();
    $arsye = trim((string) ($body['arsye'] ?? ''));

    if ($id <= 0) {
        json_error('ID e kërkesës e pavlefshme.', 400);
    }

    $flagKey = 'flag_' . $user['id'] . '_' . $id;
    if (!check_rate_limit($flagKey, 1, 2592000)) {
        json_error('E keni raportuar tashmë këtë kërkesë.', 429);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO help_request_flags (id_kerkese_ndihme, id_perdoruesi, arsye)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$id, $user['id'], $arsye !== '' ? $arsye : null]);

        if ($stmt->rowCount() === 0) {
            json_error('E keni raportuar tashmë këtë kërkesë.', 429);
        }

        $pdo->prepare('UPDATE Kerkesa_per_Ndihme SET flags = COALESCE(flags, 0) + 1 WHERE id_kerkese_ndihme = ?')
            ->execute([$id]);

        json_success(['message' => 'Kërkesa u raportua me sukses.']);
    } catch (\Exception $e) {
        error_log('help_requests flag: ' . $e->getMessage());
        json_error('Gabim gjatë raportimit.', 500);
    }
    break;

case 'get_flags':
    require_method('GET');
    require_admin();
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        json_error('ID e pavlefshme.', 400);
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT hrf.arsye, hrf.krijuar_me,
                    p.emri AS raportuesi_emri
             FROM help_request_flags hrf
             JOIN Perdoruesi p ON p.id_perdoruesi = hrf.id_perdoruesi
             WHERE hrf.id_kerkese_ndihme = ?
             ORDER BY hrf.krijuar_me DESC"
        );
        $stmt->execute([$id]);
        json_success(['flags' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\Exception $e) {
        json_error('Gabim.', 500);
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

        if ($existing['id_perdoruesi'] != $user['id'] && !is_admin_role($user['roli'])) {
            json_error('Nuk keni leje.', 403);
        }

        // Delete all applications to this request first (FK constraint safety)
        $pdo->prepare('DELETE FROM Aplikimi_Kerkese WHERE id_kerkese_ndihme = ?')->execute([$id]);

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
            json_success(['applications' => ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC))]);
        } catch (\Exception $e) {
            error_log('help_requests by_user: ' . $e->getMessage());
            json_error('Gabim gjatë marrjes së aplikimeve.', 500);
        }
        break;

    // ── APPROVE REQUEST (Admin moderation) ─────────
    case 'approve_request':
        require_method('PUT');
        $admin = require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
            $check = $pdo->prepare('SELECT id_kerkese_ndihme, id_perdoruesi, titulli, moderation_status FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
            $check->execute([$id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                json_error('Kërkesa nuk u gjet.', 404);
            }
            if (($existing['moderation_status'] ?? 'approved') === 'approved') {
                json_error('Kërkesa është tashmë e miratuar.', 400);
            }

            $pdo->prepare('UPDATE Kerkesa_per_Ndihme SET moderation_status = ? WHERE id_kerkese_ndihme = ?')
                ->execute(['approved', $id]);

            // Notify the owner
            $message = "Postimi juaj \"{$existing['titulli']}\" u miratua dhe është tani publik.";
            help_request_insert_notification($pdo, (int) $existing['id_perdoruesi'], $message, $id, 'admin_veprim');

            $ownerStmt = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
            $ownerStmt->execute([$existing['id_perdoruesi']]);
            $owner = $ownerStmt->fetch();
            help_request_email_recipient($owner['email'] ?? null, $owner['emri'] ?? 'Përdorues', 'Postimi juaj u miratua', $message);

            json_success(['message' => 'Kërkesa u miratua me sukses.']);
        } catch (\Exception $e) {
            error_log('help_requests approve_request: ' . $e->getMessage());
            json_error('Gabim gjatë miratimit të kërkesës.', 500);
        }
        break;

    // ── REJECT REQUEST (Admin moderation) ──────────
    case 'reject_request':
        require_method('PUT');
        $admin = require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kërkesës është e pavlefshme.', 400);
        }

        try {
            $check = $pdo->prepare('SELECT id_kerkese_ndihme, id_perdoruesi, titulli, moderation_status FROM Kerkesa_per_Ndihme WHERE id_kerkese_ndihme = ?');
            $check->execute([$id]);
            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                json_error('Kërkesa nuk u gjet.', 404);
            }
            if (($existing['moderation_status'] ?? 'approved') === 'rejected') {
                json_error('Kërkesa është tashmë e refuzuar.', 400);
            }

            $pdo->prepare('UPDATE Kerkesa_per_Ndihme SET moderation_status = ? WHERE id_kerkese_ndihme = ?')
                ->execute(['rejected', $id]);

            // Notify the owner
            $message = "Postimi juaj \"{$existing['titulli']}\" nuk u miratua nga administratorët.";
            help_request_insert_notification($pdo, (int) $existing['id_perdoruesi'], $message, $id, 'admin_veprim');

            $ownerStmt = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
            $ownerStmt->execute([$existing['id_perdoruesi']]);
            $owner = $ownerStmt->fetch();
            help_request_email_recipient($owner['email'] ?? null, $owner['emri'] ?? 'Përdorues', 'Postimi juaj nuk u miratua', $message);

            json_success(['message' => 'Kërkesa u refuzua.']);
        } catch (\Exception $e) {
            error_log('help_requests reject_request: ' . $e->getMessage());
            json_error('Gabim gjatë refuzimit të kërkesës.', 500);
        }
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, get, create, update, complete, cancel, reopen, delete, apply, my_applications, applicants, contact_applicant, withdraw_application, by_user, approve_request, reject_request.', 400);
}
