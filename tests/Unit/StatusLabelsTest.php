<?php
/**
 * tests/Unit/StatusLabelsTest.php
 * ---------------------------------------------------
 * Tests for status_label() and STATUS_LABELS constant.
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/status_labels.php';

class StatusLabelsTest extends TestCase
{
    /** @test */
    public function status_labels_constant_exists(): void
    {
        $this->assertTrue(defined('STATUS_LABELS'));
        $this->assertIsArray(STATUS_LABELS);
    }

    /** @test */
    public function status_labels_contains_all_expected_keys(): void
    {
        $expectedKeys = [
            'pending', 'approved', 'rejected', 'present', 'absent',
            'active', 'blocked', 'deactivated', 'admin', 'volunteer',
            'request', 'offer', 'open', 'closed', 'active_event',
            'filled', 'completed', 'cancelled', 'waitlisted', 'withdrawn',
            'pending_review', 'super_admin',
        ];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, STATUS_LABELS, "Missing label for '{$key}'");
        }
    }

    /** @test */
    public function status_label_translates_known_value(): void
    {
        $this->assertSame('Në pritje', status_label('pending'));
        $this->assertSame('Pranuar', status_label('approved'));
        $this->assertSame('Refuzuar', status_label('rejected'));
        $this->assertSame('Aktiv', status_label('active'));
        $this->assertSame('Bllokuar', status_label('blocked'));
    }

    /** @test */
    public function status_label_is_case_insensitive(): void
    {
        $this->assertSame('Në pritje', status_label('Pending'));
        $this->assertSame('Në pritje', status_label('PENDING'));
        $this->assertSame('Pranuar', status_label('Approved'));
    }

    /** @test */
    public function status_label_returns_ucfirst_for_unknown(): void
    {
        $this->assertSame('Unknown', status_label('unknown'));
        $this->assertSame('Foo', status_label('foo'));
    }

    /** @test */
    public function status_label_returns_ucfirst_for_empty(): void
    {
        $this->assertSame('', status_label(''));
    }

    /** @test */
    public function all_labels_are_non_empty_strings(): void
    {
        foreach (STATUS_LABELS as $key => $label) {
            $this->assertIsString($label, "Label for '{$key}' is not a string");
            $this->assertNotEmpty($label, "Label for '{$key}' is empty");
        }
    }

    /**
     * @test
     * Verify Albanian translations are correct for key user-facing statuses.
     */
    public function albanian_translations_are_correct(): void
    {
        $this->assertSame('Vullnetar', status_label('volunteer'));
        $this->assertSame('Admin', status_label('admin'));
        $this->assertSame('Kërkesë', status_label('request'));
        $this->assertSame('Ofertë', status_label('offer'));
        $this->assertSame('Hapur', status_label('open'));
        $this->assertSame('Mbushur', status_label('filled'));
        $this->assertSame('Mbyllur', status_label('closed'));
        $this->assertSame('Përfunduar', status_label('completed'));
        $this->assertSame('Anuluar', status_label('cancelled'));
        $this->assertSame('Në listë pritjeje', status_label('waitlisted'));
        $this->assertSame('Tërhequr', status_label('withdrawn'));
        $this->assertSame('Prezent', status_label('present'));
        $this->assertSame('Munguar', status_label('absent'));
        $this->assertSame('Çaktivizuar', status_label('deactivated'));
        $this->assertSame('Në shqyrtim', status_label('pending_review'));
        $this->assertSame('Super Admin', status_label('super_admin'));
    }
}
