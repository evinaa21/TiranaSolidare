<?php
/**
 * tests/Integration/AdminLogTest.php
 * ---------------------------------------------------
 * Integration tests for log_admin_action().
 * ---------------------------------------------------
 */

declare(strict_types=1);

require_once PROJECT_ROOT . '/includes/functions.php';
require_once __DIR__ . '/DatabaseTestCase.php';

class AdminLogTest extends DatabaseTestCase
{
    /** @test */
    public function log_admin_action_inserts_record(): void
    {
        log_admin_action(1, 'block_user', 'user', 5, ['reason' => 'Spam']);

        $row = self::$pdo->query("SELECT * FROM admin_log ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame(1, (int) $row['admin_id']);
        $this->assertSame('block_user', $row['veprim']);
        $this->assertSame('user', $row['target_type']);
        $this->assertSame(5, (int) $row['target_id']);

        $details = json_decode($row['detaje'], true);
        $this->assertSame('Spam', $details['reason']);
    }

    /** @test */
    public function log_admin_action_handles_null_target(): void
    {
        log_admin_action(1, 'generate_report');

        $row = self::$pdo->query("SELECT * FROM admin_log ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame('generate_report', $row['veprim']);
        $this->assertNull($row['target_type']);
        $this->assertNull($row['target_id']);
        $this->assertNull($row['detaje']);
    }

    /** @test */
    public function log_admin_action_stores_utf8_details(): void
    {
        log_admin_action(1, 'test', 'user', 10, ['arsyeja' => 'Përdoruesi nuk respektoi rregullat']);

        $row = self::$pdo->query("SELECT detaje FROM admin_log ORDER BY id DESC LIMIT 1")->fetch();
        $details = json_decode($row['detaje'], true);
        $this->assertSame('Përdoruesi nuk respektoi rregullat', $details['arsyeja']);
    }
}
