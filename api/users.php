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
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {

    // ── UPLOAD PROFILE PICTURE (Auth) ────────────
    case 'upload_profile_picture':
        require_method('POST');
        $user = require_auth();

        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            json_error('Asnjë foto e vlefshme nuk u ngarkua.', 400);
        }

        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            json_error('Serveri nuk mbështet përpunimin e fotove (GD/WebP).', 500);
        }

        $file = $_FILES['image'];
        $maxSize = 6 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxSize) {
            json_error('Foto duhet të jetë më e vogël se 6MB.', 400);
        }

        $allowedMimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowedMimes, true)) {
            json_error('Formati nuk lejohet. Përdorni JPG, PNG, GIF ose WEBP.', 400);
        }

        $sourceImage = null;
        switch ($mime) {
            case 'image/jpeg':
                $sourceImage = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $sourceImage = @imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $sourceImage = @imagecreatefromgif($file['tmp_name']);
                break;
            case 'image/webp':
                $sourceImage = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : null;
                break;
        }

        if (!$sourceImage) {
            json_error('Nuk u lexua foto e ngarkuar.', 400);
        }

        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        if ($origWidth <= 0 || $origHeight <= 0) {
            imagedestroy($sourceImage);
            json_error('Përmasat e fotos janë të pavlefshme.', 400);
        }

        $maxDimension = 640;
        $scale = min(1, $maxDimension / max($origWidth, $origHeight));
        $newWidth = max(1, (int) round($origWidth * $scale));
        $newHeight = max(1, (int) round($origHeight * $scale));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if (!$resized) {
            imagedestroy($sourceImage);
            json_error('Nuk u krijua varianti i optimizuar i fotos.', 500);
        }

        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

        if (!imagecopyresampled($resized, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight)) {
            imagedestroy($sourceImage);
            imagedestroy($resized);
            json_error('Gabim gjatë përpunimit të fotos.', 500);
        }

        imagedestroy($sourceImage);

        $filename = generate_upload_filename('webp');
        $uploadDir = __DIR__ . '/../uploads/images/profiles';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0755, true)) {
            imagedestroy($resized);
            json_error('Nuk u krijua dosja e fotove të profilit.', 500);
        }

        $destination = $uploadDir . '/' . $filename;
        if (!@imagewebp($resized, $destination, 78)) {
            imagedestroy($resized);
            json_error('Gabim gjatë ruajtjes së fotos WebP.', 500);
        }
        imagedestroy($resized);

        $photoUrl = '/TiranaSolidare/uploads/images/profiles/' . $filename;
        $updateStmt = $pdo->prepare('UPDATE Perdoruesi SET profile_picture = ? WHERE id_perdoruesi = ?');
        $updateStmt->execute([$photoUrl, $user['id']]);

        json_success([
            'url' => $photoUrl,
            'filename' => $filename,
            'size' => filesize($destination),
            'mime' => 'image/webp',
            'width' => $newWidth,
            'height' => $newHeight,
        ]);
        break;

    // ── UPDATE OWN PROFILE (Auth) ───────────────
    case 'update_profile':
        require_method('PUT');
        $user   = require_auth();
        $body   = get_json_body();
        $currentStmt = $pdo->prepare('SELECT emri, bio, profile_picture, profile_public, profile_color FROM Perdoruesi WHERE id_perdoruesi = ?');
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

        if ($emri === '') {
            json_error('Emri është i detyrueshëm.', 422);
        }

        if ($bio !== null && mb_strlen($bio) > 500) {
            json_error('Bio nuk mund të kalojë 500 karaktere.', 422);
        }
        if ($profilePicture !== null && mb_strlen($profilePicture) > 500) {
            json_error('URL e fotos nuk mund të kalojë 500 karaktere.', 422);
        }
        if ($profilePicture !== null && $profilePicture !== '' && !filter_var($profilePicture, FILTER_VALIDATE_URL)) {
            if (strpos($profilePicture, '/TiranaSolidare/uploads/images/profiles/') !== 0) {
                json_error('Foto e profilit nuk është e vlefshme.', 422);
            }
        }

        $palette = ts_profile_color_palette();
        if (!isset($palette[$profileColor])) {
            json_error('Ngjyra e profilit nuk është e vlefshme.', 422);
        }

        $stmt = $pdo->prepare(
            'UPDATE Perdoruesi SET emri = ?, bio = ?, profile_picture = ?, profile_public = ?, profile_color = ? WHERE id_perdoruesi = ?'
        );
        $stmt->execute([$emri, $bio, $profilePicture ?: null, $profilePublic, $profileColor, $user['id']]);

        // Keep session values in sync for UI consistency
        $_SESSION['emri'] = $emri;
        $_SESSION['profile_color'] = $profileColor;
        $sessionProfilePicture = $profilePicture ?: '';
        $_SESSION['profile_picture'] = $sessionProfilePicture;
        $_SESSION['avatar'] = $sessionProfilePicture;
        $_SESSION['photo'] = $sessionProfilePicture;
        $_SESSION['foto'] = $sessionProfilePicture;
        $_SESSION['profile_image'] = $sessionProfilePicture;

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

        $stmt = $pdo->prepare('UPDATE Perdoruesi SET profile_picture = NULL WHERE id_perdoruesi = ?');
        $stmt->execute([$user['id']]);

        // Keep avatar-related session values in sync with DB state.
        $_SESSION['profile_picture'] = '';
        $_SESSION['avatar'] = '';
        $_SESSION['photo'] = '';
        $_SESSION['foto'] = '';
        $_SESSION['profile_image'] = '';

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
            "SELECT id_perdoruesi, emri, roli, bio, profile_picture, profile_public, profile_color, krijuar_me
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
             WHERE a.id_perdoruesi = ? AND a.statusi = 'Pranuar'
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
        $profile['evente_te_fundit'] = $recentEvents->fetchAll();
        $profile['kerkesa_te_fundit'] = $recentRequests->fetchAll();

        json_success($profile);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: upload_profile_picture, update_profile, list, get, block, unblock, change_role, deactivate, reactivate, public_profile.', 400);
}
