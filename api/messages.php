<?php
/**
 * api/messages.php
 * ---------------------------------------------------
 * In-App Messaging API (Mesazhi)
 *
 * GET    ?action=conversations         – List conversations
 * GET    ?action=thread&with=<user_id> – Message thread with a user
 * POST   ?action=send                  – Send a message
 * PUT    ?action=mark_read&with=<id>   – Mark thread as read
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'conversations';

switch ($action) {

    // ── LIST CONVERSATIONS ─────────────────────────
    case 'conversations':
        require_method('GET');
        $user = require_auth();
        release_session();

        // Get list of users with whom the current user has exchanged messages,
        // along with the last message and unread count.
        // Uses MAX(id_mesazhi) + JOIN to avoid relying on MySQL's lenient GROUP BY
        // which breaks under ONLY_FULL_GROUP_BY (default in MySQL 5.7.5+).
        $stmt = $pdo->prepare(
            "SELECT
                c.other_id,
                p.emri AS other_emri,
                p.profile_color AS other_color,
                m_last.mesazhi AS last_message,
                c.last_time,
                c.unread_count
             FROM (
                SELECT
                    CASE WHEN derguesi_id = ? THEN marruesi_id ELSE derguesi_id END AS other_id,
                    MAX(id_mesazhi) AS last_msg_id,
                    MAX(krijuar_me) AS last_time,
                    SUM(CASE WHEN marruesi_id = ? AND is_read = 0 THEN 1 ELSE 0 END) AS unread_count
                FROM Mesazhi
                WHERE derguesi_id = ? OR marruesi_id = ?
                GROUP BY other_id
             ) c
             JOIN Perdoruesi p ON p.id_perdoruesi = c.other_id
             JOIN Mesazhi m_last ON m_last.id_mesazhi = c.last_msg_id
             ORDER BY c.last_time DESC"
        );
        $uid = $user['id'];
        $stmt->execute([$uid, $uid, $uid, $uid]);
        $conversations = ts_normalize_rows($stmt->fetchAll());

        // Total unread count
        $unreadStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Mesazhi WHERE marruesi_id = ? AND is_read = 0"
        );
        $unreadStmt->execute([$uid]);
        $totalUnread = (int) $unreadStmt->fetchColumn();

        json_success([
            'conversations' => $conversations,
            'total_unread'  => $totalUnread,
        ]);
        break;

    // ── MESSAGE THREAD ─────────────────────────────
    case 'thread':
        require_method('GET');
        $user = require_auth();
        $withId = (int) ($_GET['with'] ?? 0);

        if ($withId <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        // Verify other user exists
        $otherCheck = $pdo->prepare('SELECT id_perdoruesi, emri, profile_color FROM Perdoruesi WHERE id_perdoruesi = ?');
        $otherCheck->execute([$withId]);
        $other = $otherCheck->fetch();
        if (!$other) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        $pagination = get_pagination(50);

        // Fetch messages between the two users
        $stmt = $pdo->prepare(
            "SELECT id_mesazhi, derguesi_id, marruesi_id, mesazhi, is_read, krijuar_me
             FROM Mesazhi
             WHERE (derguesi_id = ? AND marruesi_id = ?)
                OR (derguesi_id = ? AND marruesi_id = ?)
             ORDER BY krijuar_me DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$user['id'], $withId, $withId, $user['id'], $pagination['limit'], $pagination['offset']]);
        $messages = $stmt->fetchAll();

        // Mark as read
        $markRead = $pdo->prepare(
            "UPDATE Mesazhi SET is_read = 1 WHERE derguesi_id = ? AND marruesi_id = ? AND is_read = 0"
        );
        $markRead->execute([$withId, $user['id']]);

        // Count total messages in thread
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Mesazhi 
             WHERE (derguesi_id = ? AND marruesi_id = ?)
                OR (derguesi_id = ? AND marruesi_id = ?)"
        );
        $countStmt->execute([$user['id'], $withId, $withId, $user['id']]);
        $total = (int) $countStmt->fetchColumn();

        json_success([
            'messages'   => array_reverse(ts_normalize_rows($messages)), // oldest first for display
            'other_user' => $other,
            'total'      => $total,
            'page'       => $pagination['page'],
            'limit'      => $pagination['limit'],
        ]);
        break;

    // ── SEND MESSAGE ───────────────────────────────
    case 'send':
        require_method('POST');
        $user = require_auth();
        $body = get_json_body();
        $errors = [];

        $receiverId = isset($body['marruesi_id']) ? (int) $body['marruesi_id'] : 0;
        $message    = trim((string) ($body['mesazhi'] ?? ''));

        if ($receiverId <= 0) {
            json_error('Duhet të specifikoni marrësin.', 422);
        }

        if ($receiverId === $user['id']) {
            json_error('Nuk mund t\'i dërgoni mesazh vetes.', 422);
        }

        if ($message === '') {
            json_error('Mesazhi nuk mund të jetë bosh.', 422);
        }

        if (mb_strlen($message) > 2000) {
            json_error('Mesazhi nuk mund të kalojë 2000 karaktere.', 422);
        }

        // Rate limit: 30 messages per 5 minutes per user
        if (!check_rate_limit('send_message_' . $user['id'], 30, 300)) {
            json_error('Po dërgoni shumë mesazhe. Provoni përsëri pas pak.', 429);
        }

        // Verify receiver exists and is active
        $receiverCheck = $pdo->prepare(
            'SELECT id_perdoruesi, emri, email, statusi_llogarise, roli FROM Perdoruesi WHERE id_perdoruesi = ?'
        );
        $receiverCheck->execute([$receiverId]);
        $receiver = $receiverCheck->fetch();

        if (!$receiver) {
            json_error('Përdoruesi marrës nuk u gjet.', 404);
        }

        if ($receiver['statusi_llogarise'] !== 'active') {
            json_error('Nuk mund t\'i dërgoni mesazh këtij përdoruesi.', 403);
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO Mesazhi (derguesi_id, marruesi_id, mesazhi) VALUES (?, ?, ?)"
            );
            $stmt->execute([$user['id'], $receiverId, $message]);
            $msgId = (int) $pdo->lastInsertId();

            // Create notification for receiver — deep-link includes sender ID so clicking
            // the notification opens that specific conversation thread directly.
            $notifMsg = "{$user['emri']} ju dërgoi një mesazh.";
            $notifLink = (is_admin_role($receiver['roli']))
                ? '/TiranaSolidare/views/dashboard.php?with=' . $user['id'] . '#messages'
                : '/TiranaSolidare/views/volunteer_panel.php?tab=messages&with=' . $user['id'];
            $notifStmt = $pdo->prepare(
                'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
            );
            $notifStmt->execute([
                $receiverId,
                $notifMsg,
                'mesazh',
                'user',
                $user['id'],   // sender — so notification UI can link to the right thread
                $notifLink
            ]);

            // Email notification — send_notification_email respects the user's email_notifications preference
            if (filter_var($receiver['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                $panelUrl = app_base_url() . $notifLink;
                send_notification_email(
                    $receiver['email'],
                    $receiver['emri'],
                    "{$user['emri']} ju dërgoi një mesazh — Tirana Solidare",
                    "{$user['emri']} ju dërgoi një mesazh të ri në Tirana Solidare. Klikoni link-un për ta lexuar: {$panelUrl}"
                );
            }

            // Web Push notification — silently no-ops when VAPID keys not configured
            send_push_to_user(
                $receiverId,
                "Mesazh i ri nga {$user['emri']}",
                $message,
                app_base_url() . $notifLink
            );

            json_success([
                'id_mesazhi' => $msgId,
                'message'    => 'Mesazhi u dërgua.',
            ], 201);
        } catch (\Exception $e) {
            error_log('messages send: ' . $e->getMessage());
            json_error('Gabim gjatë dërgimit të mesazhit.', 500);
        }
        break;

    // ── MARK THREAD READ ───────────────────────────
    case 'mark_read':
        require_method('PUT');
        $user = require_auth();
        $withId = (int) ($_GET['with'] ?? 0);

        if ($withId <= 0) {
            json_error('ID-ja e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            "UPDATE Mesazhi SET is_read = 1 WHERE derguesi_id = ? AND marruesi_id = ? AND is_read = 0"
        );
        $stmt->execute([$withId, $user['id']]);

        json_success(['message' => 'U shënuan si të lexuara.']);
        break;

    // ── SEARCH USERS TO MESSAGE ────────────────────
    case 'search_users':
        require_method('GET');
        $user = require_auth();
        $query = trim($_GET['q'] ?? '');

        if (mb_strlen($query) < 2) {
            json_error('Shkruani të paktën 2 karaktere.', 422);
        }

        $stmt = $pdo->prepare(
            "SELECT id_perdoruesi, emri, profile_color
             FROM Perdoruesi
             WHERE id_perdoruesi != ? AND statusi_llogarise = 'active' AND verified = 1
               AND emri LIKE ?
             ORDER BY emri ASC
             LIMIT 10"
        );
        $stmt->execute([$user['id'], "%$query%"]);

        json_success(['users' => $stmt->fetchAll()]);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: conversations, thread, send, mark_read, search_users.', 400);
}
