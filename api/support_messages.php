<?php
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        require_method('GET');
        require_admin();
        release_session();

        if (!ts_db_has_column($pdo, 'support_messages', 'id_support_message')) {
            json_error('Inbox-i i kontaktit nuk është aktivizuar ende. Ekzekutoni migrimin support_messages.', 500);
        }

        $pagination = get_pagination(20, 100);
        $status = trim((string) ($_GET['status'] ?? 'all'));
        $search = trim((string) ($_GET['search'] ?? ''));
        if (!in_array($status, ['all', 'new', 'read', 'replied', 'resolved'], true)) {
            $status = 'all';
        }

        $where = [];
        $params = [];
        if ($status !== 'all') {
            $where[] = 'sm.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(sm.from_name LIKE ? OR sm.from_email LIKE ? OR sm.subject LIKE ? OR sm.message LIKE ?)';
            $needle = '%' . $search . '%';
            array_push($params, $needle, $needle, $needle, $needle);
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM support_messages sm {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $summary = $pdo->query(
            "SELECT
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_total,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) AS read_total,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) AS replied_total,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) AS resolved_total,
                COUNT(*) AS all_total
             FROM support_messages"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare(
            "SELECT sm.*, 
                    u.emri AS sender_user_name,
                    rb.emri AS replied_by_name,
                    rs.emri AS resolved_by_name
             FROM support_messages sm
             LEFT JOIN Perdoruesi u ON u.id_perdoruesi = sm.from_user_id
             LEFT JOIN Perdoruesi rb ON rb.id_perdoruesi = sm.replied_by
             LEFT JOIN Perdoruesi rs ON rs.id_perdoruesi = sm.resolved_by
             {$whereSql}
             ORDER BY FIELD(sm.status, 'new', 'read', 'replied', 'resolved'), sm.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $bindIndex = 1;
        foreach ($params as $param) {
            $stmt->bindValue($bindIndex++, $param, PDO::PARAM_STR);
        }
        $stmt->bindValue($bindIndex++, (int) $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue($bindIndex, (int) $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        json_success([
            'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'summary' => [
                'all' => (int) ($summary['all_total'] ?? 0),
                'new' => (int) ($summary['new_total'] ?? 0),
                'read' => (int) ($summary['read_total'] ?? 0),
                'replied' => (int) ($summary['replied_total'] ?? 0),
                'resolved' => (int) ($summary['resolved_total'] ?? 0),
            ],
            'total' => $total,
            'page' => $pagination['page'],
            'limit' => $pagination['limit'],
            'total_pages' => (int) ceil($total / max(1, $pagination['limit'])),
        ]);
        break;

    case 'status':
        require_method('PUT');
        $admin = require_admin();
        $body = get_json_body();

        if (!ts_db_has_column($pdo, 'support_messages', 'id_support_message')) {
            json_error('Inbox-i i kontaktit nuk është aktivizuar ende. Ekzekutoni migrimin support_messages.', 500);
        }

        $id = (int) ($_GET['id'] ?? $body['id_support_message'] ?? 0);
        $status = trim((string) ($body['status'] ?? ''));

        if ($id <= 0) {
            json_error('ID-ja e mesazhit është e pavlefshme.', 400);
        }
        if (!in_array($status, ['new', 'read', 'resolved'], true)) {
            json_error('Statusi i mesazhit është i pavlefshëm.', 422);
        }

        $stmt = $pdo->prepare('SELECT id_support_message, status FROM support_messages WHERE id_support_message = ? LIMIT 1');
        $stmt->execute([$id]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$message) {
            json_error('Mesazhi nuk u gjet.', 404);
        }

        $sets = ['status = ?'];
        $params = [$status];
        if ($status === 'resolved') {
            $sets[] = 'resolved_by = ?';
            $sets[] = 'resolved_at = NOW()';
            $params[] = $admin['id'];
        } else {
            $sets[] = 'resolved_by = NULL';
            $sets[] = 'resolved_at = NULL';
        }

        $params[] = $id;
        $update = $pdo->prepare('UPDATE support_messages SET ' . implode(', ', $sets) . ' WHERE id_support_message = ?');
        $update->execute($params);

        log_admin_action($admin['id'], 'update_support_message_status', 'support_message', $id, [
            'from_status' => $message['status'] ?? null,
            'to_status' => $status,
        ]);

        json_success(['message' => 'Statusi i mesazhit u përditësua.']);
        break;

    case 'reply':
        require_method('POST');
        $admin = require_admin();
        $body = get_json_body();

        if (!ts_db_has_column($pdo, 'support_messages', 'id_support_message')) {
            json_error('Inbox-i i kontaktit nuk është aktivizuar ende. Ekzekutoni migrimin support_messages.', 500);
        }

        $id = (int) ($_GET['id'] ?? $body['id_support_message'] ?? 0);
        $errors = [];
        $replyMessage = required_field($body, 'message', $errors);
        $markResolved = !empty($body['mark_resolved']);

        if ($id <= 0) {
            json_error('ID-ja e mesazhit është e pavlefshme.', 400);
        }
        if ($errors !== []) {
            json_error('Përgjigjja është e detyrueshme.', 422, $errors);
        }
        if ($lenErr = validate_length($replyMessage ?? '', 5, 4000, 'reply')) {
            json_error($lenErr, 422);
        }
        if ($profErr = check_profanity($replyMessage ?? '')) {
            json_error($profErr, 422);
        }

        $stmt = $pdo->prepare(
            'SELECT id_support_message, from_user_id, from_name, from_email, subject, status
             FROM support_messages
             WHERE id_support_message = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$message) {
            json_error('Mesazhi nuk u gjet.', 404);
        }

        $recipientEmail = trim((string) ($message['from_email'] ?? ''));
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            json_error('Email-i i dërguesit nuk është i vlefshëm.', 422);
        }

        $recipientName = trim((string) ($message['from_name'] ?? 'Përdorues'));
        if ($recipientName === '') {
            $recipientName = 'Përdorues';
        }

        $subject = 'Përgjigje nga Tirana Solidare: ' . trim((string) ($message['subject'] ?? 'Mesazhi juaj'));
        $mailMessage = "Përshëndetje {$recipientName},\n\n{$replyMessage}\n\nNëse keni pyetje të tjera, mund të na shkruani sërish nga faqja e kontaktit.";

        $sent = send_notification_email(
            $recipientEmail,
            $recipientName,
            $subject,
            $mailMessage,
            [
                'bypass_preferences' => true,
                'send_now' => true,
                'require_send_now_success' => true,
                'reply_to_email' => ts_support_email(),
                'reply_to_name' => ts_support_name(),
                'action_url' => ts_contact_page_path(),
                'action_label' => 'Kontakto sërish',
            ]
        );

        if (!$sent) {
            json_error('Përgjigjja me email nuk u dërgua. Kontrolloni konfigurimin SMTP.', 502);
        }

        $newStatus = $markResolved ? 'resolved' : 'replied';
        $update = $pdo->prepare(
            'UPDATE support_messages
             SET status = ?, last_reply_message = ?, replied_by = ?, replied_at = NOW(),
                 resolved_by = ?, resolved_at = ?
             WHERE id_support_message = ?'
        );
        $resolvedBy = $markResolved ? $admin['id'] : null;
        $resolvedAt = $markResolved ? date('Y-m-d H:i:s') : null;
        $update->execute([$newStatus, $replyMessage, $admin['id'], $resolvedBy, $resolvedAt, $id]);

        $fromUserId = (int) ($message['from_user_id'] ?? 0);
        if ($fromUserId > 0) {
            ts_insert_notification(
                $pdo,
                $fromUserId,
                'Administratori iu përgjigj mesazhit tuaj të kontaktit në email.',
                'support_reply',
                'support_message',
                $id,
                ts_contact_page_path()
            );
        }

        log_admin_action($admin['id'], 'reply_support_message', 'support_message', $id, [
            'subject' => $message['subject'] ?? null,
            'status' => $newStatus,
        ]);

        json_success(['message' => 'Përgjigjja u dërgua me sukses.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, status, reply.', 400);
}