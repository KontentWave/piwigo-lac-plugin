<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized constants and database helper
include_once LAC_PATH . 'include/constants.inc.php';
include_once LAC_PATH . 'include/database_helper.inc.php';

// Ensure required globals are accessible when this file is included from different scopes (e.g., tests)
global $conf, $page, $template;
if (!isset($page) || !is_array($page)) { $page = []; }

// Ensure shared helper functions (including sanitizer) are available in admin context
// In normal Piwigo runtime functions.inc.php may not yet be loaded here.
if (!function_exists('lac_sanitize_fallback_url') && file_exists(LAC_PATH.'include/functions.inc.php')) {
  include_once LAC_PATH.'include/functions.inc.php';
}

// +-----------------------------------------------------------------------+
// | Configuration tab                                                     |
// +-----------------------------------------------------------------------+

// Age Gate settings (Phase 2)
// Retrieve current settings from $conf with sane defaults
if (!isset($conf[LAC_CONFIG_ENABLED])) { $conf[LAC_CONFIG_ENABLED] = true; }
if (!isset($conf[LAC_CONFIG_FALLBACK_URL])) { $conf[LAC_CONFIG_FALLBACK_URL] = ''; }
if (!isset($conf[LAC_CONFIG_CONSENT_DURATION])) { $conf[LAC_CONFIG_CONSENT_DURATION] = 0; }
$enabled  = (bool)$conf[LAC_CONFIG_ENABLED];
$fallback = (string)$conf[LAC_CONFIG_FALLBACK_URL];
$duration = (int)$conf[LAC_CONFIG_CONSENT_DURATION];

if (isset($_POST['lac_settings_submit'])) {
  // Enhanced CSRF protection validation
  if (!function_exists('check_pwg_token') || !function_exists('get_pwg_token')) {
    $page['errors'][] = l10n('Security token functions not available');
  } elseif (empty($_POST['pwg_token'])) {
    $page['errors'][] = l10n('Missing security token - form submission rejected');
  } else {
    try {
      check_pwg_token();
    } catch (Exception $e) {
      $page['errors'][] = l10n('Invalid security token - form may have expired');
    }
  }
  
  // Only proceed if CSRF validation passed
  if (empty($page['errors'])) {
  
  // Validate input sizes to prevent DoS via large inputs
  foreach (['lac_fallback_url', 'lac_consent_duration'] as $field) {
    if (isset($_POST[$field]) && strlen($_POST[$field]) > LAC_MAX_POST_INPUT_SIZE) {
      $page['errors'][] = sprintf(l10n('Input field %s too large (max %d bytes)'), $field, LAC_MAX_POST_INPUT_SIZE);
      break; // Exit early on size violation
    }
  }
  
  if (empty($page['errors'])) { // Only process if no size violations
    $enabled = isset($_POST['lac_enabled']);
    $raw = $_POST['lac_fallback_url'] ?? '';
    
    // Consent duration field processing with enhanced validation
    if (isset($_POST['lac_consent_duration'])) {
      $rawDur = trim($_POST['lac_consent_duration']);
      if ($rawDur === '') {
        $duration = 0;
      } elseif (ctype_digit($rawDur)) {
        $val = (int)$rawDur;
        if ($val > LAC_MAX_CONSENT_DURATION) {
          $page['errors'][] = sprintf(l10n('Consent duration too large (max %d minutes)'), LAC_MAX_CONSENT_DURATION);
        } elseif ($val < 0) {
          $page['errors'][] = l10n('Consent duration cannot be negative');
        } else {
          $duration = $val;
        }
      } else {
        $page['errors'][] = l10n('Invalid consent duration (must be a positive number)');
      }
    }
    
    // URL validation (only if no input size violations)
    if ($raw !== '') {
    $tooLong = (strlen($raw) > (defined('LAC_MAX_FALLBACK_URL_LEN') ? LAC_MAX_FALLBACK_URL_LEN : 2048));
    $san = function_exists('lac_sanitize_fallback_url') ? lac_sanitize_fallback_url($raw, true) : '';
    if ($tooLong) {
      $page['errors'][] = sprintf(l10n('Fallback URL too long (max %d characters)'), (defined('LAC_MAX_FALLBACK_URL_LEN') ? LAC_MAX_FALLBACK_URL_LEN : 2048));
    } elseif ($san === '') {
      // Determine if reason is internal host vs generic invalid
      $host = $_SERVER['HTTP_HOST'] ?? '';
      $p = parse_url($raw);
      if (!empty($host) && isset($p['host']) && strcasecmp($p['host'], $host) === 0) {
        $page['errors'][] = l10n('Internal URLs are not allowed as fallback');
      } else {
        $page['errors'][] = l10n('Invalid fallback URL (must start with http:// or https://)');
      }
    } else {
      $fallback = $san;
    }
  } else {
    $fallback = '';
  }
  } // End of "if no size violations" block
  
  if (empty($page['errors'])) {
    $conf[LAC_CONFIG_ENABLED] = $enabled;
    $conf[LAC_CONFIG_FALLBACK_URL] = $fallback;
    $conf[LAC_CONFIG_CONSENT_DURATION] = $duration;
    // Persist to database
    if (function_exists('conf_update_param')) {
      conf_update_param(LAC_CONFIG_ENABLED, $conf[LAC_CONFIG_ENABLED]);
      conf_update_param(LAC_CONFIG_FALLBACK_URL, $conf[LAC_CONFIG_FALLBACK_URL]);
      conf_update_param(LAC_CONFIG_CONSENT_DURATION, $conf[LAC_CONFIG_CONSENT_DURATION]);
    }
    // DB-only storage succeeded; inform user (file persistence deprecated)
    $page['infos'][] = l10n('Settings saved');
  }
  } // End CSRF validation block
}

$template->assign(array(
  'LAC_ENABLED' => $enabled ? 'checked' : '',
  'LAC_FALLBACK_URL' => htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8'),
  'LAC_CONSENT_DURATION' => (int)$duration,
  'LAC_TOKEN' => function_exists('get_pwg_token') ? get_pwg_token() : '' ,
  // Map core $page messages to template variables expected by admin.tpl (legacy placeholders)
  'LAC_MESSAGE' => !empty($page['infos']) ? implode('\n', $page['infos']) : '',
  'LAC_ERRORS'  => !empty($page['errors']) ? $page['errors'] : array(),
));

// Reuse admin.tpl
$template->set_filename('lac_content', realpath(LAC_PATH . 'admin/template/admin.tpl'));
