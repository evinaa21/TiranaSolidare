<?php
// actions/login_action.php
session_start();
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        header("Location: ../views/login.php?error=empty_fields");
        exit();
    }

    // Prepare SQL to prevent injection
    $sql = "SELECT * FROM Perdoruesi WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verify user and password
    if ($user && password_verify($password, $user['fjalekalimi'])) {
        // SUCCESS: Set session variables
        $_SESSION['user_id'] = $user['id_perdoruesi'];
        $_SESSION['emri'] = $user['emri'];
        $_SESSION['roli'] = $user['roli'];

        // Redirect to dashboard
        header("Location: ../views/dashboard.php");
        exit();
    } else {
        // FAILURE: Redirect back with error
        header("Location: ../views/login.php?error=wrong_credentials");
        exit();
    }
} else {
    // If someone tries to access this file directly without POST
    header("Location: ../views/login.php");
    exit();
}