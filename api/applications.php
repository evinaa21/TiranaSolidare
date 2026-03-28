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
        require_admin();
        $eventId = (int) ($_GET['id'] ?? 0);

        if ($eventId <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        // Verify event exists (A-05)
        $evCheck = $pdo->prepare('SELECT id_eventi, titulli, data FROM Eventi WHERE id_eventi = ?');
        $evCheck->execute([$eventId]);
        $eventRow = $evCheck->fetch();
        if (!$eventRow) {
            json_error('Eventi nuk u gjet.', 404);
        }

        // Paginated fetch (A-06)
        $pagination = get_pagination();

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ?');
        $countStmt->execute([$eventId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT a.*, p.emri AS vullnetari_emri, p.email AS vullnetari_email
             FROM Aplikimi a
             JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
             WHERE a.id_eventi = ?
             ORDER BY a.aplikuar_me DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$eventId, $pagination['limit'], $pagination['offset']]);
        $apps = ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

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
            'summary'      => $stats,
            'event_data'   => $eventRow['data'],
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

        if (is_admin_role($user['roli'])) {
            json_error('Administratorët nuk mund të aplikojnë si vullnetarë.', 403);
        }

        $body   = get_json_body();
        $errors = [];
        $eventId = isset($body['id_eventi']) ? (int) $body['id_eventi'] : 0;

        if ($eventId <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 422);
        }

        // Check event exists
        $check = $pdo->prepare('SELECT id_eventi, titulli, data, kapaciteti, statusi FROM Eventi WHERE id_eventi = ? AND is_archived = 0');
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

        // Check for duplicate application
        $dup = $pdo->prepare(
            'SELECT id_aplikimi FROM Aplikimi WHERE id_perdoruesi = ? AND id_eventi = ?'
        );
        $dup->execute([$user['id'], $eventId]);

        if ($dup->fetch()) {
            json_error('Ju keni aplikuar tashmë për këtë event.', 409);
        }

        // Determine waitlist status based on capacity
        $waitlisted = 0;
        if ($event['kapaciteti'] !== null && (int) $event['kapaciteti'] > 0) {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ? AND statusi = 'approved'"
            );
            $countStmt->execute([$eventId]);
            $acceptedCount = (int) $countStmt->fetchColumn();
            if ($acceptedCount >= (int) $event['kapaciteti']) {
                $waitlisted = 1;
            }
        }

        // Insert application
        $stmt = $pdo->prepare(
            "INSERT INTO Aplikimi (id_perdoruesi, id_eventi, statusi, ne_liste_pritje) VALUES (?, ?, 'pending', ?)"
        );
        $stmt->execute([$user['id'], $eventId, $waitlisted]);

        $appId = (int) $pdo->lastInsertId();

        // Create notification for admins
        $admins = $pdo->query("SELECT id_perdoruesi FROM Perdoruesi WHERE roli IN ('admin','super_admin')");
        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $msg = "{$user['emri']} aplikoi për eventin \"{$event['titulli']}\".";
        $eventLink = "/TiranaSolidare/views/events.php?id={$eventId}";
        foreach ($admins as $admin) {
            $notifStmt->execute([$admin['id_perdoruesi'], $msg, 'aplikim_event', 'event', $eventId, $eventLink]);
        }

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
        require_admin();
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
            'SELECT a.*, e.titulli AS eventi_titulli
             FROM Aplikimi a
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             WHERE a.id_aplikimi = ?'
        );
        $check->execute([$id]);
        $app = $check->fetch();

        if (!$app) {
            json_error('Aplikimi nuk u gjet.', 404);
        }

        // Capacity re-check when approving
        if ($newStatus === 'approved') {
            $evCheck = $pdo->prepare('SELECT kapaciteti FROM Eventi WHERE id_eventi = ?');
            $evCheck->execute([$app['id_eventi']]);
            $evData = $evCheck->fetch();
            if ($evData && $evData['kapaciteti'] !== null && (int) $evData['kapaciteti'] > 0) {
                $countStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM Aplikimi WHERE id_eventi = ? AND statusi = 'approved' AND id_aplikimi != ?"
                );
                $countStmt->execute([$app['id_eventi'], $id]);
                if ((int) $countStmt->fetchColumn() >= (int) $evData['kapaciteti']) {
                    json_error('Kapaciteti i eventit është plotësuar. Nuk mund të pranohet.', 422);
                }
            }
        }

        // Update
        $stmt = $pdo->prepare('UPDATE Aplikimi SET statusi = ? WHERE id_aplikimi = ?');
        $stmt->execute([$newStatus, $id]);

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

        json_success(['message' => "Statusi u përditësua në '$newStatus'."]);
        break;

    // ── WITHDRAW APPLICATION ───────────────────────
    case 'withdraw':
        require_method('DELETE');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e aplikimit është e pavlefshme.', 400);
        }

        // Only own pending applications can be withdrawn
        $check = $pdo->prepare(
            "SELECT id_aplikimi FROM Aplikimi WHERE id_aplikimi = ? AND id_perdoruesi = ? AND statusi = 'pending'"
        );
        $check->execute([$id, $user['id']]);

        if (!$check->fetch()) {
            json_error('Aplikimi nuk u gjet ose nuk mund të tërhiqet.', 404);
        }

        $pdo->prepare('DELETE FROM Aplikimi WHERE id_aplikimi = ?')->execute([$id]);

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
    $stmt = $pdo->prepare(
        "SELECT a.*, e.titulli AS eventi_titulli, e.data AS eventi_data,
                e.vendndodhja AS eventi_vendndodhja
         FROM Aplikimi a
         JOIN Eventi e ON e.id_eventi = a.id_eventi
         WHERE a.id_perdoruesi = ?
         ORDER BY a.aplikuar_me DESC"
    );
    $stmt->execute([$targetId]);
    json_success(['applications' => ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC))]);
} catch (\Exception $e) {
    error_log('applications by_user: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    json_error('Gabim gjatë marrjes së aplikimeve.', 500);
}
break;

    // ── MARK PRESENCE ─────────────────────────────
    case 'mark_presence':
        require_method('PUT');
        $admin = require_admin();
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
            "SELECT a.*, e.titulli AS eventi_titulli, e.data AS eventi_data
             FROM Aplikimi a
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             WHERE a.id_aplikimi = ? AND a.statusi = 'approved'"
        );
        $check->execute([$id]);
        $app = $check->fetch();

        if (!$app) {
            json_error('Aplikimi nuk u gjet ose nuk është i pranuar.', 404);
        }

        // Only allow marking presence after event date
        if (strtotime($app['eventi_data']) > time()) {
            json_error('Prezenca mund të shënohet vetëm pasi eventi ka përfunduar.', 422);
        }

        $stmt = $pdo->prepare('UPDATE Aplikimi SET statusi = ? WHERE id_aplikimi = ?');
        $stmt->execute([$presence, $id]);

        log_admin_action($admin['id'], 'mark_presence', 'application', $id, [
            'eventi' => $app['eventi_titulli'],
            'statusi' => $presence,
        ]);

        json_success(['message' => "Prezenca u shënoua si '$presence'."]);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, by_event, apply, update_status, withdraw, mark_presence.', 400);
}
