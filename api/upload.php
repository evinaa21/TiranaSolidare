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

try {
    // Check if GD library is available for image processing
    $hasGD = extension_loaded('gd');
    
    if ($hasGD) {
        // Process image with GD: resize to max 700px and convert to WebP
        
        // Load image using GD library with error suppression
        $image = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($file['tmp_name']);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $image = @imagecreatefromwebp($file['tmp_name']);
                } else {
                    $hasGD = false; // Fall back to basic upload
                }
                break;
        }

        if ($hasGD && !$image) {
            json_error('Gabim gjatë leximit të imazhit. Kontrollo formatin e skedarit.', 400);
        }

        if ($hasGD && function_exists('imagewebp')) {
            // Get original dimensions
            $origWidth = imagesx($image);
            $origHeight = imagesy($image);

            if ($origWidth === false || $origHeight === false) {
                imagedestroy($image);
                json_error('Nuk mund të lexohen përmasa të imazhit.', 400);
            }

            // Calculate new dimensions (max width 700px, maintain aspect ratio)
            $maxWidth = 700;
            $newWidth = min($origWidth, $maxWidth);
            $newHeight = (int) round(($newWidth / $origWidth) * $origHeight);

            // Create new image
            $resized = @imagecreatetruecolor($newWidth, $newHeight);
            if (!$resized) {
                imagedestroy($image);
                json_error('Nuk mund të krijohet imazha i ri.', 500);
            }

            // Preserve transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                @imagealphablending($resized, false);
                @imagesavealpha($resized, true);
                $transparent = @imagecolorallocatealpha($resized, 255, 255, 255, 127);
                if ($transparent === false) {
                    imagedestroy($image);
                    imagedestroy($resized);
                    json_error('Nuk mund të përpunohet transparenca.', 500);
                }
                @imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Resize image
            $result = @imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
            if (!$result) {
                imagedestroy($image);
                imagedestroy($resized);
                json_error('Gabim gjatë zmadhimit të imazhit.', 500);
            }

            // Clean up original
            imagedestroy($image);

            // Generate unique token-based filename (always .webp)
            $filename = generate_upload_filename('webp');

            // Ensure upload directory exists
            $uploadDir = __DIR__ . '/../public/assets/uploads';
            if (!is_dir($uploadDir)) {
                if (!@mkdir($uploadDir, 0755, true)) {
                    imagedestroy($resized);
                    json_error('Nuk mund të krijohet dosja e ngarkimit.', 500);
                }
            }

            $destination = "$uploadDir/$filename";

            // Save as WebP with quality 80
            $result = @imagewebp($resized, $destination, 80);
            imagedestroy($resized);

            if (!$result) {
                json_error('Gabim gjatë ruajtjes të imazhit WebP.', 500);
            }

            $publicPath = "/TiranaSolidare/public/assets/uploads/$filename";
            $finalMime = 'image/webp';
        }
    }

    // Fall back to basic upload (store original image without processing)
    if (!$hasGD || !isset($destination)) {
        // Generate unique token-based filename with original extension
        $filename = generate_upload_filename($ext);

        // Ensure upload directory exists
        $uploadDir = __DIR__ . '/../public/assets/uploads';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                json_error('Nuk mund të krijohet dosja e ngarkimit.', 500);
            }
        }

        $destination = "$uploadDir/$filename";

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            json_error('Gabim gjatë ruajtjes të skedarit.', 500);
        }

        $publicPath = "/TiranaSolidare/public/assets/uploads/$filename";
        $finalMime = $mimeType;
    }

    json_success([
        'url'      => $publicPath,
        'filename' => $filename,
        'size'     => filesize($destination),
        'mime'     => $finalMime,
    ]);
} catch (\Exception $e) {
    error_log('upload: ' . $e->getMessage());
    json_error('Gabim gjatë ngarkimit të skedarit.', 500);
}
