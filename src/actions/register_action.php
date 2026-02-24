<?php
// actions/register_action.php
session_start();
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 1. Sanitize Inputs
    $emri = htmlspecialchars(trim($_POST['emri']));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 2. Validation
    if (empty($emri) || empty($email) || empty($password) || empty($confirm_password)) {
        header("Location: /TiranaSolidare/views/register.php?error=empty_fields");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: /TiranaSolidare/views/register.php?error=invalid_email");
        exit();
    }

    if ($password !== $confirm_password) {
        header("Location: /TiranaSolidare/views/register.php?error=password_mismatch");
        exit();
    }

    // 3. Check if Email Already Exists
    $sql_check = "SELECT id_perdoruesi FROM Perdoruesi WHERE email = ?";
    $stmt = $pdo->prepare($sql_check);
    
    if (!$stmt) {
        header("Location: /TiranaSolidare/views/register.php?error=sql_error");
        exit();
    }
    
    $stmt->execute([$email]);
    
    if ($stmt->rowCount() > 0) {
        header("Location: /TiranaSolidare/views/register.php?error=email_taken");
        exit();
    } else {
        // 4. Hash Password (Bcrypt)
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        
        // 5. Insert New User (Default role: Vullnetar)
        $sql_insert = "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise) VALUES (?, ?, ?, 'Vullnetar', 'Aktiv')";
        $stmt = $pdo->prepare($sql_insert);
        
        if ($stmt->execute([$emri, $email, $hashed_password])) {
            header("Location: /TiranaSolidare/views/login.php?success=registered");
            exit();
        } else {
            header("Location: /TiranaSolidare/views/register.php?error=sql_error");
            exit();
        }
    }

} else {
    header("Location: /TiranaSolidare/views/register.php");
    exit();
}