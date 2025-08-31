<?php
defined('LAC_PATH') or die('Hacking attempt!');

global $page, $template, $conf, $user, $tokens, $pwg_loaded_plugins;


# DO SOME STUFF HERE... or not !


$template->assign(array(
  // this is useful when having big blocks of text which must be translated
  // prefer separated HTML files over big lang.php files
  'INTRO_CONTENT' => load_language('intro.html', LAC_PATH, array('return'=>true)),
  'LAC_PATH' => LAC_PATH,
  'LAC_ABS_PATH' => realpath(LAC_PATH).'/',
  ));

$template->set_filename('lac_page', realpath(LAC_PATH . 'template/lac_page.tpl'));
$template->assign_var_from_handle('CONTENT', 'lac_page');
