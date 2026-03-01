<?php
// actions/register_action.php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect = trim($_POST['redirect'] ?? '');
    $redirectParam = $redirect ? '&redirect=' . urlencode($redirect) : '';

    // CSRF validation
    if (!validate_csrf_token($_POST['_csrf_token'] ?? '')) {
        header("Location: /TiranaSolidare/views/register.php?error=csrf_expired" . $redirectParam);
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

    if (strlen($password) < 6) {
        header("Location: /TiranaSolidare/views/register.php?error=password_too_short" . $redirectParam);
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
        // 4. Hash Password (Q-06: Use PASSWORD_DEFAULT for best practice)
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // 5. Insert New User (Default role: Vullnetar)
        $sql_insert = "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise) VALUES (?, ?, ?, 'Vullnetar', 'Aktiv')";
        $stmt = $pdo->prepare($sql_insert);
        
        if ($stmt->execute([$emri, $email, $hashed_password])) {
            // U-02: Auto-login after registration
            $newId = (int) $pdo->lastInsertId();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newId;
            $_SESSION['emri'] = $emri;
            $_SESSION['roli'] = 'Vullnetar';
            $_SESSION['email'] = $email;

            // U-03: Redirect to original page or volunteer panel
            if ($redirect && is_safe_redirect($redirect)) {
                header("Location: $redirect");
            } else {
                header("Location: /TiranaSolidare/views/volunteer_panel.php?welcome=1");
            }
            exit();
        } else {
            header("Location: /TiranaSolidare/views/register.php?error=sql_error" . $redirectParam);
            exit();
        }
    }

} else {
    header("Location: /TiranaSolidare/views/register.php");
    exit();
}