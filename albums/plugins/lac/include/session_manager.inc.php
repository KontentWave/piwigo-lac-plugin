<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized constants and error handler
include_once LAC_PATH . 'include/constants.inc.php';
include_once LAC_PATH . 'include/error_handler.inc.php';

/**
 * Optimized LAC Session Manager with standardized error handling
 * Provides efficient session handling with caching and minimal writes
 */
class LacSessionManager {
    
    private static $instance = null;
    private static $sessionCache = [];
    private static $sessionModified = false;
    private $debug = false;
    private $errorHandler;
    
    private function __construct() {
        $this->debug = lac_is_debug_mode();
        $this->errorHandler = LacErrorHandler::getInstance();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get session value with caching to avoid repeated $_SESSION reads (standardized error handling)
     * @param string $key Session key
     * @param mixed $default Default value if key not found
     * @return array Result with success/error information
     */
    public function get(string $key, $default = null): array {
        $validation = $this->errorHandler->validateInput($key, 'string', ['required' => true]);
        if (!$validation['success']) {
            return $this->errorHandler->error('VALIDATION_ERROR', 'Session key cannot be empty');
        }
        
        try {
            // Check cache first
            if (array_key_exists($key, self::$sessionCache)) {
                if ($this->debug && lac_is_verbose_debug_mode()) {
                    error_log('[LAC DEBUG] Session cache hit: ' . $key);
                }
                return LacErrorHandler::success(self::$sessionCache[$key], ['cached' => true]);
            }
            
            // Get from session and cache
            $value = $_SESSION[$key] ?? $default;
            self::$sessionCache[$key] = $value;
            
            if ($this->debug && lac_is_verbose_debug_mode()) {
                error_log('[LAC DEBUG] Session read: ' . $key . ' = ' . (is_scalar($value) ? $value : gettype($value)));
            }
            
            return LacErrorHandler::success($value, ['cached' => false]);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'SESSION_ACCESS_ERROR',
                'Failed to access session data: ' . $e->getMessage(),
                500,
                ['key' => $key]
            );
        }
    }
    
    /**
     * Backward compatibility wrapper for get method (legacy return format)
     * @deprecated Use get() with standardized error handling
     * @param string $key Session key
     * @param mixed $default Default value if key not found
     * @return mixed Session value or default
     */
    public function getLegacy(string $key, $default = null) {
        $result = $this->get($key, $default);
        return $result['success'] ? $result['data'] : $default;
    }
    
