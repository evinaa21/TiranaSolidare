<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');

if ($token === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /TiranaSolidare/views/login.php?error=invalid_verification_link');
    exit();
}

$tokenHash = hash('sha256', $token);

try {
    $stmt = $pdo->prepare(
        'SELECT id_perdoruesi, verified, verification_token_expires
         FROM Perdoruesi
         WHERE email = ? AND verification_token_hash = ?
         LIMIT 1'
    );
    $stmt->execute([$email, $tokenHash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: /TiranaSolidare/views/login.php?error=invalid_verification_link');
        exit();
    }

    if ((int) ($user['verified'] ?? 0) === 1) {
        header('Location: /TiranaSolidare/views/login.php?success=email_already_verified');
        exit();
    }

    if (empty($user['verification_token_expires']) || strtotime($user['verification_token_expires']) < time()) {
        header('Location: /TiranaSolidare/views/login.php?error=verification_expired');
        exit();
    }

    $update = $pdo->prepare(
        'UPDATE Perdoruesi
         SET verified = 1,
             verification_token_hash = NULL,
             verification_token_expires = NULL
         WHERE id_perdoruesi = ?'
    );
    $update->execute([(int) $user['id_perdoruesi']]);

    header('Location: /TiranaSolidare/views/login.php?success=email_verified');
    exit();
} catch (Throwable $e) {
    error_log('Email verification failed: ' . $e->getMessage());
    header('Location: /TiranaSolidare/views/login.php?error=sql_error');
    exit();
}
