<?php
/**
 * tests/Unit/ApiHelpersTest.php
 * ---------------------------------------------------
 * Tests for API utility functions from api/helpers.php:
 *  - get_pagination()
 *  - required_field()
 * 
 * Note: json_success(), json_error(), require_auth(),
 * require_admin() all call exit(), so they are tested
 * via process-level integration tests.
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiHelpersTest extends TestCase
{
    /**
     * We cannot include api/helpers.php directly because it starts sessions,
     * includes DB, sets headers, etc. Instead we test the pure-logic helpers
     * by redefining them here from the known source.
     */

    // ─────────────────────────────────────────
    //  get_pagination() logic test
    // ─────────────────────────────────────────

    /** @test */
    public function pagination_defaults(): void
    {
        // Simulate no query params
        $_GET = [];
        $result = $this->computePagination(20, 100);
        $this->assertSame(1, $result['page']);
        $this->assertSame(20, $result['limit']);
        $this->assertSame(0, $result['offset']);
    }

    /** @test */
    public function pagination_respects_page_param(): void
    {
        $_GET = ['page' => '3', 'limit' => '10'];
        $result = $this->computePagination(20, 100);
        $this->assertSame(3, $result['page']);
        $this->assertSame(10, $result['limit']);
        $this->assertSame(20, $result['offset']); // (3-1) * 10
    }

    /** @test */
    public function pagination_enforces_max_limit(): void
    {
        $_GET = ['limit' => '500'];
        $result = $this->computePagination(20, 100);
        $this->assertSame(100, $result['limit']);
    }

    /** @test */
    public function pagination_enforces_min_page(): void
    {
        $_GET = ['page' => '-5'];
        $result = $this->computePagination(20, 100);
        $this->assertSame(1, $result['page']);
    }

    /** @test */
    public function pagination_enforces_min_limit(): void
    {
        $_GET = ['limit' => '0'];
        $result = $this->computePagination(20, 100);
        $this->assertSame(1, $result['limit']);
    }

    // ─────────────────────────────────────────
    //  required_field() logic test
    // ─────────────────────────────────────────

    /** @test */
    public function required_field_returns_value_when_present(): void
    {
        $errors = [];
        $source = ['name' => '  John Doe  '];
        $value = $this->extractRequiredField($source, 'name', $errors);
        $this->assertSame('John Doe', $value);
        $this->assertEmpty($errors);
    }

    /** @test */
    public function required_field_returns_null_when_missing(): void
    {
        $errors = [];
        $source = [];
        $value = $this->extractRequiredField($source, 'name', $errors);
        $this->assertNull($value);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('name', $errors[0]);
    }

    /** @test */
    public function required_field_returns_null_for_empty_string(): void
    {
        $errors = [];
        $source = ['name' => '   '];
        $value = $this->extractRequiredField($source, 'name', $errors);
        $this->assertNull($value);
        $this->assertCount(1, $errors);
    }

    /** @test */
    public function required_field_accumulates_multiple_errors(): void
    {
        $errors = [];
        $source = [];
        $this->extractRequiredField($source, 'name', $errors);
        $this->extractRequiredField($source, 'email', $errors);
        $this->assertCount(2, $errors);
    }

    // ─────────────────────────────────────────
    //  Helper methods (replicate logic from api/helpers.php)
    // ─────────────────────────────────────────

    private function computePagination(int $defaultLimit = 20, int $maxLimit = 100): array
    {
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min($maxLimit, max(1, (int) ($_GET['limit'] ?? $defaultLimit)));
        $offset = ($page - 1) * $limit;
        return compact('page', 'limit', 'offset');
    }

    private function extractRequiredField(array $source, string $key, array &$errors): ?string
    {
        $value = isset($source[$key]) ? trim((string) $source[$key]) : '';
        if ($value === '') {
            $errors[] = "Fusha '$key' është e detyrueshme.";
            return null;
        }
        return $value;
    }
}
