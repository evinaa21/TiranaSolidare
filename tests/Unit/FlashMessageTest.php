<?php
/**
 * tests/Unit/FlashMessageTest.php
 * ---------------------------------------------------
 * Tests for flash message helpers:
 *  - set_flash()
 *  - get_flash()
 *  - render_flash()
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class FlashMessageTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION['flash'] = [];
    }

    /** @test */
    public function set_flash_stores_message_in_session(): void
    {
        set_flash('success', 'Operacioni u krye me sukses.');
        $this->assertSame('Operacioni u krye me sukses.', $_SESSION['flash']['success']);
    }

    /** @test */
    public function get_flash_returns_and_clears_message(): void
    {
        set_flash('error', 'Gabim!');
        $msg = get_flash('error');
        $this->assertSame('Gabim!', $msg);
        // Second call should return null (cleared)
        $this->assertNull(get_flash('error'));
    }

    /** @test */
    public function get_flash_returns_null_for_missing_key(): void
    {
        $this->assertNull(get_flash('nonexistent_key'));
    }

    /** @test */
    public function render_flash_outputs_alert_html(): void
    {
        set_flash('success', 'U krye!');
        ob_start();
        render_flash('success', 'success');
        $output = ob_get_clean();

        $this->assertStringContainsString('alert-success', $output);
        $this->assertStringContainsString('U krye!', $output);
        $this->assertStringContainsString('btn-close', $output);
    }

    /** @test */
    public function render_flash_outputs_nothing_when_no_flash(): void
    {
        ob_start();
        render_flash('error', 'danger');
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    /** @test */
    public function render_flash_escapes_xss_in_message(): void
    {
        set_flash('error', '<script>alert(1)</script>');
        ob_start();
        render_flash('error', 'danger');
        $output = ob_get_clean();

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    /** @test */
    public function set_flash_overwrites_previous_value(): void
    {
        set_flash('info', 'First');
        set_flash('info', 'Second');
        $this->assertSame('Second', get_flash('info'));
    }
}
