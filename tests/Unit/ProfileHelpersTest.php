<?php
/**
 * tests/Unit/ProfileHelpersTest.php
 * ---------------------------------------------------
 * Tests for profile-related pure functions:
 *  - ts_profile_color_palette()
 *  - ts_resolve_profile_color()
 *  - ts_slugify()
 *  - ts_parse_public_profile_id()
 *  - ts_public_profile_url()
 *  - generate_upload_filename()
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class ProfileHelpersTest extends TestCase
{
    // ─────────────────────────────────────────
    //  ts_profile_color_palette()
    // ─────────────────────────────────────────

    /** @test */
    public function palette_returns_array_of_colors(): void
    {
        $palette = ts_profile_color_palette();
        $this->assertIsArray($palette);
        $this->assertNotEmpty($palette);
    }

    /** @test */
    public function palette_contains_expected_keys(): void
    {
        $palette = ts_profile_color_palette();
        $this->assertArrayHasKey('emerald', $palette);
        $this->assertArrayHasKey('ocean', $palette);
        $this->assertArrayHasKey('sunset', $palette);
    }

    /** @test */
    public function each_palette_entry_has_required_fields(): void
    {
        $palette = ts_profile_color_palette();
        foreach ($palette as $key => $entry) {
            $this->assertArrayHasKey('label', $entry, "Missing label for {$key}");
            $this->assertArrayHasKey('from', $entry, "Missing from for {$key}");
            $this->assertArrayHasKey('mid', $entry, "Missing mid for {$key}");
            $this->assertArrayHasKey('to', $entry, "Missing to for {$key}");
        }
    }

    /** @test */
    public function palette_colors_are_valid_hex(): void
    {
        $palette = ts_profile_color_palette();
        foreach ($palette as $key => $entry) {
            foreach (['from', 'mid', 'to'] as $field) {
                $this->assertMatchesRegularExpression(
                    '/^#[0-9a-fA-F]{6}$/',
                    $entry[$field],
                    "{$key}.{$field} is not valid hex: {$entry[$field]}"
                );
            }
        }
    }

    // ─────────────────────────────────────────
    //  ts_resolve_profile_color()
    // ─────────────────────────────────────────

    /** @test */
    public function resolve_color_returns_valid_key(): void
    {
        $result = ts_resolve_profile_color('ocean');
        $this->assertSame('ocean', $result['key']);
        $this->assertArrayHasKey('theme', $result);
        $this->assertArrayHasKey('palette', $result);
    }

    /** @test */
    public function resolve_color_defaults_to_emerald_for_null(): void
    {
        $result = ts_resolve_profile_color(null);
        $this->assertSame('emerald', $result['key']);
    }

    /** @test */
    public function resolve_color_defaults_to_emerald_for_unknown(): void
    {
        $result = ts_resolve_profile_color('nonexistent_color');
        $this->assertSame('emerald', $result['key']);
    }

    /** @test */
    public function resolve_color_includes_full_palette(): void
    {
        $result = ts_resolve_profile_color('rose');
        $this->assertCount(count(ts_profile_color_palette()), $result['palette']);
    }

    // ─────────────────────────────────────────
    //  ts_slugify()
    // ─────────────────────────────────────────

    /** @test */
    public function slugify_transforms_name_to_lowercase(): void
    {
        $this->assertSame('john-doe', ts_slugify('John Doe'));
    }

    /** @test */
    public function slugify_replaces_special_chars_with_dashes(): void
    {
        $this->assertSame('hello-world', ts_slugify('Hello & World!'));
    }

    /** @test */
    public function slugify_trims_leading_trailing_dashes(): void
    {
        $this->assertSame('test', ts_slugify('  test  '));
    }

    /** @test */
    public function slugify_returns_user_for_empty_string(): void
    {
        $this->assertSame('user', ts_slugify(''));
    }

    /** @test */
    public function slugify_returns_user_for_whitespace_only(): void
    {
        $this->assertSame('user', ts_slugify('   '));
    }

    /** @test */
    public function slugify_handles_numbers(): void
    {
        $this->assertSame('test-123', ts_slugify('Test 123'));
    }

    /** @test */
    public function slugify_collapses_multiple_dashes(): void
    {
        $this->assertSame('a-b', ts_slugify('a---b'));
    }

    // ─────────────────────────────────────────
    //  ts_parse_public_profile_id()
    // ─────────────────────────────────────────

    /** @test */
    public function parse_profile_id_from_slug(): void
    {
        $this->assertSame(42, ts_parse_public_profile_id('john-doe-42'));
    }

    /** @test */
    public function parse_profile_id_from_numeric_string(): void
    {
        $this->assertSame(123, ts_parse_public_profile_id('123'));
    }

    /** @test */
    public function parse_profile_id_returns_zero_for_null(): void
    {
        $this->assertSame(0, ts_parse_public_profile_id(null));
    }

    /** @test */
    public function parse_profile_id_returns_zero_for_empty(): void
    {
        $this->assertSame(0, ts_parse_public_profile_id(''));
    }

    /** @test */
    public function parse_profile_id_returns_zero_for_text_only(): void
    {
        $this->assertSame(0, ts_parse_public_profile_id('john-doe'));
    }

    /** @test */
    public function parse_profile_id_handles_large_ids(): void
    {
        $this->assertSame(999999, ts_parse_public_profile_id('user-999999'));
    }

    // ─────────────────────────────────────────
    //  ts_public_profile_url()
    // ─────────────────────────────────────────

    /** @test */
    public function public_profile_url_builds_correct_path(): void
    {
        $url = ts_public_profile_url(42, 'John Doe');
        $this->assertStringContainsString('/TiranaSolidare/views/public_profile.php', $url);
        $this->assertStringContainsString('u=', $url);
        $this->assertStringContainsString('42', $url);
    }

    /** @test */
    public function public_profile_url_returns_base_for_zero_id(): void
    {
        $url = ts_public_profile_url(0, 'Test');
        $this->assertSame('/TiranaSolidare/views/public_profile.php', $url);
    }

    /** @test */
    public function public_profile_url_uses_user_slug_when_no_name(): void
    {
        $url = ts_public_profile_url(5);
        $this->assertStringContainsString('user-5', $url);
    }

    // ─────────────────────────────────────────
    //  generate_upload_filename()
    // ─────────────────────────────────────────

    /** @test */
    public function upload_filename_has_correct_extension(): void
    {
        $filename = generate_upload_filename('webp');
        $this->assertStringEndsWith('.webp', $filename);
    }

    /** @test */
    public function upload_filename_contains_timestamp(): void
    {
        $before = time();
        $filename = generate_upload_filename('jpg');
        $after = time();

        // Extract the timestamp part (before the underscore)
        $parts = explode('_', $filename, 2);
        $ts = (int) $parts[0];
        $this->assertGreaterThanOrEqual($before, $ts);
        $this->assertLessThanOrEqual($after, $ts);
    }

    /** @test */
    public function upload_filename_is_unique(): void
    {
        $a = generate_upload_filename('png');
        $b = generate_upload_filename('png');
        $this->assertNotSame($a, $b);
    }

    /** @test */
    public function upload_filename_contains_hex_token(): void
    {
        $filename = generate_upload_filename('webp');
        // Format: timestamp_hextoken.ext
        $this->assertMatchesRegularExpression('/^\d+_[a-f0-9]{32}\.webp$/', $filename);
    }
}
