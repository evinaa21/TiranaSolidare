<?php
require_once __DIR__ . '/config/db.php';

function ts_support_table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table));
    return (bool) $stmt->fetchColumn();
}

function ts_support_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    if (!ts_support_table_exists($pdo, 'support_messages')) {
        $pdo->exec(
            "CREATE TABLE support_messages (
                id_support_message INT NOT NULL AUTO_INCREMENT,
                from_user_id INT NULL,
                from_name VARCHAR(160) NOT NULL,
                from_email VARCHAR(190) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('new','read','replied','resolved') NOT NULL DEFAULT 'new',
                last_reply_message TEXT NULL,
                replied_by INT NULL,
                replied_at DATETIME NULL,
                resolved_by INT NULL,
                resolved_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id_support_message),
                INDEX idx_support_status (status, created_at),
                INDEX idx_support_user (from_user_id),
                INDEX idx_support_reply (replied_by),
                INDEX idx_support_resolve (resolved_by)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        echo "Created support_messages table." . PHP_EOL;
    }

    if (!ts_support_column_exists($pdo, 'support_messages', 'last_reply_message')) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN last_reply_message TEXT NULL AFTER status");
        echo "Added last_reply_message column." . PHP_EOL;
    }
    if (!ts_support_column_exists($pdo, 'support_messages', 'replied_by')) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN replied_by INT NULL AFTER last_reply_message");
        echo "Added replied_by column." . PHP_EOL;
    }
    if (!ts_support_column_exists($pdo, 'support_messages', 'replied_at')) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN replied_at DATETIME NULL AFTER replied_by");
        echo "Added replied_at column." . PHP_EOL;
    }
    if (!ts_support_column_exists($pdo, 'support_messages', 'resolved_by')) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN resolved_by INT NULL AFTER replied_at");
        echo "Added resolved_by column." . PHP_EOL;
    }
    if (!ts_support_column_exists($pdo, 'support_messages', 'resolved_at')) {
        $pdo->exec("ALTER TABLE support_messages ADD COLUMN resolved_at DATETIME NULL AFTER resolved_by");
        echo "Added resolved_at column." . PHP_EOL;
    }

    $pdo->exec("ALTER TABLE support_messages MODIFY COLUMN status ENUM('new','read','replied','resolved') NOT NULL DEFAULT 'new'");

    echo "Support message migration completed successfully." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}