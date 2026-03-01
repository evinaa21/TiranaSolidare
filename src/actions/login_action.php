<?php
// actions/login_action.php
session_start();
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
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
        if ($user['statusi_llogarise'] === 'Bllokuar') {
            header("Location: /TiranaSolidare/views/login.php?error=account_blocked" . ($redirect ? '&redirect=' . urlencode($redirect) : ''));
            exit();
        }
        if ($user['statusi_llogarise'] === 'Çaktivizuar') {
            header("Location: /TiranaSolidare/views/login.php?error=account_deactivated" . ($redirect ? '&redirect=' . urlencode($redirect) : ''));
            exit();
        }

        // SUCCESS: Set session variables
        $_SESSION['user_id'] = $user['id_perdoruesi'];
        $_SESSION['emri'] = $user['emri'];
        $_SESSION['roli'] = $user['roli'];
        $_SESSION['email'] = $user['email'];

        // Redirect to original page or appropriate dashboard
        if ($redirect && strpos($redirect, '/TiranaSolidare/') === 0) {
            header("Location: $redirect");
        } elseif ($user['roli'] === 'Admin') {
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