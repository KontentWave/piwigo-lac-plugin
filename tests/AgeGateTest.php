<?php

use PHPUnit\Framework\TestCase;

class AgeGateTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $GLOBALS['user'] = ['is_guest' => true];
    }

    public function test_it_correctly_hooks_into_the_piwigo_init_event()
    {
        // bootstrap already executed; find init registrations
        $events = array_filter($GLOBALS['__LAC_EVENTS'], fn($e) => $e[0] === 'init');
        $names = array_map(fn($e) => is_array($e[1]) ? $e[1][1] : $e[1], $events);
        $this->assertContains('lac_age_gate_guard', $names, 'Age gate guard must hook into init');
    }

    public function test_it_does_nothing_if_the_user_is_logged_in()
    {
        $GLOBALS['user']['is_guest'] = false;
        $decision = lac_gate_decision();
        $this->assertSame('allow', $decision);
    }

    public function test_it_does_nothing_if_a_guest_has_the_correct_consent_session_variable()
    {
        $_SESSION['lac_consent_granted'] = true;
        $decision = lac_gate_decision();
        $this->assertSame('allow', $decision);
    }

    public function test_it_redirects_a_guest_to_the_root_index_if_the_consent_session_is_missing()
    {
        unset($_SESSION['lac_consent_granted']);
        $decision = lac_gate_decision();
        $this->assertSame('redirect', $decision);
    }

    public function test_it_redirects_a_guest_to_the_root_index_if_the_consent_session_is_false()
    {
        $_SESSION['lac_consent_granted'] = false; // explicit false
        $decision = lac_gate_decision();
        $this->assertSame('redirect', $decision);
    }

    public function test_direct_invocation_sets_redirect_global()
    {
        unset($_SESSION['lac_consent_granted']);
        unset($GLOBALS['lac_test_redirect_to']);
        lac_age_gate_guard();
        $this->assertSame('/index.php', $GLOBALS['lac_test_redirect_to'] ?? null);
    }

    public function test_no_redirect_loop_on_consent_page()
    {
        if (!defined('LAC_CONSENT_ROOT')) { define('LAC_CONSENT_ROOT', '/index.php'); }
        $_SERVER['SCRIPT_NAME'] = LAC_CONSENT_ROOT;
        unset($_SESSION['lac_consent_granted']);
        unset($GLOBALS['lac_test_redirect_to']);
        lac_age_gate_guard();
        $this->assertArrayNotHasKey('lac_test_redirect_to', $GLOBALS, 'Should not set redirect when already on consent page');
    }
}
