<?php
session_start();
require_once __DIR__ . '/../../includes/functions.php';

// Validate CSRF token (from GET param or header)
$token = $_GET['token'] ?? $_POST['_csrf_token'] ?? '';
if (!validate_csrf_token($token)) {
    http_response_code(403);
    echo 'Sesioni ka skaduar. Rifreskoni faqen.';
    exit();
}

session_unset();
session_destroy();
header("Location: /TiranaSolidare/views/login.php");
exit();