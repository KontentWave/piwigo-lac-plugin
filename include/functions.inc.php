<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized bootstrap for all dependencies  
include_once LAC_PATH . 'include/bootstrap.inc.php';

// Legacy constant definitions kept for backward compatibility (now reference centralized definitions)
// Maximum allowed length for fallback URL (defensive against extremely large inputs)
if (!defined('LAC_MAX_FALLBACK_URL_LEN')) {
	define('LAC_MAX_FALLBACK_URL_LEN', 2048);
}
if (!defined('LAC_MAX_CONSENT_DURATION')) {
	define('LAC_MAX_CONSENT_DURATION', 43200); // 30 days in minutes
}
if (!defined('LAC_MAX_POST_INPUT_SIZE')) {
	define('LAC_MAX_POST_INPUT_SIZE', 65536); // 64KB limit for any single POST input
}

// Provide default for test mode constant to silence undefined constant warnings when referenced.
if (!defined('LAC_TEST_MODE')) {
	define('LAC_TEST_MODE', false);
}

// Shared cookie constants (centralize to avoid magic literals in multiple files)
if (!defined('LAC_COOKIE_NAME')) { define('LAC_COOKIE_NAME', 'LAC'); }
// Absolute max window (seconds) a consent cookie can be considered for reconstruction
if (!defined('LAC_COOKIE_MAX_WINDOW')) { define('LAC_COOKIE_MAX_WINDOW', 86400); }

/**
 * Set (or refresh) the LAC timestamp cookie with standardized security attributes.
 * Abstracted so root page and any future admin/tooling paths use identical policy.
 * Enhanced with edge case validation.
 */
if (!function_exists('lac_set_consent_cookie')) {
function lac_set_consent_cookie(int $timestamp, ?bool $secureOverride = null): array
{
	$errorHandler = LacErrorHandler::getInstance();
	
	try {
		// Validate timestamp
		$validation = $errorHandler->validateInput($timestamp, 'integer', ['min' => 0]);
		if (!$validation['success']) {
			return LacErrorHandler::error('Invalid timestamp: ' . $validation['error'], 'VALIDATION_ERROR');
		}
		
		// Validate timestamp is not too far in the past or future
		$currentTime = time();
		$maxAge = 86400 * 365; // 1 year
		if ($timestamp < ($currentTime - $maxAge) || $timestamp > ($currentTime + $maxAge)) {
			return LacErrorHandler::error('Timestamp out of reasonable range', 'VALIDATION_ERROR');
		}
		
		$cookieName = lac_get_cookie_name();
		$window = lac_get_cookie_max_window();
		$secure = $secureOverride;
		if ($secure === null) {
			$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
		}
		
		$cookieOptions = [
			'expires'  => $timestamp + $window,
			'path'     => '/',
			'domain'   => '',
			'secure'   => $secure,
			'httponly' => true,
			'samesite' => 'Lax',
		];
		
		$success = setcookie($cookieName, (string)$timestamp, $cookieOptions);
		if (!$success) {
			return LacErrorHandler::error('Failed to set cookie', 'COOKIE_ERROR');
		}
		
		return LacErrorHandler::success(null, ['cookie_set' => true, 'timestamp' => $timestamp]);
		
	} catch (Exception $e) {
		return $errorHandler->handleError(
			'COOKIE_SET_ERROR',
			'Failed to set consent cookie: ' . $e->getMessage(),
			500
		);
	}
}
}

/**
 * Legacy wrapper for lac_set_consent_cookie (maintains void signature)
 * @deprecated Use lac_set_consent_cookie() with standardized error handling
 */
if (!function_exists('lac_set_consent_cookie_legacy')) {
function lac_set_consent_cookie_legacy(int $timestamp, ?bool $secureOverride = null): void
{
	$result = lac_set_consent_cookie($timestamp, $secureOverride);
	if (!$result['success']) {
		error_log('[LAC ERROR] Failed to set consent cookie: ' . $result['message']);
	}
}
}

/**
 * Determine if current user is a guest with enhanced validation.
 * @return array Result with success/error information
 */
