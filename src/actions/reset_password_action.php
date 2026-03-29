<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /TiranaSolidare/views/login.php');
    exit();
}

if (!validate_csrf_token($_POST['_csrf_token'] ?? '')) {
    header('Location: /TiranaSolidare/views/reset_password.php?error=csrf_expired');
    exit();
}

$token = trim($_POST['token'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($token === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /TiranaSolidare/views/reset_password.php?error=invalid_token&token=' . urlencode($token) . '&email=' . urlencode($email));
    exit();
}

if (empty($password) || empty($confirmPassword)) {
    header('Location: /TiranaSolidare/views/reset_password.php?error=empty_fields&token=' . urlencode($token) . '&email=' . urlencode($email));
    exit();
}

if ($password !== $confirmPassword) {
    header('Location: /TiranaSolidare/views/reset_password.php?error=password_mismatch&token=' . urlencode($token) . '&email=' . urlencode($email));
    exit();
}

if ($passwordError = validate_password_strength($password)) {
    header('Location: /TiranaSolidare/views/reset_password.php?error=password_weak&token=' . urlencode($token) . '&email=' . urlencode($email));
    exit();
}

try {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare('SELECT id_perdoruesi, password_reset_token_expires FROM Perdoruesi WHERE email = ? AND password_reset_token_hash = ? LIMIT 1');
    $stmt->execute([$email, $tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || empty($user['password_reset_token_expires']) || strtotime($user['password_reset_token_expires']) < time()) {
        header('Location: /TiranaSolidare/views/reset_password.php?error=invalid_token');
        exit();
    }

    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $pdo->prepare('UPDATE Perdoruesi SET fjalekalimi = ?, password_changed_at = NOW(), password_reset_token_hash = NULL, password_reset_token_expires = NULL WHERE id_perdoruesi = ?');
    $update->execute([$newHash, (int) $user['id_perdoruesi']]);

    // Invalidate all existing sessions for this user
    session_unset();
    session_destroy();

    // Start a fresh session for the redirect flash message
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Location: /TiranaSolidare/views/login.php?success=password_updated');
    exit();
} catch (Throwable $e) {
    error_log('Reset password failed: ' . $e->getMessage());
    header('Location: /TiranaSolidare/views/reset_password.php?error=sql_error&token=' . urlencode($token) . '&email=' . urlencode($email));
    exit();
}
