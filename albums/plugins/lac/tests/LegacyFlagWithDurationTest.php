<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

class LegacyFlagWithDurationTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        global $conf, $user;
        $conf['lac_enabled'] = true;
        $conf['lac_consent_duration'] = 5; // minutes
        $user = ['is_guest' => true];
    }

    public function testLegacyFlagIgnoredWhenDurationEnabledCausesRedirect()
    {
        $_SESSION['lac_consent_granted'] = true; // legacy only
        $this->assertSame('redirect', lac_gate_decision(), 'Legacy flag should not satisfy consent when duration > 0');
    }

    public function testLegacyFlagUpgradedWhenDurationZero()
    {
        global $conf; $conf['lac_consent_duration'] = 0;
        $_SESSION['lac_consent_granted'] = true;
        $this->assertSame('allow', lac_gate_decision(), 'Should allow when duration=0');
        $this->assertNotEmpty($_SESSION['lac_consent']);
        $this->assertTrue($_SESSION['lac_consent']['granted']);
    }
}
