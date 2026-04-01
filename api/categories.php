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

function category_supports_banner(PDO $pdo): bool
{
    static $supportsBanner = null;

    if ($supportsBanner !== null) {
        return $supportsBanner;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM Kategoria LIKE 'banner_path'");
        $supportsBanner = (bool) $stmt->fetch();
    } catch (Throwable $e) {
        $supportsBanner = false;
    }

    return $supportsBanner;
}

switch ($action) {

    // ── LIST CATEGORIES ────────────────────────────
    case 'list':
        require_method('GET');
        release_session();

        $stmt = $pdo->query(
            "SELECT k.*, COUNT(e.id_eventi) AS event_count
             FROM Kategoria k
             LEFT JOIN Eventi e ON e.id_kategoria = k.id_kategoria AND (e.is_archived = 0 OR e.is_archived IS NULL)
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
        $bannerPath = trim((string) ($body['banner_path'] ?? ''));

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        if ($lenErr = validate_length($emri, 2, 100, 'emri')) {
            json_error($lenErr, 422);
        }

        if ($bannerPath !== '' && ($lenErr = validate_length($bannerPath, 1, 500, 'banner_path'))) {
            json_error($lenErr, 422);
        }

        // Check uniqueness
        $check = $pdo->prepare('SELECT id_kategoria FROM Kategoria WHERE emri = ?');
        $check->execute([$emri]);
        if ($check->fetch()) {
            json_error('Kjo kategori ekziston tashmë.', 409);
        }

        $supportsBanner = category_supports_banner($pdo);

        if ($supportsBanner) {
            $stmt = $pdo->prepare('INSERT INTO Kategoria (emri, banner_path) VALUES (?, ?)');
            $stmt->execute([$emri, $bannerPath !== '' ? $bannerPath : null]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO Kategoria (emri) VALUES (?)');
            $stmt->execute([$emri]);
            $bannerPath = '';
        }

        json_success([
            'id_kategoria' => (int) $pdo->lastInsertId(),
            'emri'         => $emri,
            'banner_path'  => $bannerPath !== '' ? $bannerPath : null,
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
        $hasBannerPathField = array_key_exists('banner_path', $body);
        $bannerPath = $hasBannerPathField ? trim((string) $body['banner_path']) : null;

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        if ($lenErr = validate_length($emri, 2, 100, 'emri')) {
            json_error($lenErr, 422);
        }

        if ($hasBannerPathField && $bannerPath !== '' && ($lenErr = validate_length($bannerPath, 1, 500, 'banner_path'))) {
            json_error($lenErr, 422);
        }

        // Fix L-08: Check existence first, don't rely on rowCount()
        $checkExists = $pdo->prepare('SELECT id_kategoria FROM Kategoria WHERE id_kategoria = ?');
        $checkExists->execute([$id]);
        if (!$checkExists->fetch()) {
            json_error('Kategoria nuk u gjet.', 404);
        }

        // Check for duplicate name
        $checkDup = $pdo->prepare('SELECT id_kategoria FROM Kategoria WHERE emri = ? AND id_kategoria != ?');
        $checkDup->execute([$emri, $id]);
        if ($checkDup->fetch()) {
            json_error('Kjo kategori ekziston tashmë.', 409);
        }

        if (category_supports_banner($pdo) && $hasBannerPathField) {
            $stmt = $pdo->prepare('UPDATE Kategoria SET emri = ?, banner_path = ? WHERE id_kategoria = ?');
            $stmt->execute([$emri, $bannerPath !== '' ? $bannerPath : null, $id]);
        } else {
            $stmt = $pdo->prepare('UPDATE Kategoria SET emri = ? WHERE id_kategoria = ?');
            $stmt->execute([$emri, $id]);
        }

        json_success(['message' => 'Kategoria u përditësua.']);
        break;

    // ── DELETE CATEGORY ────────────────────────────
    case 'delete':
        require_method('DELETE');
        require_admin();
        $id = (int) ($_GET['id'] ?? 0);
        $reassignTo = isset($_GET['reassign_to']) && $_GET['reassign_to'] !== '' ? (int) $_GET['reassign_to'] : null;

        if ($id <= 0) {
            json_error('ID-ja e kategorisë është e pavlefshme.', 400);
        }

        if ($reassignTo !== null && $reassignTo <= 0) {
            json_error('ID-ja e kategorisë për zhvendosje është e pavlefshme.', 400);
        }

        if ($reassignTo !== null && $reassignTo === $id) {
            json_error('Nuk mund të zhvendosni eventet në të njëjtën kategori.', 422);
        }

        // Verify existence before any side effects
        $exists = $pdo->prepare('SELECT id_kategoria FROM Kategoria WHERE id_kategoria = ? LIMIT 1');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            json_error('Kategoria nuk u gjet.', 404);
        }

        if ($reassignTo !== null) {
            $targetExists = $pdo->prepare('SELECT id_kategoria FROM Kategoria WHERE id_kategoria = ? LIMIT 1');
            $targetExists->execute([$reassignTo]);
            if (!$targetExists->fetch()) {
                json_error('Kategoria e zgjedhur për zhvendosje nuk u gjet.', 404);
            }
        }

        try {
            $pdo->beginTransaction();

            // Keep both events and help requests consistent when a category is removed.
            if ($reassignTo !== null) {
                $pdo->prepare('UPDATE Eventi SET id_kategoria = ? WHERE id_kategoria = ?')->execute([$reassignTo, $id]);
                $pdo->prepare('UPDATE Kerkesa_per_Ndihme SET id_kategoria = ? WHERE id_kategoria = ?')->execute([$reassignTo, $id]);
            } else {
                $pdo->prepare('UPDATE Eventi SET id_kategoria = NULL WHERE id_kategoria = ?')->execute([$id]);
                $pdo->prepare('UPDATE Kerkesa_per_Ndihme SET id_kategoria = NULL WHERE id_kategoria = ?')->execute([$id]);
            }

            $pdo->prepare('DELETE FROM Kategoria WHERE id_kategoria = ?')->execute([$id]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            json_error('Kategoria nuk mund të fshihej për momentin.', 500);
        }

        json_success(['message' => 'Kategoria u fshi.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: list, create, update, delete.', 400);
}
