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
        $safeSite = htmlspecialchars(app_base_url(), ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style=\"font-family:Inter, Arial, sans-serif; margin:0; padding:0; background:#f6fbf9; color:#1f2d2a;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">
                <tr>
                  <td align=\"center\" style=\"padding:24px 12px;\">
                    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 8px 22px rgba(0,0,0,0.08);\">
                      <tr>
                        <td style=\"background:linear-gradient(135deg, #00715D 0%, #005a48 100%); padding:20px 24px; text-align:center; color:#fff;\">
                          <div style=\"font-size:24px; font-weight:800; letter-spacing:0.2px;\">Tirana <strong>Solidare</strong></div>
                          <div style=\"font-size:14px; opacity:0.9; margin-top:2px;\">Bëhu pjesë e ndihmës së komunitetit</div>
                        </td>
                      </tr>
                      <tr>
                        <td style=\"padding:24px 30px 20px;\">
                          <p style=\"margin:0 0 8px; color:#2b3a3a; font-size:15px;\">Përshëndetje {$safeName},</p>
                          <h2 style=\"margin:0 0 16px; color:#0b3f34; font-size:22px;\">Konfirmo email-in tënd</h2>
                          <p style=\"margin:0 0 14px; color:#4a4a4a; font-size:15px; line-height:1.6;\">Faleminderit që u regjistruat në platformën tonë. Klikoni butonin më poshtë për të verifikuar adresën tuaj dhe për të aktivizuar llogarinë.</p>
                          <p style=\"margin:20px 0; text-align:center;\">
                            <a href=\"{$safeUrl}\" style=\"display:inline-block; padding:13px 20px; background:#00715D; color:#ffffff; text-decoration:none; border-radius:8px; font-weight:700; font-size:15px;\">Konfirmo email-in</a>
                          </p>
                          <p style=\"margin:0 0 20px; color:#4a4a4a; font-size:14px;\">Nëse butoni nuk punon, kopjo dhe ngjit linkun në shfletues:</p>
                          <p style=\"word-break:break-all; margin:0; font-size:13px; color:#0b3f34;\"><a href=\"{$safeUrl}\" style=\"color:#00715D; text-decoration:none;\">{$safeUrl}</a></p>
                        </td>
                      </tr>
                      <tr>
                        <td style=\"padding:0 30px 22px; border-top:1px solid #e9f3ef;\">
                          <p style=\"margin:0; color:#6b6b6b; font-size:12px; line-height:1.4;\">Ky link skadon pas 24 orësh. Nëse nuk keni kërkuar këtë email, injoroni këtë mesazh.</p>
                        </td>
                      </tr>
                      <tr>
                        <td style=\"padding:16px 30px 20px; background:#f1f8f4; color:#3c3c3c; font-size:12px; text-align:center;\">
                          <strong>Tirana Solidare</strong> • <a href=\"{$safeSite}\" style=\"color:#00715D; text-decoration:none;\">tiranasolidare.al</a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
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
 * Send password reset email via PHPMailer using the site design.
 */
function send_password_reset_email(string $toEmail, string $toName, string $resetUrl): bool
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
        $mail->Subject = 'Rivendos fjalëkalimin - Tirana Solidare';

        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $safeSite = htmlspecialchars(app_base_url(), ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style=\"font-family:Inter, Arial, sans-serif; margin:0; padding:0; background:#f6fbf9; color:#1f2d2a;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">
                <tr>
                  <td align=\"center\" style=\"padding:24px 12px;\">
                    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 8px 22px rgba(0,0,0,0.08);\">
                      <tr>
                        <td style=\"background:linear-gradient(135deg, #00715D 0%, #005a48 100%); padding:20px 24px; text-align:center; color:#fff;\">
                          <div style=\"font-size:24px; font-weight:800; letter-spacing:0.2px;\">Tirana <strong>Solidare</strong></div>
                          <div style=\"font-size:14px; opacity:0.9; margin-top:2px;\">Rivendos fjalëkalimin tënd</div>
                        </td>
                      </tr>
                      <tr>
                        <td style=\"padding:24px 30px 20px;\">
                          <p style=\"margin:0 0 8px; color:#2b3a3a; font-size:15px;\">Përshëndetje {$safeName},</p>
                          <h2 style=\"margin:0 0 16px; color:#0b3f34; font-size:22px;\">Kërkesë për rivendosjen e fjalëkalimit</h2>
                          <p style=\"margin:0 0 14px; color:#4a4a4a; font-size:15px; line-height:1.6;\">Ne morëm kërkesë për të rivendosur fjalëkalimin tuaj. Klikoni butonin më poshtë për të zgjedhur fjalëkalim të ri. Ky link skadon pas 1 ore.</p>
                          <p style=\"margin:20px 0; text-align:center;\">
                            <a href=\"{$safeUrl}\" style=\"display:inline-block; padding:13px 20px; background:#00715D; color:#ffffff; text-decoration:none; border-radius:8px; font-weight:700; font-size:15px;\">Rivendos fjalëkalimin</a>
                          </p>
                          <p style=\"margin:0 0 20px; color:#4a4a4a; font-size:14px;\">Nëse nuk keni kërkuar këtë, mos e merrni parasysh këtë email.</p>
                          <p style=\"word-break:break-all; margin:0; font-size:13px; color:#0b3f34;\"><a href=\"{$safeUrl}\" style=\"color:#00715D; text-decoration:none;\">{$safeUrl}</a></p>
                        </td>
                      </tr>
                      <tr>
                        <td style=\"padding:0 30px 22px; border-top:1px solid #e9f3ef;\">
                          <p style=\"margin:0; color:#6b6b6b; font-size:12px; line-height:1.4;\">Nëse keni bërë më shumë se një kërkesë, përdorni linkun e fundit të dërguar. Ky link skadon pas 1 ore.</p>
                        </td>
                      </tr>
                      <tr>
                        <td style=\"padding:16px 30px 20px; background:#f1f8f4; color:#3c3c3c; font-size:12px; text-align:center;\">
                          <strong>Tirana Solidare</strong> • <a href=\"{$safeSite}\" style=\"color:#00715D; text-decoration:none;\">tiranasolidare.al</a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </div>
        ";

        $mail->AltBody = "Përshëndetje {$toName},\n\nPër të rivendosur fjalëkalimin, hapni: {$resetUrl}\n\nKy link skadon pas 1 ore.";

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Password reset email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send a generic user notification email in the same site style.
 */
function send_notification_email(string $toEmail, string $toName, string $subject, string $message): bool
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
        $host = trim((string) ($cfg['host'] ?? ''));
        $username = trim((string) ($cfg['username'] ?? ''));
        $password = trim((string) ($cfg['password'] ?? ''));
        $fromEmail = (string) ($cfg['from_email'] ?? 'no-reply@localhost');
        $fromName  = (string) ($cfg['from_name'] ?? 'Tirana Solidare');

        if ($host === '' || $host === 'smtp.example.com' || $username === '' || $password === '') {
            // Fallback to PHP mail if SMTP is not configured.
            $headers = "From: {$fromName} <{$fromEmail}>\r\n" .
                       "MIME-Version: 1.0\r\n" .
                       "Content-Type: text/html; charset=UTF-8\r\n";
            $mailBody = "<html><body><h2>" . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . "</h2><p>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</p></body></html>";
            if (mail($toEmail, $subject, $mailBody, $headers)) {
                return true;
            }
            error_log('Notification email failed: PHP mail fallback failed.');
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $username;
        $mail->Password   = $password;
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
        $mail->Subject = $subject;
        $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $safeSite = htmlspecialchars(app_base_url(), ENT_QUOTES, 'UTF-8');
        $mail->Body = "
            <div style=\"font-family:Inter, Arial, sans-serif; margin:0; padding:0; background:#f6fbf9; color:#1f2d2a;\">
              <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">
                <tr>
                  <td align=\"center\" style=\"padding:24px 12px;\">
                    <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 8px 22px rgba(0,0,0,0.08);\">
                      <tr>
                        <td style=\"background:linear-gradient(135deg, #00715D 0%, #005a48 100%); padding:20px 24px; text-align:center; color:#fff;\">
                          <div style=\"font-size:24px; font-weight:800; letter-spacing:0.2px;\">Tirana <strong>Solidare</strong></div>
                        </td>
                      </tr>
                      <tr>
                        <td style=\"padding:24px 30px 20px;\">
                          <p style=\"margin:0 0 8px; color:#2b3a3a; font-size:15px;\">Përshëndetje {$safeName},</p>
                          <h2 style=\"margin:0 0 16px; color:#0b3f34; font-size:22px;\">{$subject}</h2>
                          <p style=\"margin:0 0 20px; color:#4a4a4a; font-size:15px; line-height:1.6;\">{$safeMessage}</p>
                          <p style=\"margin:0; color:#6b6b6b; font-size:12px;\">Ky mesazh është nga Tirana Solidare.</p>
                        </td>
                      </tr>
                      <tr>
                        <td style=\"padding:16px 30px 20px; background:#f1f8f4; color:#3c3c3c; font-size:12px; text-align:center;\">
                          <strong>Tirana Solidare</strong> • <a href=\"{$safeSite}\" style=\"color:#00715D; text-decoration:none;\">tiranasolidare.al</a>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>
            </div>
        ";
        $mail->AltBody = "Përshëndetje {$toName},\n\n{$message}\n\nTirana Solidare";
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('Notification email failed: ' . $e->getMessage());
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
