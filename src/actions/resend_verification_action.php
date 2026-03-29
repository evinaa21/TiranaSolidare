<?php
/**
 * src/actions/resend_verification_action.php
 * ---------------------------------------------------
 * Handles the resend-verification-email form submission.
 * Always redirects to a generic success message to prevent
 * email enumeration.
 */
session_start();
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /TiranaSolidare/views/resend_verification.php');
    exit();
}

// CSRF check
if (!validate_csrf_token($_POST['_csrf_token'] ?? '')) {
    header('Location: /TiranaSolidare/views/resend_verification.php?error=csrf_expired');
    exit();
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /TiranaSolidare/views/resend_verification.php?error=invalid_email');
    exit();
}

// Rate limit: 3 attempts per hour per IP
if (!check_rate_limit('resend_verification', 3, 3600)) {
    header('Location: /TiranaSolidare/views/resend_verification.php?error=rate_limited');
    exit();
}

$stmt = $pdo->prepare(
    "SELECT id_perdoruesi, emri, verified, statusi_llogarise FROM Perdoruesi WHERE email = ? LIMIT 1"
);
$stmt->execute([$email]);
$user = $stmt->fetch();

// Only act if user exists, is unverified, and is active — silently skip otherwise (no enumeration)
if ($user && (int) $user['verified'] === 0 && $user['statusi_llogarise'] === 'active') {
    $plainToken = bin2hex(random_bytes(32));
    $tokenHash  = hash('sha256', $plainToken);
    $expiresAt  = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
    $verifyUrl  = app_base_url()
        . '/src/actions/verify_email.php?token=' . urlencode($plainToken)
        . '&email=' . urlencode($email);

    $pdo->prepare(
        "UPDATE Perdoruesi SET verification_token_hash = ?, verification_token_expires = ? WHERE id_perdoruesi = ?"
    )->execute([$tokenHash, $expiresAt, $user['id_perdoruesi']]);

    send_verification_email($email, $user['emri'], $verifyUrl);
}

// Always respond with the same message regardless of outcome
header('Location: /TiranaSolidare/views/resend_verification.php?success=email_sent');
exit();
