<?php
/**
 * api/auth.php
 * ---------------------------------------------------
 * Authentication API – login, register, logout, me
 *
 * POST   /api/auth.php?action=login      – Log in
 * POST   /api/auth.php?action=register   – Register
 * POST   /api/auth.php?action=logout     – Log out
 * GET    /api/auth.php?action=me         – Current user
 * ---------------------------------------------------
 */
require_once __DIR__ . '/api_helpers.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // ── LOGIN ──────────────────────────────────────
    case 'login':
        require_method('POST');
        $body = get_json_body();
        $errors = [];

        $email    = required_field($body, 'email', $errors);
        $password = required_field($body, 'password', $errors);

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Formati i email-it nuk është i vlefshëm.', 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM Perdoruesi WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['fjalekalimi'])) {
            json_error('Email ose fjalëkalimi i gabuar.', 401);
        }

        if ($user['statusi_llogarise'] === 'Bllokuar') {
            json_error('Llogaria juaj është bllokuar. Kontaktoni administratorin.', 403);
        }

        // Set session
        $_SESSION['user_id'] = $user['id_perdoruesi'];
        $_SESSION['emri']    = $user['emri'];
        $_SESSION['roli']    = $user['roli'];

        json_success([
            'id'    => (int) $user['id_perdoruesi'],
            'emri'  => $user['emri'],
            'email' => $user['email'],
            'roli'  => $user['roli'],
        ]);
        break;

    // ── REGISTER ───────────────────────────────────
    case 'register':
        require_method('POST');
        $body   = get_json_body();
        $errors = [];

        $emri             = required_field($body, 'emri', $errors);
        $email            = required_field($body, 'email', $errors);
        $password         = required_field($body, 'password', $errors);
        $confirm_password = required_field($body, 'confirm_password', $errors);

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Formati i email-it nuk është i vlefshëm.', 422);
        }

        if (strlen($password) < 6) {
            json_error('Fjalëkalimi duhet të jetë të paktën 6 karaktere.', 422);
        }

        if ($password !== $confirm_password) {
            json_error('Fjalëkalimet nuk përputhen.', 422);
        }

        // Check uniqueness
        $stmt = $pdo->prepare('SELECT id_perdoruesi FROM Perdoruesi WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            json_error('Ky email është i regjistruar tashmë.', 409);
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise)
             VALUES (?, ?, ?, 'Vullnetar', 'Aktiv')"
        );
        $stmt->execute([$emri, $email, $hashed]);

        $newId = (int) $pdo->lastInsertId();

        json_success([
            'id'    => $newId,
            'emri'  => $emri,
            'email' => $email,
            'roli'  => 'Vullnetar',
        ], 201);
        break;

    // ── LOGOUT ─────────────────────────────────────
    case 'logout':
        require_method('POST');
        session_unset();
        session_destroy();
        json_success(['message' => 'U shkëputët me sukses.']);
        break;

    // ── ME (current user) ──────────────────────────
    case 'me':
        require_method('GET');
        $user = require_auth();

        $stmt = $pdo->prepare(
            'SELECT id_perdoruesi, emri, email, roli, statusi_llogarise, krijuar_me
             FROM Perdoruesi WHERE id_perdoruesi = ?'
        );
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        if (!$profile) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        json_success($profile);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: login, register, logout, me.', 400);
}
