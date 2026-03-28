<?php
/**
 * api/notifications.php
 * ---------------------------------------------------
 * Notification API – list, mark read, count unread
 *
 * GET    ?action=list          – All notifications for current user
 * GET    ?action=unread_count  – Number of unread notifications
 * PUT    ?action=mark_read&id= – Mark one notification as read
 * PUT    ?action=mark_all_read – Mark all notifications as read
 * DELETE ?action=delete&id=    – Delete a notification
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── LIST NOTIFICATIONS ─────────────────────────
    case 'list':
        require_method('GET');
        $user       = require_auth();
        release_session();
        $pagination = get_pagination(30);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Njoftimi WHERE id_perdoruesi = ?');
        $countStmt->execute([$user['id']]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT id_njoftimi, mesazhi, tipi, target_type, target_id, linku, is_read, krijuar_me
             FROM Njoftimi
             WHERE id_perdoruesi = ?
             ORDER BY krijuar_me DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$user['id'], $pagination['limit'], $pagination['offset']]);
        $notifs = $stmt->fetchAll();

        json_success([
            'notifications' => $notifs,
            'total'         => $total,
            'page'          => $pagination['page'],
            'limit'         => $pagination['limit'],
            'total_pages'   => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── UNREAD COUNT ───────────────────────────────
    case 'unread_count':
        require_method('GET');
        $user = require_auth();
        release_session();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM Njoftimi WHERE id_perdoruesi = ? AND is_read = 0'
        );
        $stmt->execute([$user['id']]);
        $count = (int) $stmt->fetchColumn();

        json_success(['unread' => $count]);
        break;

    // ── MARK SINGLE AS READ ────────────────────────
    case 'mark_read':
        require_method('PUT');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e njoftimit është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            'UPDATE Njoftimi SET is_read = 1 WHERE id_njoftimi = ? AND id_perdoruesi = ?'
        );
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            json_error('Njoftimi nuk u gjet.', 404);
        }

        json_success(['message' => 'Njoftimi u shënua si i lexuar.']);
        break;

    // ── MARK ALL AS READ ───────────────────────────
    case 'mark_all_read':
        require_method('PUT');
        $user = require_auth();

        $stmt = $pdo->prepare(
            'UPDATE Njoftimi SET is_read = 1 WHERE id_perdoruesi = ? AND is_read = 0'
        );
        $stmt->execute([$user['id']]);

        json_success(['updated' => $stmt->rowCount()]);
        break;

    // ── DELETE NOTIFICATION ────────────────────────
    case 'delete':
        require_method('DELETE');
        $user = require_auth();
        $id   = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e njoftimit është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM Njoftimi WHERE id_njoftimi = ? AND id_perdoruesi = ?'
        );
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            json_error('Njoftimi nuk u gjet.', 404);
        }

        json_success(['message' => 'Njoftimi u fshi.']);
        break;

    // ── BROADCAST NOTIFICATION (Super Admin) ───────
    case 'broadcast':
        require_method('POST');
        require_super_admin();
        $body   = get_json_body();
        $errors = [];

        $mesazhi = required_field($body, 'mesazhi', $errors);
        if (!empty($errors)) {
            json_error('Mesazhi është i nevojshëm.', 422, $errors);
        }

        if ($lenErr = validate_length($mesazhi, 1, 1000, 'mesazhi')) {
            json_error($lenErr, 422);
        }

        $roli  = trim($body['roli'] ?? 'all');    // 'all' | 'volunteer' | 'admin'
        $linku = !empty($body['linku']) ? trim($body['linku']) : null;

        // Validate link to prevent stored XSS via javascript: or data: URIs
        if ($linku !== null && !str_starts_with($linku, '/TiranaSolidare/')) {
            json_error('Linku duhet të jetë relativ dhe të fillojë me /TiranaSolidare/.', 422);
        }

        // Only allow known role values to avoid injection
        if (!in_array($roli, ['all', 'volunteer', 'admin', 'super_admin'], true)) {
            $roli = 'all';
        }

        if ($roli === 'all') {
            $uStmt = $pdo->query("SELECT id_perdoruesi FROM Perdoruesi WHERE statusi_llogarise = 'active'");
        } else {
            $uStmt = $pdo->prepare("SELECT id_perdoruesi FROM Perdoruesi WHERE roli = ? AND statusi_llogarise = 'active'");
            $uStmt->execute([$roli]);
        }
        $userIds = $uStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($userIds)) {
            json_error('Asnjë përdorues aktiv u gjet për rolin zgjedhur.', 404);
        }

        // Bulk INSERT with a transaction instead of N separate INSERTs.
        // This is both faster and atomic — partial deliveries on timeout are impossible.
        try {
            $pdo->beginTransaction();
            $batchSize = 500;
            $count     = 0;
            foreach (array_chunk($userIds, $batchSize) as $chunk) {
                $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?, 0)'));
                $flatParams   = [];
                foreach ($chunk as $uid) {
                    $flatParams[] = $uid;
                    $flatParams[] = $mesazhi;
                    $flatParams[] = 'broadcast';
                    $flatParams[] = $linku;
                }
                $pdo->prepare(
                    "INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, linku, is_read) VALUES $placeholders"
                )->execute($flatParams);
                $count += count($chunk);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('broadcast failed: ' . $e->getMessage());
            json_error('Gabim gjatë dërgimit të njoftimit.', 500);
        }

        json_success(['sent' => $count, 'message' => "Njoftimi u dërgua te $count përdorues."]);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, unread_count, mark_read, mark_all_read, delete, broadcast.', 400);
}
