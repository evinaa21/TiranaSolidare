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
 * PUT    ?action=block&id=<id>            – Block a user (Admin), optional JSON: { arsye_bllokimi }
 * PUT    ?action=unblock&id=<id>          – Unblock a user (Admin)
 * PUT    ?action=change_role&id=<id>      – Change user role (Admin)
 * PUT    ?action=deactivate&id=<id>       – Soft-delete / deactivate (Admin)
 * PUT    ?action=reactivate&id=<id>       – Undo deactivation (Admin)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── UPDATE OWN PROFILE (Auth) ───────────────
    case 'update_profile':
        require_method('PUT');
        $user   = require_auth();
        $body   = get_json_body();
        $errors = [];

        $emri = required_field($body, 'emri', $errors);

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        $bio = isset($body['bio']) ? trim((string) $body['bio']) : null;
        $profilePicture = isset($body['profile_picture']) ? trim((string) $body['profile_picture']) : null;
        $profilePublic = isset($body['profile_public']) ? ((int) $body['profile_public'] ? 1 : 0) : 1;

        if ($bio !== null && mb_strlen($bio) > 500) {
            json_error('Bio nuk mund të kalojë 500 karaktere.', 422);
        }
        if ($profilePicture !== null && mb_strlen($profilePicture) > 500) {
            json_error('URL e fotos nuk mund të kalojë 500 karaktere.', 422);
        }
        if ($profilePicture !== null && $profilePicture !== '' && !filter_var($profilePicture, FILTER_VALIDATE_URL)) {
            json_error('URL e fotos nuk është e vlefshme.', 422);
        }

        $stmt = $pdo->prepare(
            'UPDATE Perdoruesi SET emri = ?, bio = ?, profile_picture = ?, profile_public = ? WHERE id_perdoruesi = ?'
        );
        $stmt->execute([$emri, $bio, $profilePicture ?: null, $profilePublic, $user['id']]);

        // Keep session name in sync for UI consistency
        $_SESSION['emri'] = $emri;

        json_success(['message' => 'Profili u përditësua me sukses.']);
        break;

    // ── LIST USERS ─────────────────────────────────
    case 'list':
        require_method('GET');
        require_admin();
        $pagination = get_pagination();

        // Filters
        $roli    = $_GET['roli'] ?? null;
        $statusi = $_GET['statusi'] ?? null;
        $search  = isset($_GET['search']) ? trim($_GET['search']) : '';

        $where  = [];
        $params = [];

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
        $users = $stmt->fetchAll();

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

        json_success($user);
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

        if ($targetUser['roli'] === 'Admin') {
            json_error('Nuk mund të bllokoni një administrator tjetër.', 403);
        }

        $stmt = $pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'Bllokuar' WHERE id_perdoruesi = ?");
        $stmt->execute([$id]);

        $targetInfo = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $targetInfo->execute([$id]);
        $target = $targetInfo->fetch();

        $blockedPageUrl = app_base_url() . '/TiranaSolidare/views/blocked.php';
        if ($blockReason !== '') {
            $blockMessage = "Llogaria juaj është bllokuar nga një administrator.\n\n"
                . "Arsyeja e bllokimit: {$blockReason}\n\n"
                . "Për të kërkuar zhbllokim, dërgoni email te team@tiranasolidare.al me emrin dhe adresën tuaj të llogarisë, "
                . "si dhe një shpjegim të shkurtër.";
        } else {
            $blockMessage = 'Llogaria juaj është bllokuar nga një administrator. '
                . 'Për arsye dhe hapa për zhbllokim, ju lutem shihni: ' . $blockedPageUrl;
        }

        $notifStmt = $pdo->prepare('INSERT INTO Njoftimi (id_perdoruesi, mesazhi) VALUES (?, ?)');
        $notifStmt->execute([$id, $blockMessage]);

        if ($target && filter_var($target['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            send_notification_email(
                $target['email'],
                $target['emri'] ?? 'Vullnetar',
                'Llogaria juaj është bllokuar',
                $blockMessage
            );
        }

        json_success(['message' => 'Përdoruesi u bllokua.']);
        break;

    // ── UNBLOCK USER ───────────────────────────────
    case 'unblock':
        require_method('PUT');
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        // Fix D-05: Use fetch() instead of rowCount() for unblock
        $checkUser = $pdo->prepare('SELECT id_perdoruesi FROM Perdoruesi WHERE id_perdoruesi = ?');
        $checkUser->execute([$id]);
        if (!$checkUser->fetch()) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        $stmt = $pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'Aktiv' WHERE id_perdoruesi = ?");
        $stmt->execute([$id]);

        json_success(['message' => 'Përdoruesi u zhbllokua.']);
        break;

    // ── CHANGE ROLE ────────────────────────────────
    case 'change_role':
        require_method('PUT');
        $admin = require_admin();
        $id    = (int) ($_GET['id'] ?? 0);
        $body  = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        if ($id === $admin['id']) {
            json_error('Nuk mund të ndryshoni rolin tuaj.', 400);
        }

        $newRole = $body['roli'] ?? '';
        if (!in_array($newRole, ['Admin', 'Vullnetar'], true)) {
            json_error("Roli duhet të jetë 'Admin' ose 'Vullnetar'.", 422);
        }

        // Fix D-05: Use fetch() for role change
        $checkUser = $pdo->prepare('SELECT id_perdoruesi FROM Perdoruesi WHERE id_perdoruesi = ?');
        $checkUser->execute([$id]);
        if (!$checkUser->fetch()) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        $stmt = $pdo->prepare('UPDATE Perdoruesi SET roli = ? WHERE id_perdoruesi = ?');
        $stmt->execute([$newRole, $id]);

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
        if ($target['roli'] === 'Admin') {
            json_error('Nuk mund të çaktivizoni një administrator tjetër.', 403);
        }
        if ($target['statusi_llogarise'] === 'Çaktivizuar') {
            json_error('Llogaria është tashmë e çaktivizuar.', 400);
        }

        $stmt = $pdo->prepare(
            "UPDATE Perdoruesi SET statusi_llogarise = 'Çaktivizuar', deaktivizuar_me = NOW() WHERE id_perdoruesi = ?"
        );
        $stmt->execute([$id]);

        json_success(['message' => 'Llogaria u çaktivizua (soft-delete). Të dhënat ruhen.']);
        break;

    // ── REACTIVATE USER ────────────────────────────
    case 'reactivate':
        require_method('PUT');
        require_admin();
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
        if ($current !== 'Çaktivizuar') {
            json_error('Llogaria nuk është e çaktivizuar.', 400);
        }

        $stmt = $pdo->prepare(
            "UPDATE Perdoruesi SET statusi_llogarise = 'Aktiv', deaktivizuar_me = NULL WHERE id_perdoruesi = ?"
        );
        $stmt->execute([$id]);

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
            "SELECT id_perdoruesi, emri, roli, bio, profile_picture, profile_public, krijuar_me
             FROM Perdoruesi
             WHERE id_perdoruesi = ? AND statusi_llogarise = 'Aktiv'"
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
                'private' => true,
            ]);
            break;
        }

        // Public stats
        $appCount = $pdo->prepare(
            "SELECT COUNT(*) FROM Aplikimi WHERE id_perdoruesi = ? AND statusi = 'Pranuar'"
        );
        $appCount->execute([$id]);

        $helpCount = $pdo->prepare('SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?');
        $helpCount->execute([$id]);

        $helpAppCount = $pdo->prepare(
            "SELECT COUNT(*) FROM Aplikimi_Kerkese WHERE id_perdoruesi = ? AND statusi = 'Pranuar'"
        );
        $helpAppCount->execute([$id]);

        // Recent accepted events
        $recentEvents = $pdo->prepare(
            "SELECT e.titulli, e.data, e.vendndodhja
             FROM Aplikimi a
             JOIN Eventi e ON e.id_eventi = a.id_eventi
             WHERE a.id_perdoruesi = ? AND a.statusi = 'Pranuar'
             ORDER BY e.data DESC
             LIMIT 10"
        );
        $recentEvents->execute([$id]);

        $profile['evente_pranuar'] = (int) $appCount->fetchColumn();
        $profile['total_kerkesa'] = (int) $helpCount->fetchColumn();
        $profile['kerkesa_pranuar'] = (int) $helpAppCount->fetchColumn();
        $profile['evente_te_fundit'] = $recentEvents->fetchAll();

        json_success($profile);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: update_profile, list, get, block, unblock, change_role, deactivate, reactivate, public_profile.', 400);
}
