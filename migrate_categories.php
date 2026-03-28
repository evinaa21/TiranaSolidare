<?php
/**
 * Migration: Add id_kategoria to kerkesa_per_ndihme
 * Run once, then delete this file.
 */
require_once __DIR__ . '/config/db.php';

try {
    // Check if column already exists
    $cols = $pdo->query("SHOW COLUMNS FROM kerkesa_per_ndihme LIKE 'id_kategoria'")->fetchAll();
    if (count($cols) > 0) {
        echo "Column id_kategoria already exists. Skipping.\n";
    } else {
        $pdo->exec("ALTER TABLE `kerkesa_per_ndihme` ADD COLUMN `id_kategoria` INT DEFAULT NULL AFTER `id_perdoruesi`");
        $pdo->exec("ALTER TABLE `kerkesa_per_ndihme` ADD KEY `idx_kategoria` (`id_kategoria`)");
        $pdo->exec("ALTER TABLE `kerkesa_per_ndihme` ADD CONSTRAINT `kerkesa_ndihme_kategoria_fk` FOREIGN KEY (`id_kategoria`) REFERENCES `kategoria` (`id_kategoria`) ON DELETE SET NULL");
        echo "Column id_kategoria added with FK.\n";
    }

    // Assign categories to existing seed data based on content
    $updates = [
        1 => 2,  // "Ndihmë me ushqim" → Sociale
        2 => 2,  // "Kërkoj veshje dimri" → Sociale
        3 => 3,  // "Ofroj tutoriale" → Edukimi
        4 => 5,  // "Ndihmë me riparim pas përmbytjes" → Emergjenca
        5 => 4,  // "Ofroj transport vizita mjekësore" → Shëndetësi
        6 => 3,  // "Kërkoj laptop studime" → Edukimi
        7 => 3,  // "Ofroj kurse kompjuteri" → Edukimi
        8 => 3,  // "Ndihmë furnizime shkollore" → Edukimi
        9 => 2,  // "Kërkoj ndihmë me qira" → Sociale
        10 => 2, // "Ofroj mobilje" → Sociale
    ];
    $stmt = $pdo->prepare("UPDATE kerkesa_per_ndihme SET id_kategoria = ? WHERE id_kerkese_ndihme = ?");
    foreach ($updates as $id => $catId) {
        $stmt->execute([$catId, $id]);
    }
    echo "Seed data categories assigned.\n";

    echo "Migration complete!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
