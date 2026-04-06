<?php
/**
 * tests/Integration/UserManagementTest.php
 * ---------------------------------------------------
 * Integration tests for user-related database operations,
 * simulating registration, verification, and profile flows.
 * ---------------------------------------------------
 */

declare(strict_types=1);

require_once PROJECT_ROOT . '/includes/functions.php';
require_once __DIR__ . '/DatabaseTestCase.php';

class UserManagementTest extends DatabaseTestCase
{
    /** @test */
    public function can_create_user_with_hashed_password(): void
    {
        $email = 'newuser@example.com';
        $plainPassword = 'Str0ng!Pass';
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmt = self::$pdo->prepare(
            'INSERT INTO Perdoruesi (emri, email, fjalekalimi, roli, statusi_llogarise, verified)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(['Test', $email, $hash, 'volunteer', 'Aktiv', 0]);
        $userId = (int) self::$pdo->lastInsertId();

        $this->assertGreaterThan(0, $userId);

        // Verify password validates
        $row = self::$pdo->prepare('SELECT fjalekalimi FROM Perdoruesi WHERE id_perdoruesi = ?');
        $row->execute([$userId]);
        $stored = $row->fetchColumn();
        $this->assertTrue(password_verify($plainPassword, $stored));
    }

    /** @test */
    public function email_is_unique(): void
    {
        $email = 'unique@example.com';
        $this->createTestUser(['email' => $email]);

        $this->expectException(PDOException::class);
        $this->createTestUser(['email' => $email]);
    }

    /** @test */
    public function verification_token_flow(): void
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

        $userId = $this->createTestUser([
            'verified' => 0,
            'email' => 'verify@example.com',
        ]);

        // Set verification token
        self::$pdo->prepare(
            'UPDATE Perdoruesi SET verification_token = ?, verification_expires = ? WHERE id_perdoruesi = ?'
        )->execute([$tokenHash, $expires, $userId]);

        // Simulate verification
        $stmt = self::$pdo->prepare(
            'SELECT * FROM Perdoruesi WHERE id_perdoruesi = ? AND verification_token = ? AND verification_expires > NOW()'
        );
        $stmt->execute([$userId, $tokenHash]);
        $user = $stmt->fetch();

        $this->assertNotFalse($user);
        $this->assertSame(0, (int) $user['verified']);

        // Complete verification
        self::$pdo->prepare(
            'UPDATE Perdoruesi SET verified = 1, verification_token = NULL, verification_expires = NULL WHERE id_perdoruesi = ?'
        )->execute([$userId]);

        $stmt = self::$pdo->prepare('SELECT verified, verification_token FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$userId]);
        $verified = $stmt->fetch();

        $this->assertSame(1, (int) $verified['verified']);
        $this->assertNull($verified['verification_token']);
    }

    /** @test */
    public function password_reset_token_flow(): void
    {
        $userId = $this->createTestUser(['email' => 'reset@example.com']);
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        // Store reset token
        self::$pdo->prepare(
            'UPDATE Perdoruesi SET reset_token = ?, reset_expires = ? WHERE id_perdoruesi = ?'
        )->execute([$tokenHash, $expires, $userId]);

        // Verify token retrieval
        $stmt = self::$pdo->prepare(
            'SELECT id_perdoruesi FROM Perdoruesi WHERE email = ? AND reset_token = ? AND reset_expires > NOW()'
        );
        $stmt->execute(['reset@example.com', $tokenHash]);
        $row = $stmt->fetch();
        $this->assertSame($userId, (int) $row['id_perdoruesi']);

        // Reset password
        $newHash = password_hash('NewStr0ng!Pass', PASSWORD_DEFAULT);
        self::$pdo->prepare(
            'UPDATE Perdoruesi SET fjalekalimi = ?, reset_token = NULL, reset_expires = NULL WHERE id_perdoruesi = ?'
        )->execute([$newHash, $userId]);

        $stmt = self::$pdo->prepare('SELECT fjalekalimi, reset_token FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$userId]);
        $result = $stmt->fetch();

        $this->assertTrue(password_verify('NewStr0ng!Pass', $result['fjalekalimi']));
        $this->assertNull($result['reset_token']);
    }

    /** @test */
    public function email_notifications_default_is_on(): void
    {
        $userId = $this->createTestUser(['email' => 'default@example.com']);
        $stmt = self::$pdo->prepare('SELECT email_notifications FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$userId]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    /** @test */
    public function can_toggle_email_notifications(): void
    {
        $userId = $this->createTestUser(['email' => 'toggle@example.com', 'email_notifications' => 1]);

        // Toggle off
        self::$pdo->prepare('UPDATE Perdoruesi SET email_notifications = 0 WHERE id_perdoruesi = ?')
            ->execute([$userId]);
        $stmt = self::$pdo->prepare('SELECT email_notifications FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$userId]);
        $this->assertSame(0, (int) $stmt->fetchColumn());

        // Toggle on
        self::$pdo->prepare('UPDATE Perdoruesi SET email_notifications = 1 WHERE id_perdoruesi = ?')
            ->execute([$userId]);
        $stmt->execute([$userId]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    /** @test */
    public function user_status_transitions(): void
    {
        $userId = $this->createTestUser(['statusi_llogarise' => 'Aktiv']);

        // Block
        self::$pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'blocked' WHERE id_perdoruesi = ?")
            ->execute([$userId]);
        $status = self::$pdo->prepare('SELECT statusi_llogarise FROM Perdoruesi WHERE id_perdoruesi = ?');
        $status->execute([$userId]);
        $this->assertSame('blocked', $status->fetchColumn());

        // Unblock
        self::$pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'Aktiv' WHERE id_perdoruesi = ?")
            ->execute([$userId]);
        $status->execute([$userId]);
        $this->assertSame('Aktiv', $status->fetchColumn());

        // Deactivate
        self::$pdo->prepare("UPDATE Perdoruesi SET statusi_llogarise = 'deactivated' WHERE id_perdoruesi = ?")
            ->execute([$userId]);
        $status->execute([$userId]);
        $this->assertSame('deactivated', $status->fetchColumn());
    }

    /** @test */
    public function profile_color_defaults_to_emerald(): void
    {
        $userId = $this->createTestUser(['email' => 'color@example.com']);
        $stmt = self::$pdo->prepare('SELECT profile_color FROM Perdoruesi WHERE id_perdoruesi = ?');
        $stmt->execute([$userId]);
        $this->assertSame('emerald', $stmt->fetchColumn());
    }
}
