<?php
/**
 * tests/Unit/TimeAgoTest.php
 * ---------------------------------------------------
 * Tests for koheParapake() — Albanian time-ago helper.
 * ---------------------------------------------------
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class TimeAgoTest extends TestCase
{
    /** @test */
    public function returns_tani_for_just_now(): void
    {
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $this->assertSame('tani', koheParapake($now));
    }

    /** @test */
    public function returns_minutes_ago(): void
    {
        $dt = (new DateTime())->modify('-5 minutes')->format('Y-m-d H:i:s');
        $result = koheParapake($dt);
        $this->assertStringContainsString('min më parë', $result);
    }

    /** @test */
    public function returns_hours_ago(): void
    {
        $dt = (new DateTime())->modify('-3 hours')->format('Y-m-d H:i:s');
        $result = koheParapake($dt);
        $this->assertStringContainsString('orë më parë', $result);
    }

    /** @test */
    public function returns_days_ago(): void
    {
        $dt = (new DateTime())->modify('-10 days')->format('Y-m-d H:i:s');
        $result = koheParapake($dt);
        $this->assertStringContainsString('ditë më parë', $result);
    }

    /** @test */
    public function returns_months_ago(): void
    {
        $dt = (new DateTime())->modify('-2 months')->format('Y-m-d H:i:s');
        $result = koheParapake($dt);
        $this->assertStringContainsString('muaj më parë', $result);
    }

    /** @test */
    public function returns_years_ago(): void
    {
        $dt = (new DateTime())->modify('-2 years')->format('Y-m-d H:i:s');
        $result = koheParapake($dt);
        $this->assertStringContainsString('vit më parë', $result);
    }

    /** @test */
    public function returns_1_minute_quantity(): void
    {
        $dt = (new DateTime())->modify('-1 minute')->format('Y-m-d H:i:s');
        $result = koheParapake($dt);
        $this->assertSame('1 min më parë', $result);
    }

    /** @test */
    public function returns_1_hour_quantity(): void
    {
        $dt = (new DateTime())->modify('-1 hour')->format('Y-m-d H:i:s');
        $result = koheParapake($dt);
        $this->assertSame('1 orë më parë', $result);
    }
}
