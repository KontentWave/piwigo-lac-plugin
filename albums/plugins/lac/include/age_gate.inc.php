<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized constants
include_once LAC_PATH . 'include/constants.inc.php';

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

  if ($debug_mode) { error_log('[LAC DEBUG] guest_detected='.(lac_is_guest()?1:0)); }

  // If user not guest, allow
  if (!lac_is_guest()) {
    return;
  }

  // Determine duration (default 0 if not set)
  $duration = function_exists('lac_get_consent_duration') ? lac_get_consent_duration() : (isset($conf['lac_consent_duration']) ? (int)$conf['lac_consent_duration'] : 0);

  $hasConsent = function_exists('lac_has_consent') ? lac_has_consent() : !empty($_SESSION['lac_consent_granted']);
  // Cookie reconstruction fallback:
  // If session markers missing but LAC cookie exists & still valid, rebuild consent.
  // Rationale: root consent page may have started a session under default name before
  // Piwigo sets its custom session_name; gallery then sees a fresh session. This prevents loops.
  if (!$hasConsent && empty($_SESSION[LAC_SESSION_CONSENT_KEY]) && isset($_COOKIE[lac_get_cookie_name()]) && ctype_digit($_COOKIE[lac_get_cookie_name()])) {
    $cookieTs = (int)$_COOKIE[lac_get_cookie_name()];
    $age = time() - $cookieTs;
    $cookieValidWindow = lac_get_cookie_max_window(); // absolute cookie max window
    $duration = function_exists('lac_get_consent_duration') ? lac_get_consent_duration() : (isset($conf[LAC_CONFIG_CONSENT_DURATION]) ? (int)$conf[LAC_CONFIG_CONSENT_DURATION] : 0);
    $withinCookie = $age < $cookieValidWindow;
    $withinDuration = ($duration === 0) || ($age < ($duration * 60));
    if ($withinCookie && $withinDuration) {
      $_SESSION[LAC_SESSION_CONSENT_KEY] = ['granted' => true, 'timestamp' => $cookieTs];
      $_SESSION[LAC_SESSION_CONSENT_LEGACY_KEY] = true; // legacy flag for compatibility
      
      // Regenerate session ID when reconstructing consent for security
      if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
        $_SESSION[LAC_SESSION_REGENERATED_KEY] = time();
      }
      
      $hasConsent = true;
      if ($debug_mode) { error_log('[LAC DEBUG] Guard: reconstructed consent from cookie (age='.$age.'s), session regenerated'); }
    } elseif ($debug_mode) {
      error_log('[LAC DEBUG] Guard: found LAC cookie but invalid (age='.$age.'s withinCookie=' . ($withinCookie?1:0) . ' withinDuration=' . ($withinDuration?1:0) . ' duration='.$duration.'m)');
    }
  }
  if ($hasConsent) {
    $expired = function_exists('lac_consent_expired') && lac_consent_expired($duration);
    if ($debug_mode && ($expired || isset($_GET['lac_debug_verbose']))) {
      $ts = isset($_SESSION['lac_consent']['timestamp']) ? (int)$_SESSION['lac_consent']['timestamp'] : null;
      $expAt = ($ts && $duration>0) ? $ts + ($duration*60) : null;
      error_log('[LAC DEBUG] consent check duration=' . $duration . 'm ts=' . ($ts ?: 'null') . ' expAt=' . ($expAt ?: 'n/a') . ' now=' . time() . ' expired=' . ($expired?1:0));
    }
    if ($expired) {
      unset($_SESSION['lac_consent']);
      unset($_SESSION['lac_consent_granted']);
      // fall through to redirect logic
    } else {
      return; // still valid
    }
  } elseif ($debug_mode) {
    error_log('[LAC DEBUG] hasConsent=0 duration=' . $duration . ' (no consent markers)');
  }

  if ($debug_mode) { error_log('[LAC DEBUG] redirect_reason=' . ($hasConsent? 'expired_or_cleared':'no_consent')); }

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
