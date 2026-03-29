<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /TiranaSolidare/views/login.php');
    exit();
}

if (!validate_csrf_token($_POST['_csrf_token'] ?? '')) {
    header('Location: /TiranaSolidare/views/forgot_password.php?error=csrf_expired');
    exit();
}

if (!check_rate_limit('forgot_password', 5, 900)) {
    header('Location: /TiranaSolidare/views/forgot_password.php?error=rate_limited');
    exit();
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /TiranaSolidare/views/forgot_password.php?error=invalid_email');
    exit();
}

try {
    $stmt = $pdo->prepare('SELECT id_perdoruesi, emri, verified, statusi_llogarise FROM Perdoruesi WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Only send a reset link to verified, non-blocked accounts.
    // Unverified accounts cannot log in anyway, so resetting their password serves no purpose
    // and would allow bypassing the email-verification requirement.
    if ($user && (int) ($user['verified'] ?? 0) === 1 && !in_array($user['statusi_llogarise'] ?? '', ['blocked', 'deactivated'], true)) {
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

        $resetUrl = app_base_url() . '/views/reset_password.php?token=' . urlencode($plainToken) . '&email=' . urlencode($email);
        $update = $pdo->prepare('UPDATE Perdoruesi SET password_reset_token_hash = ?, password_reset_token_expires = ? WHERE id_perdoruesi = ?');
        $update->execute([$tokenHash, $expiresAt, (int) $user['id_perdoruesi']]);

        send_password_reset_email($email, $user['emri'] ?? 'Volunteer', $resetUrl);
    }

    header('Location: /TiranaSolidare/views/forgot_password.php?success=email_sent');
    exit();
} catch (Throwable $e) {
    error_log('Forgot password flow failed: ' . $e->getMessage());
    header('Location: /TiranaSolidare/views/forgot_password.php?error=sql_error');
    exit();
}
