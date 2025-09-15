<?php
defined('LAC_PATH') or die('Hacking attempt!');

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
 */
if (!function_exists('lac_set_consent_cookie')) {
function lac_set_consent_cookie(int $timestamp, ?bool $secureOverride = null): void
{
	$cookieName = defined('LAC_COOKIE_NAME') ? LAC_COOKIE_NAME : 'LAC';
	$window = defined('LAC_COOKIE_MAX_WINDOW') ? LAC_COOKIE_MAX_WINDOW : 86400;
	$secure = $secureOverride;
	if ($secure === null) {
		$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
	}
	setcookie($cookieName, (string)$timestamp, [
		'expires'  => $timestamp + $window,
		'path'     => '/',
		'domain'   => '',
		'secure'   => $secure,
		'httponly' => true,
		'samesite' => 'Lax',
	]);
}
}

/**
 * Determine if current user is a guest.
 */
function lac_is_guest(): bool
{
	global $user;
	if (!isset($user) || !is_array($user)) { return true; }
	if (!array_key_exists('is_guest', $user)) { return true; }
	return !empty($user['is_guest']);
}

/**
 * Whether the current session signals age consent.
 */
function lac_has_consent(): bool
{
	// Structured consent takes precedence
	if (!empty($_SESSION['lac_consent']) && is_array($_SESSION['lac_consent'])) {
		$c = $_SESSION['lac_consent'];
		return !empty($c['granted']);
	}
	// Upgrade legacy flag (for backward compatibility and first acceptance) regardless of duration
	if (!empty($_SESSION['lac_consent_granted'])) {
		$_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => time()];
		if (isset($_GET['lac_debug'])) {
			error_log('[LAC DEBUG] Upgraded legacy consent flag to structured form');
		}
		return true;
	}
	return false;
}

/**
 * Evaluate whether consent has expired based on configured duration (minutes).
 * Returns true if expired, false if still valid or session-only with duration 0.
 */
function lac_consent_expired(int $durationMinutes): bool
{
	if ($durationMinutes <= 0) { return false; } // session-only
	if (empty($_SESSION['lac_consent']) || !is_array($_SESSION['lac_consent'])) { return true; }
	$ts = $_SESSION['lac_consent']['timestamp'] ?? 0;
	if (!$ts) { return true; }
	$exp = $ts + ($durationMinutes * 60);
	return time() >= $exp;
}

/**
 * Retrieve consent duration (minutes). Falls back to DB direct lookup if missing/zero but a cached value
 * may not yet be loaded into $conf (robustness for early init ordering issues reported in production).
 */
function lac_get_consent_duration(): int
{
	global $conf;
	$debug = (defined('LAC_DEBUG') && LAC_DEBUG) || isset($_GET['lac_debug']);
	if (isset($conf['lac_consent_duration'])) {
		$val = (int)$conf['lac_consent_duration'];
		if ($val > 0) {
			if ($debug) { error_log('[LAC DEBUG] duration from $conf = '.$val.'m'); }
			return $val;
		}
		// value is 0 -> attempt DB fallback below
	}
	static $fetched = false; static $cached = 0;
	if ($fetched) { return $cached; }
	$fetched = true;
	// Attempt lightweight direct DB read mirroring root index approach
	try {
		if (isset($conf['db_host'])) { // full Piwigo env already loaded (likely have it), so nothing to do
			if (isset($conf['lac_consent_duration'])) { $cached = (int)$conf['lac_consent_duration']; if ($debug) { error_log('[LAC DEBUG] duration fallback early reuse $conf = '.$cached.'m'); } return $cached; }
		}
		$localConf = [];
		$prefixeTable = 'piwigo_';
		if (file_exists(PHPWG_ROOT_PATH . 'local/config/database.inc.php')) {
			include PHPWG_ROOT_PATH . 'local/config/database.inc.php';
		}
		if (!empty($localConf['db_host'])) { // unlikely path because variable names differ; keep compatibility guard
			$dbHost = $localConf['db_host'];
		}
		// We also try global $conf style (as used by root index direct include) for consistency
		if (!empty($conf['db_host'])) {
			// Local helper duplication (cannot rely on root index bootstrap here)
			if (!function_exists('lac_safe_table')) {
				function lac_safe_table(string $prefix, string $name): string {
					if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) { $prefix = 'piwigo_'; }
					return $prefix . $name;
				}
			}
			$mysqli = mysqli_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
			if ($mysqli) {
				$prefix = $conf['prefix_table'] ?? ($conf['prefixeTable'] ?? 'piwigo_');
				$configTable = lac_safe_table($prefix, 'config');
				$stmt = mysqli_prepare($mysqli, "SELECT value FROM `{$configTable}` WHERE param=? LIMIT 1");
				if ($stmt) {
					$param = 'lac_consent_duration';
					if (mysqli_stmt_bind_param($stmt, 's', $param) && mysqli_stmt_execute($stmt)) {
						$res = mysqli_stmt_get_result($stmt);
						if ($res && ($row = mysqli_fetch_assoc($res))) {
							$cached = (int)$row['value'];
						}
					}
					mysqli_stmt_close($stmt);
				} else if ($debug) {
					error_log('[LAC DEBUG] Failed to prepare duration query in fallback: ' . mysqli_error($mysqli));
				}
				mysqli_close($mysqli);
			} else if ($debug) {
				error_log('[LAC DEBUG] Database connection failed in duration fallback');
			}
		}
	} catch (Throwable $e) { 
		if ($debug) { error_log('[LAC DEBUG] Error in duration fallback: ' . $e->getMessage()); }
	}
	if ($debug) { error_log('[LAC DEBUG] duration from DB fallback = '.$cached.'m'); }
	return $cached;
}

