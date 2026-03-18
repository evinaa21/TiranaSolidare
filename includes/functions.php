<?php
/**
 * includes/functions.php
 * ---------------------------------------------------
 * Shared helper functions for views & actions.
 * ---------------------------------------------------
 */

// ── Secure session cookie settings (applied globally) ──
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    // ini_set('session.cookie_secure', '1'); // Uncomment in production with HTTPS
}

/**
 * Check if user is logged in; redirect to login if not.
 */
function check_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session cookie settings
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/TiranaSolidare/',
            'domain'   => '',
            'secure'   => false, // Set to true in production with HTTPS
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header('Location: /TiranaSolidare/views/login.php');
        exit();
    }
}

/**
 * Check if the current user is an Admin.
 */
function is_admin(): bool
{
    return isset($_SESSION['roli']) && $_SESSION['roli'] === 'Admin';
}

/**
 * Require admin role or redirect with a 403 message.
 */
function require_admin_view(): void
{
    check_login();
    if (!is_admin()) {
        http_response_code(403);
        echo '<h1>403 – Nuk keni leje.</h1>';
        exit();
    }
}

/**
 * Sanitize output to prevent XSS.
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message helpers (set / get / clear).
 */
function set_flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

/**
 * Render a Bootstrap alert if a flash message exists.
 */
function render_flash(string $key, string $type = 'info'): void
{
    $msg = get_flash($key);
    if ($msg) {
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
            . e($msg)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
            . '</div>';
    }
}

// ═══════════════════════════════════════════════════════
//  Time-ago helper (Albanian) — single source of truth
// ═══════════════════════════════════════════════════════

if (!function_exists('koheParapake')) {
    function koheParapake(string $datetime): string
    {
        $now  = new DateTime();
        $then = new DateTime($datetime);
        $diff = $now->diff($then);
        if ($diff->y > 0) return $diff->y . ' vit më parë';
        if ($diff->m > 0) return $diff->m . ' muaj më parë';
        if ($diff->d > 0) return $diff->d . ' ditë më parë';
        if ($diff->h > 0) return $diff->h . ' orë më parë';
        if ($diff->i > 0) return $diff->i . ' min më parë';
        return 'tani';
    }
}

// ═══════════════════════════════════════════════════════
//  CSRF Protection
// ═══════════════════════════════════════════════════════

/**
 * Get or generate the current CSRF token.
 */
function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Return a hidden form field with the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Return a <meta> tag with the CSRF token (for JS AJAX calls).
 */
