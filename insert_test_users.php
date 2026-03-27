<?php
/**
 * Insert two test users: 1 admin + 1 volunteer
 * Run: php insert_test_users.php
 */
require __DIR__ . '/config/db.php';

$adminHash = password_hash('TestAdmin2025!', PASSWORD_DEFAULT);
$volHash   = password_hash('TestVolunteer2025!', PASSWORD_DEFAULT);

// Check if emails already exist
$check = $pdo->prepare("SELECT email FROM Perdoruesi WHERE email IN (?, ?)");
$check->execute(['testadmin@tiranasolidare.al', 'testvullnetar@tiranasolidare.al']);
$existing = $check->fetchAll(PDO::FETCH_COLUMN);

if (in_array('testadmin@tiranasolidare.al', $existing)) {
    echo "Admin test user already exists.\n";
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise, verified, krijuar_me)
         VALUES (?, ?, ?, 'admin', 'active', 1, NOW())"
    );
    $stmt->execute(['Test Admin', 'testadmin@tiranasolidare.al', $adminHash]);
    echo "Admin inserted: ID " . $pdo->lastInsertId() . "\n";
}

if (in_array('testvullnetar@tiranasolidare.al', $existing)) {
    echo "Volunteer test user already exists.\n";
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise, verified, krijuar_me)
         VALUES (?, ?, ?, 'volunteer', 'active', 1, NOW())"
    );
    $stmt->execute(['Test Vullnetar', 'testvullnetar@tiranasolidare.al', $volHash]);
    echo "Volunteer inserted: ID " . $pdo->lastInsertId() . "\n";
}

echo "\n--- Test User Credentials ---\n";
echo "ADMIN:\n";
echo "  Email:    testadmin@tiranasolidare.al\n";
echo "  Password: TestAdmin2025!\n";
echo "  Role:     admin\n\n";
echo "VOLUNTEER:\n";
echo "  Email:    testvullnetar@tiranasolidare.al\n";
echo "  Password: TestVolunteer2025!\n";
echo "  Role:     volunteer\n";
