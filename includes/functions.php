<?php
/**
 * includes/functions.php
 * ---------------------------------------------------
 * Shared helper functions for views & actions.
 * ---------------------------------------------------
 */

// ── Secure session cookie settings (applied globally) ──
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == '443');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
    // Ensure all sessions use the same cookie path to prevent stale cookie conflicts
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/TiranaSolidare/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Enforce session inactivity timeout (1 hour).
 * Call after session_start() on every protected page/endpoint.
 */
function enforce_session_timeout(int $maxIdleSeconds = 3600): void
{
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxIdleSeconds)) {
        session_unset();
        session_destroy();
        // Restart a clean session for the redirect
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['flash']['error'] = 'Sesioni juaj ka skaduar nga mosaktiviteti. Ju lutem kyçuni përsëri.';
        header('Location: /TiranaSolidare/views/login.php?error=session_expired');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Check if user is logged in; redirect to login if not.
 */
function check_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == '443');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/TiranaSolidare/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    enforce_session_timeout();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /TiranaSolidare/views/login.php');
        exit();
    }
}

// ═══════════════════════════════════════════════════════
//  DB Value Normalization (Albanian → English lowercase)
// ═══════════════════════════════════════════════════════

const TS_DB_NORMALIZE = [
    'Admin'       => 'admin',
    'Vullnetar'   => 'volunteer',
    'Kërkesë'     => 'request',
    'Ofertë'      => 'offer',
    'Open'        => 'open',
    'Closed'      => 'completed',
    'Filled'      => 'filled',
    'Completed'   => 'completed',
    'Cancelled'   => 'cancelled',
    'Hapur'       => 'open',
    'Mbyllur'     => 'completed',
    'Mbushur'     => 'filled',
    'Përfunduar'  => 'completed',
    'Anuluar'     => 'cancelled',
    'Në pritje'   => 'pending',
    'Pranuar'     => 'approved',
    'Refuzuar'    => 'rejected',
    'Në listë pritjeje' => 'waitlisted',
    'Tërhequr'    => 'withdrawn',
    'Aktiv'       => 'active',
    'Bllokuar'    => 'blocked',
    'Çaktivizuar' => 'deactivated',
];

function ts_normalize_value(string $val): string
{
    return TS_DB_NORMALIZE[$val] ?? strtolower($val);
}

function ts_normalize_row(array $row): array
{
    foreach (['roli', 'tipi', 'statusi', 'statusi_llogarise'] as $field) {
        if (isset($row[$field])) {
            $row[$field] = ts_normalize_value($row[$field]);
        }
    }
    return $row;
}

const TS_HELP_REQUEST_MATCHING_MODES = ['single', 'limited', 'open'];
const TS_HELP_REQUEST_ACTIVE_STATUSES = ['open', 'filled'];
const TS_HELP_REQUEST_TERMINAL_STATUSES = ['completed', 'cancelled'];

function ts_help_request_application_unlocks_location(?string $status): bool
{
    return in_array(ts_normalize_value((string) $status), ['pending', 'approved', 'waitlisted', 'completed'], true);
}

function ts_can_view_help_request_location(array $request, ?int $viewerId = null, ?string $viewerRole = null, array $locationUnlockedRequestIds = []): bool
{
    if (ts_is_admin_role_value($viewerRole)) {
        return true;
    }

    $requestOwnerId = (int) ($request['id_perdoruesi'] ?? 0);
    if ($viewerId !== null && $viewerId > 0 && $requestOwnerId === $viewerId) {
        return true;
    }

    $requestId = (int) ($request['id_kerkese_ndihme'] ?? 0);
    return $requestId > 0 && in_array($requestId, $locationUnlockedRequestIds, true);
}

function ts_strip_help_request_location(array $request): array
{
    $request['vendndodhja'] = null;
    $request['latitude'] = null;
    $request['longitude'] = null;
    return $request;
}

function ts_help_request_normalize_status(?string $status): string
{
    $normalized = ts_normalize_value((string) $status);
    return $normalized === 'closed' ? 'completed' : $normalized;
}

function ts_help_request_matching_mode(?string $matchingMode, ?int $capacityTotal = null): string
{
    $mode = strtolower(trim((string) $matchingMode));
    if (in_array($mode, TS_HELP_REQUEST_MATCHING_MODES, true)) {
        return $mode;
    }

    if ($capacityTotal !== null) {
        return $capacityTotal <= 1 ? 'single' : 'limited';
    }

    return 'open';
}

function ts_help_request_capacity_total(?string $matchingMode, ?int $capacityTotal): ?int
{
    $mode = ts_help_request_matching_mode($matchingMode, $capacityTotal);

    if ($mode === 'open') {
        return null;
    }

    if ($mode === 'single') {
        return 1;
    }

    return ($capacityTotal !== null && $capacityTotal > 1) ? $capacityTotal : null;
}

