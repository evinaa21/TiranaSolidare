<?php
/**
 * Migration: Allow public (non-logged-in) organization applications.
 * - Makes applicant_user_id nullable
 * - Adds 'organizer' to the roli ENUM if missing
 */
require_once __DIR__ . '/../config/db.php';

// Make applicant_user_id nullable
try {
    $pdo->exec("ALTER TABLE organization_applications MODIFY applicant_user_id INT NULL DEFAULT NULL");
    echo "applicant_user_id: now nullable\n";
} catch (Throwable $e) {
    echo "applicant_user_id change: " . $e->getMessage() . "\n";
}

// Add 'organizer' to roli ENUM if missing
try {
    $col = $pdo->query("SHOW COLUMNS FROM Perdoruesi WHERE Field = 'roli'")->fetch(PDO::FETCH_ASSOC);
    if (strpos($col['Type'], 'organizer') === false) {
        $pdo->exec("ALTER TABLE Perdoruesi MODIFY COLUMN roli ENUM('admin','volunteer','super_admin','organizer') DEFAULT 'volunteer'");
        echo "roli ENUM: added 'organizer'\n";
    } else {
        echo "roli ENUM: already has 'organizer'\n";
    }
} catch (Throwable $e) {
    echo "roli ENUM change: " . $e->getMessage() . "\n";
}

echo "U krye!\n";
