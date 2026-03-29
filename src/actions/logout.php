<?php
// Set the same cookie params used by api/helpers.php so PHP finds the correct session
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
require_once __DIR__ . '/../../includes/functions.php';

// Only allow POST for logout (prevent CSRF via Referer leak)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: /TiranaSolidare/views/login.php');
    exit();
}

// Validate CSRF token from POST body
$token = $_POST['_csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    // Token mismatch — still destroy the session for safety, then redirect
    session_unset();
    session_destroy();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/TiranaSolidare/',
        'secure'   => $isHttps,
        'httponly'  => true,
        'samesite' => 'Lax',
    ]);
    header('Location: /TiranaSolidare/views/login.php');
    exit();
}

session_unset();
session_destroy();
// Explicitly expire the session cookie so the browser removes it
setcookie(session_name(), '', [
    'expires'  => time() - 3600,
    'path'     => '/TiranaSolidare/',
    'secure'   => $isHttps,
    'httponly'  => true,
    'samesite' => 'Lax',
]);
header("Location: /TiranaSolidare/views/login.php");
exit();