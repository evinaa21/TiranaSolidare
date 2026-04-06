<?php
/**
 * migrate_audit_fixes.php
 * Applies schema gaps found in production-readiness audit.
 * Idempotent — safe to run on any existing installation.
 */
require_once __DIR__ . '/config/db.php';

try {
    // C-1: password_changed_at
    $col = $pdo->query("SHOW COLUMNS FROM `Perdoruesi` LIKE 'password_changed_at'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE `Perdoruesi` ADD COLUMN `password_changed_at` DATETIME NULL DEFAULT NULL");
        echo "Added password_changed_at." . PHP_EOL;
    }

    // C-2: super_admin in roli ENUM
    $pdo->exec("ALTER TABLE `Perdoruesi` MODIFY COLUMN `roli` ENUM('admin','volunteer','super_admin') DEFAULT 'volunteer'");
    echo "Updated roli ENUM." . PHP_EOL;

    // C-3: processing in email_queue.status ENUM
    $pdo->exec("ALTER TABLE `email_queue` MODIFY COLUMN `status` ENUM('pending','processing','sent','failed') DEFAULT 'pending'");
    echo "Updated email_queue status ENUM." . PHP_EOL;

    // C-4: Aplikimi_Kerkese statusi ENUM
    $pdo->exec("ALTER TABLE `Aplikimi_Kerkese` MODIFY COLUMN `statusi` ENUM('pending','approved','waitlisted','rejected','withdrawn','completed') DEFAULT 'pending'");
    echo "Updated Aplikimi_Kerkese statusi ENUM." . PHP_EOL;

    // C-5: Kerkesa_per_Ndihme statusi ENUM
    $pdo->exec("ALTER TABLE `Kerkesa_per_Ndihme` MODIFY COLUMN `statusi` ENUM('open','filled','completed','cancelled') DEFAULT 'open'");
    echo "Updated Kerkesa_per_Ndihme statusi ENUM." . PHP_EOL;

    echo PHP_EOL . "Audit fix migration complete." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
