<?php
/**
 * api/settings.php
 * ---------------------------------------------------
 * Admin settings API – logo management, site preferences
 *
 * POST   ?action=upload_logo      – Upload new site logo
 * GET    ?action=get_logo         – Get current logo URL
 * DELETE ?action=delete_logo      – Delete custom logo
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$user = require_dashboard_user();
$isSuperAdmin = ts_normalize_value($user['roli'] ?? '') === 'super_admin';

// CSRF is enforced by helpers.php for POST/PUT/DELETE

$action = $_GET['action'] ?? 'unknown';

if ($action === 'get_site_settings') {
    require_method('GET');
    json_success([
        'settings' => ts_get_site_settings($pdo),
        'can_edit' => $isSuperAdmin,
    ]);
}

if ($action === 'update_site_settings' && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (!$isSuperAdmin) {
        json_error('Vetëm super administratori mund të modifikojë identitetin e platformës.', 403);
    }

    $body = get_json_body();
    $settings = ts_save_site_settings($pdo, $body);

    log_admin_action($user['id'], 'update_site_settings', 'settings', null, $settings);

    json_success([
        'message' => 'Cilësimet e platformës u ruajtën me sukses.',
        'settings' => $settings,
    ]);
}

// ─── UPLOAD LOGO ───────────────────────────────────────
if ($action === 'upload_logo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isSuperAdmin) {
        json_error('Vetëm super administratori mund të ndryshojë logon e platformës.', 403);
    }

    if (!isset($_FILES['logo'])) {
        json_error('Asnjë skedar nuk u zgjodh.', 400);
    }

    $file = $_FILES['logo'];
    $upload_dir = __DIR__ . '/../public/assets/uploads';
    $base_url = rtrim(ts_app_path('public/assets/uploads'), '/');

    // Validate upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error('Gabim në ngarkimin e skedarit.', 400);
    }

    // Check file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        json_error('SkedarFilm është shumë i madh. Maksimumi është 2MB.', 400);
    }

    // Check MIME type
    $allowed_types = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        json_error('Tipi i skedarit nuk lejohet. Përdorni PNG, JPG, GIF, ose WebP.', 400);
    }

    // Ensure upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Derive extension from validated MIME type, not from user-supplied filename
    $mimeToExt = [
        'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg',
        'image/gif' => 'gif', 'image/webp' => 'webp',
    ];
    $logo_filename = 'site-logo-' . time() . '.' . $mimeToExt[$mime_type];
    $logo_path = $upload_dir . '/' . $logo_filename;

    if (!move_uploaded_file($file['tmp_name'], $logo_path)) {
        json_error('Dështoi në ruajtjen e skedarit.', 500);
    }

    // Remove old logos (keep only the latest 2)
    $files = glob($upload_dir . '/site-logo-*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    foreach (array_slice($files, 2) as $old_file) {
        if (file_exists($old_file)) {
            unlink($old_file);
        }
    }

    log_admin_action($user['id'], 'upload_site_logo', 'settings', null, ['filename' => $logo_filename]);

    json_success([
        'message' => 'Logoja u ngarku me sukses.',
        'url' => $base_url . '/' . $logo_filename,
        'filename' => $logo_filename,
    ]);
}

// ─── GET CURRENT LOGO ───────────────────────────────────────
else if ($action === 'get_logo') {
    require_method('GET');
    $upload_dir = __DIR__ . '/../public/assets/uploads';
    $base_url = rtrim(ts_app_path('public/assets/uploads'), '/');
    
    // Get the latest logo
    $files = glob($upload_dir . '/site-logo-*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    if (!empty($files)) {
        $latest = $files[0];
        $filename = basename($latest);
        json_success([
            'has_custom_logo' => true,
            'url' => $base_url . '/' . $filename,
            'uploaded_at' => date('Y-m-d H:i:s', filemtime($latest)),
        ]);
    } else {
        json_success([
            'has_custom_logo' => false,
            'url' => ts_get_site_logo_url(),
            'uploaded_at' => null,
        ]);
    }
}

// ─── DELETE LOGO ───────────────────────────────────────
else if ($action === 'delete_logo' && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!$isSuperAdmin) {
        json_error('Vetëm super administratori mund të fshijë logon e platformës.', 403);
    }

    $upload_dir = __DIR__ . '/../public/assets/uploads';
    
    // Delete all custom logos
    $files = glob($upload_dir . '/site-logo-*.{png,jpg,jpeg,gif,svg,webp}', GLOB_BRACE);
    $deleted_count = 0;
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
            $deleted_count++;
        }
    }

    log_admin_action($user['id'], 'delete_site_logo', 'settings');

    json_success([
        'message' => 'Logoja u fshi me sukses.',
        'deleted_count' => $deleted_count,
        'has_custom_logo' => false,
    ]);
}

else {
    json_error('Veprim i panjohur. Përdorni: get_site_settings, update_site_settings, upload_logo, get_logo, delete_logo.', 400);
}
