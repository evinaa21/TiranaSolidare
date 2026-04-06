<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

final class HelpRequestMatchingHelpersTest extends TestCase
{
    public function test_single_matching_forces_capacity_one(): void
    {
        $details = ts_help_request_matching_details([
            'statusi' => 'open',
            'matching_mode' => 'single',
            'capacity_total' => 7,
        ]);

        $this->assertSame('single', $details['matching_mode']);
        $this->assertSame(1, $details['capacity_total']);
        $this->assertTrue($details['has_capacity_limit']);
    }

    public function test_limited_matching_becomes_filled_when_capacity_is_reached(): void
    {
        $details = ts_help_request_matching_details([
            'statusi' => 'open',
            'matching_mode' => 'limited',
            'capacity_total' => 2,
        ], [
            'approved' => 2,
            'pending' => 1,
        ]);

        $this->assertSame('filled', $details['resolved_status']);
        $this->assertTrue($details['is_full']);
        $this->assertSame(0, $details['slots_remaining']);
    }

    public function test_completed_status_keeps_progress_from_completed_matches(): void
    {
        $details = ts_help_request_matching_details([
            'statusi' => 'completed',
            'matching_mode' => 'limited',
            'capacity_total' => 3,
        ], [
            'completed' => 2,
            'rejected' => 1,
        ]);

        $this->assertSame('completed', $details['resolved_status']);
        $this->assertSame(2, $details['progress_count']);
        $this->assertSame(2, $details['matched_total']);
    }

    public function test_legacy_closed_status_normalizes_to_completed(): void
    {
        $this->assertSame('completed', ts_help_request_normalize_status('closed'));
        $this->assertSame('completed', ts_help_request_normalize_status('Mbyllur'));
    }

    public function test_application_statuses_that_unlock_location_are_recognized(): void
    {
        $this->assertTrue(ts_help_request_application_unlocks_location('pending'));
        $this->assertTrue(ts_help_request_application_unlocks_location('APPROVED'));
        $this->assertTrue(ts_help_request_application_unlocks_location('completed'));
        $this->assertFalse(ts_help_request_application_unlocks_location('rejected'));
        $this->assertFalse(ts_help_request_application_unlocks_location('withdrawn'));
    }

    public function test_location_visibility_is_limited_to_owner_admin_or_active_applicant(): void
    {
        $request = [
            'id_kerkese_ndihme' => 42,
            'id_perdoruesi' => 7,
        ];

        $this->assertTrue(ts_can_view_help_request_location($request, 7, 'volunteer', []));
        $this->assertTrue(ts_can_view_help_request_location($request, 15, 'admin', []));
        $this->assertTrue(ts_can_view_help_request_location($request, 15, 'volunteer', [42]));
        $this->assertFalse(ts_can_view_help_request_location($request, 15, 'volunteer', []));
    }

    public function test_location_fields_are_stripped_when_hidden(): void
    {
        $request = ts_strip_help_request_location([
            'vendndodhja' => 'Tirane',
            'latitude' => 41.3275,
            'longitude' => 19.8187,
        ]);

        $this->assertNull($request['vendndodhja']);
        $this->assertNull($request['latitude']);
        $this->assertNull($request['longitude']);
    }
}