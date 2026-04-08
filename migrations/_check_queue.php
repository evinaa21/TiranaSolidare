<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../config/db.php';
$rows = $pdo->query("SELECT status, attempts, to_email, subject FROM email_queue ORDER BY krijuar_me DESC LIMIT 10")->fetchAll();
foreach ($rows as $r) {
    echo "[{$r['status']}] attempts={$r['attempts']} to={$r['to_email']} | " . mb_substr($r['subject'], 0, 60) . "\n";
}
echo "\nTotal: " . $pdo->query("SELECT COUNT(*) FROM email_queue")->fetchColumn() . "\n";
echo "By status:\n";
foreach ($pdo->query("SELECT status, COUNT(*) c FROM email_queue GROUP BY status")->fetchAll() as $r) {
    echo "  {$r['status']}: {$r['c']}\n";
}
