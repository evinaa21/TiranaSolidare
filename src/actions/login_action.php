<?php
// actions/login_action.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // CSRF validation
    if (!validate_csrf_token($_POST['_csrf_token'] ?? '')) {
        header("Location: /TiranaSolidare/views/login.php?error=csrf_expired");
        exit();
    }

    // Rate limiting: max 5 login attempts per 15 minutes
    if (!check_rate_limit('login', 100, 900)) {
        header("Location: /TiranaSolidare/views/login.php?error=rate_limited");
        exit();
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $redirect = trim($_POST['redirect'] ?? '');

    if (empty($email) || empty($password)) {
        header("Location: /TiranaSolidare/views/login.php?error=empty_fields" . ($redirect ? '&redirect=' . urlencode($redirect) : ''));
        exit();
    }

    // Prepare SQL to prevent injection
    $sql = "SELECT * FROM Perdoruesi WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify user and password
    if ($user && password_verify($password, $user['fjalekalimi'])) {
        // Check if account is blocked or deactivated
        $accountStatus = ts_normalize_value($user['statusi_llogarise'] ?? '');
        if ($accountStatus === 'blocked') {
            header("Location: /TiranaSolidare/views/login.php?error=account_blocked" . ($redirect ? '&redirect=' . urlencode($redirect) : ''));
            exit();
        }
        if ($accountStatus === 'deactivated') {
            header("Location: /TiranaSolidare/views/login.php?error=account_deactivated" . ($redirect ? '&redirect=' . urlencode($redirect) : ''));
            exit();
        }

        $activationErrorKey = ts_guardian_consent_error_key($user);
        if ($activationErrorKey !== null) {
            header("Location: /TiranaSolidare/views/login.php?error=" . $activationErrorKey . ($redirect ? '&redirect=' . urlencode($redirect) : ''));
            exit();
        }

        // Regenerate session to prevent session fixation (H-10)
        session_regenerate_id(true);
        // Rotate CSRF token so the pre-login token cannot be reused post-login
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // SUCCESS: Set session variables
        $_SESSION['user_id'] = $user['id_perdoruesi'];
        $_SESSION['emri'] = $user['emri'];
        $_SESSION['roli'] = ts_normalize_value($user['roli'] ?? 'volunteer');
        $_SESSION['email'] = $user['email'];
        $_SESSION['organization_name'] = (string) ($user['organization_name'] ?? '');
        $_SESSION['profile_color'] = $user['profile_color'] ?? 'emerald';
        $_SESSION['profile_picture'] = (string) ($user['profile_picture'] ?? '');

        // Safe redirect validation (H-02)
        $normalizedRole = ts_normalize_value($user['roli'] ?? 'volunteer');
        if ($redirect && is_safe_redirect($redirect)) {
            header("Location: $redirect");
        } elseif (ts_is_dashboard_role_value($normalizedRole)) {
            header("Location: /TiranaSolidare/views/dashboard.php");
        } else {
            header("Location: /TiranaSolidare/views/volunteer_panel.php");
        }
        exit();
    } else {
        // FAILURE: Redirect back with error
        header("Location: /TiranaSolidare/views/login.php?error=wrong_credentials" . ($redirect ? '&redirect=' . urlencode($redirect) : ''));
        exit();
    }
} else {
    // If someone tries to access this file directly without POST
    header("Location: /TiranaSolidare/views/login.php");
    exit();
}