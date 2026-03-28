<?php
/**
 * migrate_superadmin.php
 * ---------------------------------------------------
 * Comprehensive migration script:
 * 1. Fix corrupted Aplikimi.statusi data (empty values from bad ALTER)
 * 2. Standardize all enums to English (code-compatible)
 * 3. Add super_admin role to Perdoruesi
 * 4. Ensure all needed columns exist
 * ---------------------------------------------------
 * Run once: php migrate_superadmin.php
 */
require_once __DIR__ . '/config/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Migration: Super Admin + DB Normalization ===\n\n";

// ─── Step 1: Fix Aplikimi.statusi ──────────────────────
// The previous test changed enum from Albanian to English, blanking all rows
echo "1. Fixing Aplikimi.statusi...\n";
$currentEnum = $pdo->query("SHOW COLUMNS FROM Aplikimi LIKE 'statusi'")->fetch(PDO::FETCH_ASSOC);
echo "   Current type: {$currentEnum['Type']}\n";

// Restore original data from the SQL dump
$originalData = [
    20 => 'approved', 21 => 'approved', 22 => 'pending',
    23 => 'approved', 24 => 'approved', 25 => 'pending',
    26 => 'approved', 27 => 'approved',
    28 => 'approved', 29 => 'pending',
    30 => 'approved', 31 => 'rejected',
    32 => 'approved', 33 => 'pending',
    34 => 'approved', 35 => 'approved', 36 => 'pending',
    37 => 'approved', 38 => 'pending',
];

// Ensure the enum has all needed values
$pdo->exec("ALTER TABLE Aplikimi MODIFY statusi ENUM('pending','approved','rejected','present','absent') DEFAULT 'pending'");
echo "   Enum updated to English values.\n";

// Restore corrupted rows
$restoreStmt = $pdo->prepare("UPDATE Aplikimi SET statusi = ? WHERE id_aplikimi = ? AND (statusi = '' OR statusi IS NULL)");
$restored = 0;
foreach ($originalData as $id => $status) {
    $restoreStmt->execute([$status, $id]);
    $restored += $restoreStmt->rowCount();
}

// Any rows not in original data — set to pending
$pdo->exec("UPDATE Aplikimi SET statusi = 'pending' WHERE statusi = '' OR statusi IS NULL");
echo "   Restored $restored rows to original values.\n";

// ─── Step 2: Normalize Aplikimi_Kerkese.statusi ───────
echo "\n2. Normalizing Aplikimi_Kerkese.statusi...\n";
$akEnum = $pdo->query("SHOW COLUMNS FROM Aplikimi_Kerkese LIKE 'statusi'")->fetch(PDO::FETCH_ASSOC);
echo "   Current type: {$akEnum['Type']}\n";

// First convert existing Albanian values to English
$pdo->exec("UPDATE Aplikimi_Kerkese SET statusi = 'pending' WHERE statusi = 'Në pritje'");
$pdo->exec("UPDATE Aplikimi_Kerkese SET statusi = 'approved' WHERE statusi = 'Pranuar'");
$pdo->exec("UPDATE Aplikimi_Kerkese SET statusi = 'rejected' WHERE statusi = 'Refuzuar'");
$pdo->exec("ALTER TABLE Aplikimi_Kerkese MODIFY statusi ENUM('pending','approved','rejected') DEFAULT 'pending'");
echo "   Done.\n";

// ─── Step 3: Normalize Perdoruesi.statusi_llogarise ───
echo "\n3. Normalizing Perdoruesi.statusi_llogarise...\n";
$pdo->exec("UPDATE Perdoruesi SET statusi_llogarise = 'active' WHERE statusi_llogarise = 'Aktiv'");
$pdo->exec("UPDATE Perdoruesi SET statusi_llogarise = 'blocked' WHERE statusi_llogarise = 'Bllokuar'");
$pdo->exec("UPDATE Perdoruesi SET statusi_llogarise = 'deactivated' WHERE statusi_llogarise = 'Çaktivizuar'");
$pdo->exec("ALTER TABLE Perdoruesi MODIFY statusi_llogarise ENUM('active','blocked','deactivated') DEFAULT 'active'");
echo "   Done.\n";

