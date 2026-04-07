<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ts_app_path('views/resend_guardian_consent.php'));
    exit();
}

if (!validate_csrf_token($_POST['_csrf_token'] ?? '')) {
    header('Location: ' . ts_app_path('views/resend_guardian_consent.php?error=csrf_expired'));
    exit();
}

$email = trim((string) ($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . ts_app_path('views/resend_guardian_consent.php?error=invalid_email'));
    exit();
}

if (!check_rate_limit('resend_guardian_consent', 3, 3600)) {
    header('Location: ' . ts_app_path('views/resend_guardian_consent.php?error=rate_limited'));
    exit();
}

$stmt = $pdo->prepare(
    'SELECT id_perdoruesi, emri, email, birthdate, guardian_name, guardian_email, guardian_relation, guardian_consent_status, statusi_llogarise
     FROM Perdoruesi WHERE email = ? LIMIT 1'
);
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (
    $user
    && ts_normalize_value($user['statusi_llogarise'] ?? '') === 'active'
    && ts_user_requires_guardian_consent($user)
    && ts_guardian_consent_status($user['guardian_consent_status'] ?? null) !== 'approved'
    && filter_var($user['guardian_email'] ?? '', FILTER_VALIDATE_EMAIL)
) {
    $plainToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $plainToken);
    $expiresAt = (new DateTimeImmutable('+' . TS_GUARDIAN_CONSENT_EXPIRY_HOURS . ' hours'))->format('Y-m-d H:i:s');
    $guardianVerifyUrl = app_base_url()
        . '/src/actions/verify_guardian_consent.php?token=' . urlencode($plainToken)
        . '&email=' . urlencode($email)
        . '&guardian=' . urlencode((string) $user['guardian_email']);

    $pdo->prepare(
        "UPDATE Perdoruesi
         SET guardian_consent_status = 'pending',
             guardian_consent_token_hash = ?,
             guardian_consent_token_expires = ?,
             guardian_consent_verified_at = NULL
         WHERE id_perdoruesi = ?"
    )->execute([$tokenHash, $expiresAt, $user['id_perdoruesi']]);

    $guardianName = trim((string) ($user['guardian_name'] ?? ''));
    if ($guardianName === '') {
        $guardianName = 'Prind/Kujdestar';
    }

    send_guardian_consent_email(
        (string) $user['guardian_email'],
        $guardianName,
        (string) ($user['emri'] ?? 'Përdorues'),
        $email,
        $guardianVerifyUrl,
        (string) ($user['guardian_relation'] ?? '')
    );
}

header('Location: ' . ts_app_path('views/resend_guardian_consent.php?success=email_sent'));
exit();