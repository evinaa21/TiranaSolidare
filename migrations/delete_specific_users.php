<?php
/**
 * migrations/delete_specific_users.php
 * ─────────────────────────────────────
 * Hard-deletes specific user accounts and all their related data.
 *
 * Targets:
 *   - etershalla23@epoka*  (any @epoka domain)
 *   - akalistaa9@gmail.com
 *   - any user whose email contains "swipetocode"
 *
 * Usage (dry-run first, then real delete):
 *   php migrations/delete_specific_users.php
 *   php migrations/delete_specific_users.php --confirm
 */

require_once __DIR__ . '/../config/db.php';

$dryRun = !in_array('--confirm', $argv ?? [], true);

// ── Find matching users ───────────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT id_perdoruesi AS id, emri, email, roli
     FROM Perdoruesi
     WHERE email LIKE 'etershalla23@epoka%'
        OR email LIKE '%swipetocode%'
     ORDER BY id_perdoruesi"
);
$stmt->execute();
$users = $stmt->fetchAll();

if (!$users) {
    echo "No matching users found.\n";
    exit(0);
}

echo ($dryRun ? "[DRY RUN] " : "") . "Found " . count($users) . " user(s) to delete:\n";
foreach ($users as $u) {
    echo "  ID={$u['id']}  email={$u['email']}  name={$u['emri']}  role={$u['roli']}\n";
}

if ($dryRun) {
    echo "\nRun with --confirm to permanently delete these users.\n";
    exit(0);
}

// ── Delete each user ──────────────────────────────────────────────────────────
$deleted = 0;
$errors  = 0;

foreach ($users as $u) {
    $id = $u['id'];
    try {
        $pdo->beginTransaction();

        $pdo->prepare('DELETE FROM Njoftimi WHERE id_perdoruesi = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM Aplikimi WHERE id_perdoruesi = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM Aplikimi_Kerkese WHERE id_perdoruesi = ?')->execute([$id]);
        $pdo->prepare(
            'DELETE FROM Aplikimi_Kerkese WHERE id_kerkese_ndihme IN
             (SELECT id_kerkese_ndihme FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?)'
        )->execute([$id]);
        $pdo->prepare('DELETE FROM Kerkesa_per_Ndihme WHERE id_perdoruesi = ?')->execute([$id]);
        $pdo->prepare('UPDATE Eventi SET id_perdoruesi = NULL WHERE id_perdoruesi = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM Mesazhi WHERE derguesi_id = ? OR marruesi_id = ?')->execute([$id, $id]);
        $pdo->prepare('DELETE FROM Perdoruesi WHERE id_perdoruesi = ?')->execute([$id]);

        $pdo->commit();
        echo "  DELETED: {$u['email']} (ID {$id})\n";
        $deleted++;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo "  ERROR deleting {$u['email']} (ID {$id}): " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nDone. Deleted: $deleted, Errors: $errors\n";
