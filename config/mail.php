<?php
/**
 * SMTP settings for PHPMailer.
 * Values are loaded from .env file via config/env.php.
 * See .env.example for required variables.
 */
require_once __DIR__ . '/env.php';

return [
    'host'       => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'port'       => (int) (getenv('SMTP_PORT') ?: 587),
    'username'   => getenv('SMTP_USER') ?: '',
    'password'   => getenv('SMTP_PASS') ?: '',
    'encryption' => 'tls',
    'from_email' => getenv('SMTP_FROM') ?: 'no-reply@tiranasolidare.al',
    'from_name'  => getenv('SMTP_FROM_NAME') ?: 'Tirana Solidare',
];
