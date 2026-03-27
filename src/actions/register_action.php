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
    if (!check_rate_limit('register_form', 3, 1800)) {
        header("Location: /TiranaSolidare/views/register.php?error=rate_limited" . $redirectParam);
        exit();
    }

    // 1. Sanitize Inputs (C-03: Do NOT htmlspecialchars here — only on output)
    $emri = trim($_POST['emri']);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 2. Validation
    if (empty($emri) || empty($email) || empty($password) || empty($confirm_password)) {
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

        $verifyUrl = app_base_url() . '/TiranaSolidare/src/actions/verify_email.php?token=' . urlencode($plainToken) . '&email=' . urlencode($email);

        try {
            $pdo->beginTransaction();

            $sql_insert = "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise, verified, profile_public, profile_color, verification_token_hash, verification_token_expires) VALUES (?, ?, ?, 'volunteer', 'active', 0, 0, 'emerald', ?, ?)";
            $stmt = $pdo->prepare($sql_insert);
            $stmt->execute([$emri, $email, $hashed_password, $tokenHash, $expiresAt]);

            $mailSent = send_verification_email($email, $emri, $verifyUrl);
            if (!$mailSent) {
                $pdo->rollBack();
                header("Location: /TiranaSolidare/views/register.php?error=verification_email_failed" . $redirectParam);
                exit();
            }

            $pdo->commit();
            header("Location: /TiranaSolidare/views/login.php?success=verify_email_sent");
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