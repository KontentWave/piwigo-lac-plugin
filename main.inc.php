<?php
/*
Plugin Name: Legal Age Consent
Version: 1.0.0
Description: Toggle core privacy options in Piwigo.
Author: Your Name
Author URI: https://cores.sk
*/

/**
 * This is the main file of the plugin, called by Piwigo in "include/common.inc.php" line 137.
 * At this point of the code, Piwigo is not completely initialized, so nothing should be done directly
 * except define constants and event handlers (see http://piwigo.org/doc/doku.php?id=dev:plugins)
 */

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');


if (basename(dirname(__FILE__)) != 'lac')
{
  add_event_handler('init', 'lac_error');
  function lac_error()
  {
    global $page;
    $page['errors'][] = 'Legal Age Consent folder name is incorrect, uninstall the plugin and rename it to "lac"';
  }
  return;
}


// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+
global $prefixeTable;

define('LAC_ID',      basename(dirname(__FILE__)));
define('LAC_PATH' ,   PHPWG_PLUGINS_PATH . LAC_ID . '/');
define('LAC_TABLE',   $prefixeTable . 'lac');
define('LAC_ADMIN',   get_root_url() . 'admin.php?page=plugin-' . LAC_ID);
define('LAC_PUBLIC',  get_absolute_root_url() . make_index_url(array('section' => 'lac')) . '/');
define('LAC_DIR',     PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'lac/');
// Central lightweight debug toggle: enable by defining LAC_DEBUG in local config
if (!defined('LAC_DEBUG')) { define('LAC_DEBUG', false); }
if ( (LAC_DEBUG || isset($_GET['lac_debug'])) ) {
  error_log('[LAC DEBUG] main.inc.php bootstrap');
}

// When debug is enabled via query param, direct PHP error_log to a temp file so logs are easy to find.
// This affects only the current request and does not change server config.
if (isset($_GET['lac_debug'])) {
  $tmpLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'piwigo-lac.log';
  // Suppress warnings if ini_set is disabled; best-effort only.
  @ini_set('log_errors', 'On');
  @ini_set('error_log', $tmpLog);
  // Log where we're writing to for confirmation
  error_log('[LAC DEBUG] Logging to ' . $tmpLog);
}


// +-----------------------------------------------------------------------+
// | Add essential event handlers                                         |
// +-----------------------------------------------------------------------+
// init the plugin (language load & config prep)
add_event_handler('init', 'lac_init');
// age gate guard (public side only)
if (!defined('IN_ADMIN')) {
  add_event_handler('init', 'lac_age_gate_guard', EVENT_HANDLER_PRIORITY_NEUTRAL, LAC_PATH.'include/age_gate.inc.php');
}
// minimal admin menu link (keep plugin accessible in backend)
if (defined('IN_ADMIN')) {
  $admin_file = LAC_PATH . 'admin/config.php'; // use dedicated config handler instead of legacy demos
  // Provide a menu link via existing simple function wrapper
  add_event_handler('get_admin_plugin_menu_links', function($menu){
    $menu[] = array('NAME' => l10n('Legal Age Consent'), 'URL' => LAC_ADMIN); return $menu; }, EVENT_HANDLER_PRIORITY_NEUTRAL);
}


/**
 * plugin initialization
 *   - check for upgrades
 *   - unserialize configuration
 *   - load language
 */
function lac_init()
{
  // load plugin language file early for admin/public messages
  load_language('plugin.lang', LAC_PATH);
}
