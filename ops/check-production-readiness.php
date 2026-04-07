<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config/env.php';

function env_value(string $key): string
{
    $value = getenv($key);
    return $value === false ? '' : trim($value);
}

function add_result(array &$results, string $status, string $label, string $details = ''): void
{
    $results[] = [
        'status' => $status,
        'label' => $label,
        'details' => $details,
    ];
}

function base64url_is_valid(string $value): bool
{
    return $value !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $value) === 1;
}

$results = [];

$appUrl = env_value('APP_URL');
if ($appUrl === '') {
    add_result($results, 'fail', 'APP_URL missing', 'Set APP_URL in .env to the public base URL of this deployment.');
} else {
    $hasLocalHost = preg_match('~https?://(localhost|127\.0\.0\.1)~i', $appUrl) === 1;
    $hasTrailingSlash = str_ends_with($appUrl, '/');
    if ($hasLocalHost) {
        add_result($results, 'warn', 'APP_URL is local', 'APP_URL still points at localhost. Update it before public deployment.');
    } elseif ($hasTrailingSlash) {
        add_result($results, 'warn', 'APP_URL has trailing slash', 'Remove the trailing slash to match .env.example and avoid duplicate separators.');
    } else {
        add_result($results, 'pass', 'APP_URL configured', $appUrl);
    }
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'openssl', 'curl', 'mbstring', 'json'];
foreach ($requiredExtensions as $extension) {
    if (extension_loaded($extension)) {
        add_result($results, 'pass', 'PHP extension loaded', $extension);
    } else {
        add_result($results, 'fail', 'Missing PHP extension', $extension);
    }
}

if (extension_loaded('gd') && function_exists('imagewebp')) {
    add_result($results, 'pass', 'GD image support enabled', 'Image resizing and full upload test coverage are available.');
} else {
    add_result($results, 'warn', 'GD image support missing', 'Uploads still work, but GD-dependent tests are skipped and images are not optimized to WebP.');
}

$mailHost = env_value('SMTP_HOST');
$mailUser = env_value('SMTP_USER');
$mailPass = env_value('SMTP_PASS');
$mailFrom = env_value('SMTP_FROM');

if ($mailHost === '' || $mailUser === '' || $mailPass === '' || $mailFrom === '') {
    add_result($results, 'fail', 'SMTP incomplete', 'SMTP_HOST, SMTP_USER, SMTP_PASS, and SMTP_FROM must all be set.');
} else {
    add_result($results, 'pass', 'SMTP configured', $mailHost . ' as ' . $mailFrom);
}

$vapidPublic = env_value('VAPID_PUBLIC_KEY');
$vapidPrivate = env_value('VAPID_PRIVATE_KEY');
$vapidSubject = env_value('VAPID_SUBJECT');

if (!base64url_is_valid($vapidPublic) || !base64url_is_valid($vapidPrivate)) {
    add_result($results, 'fail', 'VAPID keys missing', 'Run php generate_vapid.php and store both keys in .env.');
} else {
    add_result($results, 'pass', 'VAPID keys configured');
}

if ($vapidSubject === '' || str_contains($vapidSubject, 'yourdomain.com')) {
    add_result($results, 'warn', 'VAPID subject placeholder', 'Set VAPID_SUBJECT to a real mailto: or HTTPS contact URL.');
} else {
    add_result($results, 'pass', 'VAPID subject configured', $vapidSubject);
}

$dbHost = env_value('DB_HOST') !== '' ? env_value('DB_HOST') : 'localhost';
$dbPort = env_value('DB_PORT') !== '' ? env_value('DB_PORT') : '3307';
$dbName = env_value('DB_NAME') !== '' ? env_value('DB_NAME') : 'TiranaSolidare';
$dbUser = env_value('DB_USER') !== '' ? env_value('DB_USER') : 'root';
$dbPass = env_value('DB_PASS');
$dbCharset = env_value('DB_CHARSET') !== '' ? env_value('DB_CHARSET') : 'utf8mb4';

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->query('SELECT 1');
    add_result($results, 'pass', 'Database connection successful', $dbHost . ':' . $dbPort . '/' . $dbName);
} catch (Throwable $e) {
    add_result($results, 'fail', 'Database connection failed', $e->getMessage());
}

$pathsToCheck = [
    'vendor/autoload.php' => true,
    'public/assets/uploads' => false,
    'uploads/images/profiles' => false,
    'cron/process_emails.php' => true,
    'ops/run-email-queue.ps1' => true,
    'ops/run-email-queue.bat' => true,
];

foreach ($pathsToCheck as $relativePath => $mustExist) {
    $absolutePath = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if ($mustExist) {
        if (file_exists($absolutePath)) {
            add_result($results, 'pass', 'Required file present', $relativePath);
        } else {
            add_result($results, 'fail', 'Required file missing', $relativePath);
        }
        continue;
    }

    if (is_dir($absolutePath)) {
        if (is_writable($absolutePath)) {
            add_result($results, 'pass', 'Writable directory ready', $relativePath);
        } else {
            add_result($results, 'fail', 'Directory not writable', $relativePath);
        }
        continue;
    }

    $parentDir = dirname($absolutePath);
    if (is_dir($parentDir) && is_writable($parentDir)) {
        add_result($results, 'warn', 'Directory missing but creatable', $relativePath);
    } else {
        add_result($results, 'fail', 'Directory missing and not creatable', $relativePath);
    }
}

$passCount = 0;
$warnCount = 0;
$failCount = 0;

foreach ($results as $result) {
    switch ($result['status']) {
        case 'pass':
            $passCount++;
            $prefix = '[PASS]';
            break;
        case 'warn':
            $warnCount++;
            $prefix = '[WARN]';
            break;
        default:
            $failCount++;
            $prefix = '[FAIL]';
            break;
    }

    $line = $prefix . ' ' . $result['label'];
    if ($result['details'] !== '') {
        $line .= ' - ' . $result['details'];
    }
    fwrite(STDOUT, $line . PHP_EOL);
}

fwrite(STDOUT, PHP_EOL . "Summary: {$passCount} passed, {$warnCount} warnings, {$failCount} failures." . PHP_EOL);
exit($failCount > 0 ? 1 : 0);