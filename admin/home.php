<?php
defined('LAC_PATH') or die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// | Home tab                                                              |
// +-----------------------------------------------------------------------+

// send variables to template
$template->assign(array(
  'lac' => $conf['lac'],
  'INTRO_CONTENT' => load_language('intro.html', LAC_PATH, array('return'=>true)),
  ));

// define template file
$template->set_filename('lac_content', realpath(LAC_PATH . 'admin/template/home.tpl'));
