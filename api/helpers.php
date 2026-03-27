<?php
/**
 * api/helpers.php
 * ---------------------------------------------------
 * Shared helper functions for all API endpoints.
 * Handles JSON responses, authentication, RBAC,
 * input validation, and CORS headers.
 * ---------------------------------------------------
 */

// Start session if not already started
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

// Enforce session timeout on API requests
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_unset();
        session_destroy();
        if (session_status() === PHP_SESSION_NONE) session_start();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sesioni ka skaduar. Ju lutem kyçuni përsëri.'], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// Include DB connection
require_once __DIR__ . '/../config/db.php';

// Include shared utility functions (CSRF, validation, etc.)
require_once __DIR__ . '/../includes/functions.php';

// ── CORS & Content-Type Headers ────────────────────
header('Content-Type: application/json; charset=utf-8');

// Restrict CORS to same-origin (localhost variants)
$allowedOrigins = [
    'http://localhost',
    'http://localhost:80',
    'http://localhost:3000',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:80',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:8080',
    'https://localhost',
    'https://localhost:443',
    'https://127.0.0.1',
    'https://127.0.0.1:443',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// ── CSRF Enforcement for state-changing requests ───
if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) {
    if (!validate_csrf_token()) {
        json_error('Sesioni ka skaduar. Rifreskoni faqen.', 403);
    }
    // Regenerate CSRF token after successful validation to prevent replay
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── JSON Response Helpers ──────────────────────────

/**
 * Send a successful JSON response.
 */
function json_success($data = [], int $code = 200): void
{
    http_response_code($code);
    $response = [
        'success' => true,
        'data'    => $data,
    ];
    // Include refreshed CSRF token so the client can use it for subsequent requests
    if (isset($_SESSION['csrf_token'])) {
        $response['csrf_token'] = $_SESSION['csrf_token'];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Send an error JSON response.
 */
function json_error(string $message, int $code = 400, array $errors = []): void
{
    http_response_code($code);
    $response = [
        'success' => false,
        'message' => $message,
    ];
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    if (isset($_SESSION['csrf_token'])) {
        $response['csrf_token'] = $_SESSION['csrf_token'];
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// ── Authentication Middleware ──────────────────────

/**
 * Require the user to be logged in (session-based).
 * Re-verifies role and account status from DB every 60 seconds
 * to catch blocks, deactivations, and role changes promptly.
 */
function require_auth(): array
{
    if (!isset($_SESSION['user_id'])) {
        json_error('Ju nuk jeni të kyçur. / Unauthorized.', 401);
    }

    // Re-verify from DB periodically (every 60s) or on first call
    $now = time();
    if (!isset($_SESSION['_auth_verified_at']) || ($now - $_SESSION['_auth_verified_at']) > 60) {
        global $pdo;
        $stmt = $pdo->prepare('SELECT roli, statusi_llogarise FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $dbUser = $stmt->fetch();

        if (!$dbUser) {
            session_unset();
            session_destroy();
            json_error('Llogaria nuk ekziston më.', 401);
        }

        if ($dbUser['statusi_llogarise'] === 'blocked') {
            session_unset();
            session_destroy();
            json_error('Llogaria juaj është bllokuar.', 403);
        }

        if ($dbUser['statusi_llogarise'] === 'deactivated') {
            session_unset();
            session_destroy();
            json_error('Llogaria juaj është çaktivizuar.', 403);
        }

        // Sync role from DB into session (handles demotions)
        $_SESSION['roli'] = $dbUser['roli'];
        $_SESSION['_auth_verified_at'] = $now;
    }

    return [
        'id'   => (int) $_SESSION['user_id'],
        'emri' => $_SESSION['emri'] ?? '',
        'roli' => $_SESSION['roli'] ?? 'volunteer',
    ];
}

/**
 * Require the current user to be an Admin.
 */
function require_admin(): array
{
    $user = require_auth();
    if ($user['roli'] !== 'admin') {
        json_error('Kjo veprim kërkon privilegje administratori. / Forbidden.', 403);
    }
    return $user;
}

// ── Input Helpers ──────────────────────────────────

/**
 * Parse JSON body from the request (for POST/PUT/DELETE).
 */
function get_json_body(): array
{
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Get a required field from an array, trimming strings.
 * Returns null and appends to $errors if missing.
 */
function required_field(array $source, string $key, array &$errors): ?string
{
    $value = isset($source[$key]) ? trim((string) $source[$key]) : '';
    if ($value === '') {
        $errors[] = "Fusha '$key' është e detyrueshme.";
        return null;
    }
    return $value;
}

// ── Method Enforcement ─────────────────────────────

/**
 * Ensure the request uses one of the allowed HTTP methods.
 */
function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        json_error('Metoda e kërkesës nuk lejohet. / Method Not Allowed.', 405);
    }
}

// ── Pagination Helper ──────────────────────────────

/**
 * Returns validated page & limit from query string.
 */
function get_pagination(int $defaultLimit = 20, int $maxLimit = 100): array
{
    $page  = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min($maxLimit, max(1, (int) ($_GET['limit'] ?? $defaultLimit)));
    $offset = ($page - 1) * $limit;
    return compact('page', 'limit', 'offset');
}
