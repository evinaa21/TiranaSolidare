<?php
/**
 * tests/Unit/ValidationTest.php
 * ---------------------------------------------------
 * Tests for input validation functions:
 *  - validate_length()
 *  - validate_password_strength()
 *  - validate_image_url()
 *  - is_safe_redirect()
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class ValidationTest extends TestCase
{
    // ─────────────────────────────────────────
    //  validate_length()
    // ─────────────────────────────────────────

    /** @test */
    public function validate_length_returns_null_for_valid_input(): void
    {
        $this->assertNull(validate_length('hello', 1, 10, 'test'));
    }

    /** @test */
    public function validate_length_returns_null_at_exact_min(): void
    {
        $this->assertNull(validate_length('ab', 2, 10, 'test'));
    }

    /** @test */
    public function validate_length_returns_null_at_exact_max(): void
    {
        $this->assertNull(validate_length('abcde', 1, 5, 'test'));
    }

    /** @test */
    public function validate_length_rejects_too_short(): void
    {
        $result = validate_length('a', 3, 10, 'emri');
        $this->assertNotNull($result);
        $this->assertStringContainsString('3', $result);
        $this->assertStringContainsString('emri', $result);
    }

    /** @test */
    public function validate_length_rejects_too_long(): void
    {
        $result = validate_length('abcdefghijk', 1, 5, 'emri');
        $this->assertNotNull($result);
        $this->assertStringContainsString('5', $result);
    }

    /** @test */
    public function validate_length_is_multibyte_safe(): void
    {
        // Albanian characters: Përshëndetje = 13 chars
        $this->assertNull(validate_length('Përshëndetje', 1, 20, 'test'));
        $this->assertNotNull(validate_length('Përshëndetje', 1, 5, 'test'));
    }

    // ─────────────────────────────────────────
    //  validate_password_strength()
    // ─────────────────────────────────────────

    /** @test */
    public function password_strength_accepts_strong_password(): void
    {
        $this->assertNull(validate_password_strength('Str0ng!Pass'));
    }

    /** @test */
    public function password_strength_rejects_short_password(): void
    {
        $result = validate_password_strength('Ab1!');
        $this->assertNotNull($result);
        $this->assertStringContainsString('8', $result);
    }

    /** @test */
    public function password_strength_rejects_password_over_128_chars(): void
    {
        $long = str_repeat('Aa1!', 33); // 132 chars
        $this->assertNotNull(validate_password_strength($long));
    }

    /** @test */
    public function password_strength_rejects_no_lowercase(): void
    {
        $result = validate_password_strength('ABCDEFG1!');
        $this->assertNotNull($result);
    }

    /** @test */
    public function password_strength_rejects_no_uppercase(): void
    {
        $result = validate_password_strength('abcdefg1!');
        $this->assertNotNull($result);
    }

    /** @test */
    public function password_strength_rejects_no_digit(): void
    {
        $result = validate_password_strength('Abcdefg!');
        $this->assertNotNull($result);
    }

    /** @test */
    public function password_strength_rejects_no_symbol(): void
    {
        $result = validate_password_strength('Abcdefg1');
        $this->assertNotNull($result);
    }

    /** @test */
    public function password_strength_rejects_spaces(): void
    {
        $result = validate_password_strength('Abc def1!');
        $this->assertNotNull($result);
    }

    /** @test */
    public function password_strength_accepts_exactly_8_chars(): void
    {
        $this->assertNull(validate_password_strength('Ab1!cdef'));
    }

    /** @test */
    public function password_strength_accepts_exactly_128_chars(): void
    {
        // 128 chars: 31 * 'Aa1!' + 'Aa1!' = 128
        $pass = str_repeat('Aa1!', 32); // 128 chars
        $this->assertNull(validate_password_strength($pass));
    }

    // ─────────────────────────────────────────
    //  validate_image_url()
    // ─────────────────────────────────────────

    /** @test */
    public function image_url_accepts_null(): void
    {
        $this->assertTrue(validate_image_url(null));
    }

    /** @test */
    public function image_url_accepts_empty_string(): void
    {
        $this->assertTrue(validate_image_url(''));
    }

    /** @test */
    public function image_url_accepts_https(): void
    {
        $this->assertTrue(validate_image_url('https://example.com/image.jpg'));
    }

    /** @test */
    public function image_url_accepts_http(): void
    {
        $this->assertTrue(validate_image_url('http://example.com/image.png'));
    }

    /** @test */
    public function image_url_rejects_javascript_scheme(): void
    {
        $this->assertFalse(validate_image_url('javascript:alert(1)'));
    }

    /** @test */
    public function image_url_rejects_data_scheme(): void
    {
        $this->assertFalse(validate_image_url('data:text/html,<script>alert(1)</script>'));
    }

    /** @test */
    public function image_url_rejects_relative_path(): void
    {
        $this->assertFalse(validate_image_url('/images/test.jpg'));
    }

    /** @test */
    public function image_url_rejects_bare_string(): void
    {
        $this->assertFalse(validate_image_url('not-a-url'));
    }

    // ─────────────────────────────────────────
    //  is_safe_redirect()
    // ─────────────────────────────────────────

    /** @test */
    public function safe_redirect_accepts_valid_internal_path(): void
    {
        $this->assertTrue(is_safe_redirect('/TiranaSolidare/views/login.php'));
    }

    /** @test */
    public function safe_redirect_accepts_path_with_query(): void
    {
        $this->assertTrue(is_safe_redirect('/TiranaSolidare/views/login.php?error=1'));
    }

    /** @test */
    public function safe_redirect_rejects_external_url(): void
    {
        $this->assertFalse(is_safe_redirect('https://evil.com'));
    }

    /** @test */
    public function safe_redirect_rejects_path_traversal(): void
    {
        $this->assertFalse(is_safe_redirect('/TiranaSolidare/../etc/passwd'));
    }

    /** @test */
    public function safe_redirect_rejects_double_slash(): void
    {
        $this->assertFalse(is_safe_redirect('/TiranaSolidare//evil.com'));
    }

    /** @test */
    public function safe_redirect_rejects_wrong_prefix(): void
    {
        $this->assertFalse(is_safe_redirect('/other-app/views/page.php'));
    }
}
