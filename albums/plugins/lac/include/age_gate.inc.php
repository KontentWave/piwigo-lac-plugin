<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized constants and optimized helpers
include_once LAC_PATH . 'include/constants.inc.php';
include_once LAC_PATH . 'include/session_manager.inc.php';

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
  $debug_mode = isset($_GET['lac_debug']);

  if ($debug_mode) {
    error_log('[LAC DEBUG] Guard entry');
  }

  // Configuration: if gate disabled, bypass
  // Config check (use $conf directly; default enabled if not set)
  global $conf;
  if (isset($conf['lac_enabled']) && !$conf['lac_enabled']) { return; }

  // Ensure helper functions are loaded (expiration logic lives there)
  if (!function_exists('lac_consent_expired')) {
    $funcFile = LAC_PATH . 'include/functions.inc.php';
    if (file_exists($funcFile)) { 
      include_once $funcFile; 
    } else if ($debug_mode) {
      error_log('[LAC DEBUG] Functions file not found: ' . $funcFile);
    }
  }

  if (!function_exists('lac_is_guest')) {
    // fallback if helpers not loaded yet
    function lac_is_guest() {
      global $user; 
      if (!isset($user) || !is_array($user)) { return true; }
      if (!array_key_exists('is_guest', $user)) { return true; }
      return !empty($user['is_guest']);
    }
  }

  // Initialize optimized session manager
  $sessionManager = LacSessionManager::getInstance();
  
  if ($debug_mode) { error_log('[LAC DEBUG] guest_detected='.(lac_is_guest()?1:0)); }

  // If user not guest, allow
  if (!lac_is_guest()) {
    return;
  }

  // Optimized consent checking using session manager
  $hasConsent = $sessionManager->hasConsent() && !$sessionManager->isConsentExpired();
  // Cookie reconstruction fallback (optimized):
  if (!$hasConsent && isset($_COOKIE[lac_get_cookie_name()]) && ctype_digit($_COOKIE[lac_get_cookie_name()])) {
    $cookieTs = (int)$_COOKIE[lac_get_cookie_name()];
    $age = time() - $cookieTs;
    $cookieValidWindow = lac_get_cookie_max_window();
    $duration = function_exists('lac_get_consent_duration') ? lac_get_consent_duration() : (isset($conf[LAC_CONFIG_CONSENT_DURATION]) ? (int)$conf[LAC_CONFIG_CONSENT_DURATION] : 0);
    $withinCookie = $age < $cookieValidWindow;
    $withinDuration = ($duration === 0) || ($age < ($duration * 60));
    
    if ($withinCookie && $withinDuration) {
      $sessionManager->setConsent($cookieTs);
      $sessionManager->regenerateIfNeeded();
      $hasConsent = true;
      
      if ($debug_mode) { 
        error_log('[LAC DEBUG] Guard: reconstructed consent from cookie (age='.$age.'s), session regenerated'); 
      }
    } elseif ($debug_mode) {
      error_log('[LAC DEBUG] Guard: found LAC cookie but invalid (age='.$age.'s withinCookie=' . ($withinCookie?1:0) . ' withinDuration=' . ($withinDuration?1:0) . ' duration='.$duration.'m)');
    }
  }
  if ($hasConsent) {
    // Consent is already validated in session manager, just log if needed
    if ($debug_mode && isset($_GET['lac_debug_verbose'])) {
      error_log('[LAC DEBUG] Valid consent found, allowing access');
    }
    return; // Allow access
  }

  // No valid consent found, need to redirect
  if ($debug_mode) { 
    error_log('[LAC DEBUG] No valid consent found, redirecting to consent page'); 
  }

  // Avoid redirect loop if already on the consent page itself (defensive) - detect by script filename
  $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
  $currentUri = $_SERVER['REQUEST_URI'] ?? $currentScript;
  // Allow defining consent root explicitly (set in root index.php) for reliability across rewrites/aliases
  if (!defined('LAC_CONSENT_ROOT')) {
    define('LAC_CONSENT_ROOT', '/index.php');
  }
  $isConsentPage = ($currentScript === LAC_CONSENT_ROOT) || ($currentUri === LAC_CONSENT_ROOT) || ($currentUri === rtrim(LAC_CONSENT_ROOT,'/'));
  if ($isConsentPage) {
    if ($debug_mode) { error_log('[LAC DEBUG] Detected consent root ("'.LAC_CONSENT_ROOT.'"), skipping redirect'); }
    return;
  }

  // Build redirect absolute or relative path: root index one level above gallery? Provided spec: "root index.php"
  // We assume gallery is deployed at /var/www/piwigo/ and root index is one level up (web root). For runtime we just send '/index.php'.
  $target = '/index.php';

  if ($test_mode) {
    // In test collect intended redirect
    $GLOBALS['lac_test_redirect_to'] = $target;
    return;
  }

  if ($debug_mode) { error_log('[LAC DEBUG] Redirecting to ' . $target); }
  header('Location: ' . $target);
  exit;
}