/**
 * Core decision function used by tests; returns one of: 'allow' or 'redirect'.
 */
function lac_gate_decision(): string
{
	if (function_exists('pwg_get_conf')) {
			// prefer direct $conf to avoid dependency during early init
		}
		global $conf;
		if (isset($conf['lac_enabled']) && !$conf['lac_enabled']) { return 'allow'; }
		if (!lac_is_guest()) { return 'allow'; }
		// Determine duration if present
		$duration = lac_get_consent_duration();
		if (lac_has_consent()) {
			if (lac_consent_expired($duration)) {
				unset($_SESSION['lac_consent']);
				unset($_SESSION['lac_consent_granted']);
				return 'redirect';
			}
			return 'allow';
		}
		return 'redirect';
}

/**
 * Sanitize fallback URL input; returns sanitized URL or empty string if invalid.
 * Enhanced validation against multiple attack vectors.
 */
function lac_sanitize_fallback_url(string $url, bool $disallow_internal = false): string
{
	$url = trim($url);
	if ($url === '') return '';
	if (strlen($url) > LAC_MAX_FALLBACK_URL_LEN) { return ''; }
	
	// Check for dangerous schemes first
	$dangerous_schemes = ['javascript:', 'data:', 'vbscript:', 'file:', 'ftp:', 'mailto:', 'news:', 'gopher:'];
	foreach ($dangerous_schemes as $scheme) {
		if (stripos($url, $scheme) === 0) {
			return ''; // Reject dangerous schemes
		}
	}
	
	// Check for encoded dangerous schemes (basic URL encoding bypass prevention)
	$encoded_js = ['%6a%61%76%61%73%63%72%69%70%74%3a', '%64%61%74%61%3a']; // javascript:, data:
	foreach ($encoded_js as $encoded) {
		if (stripos($url, $encoded) !== false) {
			return '';
		}
	}
	
	$san = filter_var($url, FILTER_SANITIZE_URL);
	if (!$san || !preg_match('#^https?://#i', $san)) { return ''; }
	
	// Parse URL for additional security checks
	$parsed = parse_url($san);
	if (!$parsed || !isset($parsed['host'])) {
		return ''; // Invalid URL structure
	}
	
	// Check for path traversal attempts in path
	if (isset($parsed['path']) && (strpos($parsed['path'], '..') !== false || strpos($parsed['path'], '//') !== false)) {
		return ''; // Path traversal attempt
	}
	
	// Check for suspicious query parameters that could be used for attacks
	if (isset($parsed['query'])) {
		$suspicious_params = ['<script', 'javascript:', 'vbscript:', 'onload=', 'onerror=', 'eval('];
		foreach ($suspicious_params as $param) {
			if (stripos($parsed['query'], $param) !== false) {
				return ''; // Suspicious query parameter
			}
		}
	}
	
	// Check for internal URLs if requested
	if ($disallow_internal) {
		$currentHost = $_SERVER['HTTP_HOST'] ?? '';
		if (!empty($currentHost) && strcasecmp($parsed['host'], $currentHost) === 0) {
			return ''; // internal URL not allowed
		}
		
		// Also block localhost, 127.0.0.1, and private IP ranges
		if (in_array(strtolower($parsed['host']), ['localhost', '127.0.0.1', '0.0.0.0']) ||
		    preg_match('/^192\.168\./', $parsed['host']) ||
		    preg_match('/^10\./', $parsed['host']) ||
		    preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $parsed['host'])) {
			return ''; // Private/local addresses not allowed
		}
	}
	
	return $san;
}

// (Legacy file-based fallback removed; lac_fallback_url now stored only in DB.)

