<?php
use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class BirthdateValidationTest extends TestCase
{
    public function test_age_zero_is_rejected(): void
    {
        $today = (new DateTime())->format('Y-m-d');
        $this->assertFalse(ts_birthdate_is_reasonable($today));
    }

    public function test_age_one_is_accepted(): void
    {
        $dob = (new DateTime('-1 year'))->format('Y-m-d');
        $this->assertTrue(ts_birthdate_is_reasonable($dob));
    }

    public function test_age_120_is_accepted(): void
    {
        $dob = (new DateTime('-120 years'))->format('Y-m-d');
        $this->assertTrue(ts_birthdate_is_reasonable($dob));
    }

    public function test_age_121_is_rejected(): void
    {
        $dob = (new DateTime('-121 years'))->format('Y-m-d');
        $this->assertFalse(ts_birthdate_is_reasonable($dob));
    }

    public function test_invalid_calendar_date_is_rejected(): void
    {
        $this->assertFalse(ts_birthdate_is_reasonable('2024-02-31'));
    }

    public function test_guardian_consent_is_required_under_sixteen(): void
    {
        $dob = (new DateTimeImmutable('-15 years'))->format('Y-m-d');

        $this->assertTrue(ts_birthdate_requires_guardian_consent($dob));
    }

    public function test_guardian_consent_is_not_required_at_sixteen(): void
    {
        $dob = (new DateTimeImmutable('-16 years'))->format('Y-m-d');

        $this->assertFalse(ts_birthdate_requires_guardian_consent($dob));
    }

    public function test_minor_account_needs_email_and_guardian_consent(): void
    {
        $dob = (new DateTimeImmutable('-15 years'))->format('Y-m-d');

        $this->assertSame(
            'email_and_guardian_pending',
            ts_user_activation_state([
                'verified' => 0,
                'birthdate' => $dob,
                'guardian_consent_status' => 'pending',
            ])
        );
    }

    public function test_minor_account_waits_only_for_guardian_after_email_verification(): void
    {
        $dob = (new DateTimeImmutable('-15 years'))->format('Y-m-d');

        $this->assertSame(
            'guardian_pending',
            ts_user_activation_state([
                'verified' => 1,
                'birthdate' => $dob,
                'guardian_consent_status' => 'pending',
            ])
        );
    }

    public function test_of_age_account_is_ready_after_email_verification(): void
    {
        $dob = (new DateTimeImmutable('-18 years'))->format('Y-m-d');

        $this->assertSame(
            'ready',
            ts_user_activation_state([
                'verified' => 1,
                'birthdate' => $dob,
                'guardian_consent_status' => 'not_required',
            ])
        );
    }
}
