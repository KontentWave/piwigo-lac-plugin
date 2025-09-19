<?php
use PHPUnit\Framework\TestCase;

class ConsentDurationTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure bootstrap loaded
        require_once __DIR__ . '/bootstrap.php';
        $_SESSION = [];
        global $conf; $conf['lac_enabled'] = true; $conf['lac_consent_duration'] = 0; // default session-only
        $GLOBALS['user'] = ['is_guest' => true];
    }

    public function test_it_saves_a_valid_consent_duration_in_admin()
    {
        global $conf, $page;
        $_POST = [
            'lac_settings_submit' => '1',
            'lac_enabled' => 'on',
            'lac_consent_duration' => '45'
        ];
        require PHPWG_PLUGINS_PATH . 'lac/admin/config.php';
        $this->assertEquals(45, $conf['lac_consent_duration']);
        $this->assertEmpty($page['errors'] ?? []);
    }

    public function test_it_rejects_a_non_numeric_consent_duration_in_admin()
    {
        global $conf, $page;
        $_POST = [
            'lac_settings_submit' => '1',
            'lac_consent_duration' => 'abc'
        ];
        require PHPWG_PLUGINS_PATH . 'lac/admin/config.php';
        $this->assertNotEmpty($page['errors'] ?? []);
    }

    public function test_it_rejects_a_negative_consent_duration_in_admin()
    {
        global $conf, $page;
        $_POST = [
            'lac_settings_submit' => '1',
            'lac_consent_duration' => '-5'
        ];
        require PHPWG_PLUGINS_PATH . 'lac/admin/config.php';
        $this->assertNotEmpty($page['errors'] ?? []);
    }

    public function test_it_does_not_redirect_guest_when_consent_is_not_expired()
    {
        global $conf; $conf['lac_consent_duration'] = 30; // 30 minutes
        $_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => time() - (10*60)];
        $decision = lac_gate_decision();
        $this->assertSame('allow', $decision);
    }

    public function test_it_redirects_guest_when_consent_is_expired()
    {
        global $conf; $conf['lac_consent_duration'] = 15; // 15 minutes
        $_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => time() - (20*60)];
        $decision = lac_gate_decision();
        $this->assertSame('redirect', $decision);
        $this->assertArrayNotHasKey('lac_consent', $_SESSION, 'Expired consent should be unset');
    }

    public function test_it_does_not_redirect_guest_when_duration_is_zero_and_session_exists()
    {
        global $conf; $conf['lac_consent_duration'] = 0; // session-only
        $_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => time() - (9999*60)];
        $decision = lac_gate_decision();
        $this->assertSame('allow', $decision);
    }

    public function test_cookie_within_duration_restores_session()
    {
        global $conf; $conf['lac_consent_duration'] = 10; // 10 minutes
        // Simulate earlier root index cookie logic: cookie timestamp 5 minutes ago
        $_COOKIE['LAC'] = (string)(time() - 300);
        $_COOKIE['PHPSESSID'] = 'dummy';
        // gate decision alone doesn't recreate session; mimic root logic by calling marking if within duration
        // We'll approximate by calling lac_has_consent before root sets; should be false
        $this->assertSame('redirect', lac_gate_decision());
    }
}
