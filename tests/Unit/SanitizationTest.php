<?php
/**
 * tests/Unit/SanitizationTest.php
 * ---------------------------------------------------
 * Tests for the e() XSS-escape helper.
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Load just the functions file (it sets session ini but won't break CLI)
require_once PROJECT_ROOT . '/includes/functions.php';

class SanitizationTest extends TestCase
{
    /** @test */
    public function e_escapes_html_entities(): void
    {
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
    }

    /** @test */
    public function e_escapes_double_quotes(): void
    {
        $this->assertSame('&quot;hello&quot;', e('"hello"'));
    }

    /** @test */
    public function e_escapes_single_quotes(): void
    {
        $this->assertSame('&#039;hello&#039;', e("'hello'"));
    }

    /** @test */
    public function e_escapes_ampersand(): void
    {
        $this->assertSame('&amp;amp;', e('&amp;'));
    }

    /** @test */
    public function e_handles_empty_string(): void
    {
        $this->assertSame('', e(''));
    }

    /** @test */
    public function e_preserves_normal_text(): void
    {
        $this->assertSame('Hello World 123', e('Hello World 123'));
    }

    /** @test */
    public function e_handles_utf8(): void
    {
        $this->assertSame('Përshëndetje', e('Përshëndetje'));
    }

    /** @test */
    public function e_handles_complex_xss_vector(): void
    {
        $input = '<img src=x onerror="alert(document.cookie)">';
        $result = e($input);
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
    }
}
