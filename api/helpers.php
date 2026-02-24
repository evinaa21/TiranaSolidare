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
    session_start();
}

// Include DB connection
require_once __DIR__ . '/../config/db.php';

// ── CORS & Content-Type Headers ────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// ── JSON Response Helpers ──────────────────────────

/**
 * Send a successful JSON response.
 */
function json_success($data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode([
        'success' => true,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
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
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// ── Authentication Middleware ──────────────────────

/**
 * Require the user to be logged in (session-based).
 * Returns the user's session data or terminates with 401.
 */
function require_auth(): array
{
    if (!isset($_SESSION['user_id'])) {
        json_error('Ju nuk jeni të kyçur. / Unauthorized.', 401);
    }
    return [
        'id'   => (int) $_SESSION['user_id'],
        'emri' => $_SESSION['emri'] ?? '',
        'roli' => $_SESSION['roli'] ?? 'Vullnetar',
    ];
}

/**
 * Require the current user to be an Admin.
 */
function require_admin(): array
{
    $user = require_auth();
    if ($user['roli'] !== 'Admin') {
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
