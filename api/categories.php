<?php
/**
 * api/categories.php
 * ---------------------------------------------------
 * Category Management API
 *
 * GET    ?action=list            – List all categories
 * POST   ?action=create          – Create a category (Admin)
 * PUT    ?action=update&id=<id>  – Rename a category (Admin)
 * DELETE ?action=delete&id=<id>  – Delete a category (Admin)
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── LIST CATEGORIES ────────────────────────────
    case 'list':
        require_method('GET');

        $stmt = $pdo->query(
            "SELECT k.*, COUNT(e.id_eventi) AS event_count
             FROM Kategoria k
             LEFT JOIN Eventi e ON e.id_kategoria = k.id_kategoria
             GROUP BY k.id_kategoria
             ORDER BY k.emri ASC"
        );
        $categories = $stmt->fetchAll();

        json_success(['categories' => $categories]);
        break;

    // ── CREATE CATEGORY ────────────────────────────
    case 'create':
        require_method('POST');
        require_admin();
        $body   = get_json_body();
        $errors = [];

        $emri = required_field($body, 'emri', $errors);

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        // Check uniqueness
        $check = $pdo->prepare('SELECT id_kategoria FROM Kategoria WHERE emri = ?');
        $check->execute([$emri]);
        if ($check->fetch()) {
            json_error('Kjo kategori ekziston tashmë.', 409);
        }

        $stmt = $pdo->prepare('INSERT INTO Kategoria (emri) VALUES (?)');
        $stmt->execute([$emri]);

        json_success([
            'id_kategoria' => (int) $pdo->lastInsertId(),
            'emri'         => $emri,
        ], 201);
        break;

    // ── UPDATE CATEGORY ────────────────────────────
    case 'update':
        require_method('PUT');
        require_admin();
        $id   = (int) ($_GET['id'] ?? 0);
        $body = get_json_body();

        if ($id <= 0) {
            json_error('ID-ja e kategorisë është e pavlefshme.', 400);
        }

        $errors = [];
        $emri   = required_field($body, 'emri', $errors);

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        $stmt = $pdo->prepare('UPDATE Kategoria SET emri = ? WHERE id_kategoria = ?');
        $stmt->execute([$emri, $id]);

        if ($stmt->rowCount() === 0) {
            json_error('Kategoria nuk u gjet ose emri është i njëjtë.', 404);
        }

        json_success(['message' => 'Kategoria u përditësua.']);
        break;

    // ── DELETE CATEGORY ────────────────────────────
    case 'delete':
        require_method('DELETE');
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0) {
            json_error('ID-ja e kategorisë është e pavlefshme.', 400);
        }

        // Nullify events referencing this category
        $pdo->prepare('UPDATE Eventi SET id_kategoria = NULL WHERE id_kategoria = ?')
            ->execute([$id]);

        $stmt = $pdo->prepare('DELETE FROM Kategoria WHERE id_kategoria = ?');
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            json_error('Kategoria nuk u gjet.', 404);
        }

        json_success(['message' => 'Kategoria u fshi.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, create, update, delete.', 400);
}
