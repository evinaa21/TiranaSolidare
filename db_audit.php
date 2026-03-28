<?php
/**
 * Database Audit & Repair Script
 * Checks current state and fixes enum inconsistencies
 */
require_once __DIR__ . '/config/db.php';

echo "=== DATABASE AUDIT ===\n\n";

// 1. Check Perdoruesi.roli enum
echo "1. Perdoruesi.roli column:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM Perdoruesi LIKE 'roli'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Type: {$col['Type']}\n";
echo "   Default: {$col['Default']}\n";

// Check actual values
$stmt = $pdo->query("SELECT DISTINCT roli FROM Perdoruesi");
echo "   Current values: " . implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";

// 2. Check Perdoruesi.statusi_llogarise enum
echo "\n2. Perdoruesi.statusi_llogarise column:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM Perdoruesi LIKE 'statusi_llogarise'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Type: {$col['Type']}\n";

$stmt = $pdo->query("SELECT DISTINCT statusi_llogarise FROM Perdoruesi");
echo "   Current values: " . implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";

// 3. Check Aplikimi.statusi enum  
echo "\n3. Aplikimi.statusi column:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM Aplikimi LIKE 'statusi'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Type: {$col['Type']}\n";
echo "   Default: {$col['Default']}\n";

$stmt = $pdo->query("SELECT DISTINCT statusi FROM Aplikimi");
$vals = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "   Current values: " . implode(', ', array_map(function($v) { return $v === '' ? '(empty)' : $v; }, $vals)) . "\n";

$emptyCount = $pdo->query("SELECT COUNT(*) FROM Aplikimi WHERE statusi = '' OR statusi IS NULL")->fetchColumn();
echo "   Empty/null rows: $emptyCount\n";

// 4. Check Aplikimi_Kerkese.statusi enum
echo "\n4. Aplikimi_Kerkese.statusi column:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM Aplikimi_Kerkese LIKE 'statusi'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Type: {$col['Type']}\n";

// 5. Check Kerkesa_per_Ndihme.tipi enum
echo "\n5. Kerkesa_per_Ndihme.tipi column:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM Kerkesa_per_Ndihme LIKE 'tipi'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Type: {$col['Type']}\n";

$stmt = $pdo->query("SELECT DISTINCT tipi FROM Kerkesa_per_Ndihme");
echo "   Current values: " . implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN)) . "\n";

// 6. Check Kerkesa_per_Ndihme.statusi enum
echo "\n6. Kerkesa_per_Ndihme.statusi column:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM Kerkesa_per_Ndihme LIKE 'statusi'");
$col = $stmt->fetch(PDO::FETCH_ASSOC);
echo "   Type: {$col['Type']}\n";

// 7. Check for flags column
echo "\n7. Kerkesa_per_Ndihme.flags column:\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM Kerkesa_per_Ndihme LIKE 'flags'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $col ? "   Exists: {$col['Type']}\n" : "   MISSING\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 8. Check for id_kategoria in Kerkesa_per_Ndihme
echo "\n8. Kerkesa_per_Ndihme.id_kategoria column:\n";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM Kerkesa_per_Ndihme LIKE 'id_kategoria'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $col ? "   Exists: {$col['Type']}\n" : "   MISSING\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 9. Check Eventi columns
echo "\n9. Eventi columns:\n";
$stmt = $pdo->query("SHOW COLUMNS FROM Eventi");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "   Columns: " . implode(', ', $cols) . "\n";

// 10. Check additional tables that may have been added
echo "\n10. All tables:\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "   " . implode(', ', $tables) . "\n";

// 11. Count records
echo "\n11. Record counts:\n";
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "   $t: $count\n";
    } catch (Exception $e) {
        echo "   $t: ERROR - " . $e->getMessage() . "\n";
    }
}

echo "\n=== AUDIT COMPLETE ===\n";
