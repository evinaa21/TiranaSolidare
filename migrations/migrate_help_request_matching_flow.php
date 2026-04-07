<?php
require_once __DIR__ . '/config/db.php';

function ts_column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function ts_index_exists(PDO $pdo, string $table, string $indexName): bool
{
    $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($indexName));
    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

try {

    if (!ts_column_exists($pdo, 'Kerkesa_per_Ndihme', 'matching_mode')) {
        $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN matching_mode ENUM('single','limited','open') NOT NULL DEFAULT 'open' AFTER statusi");
        echo "Added matching_mode to Kerkesa_per_Ndihme." . PHP_EOL;
    }
    if (!ts_column_exists($pdo, 'Kerkesa_per_Ndihme', 'capacity_total')) {
        $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN capacity_total INT NULL AFTER matching_mode");
        echo "Added capacity_total to Kerkesa_per_Ndihme." . PHP_EOL;
    }
    if (!ts_column_exists($pdo, 'Kerkesa_per_Ndihme', 'completed_at')) {
        $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN completed_at DATETIME NULL AFTER krijuar_me");
        echo "Added completed_at to Kerkesa_per_Ndihme." . PHP_EOL;
    }
    if (!ts_column_exists($pdo, 'Kerkesa_per_Ndihme', 'cancelled_at')) {
        $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN cancelled_at DATETIME NULL AFTER completed_at");
        echo "Added cancelled_at to Kerkesa_per_Ndihme." . PHP_EOL;
    }
    if (!ts_column_exists($pdo, 'Kerkesa_per_Ndihme', 'closed_reason')) {
        $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN closed_reason VARCHAR(255) NULL AFTER cancelled_at");
        echo "Added closed_reason to Kerkesa_per_Ndihme." . PHP_EOL;
    }

    $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme MODIFY COLUMN statusi VARCHAR(20) NOT NULL DEFAULT 'open'");
    $pdo->exec(
        "UPDATE Kerkesa_per_Ndihme
         SET statusi = CASE
             WHEN LOWER(statusi) IN ('open', 'hapur') THEN 'open'
             WHEN LOWER(statusi) IN ('filled', 'mbushur') THEN 'filled'
             WHEN LOWER(statusi) IN ('cancelled', 'anuluar') THEN 'cancelled'
             WHEN LOWER(statusi) IN ('closed', 'mbyllur', 'completed', 'përfunduar') THEN 'completed'
             ELSE LOWER(statusi)
         END"
    );

    $pdo->exec("ALTER TABLE Aplikimi_Kerkese MODIFY COLUMN statusi VARCHAR(20) NOT NULL DEFAULT 'pending'");
    $pdo->exec(
        "UPDATE Aplikimi_Kerkese
         SET statusi = CASE
             WHEN LOWER(statusi) IN ('pending', 'në pritje', 'ne pritje') THEN 'pending'
             WHEN LOWER(statusi) IN ('approved', 'pranuar') THEN 'approved'
             WHEN LOWER(statusi) IN ('waitlisted', 'në listë pritjeje') THEN 'waitlisted'
             WHEN LOWER(statusi) IN ('withdrawn', 'tërhequr') THEN 'withdrawn'
             WHEN LOWER(statusi) IN ('completed', 'përfunduar') THEN 'completed'
             WHEN LOWER(statusi) IN ('rejected', 'refuzuar') THEN 'rejected'
             ELSE LOWER(statusi)
         END"
    );

    $pdo->exec(
        "UPDATE Kerkesa_per_Ndihme
         SET matching_mode = CASE
             WHEN matching_mode NOT IN ('single', 'limited', 'open') OR matching_mode IS NULL THEN
                 CASE
                     WHEN capacity_total IS NULL THEN 'open'
                     WHEN capacity_total <= 1 THEN 'single'
                     ELSE 'limited'
                 END
             ELSE matching_mode
         END"
    );
    $pdo->exec("UPDATE Kerkesa_per_Ndihme SET capacity_total = 1 WHERE matching_mode = 'single'");
    $pdo->exec("UPDATE Kerkesa_per_Ndihme SET capacity_total = NULL WHERE matching_mode = 'open'");

    $pdo->exec(
        "UPDATE Kerkesa_per_Ndihme kn
         SET kn.statusi = 'filled'
         WHERE kn.statusi = 'open'
           AND kn.matching_mode IN ('single', 'limited')
           AND kn.capacity_total IS NOT NULL
           AND (
               SELECT COUNT(*)
               FROM Aplikimi_Kerkese ak
               WHERE ak.id_kerkese_ndihme = kn.id_kerkese_ndihme
                 AND ak.statusi = 'approved'
           ) >= kn.capacity_total"
    );
    $pdo->exec("UPDATE Kerkesa_per_Ndihme SET completed_at = COALESCE(completed_at, krijuar_me) WHERE statusi = 'completed'");
    $pdo->exec("UPDATE Kerkesa_per_Ndihme SET cancelled_at = COALESCE(cancelled_at, krijuar_me) WHERE statusi = 'cancelled'");

    $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme MODIFY COLUMN statusi ENUM('open','filled','completed','cancelled') NOT NULL DEFAULT 'open'");
    $pdo->exec("ALTER TABLE Aplikimi_Kerkese MODIFY COLUMN statusi ENUM('pending','approved','waitlisted','rejected','withdrawn','completed') NOT NULL DEFAULT 'pending'");

    if (!ts_index_exists($pdo, 'Kerkesa_per_Ndihme', 'idx_help_request_status')) {
        $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD INDEX idx_help_request_status (statusi)");
        echo "Added idx_help_request_status." . PHP_EOL;
    }
    if (!ts_index_exists($pdo, 'Kerkesa_per_Ndihme', 'idx_help_request_matching')) {
        $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD INDEX idx_help_request_matching (matching_mode)");
        echo "Added idx_help_request_matching." . PHP_EOL;
    }
    if (!ts_index_exists($pdo, 'Aplikimi_Kerkese', 'idx_help_request_application_status')) {
        $pdo->exec("ALTER TABLE Aplikimi_Kerkese ADD INDEX idx_help_request_application_status (statusi)");
        echo "Added idx_help_request_application_status." . PHP_EOL;
    }
    echo "Help request matching flow migration completed successfully." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}