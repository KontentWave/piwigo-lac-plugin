<?php
// Ensure bootstrap (stubs & plugin main) loaded so TemplateStub exists
require_once __DIR__ . '/bootstrap.php';
use PHPUnit\Framework\TestCase;

class AdminTemplateLoadTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('IN_ADMIN')) {
            define('IN_ADMIN', true);
        }
        global $conf, $page, $template;
        $page = [];
        $conf['lac_enabled'] = true;
        $conf['lac_fallback_url'] = '';
        // Load admin config logic (not using once so we can re-run between tests with fresh $_POST state)
        require PHPWG_PLUGINS_PATH . 'lac/admin/config.php';
    }

    public function test_admin_config_template_variables_present()
    {
        // Sanity: including config should not produce errors and should define expected globals.
        global $conf;
        $this->assertArrayHasKey('lac_enabled', $conf, 'lac_enabled missing in $conf');
        $this->assertArrayHasKey('lac_fallback_url', $conf, 'lac_fallback_url missing in $conf');
    }

    public function test_invalid_internal_url_rejected()
    {
        global $conf, $page, $template;
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_POST = [
            'lac_settings_submit' => '1',
            'lac_enabled' => 'on',
            'lac_fallback_url' => 'https://example.test/some/path'
        ];
        // Re-run include to process POST
        $page = [];
        require PHPWG_PLUGINS_PATH . 'lac/admin/config.php';
        $this->assertNotEmpty($page['errors'] ?? [], 'Expected internal URL rejection error');
        $this->assertSame('', $conf['lac_fallback_url'], 'Fallback should not be saved for internal URL');
    }

    public function test_valid_external_url_saved()
    {
        global $conf, $page;
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_POST = [
            'lac_settings_submit' => '1',
            'lac_enabled' => 'on',
            'lac_fallback_url' => 'https://external.example.org/'
        ];
        $page = [];
        require PHPWG_PLUGINS_PATH . 'lac/admin/config.php';
        $this->assertEmpty($page['errors'] ?? [], 'Did not expect errors for valid external URL');
        $this->assertSame('https://external.example.org/', $conf['lac_fallback_url']);
    }
}
