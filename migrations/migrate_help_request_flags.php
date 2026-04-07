<?php
require_once __DIR__ . '/../config/db.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS help_request_flags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_kerkese_ndihme INT NOT NULL,
        id_perdoruesi INT NOT NULL,
        arsye TEXT NULL DEFAULT NULL,
        krijuar_me TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_flag (id_kerkese_ndihme, id_perdoruesi),
        FOREIGN KEY (id_kerkese_ndihme) REFERENCES Kerkesa_per_Ndihme(id_kerkese_ndihme) ON DELETE CASCADE,
        FOREIGN KEY (id_perdoruesi) REFERENCES Perdoruesi(id_perdoruesi) ON DELETE CASCADE
    )
");

// Add flags counter column to the main table if it doesn't exist yet
try {
    $pdo->query("SELECT flags FROM Kerkesa_per_Ndihme LIMIT 0");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE Kerkesa_per_Ndihme ADD COLUMN flags INT NOT NULL DEFAULT 0");
    // Back-fill from existing flag records
    $pdo->exec("
        UPDATE Kerkesa_per_Ndihme kn
        SET kn.flags = (
            SELECT COUNT(*) FROM help_request_flags hrf
            WHERE hrf.id_kerkese_ndihme = kn.id_kerkese_ndihme
        )
    ");
}

echo 'U krye!';