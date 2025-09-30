<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class lac_maintain extends PluginMaintain
{
  public function __construct($plugin_id)
  {
    parent::__construct($plugin_id);
  }

  public function install($plugin_version, &$errors = array())
  {
    global $conf;

    $defaults = array(
      'lac_enabled' => true,
      'lac_fallback_url' => '',
      'lac_consent_duration' => 0,
      'lac_apply_to_logged_in' => false,
    );

    foreach ($defaults as $param => $value) {
      if (!isset($conf[$param])) {
        $conf[$param] = $value;
      }

      if (function_exists('conf_update_param')) {
        conf_update_param($param, $conf[$param]);
      }
    }
  }

  public function activate($plugin_version, &$errors = array())
  {
    // Nothing additional to do; installation ensures defaults.
  }

  public function deactivate()
  {
    // No filesystem or database artifacts to revert.
  }

  public function update($old_version, $new_version, &$errors = array())
  {
    $this->install($new_version, $errors);
  }

  public function uninstall()
  {
    global $conf;

    $params = array(
      'lac_enabled',
      'lac_fallback_url',
      'lac_consent_duration',
      'lac_apply_to_logged_in',
    );

    foreach ($params as $param) {
      unset($conf[$param]);

      if (function_exists('conf_delete_param')) {
        call_user_func('conf_delete_param', $param);
      }
    }
  }
}