function lac_is_guest_with_validation(): array
{
	try {
		global $user;
		
		// Check if $user is properly initialized
		if (!isset($user)) {
			return LacErrorHandler::success(true, ['reason' => 'user_not_set']);
		}
		
		if (!is_array($user)) {
			return LacErrorHandler::success(true, ['reason' => 'user_not_array']);
		}

		// Prefer Piwigo's status field when available
		if (isset($user['status'])) {
			$isGuest = (strtolower((string)$user['status']) === 'guest');
			return LacErrorHandler::success($isGuest, ['user_id' => $user['id'] ?? 'unknown', 'source' => 'status']);
		}

		// Fallback: compare user id against configured guest id
		global $conf;
		if (isset($user['id']) && isset($conf['guest_id'])) {
			$isGuest = ((string)$user['id'] === (string)$conf['guest_id']);
			return LacErrorHandler::success($isGuest, ['user_id' => $user['id'], 'source' => 'guest_id']);
		}

		// Last resort: use is_guest flag if present, assume guest otherwise
		$isGuest = !empty($user['is_guest']);
		$reason = array_key_exists('is_guest', $user) ? 'is_guest_flag' : 'assume_guest_default';
		return LacErrorHandler::success($isGuest, ['user_id' => $user['id'] ?? 'unknown', 'source' => $reason]);
		
	} catch (Exception $e) {
		$errorHandler = LacErrorHandler::getInstance();
		return $errorHandler->handleError(
			'USER_CHECK_ERROR',
			'Failed to check user guest status: ' . $e->getMessage(),
			500
		);
	}
}

/**
 * Determine if current user is a guest (legacy function with enhanced validation).
 */
function lac_is_guest(): bool
{
	$result = lac_is_guest_with_validation();
	return $result['success'] ? $result['data'] : true; // Default to guest if error
}

/**
 * Whether the current session signals age consent (with enhanced validation)
 * @return array Result with success/error information
 */
function lac_has_consent_with_validation(): array
{
	try {
		$sessionManager = LacSessionManager::getInstance();
		
		// Check for valid consent using standardized error handling
		$consentResult = $sessionManager->hasConsent();
		if (!$consentResult['success']) {
			return $consentResult;
		}
		
		if (!$consentResult['data']) {
			return LacErrorHandler::success(false, ['reason' => 'no_consent']);
		}
		
		// Check if consent has expired
		$expiryResult = $sessionManager->isConsentExpired();
		if (!$expiryResult['success']) {
			return $expiryResult;
		}
		
		if ($expiryResult['data']) {
			// Clear expired consent
			$clearResult = $sessionManager->clearConsent();
			// Note: clearResult failure is not critical for the main flow
			return LacErrorHandler::success(false, ['reason' => 'consent_expired']);
		}
		
		return LacErrorHandler::success(true, ['reason' => 'valid_consent']);
		
	} catch (Exception $e) {
		$errorHandler = LacErrorHandler::getInstance();
		return $errorHandler->handleError(
			'CONSENT_CHECK_ERROR',
			'Failed to check consent status: ' . $e->getMessage(),
			500
		);
	}
}

/**
 * Whether the current session signals age consent (optimized version)
 */
function lac_has_consent(): bool
{
	$result = lac_has_consent_with_validation();
	return $result['success'] ? $result['data'] : false; // Default to no consent on error
}

/**
 * Evaluate whether consent has expired based on configured duration (with enhanced validation)
 * @param int $durationMinutes Duration in minutes
 * @return array Result with success/error information
 */
function lac_consent_expired_with_validation(int $durationMinutes): array
{
	try {
		$errorHandler = LacErrorHandler::getInstance();
		
		// Validate duration parameter
		$validation = $errorHandler->validateInput($durationMinutes, 'integer', ['min' => 0, 'max' => LAC_MAX_CONSENT_DURATION]);
		if (!$validation['success']) {
			return LacErrorHandler::error('Invalid duration: ' . $validation['error'], 'VALIDATION_ERROR');
		}
		
		$sessionManager = LacSessionManager::getInstance();
		$expiryResult = $sessionManager->isConsentExpired();
		
		if (!$expiryResult['success']) {
			return $expiryResult;
		}
		
		return LacErrorHandler::success($expiryResult['data'], ['duration_minutes' => $durationMinutes]);
		
	} catch (Exception $e) {
		$errorHandler = LacErrorHandler::getInstance();
		return $errorHandler->handleError(
			'EXPIRY_CHECK_ERROR',
			'Failed to check consent expiry: ' . $e->getMessage(),
			500
		);
	}
}

