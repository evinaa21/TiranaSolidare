<?php
/**
 * api/users.php
 * ---------------------------------------------------
 * Admin User Management API
 *
 * GET    ?action=list                     – List all users (Admin)
 * GET    ?action=get&id=<id>              – User detail (Admin)
 * PUT    ?action=block&id=<id>            – Block a user (Admin)
 * PUT    ?action=unblock&id=<id>          – Unblock a user (Admin)
 * PUT    ?action=change_role&id=<id>      – Change user role (Admin)
 * DELETE ?action=delete&id=<id>           – Delete a user (Admin)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

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

        $sql = "SELECT id_perdoruesi, emri, email, roli, statusi_llogarise, krijuar_me
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
            "SELECT id_perdoruesi, emri, email, roli, statusi_llogarise, krijuar_me
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

        $user['total_aplikime']    = (int) $appCount->fetchColumn();
        $user['total_kerkesa']     = (int) $helpCount->fetchColumn();

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

    // ── DELETE USER ────────────────────────────────
    case 'delete':
        require_method('DELETE');
        $admin = require_admin();
        $id    = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e përdoruesit është e pavlefshme.', 400);
        }

        if ($id === $admin['id']) {
            json_error('Nuk mund të fshini llogarinë tuaj.', 400);
        }

        // Cascade delete related data
        $pdo->prepare('DELETE FROM Njoftimi WHERE id_perdoruesi = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM Aplikimi WHERE id_perdoruesi = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM Raporti WHERE id_perdoruesi = ?')->execute([$id]);

        $stmt = $pdo->prepare('DELETE FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        json_success(['message' => 'Përdoruesi u fshi.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, get, block, unblock, change_role, delete.', 400);
}
