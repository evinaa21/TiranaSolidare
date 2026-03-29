<?php
/**
 * config/env.php
 * ---------------------------------------------------
 * Loads .env file into environment variables.
 * Must be required before config/db.php or config/mail.php.
 * ---------------------------------------------------
 */

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Set application timezone from .env (APP_TIMEZONE) or default to Albania's timezone.
// All strtotime(), date(), and DateTimeImmutable() calls throughout the app
// depend on this being set correctly before any time comparisons run.
//
// Scope: affects PHP functions only — MySQL's NOW() / CURDATE() continue to use
// the MySQL server's own timezone setting (which on this XAMPP install matches the
// Windows system clock, i.e. also Europe/Tirane).
//
// Stored data safety: event datetime values in the `data` column are submitted
// as Albania-local time strings by the browser (via <input type="datetime-local">)
// and are inserted verbatim — the PHP layer never converts them to UTC.  Existing
// rows therefore remain correct; this setting only prevents PHP from misreading
// them as UTC when building comparisons like token-expiry checks.
$appTz = getenv('APP_TIMEZONE');
date_default_timezone_set(($appTz !== false && $appTz !== '') ? $appTz : 'Europe/Tirane');
