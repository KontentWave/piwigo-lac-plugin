<?php
defined('LAC_PATH') or die('Hacking attempt!');

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
    
    private function __construct() {
        $this->debug = (defined('LAC_DEBUG') && LAC_DEBUG) || isset($_GET['lac_debug']);
    }
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get validated and safe table name with prefix
     */
    public static function safeTable(string $prefix, string $name): string {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $prefix)) {
            throw new InvalidArgumentException('Invalid table prefix: must be alphanumeric/underscore only');
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException('Invalid table name: must be alphanumeric/underscore only');
        }
        return $prefix . $name;
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
     * Execute a prepared statement query with parameters and caching
     * @param string $query SQL query with ? placeholders
     * @param string $types Parameter types string (e.g., 'si' for string+int)
     * @param array $params Array of parameter values
     * @param bool $useCache Whether to cache results for repeated queries
     * @return array|false Result array or false on failure
     */
    public function preparedQuery(string $query, string $types = '', array $params = [], bool $useCache = false) {
        // Check cache first for cacheable queries
        if ($useCache && empty($params)) {
            $cacheKey = md5($query);
            if (isset(self::$queryCache[$cacheKey])) {
                if ($this->debug) {
                    error_log('[LAC DEBUG] Query cache hit: ' . substr($query, 0, 50) . '...');
                }
                return self::$queryCache[$cacheKey];
            }
        }
        
        $connection = $this->getConnection();
        if (!$connection) {
            return false;
        }
        
        $stmt = $connection->prepare($query);
        if (!$stmt) {
            if ($this->debug) {
                error_log('[LAC DEBUG] Prepare failed: ' . $connection->error);
            }
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            if ($this->debug) {
                error_log('[LAC DEBUG] Execute failed: ' . $stmt->error);
            }
            $stmt->close();
            return false;
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
            if ($this->debug) {
                error_log('[LAC DEBUG] Query result cached: ' . substr($query, 0, 50) . '...');
            }
        }
        
        return $data;
    }
    
    /**
     * Get a single configuration value from database with caching
     * @param string $param Configuration parameter name
     * @param mixed $default Default value if not found
     * @return mixed Configuration value or default
     */
    public function getConfigParam(string $param, $default = null) {
        global $conf;
        
        // Check if already loaded in $conf
        if (isset($conf[$param])) {
            return $conf[$param];
        }
        
        $prefix = isset($conf['db_prefix']) ? $conf['db_prefix'] : 'piwigo_';
        
        try {
            $configTable = self::safeTable($prefix, 'config');
            $query = "SELECT value FROM {$configTable} WHERE param = ?";
            // Use caching for config queries since they're frequently accessed
            $results = $this->preparedQuery($query, 's', [$param], true);
            
            if (!empty($results)) {
                $value = $results[0]['value'];
                // Cache in $conf for subsequent requests
                $conf[$param] = $value;
                return $value;
            }
        } catch (Exception $e) {
            if ($this->debug) {
                error_log('[LAC DEBUG] Config query failed: ' . $e->getMessage());
            }
        }
        
        return $default;
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
        return LacDatabaseHelper::safeTable($prefix, $name);
    }
}