function ts_help_request_application_counts_by_request_ids(PDO $pdo, array $requestIds): array
{
    $requestIds = array_values(array_unique(array_filter(array_map('intval', $requestIds), static fn (int $id): bool => $id > 0)));
    if ($requestIds === []) {
        return [];
    }

    $countsByRequest = [];
    $placeholders = implode(',', array_fill(0, count($requestIds), '?'));

    try {
        $stmt = $pdo->prepare(
            "SELECT id_kerkese_ndihme, LOWER(statusi) AS statusi, COUNT(*) AS total
             FROM Aplikimi_Kerkese
             WHERE id_kerkese_ndihme IN ($placeholders)
             GROUP BY id_kerkese_ndihme, LOWER(statusi)"
        );
        $stmt->execute($requestIds);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $requestId = (int) ($row['id_kerkese_ndihme'] ?? 0);
            $status = ts_help_request_normalize_status((string) ($row['statusi'] ?? 'pending'));
            if ($requestId <= 0) {
                continue;
            }
            if (!isset($countsByRequest[$requestId])) {
                $countsByRequest[$requestId] = [];
            }
            $countsByRequest[$requestId][$status] = (int) ($row['total'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('help_request_application_counts: ' . $e->getMessage());
    }

    return $countsByRequest;
}

function ts_help_request_application_counts(PDO $pdo, int $requestId): array
{
    $counts = ts_help_request_application_counts_by_request_ids($pdo, [$requestId]);
    return $counts[$requestId] ?? [];
}

function ts_help_request_matching_details(array $request, array $counts = []): array
{
    $normalizedCounts = [
        'pending' => 0,
        'approved' => 0,
        'waitlisted' => 0,
        'rejected' => 0,
        'withdrawn' => 0,
        'completed' => 0,
    ];

    foreach ($counts as $status => $total) {
        $normalizedStatus = ts_help_request_normalize_status((string) $status);
        if (array_key_exists($normalizedStatus, $normalizedCounts)) {
            $normalizedCounts[$normalizedStatus] = (int) $total;
        }
    }

    $rawCapacity = null;
    if (array_key_exists('capacity_total', $request) && $request['capacity_total'] !== null && $request['capacity_total'] !== '') {
        $rawCapacity = (int) $request['capacity_total'];
    }

    $matchingMode = ts_help_request_matching_mode($request['matching_mode'] ?? null, $rawCapacity);
    $capacityTotal = ts_help_request_capacity_total($matchingMode, $rawCapacity);
    $currentStatus = ts_help_request_normalize_status((string) ($request['statusi'] ?? 'open'));
    $approvedCount = $normalizedCounts['approved'];
    $completedCount = $normalizedCounts['completed'];
    $matchedTotal = $approvedCount + $completedCount;
    $isFull = $matchingMode !== 'open' && $capacityTotal !== null && $approvedCount >= $capacityTotal;

    $resolvedStatus = $currentStatus;
    if (!in_array($currentStatus, TS_HELP_REQUEST_TERMINAL_STATUSES, true)) {
        $resolvedStatus = $isFull ? 'filled' : 'open';
    }

    $slotsRemaining = ($matchingMode === 'open' || $capacityTotal === null)
        ? null
        : max(0, $capacityTotal - $approvedCount);

    $progressCount = $resolvedStatus === 'completed' ? $matchedTotal : $approvedCount;

    return [
        'matching_mode' => $matchingMode,
        'capacity_total' => $capacityTotal,
        'has_capacity_limit' => $capacityTotal !== null,
        'resolved_status' => $resolvedStatus,
        'is_full' => $isFull,
        'can_receive_applications' => $resolvedStatus === 'open',
        'is_active' => in_array($resolvedStatus, TS_HELP_REQUEST_ACTIVE_STATUSES, true),
        'slots_remaining' => $slotsRemaining,
        'counts' => $normalizedCounts,
        'total_applications' => array_sum($normalizedCounts),
        'active_matches' => $approvedCount,
        'completed_matches' => $completedCount,
        'matched_total' => $matchedTotal,
        'progress_count' => $progressCount,
    ];
}

function ts_help_request_sync_status(PDO $pdo, int $requestId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id_kerkese_ndihme, statusi, tipi, matching_mode, capacity_total
         FROM Kerkesa_per_Ndihme
         WHERE id_kerkese_ndihme = ?
         LIMIT 1'
    );
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        return null;
    }

    $request = ts_normalize_row($request);
    $details = ts_help_request_matching_details($request, ts_help_request_application_counts($pdo, $requestId));
    $currentStatus = ts_help_request_normalize_status((string) ($request['statusi'] ?? 'open'));

    if (!in_array($currentStatus, TS_HELP_REQUEST_TERMINAL_STATUSES, true) && $currentStatus !== $details['resolved_status']) {
        $update = $pdo->prepare('UPDATE Kerkesa_per_Ndihme SET statusi = ? WHERE id_kerkese_ndihme = ?');
        $update->execute([$details['resolved_status'], $requestId]);
    }

    return $details;
}

function ts_normalize_rows(array $rows): array
{
    return array_map('ts_normalize_row', $rows);
}

/**
 * Check if the current user is an Admin (includes super_admin).
 */
function is_admin(): bool
{
    if (!isset($_SESSION['roli'])) return false;
    $role = ts_normalize_value($_SESSION['roli']);
    return in_array($role, ['admin', 'super_admin'], true);
}

/**
 * Check if the current user is a Super Admin.
 */
function is_super_admin(): bool
{
    if (!isset($_SESSION['roli'])) return false;
    return ts_normalize_value($_SESSION['roli']) === 'super_admin';
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
//  Rate Limiting (IP-based, stored in database)
// ═══════════════════════════════════════════════════════

/**
 * Check if the action is within rate limits using IP + database.
 * Returns true if allowed, false if rate-limited.
 */
function check_rate_limit(string $action, int $maxAttempts = 5, int $windowSeconds = 900): bool
{
    global $pdo;

    // Use only REMOTE_ADDR — HTTP_X_FORWARDED_FOR is attacker-controlled and cannot be trusted
    // without a verified proxy whitelist, so we ignore it entirely.
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Count recent attempts within the time window
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM rate_limit_log WHERE ip = ? AND action = ? AND attempted_at > NOW() - INTERVAL ? SECOND'
    );
    $stmt->execute([$ip, $action, $windowSeconds]);
    $count = (int) $stmt->fetchColumn();

    if ($count >= $maxAttempts) {
        return false;
    }

    // Log this attempt
    $stmt = $pdo->prepare('INSERT INTO rate_limit_log (ip, action) VALUES (?, ?)');
    $stmt->execute([$ip, $action]);

    // Probabilistic cleanup: ~1% of requests delete old rows to avoid table bloat
    if (random_int(1, 100) === 1) {
        $pdo->exec('DELETE FROM rate_limit_log WHERE attempted_at < NOW() - INTERVAL 1 HOUR');
    }

    return true;
}

