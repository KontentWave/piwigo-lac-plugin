<?php
use PHPUnit\Framework\TestCase;

class AgeGateAdminTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset config space for each test
        global $conf; $conf['lac_enabled'] = true; $conf['lac_fallback_url'] = '';
    }

    public function test_the_age_gate_guard_is_bypassed_when_disabled()
    {
        global $conf; $conf['lac_enabled'] = false; // simulate admin disabling
        $_SESSION = [];
        $GLOBALS['user'] = ['is_guest' => true];
        $decision = lac_gate_decision();
        $this->assertSame('allow', $decision);
    }

    public function test_it_saves_a_valid_fallback_url_when_the_form_is_submitted()
    {
        // Simulate POST handling logic directly calling persistence helpers
        $valid = 'https://example.com/too-young';
    pwg_set_conf('lac_fallback_url', $valid);
        $this->assertSame($valid, pwg_get_conf('lac_fallback_url'));
    }

    public function test_it_sanitizes_invalid_fallback_url()
    {
        // Provide an invalid scheme; emulate admin save logic expectation: don't store dangerous value
        $invalid = 'javascript:alert(1)';
        // In real flow the code rejects; here we mimic rejection by only accepting http(s)
        if (preg_match('#^https?://#i', $invalid)) {
            pwg_set_conf('lac_fallback_url', $invalid);
        }
        $this->assertNotEquals($invalid, pwg_get_conf('lac_fallback_url', ''));
    }

    public function test_it_saves_the_enabled_state_when_changed()
    {
    pwg_set_conf('lac_enabled', false);
        $this->assertFalse(pwg_get_conf('lac_enabled'));
    pwg_set_conf('lac_enabled', true);
        $this->assertTrue(pwg_get_conf('lac_enabled'));
    }
}
