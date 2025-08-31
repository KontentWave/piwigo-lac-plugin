<?php
defined('LAC_PATH') or die('Hacking attempt!');

/**
 * admin plugins menu link
 */
function lac_admin_plugin_menu_links($menu)
{
  $menu[] = array(
    'NAME' => l10n('Legal Age Consent'),
    'URL' => LAC_ADMIN,
    );

  return $menu;
}

/**
 * add a tab on photo properties page
 */
function lac_tabsheet_before_select($sheets, $id)
{
  if ($id == 'photo')
  {
    $sheets['lac'] = array(
      'caption' => l10n('Legal Age Consent'),
      'url' => LAC_ADMIN.'-photo&amp;image_id='.$_GET['image_id'],
      );
  }

  return $sheets;
}

/**
 * add a prefilter to the Batch Downloader
 */
function lac_add_batch_manager_prefilters($prefilters)
{
  $prefilters[] = array(
    'ID' => 'lac',
    'NAME' => l10n('Legal Age Consent'),
    );

  return $prefilters;
}

/**
 * perform added prefilter
 */
function lac_perform_batch_manager_prefilters($filter_sets, $prefilter)
{
  if ($prefilter == 'lac')
  {
    $query = '
SELECT id
  FROM '.IMAGES_TABLE.'
  ORDER BY RAND()
  LIMIT 20
;';
    $filter_sets[] = query2array($query, null, 'id');
  }

  return $filter_sets;
}

/**
 * add an action to the Batch Manager
 */
function lac_loc_end_element_set_global()
{
  global $template;

  /*
    CONTENT is optional
    for big contents it is advised to use a template file

    $template->set_filename('lac_batchmanager_action', realpath(LAC_PATH.'template/batchmanager_action.tpl'));
    $content = $template->parse('lac_batchmanager_action', true);
   */
  $template->append('element_set_global_plugins_actions', array(
    'ID' => 'lac',
    'NAME' => l10n('Legal Age Consent'),
    'CONTENT' => '<label><input type="checkbox" name="check_lac"> '.l10n('Check me!').'</label>',
    ));
}

/**
 * perform added action
 */
function lac_element_set_global_action($action, $collection)
{
  global $page;

  if ($action == 'lac')
  {
    if (empty($_POST['check_lac']))
    {
      $page['warnings'][] = l10n('Nothing appened, but you didn\'t check the box!');
    }
    else
    {
      $page['infos'][] = l10n('Nothing appened, but you checked the box!');
    }
  }
}