    /**
     * Set session value with deferred write optimization (standardized error handling)
     * @param string $key Session key
     * @param mixed $value Value to set
     * @return array Result with success/error information
     */
    public function set(string $key, $value): array {
        $validation = $this->errorHandler->validateInput($key, 'string', ['required' => true]);
        if (!$validation['success']) {
            return $this->errorHandler->error('VALIDATION_ERROR', 'Session key cannot be empty');
        }
        
        try {
            // Check if value actually changed
            $currentResult = $this->get($key);
            $currentValue = $currentResult['success'] ? $currentResult['data'] : null;
            
            if ($currentValue === $value) {
                return LacErrorHandler::success(null, ['changed' => false, 'reason' => 'no_change']);
            }
            
            $_SESSION[$key] = $value;
            self::$sessionCache[$key] = $value;
            self::$sessionModified = true;
            
            if ($this->debug && lac_is_verbose_debug_mode()) {
                error_log('[LAC DEBUG] Session write: ' . $key . ' = ' . (is_scalar($value) ? $value : gettype($value)));
            }
            
            return LacErrorHandler::success(null, ['changed' => true]);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'SESSION_WRITE_ERROR',
                'Failed to write session data: ' . $e->getMessage(),
                500,
                ['key' => $key]
            );
        }
    }
    
    /**
     * Backward compatibility wrapper for set method (legacy return format)
     * @deprecated Use set() with standardized error handling
     * @param string $key Session key
     * @param mixed $value Value to set
     * @return void
     */
    public function setLegacy(string $key, $value): void {
        $this->set($key, $value);
    }
    
    /**
     * Remove session value (standardized error handling)
     * @param string $key Session key
     * @return array Result with success/error information
     */
    public function unset(string $key): array {
        $validation = $this->errorHandler->validateInput($key, 'string', ['required' => true]);
        if (!$validation['success']) {
            return $this->errorHandler->error('VALIDATION_ERROR', 'Session key cannot be empty');
        }
        
        try {
            $existed = isset($_SESSION[$key]);
            if ($existed) {
                unset($_SESSION[$key]);
                self::$sessionModified = true;
            }
            unset(self::$sessionCache[$key]);
            
            if ($this->debug && lac_is_verbose_debug_mode()) {
                error_log('[LAC DEBUG] Session unset: ' . $key);
            }
            
            return LacErrorHandler::success(null, ['existed' => $existed]);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'SESSION_UNSET_ERROR',
                'Failed to unset session data: ' . $e->getMessage(),
                500,
                ['key' => $key]
            );
        }
    }
    
    /**
     * Check if session key exists (standardized error handling)
     * @param string $key Session key
     * @return array Result with success/error information
     */
    public function has(string $key): array {
        $result = $this->get($key);
        if (!$result['success']) {
            return $result;
        }
        
        $exists = $result['data'] !== null;
        return LacErrorHandler::success($exists, ['key' => $key]);
    }
    
    /**
     * Backward compatibility wrapper for has method (legacy return format)
     * @deprecated Use has() with standardized error handling
     * @param string $key Session key
     * @return bool Whether key exists
     */
    public function hasLegacy(string $key): bool {
        $result = $this->has($key);
        return $result['success'] ? $result['data'] : false;
    }
    
    /**
     * Optimized consent checking with intelligent caching (standardized error handling)
     * @return array Result with success/error information
     */
    public function hasConsent(): array {
        try {
            // Check modern structured consent first
            $consentResult = $this->get(LAC_SESSION_CONSENT_KEY);
            if (!$consentResult['success']) {
                return $consentResult;
            }
            
            $consent = $consentResult['data'];
            if (is_array($consent) && !empty($consent['granted'])) {
                return LacErrorHandler::success(true, ['source' => 'structured_consent']);
            }
            
            // Hybrid legacy consent logic: only valid when duration == 0 (session-only mode)
            $legacyResult = $this->get(LAC_SESSION_CONSENT_LEGACY_KEY, false);
            if ($legacyResult['success'] && $legacyResult['data']) {
                $duration = function_exists('lac_get_consent_duration') ? lac_get_consent_duration() : 0;
                if ($duration === 0) {
                    return LacErrorHandler::success(true, ['source' => 'legacy_session_only']);
                }
                // duration > 0 -> ignore legacy flag
            }
            return LacErrorHandler::success(false, ['source' => 'no_consent']);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'CONSENT_CHECK_ERROR',
                'Failed to check consent status: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Backward compatibility wrapper for hasConsent (legacy return format)
     * @deprecated Use hasConsent() with standardized error handling
     * @return bool Whether consent is granted
     */
    public function hasConsentLegacy(): bool {
        $result = $this->hasConsent();
        return $result['success'] ? $result['data'] : false;
    }
    
    /**
     * Set consent with efficient session management (standardized error handling)
     * @param int|null $timestamp Consent timestamp (null for current time)
     * @return array Result with success/error information
     */
    public function setConsent(int $timestamp = null): array {
        try {
            if ($timestamp === null) {
                $timestamp = time();
            }
            
            $structuredResult = $this->set(LAC_SESSION_CONSENT_KEY, ['granted' => true, 'timestamp' => $timestamp]);
            if (!$structuredResult['success']) {
                return $structuredResult;
            }
            
            $legacyResult = $this->set(LAC_SESSION_CONSENT_LEGACY_KEY, true);
            if (!$legacyResult['success']) {
                return $legacyResult;
            }
            
            if ($this->debug) {
                error_log('[LAC DEBUG] Consent set in session with timestamp: ' . $timestamp);
            }
            
            return LacErrorHandler::success(null, ['timestamp' => $timestamp]);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'CONSENT_SET_ERROR',
                'Failed to set consent: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Clear consent from session (standardized error handling)
     * @return array Result with success/error information
     */
    public function clearConsent(): array {
        try {
            $structuredResult = $this->unset(LAC_SESSION_CONSENT_KEY);
            $legacyResult = $this->unset(LAC_SESSION_CONSENT_LEGACY_KEY);
            
            if ($this->debug) {
                error_log('[LAC DEBUG] Consent cleared from session');
            }
            
            return LacErrorHandler::success(null, [
                'structured_cleared' => $structuredResult['success'],
                'legacy_cleared' => $legacyResult['success']
            ]);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'CONSENT_CLEAR_ERROR',
                'Failed to clear consent: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Check if consent has expired based on duration (standardized error handling)
     * @return array Result with success/error information
     */
    public function isConsentExpired(): array {
        try {
            $consentResult = $this->get(LAC_SESSION_CONSENT_KEY);
            if (!$consentResult['success']) {
                return LacErrorHandler::success(true, ['reason' => 'consent_check_failed']);
            }
            
            $consent = $consentResult['data'];
            if (!is_array($consent) || empty($consent['granted'])) {
                // No structured consent: legacy flag handled by hasConsent(), treat as not expired so decision layer can redirect if absent
                $legacyResult = $this->get(LAC_SESSION_CONSENT_LEGACY_KEY, false);
                if ($legacyResult['success'] && $legacyResult['data']) {
                    return LacErrorHandler::success(false, ['reason' => 'legacy_flag']);
                }
                return LacErrorHandler::success(true, ['reason' => 'no_consent']);
            }
            
            $timestamp = $consent['timestamp'] ?? 0;
            if ($timestamp === 0) {
                return LacErrorHandler::success(false, ['reason' => 'session_only']);
            }
            
            // Get duration efficiently (uses database helper caching)
            $duration = function_exists('lac_get_consent_duration') ? lac_get_consent_duration() : 0;
            if ($duration === 0) {
                return LacErrorHandler::success(false, ['reason' => 'no_duration_limit']);
            }
            
            $age = time() - $timestamp;
            $expired = $age >= ($duration * 60);
            
            if ($expired && $this->debug) {
                error_log('[LAC DEBUG] Consent expired: age=' . $age . 's, limit=' . ($duration * 60) . 's');
            }
            
            return LacErrorHandler::success($expired, [
                'age_seconds' => $age,
                'limit_seconds' => $duration * 60,
                'duration_minutes' => $duration
            ]);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'CONSENT_EXPIRY_CHECK_ERROR',
                'Failed to check consent expiry: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Backward compatibility wrapper for isConsentExpired (legacy return format)
     * @deprecated Use isConsentExpired() with standardized error handling
     * @return bool Whether consent is expired
     */
    public function isConsentExpiredLegacy(): bool {
        $result = $this->isConsentExpired();
        return $result['success'] ? $result['data'] : true; // Default to expired on error
    }
    
    /**
     * Optimized session regeneration with rate limiting (standardized error handling)
     * @return array Result with success/error information
     */
    public function regenerateIfNeeded(): array {
        try {
            $lastRegeneratedResult = $this->get(LAC_SESSION_REGENERATED_KEY, 0);
            $lastRegenerated = $lastRegeneratedResult['success'] ? $lastRegeneratedResult['data'] : 0;
            $interval = LAC_SESSION_REGENERATION_INTERVAL;
            
            if ((time() - $lastRegenerated) > $interval) {
                if (function_exists('session_regenerate_id')) {
                    session_regenerate_id(true);
                    $setResult = $this->set(LAC_SESSION_REGENERATED_KEY, time());
                    
                    if (!$setResult['success']) {
                        return $this->errorHandler->error('SESSION_ERROR', 'Failed to update regeneration timestamp');
                    }
                    
                    if ($this->debug) {
                        error_log('[LAC DEBUG] Session ID regenerated');
                    }
                    
                    return LacErrorHandler::success(null, ['regenerated' => true]);
                } else {
                    return $this->errorHandler->error('SESSION_ERROR', 'session_regenerate_id function not available');
                }
            }
            
            return LacErrorHandler::success(null, ['regenerated' => false, 'reason' => 'too_recent']);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'SESSION_REGENERATION_ERROR',
                'Failed to regenerate session: ' . $e->getMessage(),
                500
            );
        }
    }
    
    /**
     * Get redirect target with efficient handling (standardized error handling)
     * @param string|null $default Default value if target not found
     * @return array Result with success/error information
     */
    public function getRedirectTarget(string $default = null): array {
        return $this->get(LAC_SESSION_REDIRECT_KEY, $default);
    }
    
    /**
     * Backward compatibility wrapper for getRedirectTarget (legacy return format)
     * @deprecated Use getRedirectTarget() with standardized error handling
     * @param string|null $default Default value if target not found
     * @return string|null Redirect target or default
     */
    public function getRedirectTargetLegacy(string $default = null): ?string {
        $result = $this->getRedirectTarget($default);
        return $result['success'] ? $result['data'] : $default;
    }
    
    /**
     * Set redirect target (standardized error handling)
     * @param string $url Target URL
     * @return array Result with success/error information
     */
    public function setRedirectTarget(string $url): array {
        return $this->set(LAC_SESSION_REDIRECT_KEY, $url);
    }
    
    /**
     * Clear redirect target and return it (standardized error handling)
     * @param string|null $default Default value if target not found
     * @return array Result with success/error information
     */
    public function consumeRedirectTarget(string $default = null): array {
        $targetResult = $this->get(LAC_SESSION_REDIRECT_KEY, $default);
        if (!$targetResult['success']) {
            return $targetResult;
        }
        
        $target = $targetResult['data'];
        $this->unset(LAC_SESSION_REDIRECT_KEY);
        return LacErrorHandler::success($target, ['consumed' => true]);
    }
    
    /**
     * Backward compatibility wrapper for consumeRedirectTarget (legacy return format)
     * @deprecated Use consumeRedirectTarget() with standardized error handling
     * @param string|null $default Default value if target not found
     * @return string|null Redirect target or default
     */
    public function consumeRedirectTargetLegacy(string $default = null): ?string {
        $result = $this->consumeRedirectTarget($default);
        return $result['success'] ? $result['data'] : $default;
    }
    
    /**
     * Clear session cache (useful for testing)
     */
    public static function clearCache(): void {
        self::$sessionCache = [];
        self::$sessionModified = false;
    }
    
    /**
     * Get session statistics for performance monitoring
     */
    public static function getStats(): array {
        return [
            'cached_keys' => array_keys(self::$sessionCache),
            'cache_size' => count(self::$sessionCache),
            'session_modified' => self::$sessionModified
        ];
    }
    
    /**
     * Force session write (useful before redirects) (standardized error handling)
     * @return array Result with success/error information
     */
    public function flush(): array {
        try {
            if (self::$sessionModified && function_exists('session_write_close')) {
                session_write_close();
                self::$sessionModified = false;
                
                if ($this->debug) {
                    error_log('[LAC DEBUG] Session flushed to storage');
                }
                
                return LacErrorHandler::success(null, ['flushed' => true]);
            }
            
            return LacErrorHandler::success(null, ['flushed' => false, 'reason' => 'no_changes_or_function_unavailable']);
            
        } catch (Exception $e) {
            return $this->errorHandler->handleError(
                'SESSION_FLUSH_ERROR',
                'Failed to flush session: ' . $e->getMessage(),
                500
            );
        }
    }
}