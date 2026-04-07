<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_contact_with_flash(string $message, string $type, array $old = []): void
{
    $_SESSION['contact_flash'] = [
        'type' => $type,
        'message' => $message,
    ];

    if ($type === 'error' && $old !== []) {
        $_SESSION['contact_form_old'] = $old;
    } else {
        unset($_SESSION['contact_form_old']);
    }

    header('Location: ' . ts_contact_page_path());
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . ts_contact_page_path());
    exit();
}

if (!validate_csrf_token($_POST['_csrf_token'] ?? null)) {
    redirect_contact_with_flash('Sesioni ka skaduar. Rifreskoni faqen dhe provoni përsëri.', 'error');
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$old = [
    'name' => $name,
    'email' => $email,
    'subject' => $subject,
    'message' => $message,
];

if (!check_rate_limit('contact_form', 5, 3600)) {
    redirect_contact_with_flash('Keni dërguar shumë mesazhe. Provoni përsëri pas një ore.', 'error', $old);
}

if ($name === '' || $email === '' || $subject === '' || $message === '') {
    redirect_contact_with_flash('Plotësoni të gjitha fushat e formularit.', 'error', $old);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_contact_with_flash('Formati i email-it nuk është i vlefshëm.', 'error', $old);
}

if ($lenErr = validate_length($name, 2, 120, 'emri')) {
    redirect_contact_with_flash($lenErr, 'error', $old);
}
if ($lenErr = validate_length($subject, 3, 160, 'subjekti')) {
    redirect_contact_with_flash($lenErr, 'error', $old);
}
if ($lenErr = validate_length($message, 10, 4000, 'mesazhi')) {
    redirect_contact_with_flash($lenErr, 'error', $old);
}
if ($profErr = check_profanity($subject, $message)) {
    redirect_contact_with_flash($profErr, 'error', $old);
}

$senderUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
if (!send_contact_email($email, $name, $subject, $message, $senderUserId)) {
    redirect_contact_with_flash('Mesazhi nuk u dërgua. Provoni përsëri pas pak.', 'error', $old);
}

redirect_contact_with_flash('Mesazhi u dërgua me sukses. Ekipi ynë do t’ju përgjigjet sa më shpejt.', 'success');