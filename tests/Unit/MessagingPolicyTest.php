<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

final class MessagingPolicyTest extends TestCase
{
    /** @test */
    public function volunteers_cannot_message_admin_roles(): void
    {
        $this->assertFalse(ts_can_message_user_roles('volunteer', 'admin'));
        $this->assertFalse(ts_can_message_user_roles('volunteer', 'super_admin'));
    }

    /** @test */
    public function admins_can_message_any_role(): void
    {
        $this->assertTrue(ts_can_message_user_roles('admin', 'volunteer'));
        $this->assertTrue(ts_can_message_user_roles('super_admin', 'admin'));
    }

    /** @test */
    public function volunteers_can_message_other_non_admin_users(): void
    {
        $this->assertTrue(ts_can_message_user_roles('volunteer', 'volunteer'));
        $this->assertTrue(ts_can_message_user_roles('volunteer', 'Volunteer'));
    }
}