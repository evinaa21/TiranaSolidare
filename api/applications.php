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
 * DELETE ?action=withdraw&id=<id>        – Withdraw application (Volunteer)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/api_helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── LIST APPLICATIONS ──────────────────────────
    case 'list':
        require_method('GET');
        $user       = require_auth();
        $pagination = get_pagination();

        if ($user['roli'] === 'Admin') {
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

        $applications = $stmt->fetchAll();

        json_success([
            'applications' => $applications,
            'total'        => $total,
            'page'         => $pagination['page'],
            'limit'        => $pagination['limit'],
            'total_pages'  => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── APPLICATIONS BY EVENT ──────────────────────
    case 'by_event':
        require_method('GET');
        require_admin();
        $eventId = (int) ($_GET['id'] ?? 0);

        if ($eventId <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            "SELECT a.*, p.emri AS vullnetari_emri, p.email AS vullnetari_email
             FROM Aplikimi a
             JOIN Perdoruesi p ON p.id_perdoruesi = a.id_perdoruesi
             WHERE a.id_eventi = ?
             ORDER BY a.aplikuar_me DESC"
        );
        $stmt->execute([$eventId]);
        $apps = $stmt->fetchAll();

        // Summary counts
        $summary = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN statusi = 'Në pritje' THEN 1 ELSE 0 END) AS ne_pritje,
                SUM(CASE WHEN statusi = 'Pranuar' THEN 1 ELSE 0 END)  AS pranuar,
                SUM(CASE WHEN statusi = 'Refuzuar' THEN 1 ELSE 0 END) AS refuzuar
             FROM Aplikimi WHERE id_eventi = ?"
        );
        $summary->execute([$eventId]);
        $stats = $summary->fetch();

        json_success([
            'applications' => $apps,
            'summary'      => $stats,
        ]);
        break;

    // ── APPLY FOR EVENT ────────────────────────────
    case 'apply':
        require_method('POST');
        $user = require_auth();

        if ($user['roli'] === 'Admin') {
            json_error('Administratorët nuk mund të aplikojnë si vullnetarë.', 403);
        }

        $body   = get_json_body();
        $errors = [];
        $eventId = isset($body['id_eventi']) ? (int) $body['id_eventi'] : 0;

        if ($eventId <= 0) {
            json_error('ID-ja e eventit është e pavlefshme.', 422);
        }

        // Check event exists
        $check = $pdo->prepare('SELECT id_eventi, titulli FROM Eventi WHERE id_eventi = ?');
        $check->execute([$eventId]);
        $event = $check->fetch();

        if (!$event) {
            json_error('Eventi nuk u gjet.', 404);
        }

        // Check for duplicate application
        $dup = $pdo->prepare(
            'SELECT id_aplikimi FROM Aplikimi WHERE id_perdoruesi = ? AND id_eventi = ?'
        );
        $dup->execute([$user['id'], $eventId]);

        if ($dup->fetch()) {
            json_error('Ju keni aplikuar tashmë për këtë event.', 409);
        }

        // Insert application
        $stmt = $pdo->prepare(
            "INSERT INTO Aplikimi (id_perdoruesi, id_eventi, statusi) VALUES (?, ?, 'Në pritje')"
        );
        $stmt->execute([$user['id'], $eventId]);

        $appId = (int) $pdo->lastInsertId();

        // Create notification for admins
        $admins = $pdo->query("SELECT id_perdoruesi FROM Perdoruesi WHERE roli = 'Admin'");
        $notifStmt = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi) VALUES (?, ?)'
        );
        $msg = "{$user['emri']} aplikoi për eventin \"{$event['titulli']}\".";
        foreach ($admins as $admin) {
            $notifStmt->execute([$admin['id_perdoruesi'], $msg]);
        }

        json_success([
            'id_aplikimi' => $appId,
            'message'     => 'Aplikimi u dërgua me sukses.',
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
        $allowed   = ['Në pritje', 'Pranuar', 'Refuzuar'];

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

        // Update
        $stmt = $pdo->prepare('UPDATE Aplikimi SET statusi = ? WHERE id_aplikimi = ?');
        $stmt->execute([$newStatus, $id]);

        // Notify the volunteer
        $statusLabel = $newStatus === 'Pranuar' ? 'pranuar ✓' : ($newStatus === 'Refuzuar' ? 'refuzuar ✗' : 'në pritje');
        $msg = "Aplikimi juaj për eventin \"{$app['eventi_titulli']}\" u {$statusLabel}.";
        $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi) VALUES (?, ?)');
        $notifStmt->execute([$app['id_perdoruesi'], $msg]);

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
            "SELECT id_aplikimi FROM Aplikimi WHERE id_aplikimi = ? AND id_perdoruesi = ? AND statusi = 'Në pritje'"
        );
        $check->execute([$id, $user['id']]);

        if (!$check->fetch()) {
            json_error('Aplikimi nuk u gjet ose nuk mund të tërhiqet.', 404);
        }

        $pdo->prepare('DELETE FROM Aplikimi WHERE id_aplikimi = ?')->execute([$id]);

        json_success(['message' => 'Aplikimi u tërhoq me sukses.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, by_event, apply, update_status, withdraw.', 400);
}