/**
 * Evaluate whether consent has expired based on configured duration (optimized version)
 * Uses session manager for efficient checking
 */
function lac_consent_expired(int $durationMinutes): bool
{
	$result = lac_consent_expired_with_validation($durationMinutes);
	return $result['success'] ? $result['data'] : true; // Default to expired on error
}

/**
 * Retrieve consent duration (minutes) with enhanced validation and error handling.
 * Falls back to DB direct lookup if missing/zero but a cached value
 * may not yet be loaded into $conf (robustness for early init ordering issues reported in production).
 * @return array Result with success/error information
 */
function lac_get_consent_duration_with_validation(): array
{
	try {
		global $conf;
		$debug = lac_is_debug_mode();
		
		if (isset($conf[LAC_CONFIG_CONSENT_DURATION])) {
			$val = (int)$conf[LAC_CONFIG_CONSENT_DURATION];
			if ($val > 0) {
				if ($debug) { error_log('[LAC DEBUG] duration from $conf = '.$val.'m'); }
				return LacErrorHandler::success($val, ['source' => 'global_config']);
			}
			// value is 0 -> attempt DB fallback below
		}
		
		static $fetched = false; 
		static $cached = 0;
		if ($fetched) { 
			return LacErrorHandler::success($cached, ['source' => 'static_cache']); 
		}
		$fetched = true;
		
		// Use centralized database helper for fallback lookup
		$dbHelper = LacDatabaseHelper::getInstance();
		$configResult = $dbHelper->getConfigParam(LAC_CONFIG_CONSENT_DURATION, 0);
		
		if (!$configResult['success']) {
			if ($debug) {
				error_log('[LAC DEBUG] duration fallback failed: ' . $configResult['message']);
			}
			$cached = 0;
			return LacErrorHandler::success(0, ['source' => 'default_fallback', 'reason' => 'db_error']);
		}
		
		$cached = (int)$configResult['data'];
		
		if ($debug) { 
			error_log('[LAC DEBUG] duration from DB fallback = '.$cached.'m'); 
		}
		
		return LacErrorHandler::success($cached, ['source' => 'database_fallback']);
		
	} catch (Exception $e) {
		$errorHandler = LacErrorHandler::getInstance();
		return $errorHandler->handleError(
			'DURATION_LOOKUP_ERROR',
			'Failed to retrieve consent duration: ' . $e->getMessage(),
			500
		);
	}
}

/**
 * Retrieve consent duration (minutes). Falls back to DB direct lookup if missing/zero but a cached value
 * may not yet be loaded into $conf (robustness for early init ordering issues reported in production).
 */
function lac_get_consent_duration(): int
{
	$result = lac_get_consent_duration_with_validation();
	return $result['success'] ? $result['data'] : 0; // Default to session-only consent on error
}

/**
 * Core decision function used by tests with enhanced validation; returns one of: 'allow' or 'redirect'.
 * @return array Result with success/error information
 */
function lac_gate_decision_with_validation(): array
{
	try {
		global $conf, $user;
		$enabled = isset($conf['lac_enabled']) ? (bool)$conf['lac_enabled'] : true;
		if (!$enabled) {
			return LacErrorHandler::success('allow', ['reason' => 'gate_disabled']);
		}
		$isGuest = (!isset($user['is_guest']) || $user['is_guest']);
		if (!$isGuest) {
			return LacErrorHandler::success('allow', ['reason' => 'user_logged_in']);
		}
		// Legacy flag semantics (hybrid): ignored when duration > 0, allowed & upgraded when duration == 0
		$duration = (int)($conf['lac_consent_duration'] ?? 0);
		if (isset($_SESSION['lac_consent_granted']) && $_SESSION['lac_consent_granted'] === true) {
			if ($duration === 0) {
				if (!isset($_SESSION['lac_consent'])) {
					$_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => time()];
				}
				return LacErrorHandler::success('allow', ['reason' => 'legacy_flag_session_only']);
			}
			// duration > 0 -> ignore legacy flag, continue to structured check
		}
		// Structured consent path
		if (isset($_SESSION['lac_consent']) && !empty($_SESSION['lac_consent']['granted'])) {
			if ($duration === 0) {
				return LacErrorHandler::success('allow', ['reason' => 'session_only']);
			}
			$ts = (int)($_SESSION['lac_consent']['timestamp'] ?? 0);
			if ($ts === 0) {
				return LacErrorHandler::success('allow', ['reason' => 'missing_timestamp']);
			}
			if ((time() - $ts) >= ($duration * 60)) {
				unset($_SESSION['lac_consent']);
				return LacErrorHandler::success('redirect', ['reason' => 'expired']);
			}
			return LacErrorHandler::success('allow', ['reason' => 'valid_structured']);
		}
		return LacErrorHandler::success('redirect', ['reason' => 'no_consent']);
	} catch (Exception $e) {
		$errorHandler = LacErrorHandler::getInstance();
		return $errorHandler->handleError(
			'GATE_DECISION_ERROR',
			'Failed to make gate decision: ' . $e->getMessage(),
			500
		);
	}
}

