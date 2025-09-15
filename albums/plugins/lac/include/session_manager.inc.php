<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized constants
include_once LAC_PATH . 'include/constants.inc.php';

/**
 * Optimized LAC Session Manager
 * Provides efficient session handling with caching and minimal writes
 */
class LacSessionManager {
    
    private static $instance = null;
    private static $sessionCache = [];
    private static $sessionModified = false;
    private $debug = false;
    
    private function __construct() {
        $this->debug = lac_is_debug_mode();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get session value with caching to avoid repeated $_SESSION reads
     */
    public function get(string $key, $default = null) {
        // Check cache first
        if (array_key_exists($key, self::$sessionCache)) {
            if ($this->debug && lac_is_verbose_debug_mode()) {
                error_log('[LAC DEBUG] Session cache hit: ' . $key);
            }
            return self::$sessionCache[$key];
        }
        
        // Get from session and cache
        $value = $_SESSION[$key] ?? $default;
        self::$sessionCache[$key] = $value;
        
        if ($this->debug && lac_is_verbose_debug_mode()) {
            error_log('[LAC DEBUG] Session read: ' . $key . ' = ' . (is_scalar($value) ? $value : gettype($value)));
        }
        
        return $value;
    }
    
    /**
     * Set session value with deferred write optimization
     */
    public function set(string $key, $value): void {
        // Check if value actually changed
        $currentValue = $this->get($key);
        if ($currentValue === $value) {
            return; // No change, skip write
        }
        
        $_SESSION[$key] = $value;
        self::$sessionCache[$key] = $value;
        self::$sessionModified = true;
        
        if ($this->debug && lac_is_verbose_debug_mode()) {
            error_log('[LAC DEBUG] Session write: ' . $key . ' = ' . (is_scalar($value) ? $value : gettype($value)));
        }
    }
    
    /**
     * Remove session value
     */
    public function unset(string $key): void {
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
            self::$sessionModified = true;
        }
        unset(self::$sessionCache[$key]);
        
        if ($this->debug && lac_is_verbose_debug_mode()) {
            error_log('[LAC DEBUG] Session unset: ' . $key);
        }
    }
    
    /**
     * Check if session key exists
     */
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }
    
    /**
     * Optimized consent checking with intelligent caching
     */
    public function hasConsent(): bool {
        // Check modern structured consent first
        $consent = $this->get(LAC_SESSION_CONSENT_KEY);
        if (is_array($consent) && !empty($consent['granted'])) {
            return true;
        }
        
        // Check legacy consent flag
        return (bool)$this->get(LAC_SESSION_CONSENT_LEGACY_KEY, false);
    }
    
    /**
     * Set consent with efficient session management
     */
    public function setConsent(int $timestamp = null): void {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        $this->set(LAC_SESSION_CONSENT_KEY, ['granted' => true, 'timestamp' => $timestamp]);
        $this->set(LAC_SESSION_CONSENT_LEGACY_KEY, true);
        
        if ($this->debug) {
            error_log('[LAC DEBUG] Consent set in session with timestamp: ' . $timestamp);
        }
    }
    
    /**
     * Clear consent from session
     */
    public function clearConsent(): void {
        $this->unset(LAC_SESSION_CONSENT_KEY);
        $this->unset(LAC_SESSION_CONSENT_LEGACY_KEY);
        
        if ($this->debug) {
            error_log('[LAC DEBUG] Consent cleared from session');
        }
    }
    
    /**
     * Check if consent has expired based on duration
     */
    public function isConsentExpired(): bool {
        $consent = $this->get(LAC_SESSION_CONSENT_KEY);
        if (!is_array($consent) || empty($consent['granted'])) {
            return true; // No consent means expired
        }
        
        $timestamp = $consent['timestamp'] ?? 0;
        if ($timestamp === 0) {
            return false; // Session-only consent doesn't expire
        }
        
        // Get duration efficiently (uses database helper caching)
        $duration = function_exists('lac_get_consent_duration') ? lac_get_consent_duration() : 0;
        if ($duration === 0) {
            return false; // Session-only consent
        }
        
        $age = time() - $timestamp;
        $expired = $age >= ($duration * 60);
        
        if ($expired && $this->debug) {
            error_log('[LAC DEBUG] Consent expired: age=' . $age . 's, limit=' . ($duration * 60) . 's');
        }
        
        return $expired;
    }
    
    /**
     * Optimized session regeneration with rate limiting
     */
    public function regenerateIfNeeded(): void {
        $lastRegenerated = $this->get(LAC_SESSION_REGENERATED_KEY, 0);
        $interval = LAC_SESSION_REGENERATION_INTERVAL;
        
        if ((time() - $lastRegenerated) > $interval) {
            if (function_exists('session_regenerate_id')) {
                session_regenerate_id(true);
                $this->set(LAC_SESSION_REGENERATED_KEY, time());
                
                if ($this->debug) {
                    error_log('[LAC DEBUG] Session ID regenerated');
                }
            }
        }
    }
    
    /**
     * Get redirect target with efficient handling
     */
    public function getRedirectTarget(string $default = null): ?string {
        return $this->get(LAC_SESSION_REDIRECT_KEY, $default);
    }
    
    /**
     * Set redirect target
     */
    public function setRedirectTarget(string $url): void {
        $this->set(LAC_SESSION_REDIRECT_KEY, $url);
    }
    
    /**
     * Clear redirect target and return it
     */
    public function consumeRedirectTarget(string $default = null): ?string {
        $target = $this->get(LAC_SESSION_REDIRECT_KEY, $default);
        $this->unset(LAC_SESSION_REDIRECT_KEY);
        return $target;
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
     * Force session write (useful before redirects)
     */
    public function flush(): void {
        if (self::$sessionModified && function_exists('session_write_close')) {
            session_write_close();
            self::$sessionModified = false;
            
            if ($this->debug) {
                error_log('[LAC DEBUG] Session flushed to storage');
            }
        }
    }
}