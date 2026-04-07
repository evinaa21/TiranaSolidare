<?php
require_once __DIR__ . '/config/db.php';

$statements = [
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS birthdate DATE NULL AFTER email",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS guardian_name VARCHAR(100) NULL AFTER verification_token_expires",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS guardian_email VARCHAR(150) NULL AFTER guardian_name",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS guardian_relation VARCHAR(60) NULL AFTER guardian_email",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS guardian_consent_status VARCHAR(20) NOT NULL DEFAULT 'not_required' AFTER guardian_relation",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS guardian_consent_token_hash VARCHAR(64) NULL AFTER guardian_consent_status",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS guardian_consent_token_expires DATETIME NULL AFTER guardian_consent_token_hash",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS guardian_consent_verified_at DATETIME NULL AFTER guardian_consent_token_expires",
    "ALTER TABLE Perdoruesi MODIFY COLUMN guardian_consent_status VARCHAR(20) NOT NULL DEFAULT 'not_required'",
    "ALTER TABLE Perdoruesi MODIFY COLUMN guardian_relation VARCHAR(60) NULL",
    "UPDATE Perdoruesi SET guardian_consent_status = 'not_required' WHERE guardian_consent_status IS NULL OR guardian_consent_status = ''",
];

foreach ($statements as $statement) {
    $pdo->exec($statement);
}

echo 'Guardian consent schema ready.';