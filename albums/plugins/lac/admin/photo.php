<?php
defined('LAC_PATH') or die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// | Photo[Legal Age Consent] tab                                                   |
// +-----------------------------------------------------------------------+

$page['active_menu'] = get_active_menu('photo'); // force oppening "Photos" menu block

/* Basic checks */
check_status(ACCESS_ADMINISTRATOR);

check_input_parameter('image_id', $_GET, false, PATTERN_ID);

$admin_photo_base_url = get_root_url().'admin.php?page=photo-'.$_GET['image_id'];
$self_url = LAC_ADMIN.'-photo&amp;image_id='.$_GET['image_id'];


/* Tabs */
// when adding a tab to an existing tabsheet you MUST reproduce the core tabsheet code
// this way it will not break compatibility with other plugins and with core functions
include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
$tabsheet = new tabsheet();
$tabsheet->set_id('photo'); // <= don't forget tabsheet id
$tabsheet->select('lac');
$tabsheet->assign();


/* Initialisation */
$image_id = (int)$_GET['image_id']; // Cast to int for safety after check_input_parameter validation
$query = '
SELECT *
  FROM '.IMAGES_TABLE.'
  WHERE id = ?
';
$stmt = $pwg_db_link->prepare($query);
if ($stmt) {
  $stmt->bind_param('i', $image_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $picture = $result ? $result->fetch_assoc() : false;
  $stmt->close();
} else {
  $picture = false;
}


# DO SOME STUFF HERE... or not !


/* Template */
$template->assign(array(
  'F_ACTION' => $self_url,
  'lac' => $conf['lac'],
  'TITLE' => render_element_name($picture),
  'TN_SRC' => DerivativeImage::thumb_url($picture),
));

$template->set_filename('lac_content', realpath(LAC_PATH . 'admin/template/photo.tpl'));
