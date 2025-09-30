<?php
defined('LAC_PATH') or die('Hacking attempt!');

// Load centralized error handling
include_once LAC_PATH . 'include/error_handler.inc.php';

/**
 * Centralized LAC Database Helper Class
 * Consolidates database connection logic and common operations with connection pooling
 */
class LacDatabaseHelper {
    
    private static $instance = null;
    private $connection = null;
    private $debug = false;
    private static $queryCache = [];
    private static $connectionCount = 0;
    private $errorHandler = null;
    
    private function __construct() {
        $this->debug = (defined('LAC_DEBUG') && LAC_DEBUG) || isset($_GET['lac_debug']);
        $this->errorHandler = LacErrorHandler::getInstance();
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get validated and safe table name with prefix (standardized error handling)
     * Returns array result for new error handling, use safeTableString for legacy compatibility
     */
    public static function safeTable(string $prefix, string $name): array {
        $errorHandler = LacErrorHandler::getInstance();
        
        $prefixValidation = $errorHandler->validateInput(
            $prefix, 
            'string', 
            ['pattern' => '/^[a-zA-Z0-9_]+$/', 'max_length' => 64]
        );
        if (!$prefixValidation['success']) {
            return $errorHandler->handleError(
                'Invalid table prefix: must be alphanumeric/underscore only',
                LacErrorHandler::CATEGORY_VALIDATION,
                LacErrorHandler::SEVERITY_HIGH,
                ['prefix' => $prefix]
            );
        }
        
        $nameValidation = $errorHandler->validateInput(
            $name, 
            'string', 
            ['pattern' => '/^[a-zA-Z0-9_]+$/', 'max_length' => 64]
        );
        if (!$nameValidation['success']) {
            return $errorHandler->handleError(
                'Invalid table name: must be alphanumeric/underscore only',
                LacErrorHandler::CATEGORY_VALIDATION,
                LacErrorHandler::SEVERITY_HIGH,
                ['name' => $name]
            );
        }
        
        return LacErrorHandler::success($prefix . $name);
    }
    
    /**
     * Legacy wrapper for backward compatibility - returns string or throws exception
     */
    public static function safeTableString(string $prefix, string $name): string {
        $result = self::safeTable($prefix, $name);
        if (!$result['success']) {
            throw new InvalidArgumentException($result['error']);
        }
        return $result['data'];
    }
    
    /**
     * Get database connection with connection reuse and error handling
     */
    public function getConnection() {
        if ($this->connection !== null) {
            // Test connection is still alive
            if (@mysqli_ping($this->connection)) {
                return $this->connection;
            } else {
                // Connection died, close and recreate
                if ($this->debug) {
                    error_log('[LAC DEBUG] Database connection lost, reconnecting');
                }
                $this->close();
            }
        }
        
        global $conf;
        if (!isset($conf['db_host']) || !isset($conf['db_user']) || !isset($conf['db_password']) || !isset($conf['db_base'])) {
            if ($this->debug) {
                error_log('[LAC DEBUG] Database configuration missing');
            }
            return false;
        }
        
        $this->connection = mysqli_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
        self::$connectionCount++;
        
        if (!$this->connection) {
            if ($this->debug) {
                error_log('[LAC DEBUG] Database connection failed: ' . mysqli_connect_error());
            }
            return false;
        }
        
        // Set connection charset for security
        mysqli_set_charset($this->connection, 'utf8');
        
        if ($this->debug) {
            error_log('[LAC DEBUG] Database connection established (total: ' . self::$connectionCount . ')');
        }
        
        return $this->connection;
    }
    
    /**
     * Execute a prepared statement query with parameters and caching (standardized error handling)
     * @param string $query SQL query with ? placeholders
     * @param string $types Parameter types string (e.g., 'si' for string+int)
     * @param array $params Array of parameter values
     * @param bool $useCache Whether to cache results for repeated queries
     * @return array Result with success/error information
     */
    public function preparedQuery(string $query, string $types = '', array $params = [], bool $useCache = false): array {
        // Check cache first for cacheable queries
        if ($useCache && empty($params)) {
            $cacheKey = md5($query);
            if (isset(self::$queryCache[$cacheKey])) {
                if ($this->debug && lac_is_verbose_debug_mode()) {
                    error_log('[LAC DEBUG] Query cache hit: ' . substr($query, 0, 50) . '...');
                }
                return LacErrorHandler::success(self::$queryCache[$cacheKey], ['cached' => true]);
            }
        }
        
        $connection = $this->getConnection();
        if (!$connection) {
            return $this->errorHandler->handleDatabaseError(
                'Connection failed',
                'Cannot establish database connection',
                ['query' => substr($query, 0, 100)]
            );
        }
        
        $stmt = $connection->prepare($query);
        if (!$stmt) {
            return $this->errorHandler->handleDatabaseError(
                'Query preparation failed',
                $connection->error,
                ['query' => substr($query, 0, 100)]
            );
        }
        
        if (!empty($params)) {
            if (!$stmt->bind_param($types, ...$params)) {
                $stmt->close();
                return $this->errorHandler->handleDatabaseError(
                    'Parameter binding failed',
                    $stmt->error,
                    ['types' => $types, 'param_count' => count($params)]
                );
            }
        }
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            return $this->errorHandler->handleDatabaseError(
                'Query execution failed',
                $error,
                ['query' => substr($query, 0, 100)]
            );
        }
        
        $result = $stmt->get_result();
        $data = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        
        $stmt->close();
        
        // Cache result if requested and successful
        if ($useCache && !empty($data) && empty($params)) {
            $cacheKey = md5($query);
            self::$queryCache[$cacheKey] = $data;
            if ($this->debug && lac_is_verbose_debug_mode()) {
                error_log('[LAC DEBUG] Query result cached: ' . substr($query, 0, 50) . '...');
            }
        }
        
        return LacErrorHandler::success($data, ['query_time' => microtime(true)]);
    }
    