/**
 * Core decision function used by tests; returns one of: 'allow' or 'redirect'.
 */
function lac_gate_decision(): string
{
	$result = lac_gate_decision_with_validation();
	return $result['success'] ? $result['data'] : 'redirect'; // Default to redirect on error for security
}

/**
 * Sanitize fallback URL input with enhanced validation and standardized error handling.
 * Enhanced validation against multiple attack vectors.
 * @param string $url URL to sanitize
 * @param bool $disallow_internal Whether to disallow internal URLs
 * @return array Result with success/error information
 */
function lac_sanitize_fallback_url_with_validation(string $url, bool $disallow_internal = false): array
{
	try {
		$errorHandler = LacErrorHandler::getInstance();
		
		// Validate input
		$validation = $errorHandler->validateInput($url, 'string', ['max_length' => LAC_MAX_FALLBACK_URL_LEN]);
		if (!$validation['success']) {
			return LacErrorHandler::error('URL validation failed: ' . $validation['error'], 'VALIDATION_ERROR');
		}
		
		$url = trim($url);
		if ($url === '') {
			return LacErrorHandler::success('', ['reason' => 'empty_url']);
		}
		
		// Check for dangerous schemes first
		$dangerous_schemes = ['javascript:', 'data:', 'vbscript:', 'file:', 'ftp:', 'mailto:', 'news:', 'gopher:'];
		foreach ($dangerous_schemes as $scheme) {
			if (stripos($url, $scheme) === 0) {
				return LacErrorHandler::error('Dangerous URL scheme detected', 'SECURITY_ERROR', ['scheme' => $scheme]);
			}
		}
		
		// Check for encoded dangerous schemes (basic URL encoding bypass prevention)
		$encoded_js = ['%6a%61%76%61%73%63%72%69%70%74%3a', '%64%61%74%61%3a']; // javascript:, data:
		foreach ($encoded_js as $encoded) {
			if (stripos($url, $encoded) !== false) {
				return LacErrorHandler::error('Encoded dangerous scheme detected', 'SECURITY_ERROR');
			}
		}
		
		$san = filter_var($url, FILTER_SANITIZE_URL);
		if (!$san || !preg_match('#^https?://#i', $san)) { 
			return LacErrorHandler::error('Invalid URL format (must be http/https)', 'VALIDATION_ERROR');
		}
		
		// Parse URL for additional security checks
		$parsed = parse_url($san);
		if (!$parsed || !isset($parsed['host'])) {
			return LacErrorHandler::error('Invalid URL structure', 'VALIDATION_ERROR');
		}
		
		// Check for path traversal attempts in path
		if (isset($parsed['path']) && (strpos($parsed['path'], '..') !== false || strpos($parsed['path'], '//') !== false)) {
			return LacErrorHandler::error('Path traversal attempt detected', 'SECURITY_ERROR');
		}
		
		// Check for suspicious query parameters that could be used for attacks
		if (isset($parsed['query'])) {
			$suspicious_params = ['<script', 'javascript:', 'vbscript:', 'onload=', 'onerror=', 'eval('];
			foreach ($suspicious_params as $param) {
				if (stripos($parsed['query'], $param) !== false) {
					return LacErrorHandler::error('Suspicious query parameter detected', 'SECURITY_ERROR', ['param' => $param]);
				}
			}
		}
		
		// Check for internal URLs if requested
		if ($disallow_internal) {
			$currentHost = $_SERVER['HTTP_HOST'] ?? '';
			if (!empty($currentHost) && strcasecmp($parsed['host'], $currentHost) === 0) {
				return LacErrorHandler::error('Internal URLs not allowed', 'VALIDATION_ERROR');
			}
			
			// Also block localhost, 127.0.0.1, and private IP ranges
			if (in_array(strtolower($parsed['host']), ['localhost', '127.0.0.1', '0.0.0.0']) ||
			    preg_match('/^192\.168\./', $parsed['host']) ||
			    preg_match('/^10\./', $parsed['host']) ||
			    preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $parsed['host'])) {
				return LacErrorHandler::error('Private/local addresses not allowed', 'VALIDATION_ERROR');
			}
		}
		
		return LacErrorHandler::success($san, ['validated' => true, 'host' => $parsed['host']]);
		
	} catch (Exception $e) {
		$errorHandler = LacErrorHandler::getInstance();
		return $errorHandler->handleError(
			'URL_SANITIZATION_ERROR',
			'Failed to sanitize URL: ' . $e->getMessage(),
			500
		);
	}
}

