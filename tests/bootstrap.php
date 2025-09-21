<?php
// Minimal PHPUnit bootstrap for LAC plugin tests
// Provides lightweight mocks of Piwigo globals & loads plugin helpers.

// Start session for tests
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

// Define plugin path constants relative to this bootstrap
if (!defined('LAC_PATH')) {
    define('LAC_PATH', realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR);
}

// Provide minimal globals expected by plugin code
global $conf, $user;
if (!isset($conf) || !is_array($conf)) { $conf = []; }
if (!isset($user) || !is_array($user)) { $user = []; }

// Sensible defaults
$conf['lac_enabled'] = $conf['lac_enabled'] ?? true;
$conf['lac_consent_duration'] = $conf['lac_consent_duration'] ?? 0;
$conf['lac_fallback_url'] = $conf['lac_fallback_url'] ?? '';
$conf['lac_apply_to_logged_in'] = $conf['lac_apply_to_logged_in'] ?? false;

// Minimal stubs for Piwigo functions referenced by plugin code
if (!function_exists('conf_update_param')) {
    function conf_update_param($k, $v) { global $conf; $conf[$k] = $v; }
}
if (!function_exists('l10n')) {
    function l10n($s) { return $s; }
}
if (!function_exists('check_pwg_token')) {
    function check_pwg_token() { return true; }
}
if (!function_exists('get_pwg_token')) {
    function get_pwg_token() { return 'test-token'; }
}

// Load bootstrap (brings in constants, helpers, session manager, etc.)
require_once LAC_PATH . 'include/bootstrap.inc.php';

// Provide simplified legacy decision helper if not present (for existing legacy tests)
if (!function_exists('lac_gate_decision')) {
    function lac_gate_decision(): string {
        global $conf, $user;
        $sessionManager = LacSessionManager::getInstance();
        $isGuest = (!isset($user['is_guest']) || $user['is_guest']);
        if (!$conf['lac_enabled']) { return 'allow'; }

        // Original simple test semantics:
        // - Logged in users always allowed
        // - Legacy flag true => allow (regardless of duration)
        // - Structured consent honored with expiry when duration>0
        if (!isset($user['is_guest']) || !$user['is_guest']) { return 'allow'; }
        if (isset($_SESSION['lac_consent_granted']) && $_SESSION['lac_consent_granted'] === true) {
            if (!isset($_SESSION['lac_consent']) && (int)($conf['lac_consent_duration'] ?? 0) === 0) {
                $_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => time()];
            }
            return 'allow';
        }
        if (isset($_SESSION['lac_consent']) && !empty($_SESSION['lac_consent']['granted'])) {
            $duration = (int)($conf['lac_consent_duration'] ?? 0);
            if ($duration === 0) { return 'allow'; }
            $ts = (int)($_SESSION['lac_consent']['timestamp'] ?? 0);
            if ($ts === 0) { return 'allow'; }
            if ((time() - $ts) >= ($duration * 60)) {
                unset($_SESSION['lac_consent']);
                $GLOBALS['lac_test_redirect_to'] = '/index.php';
                return 'redirect';
            }
            return 'allow';
        }
        $GLOBALS['lac_test_redirect_to'] = '/index.php';
        return 'redirect';
    }
}

// Helper to reset environment between tests
function lac_test_reset_env(): void {
    global $conf, $user;
    $_SESSION = [];
    LacSessionManager::clearCache();
    $conf['lac_enabled'] = true;
    $conf['lac_consent_duration'] = 0;
    $conf['lac_fallback_url'] = '';
    $conf['lac_apply_to_logged_in'] = false;
    $user = ['is_guest' => true, 'status' => 'guest'];
}
