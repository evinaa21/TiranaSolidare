<?php
use PHPUnit\Framework\TestCase;

require_once PROJECT_ROOT . '/includes/functions.php';

class SiteSettingsTest extends TestCase
{
    public function test_site_settings_sanitizer_uses_defaults_and_validates_colors(): void
    {
        $settings = ts_sanitize_site_settings([
            'organization_name' => '  Organizata Test  ',
            'theme_primary' => '#123ABC',
            'theme_accent' => 'bad-color',
            'hero_title' => '',
        ]);

        $defaults = ts_default_site_settings();

        $this->assertSame('Organizata Test', $settings['organization_name']);
        $this->assertSame('#123ABC', $settings['theme_primary']);
        $this->assertSame($defaults['theme_accent'], $settings['theme_accent']);
        $this->assertSame($defaults['hero_title'], $settings['hero_title']);
    }

    public function test_dashboard_role_detection_includes_organizer(): void
    {
        $this->assertTrue(ts_is_dashboard_role_value('organizer'));
        $this->assertTrue(ts_is_event_manager_role_value('organizer'));
        $this->assertFalse(ts_is_admin_role_value('organizer'));
    }

    public function test_organizer_can_only_manage_own_events(): void
    {
        $organizer = ['id' => 12, 'roli' => 'organizer'];

        $this->assertTrue(ts_can_manage_event($organizer, ['id_perdoruesi' => 12]));
        $this->assertFalse(ts_can_manage_event($organizer, ['id_perdoruesi' => 44]));
        $this->assertTrue(ts_can_manage_event(['id' => 3, 'roli' => 'admin'], ['id_perdoruesi' => 44]));
    }
}