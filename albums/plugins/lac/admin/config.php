<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized bootstrap for all dependencies
include_once LAC_PATH . 'include/bootstrap.inc.php';

// Ensure required globals are accessible when this file is included from different scopes (e.g., tests)
global $conf, $page, $template;
if (!isset($page) || !is_array($page)) { $page = []; }

// Initialize error handler and database helper using singleton pattern
$errorHandler = LacErrorHandler::getInstance();
$dbHelper = LacDatabaseHelper::getInstance();

// Ensure shared helper functions (including sanitizer) are available in admin context
// In normal Piwigo runtime functions.inc.php may not yet be loaded here.
if (!function_exists('lac_sanitize_fallback_url') && file_exists(LAC_PATH.'include/functions.inc.php')) {
  include_once LAC_PATH.'include/functions.inc.php';
}

// +-----------------------------------------------------------------------+
// | Configuration tab with standardized error handling                   |
// +-----------------------------------------------------------------------+

/**
 * Validate configuration form inputs with standardized error handling
 * @param array $postData POST data to validate
 * @return array Result with success/error information
 */
function lac_validate_config_form(array $postData): array {
  $errorHandler = LacErrorHandler::getInstance();
  
  $errors = [];
  $validated = [];
  
  try {
    // Validate enabled checkbox
    $validated['enabled'] = isset($postData['lac_enabled']);
    
    // Validate fallback URL
    $rawUrl = $postData['lac_fallback_url'] ?? '';
    $urlValidation = $errorHandler->validateInput($rawUrl, 'string', ['max_length' => LAC_MAX_FALLBACK_URL_LEN]);
    
    if (!$urlValidation['success']) {
      $errors[] = 'Fallback URL: ' . $urlValidation['error'];
    } else {
      if ($rawUrl !== '') {
        $sanitized = function_exists('lac_sanitize_fallback_url') ? lac_sanitize_fallback_url($rawUrl, true) : '';
        if ($sanitized === '') {
          // Determine if reason is internal host vs generic invalid
          $host = $_SERVER['HTTP_HOST'] ?? '';
          $p = parse_url($rawUrl);
          if (!empty($host) && isset($p['host']) && strcasecmp($p['host'], $host) === 0) {
            $errors[] = l10n('Internal URLs are not allowed as fallback');
          } else {
            $errors[] = l10n('Invalid fallback URL (must start with http:// or https://)');
          }
        } else {
          $validated['fallback_url'] = $sanitized;
        }
      } else {
        $validated['fallback_url'] = '';
      }
    }
    
    // Validate consent duration
    $rawDuration = trim($postData['lac_consent_duration'] ?? '');
    if ($rawDuration === '') {
      $validated['consent_duration'] = 0;
    } else {
      $durationValidation = $errorHandler->validateInput($rawDuration, 'integer', [
        'min' => 0,
        'max' => LAC_MAX_CONSENT_DURATION
      ]);
      
      if (!$durationValidation['success']) {
        $errors[] = 'Consent duration: ' . $durationValidation['error'];
      } else {
        $validated['consent_duration'] = (int)$rawDuration;
      }
    }
    
    if (!empty($errors)) {
      return LacErrorHandler::error('Form validation failed', 'VALIDATION_ERROR', ['errors' => $errors]);
    }
    
    return LacErrorHandler::success($validated);
    
  } catch (Exception $e) {
    return LacErrorHandler::error('Form validation failed: ' . $e->getMessage(), 'VALIDATION_ERROR');
  }
}

/**
 * Save configuration settings with standardized error handling
 * @param array $settings Validated settings to save
 * @return array Result with success/error information
 */
function lac_save_config_settings(array $settings): array {
  global $conf, $dbHelper;
  
  try {
    // Update global configuration
    $conf[LAC_CONFIG_ENABLED] = $settings['enabled'];
    $conf[LAC_CONFIG_FALLBACK_URL] = $settings['fallback_url'];
    $conf[LAC_CONFIG_CONSENT_DURATION] = $settings['consent_duration'];
    
    // Persist to database
    if (function_exists('conf_update_param')) {
      conf_update_param(LAC_CONFIG_ENABLED, $conf[LAC_CONFIG_ENABLED]);
      conf_update_param(LAC_CONFIG_FALLBACK_URL, $conf[LAC_CONFIG_FALLBACK_URL]);
      conf_update_param(LAC_CONFIG_CONSENT_DURATION, $conf[LAC_CONFIG_CONSENT_DURATION]);
      
      return LacErrorHandler::success(null, ['settings_saved' => 3]);
    } else {
      return LacErrorHandler::error('Configuration update function not available', 'CONFIG_ERROR');
    }
    
  } catch (Exception $e) {
    return LacErrorHandler::error('Failed to save configuration: ' . $e->getMessage(), 'CONFIG_ERROR');
  }
}

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
      
      // Validate form data using standardized error handling
      $validationResult = lac_validate_config_form($_POST);
      
      if (!$validationResult['success']) {
        if (isset($validationResult['data']['errors'])) {
          $page['errors'] = array_merge($page['errors'], $validationResult['data']['errors']);
        } else {
          $page['errors'][] = $validationResult['message'];
        }
      } else {
        // Save settings using standardized error handling
        $saveResult = lac_save_config_settings($validationResult['data']);
        
        if (!$saveResult['success']) {
          $page['errors'][] = $saveResult['message'];
        } else {
          // Update local variables for display
          $enabled = $validationResult['data']['enabled'];
          $fallback = $validationResult['data']['fallback_url'];
          $duration = $validationResult['data']['consent_duration'];
          
          $page['infos'][] = l10n('Settings saved');
        }
      }
      
    } catch (Exception $e) {
      $page['errors'][] = l10n('Invalid security token - form may have expired');
    }
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
