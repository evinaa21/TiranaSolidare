<?php
/**
 * tests/Unit/SessionHelpersTest.php
 * ---------------------------------------------------
 * Tests for session/auth helper functions:
 *  - is_admin()
 *  - app_base_url()
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class SessionHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        // Clean session state for each test
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Restore env
        putenv('APP_URL=http://localhost/TiranaSolidare');
    }

    // ─────────────────────────────────────────
    //  is_admin()
    // ─────────────────────────────────────────

    /** @test */
    public function is_admin_returns_true_for_admin_role(): void
    {
        $_SESSION['roli'] = 'admin';
        $this->assertTrue(is_admin());
    }

    /** @test */
    public function is_admin_returns_false_for_volunteer_role(): void
    {
        $_SESSION['roli'] = 'volunteer';
        $this->assertFalse(is_admin());
    }

    /** @test */
    public function is_admin_returns_false_when_no_role_set(): void
    {
        $this->assertFalse(is_admin());
    }

    /** @test */
    public function is_admin_returns_true_for_case_mismatch(): void
    {
        $_SESSION['roli'] = 'Admin'; // Capital A — normalized by ts_normalize_value
        $this->assertTrue(is_admin());
    }

    // ─────────────────────────────────────────
    //  app_base_url()
    // ─────────────────────────────────────────

    /** @test */
    public function app_base_url_reads_from_env(): void
    {
        putenv('APP_URL=https://tiranasolidare.al');
        $this->assertSame('https://tiranasolidare.al', app_base_url());
    }

    /** @test */
    public function app_base_url_strips_trailing_slash(): void
    {
        putenv('APP_URL=https://tiranasolidare.al/');
        $this->assertSame('https://tiranasolidare.al', app_base_url());
    }

    /** @test */
    public function app_base_url_falls_back_to_localhost(): void
    {
        putenv('APP_URL=');
        // In CLI mode without proper SERVER vars, should still return something
        $url = app_base_url();
        $this->assertStringStartsWith('http', $url);
    }
}
