<?php
defined('LAC_PATH') or die('Hacking attempt!');

/**
 * detect current section
 */
function lac_loc_end_section_init()
{
  global $tokens, $page, $conf;

  if ($tokens[0] == 'lac')
  {
    $page['section'] = 'lac';

    // section_title is for breadcrumb, title is for page <title>
    $page['section_title'] = '<a href="'.get_absolute_root_url().'">'.l10n('Home').'</a>'.$conf['level_separator'].'<a href="'.LAC_PUBLIC.'">'.l10n('Legal Age Consent').'</a>';
    $page['title'] = l10n('Legal Age Consent');

    $page['body_id'] = 'theCorePrivacyTogglePage';
    $page['is_external'] = true; // inform Piwigo that you are on a new page
  }
}

/**
 * include public page
 */
function lac_loc_end_page()
{
  global $page, $template;

  if (isset($page['section']) and $page['section']=='lac')
  {
    include(LAC_PATH . 'include/lac_page.inc.php');
  }
}

/*
 * button on album and photos pages
 */
function lac_add_button()
{
  global $template;

  $template->assign('LAC_PATH', LAC_PATH);
  $template->set_filename('lac_button', realpath(LAC_PATH.'template/my_button.tpl'));
  $button = $template->parse('lac_button', true);

  if (script_basename()=='index')
  {
    $template->add_index_button($button, BUTTONS_RANK_NEUTRAL);
  }
  else
  {
    $template->add_picture_button($button, BUTTONS_RANK_NEUTRAL);
  }
}

/**
 * add a prefilter on photo page
 */
function lac_loc_end_picture()
{
  global $template;

  $template->set_prefilter('picture', 'lac_picture_prefilter');
}

function lac_picture_prefilter($content)
{
  $search = '{if $display_info.author and isset($INFO_AUTHOR)}';
  $replace = '
<div id="Legal Age Consent" class="imageInfo">
  <dt>{\'Legal Age Consent\'|@translate}</dt>
  <dd style="color:orange;">{\'Piwigo rocks\'|@translate}</dd>
</div>
';

  return str_replace($search, $replace.$search, $content);
}
