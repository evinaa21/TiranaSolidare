<?php
/**
 * includes/functions.php
 * ---------------------------------------------------
 * Shared helper functions for views & actions.
 * ---------------------------------------------------
 */

/**
 * Check if user is logged in; redirect to login if not.
 */
function check_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
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
