<?php
declare(strict_types=1);

require_once PROJECT_ROOT . '/includes/functions.php';
require_once __DIR__ . '/DatabaseTestCase.php';

final class SupportInboxTest extends DatabaseTestCase
{
    public function test_store_support_message_persists_a_new_record(): void
    {
        $userId = $this->createTestUser(['email' => 'sender@example.com']);

        $messageId = ts_store_support_message(
            self::$pdo,
            'Dergues Test',
            'sender@example.com',
            'Kam nevojë për ndihmë',
            'Ky është mesazhi i kontaktit.',
            $userId
        );

        $this->assertGreaterThan(0, $messageId);

        $stmt = self::$pdo->prepare('SELECT * FROM support_messages WHERE id_support_message = ?');
        $stmt->execute([$messageId]);
        $row = $stmt->fetch();

        $this->assertSame('Dergues Test', $row['from_name']);
        $this->assertSame('sender@example.com', $row['from_email']);
        $this->assertSame('new', $row['status']);
    }

    public function test_notify_admins_about_support_message_creates_notifications(): void
    {
        $adminId = $this->createTestUser([
            'email' => 'admin@example.com',
            'roli' => 'admin',
        ]);
        $this->createTestUser([
            'email' => 'volunteer@example.com',
            'roli' => 'volunteer',
        ]);

        $messageId = ts_store_support_message(
            self::$pdo,
            'Dergues Test',
            'sender@example.com',
            'Subjekti',
            'Mesazhi',
            null
        );

        $count = ts_notify_admins_about_support_message(self::$pdo, $messageId, 'Subjekti', 'Dergues Test', false);

        $this->assertSame(1, $count);

        $stmt = self::$pdo->prepare('SELECT id_perdoruesi, mesazhi, lloji FROM Njoftimi WHERE id_perdoruesi = ?');
        $stmt->execute([$adminId]);
        $row = $stmt->fetch();

        $this->assertSame($adminId, (int) $row['id_perdoruesi']);
        $this->assertSame('support_message', $row['lloji']);
        $this->assertStringContainsString('Subjekti', $row['mesazhi']);
    }
}