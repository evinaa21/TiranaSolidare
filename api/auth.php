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
 * PUT    /api/auth.php?action=change_email – Change email
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

        if ($user['statusi_llogarise'] === 'blocked') {
            json_error('Llogaria juaj është bllokuar. Kontaktoni administratorin.', 403);
        }

        if ($user['statusi_llogarise'] === 'deactivated') {
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
        $_SESSION['roli']    = ts_normalize_value($user['roli']);
        $_SESSION['email']   = $user['email'];
        $_SESSION['profile_color']   = $user['profile_color'] ?? 'emerald';
        $_SESSION['profile_picture'] = (string) ($user['profile_picture'] ?? '');

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

        // Privacy consent required (mirrors form-based register)
        if (empty($body['privacy_consent'])) {
            json_error('Duhet të pranoni Politikën e Privatësisë për të vazhduar.', 422);
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
        $verifyUrl = app_base_url() . '/src/actions/verify_email.php?token=' . urlencode($plainToken) . '&email=' . urlencode($email);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                 "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise, verified, profile_public, profile_color, verification_token_hash, verification_token_expires)
                VALUES (?, ?, ?, 'volunteer', 'active', 0, 0, 'emerald', ?, ?)"
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
                'roli'    => 'volunteer',
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

        // Rate limit per-user: 5 attempts per 15 minutes (prevents brute-forcing own password)
        if (!check_rate_limit('change_password_' . $user['id'], 5, 900)) {
            json_error('Shumë tentativa. Provoni përsëri pas 15 minutash.', 429);
        }

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

        // Update password and record timestamp so stale sessions on other devices are invalidated
        $update = $pdo->prepare('UPDATE Perdoruesi SET fjalekalimi = ?, password_changed_at = NOW() WHERE id_perdoruesi = ?');
        $update->execute([$newHash, $user['id']]);

        // Regenerate the current session ID so the old cookie is no longer valid
        session_regenerate_id(true);

        // Security email: alert user that their password was changed
        $userEmailStmt = $pdo->prepare('SELECT emri, email FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $userEmailStmt->execute([$user['id']]);
        $userRecord = $userEmailStmt->fetch();
        if ($userRecord && filter_var($userRecord['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            send_notification_email(
                $userRecord['email'],
                $userRecord['emri'],
                'Fjalëkalimi juaj u ndryshua — Tirana Solidare',
                'Fjalëkalimi i llogarisë suaj në Tirana Solidare u ndryshua me sukses. Nëse nuk e keni bërë ju,.kontaktoni menjëherë administratorin ose ndryshoni fjalëkalimin.'
            );
        }

        json_success(['message' => 'Fjalëkalimi u përditësua me sukses.']);
        break;

    // ── CHANGE EMAIL ─────────────────────────────
    case 'change_email':
        require_method('PUT');
        $user = require_auth();

        // Rate limit per-user: 5 attempts per 15 minutes
        if (!check_rate_limit('change_email_' . $user['id'], 5, 900)) {
            json_error('Shumë tentativa. Provoni përsëri pas 15 minutash.', 429);
        }

        $body = get_json_body();
        $errors = [];

        $newEmail = required_field($body, 'new_email', $errors);
        $confirmEmail = required_field($body, 'confirm_email', $errors);
        $currentPassword = required_field($body, 'current_password', $errors);

        if (!empty($errors)) {
            json_error('Të dhëna të pavlefshme.', 422, $errors);
        }

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            json_error('Formati i email-it nuk është i vlefshëm.', 422);
        }

        if (mb_strtolower($newEmail) !== mb_strtolower($confirmEmail)) {
            json_error('Email-et nuk përputhen.', 422);
        }

        $currentUserStmt = $pdo->prepare('SELECT email, fjalekalimi FROM Perdoruesi WHERE id_perdoruesi = ? LIMIT 1');
        $currentUserStmt->execute([$user['id']]);
        $currentUser = $currentUserStmt->fetch();

        if (!$currentUser) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        if (!password_verify($currentPassword, (string) $currentUser['fjalekalimi'])) {
            json_error('Fjalëkalimi aktual është i pasaktë.', 401);
        }

        if (mb_strtolower((string) $currentUser['email']) === mb_strtolower($newEmail)) {
            json_error('Ky është tashmë email-i aktual.', 422);
        }

        $checkExistsStmt = $pdo->prepare('SELECT id_perdoruesi FROM Perdoruesi WHERE email = ? AND id_perdoruesi <> ? LIMIT 1');
        $checkExistsStmt->execute([$newEmail, $user['id']]);
        if ($checkExistsStmt->fetch()) {
            json_error('Ky email është i përdorur nga një llogari tjetër.', 409);
        }

        // Atomically update email + set verified=0 + store token, then send confirmation
        try {
            $pdo->beginTransaction();

            $plainToken = bin2hex(random_bytes(32));
            $tokenHash  = hash('sha256', $plainToken);
            $expiresAt  = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
            $verifyUrl  = app_base_url() . '/src/actions/verify_email.php?token=' . urlencode($plainToken) . '&email=' . urlencode($newEmail);

            $updateStmt = $pdo->prepare(
                'UPDATE Perdoruesi SET email = ?, verified = 0, verification_token_hash = ?, verification_token_expires = ? WHERE id_perdoruesi = ?'
            );
            $updateStmt->execute([$newEmail, $tokenHash, $expiresAt, $user['id']]);

            if (!send_verification_email($newEmail, $_SESSION['emri'] ?? 'Volunteer', $verifyUrl)) {
                $pdo->rollBack();
                json_error('Konfirmimi i email-it nuk u dërgua. Provoni përsëri.', 500);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('change_email failed: ' . $e->getMessage());
            json_error('Gabim gjatë ndryshimit të email-it.', 500);
        }

        // Destroy the current session — user must log in again after verifying the new address
        session_unset();
        session_destroy();

        json_success([
            'message'              => 'Email-i u përditësua. Konfirmoni adresën e re para hyrjes.',
            'requires_verification' => true,
        ]);
        break;

    // ── ME (current user) ──────────────────────────
    case 'me':
        require_method('GET');
        $user = require_auth();

        $stmt = $pdo->prepare(
            'SELECT id_perdoruesi, emri, email, bio, profile_picture, profile_public, profile_color, email_notifications, roli, statusi_llogarise, krijuar_me
             FROM Perdoruesi WHERE id_perdoruesi = ?'
        );
        $stmt->execute([$user['id']]);
        $profile = $stmt->fetch();

        if (!$profile) {
            json_error('Përdoruesi nuk u gjet.', 404);
        }

        json_success($profile);
        break;

    // ── DELETE ACCOUNT ──────────────────────────
    case 'delete_account':
        require_method('DELETE');
        $user = require_auth();
        $body = get_json_body();

        $password = $body['password'] ?? '';
        if (empty($password)) {
            json_error('Fjalëkalimi është i nevojshëm për konfirmimin e fshirjes.', 422);
        }

        // Verify password
        $stmt = $pdo->prepare('SELECT fjalekalimi FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$user['id']]);
        $existingHash = $stmt->fetchColumn();

        if (!$existingHash || !password_verify($password, $existingHash)) {
            json_error('Fjalëkalimi është i pasaktë.', 401);
        }

        if (is_admin_role($user['roli'])) {
            json_error('Administratorët nuk mund të fshijnë llogarinë e tyre. Kontaktoni një administrator tjetër.', 403);
        }

        try {
            $pdo->beginTransaction();

            // Delete user's notifications
            $pdo->prepare('DELETE FROM Njoftimi WHERE id_perdoruesi = ?')->execute([$user['id']]);

            // Delete user's applications
            $pdo->prepare('DELETE FROM Aplikimi WHERE id_perdoruesi = ?')->execute([$user['id']]);
            $pdo->prepare('DELETE FROM Aplikimi_Kerkese WHERE id_perdoruesi = ?')->execute([$user['id']]);

            // Delete OTHER users' applications to this user's help requests (FK safety)
            $pdo->prepare(
                'DELETE FROM Aplikimi_Kerkese WHERE id_kerkese_ndihme IN
                 (SELECT id_kerkese_ndihme FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?)'
            )->execute([$user['id']]);

            // Delete user's help requests
            $pdo->prepare('DELETE FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?')->execute([$user['id']]);

            // Orphan events created by this user rather than deleting them (they may have
            // other volunteers approved/applied). Setting id_perdoruesi = NULL is safe
            // because the column is nullable in the schema.
            $pdo->prepare('UPDATE Eventi SET id_perdoruesi = NULL WHERE id_perdoruesi = ?')->execute([$user['id']]);

            // Delete user's messages
            $pdo->prepare('DELETE FROM Mesazhi WHERE derguesi_id = ? OR marruesi_id = ?')->execute([$user['id'], $user['id']]);

            // Delete the user
            $pdo->prepare('DELETE FROM Perdoruesi WHERE id_perdoruesi = ?')->execute([$user['id']]);

            $pdo->commit();

            // Destroy session
            session_unset();
            session_destroy();

            json_success(['message' => 'Llogaria juaj u fshi përfundimisht.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('delete_account failed: ' . $e->getMessage());
            json_error('Gabim gjatë fshirjes së llogarise.', 500);
        }
        break;

    // ── RESEND VERIFICATION EMAIL ────────────────
    case 'resend_verification':
        require_method('POST');
        $body  = get_json_body();
        $email = trim((string) ($body['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Formati i email-it nuk është i vlefshëm.', 422);
        }

        // Rate limit: 3 per hour per IP
        if (!check_rate_limit('resend_verification', 3, 3600)) {
            json_error('Shumë tentativa. Provoni përsëri pas disa minutash.', 429);
        }

        $stmt = $pdo->prepare(
            "SELECT id_perdoruesi, emri, verified, statusi_llogarise FROM Perdoruesi WHERE email = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $uvUser = $stmt->fetch();

        if ($uvUser && (int) $uvUser['verified'] === 0 && $uvUser['statusi_llogarise'] === 'active') {
            $plainToken = bin2hex(random_bytes(32));
            $tokenHash  = hash('sha256', $plainToken);
            $expiresAt  = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');
            $verifyUrl  = app_base_url()
                . '/src/actions/verify_email.php?token=' . urlencode($plainToken)
                . '&email=' . urlencode($email);

            $pdo->prepare(
                "UPDATE Perdoruesi SET verification_token_hash = ?, verification_token_expires = ? WHERE id_perdoruesi = ?"
            )->execute([$tokenHash, $expiresAt, $uvUser['id_perdoruesi']]);

            send_verification_email($email, $uvUser['emri'], $verifyUrl);
        }

        // Always return success — prevents email enumeration
        json_success(['message' => 'Nëse ky email është i paverifikuar, do të marrësh një link konfirmimi të ri.']);
        break;

    default:
        json_error('Veprim i panjohur. Përdorni: login, register, logout, change_password, change_email, me, delete_account, resend_verification.', 400);
}
