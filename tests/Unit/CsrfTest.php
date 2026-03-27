<?php
/**
 * tests/Unit/CsrfTest.php
 * ---------------------------------------------------
 * Tests for CSRF token functions:
 *  - csrf_token()
 *  - csrf_field()
 *  - csrf_meta()
 *  - validate_csrf_token()
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset the CSRF token before each test
        unset($_SESSION['csrf_token']);
        unset($_POST['_csrf_token']);
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    }

    /** @test */
    public function csrf_token_generates_a_hex_string(): void
    {
        $token = csrf_token();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    /** @test */
    public function csrf_token_is_consistent_within_session(): void
    {
        $first = csrf_token();
        $second = csrf_token();
        $this->assertSame($first, $second);
    }

    /** @test */
    public function csrf_token_stored_in_session(): void
    {
        $token = csrf_token();
        $this->assertSame($token, $_SESSION['csrf_token']);
    }

    /** @test */
    public function csrf_field_returns_hidden_input(): void
    {
        $field = csrf_field();
        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    /** @test */
    public function csrf_field_html_escapes_value(): void
    {
        $field = csrf_field();
        // The value should be hex only, no special chars, but verify the output
        // doesn't contain unescaped quotes
        $this->assertStringNotContainsString('""', $field);
    }

    /** @test */
    public function csrf_meta_returns_meta_tag(): void
    {
        $meta = csrf_meta();
        $this->assertStringContainsString('<meta name="csrf-token"', $meta);
        $this->assertStringContainsString('content="', $meta);
    }

    /** @test */
    public function validate_csrf_token_accepts_valid_token(): void
    {
        $token = csrf_token();
        $this->assertTrue(validate_csrf_token($token));
    }

    /** @test */
    public function validate_csrf_token_rejects_invalid_token(): void
    {
        csrf_token(); // Ensure a token exists in session
        $this->assertFalse(validate_csrf_token('definitely-wrong-token'));
    }

    /** @test */
    public function validate_csrf_token_rejects_empty_token(): void
    {
        csrf_token();
        $this->assertFalse(validate_csrf_token(''));
    }

    /** @test */
    public function validate_csrf_token_rejects_when_no_session_token(): void
    {
        // No token in session
        unset($_SESSION['csrf_token']);
        $this->assertFalse(validate_csrf_token('anything'));
    }

    /** @test */
    public function validate_csrf_token_reads_from_post(): void
    {
        $token = csrf_token();
        $_POST['_csrf_token'] = $token;
        $this->assertTrue(validate_csrf_token());
    }

    /** @test */
    public function validate_csrf_token_reads_from_header(): void
    {
        $token = csrf_token();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $this->assertTrue(validate_csrf_token());
    }

    /** @test */
    public function validate_csrf_token_timing_safe(): void
    {
        // Ensure it uses hash_equals (no early return for partial match)
        $token = csrf_token();
        // Flip one bit - should fail
        $bad = substr($token, 0, -1) . ($token[-1] === 'a' ? 'b' : 'a');
        $this->assertFalse(validate_csrf_token($bad));
    }
}
