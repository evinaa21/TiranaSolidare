<?php
require_once __DIR__ . '/config/db.php';

$pdo->exec("
    ALTER TABLE Perdoruesi 
    ADD COLUMN IF NOT EXISTS arsye_bllokimi TEXT NULL DEFAULT NULL
");

echo 'U krye!';