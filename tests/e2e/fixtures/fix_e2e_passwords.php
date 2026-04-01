<?php
require __DIR__ . '/../../../config/db.php';
$h1 = password_hash('Test1234!', PASSWORD_DEFAULT);
$h2 = password_hash('Test1234!', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE Perdoruesi SET fjalekalimi=? WHERE email=?');
$stmt->execute([$h1, 'e2e.volunteer@test.local']);
echo "Volunteer updated. Verify: " . (password_verify('Test1234!', $h1) ? 'OK' : 'FAIL') . "\n";
$stmt->execute([$h2, 'e2e.admin@test.local']);
echo "Admin updated. Verify: " . (password_verify('Test1234!', $h2) ? 'OK' : 'FAIL') . "\n";