// ═══════════════════════════════════════════════════════
//  Admin Audit Log
// ═══════════════════════════════════════════════════════

/**
 * Log an admin action for auditing purposes.
 */
function log_admin_action(int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, array $details = []): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO admin_log (admin_id, veprim, target_type, target_id, detaje) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $adminId,
        $action,
        $targetType,
        $targetId,
        !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
    ]);
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
 * Build base URL from trusted configuration.
 * Never trust HTTP_HOST — it is attacker-controlled.
 */
function app_base_url(): string
{
    $configured = getenv('APP_URL');
    if ($configured !== false && $configured !== '') {
        return rtrim($configured, '/');
    }
    // Fallback for local development only
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') == '443');
    $scheme = $https ? 'https' : 'http';
    return $scheme . '://localhost/TiranaSolidare';
}

function ts_absolute_app_url(string $path = ''): string
{
    $path = trim($path);
    if ($path === '') {
        return app_base_url();
    }
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }

    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    if (str_starts_with($path, '/TiranaSolidare/')) {
        $path = substr($path, strlen('/TiranaSolidare'));
    }

    return app_base_url() . $path;
}

function ts_contact_page_path(): string
{
    return '/TiranaSolidare/views/contact.php';
}

function ts_support_email(): string
{
    $email = trim((string) (getenv('CONTACT_EMAIL') ?: getenv('SUPPORT_EMAIL') ?: getenv('SMTP_FROM') ?: 'info@tiranasolidare.al'));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : 'info@tiranasolidare.al';
}

function ts_support_name(): string
{
    $name = trim((string) (getenv('CONTACT_NAME') ?: getenv('SUPPORT_NAME') ?: 'Ekipi Tirana Solidare'));
    return $name !== '' ? $name : 'Ekipi Tirana Solidare';
}

function ts_is_admin_role_value(?string $role): bool
{
    return in_array(ts_normalize_value((string) $role), ['admin', 'super_admin'], true);
}

function ts_can_message_user_roles(?string $senderRole, ?string $receiverRole): bool
{
    return ts_is_admin_role_value($senderRole) || !ts_is_admin_role_value($receiverRole);
}

function ts_admin_contact_policy_message(): string
{
    return 'Administratorët nuk kontaktohen me mesazhe direkte. Përdorni faqen e kontaktit.';
}

/**
 * Send an email immediately via PHPMailer (synchronous).
 * Use for user-triggered flows (registration, password reset) where delivery must be instant.
 */
