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



// +-----------------------------------------------------------------------+
// | Add event handlers                                                    |
// +-----------------------------------------------------------------------+
// init the plugin
add_event_handler('init', 'lac_init');
// age gate guard (must run very early for public side only)
if (!defined('IN_ADMIN')) {
  add_event_handler('init', 'lac_age_gate_guard', EVENT_HANDLER_PRIORITY_NEUTRAL, LAC_PATH.'include/age_gate.inc.php');
}

/*
 * this is the common way to define event functions: create a new function for each event you want to handle
 */
if (defined('IN_ADMIN'))
{
  // file containing all admin handlers functions
  $admin_file = LAC_PATH . 'include/admin_events.inc.php';

  // admin plugins menu link
  add_event_handler('get_admin_plugin_menu_links', 'lac_admin_plugin_menu_links',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // new tab on photo page
  add_event_handler('tabsheet_before_select', 'lac_tabsheet_before_select',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // new prefiler in Batch Manager
  add_event_handler('get_batch_manager_prefilters', 'lac_add_batch_manager_prefilters',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);
  add_event_handler('perform_batch_manager_prefilters', 'lac_perform_batch_manager_prefilters',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // new action in Batch Manager
  add_event_handler('loc_end_element_set_global', 'lac_loc_end_element_set_global',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);
  add_event_handler('element_set_global_action', 'lac_element_set_global_action',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);
}
else
{
  // file containing all public handlers functions
  $public_file = LAC_PATH . 'include/public_events.inc.php';

  // add a public section
  add_event_handler('loc_end_section_init', 'lac_loc_end_section_init',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);
  add_event_handler('loc_end_index', 'lac_loc_end_page',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);

  // add button on album and photos pages
  add_event_handler('loc_end_index', 'lac_add_button',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);
  add_event_handler('loc_end_picture', 'lac_add_button',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);

  // prefilter on photo page
  add_event_handler('loc_end_picture', 'lac_loc_end_picture',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $public_file);
}

// file containing API function
$ws_file = LAC_PATH . 'include/ws_functions.inc.php';

// add API function
add_event_handler('ws_add_methods', 'lac_ws_add_methods',
    EVENT_HANDLER_PRIORITY_NEUTRAL, $ws_file);


/*
 * event functions can also be wrapped in a class
 */

// file containing the class for menu handlers functions
$menu_file = LAC_PATH . 'include/menu_events.class.php';

// add item to existing menu (EVENT_HANDLER_PRIORITY_NEUTRAL+10 is for compatibility with Advanced Menu Manager plugin)
add_event_handler('blockmanager_apply', array('CorePrivacyToggleMenu', 'blockmanager_apply1'),
  EVENT_HANDLER_PRIORITY_NEUTRAL+10, $menu_file);

// add a new menu block (the declaration must be done every time, in order to be able to manage the menu block in "Menus" screen and Advanced Menu Manager)
add_event_handler('blockmanager_register_blocks', array('CorePrivacyToggleMenu', 'blockmanager_register_blocks'),
  EVENT_HANDLER_PRIORITY_NEUTRAL, $menu_file);
add_event_handler('blockmanager_apply', array('CorePrivacyToggleMenu', 'blockmanager_apply2'),
  EVENT_HANDLER_PRIORITY_NEUTRAL, $menu_file);

// NOTE: blockmanager_apply1() and blockmanager_apply2() can (must) be merged


/**
 * plugin initialization
 *   - check for upgrades
 *   - unserialize configuration
 *   - load language
 */
function lac_init()
{
  global $conf;

  // load plugin language file
  load_language('plugin.lang', LAC_PATH);

  // prepare plugin configuration
  $conf['lac'] = safe_unserialize($conf['lac']);
}