/**
 * Sanitize fallback URL input; returns sanitized URL or empty string if invalid.
 * Enhanced validation against multiple attack vectors.
 */
function lac_sanitize_fallback_url(string $url, bool $disallow_internal = false): string
{
	$result = lac_sanitize_fallback_url_with_validation($url, $disallow_internal);
	return $result['success'] ? $result['data'] : ''; // Return empty string on error for legacy compatibility
}

// (Legacy file-based fallback removed; lac_fallback_url now stored only in DB.)


/**
 * Determine if the current user is exempt from age gating.
 * Contract:
 * - Inputs: none (uses $user, $conf, session/cookie via helpers)
 * - Output: bool (true = exempt, false = not exempt)
 * - Error modes: On any internal error or missing dependency, return false (not exempt) without throwing
 * Rules:
 * - If gate globally disabled (lac_enabled = false): exempt
 * - Admin/Webmaster: exempt always
 * - Logged-in non-admin: exempt when lac_apply_to_logged_in = false; otherwise require consent like guests
 * - Guest: not exempt unless valid consent present (duration rules apply via session manager)
 */
if (!function_exists('lac_is_user_exempt')) {
function lac_is_user_exempt(): bool
{
	try {
		global $conf, $user;

		// If config array not available, be conservative (not exempt) but avoid fatals
		if (!isset($conf) || !is_array($conf)) {
			return false;
		}

		// If gate is disabled globally, everyone is exempt
		$enabled = isset($conf[LAC_CONFIG_ENABLED]) ? (bool)$conf[LAC_CONFIG_ENABLED] : true;
		if ($enabled === false) {
			return true;
		}

		// Admin/webmaster bypass
		$isAdmin = false;
		if (isset($user) && is_array($user)) {
			$status = isset($user['status']) ? strtolower((string)$user['status']) : '';
			$isAdmin = (!empty($user['is_admin'])) || ($status === 'admin' || $status === 'webmaster');
		}
		if ($isAdmin) {
			return true;
		}

		// Determine if user is guest
		$guestCheck = lac_is_guest_with_validation();
		$isGuest = $guestCheck['success'] ? (bool)$guestCheck['data'] : true; // default to guest on error

		// Determine setting for applying to logged-in users
		$applyToLoggedIn = isset($conf[LAC_CONFIG_APPLY_LOGGED_IN]) ? (bool)$conf[LAC_CONFIG_APPLY_LOGGED_IN] : false;

		if (!$isGuest) {
			// Logged-in non-admin
			if ($applyToLoggedIn === false) {
				return true; // exempt when not applying to logged-in users
			}
			// When applying to logged-in, require consent checks like guests
		}

		// For guests or logged-in when applyToLoggedIn=true: require consent
		$hasConsent = lac_has_consent();
		if ($hasConsent) {
			// Additionally enforce expiry when duration > 0
			$duration = lac_get_consent_duration();
			if ($duration > 0) {
				$expired = lac_consent_expired($duration);
				return !$expired;
			}
			return true; // session-only consent present
		}

		// No valid consent → not exempt
		return false;

	} catch (Throwable $e) {
		// Do not throw; be conservative
		return false;
	}
}
}

