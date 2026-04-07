<?php
/**
 * One-time VAPID key generator.
 * Run once from CLI: php generate_vapid.php
 * Then add the two lines printed below to your .env file.
 *
 * The public key is also needed in the browser (JavaScript).
 * Store it as VAPID_PUBLIC_KEY in .env.
 *
 * DO NOT commit the private key to version control.
 */
// CLI only — deny web access
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
require_once __DIR__ . '/includes/web_push.php';

$keys = vapid_generate_keys();
echo "Add these lines to your .env file:\n\n";
echo "VAPID_PUBLIC_KEY={$keys['public']}\n";
echo "VAPID_PRIVATE_KEY={$keys['private']}\n\n";
echo "Public key for the browser subscription flow:\n";
echo $keys['public'] . "\n";
