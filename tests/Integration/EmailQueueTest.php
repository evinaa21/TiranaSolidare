<?php
/**
 * tests/Integration/EmailQueueTest.php
 * ---------------------------------------------------
 * Integration tests for email queuing functions:
 *  - queue_email()
 *  - send_verification_email()
 *  - send_password_reset_email()
 *  - send_notification_email()
 * ---------------------------------------------------
 */

declare(strict_types=1);

require_once PROJECT_ROOT . '/includes/functions.php';
require_once __DIR__ . '/DatabaseTestCase.php';

class EmailQueueTest extends DatabaseTestCase
{
    /** @test */
    public function queue_email_inserts_into_database(): void
    {
        $result = queue_email('user@example.com', 'Test User', 'Hello', '<p>World</p>', 'World');
        $this->assertTrue($result);

        $row = self::$pdo->query("SELECT * FROM email_queue ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame('user@example.com', $row['to_email']);
        $this->assertSame('Test User', $row['to_name']);
        $this->assertSame('Hello', $row['subject']);
        $this->assertSame('<p>World</p>', $row['body_html']);
        $this->assertSame('World', $row['body_text']);
        $this->assertSame('pending', $row['status']);
    }

    /** @test */
    public function queue_email_sets_max_attempts(): void
    {
        queue_email('user@example.com', 'User', 'Sub', '<p>Body</p>', '', 5);
        $row = self::$pdo->query("SELECT max_attempts FROM email_queue ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame(5, (int) $row['max_attempts']);
    }

    /** @test */
    public function send_verification_email_queues_correctly(): void
    {
        $result = send_verification_email('new@example.com', 'New User', 'http://localhost/verify?token=abc123');
        $this->assertTrue($result);

        $row = self::$pdo->query("SELECT * FROM email_queue ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame('new@example.com', $row['to_email']);
        $this->assertStringContainsString('Konfirmo email', $row['subject']);
        $this->assertStringContainsString('verify?token=abc123', $row['body_html']);
        $this->assertStringContainsString('New User', $row['body_html']);
    }

    /** @test */
    public function send_verification_email_escapes_xss_in_name(): void
    {
        send_verification_email('test@example.com', '<script>alert(1)</script>', 'http://localhost/verify');
        $row = self::$pdo->query("SELECT body_html FROM email_queue ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertStringNotContainsString('<script>', $row['body_html']);
        $this->assertStringContainsString('&lt;script&gt;', $row['body_html']);
    }

    /** @test */
    public function send_verification_email_escapes_url(): void
    {
        send_verification_email('test@example.com', 'User', 'http://localhost/verify?a=1&b=2');
        $row = self::$pdo->query("SELECT body_html FROM email_queue ORDER BY id DESC LIMIT 1")->fetch();
        // ampersand should be encoded as &amp; in HTML context
        $this->assertStringContainsString('&amp;b=2', $row['body_html']);
    }

    /** @test */
    public function send_password_reset_email_queues_correctly(): void
    {
        $result = send_password_reset_email('user@example.com', 'User Name', 'http://localhost/reset?token=xyz');
        $this->assertTrue($result);

        $row = self::$pdo->query("SELECT * FROM email_queue ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertStringContainsString('Rivendos fjalëkalimin', $row['subject']);
        $this->assertStringContainsString('reset?token=xyz', $row['body_html']);
    }

    /** @test */
    public function send_notification_email_queues_for_opted_in_user(): void
    {
        $this->createTestUser([
            'email' => 'opted-in@example.com',
            'email_notifications' => 1,
        ]);

        $result = send_notification_email('opted-in@example.com', 'User', 'Subject', 'Message body');
        $this->assertTrue($result);

        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM email_queue WHERE to_email = 'opted-in@example.com'")->fetchColumn();
        $this->assertSame(1, $count);
    }

    /** @test */
    public function send_notification_email_skips_opted_out_user(): void
    {
        $this->createTestUser([
            'email' => 'opted-out@example.com',
            'email_notifications' => 0,
        ]);

        $result = send_notification_email('opted-out@example.com', 'User', 'Subject', 'Message body');
        $this->assertTrue($result); // Returns true (silently skips)

        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM email_queue WHERE to_email = 'opted-out@example.com'")->fetchColumn();
        $this->assertSame(0, $count);
    }

    /** @test */
    public function send_notification_email_escapes_subject_in_html(): void
    {
        $this->createTestUser(['email' => 'xss@example.com']);

        send_notification_email('xss@example.com', 'User', '<img onerror=alert(1)>', 'Message');

        $row = self::$pdo->query("SELECT body_html FROM email_queue ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertStringNotContainsString('<img', $row['body_html']);
        $this->assertStringContainsString('&lt;img', $row['body_html']);
    }

    /** @test */
    public function send_notification_email_includes_unsubscribe_link(): void
    {
        $this->createTestUser(['email' => 'unsub@example.com']);

        send_notification_email('unsub@example.com', 'User', 'Test', 'Test message');

        $row = self::$pdo->query("SELECT body_html FROM email_queue ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertStringContainsString('aktivizoni njoftimet', mb_strtolower($row['body_html']));
    }

    /** @test */
    public function send_notification_email_sends_to_unknown_email(): void
    {
        // User doesn't exist in DB — should still send (preference check fails gracefully)
        $result = send_notification_email('unknown@example.com', 'Unknown', 'Test', 'Body');
        $this->assertTrue($result);

        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM email_queue WHERE to_email = 'unknown@example.com'")->fetchColumn();
        $this->assertSame(1, $count);
    }
}