function csrf_meta(): string
{
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validate CSRF token from POST field or X-CSRF-Token header.
 */
function validate_csrf_token(?string $token = null): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    if ($token === null) {
        $token = $_POST['_csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ═══════════════════════════════════════════════════════
//  Rate Limiting (session-based)
// ═══════════════════════════════════════════════════════

/**
 * Check if the action is within rate limits.
 * Returns true if allowed, false if rate-limited.
 */
function check_rate_limit(string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $rateKey = "rate_limit_{$key}";
    $now = time();

    if (!isset($_SESSION[$rateKey]) || !is_array($_SESSION[$rateKey])) {
        $_SESSION[$rateKey] = [];
    }

    // Remove expired attempts
    $_SESSION[$rateKey] = array_values(array_filter(
        $_SESSION[$rateKey],
        fn($t) => $t > ($now - $windowSeconds)
    ));

    if (count($_SESSION[$rateKey]) >= $maxAttempts) {
        return false;
    }

    $_SESSION[$rateKey][] = $now;
    return true;
}

// ═══════════════════════════════════════════════════════
//  Input Validation Helpers
// ═══════════════════════════════════════════════════════

/**
 * Validate string length (multibyte-safe).
 * Returns an error message string, or null if valid.
 */
function validate_length(string $value, int $min, int $max, string $fieldName): ?string
{
    $len = mb_strlen($value);
    if ($len < $min) {
        return "Fusha '$fieldName' duhet të ketë të paktën $min karaktere.";
    }
    if ($len > $max) {
        return "Fusha '$fieldName' nuk mund të ketë më shumë se $max karaktere.";
    }
    return null;
}

/**
 * Validate password strength against common modern requirements.
 * Returns null if valid, otherwise an Albanian error message.
 */
function validate_password_strength(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Fjalëkalimi duhet të ketë të paktën 8 karaktere.';
    }
    if (strlen($password) > 128) {
        return 'Fjalëkalimi nuk mund të ketë më shumë se 128 karaktere.';
    }
    if (preg_match('/\s/', $password)) {
        return 'Fjalëkalimi nuk duhet të përmbajë hapësira.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Fjalëkalimi duhet të ketë të paktën një shkronjë të vogël.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Fjalëkalimi duhet të ketë të paktën një shkronjë të madhe.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Fjalëkalimi duhet të ketë të paktën një numër.';
    }
    if (!preg_match('/[^a-zA-Z\d]/', $password)) {
        return 'Fjalëkalimi duhet të ketë të paktën një simbol.';
    }
    return null;
}

/**
 * Build base URL from current HTTP request context.
 */
function app_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * Send account verification email via PHPMailer.
 */
function send_verification_email(string $toEmail, string $toName, string $verificationUrl): bool
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('PHPMailer autoload not found at vendor/autoload.php');
        return false;
    }

    require_once $autoload;

    if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log('PHPMailer class not found after loading autoload.');
        return false;
    }

    $mailConfigPath = __DIR__ . '/../config/mail.php';
    if (!file_exists($mailConfigPath)) {
        error_log('Mail config not found at config/mail.php');
        return false;
    }

    $cfg = require $mailConfigPath;
    if (!is_array($cfg)) {
        error_log('Mail config is invalid (expected array).');
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = (string) ($cfg['host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string) ($cfg['username'] ?? '');
        $mail->Password   = (string) ($cfg['password'] ?? '');
        $mail->Port       = (int) ($cfg['port'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        $secure = (string) ($cfg['encryption'] ?? 'tls');
        if ($secure === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $fromEmail = (string) ($cfg['from_email'] ?? 'no-reply@localhost');
        $fromName  = (string) ($cfg['from_name'] ?? 'Tirana Solidare');

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'Konfirmo email-in tënd - Tirana Solidare';

        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style=\"font-family:Arial,sans-serif;line-height:1.5;color:#1f2d2a\">
              <h2 style=\"margin:0 0 12px;color:#003229\">Mirë se erdhët në Tirana Solidare</h2>
              <p>Përshëndetje {$safeName},</p>
              <p>Ju lutem konfirmoni email-in tuaj duke klikuar butonin më poshtë:</p>
              <p style=\"margin:24px 0\">
                <a href=\"{$safeUrl}\" style=\"background:#00715D;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:600\">Konfirmo email-in</a>
              </p>
              <p>Nëse butoni nuk funksionon, kopjo këtë link në shfletues:</p>
              <p><a href=\"{$safeUrl}\">{$safeUrl}</a></p>
              <p style=\"font-size:12px;color:#667\">Ky link skadon pas 24 orësh.</p>
            </div>
        ";

        $mail->AltBody = "Përshëndetje {$toName},\n\nKonfirmo email-in tënd duke hapur këtë link:\n{$verificationUrl}\n\nKy link skadon pas 24 orësh.";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Verification email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Validate that a URL is a safe image URL (https:// only, no JS/data schemes).
 */
function validate_image_url(?string $url): bool
{
    if (empty($url)) return true; // Optional field
    if (!preg_match('#^https?://#i', $url)) {
        return false;
    }
    if (preg_match('#^(javascript|data|vbscript):#i', $url)) {
        return false;
    }
    return true;
}

/**
 * Check that a path is a safe internal redirect (no open redirect).
 */
function is_safe_redirect(string $url): bool
{
    if (strpos($url, '/TiranaSolidare/') !== 0) {
        return false;
    }
    if (strpos($url, '//') !== false) {
        return false;
    }
    if (strpos($url, '..') !== false) {
        return false;
    }
    $parsed = parse_url($url);
    if (isset($parsed['host']) || isset($parsed['scheme'])) {
        return false;
    }
    return true;
}

/**
 * Generate a unique token-based filename for an uploaded image.
 * Returns just the filename (no path).
 */
function generate_upload_filename(string $extension): string
{
    $token = bin2hex(random_bytes(16));
    $timestamp = time();
    return "{$timestamp}_{$token}.{$extension}";
}
