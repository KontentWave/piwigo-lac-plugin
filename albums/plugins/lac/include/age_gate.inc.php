<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized bootstrap for all dependencies
include_once LAC_PATH . 'include/bootstrap.inc.php';

/**
 * Age Gate Guard for Event Handler (maintains original void signature)
 * Runs on init for public side. Redirects guest users without granted consent.
 * Contract:
 *  - If user is logged in (not guest) -> no-op
 *  - If guest and $_SESSION['lac_consent_granted'] === true -> no-op
 *  - Else redirect to root /index.php and exit
 * @return void
 */
function lac_age_gate_guard(): void {
  $result = lac_age_gate_guard_with_error_handling();
  // Log errors but maintain void signature for event handler compatibility
  if (!$result['success']) {
    error_log('[LAC ERROR] Age gate guard failed: ' . $result['error']);
  }
}

/**
 * Age Gate Guard with standardized error handling
 * Runs on init for public side. Redirects guest users without granted consent.
 * Contract:
 *  - If user is logged in (not guest) -> no-op
 *  - If guest and $_SESSION['lac_consent_granted'] === true -> no-op
 *  - Else redirect to root /index.php and exit
 * @return array Result with success/error information
 */
function lac_age_gate_guard_with_error_handling(): array
{
  $errorHandler = LacErrorHandler::getInstance();
  
  // Allow tests to disable real header()/exit side effects
  $test_mode = defined('LAC_TEST_MODE') && LAC_TEST_MODE === true;
  $debug_mode = isset($_GET['lac_debug']);

  try {
    if ($debug_mode) {
      error_log('[LAC DEBUG] Guard entry');
    }

    // Configuration: if gate disabled, bypass
    global $conf;
    if (isset($conf['lac_enabled']) && !$conf['lac_enabled']) {
      return LacErrorHandler::success(null, ['action' => 'bypassed', 'reason' => 'gate_disabled']);
    }

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
    
    if ($debug_mode) { 
      error_log('[LAC DEBUG] guest_detected='.(lac_is_guest()?1:0)); 
    }

    // If user not guest, allow
    if (!lac_is_guest()) {
      return LacErrorHandler::success(null, ['action' => 'allowed', 'reason' => 'user_logged_in']);
    }

    // Optimized consent checking using session manager
    $consentResult = $sessionManager->hasConsent();
    if (!$consentResult['success']) {
      return $errorHandler->handleError(
        'CONSENT_CHECK_ERROR',
        'Failed to check consent status',
        500,
        ['step' => 'consent_check']
      );
    }
    
    $expiryResult = $sessionManager->isConsentExpired();
    if (!$expiryResult['success']) {
      return $errorHandler->handleError(
        'EXPIRY_CHECK_ERROR',
        'Failed to check consent expiry',
        500,
        ['step' => 'expiry_check']
      );
    }
    
    $hasConsent = $consentResult['data'] && !$expiryResult['data'];
    
    // Cookie reconstruction fallback (optimized):
    if (!$hasConsent && isset($_COOKIE[lac_get_cookie_name()]) && ctype_digit($_COOKIE[lac_get_cookie_name()])) {
      $cookieTs = (int)$_COOKIE[lac_get_cookie_name()];
      $age = time() - $cookieTs;
      $cookieValidWindow = lac_get_cookie_max_window();
      $duration = function_exists('lac_get_consent_duration') ? lac_get_consent_duration() : (isset($conf[LAC_CONFIG_CONSENT_DURATION]) ? (int)$conf[LAC_CONFIG_CONSENT_DURATION] : 0);
      $withinCookie = $age < $cookieValidWindow;
      $withinDuration = ($duration === 0) || ($age < ($duration * 60));
      
      if ($withinCookie && $withinDuration) {
        $setConsentResult = $sessionManager->setConsent($cookieTs);
        if (!$setConsentResult['success']) {
          return $errorHandler->handleError(
            'CONSENT_SET_ERROR',
            'Failed to set consent from cookie',
            500,
            ['step' => 'cookie_reconstruction']
          );
        }
        
        $regenerateResult = $sessionManager->regenerateIfNeeded();
        // Note: regenerateIfNeeded can fail but it's not critical
        
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
      return LacErrorHandler::success(null, ['action' => 'allowed', 'reason' => 'valid_consent']);
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
      if ($debug_mode) { 
        error_log('[LAC DEBUG] Detected consent root ("'.LAC_CONSENT_ROOT.'"), skipping redirect'); 
      }
      return LacErrorHandler::success(null, ['action' => 'allowed', 'reason' => 'on_consent_page']);
    }

    // Build redirect absolute or relative path: root index one level above gallery? Provided spec: "root index.php"
    // We assume gallery is deployed at /var/www/piwigo/ and root index is one level up (web root). For runtime we just send '/index.php'.
    $target = '/index.php';

    if ($test_mode) {
      // In test collect intended redirect
      $GLOBALS['lac_test_redirect_to'] = $target;
      return LacErrorHandler::success(null, ['action' => 'test_redirect', 'target' => $target]);
    }

    if ($debug_mode) { 
      error_log('[LAC DEBUG] Redirecting to ' . $target); 
    }
    
    header('Location: ' . $target);
    exit;
    
  } catch (Exception $e) {
    return $errorHandler->handleError(
      'AGE_GATE_ERROR',
      'Age gate processing failed: ' . $e->getMessage(),
      500,
      ['step' => 'general_exception']
    );
  }
}
