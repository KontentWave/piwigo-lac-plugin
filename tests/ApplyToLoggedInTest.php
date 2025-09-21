<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class ApplyToLoggedInTest extends TestCase
{
    protected function setUp(): void
    {
        lac_test_reset_env();
    }

    public function testSettingPersistsTrueAndFalse()
    {
        global $conf;
        $this->assertFalse(!empty($conf['lac_apply_to_logged_in']));
        $conf['lac_apply_to_logged_in'] = true;
        $this->assertTrue(!empty($conf['lac_apply_to_logged_in']));
        $conf['lac_apply_to_logged_in'] = false;
        $this->assertFalse(!empty($conf['lac_apply_to_logged_in']));
    }

    public function testAdminAlwaysBypassesRegardlessOfSetting()
    {
        global $conf, $user;
        $user = ['is_guest' => false, 'status' => 'admin', 'is_admin' => true];
        $conf['lac_apply_to_logged_in'] = false;
        $this->assertSame('allow', lac_gate_decision());
        $conf['lac_apply_to_logged_in'] = true;
        $this->assertSame('allow', lac_gate_decision());
    }

    public function testLoggedInBypassesWhenSettingDisabled()
    {
        global $conf, $user;
        $user = ['is_guest' => false, 'status' => 'normal'];
        $conf['lac_apply_to_logged_in'] = false;
        $this->assertSame('allow', lac_gate_decision());
    }

    public function testLoggedInCheckedWhenSettingEnabledAndNoConsent()
    {
        global $conf, $user;
        $user = ['is_guest' => false, 'status' => 'normal'];
        $conf['lac_apply_to_logged_in'] = true;
        $this->assertSame('redirect', lac_gate_decision());
    }

    public function testLoggedInAllowedWhenSettingEnabledAndConsentPresent()
    {
        global $conf, $user, $_SESSION;
        $user = ['is_guest' => false, 'status' => 'normal'];
        $conf['lac_apply_to_logged_in'] = true;
        // Set consent (structured)
        $_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => time()];
        $this->assertSame('allow', lac_gate_decision());
    }

    public function testGuestAlwaysCheckedRegardlessOfSetting()
    {
        global $conf, $user;
        $user = ['is_guest' => true, 'status' => 'guest'];
        $conf['lac_apply_to_logged_in'] = false;
        $this->assertSame('redirect', lac_gate_decision());
        $conf['lac_apply_to_logged_in'] = true;
        $this->assertSame('redirect', lac_gate_decision());
    }
}
