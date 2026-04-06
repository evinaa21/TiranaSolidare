<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');
$guardianEmail = trim($_GET['guardian'] ?? '');

if ($token === '' || $email === '' || $guardianEmail === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: /TiranaSolidare/views/login.php?error=invalid_guardian_consent_link');
    exit();
}

$tokenHash = hash('sha256', $token);

try {
    $update = $pdo->prepare(
        'UPDATE Perdoruesi
         SET guardian_consent_status = \'approved\',
             guardian_consent_token_hash = NULL,
             guardian_consent_token_expires = NULL,
             guardian_consent_verified_at = NOW()
         WHERE email = ?
           AND guardian_email = ?
           AND guardian_consent_status = \'pending\'
           AND guardian_consent_token_hash = ?
           AND guardian_consent_token_expires > NOW()'
    );
    $update->execute([$email, $guardianEmail, $tokenHash]);

    if ($update->rowCount() === 0) {
        $check = $pdo->prepare(
            'SELECT verified, birthdate, guardian_consent_status, guardian_consent_token_expires
             FROM Perdoruesi WHERE email = ? AND guardian_email = ? LIMIT 1'
        );
        $check->execute([$email, $guardianEmail]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if ($row && ($row['guardian_consent_status'] ?? '') === 'approved') {
            $successKey = ts_user_activation_state($row) === 'email_pending'
                ? 'guardian_consent_already_verified_email_pending'
                : 'guardian_consent_already_verified';
            header('Location: /TiranaSolidare/views/login.php?success=' . $successKey);
        } elseif ($row && !empty($row['guardian_consent_token_expires']) && strtotime($row['guardian_consent_token_expires']) < time()) {
            header('Location: /TiranaSolidare/views/login.php?error=guardian_consent_expired');
        } else {
            header('Location: /TiranaSolidare/views/login.php?error=invalid_guardian_consent_link');
        }
        exit();
    }

    $userStmt = $pdo->prepare('SELECT emri, verified, birthdate, guardian_consent_status FROM Perdoruesi WHERE email = ? LIMIT 1');
    $userStmt->execute([$email]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['emri' => 'Përdorues', 'verified' => 0];
    $activationState = ts_user_activation_state($user);

    send_notification_email(
        $email,
        $user['emri'] ?? 'Përdorues',
        'Pëlqimi prindëror u konfirmua',
        ($activationState === 'ready')
            ? 'Pëlqimi i prindit ose kujdestarit u regjistrua me sukses. Tani mund të kyçeni në llogarinë tuaj.'
            : 'Pëlqimi i prindit ose kujdestarit u regjistrua me sukses. Hapi i fundit është të verifikoni edhe email-in tuaj.',
        [
            'bypass_preferences' => true,
            'action_url' => '/views/login.php',
            'action_label' => 'Hap hyrjen',
        ]
    );

    header('Location: /TiranaSolidare/views/login.php?success=' . (($activationState === 'ready') ? 'guardian_consent_verified' : 'guardian_consent_verified_email_pending'));
    exit();
} catch (Throwable $e) {
    error_log('Guardian consent verification failed: ' . $e->getMessage());
    header('Location: /TiranaSolidare/views/login.php?error=sql_error');
    exit();
}