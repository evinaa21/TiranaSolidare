<?php
/**
 * api/users.php
 * ---------------------------------------------------
 * Admin User Management API
 *
 * GET    ?action=list                     – List all users (Admin)
 * GET    ?action=get&id=<id>              – User detail (Admin)
 * PUT    ?action=update_profile           – Update own profile (Auth)
 * PUT    ?action=block&id=<id>            – Block a user (Admin)
 * PUT    ?action=unblock&id=<id>          – Unblock a user (Admin)
 * PUT    ?action=change_role&id=<id>      – Change user role (Admin)
 * PUT    ?action=deactivate&id=<id>       – Soft-delete / deactivate (Admin)
 * PUT    ?action=reactivate&id=<id>       – Undo deactivation (Admin)
 * PUT    ?action=reset_password&id=<id>   – Admin password reset (Admin)
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

        $stmt = $pdo->prepare('UPDATE Perdoruesi SET emri = ? WHERE id_perdoruesi = ?');
        $stmt->execute([$emri, $user['id']]);

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

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        if ($id === $admin['id']) {
            json_error('Nuk mund të bllokoni veten.', 400);
        }

        $stmt = $pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'Bllokuar' WHERE id_perdoruesi = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            json_error('Përdoruesi nuk u gjet.', 404);
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

        $stmt = $pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'Aktiv' WHERE id_perdoruesi = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

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

        $stmt = $pdo->prepare('UPDATE Perdoruesi SET roli = ? WHERE id_perdoruesi = ?');
        $stmt->execute([$newRole, $id]);

        if ($stmt->rowCount() === 0) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

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
        $check = $pdo->prepare('SELECT statusi_llogarise FROM Perdoruesi WHERE id_perdoruesi = ?');
        $check->execute([$id]);
        $current = $check->fetchColumn();

        if ($current === false) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }
        if ($current === 'Çaktivizuar') {
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

    // ── RESET PASSWORD (Admin) ─────────────────────
    case 'reset_password':
        require_method('PUT');
        $admin = require_admin();
        $id    = (int) ($_GET['id'] ?? 0);
        $body  = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        $newPassword = $body['password'] ?? '';
        if (strlen($newPassword) < 6) {
            json_error('Fjalëkalimi duhet të ketë të paktën 6 karaktere.', 422);
        }

        // Verify user exists
        $check = $pdo->prepare('SELECT id_perdoruesi FROM Perdoruesi WHERE id_perdoruesi = ?');
        $check->execute([$id]);
        if (!$check->fetch()) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt   = $pdo->prepare('UPDATE Perdoruesi SET fjalekalimi = ? WHERE id_perdoruesi = ?');
        $stmt->execute([$hashed, $id]);

        json_success(['message' => 'Fjalëkalimi u rivendos me sukses.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: update_profile, list, get, block, unblock, change_role, deactivate, reactivate, reset_password.', 400);
}
