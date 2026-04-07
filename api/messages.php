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

function messages_require_non_admin(array $user): void
{
    if (is_admin_role($user['roli'] ?? '')) {
        json_error('Mesazhet direkte nuk janë aktive për administratorët.', 403);
    }
}

$action = $_GET['action'] ?? 'conversations';

switch ($action) {

    // ── LIST CONVERSATIONS ─────────────────────────
case 'conversations':
    require_method('GET');
    $user = require_auth();
    messages_require_non_admin($user);
    release_session();

    $stmt = $pdo->prepare(
        "SELECT
            c.other_id,
            p.emri AS other_emri,
            p.profile_color AS other_color,
            p.roli AS other_roli,
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
         WHERE NOT EXISTS (
             SELECT 1 FROM user_blocks
             WHERE blocker_id = c.other_id AND blocked_id = ?
         )
         ORDER BY c.last_time DESC"
    );
    $uid = $user['id'];
    $stmt->execute([$uid, $uid, $uid, $uid, $uid]);
    $conversations = ts_normalize_rows($stmt->fetchAll());

    $conversations = array_values(array_filter(
        $conversations,
        static fn (array $conversation): bool => ts_can_message_user_roles($user['roli'] ?? 'volunteer', $conversation['other_roli'] ?? null)
    ));
    $totalUnread = array_reduce(
        $conversations,
        static fn (int $carry, array $conversation): int => $carry + (int) ($conversation['unread_count'] ?? 0),
        0
    );
    $conversations = array_map(static function (array $conversation): array {
        unset($conversation['other_roli']);
        return $conversation;
    }, $conversations);

    json_success([
        'conversations' => $conversations,
        'total_unread'  => $totalUnread,
    ]);
    break;

    // ── MESSAGE THREAD ─────────────────────────────
    case 'thread':
        require_method('GET');
        $user = require_auth();
        messages_require_non_admin($user);
        $withId = (int) ($_GET['with'] ?? 0);

        if ($withId <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        // Verify other user exists
        $otherCheck = $pdo->prepare('SELECT id_perdoruesi, emri, profile_color, roli FROM Perdoruesi WHERE id_perdoruesi = ?');
        $otherCheck->execute([$withId]);
        $other = $otherCheck->fetch();
        if (!$other) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }
        if (!ts_can_message_user_roles($user['roli'] ?? 'volunteer', $other['roli'] ?? null)) {
            json_error(ts_admin_contact_policy_message(), 403);
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
        messages_require_non_admin($user);
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

        if (ts_normalize_value((string) ($receiver['statusi_llogarise'] ?? '')) !== 'active') {
            json_error('Nuk mund t\'i dërgoni mesazh këtij përdoruesi.', 403);
        }
        if (!ts_can_message_user_roles($user['roli'] ?? 'volunteer', $receiver['roli'] ?? null)) {
            json_error(ts_admin_contact_policy_message(), 403);
        }

        // Kontrollo nëse marrësi ka bllokuar dërguesin
       $blockCheck = $pdo->prepare(
           "SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?"
);
$blockCheck->execute([$receiverId, $user['id']]);
if ($blockCheck->fetchColumn() > 0) {
    json_error('Nuk mund të dërgosh mesazh këtij përdoruesi.', 403);
}

// Kontrollo nëse dërguesi ka bllokuar marrësin
$blockCheck2 = $pdo->prepare(
    "SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?"
);
$blockCheck2->execute([$user['id'], $receiverId]);
if ($blockCheck2->fetchColumn() > 0) {
    json_error('Ke bllokuar këtë përdorues. Zhbllokoje për të dërguar mesazhe.', 403);
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
            // In-app link (absolute web path for <a href="..."> inside the app)
            $notifLink = (is_admin_role($receiver['roli']))
                ? ts_app_path('views/dashboard.php?with=' . $user['id'] . '#messages')
                : ts_app_path('views/volunteer_panel.php?tab=messages&with=' . $user['id']);
            // App-relative path for constructing full URLs (email / push)
            $emailPath = (is_admin_role($receiver['roli']))
                ? '/views/dashboard.php?with=' . $user['id'] . '#messages'
                : '/views/volunteer_panel.php?tab=messages&with=' . $user['id'];
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
                $panelUrl = app_base_url() . $emailPath;
                send_notification_email(
                    $receiver['email'],
                    $receiver['emri'],
                    "{$user['emri']} ju dërgoi një mesazh — Tirana Solidare",
                    "{$user['emri']} ju dërgoi një mesazh të ri në Tirana Solidare. Klikoni link-un për ta lexuar: {$panelUrl}",
                    ['send_now' => true, 'action_url' => $panelUrl, 'action_label' => 'Shiko mesazhin']
                );
            }

            // Web Push notification — silently no-ops when VAPID keys not configured
            send_push_to_user(
                $receiverId,
                "Mesazh i ri nga {$user['emri']}",
                $message,
                app_base_url() . $emailPath
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
        messages_require_non_admin($user);
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
        messages_require_non_admin($user);
        $query = trim($_GET['q'] ?? '');

        if (mb_strlen($query) < 2) {
            json_error('Shkruani të paktën 2 karaktere.', 422);
        }

        $stmt = $pdo->prepare(
            "SELECT id_perdoruesi, emri, profile_color, roli
             FROM Perdoruesi
             WHERE id_perdoruesi != ? AND statusi_llogarise IN ('active', 'Aktiv') AND verified = 1
               AND emri LIKE ?
             ORDER BY emri ASC
             LIMIT 25"
        );
        $stmt->execute([$user['id'], "%$query%"]);

        $users = ts_normalize_rows($stmt->fetchAll());
        $users = array_values(array_filter(
            $users,
            static fn (array $candidate): bool => ts_can_message_user_roles($user['roli'] ?? 'volunteer', $candidate['roli'] ?? null)
        ));
        $users = array_slice(array_map(static function (array $candidate): array {
            unset($candidate['roli']);
            return $candidate;
        }, $users), 0, 10);

        json_success(['users' => $users]);
        break;

        // ── BLOCK USER ─────────────────────────────────
case 'block_user':
    require_method('POST');
    $user = require_auth();
    $body = get_json_body();
    $blocked_id = (int) ($body['blocked_id'] ?? 0);

    if (!$blocked_id || $blocked_id === $user['id']) {
        json_error('ID e pavlefshme.', 400);
    }

    try {
        $pdo->beginTransaction();

        // Insert the block (one-directional)
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)"
        );
        $stmt->execute([$user['id'], $blocked_id]);

        // Auto-withdraw active applications between the two users
        // Case 1: User blocked an applicant who applied to their requests
        $pdo->prepare(
            "UPDATE Aplikimi_Kerkese
             SET statusi = 'withdrawn'
             WHERE id_perdoruesi = ? 
               AND id_kerkese_ndihme IN (
                   SELECT id_kerkese_ndihme FROM Kerkesa_per_Ndihme 
                   WHERE id_perdoruesi = ?
               )
               AND statusi IN ('pending', 'approved', 'waitlisted')"
        )->execute([$blocked_id, $user['id']]);

        // Case 2: User's applications to the blocked user's requests
        $pdo->prepare(
            "UPDATE Aplikimi_Kerkese
             SET statusi = 'withdrawn'
             WHERE id_perdoruesi = ?
               AND id_kerkese_ndihme IN (
                   SELECT id_kerkese_ndihme FROM Kerkesa_per_Ndihme 
                   WHERE id_perdoruesi = ?
               )
               AND statusi IN ('pending', 'approved', 'waitlisted')"
        )->execute([$user['id'], $blocked_id]);

        $pdo->commit();

        json_success(['message' => 'Përdoruesi u bllokua.']);
    } catch (\Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('messages block_user: ' . $e->getMessage());
        json_error('Gabim gjatë bllokimit të përdoruesit.', 500);
    }
    break;

// ── UNBLOCK USER ───────────────────────────────
case 'unblock_user':
    require_method('POST');
    $user = require_auth();
    $body = get_json_body();
    $blocked_id = (int) ($body['blocked_id'] ?? 0);

    if (!$blocked_id) {
        json_error('ID e pavlefshme.', 400);
    }

    $stmt = $pdo->prepare(
        "DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?"
    );
    $stmt->execute([$user['id'], $blocked_id]);
    json_success(['message' => 'Përdoruesi u zhbllokua.']);
    break;

// ── CHECK IF BLOCKED ───────────────────────────
case 'is_blocked':
    require_method('GET');
    $user = require_auth();
    $other_id = (int) ($_GET['user_id'] ?? 0);

        // Merr rolin e tjetrit
    $roleStmt = $pdo->prepare('SELECT roli FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
    $roleStmt->execute([$other_id]);
    $other_role = $roleStmt->fetchColumn() ?: 'volunteer';

    // Nëse tjetri është admin, nuk mund të bllokohet
    if (is_admin_role($other_role)) {
        json_success([
            'i_blocked_them' => false,
            'they_blocked_me' => false,
            'is_blocked' => false,
            'other_role' => $other_role,
            'can_block' => false,
        ]);
        break;
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?"
    );
    $stmt->execute([$user['id'], $other_id]);
    $iBlocked = (bool) $stmt->fetchColumn();

    // Kontrollo edhe nëse tjetri ka bllokuar mua
    $stmt2 = $pdo->prepare(
        "SELECT COUNT(*) FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?"
    );
    $stmt2->execute([$other_id, $user['id']]);
    $theyBlocked = (bool) $stmt2->fetchColumn();

    json_success([
        'i_blocked_them' => $iBlocked,
        'they_blocked_me' => $theyBlocked,
        'is_blocked' => $iBlocked || $theyBlocked,
    ]);
    break;
    
    // ── DELETE CONVERSATION ────────────────────────
case 'delete_conversation':
    require_method('DELETE');
    $user = require_auth();
    messages_require_non_admin($user);
    $withId = (int) ($_GET['with'] ?? 0);

    if (!$withId) {
        json_error('ID e pavlefshme.', 400);
    }

    // Fshi vetëm mesazhet ku ky përdorues është dërgues ose marrës
    // Përdoruesi tjetër e sheh akoma bisedën
    $stmt = $pdo->prepare(
        "DELETE FROM Mesazhi 
         WHERE (derguesi_id = ? AND marruesi_id = ?)
            OR (derguesi_id = ? AND marruesi_id = ?)"
    );
    $stmt->execute([$user['id'], $withId, $withId, $user['id']]);

    json_success(['message' => 'Biseda u fshi.']);
    break;
    
    default:
        json_error('Veprim i panjohur. Përdorni: conversations, thread, send, mark_read, search_users.', 400);
}
