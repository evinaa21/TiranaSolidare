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

// CSRF is already enforced by helpers.php for all POST/PUT/DELETE requests.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Metoda duhet të jetë POST.', 405);
}

if (!isset($_FILES['image'])) {
    json_error('Asnjë skedar nuk u zgjodh.', 400);
}

$result = handle_image_upload(
    $_FILES['image'],
    __DIR__ . '/../public/assets/uploads',
    ts_app_path('public/assets/uploads'),
    5 * 1024 * 1024,
    700,
    80
);

if (is_string($result)) {
    json_error($result, 400);
}

json_success($result);
