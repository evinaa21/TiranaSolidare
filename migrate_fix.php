<?php
/**
 * migrate_fix.php
 * Fix the enum value conversion — proper order:
 * 1. ALTER to include BOTH old and new values
 * 2. UPDATE data from old to new
 * 3. ALTER to remove old values
 */
require_once __DIR__ . '/config/db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Fix Migration ===\n\n";

// ─── Fix Perdoruesi.roli ──────────────────────────────
echo "1. Fixing Perdoruesi.roli...\n";
// Step A: Expand enum to include all values
$pdo->exec("ALTER TABLE Perdoruesi MODIFY roli ENUM('Admin','Vullnetar','admin','volunteer','super_admin') DEFAULT 'volunteer'");
// Step B: Convert old values
$pdo->exec("UPDATE Perdoruesi SET roli = 'volunteer' WHERE roli = 'Vullnetar' OR roli = ''");
$pdo->exec("UPDATE Perdoruesi SET roli = 'admin' WHERE roli = 'Admin'");
// Promote first admin to super_admin
$firstAdmin = $pdo->query("SELECT id_perdoruesi FROM Perdoruesi WHERE roli = 'admin' ORDER BY krijuar_me ASC LIMIT 1")->fetchColumn();
if ($firstAdmin) {
    $pdo->prepare("UPDATE Perdoruesi SET roli = 'super_admin' WHERE id_perdoruesi = ?")->execute([$firstAdmin]);
    echo "   User #$firstAdmin promoted to super_admin.\n";
}
// Step C: Shrink enum to final values only
$pdo->exec("ALTER TABLE Perdoruesi MODIFY roli ENUM('admin','volunteer','super_admin') DEFAULT 'volunteer'");
$vals = $pdo->query("SELECT DISTINCT roli FROM Perdoruesi")->fetchAll(PDO::FETCH_COLUMN);
echo "   Values: [" . implode(', ', $vals) . "]\n";

// ─── Fix Perdoruesi.statusi_llogarise ─────────────────
echo "\n2. Fixing Perdoruesi.statusi_llogarise...\n";
$pdo->exec("ALTER TABLE Perdoruesi MODIFY statusi_llogarise VARCHAR(30) DEFAULT 'active'");
$pdo->exec("UPDATE Perdoruesi SET statusi_llogarise = 'active' WHERE statusi_llogarise IN ('Aktiv','aktiv','')");
$pdo->exec("UPDATE Perdoruesi SET statusi_llogarise = 'blocked' WHERE statusi_llogarise IN ('Bllokuar','bllokuar')");
$pdo->exec("UPDATE Perdoruesi SET statusi_llogarise = 'deactivated' WHERE statusi_llogarise IN ('Çaktivizuar','çaktivizuar')");
// Check for any remaining non-standard values
$pdo->exec("UPDATE Perdoruesi SET statusi_llogarise = 'active' WHERE statusi_llogarise NOT IN ('active','blocked','deactivated')");
$pdo->exec("ALTER TABLE Perdoruesi MODIFY statusi_llogarise ENUM('active','blocked','deactivated') DEFAULT 'active'");
$vals = $pdo->query("SELECT DISTINCT statusi_llogarise FROM Perdoruesi")->fetchAll(PDO::FETCH_COLUMN);
echo "   Values: [" . implode(', ', $vals) . "]\n";

// ─── Fix Kerkesa_per_Ndihme.tipi ─────────────────────
echo "\n3. Fixing Kerkesa_per_Ndihme.tipi...\n";
$pdo->exec("ALTER TABLE Kerkesa_per_Ndihme MODIFY tipi VARCHAR(30) DEFAULT NULL");
$pdo->exec("UPDATE Kerkesa_per_Ndihme SET tipi = 'request' WHERE tipi IN ('Kërkesë','kërkesë','')");
$pdo->exec("UPDATE Kerkesa_per_Ndihme SET tipi = 'offer' WHERE tipi IN ('Ofertë','ofertë')");
$pdo->exec("UPDATE Kerkesa_per_Ndihme SET tipi = 'request' WHERE tipi NOT IN ('request','offer')");
$pdo->exec("ALTER TABLE Kerkesa_per_Ndihme MODIFY tipi ENUM('request','offer') DEFAULT NULL");
$vals = $pdo->query("SELECT DISTINCT tipi FROM Kerkesa_per_Ndihme")->fetchAll(PDO::FETCH_COLUMN);
echo "   Values: [" . implode(', ', $vals) . "]\n";

// ─── Verify everything ───────────────────────────────
echo "\n=== FINAL VERIFICATION ===\n";
$checks = [
    'Perdoruesi.roli',
    'Perdoruesi.statusi_llogarise',
    'Aplikimi.statusi',
    'Aplikimi_Kerkese.statusi',
    'Kerkesa_per_Ndihme.tipi',
    'Kerkesa_per_Ndihme.statusi',
];
foreach ($checks as $tc) {
    [$table, $col] = explode('.', $tc);
    $info = $pdo->query("SHOW COLUMNS FROM $table LIKE '$col'")->fetch(PDO::FETCH_ASSOC);
    $vals = $pdo->query("SELECT DISTINCT `$col` FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $valsStr = implode(', ', array_filter($vals, fn($v) => $v !== ''));
    echo "$tc: {$info['Type']} → [$valsStr]\n";
}

// Show all users with roles
echo "\nAll users:\n";
$users = $pdo->query("SELECT id_perdoruesi, emri, roli, statusi_llogarise FROM Perdoruesi ORDER BY id_perdoruesi")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "  #{$u['id_perdoruesi']} {$u['emri']} — roli: {$u['roli']} — statusi: {$u['statusi_llogarise']}\n";
}

echo "\n=== DONE ===\n";
