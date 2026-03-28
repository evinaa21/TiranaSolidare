<?php
require_once __DIR__ . '/config/db.php';

try {
    $pdo->exec('ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN flags INT DEFAULT 0;');
    echo "Column 'flags' added successfully.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
        echo "Column 'flags' already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
