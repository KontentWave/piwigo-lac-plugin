<?php
/**
 * This is the main administration page, if you have only one admin page you can put
 * directly its code here or using the tabsheet system like bellow
 */

defined('LAC_PATH') or die('Hacking attempt!');

global $template, $page, $conf;


// get current tab (default now 'config' since legacy 'home' welcome tab removed)
$page['tab'] = isset($_GET['tab']) ? $_GET['tab'] : 'config';

// Whitelist of valid tabs to prevent inclusion of removed/unknown pages
$valid_tabs = array('config');
if (!in_array($page['tab'], $valid_tabs, true)) {
  $page['tab'] = 'config';
}

// plugin tabsheet setup
{
  // tabsheet
  include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
  $tabsheet = new tabsheet();
  $tabsheet->set_id('lac');

  // Only configuration tab remains ("Welcome" tab removed as cosmetic skeleton artifact)
  $tabsheet->add('config', l10n('Configuration'), LAC_ADMIN . '-config');
  $tabsheet->select($page['tab']);
  $tabsheet->assign();
}

// include page
include(LAC_PATH . 'admin/' . $page['tab'] . '.php');

// template vars
$template->assign(array(
  'LAC_PATH'=> LAC_PATH, // used for images, scripts, ... access
  'LAC_ABS_PATH'=> realpath(LAC_PATH), // used for template inclusion (Smarty needs a real path)
  'LAC_ADMIN' => LAC_ADMIN,
  ));

// send page content
$template->assign_var_from_handle('ADMIN_CONTENT', 'lac_content');
