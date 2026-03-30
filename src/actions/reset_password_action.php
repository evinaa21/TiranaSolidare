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

    // Atomic UPDATE: only succeeds if token matches AND has not expired.
    // This eliminates the TOCTOU race — two concurrent requests with the same
    // token can both hit this UPDATE, but only the first will match (rowCount > 0);
    // the second sees rowCount = 0 because the first already nulled the token.
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $pdo->prepare(
        'UPDATE Perdoruesi
         SET fjalekalimi = ?, password_changed_at = NOW(),
             password_reset_token_hash = NULL, password_reset_token_expires = NULL
         WHERE email = ? AND password_reset_token_hash = ? AND password_reset_token_expires > NOW()'
    );
    $update->execute([$newHash, $email, $tokenHash]);

    if ($update->rowCount() === 0) {
        // Token was invalid, expired, or already consumed by a concurrent request
        header('Location: /TiranaSolidare/views/reset_password.php?error=invalid_token');
        exit();
    }

    // Derive user ID for session cleanup
    $user = $pdo->prepare('SELECT id_perdoruesi FROM Perdoruesi WHERE email = ? LIMIT 1');
    $user->execute([$email]);
    $user = $user->fetch(PDO::FETCH_ASSOC);

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
