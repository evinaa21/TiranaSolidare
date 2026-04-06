<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

final class PublicMetricsConsistencyTest extends TestCase
{
    private static ?PDO $pdo = null;
    private static string $dbName = '';

    public static function setUpBeforeClass(): void
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3307';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';

        self::$dbName = 'tiranasolidare_metrics_test_' . bin2hex(random_bytes(4));
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
            roli VARCHAR(30) NOT NULL DEFAULT 'volunteer',
            statusi_llogarise VARCHAR(30) NOT NULL DEFAULT 'active'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::$pdo->exec("CREATE TABLE Kerkesa_per_Ndihme (
            id_kerkese_ndihme INT AUTO_INCREMENT PRIMARY KEY,
            id_perdoruesi INT NOT NULL,
            titulli VARCHAR(255) NOT NULL DEFAULT 'Test',
            tipi VARCHAR(30) NOT NULL,
            statusi VARCHAR(30) NOT NULL,
            moderation_status VARCHAR(30) DEFAULT 'approved',
            matching_mode VARCHAR(20) DEFAULT 'open',
            capacity_total INT DEFAULT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::$pdo->exec("CREATE TABLE Aplikimi_Kerkese (
            id_aplikimi_kerkese INT AUTO_INCREMENT PRIMARY KEY,
            id_kerkese_ndihme INT NOT NULL,
            id_perdoruesi INT NOT NULL,
            statusi VARCHAR(30) NOT NULL DEFAULT 'pending',
            aplikuar_me DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::$pdo->exec("CREATE TABLE Eventi (
            id_eventi INT AUTO_INCREMENT PRIMARY KEY,
            titulli VARCHAR(255) NOT NULL DEFAULT 'Event',
            statusi VARCHAR(30) NOT NULL DEFAULT 'active',
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            data DATETIME NOT NULL,
            latitude DECIMAL(10,7) DEFAULT NULL,
            longitude DECIMAL(10,7) DEFAULT NULL
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
        self::$pdo->exec('TRUNCATE TABLE Aplikimi_Kerkese');
        self::$pdo->exec('TRUNCATE TABLE Kerkesa_per_Ndihme');
        self::$pdo->exec('TRUNCATE TABLE Eventi');
        self::$pdo->exec('TRUNCATE TABLE Perdoruesi');
        $GLOBALS['pdo'] = self::$pdo;
    }

    public function test_help_request_summary_keeps_public_counts_consistent(): void
    {
        $ownerId = $this->insertUser('volunteer', 'active');

        $this->insertHelpRequest($ownerId, 'request', 'open', 'approved');
        $this->insertHelpRequest($ownerId, 'offer', 'open', 'approved');
        $this->insertHelpRequest($ownerId, 'request', 'completed', 'approved');
        $filledOfferId = $this->insertHelpRequest($ownerId, 'offer', 'open', 'approved', 'single', 1);
        $this->insertHelpRequestApplication($filledOfferId, $ownerId, 'approved');
        $this->insertHelpRequest($ownerId, 'offer', 'cancelled', 'approved');
        $this->insertHelpRequest($ownerId, 'request', 'open', 'pending_review');

        $approved = ts_help_request_summary(self::$pdo, ['approved_only' => true]);
        $all = ts_help_request_summary(self::$pdo);

        $this->assertSame(5, $approved['all_total']);
        $this->assertSame(2, $approved['request_total']);
        $this->assertSame(3, $approved['offer_total']);
        $this->assertSame(3, $approved['active_total']);
        $this->assertSame(2, $approved['open_total']);
        $this->assertSame(1, $approved['filled_total']);
        $this->assertSame(1, $approved['completed_total']);
        $this->assertSame(1, $approved['cancelled_total']);
        $this->assertSame(1, $approved['request_open']);
        $this->assertSame(1, $approved['offer_open']);
        $this->assertSame(1, $approved['offer_filled']);
        $this->assertSame(1, $approved['request_completed']);
        $this->assertSame(1, $approved['offer_cancelled']);

        $this->assertSame(6, $all['all_total']);
        $this->assertSame(1, $all['pending_moderation']);
    }

    public function test_count_active_volunteers_excludes_blocked_users_and_admins(): void
    {
        $this->insertUser('volunteer', 'active');
        $this->insertUser('volunteer', 'blocked');
        $this->insertUser('admin', 'active');

        $this->assertSame(1, ts_count_active_volunteers(self::$pdo));
    }

    public function test_public_event_filter_sql_excludes_archived_pending_and_cancelled_rows(): void
    {
        $this->insertEvent('active', 0, '+2 days');
        $this->insertEvent('completed', 0, '-2 days');
        $this->insertEvent('pending_review', 0, '+1 day');
        $this->insertEvent('cancelled', 0, '+1 day');
        $this->insertEvent('active', 1, '+1 day');

        $count = (int) self::$pdo->query('SELECT COUNT(*) FROM Eventi WHERE ' . ts_public_event_filter_sql('Eventi'))->fetchColumn();

        $this->assertSame(2, $count);
    }

    private function insertUser(string $role, string $accountStatus): int
    {
        $stmt = self::$pdo->prepare(
            'INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            'Test User',
            uniqid('metrics_', true) . '@example.com',
            password_hash('Str0ng!Pass', PASSWORD_DEFAULT),
            $role,
            $accountStatus,
        ]);

        return (int) self::$pdo->lastInsertId();
    }

    private function insertHelpRequest(
        int $ownerId,
        string $type,
        string $status,
        string $moderationStatus,
        string $matchingMode = 'open',
        ?int $capacityTotal = null
    ): int
    {
        $stmt = self::$pdo->prepare(
            'INSERT INTO Kerkesa_per_Ndihme (id_perdoruesi, titulli, tipi, statusi, moderation_status, matching_mode, capacity_total, latitude, longitude)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $ownerId,
            ucfirst($type) . ' ' . ucfirst($status),
            $type,
            $status,
            $moderationStatus,
            $matchingMode,
            $capacityTotal,
            41.3275000,
            19.8187000,
        ]);

        return (int) self::$pdo->lastInsertId();
    }

    private function insertHelpRequestApplication(int $requestId, int $userId, string $status): void
    {
        $stmt = self::$pdo->prepare(
            'INSERT INTO Aplikimi_Kerkese (id_kerkese_ndihme, id_perdoruesi, statusi) VALUES (?, ?, ?)'
        );
        $stmt->execute([$requestId, $userId, $status]);
    }

    private function insertEvent(string $status, int $isArchived, string $relativeDate): void
    {
        $stmt = self::$pdo->prepare(
            'INSERT INTO Eventi (titulli, statusi, is_archived, data, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            'Event ' . $status,
            $status,
            $isArchived,
            (new DateTimeImmutable($relativeDate))->format('Y-m-d H:i:s'),
            41.3275000,
            19.8187000,
        ]);
    }
}