<?php
/**
 * migrations/download_images.php
 * ─────────────────────────────────────────────────────
 * Downloads all external image URLs stored in the database
 * to local storage, then updates the DB rows to use local paths.
 *
 * Run from CLI: php migrations/download_images.php
 *
 * Safe to run multiple times (idempotent - skips already-local URLs).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

require_once __DIR__ . '/../config/db.php';

// ── Config ────────────────────────────────────────────────────────
define('AVATAR_DIR',  __DIR__ . '/../public/assets/uploads/profiles');
define('AVATAR_URL',  '/TiranaSolidare/public/assets/uploads/profiles');
define('BANNER_DIR',  __DIR__ . '/../public/assets/uploads/banners');
define('BANNER_URL',  '/TiranaSolidare/public/assets/uploads/banners');
define('CATBANNER_DIR', __DIR__ . '/../public/assets/uploads/categories');
define('CATBANNER_URL', '/TiranaSolidare/public/assets/uploads/categories');

$stats = ['downloaded' => 0, 'skipped' => 0, 'failed' => 0];

// ── Helpers ───────────────────────────────────────────────────────
function ensure_dir(string $dir): void {
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException("Cannot create directory: $dir");
    }
}

/**
 * Returns true if the URL is already a local path (not an external http URL).
 */
function is_local_path(string $url): bool {
    return !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://');
}

/**
 * Downloads a URL to a local file. Returns the saved filename or false.
 */
function download_file(string $url, string $destDir, string $prefix = 'img'): string|false {
    ensure_dir($destDir);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'TiranaSolidare/1.0 (image-downloader)',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $body = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $httpCode >= 400 || strlen($body) < 100) {
            return false;
        }
    } else {
        $body = @file_get_contents($url);
        if ($body === false || strlen($body) < 100) {
            return false;
        }
        $contentType = 'image/jpeg';
    }

    // Determine extension from content type or URL
    $ext = 'jpg';
    if (!empty($contentType)) {
        $mime = strtolower(explode(';', $contentType)[0]);
        $ext = match(trim($mime)) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            default      => pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg',
        };
    } else {
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
    }
    $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) $ext = 'jpg';

    $filename = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = rtrim($destDir, '/') . '/' . $filename;

    if (file_put_contents($dest, $body) === false) {
        return false;
    }
    return $filename;
}

// ── Process profile pictures ──────────────────────────────────────
echo "Processing profile pictures...\n";
$rows = $pdo->query(
    "SELECT id_perdoruesi, profile_picture FROM Perdoruesi
     WHERE profile_picture IS NOT NULL AND profile_picture <> ''"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $url = $row['profile_picture'];
    if (is_local_path($url)) {
        $stats['skipped']++;
        continue;
    }

    echo "  Downloading avatar for user #{$row['id_perdoruesi']}: $url\n";
    $filename = download_file($url, AVATAR_DIR, 'avatar');
    if ($filename) {
        $localPath = AVATAR_URL . '/' . $filename;
        $pdo->prepare("UPDATE Perdoruesi SET profile_picture = ? WHERE id_perdoruesi = ?")
            ->execute([$localPath, $row['id_perdoruesi']]);
        echo "  ✓ Saved as $localPath\n";
        $stats['downloaded']++;
    } else {
        echo "  ✗ Failed — clearing field for user #{$row['id_perdoruesi']}\n";
        $pdo->prepare("UPDATE Perdoruesi SET profile_picture = NULL WHERE id_perdoruesi = ?")
            ->execute([$row['id_perdoruesi']]);
        $stats['failed']++;
    }
}

// ── Process event banners ─────────────────────────────────────────
echo "\nProcessing event banners...\n";
$rows = $pdo->query(
    "SELECT id_eventi, banner FROM Eventi
     WHERE banner IS NOT NULL AND banner <> ''"
)->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $url = $row['banner'];
    if (is_local_path($url)) {
        $stats['skipped']++;
        continue;
    }

    echo "  Downloading banner for event #{$row['id_eventi']}: $url\n";
    $filename = download_file($url, BANNER_DIR, 'event');
    if ($filename) {
        $localPath = BANNER_URL . '/' . $filename;
        $pdo->prepare("UPDATE Eventi SET banner = ? WHERE id_eventi = ?")
            ->execute([$localPath, $row['id_eventi']]);
        echo "  ✓ Saved as $localPath\n";
        $stats['downloaded']++;
    } else {
        echo "  ✗ Failed — clearing field for event #{$row['id_eventi']}\n";
        $pdo->prepare("UPDATE Eventi SET banner = NULL WHERE id_eventi = ?")
            ->execute([$row['id_eventi']]);
        $stats['failed']++;
    }
}

// ── Process category banners ──────────────────────────────────────
echo "\nProcessing category banners...\n";
try {
    $rows = $pdo->query(
        "SELECT id_kategoria, banner_path FROM Kategoria
         WHERE banner_path IS NOT NULL AND banner_path <> ''"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $url = $row['banner_path'];
        if (is_local_path($url)) {
            $stats['skipped']++;
            continue;
        }
        echo "  Downloading banner for category #{$row['id_kategoria']}: $url\n";
        $filename = download_file($url, CATBANNER_DIR, 'cat');
        if ($filename) {
            $localPath = CATBANNER_URL . '/' . $filename;
            $pdo->prepare("UPDATE Kategoria SET banner_path = ? WHERE id_kategoria = ?")
                ->execute([$localPath, $row['id_kategoria']]);
            echo "  ✓ Saved as $localPath\n";
            $stats['downloaded']++;
        } else {
            echo "  ✗ Failed — clearing field for category #{$row['id_kategoria']}\n";
            $pdo->prepare("UPDATE Kategoria SET banner_path = NULL WHERE id_kategoria = ?")
                ->execute([$row['id_kategoria']]);
            $stats['failed']++;
        }
    }
} catch (Throwable $e) {
    echo "  (category banner_path column not present — skipping)\n";
}

echo "\n────────────────────────────────────────\n";
echo "Downloaded : {$stats['downloaded']}\n";
echo "Skipped    : {$stats['skipped']} (already local)\n";
echo "Failed     : {$stats['failed']} (URL cleared from DB)\n";
echo "Done.\n";
