<?php
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
    http_response_code(403);
    echo 'Sesioni ka skaduar. Rifreskoni faqen.';
    exit();
}

session_unset();
session_destroy();
header("Location: /TiranaSolidare/views/login.php");
exit();