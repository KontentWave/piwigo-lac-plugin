<?php
defined('LAC_PATH') or die('Hacking attempt!');

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
if (!isset($conf['lac_enabled'])) { $conf['lac_enabled'] = true; }
if (!isset($conf['lac_fallback_url'])) { $conf['lac_fallback_url'] = ''; }
if (!isset($conf['lac_consent_duration'])) { $conf['lac_consent_duration'] = 0; }
$enabled  = (bool)$conf['lac_enabled'];
$fallback = (string)$conf['lac_fallback_url'];
$duration = (int)$conf['lac_consent_duration'];

if (isset($_POST['lac_settings_submit'])) {
  check_pwg_token();
  $enabled = isset($_POST['lac_enabled']);
  $raw = $_POST['lac_fallback_url'] ?? '';
  // Consent duration field processing
  if (isset($_POST['lac_consent_duration'])) {
    $rawDur = trim($_POST['lac_consent_duration']);
    if ($rawDur === '') {
      $duration = 0;
    } elseif (ctype_digit($rawDur)) {
      // optional upper bound to prevent absurd values (e.g. > 43200 minutes ~ 30 days) - choose generous cap
      $val = (int)$rawDur;
      if ($val > 43200) { // 30 days
        $page['errors'][] = l10n('Consent duration too large');
      } else {
        $duration = $val;
      }
    } else {
      $page['errors'][] = l10n('Invalid consent duration');
    }
  }
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
  if (empty($page['errors'])) {
    $conf['lac_enabled'] = $enabled;
    $conf['lac_fallback_url'] = $fallback;
    $conf['lac_consent_duration'] = $duration;
    // Persist to database
    if (function_exists('conf_update_param')) {
      conf_update_param('lac_enabled', $conf['lac_enabled']);
      conf_update_param('lac_fallback_url', $conf['lac_fallback_url']);
      conf_update_param('lac_consent_duration', $conf['lac_consent_duration']);
    }
    // DB-only storage succeeded; inform user (file persistence deprecated)
    $page['infos'][] = l10n('Settings saved');
  }
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
