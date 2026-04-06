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
}
