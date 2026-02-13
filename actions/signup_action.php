<?php
// actions/signup_action.php
session_start();
require_once '../config/db.php';

if (isset($_POST['signup_submit'])) {
    
    // 1. Sanitize Inputs
    $emri = htmlspecialchars(trim($_POST['emri']));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 2. Validation
    if (empty($emri) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: ../views/signup.php?error=empty_fields");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: ../views/signup.php?error=invalid_email");
        exit();
    }

    if ($password !== $confirm_password) {
        header("Location: ../views/signup.php?error=password_mismatch");
        exit();
    }

    // 3. Check if Email Already Exists
    $sql_check = "SELECT id_perdoruesi FROM Perdoruesi WHERE email = ?";
    $stmt = $pdo->prepare($sql_check);
    
    if (!$stmt) {
        header("Location: ../views/signup.php?error=sql_error");
        exit();
    }
    
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        header("Location: ../views/signup.php?error=email_taken");
        exit();
    } else {
        // 4. Hash Password (Bcrypt)
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // 5. Insert New User
        // Default role is 'Vullnetar' and status is 'Aktiv'
        $sql_insert = "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise) VALUES (?, ?, ?, 'Vullnetar', 'Aktiv')";
        $stmt = $pdo->prepare($sql_insert);
        
        if ($stmt->execute([$emri, $email, $hashed_password])) {
            // Success! Redirect to login or show success message on signup page
            header("Location: ../views/signup.php?success=registered");
            exit();
        } else {
            header("Location: ../views/signup.php?error=sql_error");
            exit();
        }
    }

} else {
    // Prevent direct access to this file
    header("Location: ../views/signup.php");
    exit();
}