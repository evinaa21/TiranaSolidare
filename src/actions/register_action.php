<?php
// actions/register_action.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect = trim($_POST['redirect'] ?? '');
    $redirectParam = $redirect ? '&redirect=' . urlencode($redirect) : '';

    // CSRF validation
    if (!validate_csrf_token($_POST['_csrf_token'] ?? '')) {
        header("Location: /TiranaSolidare/views/register.php?error=csrf_expired" . $redirectParam);
        exit();
    }

    // Privacy consent check
    if (empty($_POST['privacy_consent'])) {
        header("Location: /TiranaSolidare/views/register.php?error=no_consent" . $redirectParam);
        exit();
    }

    // Rate limiting: max 3 registrations per 30 minutes
    if (!check_rate_limit('register', 3, 1800)) {
        header("Location: /TiranaSolidare/views/register.php?error=rate_limited" . $redirectParam);
        exit();
    }

    // 1. Sanitize Inputs (C-03: Do NOT htmlspecialchars here — only on output)
    $emri = trim($_POST['emri']);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $birthdate = trim((string) ($_POST['birthdate'] ?? ''));
    $guardianName = trim((string) ($_POST['guardian_name'] ?? ''));
    $guardianEmail = filter_input(INPUT_POST, 'guardian_email', FILTER_SANITIZE_EMAIL);
    $guardianRelation = trim((string) ($_POST['guardian_relation'] ?? ''));

    // 2. Validation
    if (empty($emri) || empty($email) || empty($password) || empty($confirm_password) || $birthdate === '') {
        header("Location: /TiranaSolidare/views/register.php?error=empty_fields" . $redirectParam);
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: /TiranaSolidare/views/register.php?error=invalid_email" . $redirectParam);
        exit();
    }

    if ($lenErr = validate_length($emri, 2, 100, 'emri')) {
        header("Location: /TiranaSolidare/views/register.php?error=invalid_name" . $redirectParam);
        exit();
    }

    if ($password !== $confirm_password) {
        header("Location: /TiranaSolidare/views/register.php?error=password_mismatch" . $redirectParam);
        exit();
    }

    if ($passwordError = validate_password_strength($password)) {
        header("Location: /TiranaSolidare/views/register.php?error=password_weak" . $redirectParam);
        exit();
    }

    if (!ts_birthdate_is_reasonable($birthdate)) {
        header("Location: /TiranaSolidare/views/register.php?error=invalid_birthdate" . $redirectParam);
        exit();
    }

    $requiresGuardianConsent = ts_birthdate_requires_guardian_consent($birthdate);
    if ($requiresGuardianConsent) {
        if ($guardianName === '' || empty($guardianEmail) || $guardianRelation === '') {
            header("Location: /TiranaSolidare/views/register.php?error=guardian_details_required" . $redirectParam);
            exit();
        }

        if (validate_length($guardianName, 2, 100, 'guardian_name')) {
            header("Location: /TiranaSolidare/views/register.php?error=invalid_guardian_name" . $redirectParam);
            exit();
        }

        if (!filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
            header("Location: /TiranaSolidare/views/register.php?error=invalid_guardian_email" . $redirectParam);
            exit();
        }

        if (strcasecmp($guardianEmail, $email) === 0) {
            header("Location: /TiranaSolidare/views/register.php?error=guardian_email_same_as_user" . $redirectParam);
            exit();
        }

        if (validate_length($guardianRelation, 2, 60, 'guardian_relation')) {
            header("Location: /TiranaSolidare/views/register.php?error=invalid_guardian_relation" . $redirectParam);
            exit();
        }
    } else {
        $guardianName = '';
        $guardianEmail = null;
        $guardianRelation = '';
    }

    // 3. Check if Email Already Exists
    $sql_check = "SELECT id_perdoruesi FROM Perdoruesi WHERE email = ?";
    $stmt = $pdo->prepare($sql_check);
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        header("Location: /TiranaSolidare/views/register.php?error=email_taken" . $redirectParam);
        exit();
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
        $guardianPlainToken = null;
        $guardianTokenHash = null;
        $guardianExpiresAt = null;
        $guardianStatus = $requiresGuardianConsent ? 'pending' : 'not_required';

        if ($requiresGuardianConsent) {
            $guardianPlainToken = bin2hex(random_bytes(32));
            $guardianTokenHash = hash('sha256', $guardianPlainToken);
            $guardianExpiresAt = (new DateTimeImmutable('+' . TS_GUARDIAN_CONSENT_EXPIRY_HOURS . ' hours'))->format('Y-m-d H:i:s');
        }

        $verifyUrl = app_base_url() . '/src/actions/verify_email.php?token=' . urlencode($plainToken) . '&email=' . urlencode($email);
        $guardianVerifyUrl = $requiresGuardianConsent
            ? app_base_url() . '/src/actions/verify_guardian_consent.php?token=' . urlencode((string) $guardianPlainToken) . '&email=' . urlencode($email) . '&guardian=' . urlencode((string) $guardianEmail)
            : '';

        try {
            $pdo->beginTransaction();

            $sql_insert = "INSERT INTO Perdoruesi (emri, email, birthdate, fjalekalimi, roli, statusi_llogarise, verified, profile_public, profile_color, verification_token_hash, verification_token_expires, guardian_name, guardian_email, guardian_relation, guardian_consent_status, guardian_consent_token_hash, guardian_consent_token_expires, guardian_consent_verified_at) VALUES (?, ?, ?, ?, 'volunteer', 'active', 0, 0, 'emerald', ?, ?, ?, ?, ?, ?, ?, ?, NULL)";
            $stmt = $pdo->prepare($sql_insert);
            $stmt->execute([
                $emri,
                $email,
                $birthdate,
                $hashed_password,
                $tokenHash,
                $expiresAt,
                $guardianName !== '' ? $guardianName : null,
                $guardianEmail,
                $guardianRelation !== '' ? $guardianRelation : null,
                $guardianStatus,
                $guardianTokenHash,
                $guardianExpiresAt,
            ]);

            $pdo->commit();

            if (!send_verification_email($email, $emri, $verifyUrl)) {
                error_log('Verification email send failed immediately for registration: ' . $email);
            }

            if ($requiresGuardianConsent && !send_guardian_consent_email((string) $guardianEmail, $guardianName, $emri, $email, $guardianVerifyUrl, $guardianRelation)) {
                error_log('Guardian consent email send failed immediately for registration: ' . $email);
            }

            $successKey = $requiresGuardianConsent ? 'verify_email_and_guardian_sent' : 'verify_email_sent';
            header("Location: /TiranaSolidare/views/login.php?success=" . $successKey . $redirectParam);
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Registration failed: ' . $e->getMessage());
            header("Location: /TiranaSolidare/views/register.php?error=sql_error" . $redirectParam);
            exit();
        }
    }

} else {
    header("Location: /TiranaSolidare/views/register.php");
    exit();
}