function send_email_direct(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText = '', array $options = []): bool
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('send_email_direct: autoload not found');
        return false;
    }
    require_once $autoload;

    $mailConfigPath = __DIR__ . '/../config/mail.php';
    if (!file_exists($mailConfigPath)) {
        error_log('send_email_direct: mail config not found');
        return false;
    }
    $cfg = require $mailConfigPath;
    if (!is_array($cfg)) {
        error_log('send_email_direct: invalid mail config');
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
        $mail->SMTPSecure = $secure === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->setFrom(
            (string) ($cfg['from_email'] ?? 'no-reply@localhost'),
            (string) ($cfg['from_name']  ?? 'Tirana Solidare')
        );
        $replyToEmail = $options['reply_to_email'] ?? null;
        if (is_string($replyToEmail) && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
            $replyToName = trim((string) ($options['reply_to_name'] ?? $replyToEmail));
            $mail->addReplyTo($replyToEmail, $replyToName !== '' ? $replyToName : $replyToEmail);
        }
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        if ($bodyText !== '') {
            $mail->AltBody = $bodyText;
        }
        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('send_email_direct failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Queue an email for delivery. Inserts into the email_queue table.
 * Actual sending is handled by process_email_queue().
 */
function queue_email(string $toEmail, string $toName, string $subject, string $bodyHtml, string $bodyText = '', int $maxAttempts = 3): bool
{
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO email_queue (to_email, to_name, subject, body_html, body_text, max_attempts, next_retry_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        return $stmt->execute([$toEmail, $toName, $subject, $bodyHtml, $bodyText, $maxAttempts]);
    } catch (Throwable $e) {
        error_log('queue_email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Process pending emails from the queue with retry and exponential backoff.
 * Call from a cron job or after request (register_shutdown_function).
 */
function process_email_queue(int $batchSize = 10): int
{
    global $pdo;

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) { error_log('process_email_queue: autoload not found'); return 0; }
    require_once $autoload;

    $mailConfigPath = __DIR__ . '/../config/mail.php';
    if (!file_exists($mailConfigPath)) { error_log('process_email_queue: mail config not found'); return 0; }
    $cfg = require $mailConfigPath;
    if (!is_array($cfg)) { error_log('process_email_queue: invalid config'); return 0; }

    $stmt = $pdo->prepare(
        "SELECT * FROM email_queue
         WHERE status = 'pending' AND next_retry_at <= NOW()
         ORDER BY krijuar_me ASC
         LIMIT ?"
    );
    $stmt->execute([$batchSize]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sent = 0;
    foreach ($emails as $row) {
        // Atomically claim this row using a conditional UPDATE (compare-and-swap).
        // If another cron instance already claimed it (race), rowCount() == 0 → skip.
        // This prevents double-sending without holding a DB transaction open during SMTP.
        // NOTE: requires status ENUM to include 'processing' — see migrate_security.php.
        $claim = $pdo->prepare(
            "UPDATE email_queue SET status = 'processing' WHERE id = ? AND status = 'pending'"
        );
        $claim->execute([$row['id']]);
        if ($claim->rowCount() === 0) {
            continue;
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
            $mail->SMTPSecure = $secure === 'ssl'
                ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->setFrom(
                (string) ($cfg['from_email'] ?? 'no-reply@localhost'),
                (string) ($cfg['from_name']  ?? 'Tirana Solidare')
            );
            $mail->addAddress($row['to_email'], $row['to_name']);
            $mail->isHTML(true);
            $mail->Subject = $row['subject'];
            $mail->Body    = $row['body_html'];
            if (!empty($row['body_text'])) $mail->AltBody = $row['body_text'];

            $mail->send();

            $pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW(), attempts=attempts+1 WHERE id=?")
                ->execute([$row['id']]);
            $sent++;
        } catch (Throwable $e) {
            $newAttempts = (int) $row['attempts'] + 1;
            $maxAttempts = (int) $row['max_attempts'];
            $newStatus = $newAttempts >= $maxAttempts ? 'failed' : 'pending';
            $backoffSeconds = min(3600, (int) pow(2, $newAttempts) * 30); // 60s, 120s, 240s … max 1h
            $pdo->prepare(
                "UPDATE email_queue SET status=?, attempts=?, last_error=?, next_retry_at=DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id=?"
            )->execute([$newStatus, $newAttempts, $e->getMessage(), $backoffSeconds, $row['id']]);
            error_log("Email queue #{$row['id']} attempt {$newAttempts} failed: " . $e->getMessage());
        }
    }
    return $sent;
}

/**
 * Send account verification email via PHPMailer (immediate, synchronous).
 */
function send_verification_email(string $toEmail, string $toName, string $verificationUrl): bool
{
    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
    $safeSite = htmlspecialchars(app_base_url(), ENT_QUOTES, 'UTF-8');

    $subject = 'Konfirmo email-in tënd - Tirana Solidare';
    $bodyHtml = "
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
        </div>";
    $bodyText = "Përshëndetje {$toName},\n\nKonfirmo email-in tënd duke hapur këtë link:\n{$verificationUrl}\n\nKy link skadon pas 24 orësh.";

    // Queue for audit trail and cron fallback if SMTP fails immediately
    global $pdo;
    $queueId = 0;
    try {
        if (queue_email($toEmail, $toName, $subject, $bodyHtml, $bodyText) && $pdo) {
            $queueId = (int) $pdo->lastInsertId();
        }
    } catch (Throwable $e) { /* non-critical */ }

    $sent = send_email_direct($toEmail, $toName, $subject, $bodyHtml, $bodyText);

    if ($sent && $queueId > 0) {
        try {
            $pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")->execute([$queueId]);
        } catch (Throwable $e) { /* non-critical */ }
    }

    return $sent;
}

/**
 * Send password reset email (immediate, synchronous).
 */
function send_password_reset_email(string $toEmail, string $toName, string $resetUrl): bool
{
    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $safeSite = htmlspecialchars(app_base_url(), ENT_QUOTES, 'UTF-8');

    $subject = 'Rivendos fjalëkalimin - Tirana Solidare';
    $bodyHtml = "
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
        </div>";
    $bodyText = "Përshëndetje {$toName},\n\nPër të rivendosur fjalëkalimin, hapni: {$resetUrl}\n\nKy link skadon pas 1 ore.";

    // Queue for audit trail and cron fallback if SMTP fails immediately
    global $pdo;
    $queueId = 0;
    try {
        if (queue_email($toEmail, $toName, $subject, $bodyHtml, $bodyText) && $pdo) {
            $queueId = (int) $pdo->lastInsertId();
        }
    } catch (Throwable $e) { /* non-critical */ }

    $sent = send_email_direct($toEmail, $toName, $subject, $bodyHtml, $bodyText);

    if ($sent && $queueId > 0) {
        try {
            $pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")->execute([$queueId]);
        } catch (Throwable $e) { /* non-critical */ }
    }

    return $sent;
}

/**
 * Send a generic user notification email (queued for background delivery).
 */
function send_notification_email(string $toEmail, string $toName, string $subject, string $message, array $options = []): bool
{
    global $pdo;
    $bypassPreferences = !empty($options['bypass_preferences']);

    if (!$bypassPreferences) {
        try {
            $prefStmt = $pdo->prepare('SELECT email_notifications FROM Perdoruesi WHERE email = ? LIMIT 1');
            $prefStmt->execute([$toEmail]);
            $pref = $prefStmt->fetch(PDO::FETCH_ASSOC);
            if ($pref && (int) $pref['email_notifications'] === 0) {
                return true;
            }
        } catch (Throwable $e) {
            // If preference check fails, proceed with sending.
        }
    }

    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    $safeSite = htmlspecialchars(app_base_url(), ENT_QUOTES, 'UTF-8');
    $actionUrl = trim((string) ($options['action_url'] ?? ''));
    if ($actionUrl !== '') {
        $actionUrl = ts_absolute_app_url($actionUrl);
    }
    $actionLabel = trim((string) ($options['action_label'] ?? ''));
    if ($actionUrl !== '' && $actionLabel === '') {
        $actionLabel = 'Shiko detajet';
    }
    $actionHtml = '';
    $actionText = '';
    if ($actionUrl !== '' && $actionLabel !== '') {
        $safeActionUrl = htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8');
        $safeActionLabel = htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8');
        $actionHtml = '
                      <p style="margin:0 0 20px; text-align:center;">
                        <a href="' . $safeActionUrl . '" style="display:inline-block; padding:13px 20px; background:#00715D; color:#ffffff; text-decoration:none; border-radius:8px; font-weight:700; font-size:15px;">' . $safeActionLabel . '</a>
                      </p>';
        $actionText = "\n\n{$actionLabel}: {$actionUrl}";
    }
    $preferencesHtml = '';
    $preferencesText = '';
    if (!$bypassPreferences) {
        $preferencesHtml = '
                  <tr>
                    <td style="padding:12px 30px 16px; border-top:1px solid #e9f3ef;">
                      <p style="margin:0; color:#999; font-size:11px; text-align:center; line-height:1.5;">Nuk dëshironi të merrni këto email? <a href="' . $safeSite . '/views/volunteer_panel.php?tab=settings" style="color:#00715D; text-decoration:underline;">Çaktivizoni njoftimet me email</a> në cilësimet e llogarisë suaj.</p>
                    </td>
                  </tr>';
        $preferencesText = "\n\nPër të çaktivizuar njoftimet me email, vizitoni cilësimet e llogarisë.";
    }

    $bodyHtml = "
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
                      <h2 style=\"margin:0 0 16px; color:#0b3f34; font-size:22px;\">{$safeSubject}</h2>
                      <p style=\"margin:0 0 20px; color:#4a4a4a; font-size:15px; line-height:1.6;\">{$safeMessage}</p>
                                            {$actionHtml}
                      <p style=\"margin:0; color:#6b6b6b; font-size:12px;\">Ky mesazh është nga Tirana Solidare.</p>
                    </td>
                  </tr>
                                    {$preferencesHtml}
                  <tr>
                    <td style=\"padding:16px 30px 20px; background:#f1f8f4; color:#3c3c3c; font-size:12px; text-align:center;\">
                      <strong>Tirana Solidare</strong> • <a href=\"{$safeSite}\" style=\"color:#00715D; text-decoration:none;\">tiranasolidare.al</a>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </div>";
        $bodyText = "Përshëndetje {$toName},\n\n{$message}{$actionText}{$preferencesText}\n\nTirana Solidare";

    return queue_email($toEmail, $toName, $subject, $bodyHtml, $bodyText);
}

function send_contact_email(string $fromEmail, string $fromName, string $subject, string $message, ?int $fromUserId = null): bool
{
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                return false;
        }

        $fromName = trim($fromName) !== '' ? trim($fromName) : 'Vizitor';
        $subject = trim($subject) !== '' ? trim($subject) : 'Mesazh nga faqja e kontaktit';
        $message = trim($message);
        if ($message === '') {
                return false;
        }

        $toEmail = ts_support_email();
        $toName = ts_support_name();
        $finalSubject = '[Kontakt] ' . $subject;
        $safeFromName = htmlspecialchars($fromName, ENT_QUOTES, 'UTF-8');
        $safeFromEmail = htmlspecialchars($fromEmail, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $safeSite = htmlspecialchars(app_base_url(), ENT_QUOTES, 'UTF-8');
        $accountLine = $fromUserId !== null ? 'ID e llogarisë: ' . $fromUserId : 'Dërgues pa hyrje';
        $safeAccountLine = htmlspecialchars($accountLine, ENT_QUOTES, 'UTF-8');

        $bodyHtml = "
                <div style=\"font-family:Inter, Arial, sans-serif; margin:0; padding:0; background:#f6fbf9; color:#1f2d2a;\">
                    <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\">
                        <tr>
                            <td align=\"center\" style=\"padding:24px 12px;\">
                                <table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" role=\"presentation\" style=\"background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 8px 22px rgba(0,0,0,0.08);\">
                                    <tr>
                                        <td style=\"background:linear-gradient(135deg, #00715D 0%, #005a48 100%); padding:20px 24px; text-align:center; color:#fff;\">
                                            <div style=\"font-size:24px; font-weight:800; letter-spacing:0.2px;\">Tirana <strong>Solidare</strong></div>
                                            <div style=\"font-size:14px; opacity:0.9; margin-top:2px;\">Mesazh i ri nga faqja e kontaktit</div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style=\"padding:24px 30px 20px;\">
                                            <h2 style=\"margin:0 0 16px; color:#0b3f34; font-size:22px;\">{$safeSubject}</h2>
                                            <p style=\"margin:0 0 8px; color:#2b3a3a; font-size:15px;\"><strong>Dërguesi:</strong> {$safeFromName}</p>
                                            <p style=\"margin:0 0 8px; color:#2b3a3a; font-size:15px;\"><strong>Email-i:</strong> <a href=\"mailto:{$safeFromEmail}\" style=\"color:#00715D; text-decoration:none;\">{$safeFromEmail}</a></p>
                                            <p style=\"margin:0 0 16px; color:#2b3a3a; font-size:14px;\">{$safeAccountLine}</p>
                                            <div style=\"padding:16px 18px; background:#f4faf7; border:1px solid #dceee6; border-radius:10px; color:#374151; font-size:15px; line-height:1.65;\">{$safeMessage}</div>
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
                </div>";
        $bodyText = "Mesazh i ri nga faqja e kontaktit\n\nSubjekti: {$subject}\nDërguesi: {$fromName}\nEmail-i: {$fromEmail}\n{$accountLine}\n\n{$message}";

        global $pdo;
        $queueId = 0;
        try {
                if (queue_email($toEmail, $toName, $finalSubject, $bodyHtml, $bodyText) && $pdo) {
                        $queueId = (int) $pdo->lastInsertId();
                }
        } catch (Throwable $e) {
                // Non-critical fallback to direct send below.
        }

        $sent = send_email_direct($toEmail, $toName, $finalSubject, $bodyHtml, $bodyText, [
                'reply_to_email' => $fromEmail,
                'reply_to_name' => $fromName,
        ]);

        if ($sent && $queueId > 0) {
                try {
                        $pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW() WHERE id=?")->execute([$queueId]);
                } catch (Throwable $e) {
                        // Non-critical.
                }
        }

        return $sent;
}

/**
 * Check user-submitted text for profanity/slurs.
 *
 * Returns a translated error string if a match is found, or null if the text is clean.
 * Matching is word-boundary aware and case-insensitive.
 * Update $BANNED_WORDS as needed — keep the list in the source, no DB table needed.
 */
function check_profanity(string ...$texts): ?string
{
    // Albanian and common English slurs / profanity
    static $BANNED_WORDS = [
        // Albanian
        'pidh', 'kar', 'qifte', 'qifsha', 'qift', 'byth', 'bythë',
        'mut', 'mutit', 'mutja', 'cope', 'copë', 'hajvan', 'kurv',
        'kurvë', 'kurvat', 'lavire', 'dollosh', 'gomar', 'derra',
        'idiot', 'budall', 'budalla', 'kretën', 'kretin', 'rrot',
        'rrota', 'trap', 'trapi',
        // English
        'fuck', 'shit', 'asshole', 'bitch', 'cunt', 'bastard',
        'dickhead', 'motherfucker', 'faggot', 'nigger', 'retard',
        'whore', 'slut', 'prick', 'cock', 'pussy', 'twat',
    ];

    foreach ($texts as $text) {
        if ($text === '') continue;
        foreach ($BANNED_WORDS as $word) {
            // Word-boundary regex prevents false positives on innocent substrings (e.g. 'kar' in 'karkasë')
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/iu', $text)) {
                return 'Teksti përmban fjalë të papërshtatshme. Ju lutem rishikoni dhe provoni përsëri.';
            }
        }
    }
    return null;
}

/**
 * Validate that a URL is a safe image URL (https:// only, no JS/data schemes).
 */
function validate_image_url(?string $url): bool
{
    if (empty($url)) return true; // Optional field
    // Allow internal upload paths (relative paths starting with our app prefix)
    if (strncmp($url, '/TiranaSolidare/', 16) === 0) {
        return true;
    }
    if (!preg_match('#^https://#i', $url)) {
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

/**
 * Unified image upload handler.
 *
 * Validates the uploaded file, optionally resizes via GD, converts to WebP,
 * and saves to the given directory.  Returns an associative array on success
 * or a string error message on failure.
 *
 * @param array  $file         $_FILES['...'] entry
 * @param string $destDir      Absolute path to the target directory
 * @param string $publicPrefix URL prefix for the saved file (e.g. '/TiranaSolidare/uploads/images/')
 * @param int    $maxBytes     Maximum allowed file size in bytes (default 5 MB)
 * @param int    $maxDimension Resize longest side to this many pixels (0 = no resize)
 * @param int    $webpQuality  WebP quality 0-100 (default 80)
 * @return array{url:string,filename:string,size:int,mime:string,width:int,height:int}|string
 */
function handle_image_upload(
    array  $file,
    string $destDir,
    string $publicPrefix,
    int    $maxBytes     = 5 * 1024 * 1024,
    int    $maxDimension = 700,
    int    $webpQuality  = 80
): array|string {
    // 1. Upload error check
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'Skedari është shumë i madh (limit serveri).',
            UPLOAD_ERR_FORM_SIZE  => 'Skedari kalon limitin e formës.',
            UPLOAD_ERR_PARTIAL    => 'Skedari u ngarkua vetëm pjesërisht.',
            UPLOAD_ERR_NO_FILE    => 'Asnjë skedar nuk u zgjodh.',
            UPLOAD_ERR_NO_TMP_DIR => 'Mungon dosja e përkohshme.',
            UPLOAD_ERR_CANT_WRITE => 'Gabim gjatë shkrimit në disk.',
        ];
        return $errorMessages[$file['error']] ?? 'Gabim i panjohur gjatë ngarkimit.';
    }

    // 2. Size check
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > $maxBytes) {
        return 'Skedari është shumë i madh. Maksimumi është ' . round($maxBytes / 1048576) . 'MB.';
    }

    // 3. MIME validation via finfo (server-side, ignores client header)
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!isset($allowedMimes[$mime])) {
        return 'Formati i skedarit nuk lejohet. Përdorni: JPG, PNG, GIF ose WEBP.';
    }

    $hasGD = extension_loaded('gd') && function_exists('imagewebp');

    // 4. If GD + WebP available, resize & convert
    if ($hasGD) {
        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => @imagecreatefrompng($file['tmp_name']),
            'image/gif'  => @imagecreatefromgif($file['tmp_name']),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
            default      => false,
        };
        if (!$source) {
            return 'Gabim gjatë leximit të imazhit. Kontrollo formatin e skedarit.';
        }

        $origW = imagesx($source);
        $origH = imagesy($source);
        if ($origW <= 0 || $origH <= 0) {
            imagedestroy($source);
            return 'Përmasat e imazhit janë të pavlefshme.';
        }

        if ($maxDimension > 0) {
            $scale = min(1, $maxDimension / max($origW, $origH));
        } else {
            $scale = 1;
        }
        $newW = max(1, (int) round($origW * $scale));
        $newH = max(1, (int) round($origH * $scale));

        $resized = @imagecreatetruecolor($newW, $newH);
        if (!$resized) {
            imagedestroy($source);
            return 'Nuk mund të krijohet imazhi i ri.';
        }

        // Preserve transparency
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);

        if (!imagecopyresampled($resized, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH)) {
            imagedestroy($source);
            imagedestroy($resized);
            return 'Gabim gjatë përpunimit të imazhit.';
        }
        imagedestroy($source);

        $filename = generate_upload_filename('webp');
        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
            imagedestroy($resized);
            return 'Nuk mund të krijohet dosja e ngarkimit.';
        }

        $dest = $destDir . '/' . $filename;
        if (!@imagewebp($resized, $dest, $webpQuality)) {
            imagedestroy($resized);
            return 'Gabim gjatë ruajtjes të imazhit WebP.';
        }
        imagedestroy($resized);

        return [
            'url'      => rtrim($publicPrefix, '/') . '/' . $filename,
            'filename' => $filename,
            'size'     => filesize($dest),
            'mime'     => 'image/webp',
            'width'    => $newW,
            'height'   => $newH,
        ];
    }

    // 5. Fallback: store original file without processing
    $ext = $allowedMimes[$mime];
    $filename = generate_upload_filename($ext);
    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) {
        return 'Nuk mund të krijohet dosja e ngarkimit.';
    }
    $dest = $destDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return 'Gabim gjatë ruajtjes të skedarit.';
    }

    return [
        'url'      => rtrim($publicPrefix, '/') . '/' . $filename,
        'filename' => $filename,
        'size'     => filesize($dest),
        'mime'     => $mime,
        'width'    => 0,
        'height'   => 0,
    ];
}

