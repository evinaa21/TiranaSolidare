<?php
/**
 * tests/Integration/NotificationPreferenceTest.php
 * ---------------------------------------------------
 * Integration tests verifying the email notification
 * preference system end-to-end.
 * ---------------------------------------------------
 */

declare(strict_types=1);

require_once PROJECT_ROOT . '/includes/functions.php';
require_once __DIR__ . '/DatabaseTestCase.php';

class NotificationPreferenceTest extends DatabaseTestCase
{
    /** @test */
    public function notification_respected_when_opted_out(): void
    {
        $email = 'optout-test@example.com';
        $this->createTestUser(['email' => $email, 'email_notifications' => 0]);

        // Try sending a notification
        $result = send_notification_email($email, 'Test', 'Subject', 'Body');
        $this->assertTrue($result); // Returns true (skip, not error)

        // Email should NOT be in the queue
        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM email_queue WHERE to_email = '{$email}'"
        )->fetchColumn();
        $this->assertSame(0, $count, 'Email should not be queued for opted-out user');
    }

    /** @test */
    public function notification_sent_when_opted_in(): void
    {
        $email = 'optin-test@example.com';
        $this->createTestUser(['email' => $email, 'email_notifications' => 1]);

        $result = send_notification_email($email, 'Test', 'Subject', 'Body');
        $this->assertTrue($result);

        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM email_queue WHERE to_email = '{$email}'"
        )->fetchColumn();
        $this->assertSame(1, $count, 'Email should be queued for opted-in user');
    }

    /** @test */
    public function verification_email_always_sent_regardless_of_preference(): void
    {
        $email = 'verify-always@example.com';
        $this->createTestUser(['email' => $email, 'email_notifications' => 0]);

        // Verification emails bypass preference check
        $result = send_verification_email($email, 'Test', 'http://localhost/verify?token=abc');
        $this->assertTrue($result);

        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM email_queue WHERE to_email = '{$email}'"
        )->fetchColumn();
        $this->assertSame(1, $count, 'Verification emails should always be sent');
    }

    /** @test */
    public function password_reset_email_always_sent_regardless_of_preference(): void
    {
        $email = 'reset-always@example.com';
        $this->createTestUser(['email' => $email, 'email_notifications' => 0]);

        // Password reset emails bypass preference check
        $result = send_password_reset_email($email, 'Test', 'http://localhost/reset?token=abc');
        $this->assertTrue($result);

        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM email_queue WHERE to_email = '{$email}'"
        )->fetchColumn();
        $this->assertSame(1, $count, 'Password reset emails should always be sent');
    }

    /** @test */
    public function toggling_preference_changes_behavior(): void
    {
        $email = 'toggle-test@example.com';
        $userId = $this->createTestUser(['email' => $email, 'email_notifications' => 1]);

        // Should send while opted in
        send_notification_email($email, 'Test', 'Before toggle', 'Body');
        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM email_queue WHERE to_email = '{$email}'"
        )->fetchColumn();
        $this->assertSame(1, $count);

        // Toggle off
        self::$pdo->prepare('UPDATE Perdoruesi SET email_notifications = 0 WHERE id_perdoruesi = ?')
            ->execute([$userId]);

        // Should NOT send while opted out
        send_notification_email($email, 'Test', 'After toggle', 'Body');
        $count = (int) self::$pdo->query(
            "SELECT COUNT(*) FROM email_queue WHERE to_email = '{$email}'"
        )->fetchColumn();
        $this->assertSame(1, $count, 'Count should still be 1 (no new email)');
    }
}
