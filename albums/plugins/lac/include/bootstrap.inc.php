<?php
/**
 * LAC Plugin Bootstrap File
 * Centralizes all common includes and initialization to avoid duplication
 */
defined('LAC_PATH') or die('Hacking attempt!');

// Prevent multiple inclusions of this bootstrap
if (defined('LAC_BOOTSTRAP_LOADED')) {
    return;
}
define('LAC_BOOTSTRAP_LOADED', true);

// Core dependencies loaded in dependency order
if (!defined('LAC_CONSTANTS_LOADED')) {
    include_once LAC_PATH . 'include/constants.inc.php';
    define('LAC_CONSTANTS_LOADED', true);
}

if (!defined('LAC_ERROR_HANDLER_LOADED')) {
    include_once LAC_PATH . 'include/error_handler.inc.php';
    define('LAC_ERROR_HANDLER_LOADED', true);
}

if (!defined('LAC_DATABASE_HELPER_LOADED')) {
    include_once LAC_PATH . 'include/database_helper.inc.php';
    define('LAC_DATABASE_HELPER_LOADED', true);
}

if (!defined('LAC_SESSION_MANAGER_LOADED')) {
    include_once LAC_PATH . 'include/session_manager.inc.php';
    define('LAC_SESSION_MANAGER_LOADED', true);
}

if (!defined('LAC_FUNCTIONS_LOADED')) {
    include_once LAC_PATH . 'include/functions.inc.php';
    define('LAC_FUNCTIONS_LOADED', true);
}

// Optional debug logging for bootstrap
if (lac_is_debug_mode()) {
    error_log('[LAC DEBUG] Bootstrap loaded - all core components available');
}