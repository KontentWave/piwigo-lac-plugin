<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class RedirectTargetTest extends TestCase
{
    protected function setUp(): void
    {
        lac_test_reset_env();
    // Ensure public side context (not IN_ADMIN). Tests include only the guard file directly.
        $_SERVER['REQUEST_URI'] = '/albums/index.php?/photo/123';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'piwigo.local';
        unset($GLOBALS['lac_test_redirect_to']);
    }

    public function test_it_saves_the_intended_url_to_the_session_before_redirecting(): void
    {
        global $conf, $user;
        // Guest without consent
        $user = ['status' => 'guest', 'is_guest' => true];
        unset($_SESSION['lac_consent'], $_SESSION['lac_consent_granted']);

        // Enable test mode to avoid real header()/exit side-effects
        if (!defined('LAC_TEST_MODE')) { define('LAC_TEST_MODE', true); }

        // Include guard file and invoke the handler
        require_once LAC_PATH . 'include/age_gate.inc.php';
        $result = lac_age_gate_guard_with_error_handling();

        $this->assertTrue($result['success'], 'Guard should return success envelope');
        $this->assertSame('test_redirect', $result['context']['action'] ?? '', 'Guard should signal a test redirect');
        // Session should contain the intended URI
        $this->assertArrayHasKey('LAC_REDIRECT', $_SESSION, 'Session should include LAC_REDIRECT');
        $this->assertSame('/albums/index.php?/photo/123', $_SESSION['LAC_REDIRECT']);
    }
}
