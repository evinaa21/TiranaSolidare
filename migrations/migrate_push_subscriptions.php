<?php
/**
 * migrate_push_subscriptions.php
 * Creates the push_subscriptions table for Web Push notifications.
 * Idempotent - safe to run multiple times.
 */
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            user_id     INT NOT NULL,
            endpoint    VARCHAR(512) NOT NULL,
            p256dh      VARCHAR(512) NOT NULL,
            auth        VARCHAR(256) NOT NULL,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_endpoint (endpoint(512)),
            INDEX idx_push_user (user_id),
            CONSTRAINT fk_push_user FOREIGN KEY (user_id)
                REFERENCES Perdoruesi (id_perdoruesi)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "push_subscriptions table created (or already exists)." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
