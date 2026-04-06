<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

final class OrganizationApplicationFlowTest extends TestCase
{
    private static ?PDO $pdo = null;
    private static string $dbName = '';

    public static function setUpBeforeClass(): void
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3307';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        self::$dbName = 'tiranasolidare_org_test_' . bin2hex(random_bytes(4));
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        self::$pdo->exec('CREATE DATABASE `' . self::$dbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        self::$pdo->exec('USE `' . self::$dbName . '`');
        self::$pdo->exec("CREATE TABLE Perdoruesi (
            id_perdoruesi INT AUTO_INCREMENT PRIMARY KEY,
            emri VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            fjalekalimi VARCHAR(255) NOT NULL,
            roli ENUM('admin','volunteer','super_admin','organizer') DEFAULT 'volunteer',
            statusi_llogarise VARCHAR(30) DEFAULT 'active',
            verified TINYINT(1) DEFAULT 1,
            organization_name VARCHAR(160) NULL,
            organization_website VARCHAR(255) NULL,
            organization_phone VARCHAR(40) NULL,
            organization_description TEXT NULL,
            krijuar_me TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$pdo->exec("CREATE TABLE organization_applications (
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
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$pdo instanceof PDO && self::$dbName !== '') {
            self::$pdo->exec('DROP DATABASE IF EXISTS `' . self::$dbName . '`');
        }
    }

    protected function setUp(): void
    {
        self::$pdo->exec('TRUNCATE TABLE organization_applications');
        self::$pdo->exec('TRUNCATE TABLE Perdoruesi');
        $GLOBALS['pdo'] = self::$pdo;
    }

    public function test_approved_application_promotes_user_to_organizer(): void
    {
        $applicantId = $this->createUser('volunteer', 'applicant@example.com');
        $reviewerId = $this->createUser('super_admin', 'reviewer@example.com');

        $applicationId = ts_submit_organization_application(self::$pdo, $applicantId, [
            'organization_name' => 'Qendra Rinore Tirane',
            'contact_name' => 'Arta Kola',
            'contact_email' => 'arta@example.com',
            'contact_phone' => '+355691234000',
            'website' => 'https://example.org',
            'description' => 'Organizate komunitare qe menaxhon evente rinore dhe edukative.',
        ]);

        $reviewed = ts_review_organization_application(self::$pdo, $applicationId, $reviewerId, 'approved');

        $this->assertSame('approved', $reviewed['status']);

        $stmt = self::$pdo->prepare('SELECT roli, organization_name, organization_website FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$applicantId]);
        $user = $stmt->fetch();

        $this->assertSame('organizer', $user['roli']);
        $this->assertSame('Qendra Rinore Tirane', $user['organization_name']);
        $this->assertSame('https://example.org', $user['organization_website']);
    }

    public function test_rejected_application_keeps_user_as_volunteer(): void
    {
        $applicantId = $this->createUser('volunteer', 'applicant2@example.com');
        $reviewerId = $this->createUser('super_admin', 'reviewer2@example.com');

        $applicationId = ts_submit_organization_application(self::$pdo, $applicantId, [
            'organization_name' => 'Shoqata Test',
            'contact_name' => 'Elira Meta',
            'contact_email' => 'elira@example.com',
            'contact_phone' => '',
            'website' => '',
            'description' => 'Shoqatë prove për të testuar refuzimin e aplikimit si organizatë.',
        ]);

        $reviewed = ts_review_organization_application(self::$pdo, $applicationId, $reviewerId, 'rejected', 'Duhet më shumë dokumentim.');

        $this->assertSame('rejected', $reviewed['status']);

        $stmt = self::$pdo->prepare('SELECT roli, organization_name FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$applicantId]);
        $user = $stmt->fetch();

        $this->assertSame('volunteer', $user['roli']);
        $this->assertNull($user['organization_name']);
    }

    private function createUser(string $role, string $email): int
    {
        $stmt = self::$pdo->prepare(
            'INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise, verified)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            'Test User',
            $email,
            password_hash('Str0ng!Pass', PASSWORD_DEFAULT),
            $role,
            'active',
        ]);

        return (int) self::$pdo->lastInsertId();
    }
}