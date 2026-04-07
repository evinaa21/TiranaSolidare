<?php
require_once __DIR__ . '/config/db.php';

try {
    $check = $pdo->query("SHOW COLUMNS FROM Kategoria LIKE 'banner_path'");
    if ($check->fetch()) {
        echo "banner_path already exists on Kategoria." . PHP_EOL;
        exit(0);
    }

    $pdo->exec('ALTER TABLE Kategoria ADD COLUMN banner_path VARCHAR(500) NULL AFTER emri');
    echo "Added banner_path to Kategoria." . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}