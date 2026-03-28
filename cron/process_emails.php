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

// Advisory file lock to prevent concurrent cron runs
$lockFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ts_email_cron.lock';
$lock = fopen($lockFile, 'w');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    // Another process is still running — exit cleanly
    exit(0);
}

$sent = process_email_queue(20);

flock($lock, LOCK_UN);
fclose($lock);

if ($sent > 0) {
    echo date('Y-m-d H:i:s') . " — Processed {$sent} email(s).\n";
}
