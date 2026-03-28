<?php
// Quick API test - delete after use
require_once __DIR__ . '/config/db.php';

echo "=== Testing DB Queries ===\n";

echo "Users: ";
$s = $pdo->query('SELECT COUNT(*) FROM Perdoruesi');
echo $s->fetchColumn() . " total\n";

echo "Help Requests: ";
$s = $pdo->query('SELECT COUNT(*) FROM Kerkesa_per_Ndihme');
echo $s->fetchColumn() . " total\n";

echo "Events: ";
$s = $pdo->query('SELECT COUNT(*) FROM Eventi');
echo $s->fetchColumn() . " total\n";

echo "Messages: ";
$s = $pdo->query('SELECT COUNT(*) FROM Mesazhi');
echo $s->fetchColumn() . " total\n";

echo "\n=== Testing users.php list query ===\n";
$stmt = $pdo->prepare("SELECT id_perdoruesi, emri, email, roli, statusi_llogarise, krijuar_me 
        FROM Perdoruesi WHERE id_perdoruesi != ? ORDER BY krijuar_me DESC LIMIT 15 OFFSET 0");
$stmt->execute([2]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Got " . count($users) . " users\n";
if (!empty($users)) {
    echo "First: " . json_encode($users[0], JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== Testing help_requests list query ===\n";
$stmt = $pdo->prepare("SELECT kn.*, p.emri AS krijuesi_emri 
    FROM Kerkesa_per_Ndihme kn 
    JOIN Perdoruesi p ON p.id_perdoruesi = kn.id_perdoruesi 
    ORDER BY CASE WHEN kn.statusi = 'open' THEN 0 ELSE 1 END ASC, kn.krijuar_me DESC 
    LIMIT 10 OFFSET 0");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Got " . count($requests) . " requests\n";
if (!empty($requests)) {
    echo "First: " . json_encode($requests[0], JSON_UNESCAPED_UNICODE) . "\n";
}

echo "\n=== Testing monthly stats query ===\n";
$stmt = $pdo->query("SELECT DATE_FORMAT(aplikuar_me, '%Y-%m') AS muaji, COUNT(*) AS total FROM Aplikimi GROUP BY muaji ORDER BY muaji ASC");
$monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Monthly apps data: " . count($monthly) . " months\n";

echo "\n=== All OK ===\n";
