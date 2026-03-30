<?php
/**
 * api/users.php
 * ---------------------------------------------------
 * Admin User Management API
 *
 * GET    ?action=list                     – List all users (Admin)
 * GET    ?action=get&id=<id>              – User detail (Admin)
 * GET    ?action=public_profile&id=<id>   – Public profile (Public)
 * PUT    ?action=update_profile           – Update own profile (Auth)
 * POST   ?action=upload_profile_picture   – Upload profile picture (Auth)
 * DELETE ?action=delete_profile_picture   – Delete profile picture (Auth)
 * PUT    ?action=block&id=<id>            – Block a user (Admin), optional JSON: { arsye_bllokimi }
 * PUT    ?action=unblock&id=<id>          – Unblock a user (Admin)
 * PUT    ?action=change_role&id=<id>      – Change user role (Admin)
 * PUT    ?action=deactivate&id=<id>       – Soft-delete / deactivate (Admin)
 * PUT    ?action=reactivate&id=<id>       – Undo deactivation (Admin)
 * DELETE ?action=delete_account           – Delete own account / GDPR erasure (Auth)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── UPLOAD PROFILE PICTURE (Auth) ────────────
    case 'upload_profile_picture':
        require_method('POST');
        $user = require_auth();

        if (!isset($_FILES['image'])) {
            json_error('Asnjë foto e vlefshme nuk u ngarkua.', 400);
        }

        $result = handle_image_upload(
            $_FILES['image'],
            __DIR__ . '/../uploads/images/profiles',
            '/TiranaSolidare/uploads/images/profiles',
            6 * 1024 * 1024,
            640,
            78
        );

        if (is_string($result)) {
            json_error($result, 400);
        }

        // Delete the old profile picture file from disk if it was an internal upload
        $oldPicStmt = $pdo->prepare('SELECT profile_picture FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $oldPicStmt->execute([$user['id']]);
        $oldPic = $oldPicStmt->fetchColumn();
        if ($oldPic && str_starts_with($oldPic, '/TiranaSolidare/uploads/images/profiles/')) {
            $oldFilename = basename($oldPic);
            // Validate filename to prevent path traversal
            if (preg_match('/^[a-f0-9_]+\.webp$/i', $oldFilename)) {
                $oldPath = __DIR__ . '/../uploads/images/profiles/' . $oldFilename;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }

        $updateStmt = $pdo->prepare('UPDATE Perdoruesi SET profile_picture = ? WHERE id_perdoruesi = ?');
        $updateStmt->execute([$result['url'], $user['id']]);

        $_SESSION['profile_picture'] = $result['url'];
        json_success($result);
        break;

    // ── UPDATE OWN PROFILE (Auth) ───────────────
    case 'update_profile':
        require_method('PUT');
        $user   = require_auth();
        $body   = get_json_body();
        $currentStmt = $pdo->prepare('SELECT emri, bio, profile_picture, profile_public, profile_color, email_notifications FROM Perdoruesi WHERE id_perdoruesi = ?');
        $currentStmt->execute([$user['id']]);
        $current = $currentStmt->fetch();

        if (!$current) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        $emri = isset($body['emri']) ? trim((string) $body['emri']) : (string) $current['emri'];
        $bio = array_key_exists('bio', $body) ? trim((string) $body['bio']) : $current['bio'];
        $profilePicture = array_key_exists('profile_picture', $body) ? trim((string) $body['profile_picture']) : $current['profile_picture'];
        $profilePublic = array_key_exists('profile_public', $body) ? ((int) $body['profile_public'] ? 1 : 0) : (int) $current['profile_public'];
        $profileColor = array_key_exists('profile_color', $body) ? trim((string) $body['profile_color']) : (string) ($current['profile_color'] ?? 'emerald');
        $emailNotifications = array_key_exists('email_notifications', $body) ? ((int) $body['email_notifications'] ? 1 : 0) : (int) ($current['email_notifications'] ?? 1);

        if ($emri === '') {
            json_error('Emri është i detyrueshëm.', 422);
        }

        if ($bio !== null && mb_strlen($bio) > 500) {
            json_error('Bio nuk mund të kalojë 500 karaktere.', 422);
        }
        if ($profilePicture !== null && mb_strlen($profilePicture) > 500) {
            json_error('URL e fotos nuk mund të kalojë 500 karaktere.', 422);
        }
        if ($profilePicture !== null && $profilePicture !== '') {
            $isInternalPath = strpos($profilePicture, '/TiranaSolidare/uploads/images/profiles/') === 0;
            if (!$isInternalPath && !validate_image_url($profilePicture)) {
                json_error('Foto e profilit nuk është e vlefshme.', 422);
            }
        }

        $palette = ts_profile_color_palette();
        if (!isset($palette[$profileColor])) {
            json_error('Ngjyra e profilit nuk është e vlefshme.', 422);
        }

        $stmt = $pdo->prepare(
            'UPDATE Perdoruesi SET emri = ?, bio = ?, profile_picture = ?, profile_public = ?, profile_color = ?, email_notifications = ? WHERE id_perdoruesi = ?'
        );
        $stmt->execute([$emri, $bio, $profilePicture ?: null, $profilePublic, $profileColor, $emailNotifications, $user['id']]);

        // Keep session values in sync for UI consistency
        $_SESSION['emri'] = $emri;
        $_SESSION['profile_color'] = $profileColor;
        $_SESSION['profile_picture'] = $profilePicture ?: '';

        json_success(['message' => 'Profili u përditësua me sukses.']);
        break;

    // ── LIST USERS ─────────────────────────────────
    case 'list':
        require_method('GET');
        $user = require_admin();
        release_session();
        $pagination = get_pagination();

        // Filters
        $roli    = $_GET['roli'] ?? null;
        $statusi = $_GET['statusi'] ?? null;
        $search  = isset($_GET['search']) ? trim($_GET['search']) : '';

$where  = [];
$params = [];

$where[]  = 'id_perdoruesi != ?';
$params[] = $user['id'];

        

        if ($roli) {
            $where[]  = 'roli = ?';
            $params[] = $roli;
        }
        if ($statusi) {
            $where[]  = 'statusi_llogarise = ?';
            $params[] = $statusi;
        }
        if ($search !== '') {
            $where[]  = '(emri LIKE ? OR email LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM Perdoruesi $whereSQL");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT id_perdoruesi, emri, email, roli, statusi_llogarise, krijuar_me, deaktivizuar_me
                FROM Perdoruesi $whereSQL
                ORDER BY krijuar_me DESC
                LIMIT ? OFFSET ?";

        $params[] = $pagination['limit'];
        $params[] = $pagination['offset'];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = ts_normalize_rows($stmt->fetchAll(PDO::FETCH_ASSOC));

        json_success([
            'users'       => $users,
            'total'       => $total,
            'page'        => $pagination['page'],
            'limit'       => $pagination['limit'],
            'total_pages' => (int) ceil($total / $pagination['limit']),
        ]);
        break;

    // ── GET USER DETAIL ────────────────────────────
    case 'get':
        require_method('GET');
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            "SELECT id_perdoruesi, emri, email, roli, statusi_llogarise, krijuar_me, deaktivizuar_me
             FROM Perdoruesi WHERE id_perdoruesi = ?"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        // Include stats
        $appCount = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi WHERE id_perdoruesi = ?');
        $appCount->execute([$id]);

        $helpCount = $pdo->prepare('SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?');
        $helpCount->execute([$id]);

        // Count events created by user
        $eventCount = $pdo->prepare('SELECT COUNT(*) FROM Eventi WHERE id_perdoruesi = ?');
        $eventCount->execute([$id]);

        $user['total_aplikime']    = (int) $appCount->fetchColumn();
        $user['total_kerkesa']     = (int) $helpCount->fetchColumn();
        $user['total_evente']      = (int) $eventCount->fetchColumn();

        // Count help request applications by this user
        try {
            $reqAppCount = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi_Kerkese WHERE id_perdoruesi = ?');
            $reqAppCount->execute([$id]);
            $user['total_aplikime_kerkesa'] = (int) $reqAppCount->fetchColumn();
        } catch (\Exception $e) {
            $user['total_aplikime_kerkesa'] = 0;
        }

        json_success($user);
        break;

    // ── DELETE PROFILE PICTURE ──────────────────────
    case 'delete_profile_picture':
        require_method('DELETE');
        $user = require_auth();

        // Fetch existing picture before NULLing the DB record
        $oldPicStmt = $pdo->prepare('SELECT profile_picture FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $oldPicStmt->execute([$user['id']]);
        $oldPic = $oldPicStmt->fetchColumn();

        $stmt = $pdo->prepare('UPDATE Perdoruesi SET profile_picture = NULL WHERE id_perdoruesi = ?');
        $stmt->execute([$user['id']]);

        // Remove the file from disk if it was an internal upload
        if ($oldPic && str_starts_with($oldPic, '/TiranaSolidare/uploads/images/profiles/')) {
            $oldFilename = basename($oldPic);
            if (preg_match('/^[a-f0-9_]+\.webp$/i', $oldFilename)) {
                $oldPath = __DIR__ . '/../uploads/images/profiles/' . $oldFilename;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }

        $_SESSION['profile_picture'] = '';

        json_success(['message' => 'Fotoja e profilit u fshi me sukses.']);
        break;

    // ── BLOCK USER ─────────────────────────────────
    case 'block':
        require_method('PUT');
        $admin = require_admin();
        $id    = (int) ($_GET['id'] ?? 0);
        $body  = get_json_body();

        $blockReason = trim((string) ($body['arsye_bllokimi'] ?? ''));
        if (mb_strlen($blockReason) > 1000) {
            json_error('Arsyeja e bllokimit nuk mund të kalojë 1000 karaktere.', 422);
        }

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        if ($id === $admin['id']) {
            json_error('Nuk mund të bllokoni veten.', 400);
        }

        // Prevent blocking other admins (H-09)
        $targetCheck = $pdo->prepare('SELECT roli FROM Perdoruesi WHERE id_perdoruesi = ?');
        $targetCheck->execute([$id]);
        $targetUser = $targetCheck->fetch();

        if (!$targetUser) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        if (is_admin_role($targetUser['roli'])) {
            json_error('Nuk mund të bllokoni një administrator tjetër.', 403);
        }

        $stmt = $pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'blocked', arsye_bllokimi = ? WHERE id_perdoruesi = ?");
        $stmt->execute([$blockReason !== '' ? $blockReason : null, $id]);

        $targetInfo = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $targetInfo->execute([$id]);
        $target = $targetInfo->fetch();

        $blockedPageUrl = app_base_url() . '/views/blocked.php';
        if ($blockReason !== '') {
            $blockMessage = "Llogaria juaj është bllokuar nga një administrator.\n\n"
                . "Arsyeja e bllokimit: {$blockReason}\n\n"
                . "Për të kërkuar zhbllokim, dërgoni email te team@tiranasolidare.al me emrin dhe adresën tuaj të llogarisë, "
                . "si dhe një shpjegim të shkurtër.";
        } else {
            $blockMessage = 'Llogaria juaj është bllokuar nga një administrator. '
                . 'Për arsye dhe hapa për zhbllokim, ju lutem shihni: ' . $blockedPageUrl;
        }

        $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)');
        $notifStmt->execute([$id, $blockMessage, 'admin_veprim', 'user', $id, '/TiranaSolidare/views/blocked.php']);

        if ($target && filter_var($target['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            send_notification_email(
                $target['email'],
                $target['emri'] ?? 'Volunteer',
                'Llogaria juaj është bllokuar',
                $blockMessage
            );
        }

        log_admin_action($admin['id'], 'block_user', 'user', $id, [
            'emri' => $target['emri'] ?? '',
            'arsye' => $blockReason,
        ]);

        json_success(['message' => 'Përdoruesi u bllokua.']);
        break;

    // ── UNBLOCK USER ───────────────────────────────
    case 'unblock':
        require_method('PUT');
        $admin = require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        $checkUser = $pdo->prepare('SELECT id_perdoruesi, statusi_llogarise FROM Perdoruesi WHERE id_perdoruesi = ?');
        $checkUser->execute([$id]);
        $target = $checkUser->fetch();
        if (!$target) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }
        if ($target['statusi_llogarise'] !== 'blocked') {
            json_error('Vetëm përdoruesit e bllokuar mund të zhbllokohen.', 400);
        }

        $stmt = $pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'active' WHERE id_perdoruesi = ?");
        $stmt->execute([$id]);

        // Fetch user info for notification
        $unblockInfo = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $unblockInfo->execute([$id]);
        $unblockTarget = $unblockInfo->fetch();

        // In-app notification
        $panelUrl = '/TiranaSolidare/views/volunteer_panel.php';
        $unblockMsg = 'Llogaria juaj është zhbllokuar. Mund të hyëni përsëri në platformë.';
        $notifInsert = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)');
        $notifInsert->execute([$id, $unblockMsg, 'admin_veprim', 'user', $id, $panelUrl]);

        // Email notification
        if ($unblockTarget && filter_var($unblockTarget['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            send_notification_email(
                $unblockTarget['email'],
                $unblockTarget['emri'] ?? 'Volunteer',
                'Llogaria juaj u zhbllokua — Tirana Solidare',
                $unblockMsg
            );
        }

        log_admin_action($admin['id'], 'unblock_user', 'user', $id, []);

        json_success(['message' => 'Përdoruesi u zhbllokua.']);
        break;

    // ── CHANGE ROLE (Super Admin only) ────────────
    case 'change_role':
        require_method('PUT');
        $admin = require_super_admin();
        $id    = (int) ($_GET['id'] ?? 0);
        $body  = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        if ($id === $admin['id']) {
            json_error('Nuk mund të ndryshoni rolin tuaj.', 400);
        }

        $newRole = $body['roli'] ?? '';
        if (!in_array($newRole, ['admin', 'volunteer'], true)) {
            json_error("Roli duhet të jetë 'admin' ose 'volunteer'.", 422);
        }

        // Fix D-05: Use fetch() for role change
        $checkUser = $pdo->prepare('SELECT id_perdoruesi, roli FROM Perdoruesi WHERE id_perdoruesi = ?');
        $checkUser->execute([$id]);
        $targetUser = $checkUser->fetch();
        if (!$targetUser) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        // Cannot change another super_admin's role
        if ($targetUser['roli'] === 'super_admin') {
            json_error('Nuk mund të ndryshoni rolin e një Super Administratori.', 403);
        }

        $stmt = $pdo->prepare('UPDATE Perdoruesi SET roli = ? WHERE id_perdoruesi = ?');
        $stmt->execute([$newRole, $id]);

        // Notify the affected user about their role change
        $roleLabel = $newRole === 'admin' ? 'Administrator' : 'Vullnetar';
        $roleMsg   = "Roli juaj në platformë u ndryshua në '{$roleLabel}' nga një Super Administrator.";
        $panelLink = $newRole === 'admin'
            ? '/TiranaSolidare/views/dashboard.php'
            : '/TiranaSolidare/views/volunteer_panel.php';
        $notifInsert = $pdo->prepare(
            'INSERT INTO Njoftimi (id_perdoruesi, mesazhi, tipi, target_type, target_id, linku) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $notifInsert->execute([$id, $roleMsg, 'admin_veprim', 'user', $id, $panelLink]);

        $roleEmailInfo = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $roleEmailInfo->execute([$id]);
        $roleEmailUser = $roleEmailInfo->fetch();
        if ($roleEmailUser && filter_var($roleEmailUser['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            send_notification_email(
                $roleEmailUser['email'],
                $roleEmailUser['emri'] ?? 'Volunteer',
                'Roli juaj u ndryshua — Tirana Solidare',
                $roleMsg
            );
        }

        log_admin_action($admin['id'], 'change_role', 'user', $id, [
            'roli_ri' => $newRole,
        ]);

        json_success(['message' => "Roli u ndryshua në '$newRole'."]);
        break;

    // ── DEACTIVATE USER (Soft-Delete) ──────────────
    case 'deactivate':
        require_method('PUT');
        $admin = require_admin();
        $id    = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        if ($id === $admin['id']) {
            json_error('Nuk mund të çaktivizoni llogarinë tuaj.', 400);
        }

        // Check user exists and is not already deactivated
        $check = $pdo->prepare('SELECT statusi_llogarise, roli FROM Perdoruesi WHERE id_perdoruesi = ?');
        $check->execute([$id]);
        $target = $check->fetch();

        if (!$target) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }
        if (is_admin_role($target['roli'])) {
            json_error('Nuk mund të çaktivizoni një administrator tjetër.', 403);
        }
        if ($target['statusi_llogarise'] === 'deactivated') {
            json_error('Llogaria është tashmë e çaktivizuar.', 400);
        }

        $stmt = $pdo->prepare(
            "UPDATE Perdoruesi SET statusi_llogarise = 'deactivated', deaktivizuar_me = NOW() WHERE id_perdoruesi = ?"
        );
        $stmt->execute([$id]);

        log_admin_action($admin['id'], 'deactivate_user', 'user', $id, []);

        json_success(['message' => 'Llogaria u çaktivizua (soft-delete). Të dhënat ruhen.']);
        break;

    // ── REACTIVATE USER ────────────────────────────
    case 'reactivate':
        require_method('PUT');
        $admin = require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        // Check user exists and is deactivated
        $check = $pdo->prepare('SELECT statusi_llogarise FROM Perdoruesi WHERE id_perdoruesi = ?');
        $check->execute([$id]);
        $current = $check->fetchColumn();

        if ($current === false) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }
        if ($current !== 'deactivated') {
            json_error('Llogaria nuk është e çaktivizuar.', 400);
        }

        $stmt = $pdo->prepare(
            "UPDATE Perdoruesi SET statusi_llogarise = 'active', deaktivizuar_me = NULL WHERE id_perdoruesi = ?"
        );
        $stmt->execute([$id]);

        log_admin_action($admin['id'], 'reactivate_user', 'user', $id, []);

        json_success(['message' => 'Llogaria u riaktivizua me sukses.']);
        break;

    // ── PUBLIC PROFILE ─────────────────────────────
    case 'public_profile':
        require_method('GET');
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        $stmt = $pdo->prepare(
            "SELECT id_perdoruesi, emri, roli, bio, profile_picture, profile_public, profile_color, krijuar_me
             FROM Perdoruesi
             WHERE id_perdoruesi = ? AND statusi_llogarise = 'active' AND verified = 1"
        );
        $stmt->execute([$id]);
        $profile = $stmt->fetch();

        if (!$profile) {
            json_error('Profili nuk u gjet.', 404);
        }

        // Privacy: if profile is not public, only the owner can see full details
        $isOwner = isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] === $id;
        if (!(int) $profile['profile_public'] && !$isOwner) {
            json_success([
                'id_perdoruesi' => $profile['id_perdoruesi'],
                'emri' => $profile['emri'],
                'roli' => $profile['roli'],
                'profile_public' => 0,
                'profile_color' => $profile['profile_color'] ?? 'emerald',
                'private' => true,
            ]);
            break;
        }

        // Public stats and badges
        $badgeInfo = ts_get_user_profile_badges($pdo, $id);

        // Recent accepted events
        $recentEvents = $pdo->prepare(
            "SELECT e.id_eventi, e.titulli, e.data, e.vendndodhja
             FROM Aplikimi a
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             WHERE a.id_perdoruesi = ? AND a.statusi = 'approved'
             ORDER BY e.data DESC
             LIMIT 10"
        );
        $recentEvents->execute([$id]);

        $recentRequests = $pdo->prepare(
            "SELECT id_kerkese_ndihme, titulli, tipi, statusi, krijuar_me
             FROM Kerkesa_per_Ndihme
             WHERE id_perdoruesi = ?
             ORDER BY krijuar_me DESC
             LIMIT 10"
        );
        $recentRequests->execute([$id]);

        $profile['evente_pranuar'] = $badgeInfo['metrics']['accepted_events'];
        $profile['total_kerkesa'] = $badgeInfo['metrics']['total_requests'];
        $profile['kerkesa_pranuar'] = $badgeInfo['metrics']['accepted_help_applications'];
        $profile['badges'] = $badgeInfo['badges'];
        $profile['evente_te_fundit'] = ts_normalize_rows($recentEvents->fetchAll(PDO::FETCH_ASSOC));
        $profile['kerkesa_te_fundit'] = ts_normalize_rows($recentRequests->fetchAll(PDO::FETCH_ASSOC));

        json_success($profile);
        break;

    // ── DELETE OWN ACCOUNT (GDPR Right to Erasure) ────────
    case 'delete_account':
        require_method('DELETE');
        $user = require_auth();
        $body = get_json_body();

        $password = trim((string) ($body['current_password'] ?? ''));
        if ($password === '') {
            json_error('Konfirmoni fjalëkalimin tuaj.', 422);
        }

        // Fetch current password hash and profile picture
        $stmt = $pdo->prepare('SELECT fjalekalimi, profile_picture FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $account = $stmt->fetch();

        if (!$account) {
            json_error('Llogaria nuk u gjet.', 404);
        }

        if (!password_verify($password, $account['fjalekalimi'])) {
            json_error('Fjalëkalimi i pasaktë.', 401);
        }

        // Anonymize the user record (GDPR right to erasure)
        $anonymisedEmail = 'deleted_' . $user['id'] . '_' . time() . '@deleted.invalid';
        $stmt = $pdo->prepare(
            "UPDATE Perdoruesi
             SET emri = '[Fshirë]',
                 email = ?,
                 fjalekalimi = '',
                 bio = NULL,
                 profile_picture = NULL,
                 statusi_llogarise = 'deactivated',
                 verified = 0,
                 deaktivizuar_me = NOW()
             WHERE id_perdoruesi = ?"
        );
        $stmt->execute([$anonymisedEmail, $user['id']]);

        // Delete profile picture file from disk
        $oldPic = $account['profile_picture'];
        if ($oldPic && str_starts_with($oldPic, '/TiranaSolidare/uploads/images/profiles/')) {
            $oldFilename = basename($oldPic);
            if (preg_match('/^[a-f0-9_]+\.webp$/i', $oldFilename)) {
                $oldPath = __DIR__ . '/../uploads/images/profiles/' . $oldFilename;
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }

        // Destroy the session
        session_unset();
        session_destroy();

        json_success(['message' => 'Llogaria juaj u fshi me sukses. Të dhënat tuaja u anonimizuan.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: upload_profile_picture, update_profile, list, get, block, unblock, change_role, deactivate, reactivate, public_profile, delete_account.', 400);
}
