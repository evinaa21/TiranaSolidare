<?php
/**
 * api/organizations.php
 * ---------------------------------------------------
 * Organization onboarding API
 *
 * GET    ?action=mine                – My latest organization application
 * GET    ?action=list                – List applications (Super Admin)
 * POST   ?action=submit              – Submit application (Authenticated user)
 * PUT    ?action=review&id=<id>      – Approve/reject application (Super Admin)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'mine';

switch ($action) {
    case 'mine':
        require_method('GET');
        $user = require_auth();

        $stmt = $pdo->prepare(
            'SELECT oa.*, reviewer.emri AS reviewer_name
             FROM organization_applications oa
             LEFT JOIN Perdoruesi reviewer ON reviewer.id_perdoruesi = oa.reviewed_by_user_id
             WHERE oa.applicant_user_id = ?
             ORDER BY oa.created_at DESC, oa.id DESC
             LIMIT 1'
        );
        $stmt->execute([$user['id']]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        json_success([
            'application' => $application ? ts_normalize_row($application) : null,
            'is_organizer' => ts_is_organizer_role_value($user['roli'] ?? null),
            'organization_name' => (string) ($user['organization_name'] ?? ''),
        ]);
        break;

    case 'submit':
        require_method('POST');
        $user = require_auth();

        if (in_array(ts_normalize_value($user['roli'] ?? ''), ['admin', 'super_admin'], true)) {
            json_error('Administratorët nuk kanë nevojë të aplikojnë si organizatorë.', 403);
        }
        if (ts_is_organizer_role_value($user['roli'] ?? null)) {
            json_error('Ju jeni tashmë organizator i miratuar.', 409);
        }

        $body = get_json_body();
        $errors = [];
        $organizationName = required_field($body, 'organization_name', $errors);
        $contactName = required_field($body, 'contact_name', $errors);
        $contactEmail = required_field($body, 'contact_email', $errors);
        $description = required_field($body, 'description', $errors);
        $contactPhone = trim((string) ($body['contact_phone'] ?? ''));
        $website = trim((string) ($body['website'] ?? ''));

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            json_error('Email-i i kontaktit nuk është i vlefshëm.', 422, ['contact_email' => 'invalid']);
        }
        if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
            json_error('Faqja e internetit nuk është e vlefshme.', 422, ['website' => 'invalid']);
        }
        if ($lenErr = validate_length($organizationName, 3, 160, 'organization_name')) {
            json_error($lenErr, 422);
        }
        if ($lenErr = validate_length($contactName, 3, 120, 'contact_name')) {
            json_error($lenErr, 422);
        }
        if ($contactPhone !== '' && validate_length($contactPhone, 6, 40, 'contact_phone')) {
            json_error('Numri i kontaktit nuk është i vlefshëm.', 422, ['contact_phone' => 'invalid']);
        }
        if ($lenErr = validate_length($description, 30, 2000, 'description')) {
            json_error($lenErr, 422);
        }

        $pendingCheck = $pdo->prepare(
            "SELECT id, status FROM organization_applications
             WHERE applicant_user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 1"
        );
        $pendingCheck->execute([$user['id']]);
        $latest = $pendingCheck->fetch(PDO::FETCH_ASSOC);

        if ($latest && ts_organization_application_status($latest['status'] ?? null) === 'pending') {
            json_error('Ju keni tashmë një aplikim në pritje.', 409);
        }

        $applicationId = ts_submit_organization_application($pdo, (int) $user['id'], [
            'organization_name' => $organizationName,
            'contact_name' => $contactName,
            'contact_email' => $contactEmail,
            'contact_phone' => $contactPhone,
            'website' => $website,
            'description' => $description,
        ]);

        $reviewers = $pdo->query(
            "SELECT id_perdoruesi, emri, email
             FROM Perdoruesi
             WHERE roli = 'super_admin' AND statusi_llogarise = 'active'"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($reviewers)) {
            $reviewers = $pdo->query(
                "SELECT id_perdoruesi, emri, email
                 FROM Perdoruesi
                 WHERE roli IN ('admin', 'super_admin') AND statusi_llogarise = 'active'"
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $message = $organizationName . ' dërgoi një aplikim të ri për llogari organizatori.';
        foreach ($reviewers as $reviewer) {
            $notifStmt->execute([
                $reviewer['id_perdoruesi'],
                $message,
                'organization_application',
                'organization_application',
                $applicationId,
                '/TiranaSolidare/views/dashboard.php#panel-organizations',
            ]);

            if (filter_var($reviewer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                send_notification_email(
                    $reviewer['email'],
                    $reviewer['emri'] ?? 'Super Admin',
                    'Aplikim i ri organizate — Tirana Solidare',
                    $message
                );
            }
        }

        json_success([
            'id' => $applicationId,
            'message' => 'Aplikimi u dërgua me sukses. Do të njoftoheni pasi të shqyrtohet.',
        ], 201);
        break;

    case 'list':
        require_method('GET');
        require_super_admin();
        release_session();
        $pagination = get_pagination(20, 100);
        $status = ts_organization_application_status($_GET['status'] ?? 'pending');
        $search = trim((string) ($_GET['search'] ?? ''));

        $where = [];
        $params = [];
        if ($status !== '') {
            $where[] = 'oa.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(oa.organization_name LIKE ? OR oa.contact_name LIKE ? OR applicant.email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM organization_applications oa
             JOIN Perdoruesi applicant ON applicant.id_perdoruesi = oa.applicant_user_id
             $whereSql"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT oa.*, applicant.emri AS applicant_name, applicant.email AS applicant_email,
                    reviewer.emri AS reviewer_name
             FROM organization_applications oa
             JOIN Perdoruesi applicant ON applicant.id_perdoruesi = oa.applicant_user_id
             LEFT JOIN Perdoruesi reviewer ON reviewer.id_perdoruesi = oa.reviewed_by_user_id
             $whereSql
             ORDER BY FIELD(oa.status, 'pending', 'approved', 'rejected'), oa.created_at DESC, oa.id DESC
             LIMIT ? OFFSET ?"
        );
        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];
        $stmt->execute($params);

        json_success([
            'applications' => ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC)),
            'total' => $total,
            'page' => $pagination['page'],
            'limit' => $pagination['limit'],
            'total_pages' => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    case 'review':
        require_method('PUT');
        $reviewer = require_super_admin();
        $applicationId = (int) ($_GET['id'] ?? 0);
        $body = get_json_body();

        if ($applicationId <= 0) {
            json_error('ID-ja e aplikimit është e pavlefshme.', 400);
        }

        $decision = ts_normalize_value($body['decision'] ?? '');
        $notes = trim((string) ($body['review_notes'] ?? ''));

        try {
            $application = ts_review_organization_application($pdo, $applicationId, (int) $reviewer['id'], $decision, $notes);
        } catch (InvalidArgumentException $e) {
            json_error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            json_error($e->getMessage(), 409);
        } catch (Throwable $e) {
            error_log('Organization application review failed: ' . $e->getMessage());
            json_error('Gabim gjatë shqyrtimit të aplikimit.', 500);
        }

        $decisionLabel = $decision === 'approved' ? 'u miratua' : 'u refuzua';
        $message = 'Aplikimi i organizatës "' . ($application['organization_name'] ?? '') . '" ' . $decisionLabel . '.';
        if ($notes !== '') {
            $message .= ' Shënim: ' . $notes;
        }

        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $notifStmt->execute([
            $application['applicant_user_id'],
            $message,
            'organization_application',
            'organization_application',
            $applicationId,
            '/TiranaSolidare/views/become_organizer.php',
        ]);

        if (filter_var($application['applicant_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            send_notification_email(
                $application['applicant_email'],
                $application['applicant_name'] ?? 'Përdorues',
                'Përditësim për aplikimin e organizatës — Tirana Solidare',
                $message
            );
        }

        log_admin_action($reviewer['id'], 'review_organization_application', 'organization_application', $applicationId, [
            'decision' => $decision,
            'organization_name' => $application['organization_name'] ?? '',
        ]);

        json_success([
            'message' => 'Aplikimi u përditësua me sukses.',
            'application' => ts_normalize_row($application),
        ]);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: mine, submit, list, review.', 400);
}