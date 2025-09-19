<?php
// Minimal bootstrap to exercise the LAC plugin age gate logic in isolation.

// Simulate required Piwigo constants/functions so plugin code including main.inc.php doesn't fatal.
if (!defined('PHPWG_ROOT_PATH')) {
  define('PHPWG_ROOT_PATH', __DIR__ . '/../');
}
if (!defined('PHPWG_PLUGINS_PATH')) {
  define('PHPWG_PLUGINS_PATH', PHPWG_ROOT_PATH . 'albums/plugins/');
}
if (!defined('PWG_LOCAL_DIR')) {
  define('PWG_LOCAL_DIR', 'local/');
}

// Enable plugin test mode to bypass CSRF token enforcement & side effects inside admin/config.php
if (!defined('LAC_TEST_MODE')) {
  define('LAC_TEST_MODE', true);
}

// Stubs for functions used in main.inc.php
// Define core priority/constants normally provided by Piwigo
if (!defined('EVENT_HANDLER_PRIORITY_NEUTRAL')) define('EVENT_HANDLER_PRIORITY_NEUTRAL', 0);
if (!defined('BUTTONS_RANK_NEUTRAL')) define('BUTTONS_RANK_NEUTRAL', 0);

if (!function_exists('add_event_handler')) {
  function add_event_handler($event, $callable, $priority = 0, $file = null) {
    // record registration for test assertions
    $GLOBALS['__LAC_EVENTS'][] = [$event, $callable, $file];
  }
}
if (!function_exists('l10n')) { function l10n($k){ return $k; } }
if (!function_exists('script_basename')) { function script_basename(){ return 'index'; } }
if (!function_exists('query2array')) { function query2array($q,$a=null,$b=null){ return []; } }

// Minimal template stub to satisfy button additions if ever executed
if (!class_exists('TemplateStub')) {
  class TemplateStub {
    public array $assigned = [];
    public array $parsed = [];
    public function assign($k,$v=null){
      if (is_array($k)) { $this->assigned = array_merge($this->assigned,$k); }
      else { $this->assigned[$k] = $v; }
    }
    public function set_filename($a,$b){}
    public function parse($a,$b){ return ''; }
    public function add_index_button($b,$r){}
    public function add_picture_button($b,$r){}
    public function set_prefilter($a,$b){}
    public function append($a,$b){}
  }
}
if (!isset($GLOBALS['template'])) { $GLOBALS['template'] = new TemplateStub(); }

if (!function_exists('get_root_url')) { function get_root_url() { return '/'; } }
if (!function_exists('get_absolute_root_url')) { function get_absolute_root_url() { return '/'; } }
if (!function_exists('make_index_url')) { function make_index_url($args=array()) { return '/'; } }
if (!function_exists('safe_unserialize')) { function safe_unserialize($v) { return $v; } }
if (!function_exists('load_language')) { function load_language($a,$b){ /* noop */ } }
if (!function_exists('pwg_get_conf')) { function pwg_get_conf($k,$d=null){ global $conf; return $conf[$k] ?? $d; } }
if (!function_exists('pwg_set_conf')) { function pwg_set_conf($k,$v){ global $conf; $conf[$k]=$v; return true; } }
if (!function_exists('check_pwg_token')) { function check_pwg_token(){ $GLOBALS['__TOKEN_CHECKED']=true; } }
if (!function_exists('get_pwg_token')) { function get_pwg_token(){ return 'dummy'; } }

// Provide minimal globals
$GLOBALS['conf'] = ['lac' => serialize([]), 'level_separator' => ' / '];
$GLOBALS['user'] = ['is_guest' => true];
$GLOBALS['page'] = [];

// Include plugin main to register handlers.
require_once PHPWG_PLUGINS_PATH . 'lac/main.inc.php';

// Ensure functions helpers + age gate file loaded for direct calling in tests
require_once PHPWG_PLUGINS_PATH . 'lac/include/functions.inc.php';
require_once PHPWG_PLUGINS_PATH . 'lac/include/age_gate.inc.php';

?>
