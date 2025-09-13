<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Maximum allowed length for fallback URL (defensive against extremely large inputs)
if (!defined('LAC_MAX_FALLBACK_URL_LEN')) {
	define('LAC_MAX_FALLBACK_URL_LEN', 2048);
}

// Provide default for test mode constant to silence undefined constant warnings when referenced.
if (!defined('LAC_TEST_MODE')) {
	define('LAC_TEST_MODE', false);
}

/**
 * Determine if current user is a guest.
 */
function lac_is_guest(): bool
{
	global $user;
	return isset($user['is_guest']) && $user['is_guest'];
}

/**
 * Whether the current session signals age consent.
 */
function lac_has_consent(): bool
{
	return !empty($_SESSION['lac_consent_granted']);
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
	return lac_has_consent() ? 'allow' : 'redirect';
}

/**
 * Sanitize fallback URL input; returns sanitized URL or empty string if invalid.
 */
function lac_sanitize_fallback_url(string $url, bool $disallow_internal = false): string
{
	$url = trim($url);
	if ($url === '') return '';
	if (strlen($url) > LAC_MAX_FALLBACK_URL_LEN) { return ''; }
	$san = filter_var($url, FILTER_SANITIZE_URL);
	if (!$san || !preg_match('#^https?://#i', $san)) { return ''; }
	if ($disallow_internal) {
		// Determine current host if available and compare host parts case-insensitively
		$currentHost = $_SERVER['HTTP_HOST'] ?? '';
		$parsed = @parse_url($san);
		if (!empty($currentHost) && isset($parsed['host']) && strcasecmp($parsed['host'], $currentHost) === 0) {
			return ''; // internal URL not allowed
		}
	}
	return $san;
}

// (Legacy file-based fallback removed; lac_fallback_url now stored only in DB.)

