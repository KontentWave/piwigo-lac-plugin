<?php
defined('LAC_PATH') or die('Hacking attempt!');

/**
 * Centralized LAC Constants Definition
 * All LAC-related constants defined in one place to avoid duplication
 */

// Core plugin constants (set in main.inc.php, referenced here for completeness)
// LAC_PATH, LAC_PUBLIC, LAC_ADMIN, LAC_DIR, LAC_TABLE

// Security and validation limits
if (!defined('LAC_MAX_FALLBACK_URL_LEN')) {
    define('LAC_MAX_FALLBACK_URL_LEN', 2048);
}
if (!defined('LAC_MAX_CONSENT_DURATION')) {
    define('LAC_MAX_CONSENT_DURATION', 43200); // 30 days in minutes
}
if (!defined('LAC_MAX_POST_INPUT_SIZE')) {
    define('LAC_MAX_POST_INPUT_SIZE', 65536); // 64KB limit for any single POST input
}

// Default fallback URL when no configuration is set and no referer available
if (!defined('LAC_DEFAULT_FALLBACK_URL')) {
    define('LAC_DEFAULT_FALLBACK_URL', 'https://www.google.com');
}

// Cookie configuration
if (!defined('LAC_COOKIE_NAME')) { 
    define('LAC_COOKIE_NAME', 'LAC'); 
}
if (!defined('LAC_COOKIE_MAX_WINDOW')) { 
    define('LAC_COOKIE_MAX_WINDOW', 86400); // 24 hours in seconds
}

// Session configuration
if (!defined('LAC_SESSION_REGENERATION_INTERVAL')) {
    define('LAC_SESSION_REGENERATION_INTERVAL', 300); // 5 minutes
}

// Consent page routing
if (!defined('LAC_CONSENT_ROOT')) {
    define('LAC_CONSENT_ROOT', '/index.php');
}

// Debug and testing
if (!defined('LAC_DEBUG')) {
    define('LAC_DEBUG', false);
}
if (!defined('LAC_TEST_MODE')) {
    define('LAC_TEST_MODE', false);
}

// Session key constants (prevent typos in session key names)
if (!defined('LAC_SESSION_CONSENT_KEY')) {
    define('LAC_SESSION_CONSENT_KEY', 'lac_consent');
}
if (!defined('LAC_SESSION_CONSENT_LEGACY_KEY')) {
    define('LAC_SESSION_CONSENT_LEGACY_KEY', 'lac_consent_granted');
}
if (!defined('LAC_SESSION_REGENERATED_KEY')) {
    define('LAC_SESSION_REGENERATED_KEY', 'lac_session_regenerated');
}
if (!defined('LAC_SESSION_REDIRECT_KEY')) {
    define('LAC_SESSION_REDIRECT_KEY', 'LAC_REDIRECT');
}

// Configuration parameter names (prevent typos in config keys)
if (!defined('LAC_CONFIG_ENABLED')) {
    define('LAC_CONFIG_ENABLED', 'lac_enabled');
}
if (!defined('LAC_CONFIG_FALLBACK_URL')) {
    define('LAC_CONFIG_FALLBACK_URL', 'lac_fallback_url');
}
if (!defined('LAC_CONFIG_CONSENT_DURATION')) {
    define('LAC_CONFIG_CONSENT_DURATION', 'lac_consent_duration');
}
if (!defined('LAC_CONFIG_APPLY_LOGGED_IN')) {
    define('LAC_CONFIG_APPLY_LOGGED_IN', 'lac_apply_to_logged_in');
}

// URL parameter names
if (!defined('LAC_PARAM_DEBUG')) {
    define('LAC_PARAM_DEBUG', 'lac_debug');
}
if (!defined('LAC_PARAM_DEBUG_VERBOSE')) {
    define('LAC_PARAM_DEBUG_VERBOSE', 'lac_debug_verbose');
}
/**
 * Get the default fallback URL from configuration
 * This allows for easy customization in derived installations
 * 
 * @return string Default fallback URL
 */
function lac_get_default_fallback_url(): string {
    // Allow override via environment variable for deployment flexibility
    if (!empty($_ENV['LAC_DEFAULT_FALLBACK_URL'])) {
        return $_ENV['LAC_DEFAULT_FALLBACK_URL'];
    }
    
    // Allow override via server variable
    if (!empty($_SERVER['LAC_DEFAULT_FALLBACK_URL'])) {
        return $_SERVER['LAC_DEFAULT_FALLBACK_URL'];
    }
    
    // Default to Google as a safe, always-available fallback
    return LAC_DEFAULT_FALLBACK_URL;
}

// URL parameter names
if (!defined('LAC_PARAM_DEBUG')) {
    define('LAC_PARAM_DEBUG', 'lac_debug');
}
if (!defined('LAC_PARAM_DEBUG_VERBOSE')) {
    define('LAC_PARAM_DEBUG_VERBOSE', 'lac_debug_verbose');
}
if (!defined('LAC_PARAM_ACCEPT')) {
    define('LAC_PARAM_ACCEPT', 'lac_accept');
}

/**
 * Helper function to check if debug mode is active
 */
function lac_is_debug_mode(): bool {
    return (defined('LAC_DEBUG') && LAC_DEBUG) || isset($_GET[LAC_PARAM_DEBUG]);
}

/**
 * Helper function to check if verbose debug mode is active
 */
function lac_is_verbose_debug_mode(): bool {
    return lac_is_debug_mode() && isset($_GET[LAC_PARAM_DEBUG_VERBOSE]);
}

/**
 * Helper function to get cookie name consistently
 */
function lac_get_cookie_name(): string {
    return LAC_COOKIE_NAME;
}

/**
 * Helper function to get cookie max window consistently
 */
function lac_get_cookie_max_window(): int {
    return LAC_COOKIE_MAX_WINDOW;
}