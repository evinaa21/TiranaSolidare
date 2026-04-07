<?php
require_once __DIR__ . '/config/db.php';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_blocks (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        blocker_id  INT NOT NULL,
        blocked_id  INT NOT NULL,
        krijuar_me  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_block (blocker_id, blocked_id),
        FOREIGN KEY (blocker_id) REFERENCES Perdoruesi(id_perdoruesi) ON DELETE CASCADE,
        FOREIGN KEY (blocked_id) REFERENCES Perdoruesi(id_perdoruesi) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "U krye!";