    /**
     * Get a single configuration value from database with caching (standardized error handling)
     * @param string $param Configuration parameter name
     * @param mixed $default Default value if not found
     * @return array Result with success/error information
     */
    public function getConfigParam(string $param, $default = null): array {
        $validation = $this->errorHandler->validateInput($param, 'string', ['required' => true]);
        if (!$validation['success']) {
            return $this->errorHandler->error('VALIDATION_ERROR', 'Parameter name cannot be empty');
        }
        
        global $conf;
        
        // Check if already loaded in $conf
        if (isset($conf[$param])) {
            return LacErrorHandler::success($conf[$param], ['source' => 'global_config']);
        }
        
        $prefix = isset($conf['db_prefix']) ? $conf['db_prefix'] : 'piwigo_';
        
        try {
            $configTableResult = self::safeTable($prefix, 'config');
            if (!$configTableResult['success']) {
                return LacErrorHandler::success($default, ['source' => 'default_value', 'reason' => 'table_check_failed']);
            }
            $configTable = $configTableResult['data'];
            
            $query = "SELECT value FROM {$configTable} WHERE param = ?";
            // Use caching for config queries since they're frequently accessed
            $results = $this->preparedQuery($query, 's', [$param], true);
            
            if (!$results['success']) {
                return LacErrorHandler::success($default, ['source' => 'default_value', 'reason' => 'query_failed']);
            }
            
            if (!empty($results['data'])) {
                $value = $results['data'][0]['value'];
                // Cache in $conf for subsequent requests
                $conf[$param] = $value;
                return LacErrorHandler::success($value, ['source' => 'database', 'cached_in_conf' => true]);
            }
        } catch (Exception $e) {
            return $this->errorHandler->handleDatabaseError(
                'Configuration query failed',
                $e->getMessage(),
                ['param' => $param]
            );
        }
        
        return LacErrorHandler::success($default, ['source' => 'default_value', 'reason' => 'not_found']);
    }
    
    /**
     * Backward compatibility wrapper for getConfigParam (legacy return format)
     * @deprecated Use getConfigParam() with standardized error handling
     * @param string $param Configuration parameter name
     * @param mixed $default Default value if not found
     * @return mixed Configuration value or default
     */
    public function getConfigParamLegacy(string $param, $default = null) {
        $result = $this->getConfigParam($param, $default);
        return $result['success'] ? $result['data'] : $default;
    }
    
    /**
     * Clear query cache (useful for testing or after config changes)
     */
    public static function clearCache(): void {
        self::$queryCache = [];
    }
    
    /**
     * Get connection statistics for performance monitoring
     */
    public static function getStats(): array {
        return [
            'total_connections' => self::$connectionCount,
            'cached_queries' => count(self::$queryCache),
            'cache_keys' => array_keys(self::$queryCache)
        ];
    }
    
    /**
     * Close database connection
     */
    public function close() {
        if ($this->connection) {
            mysqli_close($this->connection);
            $this->connection = null;
        }
    }
    
    /**
     * Destructor - ensure connection is closed
     */
    public function __destruct() {
        $this->close();
    }
}

// Backward compatibility functions - delegate to centralized class
if (!function_exists('lac_safe_table')) {
    function lac_safe_table(string $prefix, string $name): string {
        return LacDatabaseHelper::safeTableString($prefix, $name);
    }
}