// ─── Step 4: Add super_admin to Perdoruesi.roli ──────
echo "\n4. Adding super_admin role...\n";
$pdo->exec("UPDATE Perdoruesi SET roli = 'admin' WHERE roli = 'Admin'");
$pdo->exec("UPDATE Perdoruesi SET roli = 'volunteer' WHERE roli = 'Vullnetar'");
$pdo->exec("ALTER TABLE Perdoruesi MODIFY roli ENUM('admin','volunteer','super_admin') DEFAULT 'volunteer'");
echo "   Done. Enum is now: admin, volunteer, super_admin\n";

// ─── Step 5: Normalize Kerkesa_per_Ndihme.tipi ───────
echo "\n5. Normalizing Kerkesa_per_Ndihme.tipi...\n";
$pdo->exec("UPDATE Kerkesa_per_Ndihme SET tipi = 'request' WHERE tipi = 'Kërkesë'");
$pdo->exec("UPDATE Kerkesa_per_Ndihme SET tipi = 'offer' WHERE tipi = 'Ofertë'");
$pdo->exec("ALTER TABLE Kerkesa_per_Ndihme MODIFY tipi ENUM('request','offer') DEFAULT NULL");
echo "   Done.\n";

// ─── Step 6: Normalize Kerkesa_per_Ndihme.statusi ────
// Already English ('Open','Closed'), let's make it lowercase
echo "\n6. Normalizing Kerkesa_per_Ndihme.statusi...\n";
$pdo->exec("UPDATE Kerkesa_per_Ndihme SET statusi = 'open' WHERE statusi = 'Open'");
$pdo->exec("UPDATE Kerkesa_per_Ndihme SET statusi = 'closed' WHERE statusi = 'Closed'");
$pdo->exec("ALTER TABLE Kerkesa_per_Ndihme MODIFY statusi ENUM('open','closed') DEFAULT 'open'");
echo "   Done.\n";

// ─── Step 7: Ensure flags column exists ───────────────
echo "\n7. Ensuring flags column...\n";
try {
    $pdo->query("SELECT flags FROM Kerkesa_per_Ndihme LIMIT 1");
    echo "   Already exists.\n";
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN flags INT DEFAULT 0");
    echo "   Created.\n";
}

// ─── Step 8: Promote the first admin to super_admin ───
echo "\n8. Setting first admin as super_admin...\n";
$adminStmt = $pdo->query("SELECT id_perdoruesi, emri FROM Perdoruesi WHERE roli = 'admin' ORDER BY krijuar_me ASC LIMIT 1");
$firstAdmin = $adminStmt->fetch(PDO::FETCH_ASSOC);
if ($firstAdmin) {
    $pdo->prepare("UPDATE Perdoruesi SET roli = 'super_admin' WHERE id_perdoruesi = ?")->execute([$firstAdmin['id_perdoruesi']]);
    echo "   User '{$firstAdmin['emri']}' (ID: {$firstAdmin['id_perdoruesi']}) promoted to super_admin.\n";
} else {
    echo "   No admin found to promote.\n";
}

// ─── Verification ─────────────────────────────────────
echo "\n=== VERIFICATION ===\n\n";

$tables = [
    'Perdoruesi.roli',
    'Perdoruesi.statusi_llogarise',
    'Aplikimi.statusi',
    'Aplikimi_Kerkese.statusi',
    'Kerkesa_per_Ndihme.tipi',
    'Kerkesa_per_Ndihme.statusi',
];

foreach ($tables as $tc) {
    [$table, $col] = explode('.', $tc);
    $stmt = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'");
    $info = $stmt->fetch(PDO::FETCH_ASSOC);
    $vals = $pdo->query("SELECT DISTINCT $col FROM $table")->fetchAll(PDO::FETCH_COLUMN);
    echo "$tc: {$info['Type']} → values: [" . implode(', ', $vals) . "]\n";
}

$emptyApps = (int) $pdo->query("SELECT COUNT(*) FROM Aplikimi WHERE statusi = '' OR statusi IS NULL")->fetchColumn();
echo "\nAplikimi rows with empty statusi: $emptyApps\n";

echo "\n=== MIGRATION COMPLETE ===\n";
