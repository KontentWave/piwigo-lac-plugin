<?php
defined('LAC_PATH') or die('Hacking attempt!');

/**
 * Age Gate Guard
 * Runs on init for public side. Redirects guest users without granted consent.
 * Contract:
 *  - If user is logged in (not guest) -> no-op
 *  - If guest and $_SESSION['lac_consent_granted'] === true -> no-op
 *  - Else redirect to root /index.php and exit
 */
function lac_age_gate_guard()
{
  // Allow tests to disable real header()/exit side effects
  $test_mode = defined('LAC_TEST_MODE') && LAC_TEST_MODE === true;

  // Configuration: if gate disabled, bypass
  // Config check (use $conf directly; default enabled if not set)
  global $conf;
  if (isset($conf['lac_enabled']) && !$conf['lac_enabled']) { return; }

  if (!function_exists('lac_is_guest')) {
    // fallback if helpers not loaded yet
    function lac_is_guest() {
      global $user; return isset($user['is_guest']) && $user['is_guest'];
    }
  }

  // If user not guest, allow
  if (!lac_is_guest()) {
    return;
  }

  // Check consent session
  $hasConsent = !empty($_SESSION['lac_consent_granted']);
  if ($hasConsent) {
    return; // allowed
  }

  // Avoid redirect loop if already on the consent page itself (defensive) - detect by script filename
  $current = $_SERVER['SCRIPT_NAME'] ?? '';
  if ($current === '/index.php' || $current === 'index.php') {
    return; // already there
  }

  // Build redirect absolute or relative path: root index one level above gallery? Provided spec: "root index.php"
  // We assume gallery is deployed at /var/www/piwigo/ and root index is one level up (web root). For runtime we just send '/index.php'.
  $target = '/index.php';

  if ($test_mode) {
    // In test collect intended redirect
    $GLOBALS['lac_test_redirect_to'] = $target;
    return;
  }

  header('Location: ' . $target);
  exit;
}