/**
 * Build profile activity metrics used by badges and profile widgets.
 */
function ts_collect_user_badge_metrics(PDO $pdo, int $userId): array
{
    $registeredAtStmt = $pdo->prepare('SELECT krijuar_me FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
    $registeredAtStmt->execute([$userId]);
    $registeredAt = $registeredAtStmt->fetchColumn();

    $totalAppsStmt = $pdo->prepare('SELECT COUNT(*) FROM Aplikimi WHERE id_perdoruesi = ?');
    $totalAppsStmt->execute([$userId]);
    $totalApps = (int) $totalAppsStmt->fetchColumn();

    $acceptedEventsStmt = $pdo->prepare("SELECT COUNT(*) FROM Aplikimi WHERE id_perdoruesi = ? AND statusi = 'approved'");
    $acceptedEventsStmt->execute([$userId]);
    $acceptedEvents = (int) $acceptedEventsStmt->fetchColumn();

    $totalRequestsStmt = $pdo->prepare('SELECT COUNT(*) FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?');
    $totalRequestsStmt->execute([$userId]);
    $totalRequests = (int) $totalRequestsStmt->fetchColumn();

    $acceptedHelpApps = 0;
    try {
        $acceptedHelpAppsStmt = $pdo->prepare("SELECT COUNT(*) FROM Aplikimi_Kerkese WHERE id_perdoruesi = ? AND statusi IN ('approved', 'completed')");
        $acceptedHelpAppsStmt->execute([$userId]);
        $acceptedHelpApps = (int) $acceptedHelpAppsStmt->fetchColumn();
    } catch (Throwable $e) {
        $acceptedHelpApps = 0;
    }

    $memberDays = 0;
    if ($registeredAt) {
        $registered = new DateTimeImmutable((string) $registeredAt);
        $now = new DateTimeImmutable('now');
        $memberDays = max(0, (int) $registered->diff($now)->days);
    }

    return [
        'total_applications' => $totalApps,
        'accepted_events' => $acceptedEvents,
        'total_requests' => $totalRequests,
        'accepted_help_applications' => $acceptedHelpApps,
        'member_days' => $memberDays,
    ];
}

/**
 * Return earned badges for a user profile, derived from platform activity.
 */
function ts_get_user_profile_badges(PDO $pdo, int $userId): array
{
    $metrics = ts_collect_user_badge_metrics($pdo, $userId);

    $badgeCatalog = [
        [
            'key' => 'first_step',
            'name' => 'Hapi i Parë',
            'description' => 'Nisur kontributin në komunitet.',  
            'icon' => 'seedling',
            'condition' => ($metrics['total_applications'] + $metrics['total_requests'] + $metrics['accepted_help_applications']) >= 1,
        ],
        [
            'key' => 'event_starter',
            'name' => 'Startues Eventesh',
            'description' => 'Të paktën 1 aplikim eventi i pranuar.',
            'icon' => 'calendar-check',
            'condition' => $metrics['accepted_events'] >= 1,
        ],
        [
            'key' => 'community_helper',
            'name' => 'Ndihmues i Komunitetit',
            'description' => 'Të paktën 5 evente të pranuara.',
            'icon' => 'hands-helping',
            'condition' => $metrics['accepted_events'] >= 5,
        ],
        [
            'key' => 'request_creator',
            'name' => 'Zëri i Lagjes',
            'description' => 'Të paktën 3 kërkesa për ndihmë të krijuara.',
            'icon' => 'megaphone',
            'condition' => $metrics['total_requests'] >= 3,
        ],
        [
            'key' => 'trusted_supporter',
            'name' => 'Mbështetës i Besuar',
            'description' => 'Të paktën 3 aplikime ndihme të pranuara.',
            'icon' => 'heart-handshake',
            'condition' => $metrics['accepted_help_applications'] >= 3,
        ],
        [
            'key' => 'veteran_member',
            'name' => 'Anëtar Veteran',
            'description' => 'Pjesë e platformës prej të paktën 180 ditësh.',
            'icon' => 'shield',
            'condition' => $metrics['member_days'] >= 180,
        ],
        [
            'key' => 'all_rounder',
            'name' => 'All-Rounder',
            'description' => 'Aktiv në evente, kërkesa dhe ndihmë të drejtpërdrejtë.',
            'icon' => 'sparkles',
            'condition' => $metrics['accepted_events'] >= 3
                && $metrics['total_requests'] >= 2
                && $metrics['accepted_help_applications'] >= 2,
        ],
    ];

    $earned = [];
    foreach ($badgeCatalog as $badge) {
        if ($badge['condition']) {
            unset($badge['condition']);
            $earned[] = $badge;
        }
    }

    return [
        'badges' => $earned,
        'metrics' => $metrics,
    ];
}

/**
 * Predefined profile color palette (no free color picker).
 */
function ts_profile_color_palette(): array
{
    return [
        'emerald' => ['label' => 'Emerald', 'from' => '#003229', 'mid' => '#00715D', 'to' => '#009e7e'],
        'ocean' => ['label' => 'Ocean', 'from' => '#0b2a52', 'mid' => '#1d4ed8', 'to' => '#2563eb'],
        'sunset' => ['label' => 'Sunset', 'from' => '#7c2d12', 'mid' => '#ea580c', 'to' => '#f97316'],
        'rose' => ['label' => 'Rose', 'from' => '#881337', 'mid' => '#be185d', 'to' => '#e11d48'],
        'violet' => ['label' => 'Violet', 'from' => '#3b0764', 'mid' => '#7e22ce', 'to' => '#9333ea'],
        'slate' => ['label' => 'Slate', 'from' => '#1e293b', 'mid' => '#334155', 'to' => '#475569'],
        'teal' => ['label' => 'Teal', 'from' => '#134e4a', 'mid' => '#0d9488', 'to' => '#14b8a6'],
        'amber' => ['label' => 'Amber', 'from' => '#78350f', 'mid' => '#d97706', 'to' => '#f59e0b'],
        'indigo' => ['label' => 'Indigo', 'from' => '#312e81', 'mid' => '#4f46e5', 'to' => '#6366f1'],
        'pink' => ['label' => 'Pink', 'from' => '#831843', 'mid' => '#ec4899', 'to' => '#f472b6'],
        'lime' => ['label' => 'Lime', 'from' => '#365314', 'mid' => '#84cc16', 'to' => '#a3e635'],
        'cyan' => ['label' => 'Cyan', 'from' => '#082f49', 'mid' => '#0891b2', 'to' => '#06b6d4'],
    ];
}

/**
 * Resolve a profile color key to a valid palette entry.
 */
function ts_resolve_profile_color(?string $key): array
{
    $palette = ts_profile_color_palette();
    $selected = $key && isset($palette[$key]) ? $key : 'emerald';
    return [
        'key' => $selected,
        'theme' => $palette[$selected],
        'palette' => $palette,
    ];
}

/**
 * Build a URL-safe slug from a display name.
 */
function ts_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'user';
    }

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            $value = $converted;
        }
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'user';
}

