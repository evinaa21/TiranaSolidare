<?php
require_once __DIR__ . '/config/db.php';

$statements = [
    "CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "INSERT INTO site_settings (setting_key, setting_value) VALUES
        ('organization_name', 'Tirana Solidare'),
        ('hero_badge', 'Platforma Zyrtare e Vullnetarizmit — Tirana Solidare'),
        ('hero_title', 'Bashkohu me komunitetin\nqë ndryshon jetë'),
        ('hero_subtitle', 'Së bashku mund të bëjmë më shumë. Ndihmo dikë sot dhe bëhu ndryshimi që dëshiron të shohësh.'),
        ('footer_blurb', 'Ne besojmë se çdo akt i vogël mirësie ka fuqinë të ndryshojë jetën e dikujt. Platforma jonë është krijuar për të afruar njerëzit dhe për të ndërtuar një komunitet më të kujdesshëm dhe mbështetës.'),
        ('contact_phone', '+355 69 123 4567'),
        ('contact_address', 'Bashkia Tiranë, Tiranë'),
        ('theme_primary', '#00715D'),
        ('theme_accent', '#E17254')
     ON DUPLICATE KEY UPDATE setting_value = setting_value",
    "CREATE TABLE IF NOT EXISTS organization_applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        applicant_user_id INT NOT NULL,
        organization_name VARCHAR(160) NOT NULL,
        contact_name VARCHAR(120) NOT NULL,
        contact_email VARCHAR(160) NOT NULL,
        contact_phone VARCHAR(40) DEFAULT NULL,
        website VARCHAR(255) DEFAULT NULL,
        description TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        review_notes TEXT DEFAULT NULL,
        reviewed_by_user_id INT DEFAULT NULL,
        reviewed_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_org_app_status (status),
        INDEX idx_org_app_user (applicant_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS organization_name VARCHAR(160) NULL AFTER guardian_consent_verified_at",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS organization_website VARCHAR(255) NULL AFTER organization_name",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS organization_phone VARCHAR(40) NULL AFTER organization_website",
    "ALTER TABLE Perdoruesi ADD COLUMN IF NOT EXISTS organization_description TEXT NULL AFTER organization_phone",
    "ALTER TABLE Perdoruesi MODIFY COLUMN roli VARCHAR(30) NOT NULL DEFAULT 'volunteer'",
    "UPDATE Perdoruesi SET roli = CASE
        WHEN LOWER(roli) IN ('admin') THEN 'admin'
        WHEN LOWER(roli) IN ('super_admin', 'super admin') THEN 'super_admin'
        WHEN LOWER(roli) IN ('organizer', 'organizator') THEN 'organizer'
        ELSE 'volunteer'
    END",
    "ALTER TABLE Perdoruesi MODIFY COLUMN roli ENUM('admin','volunteer','super_admin','organizer') NOT NULL DEFAULT 'volunteer'",
    "ALTER TABLE Eventi ADD COLUMN IF NOT EXISTS statusi VARCHAR(30) NOT NULL DEFAULT 'active' AFTER banner",
    "ALTER TABLE Eventi ADD COLUMN IF NOT EXISTS is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER statusi",
    "ALTER TABLE Eventi MODIFY COLUMN statusi VARCHAR(30) NOT NULL DEFAULT 'active'",
    "UPDATE Eventi SET statusi = 'active' WHERE statusi IS NULL OR statusi = ''",
    "ALTER TABLE Eventi MODIFY COLUMN statusi ENUM('active','completed','cancelled','pending_review') NOT NULL DEFAULT 'active'",
];

foreach ($statements as $statement) {
    $pdo->exec($statement);
}

echo 'Platform branding and organization schema ready.';