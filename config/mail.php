<?php
/**
 * SMTP settings for PHPMailer.
 * Fill these values with your SMTP provider credentials.
 */
return [
    'host' => getenv('MAIL_HOST') ?: 'smtp.example.com',                   // SET SMTP
    'port' => (int) (getenv('MAIL_PORT') ?: 587),
    'username' => getenv('MAIL_USERNAME') ?: 'example@example.com', // SET EMAIL
    'password' => getenv('MAIL_PASSWORD') ?: 'example',         // SET PASSWORD
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls', // tls or ssl
    'from_email' => getenv('MAIL_FROM_EMAIL') ?: 'no-reply@tiranasolidare.al',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Tirana Solidare',
];