/**
 * Parse public profile handle (e.g. emri-mbiemri-123) and return user ID.
 */
function ts_parse_public_profile_id(?string $handle): int
{
    $value = trim((string) $handle);
    if ($value === '') {
        return 0;
    }

    if (preg_match('/-(\d+)$/', $value, $matches)) {
        return (int) $matches[1];
    }

    if (ctype_digit($value)) {
        return (int) $value;
    }

    return 0;
}

/**
 * Send a Web Push notification to all active subscriptions for a given user.
 * Silently no-ops when VAPID keys are not configured or web_push.php is unavailable.
 */
function send_push_to_user(int $userId, string $title, string $body, string $url = ''): void
{
    $vapidPublic  = getenv('VAPID_PUBLIC_KEY');
    $vapidPrivate = getenv('VAPID_PRIVATE_KEY');
    $vapidSubject = getenv('VAPID_SUBJECT') ?: 'mailto:admin@tiranasolidare.al';

    if (!$vapidPublic || !$vapidPrivate) {
        return;
    }

    $webPushFile = __DIR__ . '/web_push.php';
    if (!file_exists($webPushFile)) {
        return;
    }
    require_once $webPushFile;

    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT id, endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    $payload = ['title' => $title, 'body' => $body, 'url' => $url];

    foreach ($subscriptions as $sub) {
        try {
            send_web_push($sub, $payload, $vapidPublic, $vapidPrivate, $vapidSubject);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'subscription_expired') {
                $pdo->prepare('DELETE FROM push_subscriptions WHERE id = ?')
                    ->execute([$sub['id']]);
            }
        }
    }
}

