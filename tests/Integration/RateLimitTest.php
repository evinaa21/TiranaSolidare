<?php
/**
 * tests/Integration/RateLimitTest.php
 * ---------------------------------------------------
 * Integration tests for check_rate_limit() which
 * requires a real database.
 * ---------------------------------------------------
 */

declare(strict_types=1);

require_once PROJECT_ROOT . '/includes/functions.php';
require_once __DIR__ . '/DatabaseTestCase.php';

class RateLimitTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Simulate an IP address
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    /** @test */
    public function rate_limit_allows_first_attempt(): void
    {
        $this->assertTrue(check_rate_limit('test_action', 5, 900));
    }

    /** @test */
    public function rate_limit_allows_up_to_max_attempts(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->assertTrue(check_rate_limit('login', 5, 900));
        }
        // 5th attempt should still be allowed (count was 4 before this call)
        $this->assertTrue(check_rate_limit('login', 5, 900));
    }

    /** @test */
    public function rate_limit_blocks_after_max_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            check_rate_limit('login', 5, 900);
        }
        // 6th attempt should be blocked
        $this->assertFalse(check_rate_limit('login', 5, 900));
    }

    /** @test */
    public function rate_limit_is_per_action(): void
    {
        // Max out 'login'
        for ($i = 0; $i < 5; $i++) {
            check_rate_limit('login', 5, 900);
        }
        // Different action should still be allowed
        $this->assertTrue(check_rate_limit('register', 5, 900));
    }

    /** @test */
    public function rate_limit_is_per_ip(): void
    {
        // Max out for 127.0.0.1
        for ($i = 0; $i < 5; $i++) {
            check_rate_limit('login', 5, 900);
        }
        // Different IP should still be allowed
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $this->assertTrue(check_rate_limit('login', 5, 900));
    }

    /** @test */
    public function rate_limit_logs_attempts_in_database(): void
    {
        check_rate_limit('test_action', 5, 900);

        $count = self::$pdo->query("SELECT COUNT(*) FROM rate_limit_log WHERE action = 'test_action'")->fetchColumn();
        $this->assertSame(1, (int) $count);
    }

    /** @test */
    public function rate_limit_ignores_x_forwarded_for_for_security(): void
    {
        // By design, check_rate_limit uses REMOTE_ADDR only.
        // HTTP_X_FORWARDED_FOR is attacker-controlled without a verified proxy whitelist.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 192.168.1.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        check_rate_limit('test_action', 5, 900);

        $row = self::$pdo->query("SELECT ip FROM rate_limit_log ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame('127.0.0.1', $row['ip'], 'Rate limiter must use REMOTE_ADDR, not X-Forwarded-For');
    }
}
