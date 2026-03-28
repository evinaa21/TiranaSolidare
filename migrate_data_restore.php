<?php
/**
 * migrate_data_restore.php
 * Restore data that was lost during enum migration
 */
require_once __DIR__ . '/config/db.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Data Restoration ===\n\n";

// ─── 1. Restore Kerkesa_per_Ndihme.tipi (Ofertë → offer) ──
echo "1. Restoring help request types...\n";
$originalTypes = [
    1  => 'request', // Kërkesë
    2  => 'request', // Kërkesë
    3  => 'offer',   // Ofertë
    4  => 'request', // Kërkesë
    5  => 'offer',   // Ofertë
    6  => 'request', // Kërkesë
    7  => 'offer',   // Ofertë
    8  => 'request', // Kërkesë
    9  => 'request', // Kërkesë
    10 => 'offer',   // Ofertë
];
$stmt = $pdo->prepare("UPDATE Kerkesa_per_Ndihme SET tipi = ? WHERE id_kerkese_ndihme = ?");
foreach ($originalTypes as $id => $type) {
    $stmt->execute([$type, $id]);
}
$vals = $pdo->query("SELECT tipi, COUNT(*) as cnt FROM Kerkesa_per_Ndihme GROUP BY tipi")->fetchAll(PDO::FETCH_ASSOC);
foreach ($vals as $v) echo "   {$v['tipi']}: {$v['cnt']}\n";

// ─── 2. Restore blocked user (Fatjon Muça, ID 10) ─────────
echo "\n2. Restoring blocked user statuses...\n";
$pdo->exec("UPDATE Perdoruesi SET statusi_llogarise = 'blocked' WHERE id_perdoruesi = 10");
echo "   User #10 set to 'blocked'.\n";

// ─── 3. Fix roles: user #2 = super_admin, test users cleaned ─
echo "\n3. Fixing user roles...\n";
$pdo->exec("UPDATE Perdoruesi SET roli = 'super_admin' WHERE id_perdoruesi = 2");
echo "   User #2 (admin) → super_admin.\n";

// Make test admin (#12) just a regular admin
$pdo->exec("UPDATE Perdoruesi SET roli = 'admin' WHERE id_perdoruesi = 12");
echo "   User #12 (Test Admin) → admin.\n";

// ─── 4. Final verification ─────────────────────────────
echo "\n=== VERIFICATION ===\n";

echo "\nUsers:\n";
$users = $pdo->query("SELECT id_perdoruesi, emri, roli, statusi_llogarise FROM Perdoruesi ORDER BY id_perdoruesi")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "  #{$u['id_perdoruesi']} {$u['emri']} — {$u['roli']} — {$u['statusi_llogarise']}\n";
}

echo "\nApplications:\n";
$apps = $pdo->query("SELECT id_aplikimi, id_perdoruesi, id_eventi, statusi FROM Aplikimi ORDER BY id_aplikimi LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
foreach ($apps as $a) {
    echo "  #{$a['id_aplikimi']} user#{$a['id_perdoruesi']} event#{$a['id_eventi']} — {$a['statusi']}\n";
}

echo "\nHelp requests:\n";
$reqs = $pdo->query("SELECT id_kerkese_ndihme, tipi, statusi, titulli FROM Kerkesa_per_Ndihme ORDER BY id_kerkese_ndihme")->fetchAll(PDO::FETCH_ASSOC);
foreach ($reqs as $r) {
    echo "  #{$r['id_kerkese_ndihme']} [{$r['tipi']}] [{$r['statusi']}] {$r['titulli']}\n";
}

echo "\n=== DONE ===\n";
