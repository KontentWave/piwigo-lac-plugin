<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized constants
include_once LAC_PATH . 'include/constants.inc.php';

/**
 * Standardized LAC Error Handling System
 * Provides consistent error handling, logging, and response formatting
 */
class LacErrorHandler {
    
    // Error severity levels
    const SEVERITY_LOW = 1;
    const SEVERITY_MEDIUM = 2;
    const SEVERITY_HIGH = 3;
    const SEVERITY_CRITICAL = 4;
    
    // Error categories
    const CATEGORY_VALIDATION = 'validation';
    const CATEGORY_DATABASE = 'database';
    const CATEGORY_SESSION = 'session';
    const CATEGORY_SECURITY = 'security';
    const CATEGORY_CONFIGURATION = 'configuration';
    const CATEGORY_NETWORK = 'network';
    
    private static $instance = null;
    private $debug = false;
    private static $errorLog = [];
    
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
     * Standardized error result structure
     */
    public static function createResult(bool $success, $data = null, string $error = '', string $errorCode = '', array $context = []): array {
        return [
            'success' => $success,
            'data' => $data,
            'error' => $error,
            'error_code' => $errorCode,
            'context' => $context,
            'timestamp' => time()
        ];
    }
    
    /**
     * Create successful result
     */
    public static function success($data = null, array $context = []): array {
        return self::createResult(true, $data, '', '', $context);
    }
    
    /**
     * Create error result
     */
    public static function error(string $message, string $errorCode = '', $data = null, array $context = []): array {
        return self::createResult(false, $data, $message, $errorCode, $context);
    }
    
    /**
     * Handle and log errors with standardized approach
     */
    public function handleError(string $message, string $category, int $severity = self::SEVERITY_MEDIUM, array $context = [], Exception $exception = null): array {
        $errorData = [
            'message' => $message,
            'category' => $category,
            'severity' => $severity,
            'context' => $context,
            'timestamp' => time(),
            'trace' => $this->debug ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5) : []
        ];
        
        if ($exception) {
            $errorData['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->debug ? $exception->getTraceAsString() : ''
            ];
        }
        
        // Log to internal error log
        self::$errorLog[] = $errorData;
        
        // Log to system error log based on severity
        if ($severity >= self::SEVERITY_HIGH || $this->debug) {
            $logMessage = sprintf(
                '[LAC ERROR] %s: %s (Category: %s, Severity: %d)',
                strtoupper($category),
                $message,
                $category,
                $severity
            );
            
            if (!empty($context)) {
                $logMessage .= ' Context: ' . json_encode($context);
            }
            
            if ($exception) {
                $logMessage .= ' Exception: ' . $exception->getMessage();
            }
            
            error_log($logMessage);
        }
        
