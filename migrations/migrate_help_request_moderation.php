<?php
/**
 * migrate_help_request_moderation.php
 * ---------------------------------------------------
 * Adds moderation_status column to Kerkesa_per_Ndihme.
 *
 * Values: 'pending_review', 'approved', 'rejected'
 *
 * Existing rows are backfilled to 'approved' so current
 * live data stays publicly visible.
 *
 * Idempotent — safe to run multiple times on MariaDB/MySQL.
 * ---------------------------------------------------
 */
require_once __DIR__ . '/config/db.php';

function ts_migration_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function ts_migration_index_exists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($indexName));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    // ── 1. Add moderation_status column ──
    if (!ts_migration_column_exists($pdo, 'Kerkesa_per_Ndihme', 'moderation_status')) {
        $pdo->exec(
            "ALTER TABLE Kerkesa_per_Ndihme
             ADD COLUMN moderation_status ENUM('pending_review','approved','rejected')
             NOT NULL DEFAULT 'approved'
             AFTER statusi"
        );
        echo "Added moderation_status to Kerkesa_per_Ndihme." . PHP_EOL;
    } else {
        echo "Column moderation_status already exists — skipping." . PHP_EOL;
    }

    // ── 2. Backfill: all existing rows → approved ──
    $updated = $pdo->exec(
        "UPDATE Kerkesa_per_Ndihme
         SET moderation_status = 'approved'
         WHERE moderation_status != 'approved'"
    );
    echo "Backfilled {$updated} row(s) to moderation_status = 'approved'." . PHP_EOL;

    // ── 3. Index for efficient filtering ──
    if (!ts_migration_index_exists($pdo, 'Kerkesa_per_Ndihme', 'idx_help_request_moderation')) {
        $pdo->exec(
            "ALTER TABLE Kerkesa_per_Ndihme
             ADD INDEX idx_help_request_moderation (moderation_status)"
        );
        echo "Added idx_help_request_moderation." . PHP_EOL;
    } else {
        echo "Index idx_help_request_moderation already exists — skipping." . PHP_EOL;
    }

    echo PHP_EOL . "Migration complete." . PHP_EOL;
} catch (\PDOException $e) {
    echo "Migration FAILED: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
