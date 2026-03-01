<?php
/**
 * api/upload.php
 * ---------------------------------------------------
 * Image upload endpoint with token-based filenames.
 *
 * POST  multipart/form-data  field: "image"
 * Returns the public URL of the uploaded file.
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

$user = require_auth();

// CSRF check
if (!validate_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    json_error('Sesioni ka skaduar. Rifreskoni faqen.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metoda duhet të jetë POST.', 405);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'Skedari është shumë i madh (limit serveri).',
        UPLOAD_ERR_FORM_SIZE  => 'Skedari kalon limitin e formës.',
        UPLOAD_ERR_PARTIAL    => 'Skedari u ngarkua vetëm pjesërisht.',
        UPLOAD_ERR_NO_FILE    => 'Asnjë skedar nuk u zgjodh.',
        UPLOAD_ERR_NO_TMP_DIR => 'Mungon dosja e përkohshme.',
        UPLOAD_ERR_CANT_WRITE => 'Gabim gjatë shkrimit në disk.',
    ];
    $errCode = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $msg = $errorMessages[$errCode] ?? 'Gabim i panjohur gjatë ngarkimit.';
    json_error($msg, 400);
}

$file = $_FILES['image'];

// Validate file size (max 5 MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    json_error('Skedari është shumë i madh. Maksimumi është 5MB.', 400);
}

// Validate MIME type using finfo (safe, doesn't rely on client)
$allowedMimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!isset($allowedMimes[$mimeType])) {
    json_error('Formati i skedarit nuk lejohet. Përdorni: JPG, PNG, GIF ose WEBP.', 400);
}

$ext = $allowedMimes[$mimeType];

// Generate unique token-based filename
$filename = generate_upload_filename($ext);

// Ensure upload directory exists
$uploadDir = __DIR__ . '/../uploads/images';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destination = "$uploadDir/$filename";

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    json_error('Gabim gjatë ruajtjes së skedarit.', 500);
}

$publicPath = "/TiranaSolidare/uploads/images/$filename";

json_success([
    'url'      => $publicPath,
    'filename' => $filename,
    'size'     => $file['size'],
    'mime'     => $mimeType,
]);
