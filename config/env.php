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