/**
 * Build a readable public profile URL with name slug and ID.
 */
function ts_public_profile_url(int $userId, ?string $displayName = null): string
{
    $id = max(0, $userId);
    if ($id <= 0) {
        return '/TiranaSolidare/views/public_profile.php';
    }

    $slug = ts_slugify((string) ($displayName ?? 'user'));
    return '/TiranaSolidare/views/public_profile.php?u=' . rawurlencode($slug . '-' . $id);
}

/**
 * Get the current site logo URL.
 * Returns the latest uploaded custom logo if available, otherwise the default logo.
 */
function ts_get_site_logo_url(): string
{
    $upload_dir = __DIR__ . '/../public/assets/uploads';
    $base_url = '/TiranaSolidare/public/assets/uploads';
    
    if (!is_dir($upload_dir)) {
        return '/TiranaSolidare/public/assets/images/logo.png';
    }
    
    $files = glob($upload_dir . '/site-logo-*.{png,jpg,jpeg,gif,svg,webp}', GLOB_BRACE);
    if (empty($files)) {
        return '/TiranaSolidare/public/assets/images/logo.png';
    }
    
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $latest = $files[0];
    $filename = basename($latest);
    return $base_url . '/' . $filename;
}

/**
 * Check if a custom logo has been uploaded (not the default).
 */