        // Create standardized error result
        $errorCode = strtoupper($category) . '_ERROR';
        return self::error($message, $errorCode, null, $context);
    }
    
    /**
     * Validate input with standardized error handling
     */
    public function validateInput($value, string $type, array $options = []): array {
        try {
            switch ($type) {
                case 'url':
                    return $this->validateUrl($value, $options);
                case 'integer':
                    return $this->validateInteger($value, $options);
                case 'string':
                    return $this->validateString($value, $options);
                case 'array':
                    return $this->validateArray($value, $options);
                case 'session_key':
                    return $this->validateSessionKey($value, $options);
                default:
                    return $this->handleError(
                        'Unknown validation type: ' . $type,
                        self::CATEGORY_VALIDATION,
                        self::SEVERITY_HIGH,
                        ['type' => $type, 'value' => $value]
                    );
            }
        } catch (Exception $e) {
            return $this->handleError(
                'Validation failed: ' . $e->getMessage(),
                self::CATEGORY_VALIDATION,
                self::SEVERITY_HIGH,
                ['type' => $type, 'value' => $value],
                $e
            );
        }
    }
    
    /**
     * URL validation with comprehensive error handling
     */
    private function validateUrl($url, array $options = []): array {
        if (!is_string($url)) {
            return $this->handleError(
                'URL must be a string',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['provided_type' => gettype($url)]
            );
        }
        
        $url = trim($url);
        if ($url === '' && empty($options['required'])) {
            return self::success(''); // Empty URL is valid if not required
        }
        
        if ($url === '' && !empty($options['required'])) {
            return $this->handleError(
                'URL is required but empty',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM
            );
        }
        
        $maxLength = $options['max_length'] ?? LAC_MAX_FALLBACK_URL_LEN;
        if (strlen($url) > $maxLength) {
            return $this->handleError(
                'URL too long',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['length' => strlen($url), 'max_length' => $maxLength]
            );
        }
        
        // Use existing sanitization function
        if (function_exists('lac_sanitize_fallback_url')) {
            $sanitized = lac_sanitize_fallback_url($url, $options['disallow_internal'] ?? false);
            if ($sanitized === '') {
                return $this->handleError(
                    'URL failed security validation',
                    self::CATEGORY_SECURITY,
                    self::SEVERITY_HIGH,
                    ['original_url' => $url]
                );
            }
            return self::success($sanitized);
        }
        
        return $this->handleError(
            'URL sanitization function not available',
            self::CATEGORY_CONFIGURATION,
            self::SEVERITY_HIGH
        );
    }
    
    /**
     * Integer validation with range checking
     */
    private function validateInteger($value, array $options = []): array {
        if (!is_numeric($value)) {
            return $this->handleError(
                'Value must be numeric',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['provided_value' => $value, 'type' => gettype($value)]
            );
        }
        
        $intValue = (int)$value;
        
        if (isset($options['min']) && $intValue < $options['min']) {
            return $this->handleError(
                'Value below minimum',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['value' => $intValue, 'min' => $options['min']]
            );
        }
        
        if (isset($options['max']) && $intValue > $options['max']) {
            return $this->handleError(
                'Value above maximum',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['value' => $intValue, 'max' => $options['max']]
            );
        }
        
        return self::success($intValue);
    }
    
    /**
     * String validation with length and pattern checking
     */
    private function validateString($value, array $options = []): array {
        if (!is_string($value)) {
            return $this->handleError(
                'Value must be a string',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['provided_type' => gettype($value)]
            );
        }
        
        $maxLength = $options['max_length'] ?? LAC_MAX_POST_INPUT_SIZE;
        if (strlen($value) > $maxLength) {
            return $this->handleError(
                'String too long',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['length' => strlen($value), 'max_length' => $maxLength]
            );
        }
        
        if (isset($options['pattern']) && !preg_match($options['pattern'], $value)) {
            return $this->handleError(
                'String does not match required pattern',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['pattern' => $options['pattern']]
            );
        }
        
        return self::success($value);
    }
    
    /**
     * Array validation
     */
    private function validateArray($value, array $options = []): array {
        if (!is_array($value)) {
            return $this->handleError(
                'Value must be an array',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['provided_type' => gettype($value)]
            );
        }
        
        if (isset($options['required_keys'])) {
            foreach ($options['required_keys'] as $key) {
                if (!array_key_exists($key, $value)) {
                    return $this->handleError(
                        'Required array key missing',
                        self::CATEGORY_VALIDATION,
                        self::SEVERITY_MEDIUM,
                        ['missing_key' => $key, 'available_keys' => array_keys($value)]
                    );
                }
            }
        }
        
        return self::success($value);
    }
    
    /**
     * Session key validation
     */
    private function validateSessionKey($key, array $options = []): array {
        if (!is_string($key)) {
            return $this->handleError(
                'Session key must be a string',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_MEDIUM,
                ['provided_type' => gettype($key)]
            );
        }
        
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
            return $this->handleError(
                'Invalid session key format',
                self::CATEGORY_VALIDATION,
                self::SEVERITY_HIGH,
                ['key' => $key]
            );
        }
        
        return self::success($key);
    }
    
    /**
     * Database operation error handler
     */
    public function handleDatabaseError(string $operation, string $error, array $context = []): array {
        return $this->handleError(
            'Database operation failed: ' . $operation,
            self::CATEGORY_DATABASE,
            self::SEVERITY_HIGH,
            array_merge($context, ['db_error' => $error])
        );
    }
    
    /**
     * Session operation error handler
     */
    public function handleSessionError(string $operation, string $error, array $context = []): array {
        return $this->handleError(
            'Session operation failed: ' . $operation,
            self::CATEGORY_SESSION,
            self::SEVERITY_MEDIUM,
            array_merge($context, ['session_error' => $error])
        );
    }
    
    /**
     * Get error statistics for monitoring
     */
    public static function getErrorStats(): array {
        $stats = [
            'total_errors' => count(self::$errorLog),
            'by_category' => [],
            'by_severity' => [],
            'recent_errors' => array_slice(self::$errorLog, -10) // Last 10 errors
        ];
        
        foreach (self::$errorLog as $error) {
            $category = $error['category'];
            $severity = $error['severity'];
            
            $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;
            $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + 1;
        }
        
        return $stats;
    }
    
    /**
     * Clear error log (useful for testing)
     */
    public static function clearErrorLog(): void {
        self::$errorLog = [];
    }
}