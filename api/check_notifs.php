<?php
/**
 * api/check_notifs.php
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
require_once __DIR__ . '/api_helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── LIST NOTIFICATIONS ─────────────────────────
    case 'list':
        require_method('GET');
        $user       = require_auth();
        $pagination = get_pagination(30);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM Njoftimi WHERE id_perdoruesi = ?');
        $countStmt->execute([$user['id']]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT id_njoftimi, mesazhi, is_read, krijuar_me
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
        ]);
        break;

    // ── UNREAD COUNT ───────────────────────────────
    case 'unread_count':
        require_method('GET');
        $user = require_auth();

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

    default:
        json_error('Veprim i panjohur. Përdorni: list, unread_count, mark_read, mark_all_read, delete.', 400);
}
