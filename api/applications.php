<?php
/**
 * api/applications.php
 * ---------------------------------------------------
 * Volunteer Application Management API
 *
 * GET    ?action=list                    – My applications (Volunteer) or all (Admin)
 * GET    ?action=by_event&id=<event_id>  – Applications for an event (Admin)
 * POST   ?action=apply                   – Apply for an event (Volunteer)
 * PUT    ?action=update_status&id=<id>   – Accept/Reject application (Admin)
 * PUT    ?action=mark_presence&id=<id>   – Mark present/absent (Admin)
 * DELETE ?action=withdraw&id=<id>        – Withdraw application (Volunteer)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

function ts_load_event_for_application_manager(PDO $pdo, int $eventId, array $user): array
{
    $stmt = $pdo->prepare('SELECT id_eventi, id_perdoruesi, titulli, data, kapaciteti, statusi FROM Eventi WHERE id_eventi = ?');
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        json_error('Eventi nuk u gjet.', 404);
    }
    if (!ts_can_manage_event($user, $event)) {
        json_error('Nuk keni leje për të menaxhuar aplikimet e këtij eventi.', 403);
    }

    return $event;
}

switch ($action) {

    // ── LIST APPLICATIONS ──────────────────────────
    case 'list':
        require_method('GET');
        $user       = require_auth();
        $pagination = get_pagination();

        if (is_admin_role($user['roli'])) {
            // Admin sees all
            $countStmt = $pdo->query('SELECT COUNT(*) FROM Aplikimi');
            $total = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT a.*, p.emri AS vullnetari_emri, p.email AS vullnetari_email,
                        e.titulli AS eventi_titulli, e.data AS eventi_data
                 FROM Aplikimi a
                 JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
                 JOIN Eventi e ON e.id_eventi = a.id_eventi
                 ORDER BY a.aplikuar_me DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$pagination['limit'], $pagination['offset']]);
        } else {
            // Volunteer sees own
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi WHERE id_perdoruesi = ?');
            $countStmt->execute([$user['id']]);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT a.*, e.titulli AS eventi_titulli, e.data AS eventi_data,
                        e.vendndodhja AS eventi_vendndodhja
                 FROM Aplikimi a
                 JOIN Eventi e ON e.id_eventi = a.id_eventi
                 WHERE a.id_perdoruesi = ?
                 ORDER BY a.aplikuar_me DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute([$user['id'], $pagination['limit'], $pagination['offset']]);
        }

        $applications = ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

        json_success([
            'applications' => $applications,
            'total'        => $total,
            'page'         => $pagination['page'],
            'limit'        => $pagination['limit'],
            'total_pages'  => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── APPLICATIONS BY EVENT (A-05/A-06) ──────────
    case 'by_event':
        require_method('GET');
        $manager = require_event_manager();
        $eventId = (int) ($_GET['id'] ?? 0);

        if ($eventId <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $eventRow = ts_load_event_for_application_manager($pdo, $eventId, $manager);

        // Modal view does not paginate in the UI, so use a larger default page size.
        $pagination = get_pagination(100, 500);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ?');
        $countStmt->execute([$eventId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT a.*, p.emri AS vullnetari_emri, p.email AS vullnetari_email
             FROM Aplikimi a
             JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
             WHERE a.id_eventi = ?
             ORDER BY
                CASE
                    WHEN a.statusi = 'approved' THEN 0
                    WHEN a.statusi = 'present' THEN 1
                    WHEN a.statusi = 'pending' AND COALESCE(a.ne_liste_pritje, 0) = 0 THEN 2
                    WHEN a.statusi = 'pending' AND COALESCE(a.ne_liste_pritje, 0) = 1 THEN 3
                    WHEN a.statusi = 'absent' THEN 4
                    WHEN a.statusi = 'rejected' THEN 5
                    ELSE 6
                END ASC,
                a.aplikuar_me ASC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$eventId, $pagination['limit'], $pagination['offset']]);
        $apps = ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

        $confirmedStmt = $pdo->prepare(
            "SELECT a.id_aplikimi, a.statusi, p.emri AS vullnetari_emri, p.email AS vullnetari_email
             FROM Aplikimi a
             JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
             WHERE a.id_eventi = ? AND a.statusi IN ('approved', 'present', 'absent')
             ORDER BY
                CASE
                    WHEN a.statusi = 'approved' THEN 0
                    WHEN a.statusi = 'present' THEN 1
                    WHEN a.statusi = 'absent' THEN 2
                    ELSE 3
                END ASC,
                a.aplikuar_me ASC"
        );
        $confirmedStmt->execute([$eventId]);
        $confirmedApplicants = ts_normalize_rows($confirmedStmt->fetchAll(PDO::FETCH_ASSOC));

        // Summary counts
        $summary = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN statusi = 'pending' THEN 1 ELSE 0 END) AS ne_pritje,
                SUM(CASE WHEN statusi = 'approved' THEN 1 ELSE 0 END)  AS pranuar,
                SUM(CASE WHEN statusi = 'rejected' THEN 1 ELSE 0 END) AS refuzuar,
                SUM(CASE WHEN statusi = 'present' THEN 1 ELSE 0 END)  AS prezent,
                SUM(CASE WHEN statusi = 'absent' THEN 1 ELSE 0 END)  AS munguar
             FROM Aplikimi WHERE id_eventi = ?"
        );
        $summary->execute([$eventId]);
        $stats = $summary->fetch();

        json_success([
            'applications' => $apps,
            'confirmed_applicants' => $confirmedApplicants,
            'summary'      => $stats,
            'event_data'   => $eventRow['data'],
            'event_title'  => $eventRow['titulli'],
            'capacity_total' => isset($eventRow['kapaciteti']) ? (int) $eventRow['kapaciteti'] : null,
            'total'        => $total,
            'page'         => $pagination['page'],
            'limit'        => $pagination['limit'],
            'total_pages'  => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── APPLY FOR EVENT ────────────────────────────
    case 'apply':
        require_method('POST');
        $user = require_auth();

        if (ts_is_dashboard_role_value($user['roli'] ?? null)) {
            json_error('Përdoruesit me rol menaxhimi nuk mund të aplikojnë si vullnetarë.', 403);
        }
        // Rate limit: max 20 event applications per hour per user
        if (!check_rate_limit('apply_event_' . $user['id'], 20, 3600)) {
            json_error('Po dërgoni shumë aplikime. Provoni përsëri pas një ore.', 429);
        }
        $body   = get_json_body();
        $errors = [];
        $eventId = isset($body['id_eventi']) ? (int) $body['id_eventi'] : 0;

        if ($eventId <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 422);
        }

        // Check event exists
        $check = $pdo->prepare('SELECT id_eventi, id_perdoruesi, titulli, data, kapaciteti, statusi FROM Eventi WHERE id_eventi = ? AND is_archived = 0');
        $check->execute([$eventId]);
        $event = $check->fetch();

        if (!$event) {
            json_error('Eventi nuk u gjet.', 404);
        }

        // Only allow applications for active events
        if ($event['statusi'] !== 'active') {
            json_error('Ky event nuk pranon më aplikime.', 422);
        }

        // Check event is not in the past (L-01)
        if (strtotime($event['data']) <= time()) {
            json_error('Nuk mund të aplikoni për një event që ka kaluar.', 422);
        }

        // Determine waitlist status and insert atomically — prevents duplicate applications
        // and duplicate waitlist flags under concurrent requests.
        $waitlisted = 0;
        try {
            $pdo->beginTransaction();

            // Duplicate check inside transaction with FOR UPDATE to prevent race condition
            // where two simultaneous requests from the same user both pass the check.
            $dup = $pdo->prepare(
                'SELECT id_aplikimi FROM Aplikimi WHERE id_perdoruesi = ? AND id_eventi = ? FOR UPDATE'
            );
            $dup->execute([$user['id'], $eventId]);
            if ($dup->fetch()) {
                $pdo->rollBack();
                json_error('Ju keni aplikuar tashmë për këtë event.', 409);
            }

            if ($event['kapaciteti'] !== null && (int) $event['kapaciteti'] > 0) {
                // Lock approved rows for this event so no other transaction can sneak an INSERT
                // between our count check and our own INSERT.
                $countStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ? AND statusi = 'approved' FOR UPDATE"
                );
                $countStmt->execute([$eventId]);
                $acceptedCount = (int) $countStmt->fetchColumn();
                if ($acceptedCount >= (int) $event['kapaciteti']) {
                    $waitlisted = 1;
                }
            }

            $stmt = $pdo->prepare(
                "INSERT INTO Aplikimi (id_perdoruesi, id_eventi, statusi, ne_liste_pritje) VALUES (?, ?, 'pending', ?)"
            );
            $stmt->execute([$user['id'], $eventId, $waitlisted]);
            $appId = (int) $pdo->lastInsertId();

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('applications apply tx: ' . $e->getMessage());
            json_error('Gabim gjatë dërgimit të aplikimit.', 500);
        }

        // Create notifications for admins and the organizer that owns the event.
        $admins = $pdo->query(
            "SELECT id_perdoruesi, emri, email
             FROM Perdoruesi
             WHERE roli IN ('admin','super_admin','Admin')
               AND statusi_llogarise IN ('active', 'Aktiv')"
        )->fetchAll(PDO::FETCH_ASSOC);
        $organizer = null;
        if (!empty($event['id_perdoruesi'])) {
            $ownerStmt = $pdo->prepare('SELECT id_perdoruesi, emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
            $ownerStmt->execute([(int) $event['id_perdoruesi']]);
            $organizer = $ownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $msg = "{$user['emri']} aplikoi për eventin \"{$event['titulli']}\".";
        $eventLink = "/TiranaSolidare/views/events.php?id={$eventId}";
        foreach ($admins as $admin) {
            $notifStmt->execute([$admin['id_perdoruesi'], $msg, 'aplikim_event', 'event', $eventId, $eventLink]);
            if (filter_var($admin['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                send_notification_email(
                    $admin['email'],
                    $admin['emri'] ?? 'Administrator',
                    'Aplikim i ri për event — Tirana Solidare',
                    $msg,
                    [
                        'action_url' => "/views/events.php?id={$eventId}",
                        'action_label' => 'Shiko eventin',
                    ]
                );
            }
        }
        $adminIds = array_map(static fn(array $row): int => (int) $row['id_perdoruesi'], $admins);
        if ($organizer && !in_array((int) $organizer['id_perdoruesi'], $adminIds, true)) {
            $notifStmt->execute([$organizer['id_perdoruesi'], $msg, 'aplikim_event', 'event', $eventId, $eventLink]);
            if (filter_var($organizer['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                send_notification_email(
                    $organizer['email'],
                    $organizer['emri'] ?? 'Organizator',
                    'Aplikim i ri për event — Tirana Solidare',
                    $msg,
                    [
                        'action_url' => "/views/events.php?id={$eventId}",
                        'action_label' => 'Shiko eventin',
                    ]
                );
            }
        }

        $userEmailStmt = $pdo->prepare('SELECT email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $userEmailStmt->execute([$user['id']]);
        $userEmail = $userEmailStmt->fetchColumn();
        if (filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            $applicantMessage = ($waitlisted
                ? "Aplikimi juaj për eventin \"{$event['titulli']}\" u regjistrua dhe jeni shtuar në listën e pritjes."
                : "Aplikimi juaj për eventin \"{$event['titulli']}\" u regjistrua me sukses dhe është në pritje të shqyrtimit.")
                . "\n\nDetaje:\n"
                . 'Data: ' . date('d/m/Y H:i', strtotime((string) $event['data'])) . "\n"
                . 'Vendndodhja: ' . (($event['vendndodhja'] ?? '') !== '' ? $event['vendndodhja'] : 'Do të konfirmohet në faqen e eventit.');

            send_notification_email(
                (string) $userEmail,
                $user['emri'] ?? 'Përdorues',
                'Konfirmim aplikimi për event — Tirana Solidare',
                $applicantMessage,
                [
                    'bypass_preferences' => true,
                    'action_url' => "/views/events.php?id={$eventId}",
                    'action_label' => 'Shiko eventin',
                ]
            );
        }

        $guardianMessage = ($waitlisted
            ? "{$user['emri']} u regjistrua për eventin \"{$event['titulli']}\" dhe u shtua në listën e pritjes."
            : "{$user['emri']} u regjistrua për eventin \"{$event['titulli']}\" dhe aplikimi është në pritje të shqyrtimit.")
            . "\n\nDetaje:\n"
            . 'Data: ' . date('d/m/Y H:i', strtotime((string) $event['data'])) . "\n"
            . 'Vendndodhja: ' . (($event['vendndodhja'] ?? '') !== '' ? $event['vendndodhja'] : 'Do të konfirmohet në faqen e eventit.');

        ts_send_guardian_activity_email(
            $pdo,
            (int) $user['id'],
            'Fëmija juaj aplikoi për një event — Tirana Solidare',
            $guardianMessage,
            [
                'action_url' => "/views/events.php?id={$eventId}",
                'action_label' => 'Shiko eventin',
            ]
        );

        $successMsg = $waitlisted
            ? 'Eventi është plot. Jeni shtuar në listën e pritjes.'
            : 'Aplikimi u dërgua me sukses.';

        json_success([
            'id_aplikimi'    => $appId,
            'ne_liste_pritje' => $waitlisted,
            'message'         => $successMsg,
        ], 201);
        break;

    // ── UPDATE APPLICATION STATUS ──────────────────
    case 'update_status':
        require_method('PUT');
        $manager = require_event_manager();
        $id   = (int) ($_GET['id'] ?? 0);
        $body = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e aplikimit është e pavlefshme.', 400);
        }

        $newStatus = $body['statusi'] ?? '';
        $allowed   = ['pending', 'approved', 'rejected'];

        if (!in_array($newStatus, $allowed, true)) {
            json_error("Statusi duhet të jetë njëri nga: " . implode(', ', $allowed), 422);
        }

        // Fetch existing application
        $check = $pdo->prepare(
            'SELECT a.*, e.titulli AS eventi_titulli, e.id_perdoruesi AS event_owner_id
             FROM Aplikimi a
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             WHERE a.id_aplikimi = ?'
        );
        $check->execute([$id]);
        $app = $check->fetch();

        if (!$app) {
            json_error('Aplikimi nuk u gjet.', 404);
        }
        if (!ts_can_manage_event($manager, ['id_perdoruesi' => $app['event_owner_id'] ?? 0])) {
            json_error('Nuk keni leje për të menaxhuar këtë aplikim.', 403);
        }

        // Capacity re-check and status update are wrapped in a transaction to prevent
        // two admins simultaneously approving applications beyond event capacity.
        try {
            $pdo->beginTransaction();

            if ($newStatus === 'approved') {
                // Lock the event row to block concurrent approval attempts
                $evCheck = $pdo->prepare('SELECT kapaciteti FROM Eventi WHERE id_eventi = ? FOR UPDATE');
                $evCheck->execute([$app['id_eventi']]);
                $evData = $evCheck->fetch();
                if ($evData && $evData['kapaciteti'] !== null && (int) $evData['kapaciteti'] > 0) {
                    $countStmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ? AND statusi = 'approved' AND id_aplikimi != ?"
                    );
                    $countStmt->execute([$app['id_eventi'], $id]);
                    if ((int) $countStmt->fetchColumn() >= (int) $evData['kapaciteti']) {
                        $pdo->rollBack();
                        json_error('Kapaciteti i eventit është plotësuar. Nuk mund të pranohet.', 422);
                    }
                }
            }

            $stmt = $pdo->prepare('UPDATE Aplikimi SET statusi = ? WHERE id_aplikimi = ?');
            $stmt->execute([$newStatus, $id]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('applications update_status tx: ' . $e->getMessage());
            json_error('Gabim gjatë përditësimit të statusit.', 500);
        }

        // Notify the volunteer
        $statusLabel = $newStatus === 'approved' ? 'pranuar ✓' : ($newStatus === 'rejected' ? 'refuzuar ✗' : 'në pritje');
        $msg = "Aplikimi juaj për eventin \"{$app['eventi_titulli']}\" është {$statusLabel}.";
        $eventLink = "/TiranaSolidare/views/events.php?id={$app['id_eventi']}";
        $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)');
        $notifStmt->execute([$app['id_perdoruesi'], $msg, 'aplikim_event', 'event', $app['id_eventi'], $eventLink]);

        $userContact = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $userContact->execute([$app['id_perdoruesi']]);
        $recipient = $userContact->fetch();
        if ($recipient && filter_var($recipient['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            send_notification_email(
                $recipient['email'],
                $recipient['emri'] ?? 'Volunteer',
                'Njoftim i ri nga Tirana Solidare',
                $msg
            );
        }

        // Auto-promote the next waitlisted applicant if an approved slot just opened
        if ($app['statusi'] === 'approved' && in_array($newStatus, ['rejected', 'pending'], true)) {
            $nextApp = null;
            try {
                $pdo->beginTransaction();
                // Lock event row to prevent concurrent promotions from double-filling the same slot
                $evStmt = $pdo->prepare('SELECT kapaciteti FROM Eventi WHERE id_eventi = ? FOR UPDATE');
                $evStmt->execute([$app['id_eventi']]);
                $evRow = $evStmt->fetch();

                $canPromote = true;
                if ($evRow && $evRow['kapaciteti'] !== null) {
                    $approvedCnt = $pdo->prepare("SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ? AND statusi = 'approved'");
                    $approvedCnt->execute([$app['id_eventi']]);
                    if ((int) $approvedCnt->fetchColumn() >= (int) $evRow['kapaciteti']) {
                        $canPromote = false;
                    }
                }

                if ($canPromote) {
                    $nextStmt = $pdo->prepare(
                        "SELECT a.id_aplikimi, a.id_perdoruesi, p.emri, p.email
                         FROM Aplikimi a
                         JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
                         WHERE a.id_eventi = ? AND a.ne_liste_pritje = 1 AND a.statusi = 'pending'
                         ORDER BY a.aplikuar_me ASC LIMIT 1"
                    );
                    $nextStmt->execute([$app['id_eventi']]);
                    $nextApp = $nextStmt->fetch();

                    if ($nextApp) {
                        $pdo->prepare("UPDATE Aplikimi SET statusi = 'approved', ne_liste_pritje = 0 WHERE id_aplikimi = ?")
                            ->execute([$nextApp['id_aplikimi']]);
                    }
                }
                $pdo->commit();
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('waitlist promotion failed: ' . $e->getMessage());
                $nextApp = null;
            }

            if ($nextApp) {
                $promoteMsg  = "U promovuat nga lista e pritjes! Aplikimi juaj për eventin \"{$app['eventi_titulli']}\" u pranua.";
                $promoteLink = "/TiranaSolidare/views/events.php?id={$app['id_eventi']}";
                $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$nextApp['id_perdoruesi'], $promoteMsg, 'aplikim_event', 'event', $app['id_eventi'], $promoteLink]);

                if (filter_var($nextApp['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    send_notification_email(
                        $nextApp['email'],
                        $nextApp['emri'],
                        'U promovuat nga lista e pritjes — Tirana Solidare',
                        $promoteMsg
                    );
                }
            }
        }

        $statusLabels = [
    'approved' => 'Pranuar',
    'rejected' => 'Refuzuar',
];
$label = $statusLabels[$newStatus] ?? $newStatus;
json_success(['message' => "Statusi u përditësua në '$label'."]);
        break;

    // ── WITHDRAW APPLICATION ───────────────────────
    case 'withdraw':
        require_method('DELETE');
        $user = require_auth();

        $id   = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e aplikimit është e pavlefshme.', 400);
        }

        // Allow withdrawal of both pending and approved applications
        $check = $pdo->prepare(
            "SELECT a.id_aplikimi, a.statusi, a.id_eventi, e.titulli AS eventi_titulli
             FROM Aplikimi a
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             WHERE a.id_aplikimi = ? AND a.id_perdoruesi = ? AND a.statusi IN ('pending', 'approved')"
        );
        $check->execute([$id, $user['id']]);
        $withdrawn = $check->fetch();

        if (!$withdrawn) {
            json_error('Aplikimi nuk u gjet ose nuk mund të tëriqet.', 404);
        }

        // Rate limit: 10 withdrawals per hour per user per event.
        // Key includes event ID so a user enrolled in many events isn't blocked
        // by a single global bucket — each event gets its own 10/hr allowance.
        // Prevents apply→withdraw spam that floods admin notifications and abuses waitlist logic.
        if (!check_rate_limit('withdraw_event_' . $user['id'] . '_' . (int)$withdrawn['id_eventi'], 10, 3600)) {
            json_error('Po tërhiqni shumë aplikime për këtë event. Provoni përsëri pas një ore.', 429);
        }

        $pdo->prepare('DELETE FROM Aplikimi WHERE id_aplikimi = ?')->execute([$id]);

        // If an approved volunteer withdraws, try to promote next waitlisted applicant
        if ($withdrawn['statusi'] === 'approved') {
            $nextApp = null;
            try {
                $pdo->beginTransaction();
                $evStmt = $pdo->prepare('SELECT kapaciteti FROM Eventi WHERE id_eventi = ? FOR UPDATE');
                $evStmt->execute([$withdrawn['id_eventi']]);
                $evRow  = $evStmt->fetch();

                $canPromote = true;
                if ($evRow && $evRow['kapaciteti'] !== null) {
                    $approvedCnt = $pdo->prepare("SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ? AND statusi = 'approved'");
                    $approvedCnt->execute([$withdrawn['id_eventi']]);
                    if ((int) $approvedCnt->fetchColumn() >= (int) $evRow['kapaciteti']) {
                        $canPromote = false;
                    }
                }

                if ($canPromote) {
                    $nextStmt = $pdo->prepare(
                        "SELECT a.id_aplikimi, a.id_perdoruesi, p.emri, p.email
                         FROM Aplikimi a
                         JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
                         WHERE a.id_eventi = ? AND a.ne_liste_pritje = 1 AND a.statusi = 'pending'
                         ORDER BY a.aplikuar_me ASC LIMIT 1"
                    );
                    $nextStmt->execute([$withdrawn['id_eventi']]);
                    $nextApp = $nextStmt->fetch();
                    if ($nextApp) {
                        $pdo->prepare("UPDATE Aplikimi SET statusi = 'approved', ne_liste_pritje = 0 WHERE id_aplikimi = ?")
                            ->execute([$nextApp['id_aplikimi']]);
                    }
                }
                $pdo->commit();
            } catch (\Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('waitlist promotion on withdraw failed: ' . $e->getMessage());
                $nextApp = null;
            }

            if ($nextApp) {
                $promoteMsg  = "U promovuat nga lista e pritjes! Aplikimi juaj për eventin \"{$withdrawn['eventi_titulli']}\" u pranua.";
                $promoteLink = "/TiranaSolidare/views/events.php?id={$withdrawn['id_eventi']}";
                $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$nextApp['id_perdoruesi'], $promoteMsg, 'aplikim_event', 'event', $withdrawn['id_eventi'], $promoteLink]);
                if (filter_var($nextApp['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    send_notification_email(
                        $nextApp['email'],
                        $nextApp['emri'],
                        'U promovuat nga lista e pritjes — Tirana Solidare',
                        $promoteMsg
                    );
                }
            }
        }

        json_success(['message' => 'Aplikimi u tërhoq me sukses.']);
        break;

        // ── APPLICATIONS BY USER (Admin) ───────────────
case 'by_user':
    require_method('GET');
    require_admin();
    $targetId = (int) ($_GET['id'] ?? 0);

    if ($targetId <= 0) {
        json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
    }

try {
    $pagination = get_pagination();

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi WHERE id_perdoruesi = ?');
    $countStmt->execute([$targetId]);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT a.*, e.titulli AS eventi_titulli, e.data AS eventi_data,
                e.vendndodhja AS eventi_vendndodhja
         FROM Aplikimi a
         JOIN Eventi e ON e.id_eventi = a.id_eventi
         WHERE a.id_perdoruesi = ?
         ORDER BY a.aplikuar_me DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute([$targetId, $pagination['limit'], $pagination['offset']]);
    json_success([
        'applications' => ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC)),
        'total'        => $total,
        'page'         => $pagination['page'],
        'limit'        => $pagination['limit'],
        'total_pages'  => (int) ceil($total / $pagination['limit']),
    ]);
} catch (\Exception $e) {
    error_log('applications by_user: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    json_error('Gabim gjatë marrjes së aplikimeve.', 500);
}
break;

    // ── MARK PRESENCE ─────────────────────────────
    case 'mark_presence':
        require_method('PUT');
        $manager = require_event_manager();
        $id    = (int) ($_GET['id'] ?? 0);
        $body  = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e aplikimit është e pavlefshme.', 400);
        }

        $presence = $body['statusi'] ?? '';
        if (!in_array($presence, ['present', 'absent'], true)) {
            json_error("Statusi duhet të jetë 'present' ose 'absent'.", 422);
        }

        // Fetch application with event info
        $check = $pdo->prepare(
            "SELECT a.*, e.titulli AS eventi_titulli, e.data AS eventi_data, e.statusi AS eventi_statusi, e.id_perdoruesi AS event_owner_id
             FROM Aplikimi a
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             WHERE a.id_aplikimi = ? AND a.statusi = 'approved'"
        );
        $check->execute([$id]);
        $app = $check->fetch();

        if (!$app) {
            json_error('Aplikimi nuk u gjet ose nuk është i pranuar.', 404);
        }
        if (!ts_can_manage_event($manager, ['id_perdoruesi' => $app['event_owner_id'] ?? 0])) {
            json_error('Nuk keni leje për të menaxhuar këtë aplikim.', 403);
        }

        // Cannot mark presence for cancelled events
        if ($app['eventi_statusi'] === 'cancelled') {
            json_error('Prezenca nuk mund të shënohet për evente të anuluara.', 422);
        }

        // Only allow marking presence after event date
        if (strtotime($app['eventi_data']) > time()) {
            json_error('Prezenca mund të shënohet vetëm pasi eventi ka përfunduar.', 422);
        }

        $stmt = $pdo->prepare('UPDATE Aplikimi SET statusi = ? WHERE id_aplikimi = ?');
        $stmt->execute([$presence, $id]);

        log_admin_action($manager['id'], 'mark_presence', 'application', $id, [
            'eventi' => $app['eventi_titulli'],
            'statusi' => $presence,
        ]);

        json_success(['message' => "Prezenca u shënoua si '$presence'."]);
        break;

    // ── BULK APPROVE ALL PENDING ───────────────────
    case 'bulk_approve':
        require_method('PUT');
        $manager = require_event_manager();
        $eventId = (int) ($_GET['event_id'] ?? 0);

        if ($eventId <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        try {
            $pdo->beginTransaction();

            // Lock event row, read capacity and verify ownership.
            $evStmt = $pdo->prepare('SELECT id_eventi, id_perdoruesi, titulli, kapaciteti FROM Eventi WHERE id_eventi = ? FOR UPDATE');
            $evStmt->execute([$eventId]);
            $ev = $evStmt->fetch();
            if (!$ev) {
                $pdo->rollBack();
                json_error('Eventi nuk u gjet.', 404);
            }
            if (!ts_can_manage_event($manager, $ev)) {
                $pdo->rollBack();
                json_error('Nuk keni leje për të menaxhuar këtë event.', 403);
            }

            $capacity = ($ev['kapaciteti'] !== null && (int) $ev['kapaciteti'] > 0)
                ? (int) $ev['kapaciteti']
                : PHP_INT_MAX;

            // Count already approved
            $approvedStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ? AND statusi = 'approved'"
            );
            $approvedStmt->execute([$eventId]);
            $approvedCount = (int) $approvedStmt->fetchColumn();

            $slots = $capacity - $approvedCount;

            // Fetch pending applications
            $pendingStmt = $pdo->prepare(
                "SELECT a.id_aplikimi, a.id_perdoruesi, p.emri AS emri, p.email AS email
                 FROM Aplikimi a
                 JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
                 WHERE a.id_eventi = ? AND a.statusi = 'pending'
                 ORDER BY a.aplikuar_me ASC"
            );
            $pendingStmt->execute([$eventId]);
            $pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

            $approved = 0;
            $skipped  = 0;
            $updateStmt = $pdo->prepare("UPDATE Aplikimi SET statusi = 'approved' WHERE id_aplikimi = ?");
            $notifStmt  = $pdo->prepare(
                'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $eventLink = "/TiranaSolidare/views/events.php?id={$eventId}";

            foreach ($pending as $app) {
                if ($approved >= $slots) {
                    $skipped++;
                    continue;
                }
                $updateStmt->execute([$app['id_aplikimi']]);
                $msg = "Aplikimi juaj për eventin \"{$ev['titulli']}\" është pranuar ✓.";
                $notifStmt->execute([
                    $app['id_perdoruesi'], $msg,
                    'aplikim_event', 'event', $eventId, $eventLink,
                ]);
                if (filter_var($app['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    send_notification_email(
                        $app['email'],
                        $app['emri'] ?? 'Volunteer',
                        'Njoftim i ri nga Tirana Solidare',
                        $msg
                    );
                }
                $approved++;
            }

            $pdo->commit();

            log_admin_action($manager['id'], 'bulk_approve', 'event', $eventId, [
                'approved' => $approved,
                'skipped'  => $skipped,
            ]);

            $message = $approved > 0
                ? "{$approved} aplikime u pranuan me sukses."
                : 'Asnjë aplikim nuk u pranua.';
            if ($skipped > 0) {
                $message .= " {$skipped} u anashkaluan (kapaciteti u plotësua).";
            }
            json_success(['approved' => $approved, 'skipped' => $skipped, 'message' => $message]);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('applications bulk_approve: ' . $e->getMessage());
            json_error('Gabim gjatë pranimit masiv.', 500);
        }
        break;

    // ── BULK REJECT ALL PENDING ────────────────────
    case 'bulk_reject':
        require_method('PUT');
        $manager = require_event_manager();
        $eventId = (int) ($_GET['event_id'] ?? 0);

        if ($eventId <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        try {
            $pdo->beginTransaction();

            $evStmt = $pdo->prepare('SELECT id_eventi, id_perdoruesi, titulli FROM Eventi WHERE id_eventi = ?');
            $evStmt->execute([$eventId]);
            $ev = $evStmt->fetch();
            if (!$ev) {
                $pdo->rollBack();
                json_error('Eventi nuk u gjet.', 404);
            }
            if (!ts_can_manage_event($manager, $ev)) {
                $pdo->rollBack();
                json_error('Nuk keni leje për të menaxhuar këtë event.', 403);
            }

            $pendingStmt = $pdo->prepare(
                "SELECT a.id_aplikimi, a.id_perdoruesi, p.emri AS emri, p.email AS email
                 FROM Aplikimi a
                 JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
                 WHERE a.id_eventi = ? AND a.statusi = 'pending'
                 ORDER BY a.aplikuar_me ASC"
            );
            $pendingStmt->execute([$eventId]);
            $pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($pending)) {
                $pdo->rollBack();
                json_success(['rejected' => 0, 'message' => 'Nuk ka aplikime në pritje.']);
                break;
            }

            $updateStmt = $pdo->prepare("UPDATE Aplikimi SET statusi = 'rejected' WHERE id_aplikimi = ?");
            $notifStmt  = $pdo->prepare(
                'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $eventLink  = "/TiranaSolidare/views/events.php?id={$eventId}";
            $rejected   = 0;

            foreach ($pending as $app) {
                $updateStmt->execute([$app['id_aplikimi']]);
                $msg = "Aplikimi juaj për eventin \"{$ev['titulli']}\" është refuzuar.";
                $notifStmt->execute([
                    $app['id_perdoruesi'], $msg,
                    'aplikim_event', 'event', $eventId, $eventLink,
                ]);
                if (filter_var($app['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                    send_notification_email(
                        $app['email'],
                        $app['emri'] ?? 'Volunteer',
                        'Njoftim i ri nga Tirana Solidare',
                        $msg
                    );
                }
                $rejected++;
            }

            $pdo->commit();

            log_admin_action($manager['id'], 'bulk_reject', 'event', $eventId, [
                'rejected' => $rejected,
            ]);

            json_success(['rejected' => $rejected, 'message' => "{$rejected} aplikime u refuzuan."]);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('applications bulk_reject: ' . $e->getMessage());
            json_error('Gabim gjatë refuzimit masiv.', 500);
        }
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, by_event, apply, update_status, withdraw, mark_presence, bulk_approve, bulk_reject.', 400);
}
