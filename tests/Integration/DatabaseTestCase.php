<?php
/**
 * tests/Integration/DatabaseTestCase.php
 * ---------------------------------------------------
 * Base class for integration tests that need a real DB.
 * Uses the TiranaSolidare_test database (created from the
 * same schema as production).
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected static ?PDO $pdo = null;

    /**
     * Connect once and share across all tests in the suite.
     */
    public static function setUpBeforeClass(): void
    {
        if (self::$pdo !== null) return;

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3307';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        // Connect without a specific database first
        $dsn = "mysql:host={$host};port={$port};charset={$charset}";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Create test database if not exists
        self::$pdo->exec('CREATE DATABASE IF NOT EXISTS TiranaSolidare_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        self::$pdo->exec('USE TiranaSolidare_test');

        // Set up tables needed for testing
        self::createTestTables();
    }

    /**
     * Clean up data between tests (truncate, not drop).
     */
    protected function setUp(): void
    {
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = ['Perdoruesi', 'rate_limit_log', 'email_queue', 'admin_log', 'Njoftimi', 'support_messages'];
        foreach ($tables as $table) {
            self::$pdo->exec("TRUNCATE TABLE {$table}");
        }
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        // Make our PDO globally available (functions.php uses global $pdo)
        $GLOBALS['pdo'] = self::$pdo;
    }

    /**
     * Create the minimum table set for integration testing.
     */
    private static function createTestTables(): void
    {
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS Perdoruesi (
                id_perdoruesi INT AUTO_INCREMENT PRIMARY KEY,
                emri VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                fjalekalimi VARCHAR(255) NOT NULL,
                roli ENUM('admin','volunteer','super_admin') DEFAULT 'volunteer',
                statusi_llogarise VARCHAR(30) DEFAULT 'Aktiv',
                verified TINYINT(1) DEFAULT 0,
                verification_token VARCHAR(255) DEFAULT NULL,
                verification_expires DATETIME DEFAULT NULL,
                reset_token VARCHAR(255) DEFAULT NULL,
                reset_expires DATETIME DEFAULT NULL,
                bio TEXT DEFAULT NULL,
                profilePicture VARCHAR(500) DEFAULT NULL,
                profile_color VARCHAR(20) DEFAULT 'emerald',
                email_notifications TINYINT(1) NOT NULL DEFAULT 1,
                krijuar_me TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                action VARCHAR(100) NOT NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_action (ip, action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS email_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                to_name VARCHAR(200) NOT NULL,
                subject VARCHAR(500) NOT NULL,
                body_html LONGTEXT NOT NULL,
                body_text TEXT DEFAULT '',
                status ENUM('pending','processing','sent','failed') DEFAULT 'pending',
                attempts INT DEFAULT 0,
                max_attempts INT DEFAULT 3,
                last_error TEXT DEFAULT NULL,
                next_retry_at DATETIME DEFAULT NULL,
                sent_at DATETIME DEFAULT NULL,
                krijuar_me TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                veprim VARCHAR(200) NOT NULL,
                target_type VARCHAR(100) DEFAULT NULL,
                target_id INT DEFAULT NULL,
                detaje JSON DEFAULT NULL,
                krijuar_me TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS Njoftimi (
                id_njoftimi INT AUTO_INCREMENT PRIMARY KEY,
                id_perdoruesi INT NOT NULL,
                mesazhi TEXT NOT NULL,
                lloji VARCHAR(50) DEFAULT 'general',
                is_read TINYINT(1) DEFAULT 0,
                krijuar_me TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$pdo->exec(" 
            CREATE TABLE IF NOT EXISTS support_messages (
                id_support_message INT AUTO_INCREMENT PRIMARY KEY,
                from_user_id INT DEFAULT NULL,
                from_name VARCHAR(160) NOT NULL,
                from_email VARCHAR(190) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('new','read','replied','resolved') NOT NULL DEFAULT 'new',
                last_reply_message TEXT DEFAULT NULL,
                replied_by INT DEFAULT NULL,
                replied_at DATETIME DEFAULT NULL,
                resolved_by INT DEFAULT NULL,
                resolved_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Helper: insert a test user and return their ID.
     */
    protected function createTestUser(array $overrides = []): int
    {
        $defaults = [
            'emri' => 'Test',
            'email' => 'test' . uniqid() . '@example.com',
            'fjalekalimi' => password_hash('Str0ng!Pass', PASSWORD_DEFAULT),
            'roli' => 'volunteer',
            'statusi_llogarise' => 'Aktiv',
            'verified' => 1,
            'email_notifications' => 1,
        ];
        $data = array_merge($defaults, $overrides);

        $stmt = self::$pdo->prepare(
            'INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise, verified, email_notifications)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['emri'], $data['email'], $data['fjalekalimi'],
            $data['roli'], $data['statusi_llogarise'], $data['verified'], $data['email_notifications'],
        ]);
        return (int) self::$pdo->lastInsertId();
    }
}
