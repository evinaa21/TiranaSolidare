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
    // Atomically consume the token in a single query — prevents TOCTOU race conditions where
    // two concurrent requests with the same token could both SELECT before either UPDATEs.
    $update = $pdo->prepare(
        'UPDATE Perdoruesi
         SET verified = 1,
             verification_token_hash = NULL,
             verification_token_expires = NULL
         WHERE email = ?
           AND verification_token_hash = ?
           AND verified = 0
           AND verification_token_expires > NOW()'
    );
    $update->execute([$email, $tokenHash]);

    if ($update->rowCount() === 0) {
        // Could be: wrong token, already verified, or expired — distinguish for UX
        $check = $pdo->prepare('SELECT verified, verification_token_expires, birthdate, guardian_consent_status FROM Perdoruesi WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if ($row && (int) ($row['verified'] ?? 0) === 1) {
            $successKey = ts_user_activation_state($row) === 'guardian_pending'
                ? 'email_already_verified_guardian_pending'
                : 'email_already_verified';
            header('Location: /TiranaSolidare/views/login.php?success=' . $successKey);
        } elseif ($row && !empty($row['verification_token_expires']) && strtotime($row['verification_token_expires']) < time()) {
            header('Location: /TiranaSolidare/views/login.php?error=verification_expired');
        } else {
            header('Location: /TiranaSolidare/views/login.php?error=invalid_verification_link');
        }
        exit();
    }

    $stateStmt = $pdo->prepare('SELECT verified, birthdate, guardian_consent_status FROM Perdoruesi WHERE email = ? LIMIT 1');
    $stateStmt->execute([$email]);
    $userState = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: ['verified' => 1];
    $successKey = ts_user_activation_state($userState) === 'guardian_pending'
        ? 'email_verified_guardian_pending'
        : 'email_verified';

    header('Location: /TiranaSolidare/views/login.php?success=' . $successKey);
    exit();
} catch (Throwable $e) {
    error_log('Email verification failed: ' . $e->getMessage());
    header('Location: /TiranaSolidare/views/login.php?error=sql_error');
    exit();
}