function ts_has_custom_logo(): bool
{
    $upload_dir = __DIR__ . '/../public/assets/uploads';
    
    if (!is_dir($upload_dir)) {
        return false;
    }
    
    $files = glob($upload_dir . '/site-logo-*.{png,jpg,jpeg,gif,svg,webp}', GLOB_BRACE);
    return !empty($files);
}

/**
 * Check if two users have blocked each other (mutual block check).
 * Returns true if blocker_id=A,blocked_id=B OR blocker_id=B,blocked_id=A
 *
 * @param PDO $pdo Database connection
 * @param int $userId First user ID
 * @param int $otherUserId Second user ID
 * @return bool True if users are blocked from each other
 */
function isUserBlocked(PDO $pdo, int $userId, int $otherUserId): bool
{
    if ($userId <= 0 || $otherUserId <= 0 || $userId === $otherUserId) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM user_blocks
         WHERE (blocker_id = ? AND blocked_id = ?)
            OR (blocker_id = ? AND blocked_id = ?)'
    );
    $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Return true if the given date-of-birth string represents a reasonable age
 * (at least 1 year old and no more than 120 years old).
 */
function ts_birthdate_is_reasonable(?string $dob): bool
{
    if ($dob === null || $dob === '') {
        return false;
    }
    $birth = DateTime::createFromFormat('Y-m-d', $dob);
    if ($birth === false) {
        return false;
    }
    $now = new DateTime();
    $age = (int) $now->diff($birth)->y;
    // diff gives the absolute difference; ensure birth is in the past
    if ($birth > $now) {
        return false;
    }
    return $age >= 1 && $age <= 120;
}
