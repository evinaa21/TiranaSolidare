<?php
/**
 * cron/process_emails.php
 * -------------------------------------------------------
 * Processes the email queue in batches.
 * Run via cron or Windows Task Scheduler, e.g.:
 *   php C:\xampp\htdocs\TiranaSolidare\cron\process_emails.php
 *
 * Recommended interval: every 1–2 minutes.
 * -------------------------------------------------------
 */

// Prevent web access
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$sent = process_email_queue(20);

if ($sent > 0) {
    echo date('Y-m-d H:i:s') . " — Processed {$sent} email(s).\n";
}
