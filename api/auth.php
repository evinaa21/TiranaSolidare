<?php
/**
 * api/auth.php
 * ---------------------------------------------------
 * Authentication API – login, register, logout, me
 *
 * POST   /api/auth.php?action=login      – Log in
 * POST   /api/auth.php?action=register   – Register
 * POST   /api/auth.php?action=logout     – Log out
 * PUT    /api/auth.php?action=change_password – Change password
 * GET    /api/auth.php?action=me         – Current user
 * ---------------------------------------------------
 */
require_once __DIR__ . '/helpers.php';

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

        // Rate limit: max 5 login attempts per 15 minutes (checked BEFORE DB lookup)
        if (!check_rate_limit('login', 5, 900)) {
            json_error('Shumë tentativa hyrjeje. Provoni përsëri pas disa minutash.', 429);
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

        if ($user['statusi_llogarise'] === 'Çaktivizuar') {
            json_error('Llogaria juaj është çaktivizuar. Kontaktoni administratorin për ta riaktivizuar.', 403);
        }

        if ((int) ($user['verified'] ?? 0) !== 1) {
            json_error('Duhet të konfirmoni email-in para hyrjes.', 403);
        }

        // Regenerate session ID to prevent fixation attacks
        session_regenerate_id(true);

        // Set session (including email)
        $_SESSION['user_id'] = $user['id_perdoruesi'];
        $_SESSION['emri']    = $user['emri'];
        $_SESSION['roli']    = $user['roli'];
        $_SESSION['email']   = $user['email'];

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

        // Input length validation
        if ($lenErr = validate_length($emri, 2, 100, 'emri')) {
            json_error($lenErr, 422);
        }

        if ($passwordError = validate_password_strength($password)) {
            json_error($passwordError, 422);
        }

        if ($password !== $confirm_password) {
            json_error('Fjalëkalimet nuk përputhen.', 422);
        }

        // Rate limit: max 3 registrations per 30 minutes
        if (!check_rate_limit('register', 3, 1800)) {
            json_error('Shumë tentativa regjistrimi. Provoni përsëri më vonë.', 429);
        }

        // Check uniqueness
        $stmt = $pdo->prepare('SELECT id_perdoruesi FROM Perdoruesi WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->rowCount() > 0) {
            json_error('Ky email është i regjistruar tashmë.', 409);
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
        $verifyUrl = app_base_url() . '/TiranaSolidare/src/actions/verify_email.php?token=' . urlencode($plainToken) . '&email=' . urlencode($email);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise, verified, verification_token_hash, verification_token_expires)
                 VALUES (?, ?, ?, 'Vullnetar', 'Aktiv', 0, ?, ?)"
            );
            $stmt->execute([$emri, $email, $hashed, $tokenHash, $expiresAt]);

            if (!send_verification_email($email, $emri, $verifyUrl)) {
                $pdo->rollBack();
                json_error('Nuk u dërgua email-i i verifikimit. Kontrolloni konfigurimin SMTP.', 500);
            }

            $newId = (int) $pdo->lastInsertId();
            $pdo->commit();

            json_success([
                'id'      => $newId,
                'emri'    => $emri,
                'email'   => $email,
                'roli'    => 'Vullnetar',
                'message' => 'Llogaria u krijua. Konfirmoni email-in para hyrjes.',
            ], 201);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('API register failed: ' . $e->getMessage());
            json_error('Ndodhi një gabim gjatë regjistrimit.', 500);
        }
        break;

    // ── LOGOUT ─────────────────────────────────────
    case 'logout':
        require_method('POST');
        session_unset();
        session_destroy();
        json_success(['message' => 'U shkëputët me sukses.']);
        break;

         // ── CHANGE PASSWORD ──────────────────────────
    case 'change_password':
        require_method('PUT');
        $user   = require_auth();
        $body   = get_json_body();
        $errors = [];

        $currentPassword  = required_field($body, 'current_password', $errors);
        $newPassword      = required_field($body, 'new_password', $errors);
        $confirmPassword  = required_field($body, 'confirm_password', $errors);

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        if ($passwordError = validate_password_strength($newPassword)) {
            json_error($passwordError, 422);
        }

        if ($newPassword !== $confirmPassword) {
            json_error('Fjalëkalimet nuk përputhen.', 422);
        }

        $stmt = $pdo->prepare('SELECT fjalekalimi FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$user['id']]);
        $existingHash = $stmt->fetchColumn();

        if ($existingHash === false) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        if (!password_verify($currentPassword, $existingHash)) {
            json_error('Fjalëkalimi aktual është i pasaktë.', 401);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $update = $pdo->prepare('UPDATE Perdoruesi SET fjalekalimi = ? WHERE id_perdoruesi = ?');
        $update->execute([$newHash, $user['id']]);

        json_success(['message' => 'Fjalëkalimi u përditësua me sukses.']);
        break;

    // ── ME (current user) ──────────────────────────
    case 'me':
        require_method('GET');
        $user = require_auth();

        $stmt = $pdo->prepare(
            'SELECT id_perdoruesi, emri, email, bio, profile_picture, profile_public, roli, statusi_llogarise, krijuar_me
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
        json_error('Veprim i panjohur. Përdorni: login, register, logout, change_password, me.', 400);
}
