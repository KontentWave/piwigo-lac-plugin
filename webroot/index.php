<?php
    // Define consent root constant early so plugin guard can reliably detect this page
    if (!defined('LAC_CONSENT_ROOT')) {
        define('LAC_CONSENT_ROOT', '/index.php');
    }
    
    // Default fallback URL when no configuration is set and no referer available
    if (!defined('LAC_DEFAULT_FALLBACK_URL')) {
        define('LAC_DEFAULT_FALLBACK_URL', 'https://www.google.com');
    }
    
    $debug = (defined('LAC_DEBUG') && LAC_DEBUG) || isset($_GET['lac_debug']);
    if ($debug) {
        $tmpLog = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'piwigo-lac.log';
        @ini_set('log_errors', 'On');
        @ini_set('error_log', $tmpLog);
        error_log('[LAC DEBUG] Root: logging to ' . $tmpLog);
    }
    
    // Determine if HTTPS is enabled (moved up for session configuration)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

    // Determine gallery (Piwigo) directory (hardcoded to ./albums with optional override via .gallerydir)
    $gallerydir_path = __DIR__ . '/.gallerydir';
    $galleryDir = './albums'; // default fallback
    if (file_exists($gallerydir_path)) {
        $custom = trim(@file_get_contents($gallerydir_path));
        if ($custom !== '') {
            $galleryDir = $custom;
        }
    }

    // Phase 6 Part 2: Try to bootstrap Piwigo and plugin (if available) BEFORE starting root session
    $piwigoBooted = false;
    $piwigoCommon = __DIR__ . '/albums/include/common.inc.php';
    if (file_exists($piwigoCommon)) {
        // Ensure no session is active yet to let Piwigo manage its own (pwg) session
        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
        if (!defined('PHPWG_ROOT_PATH')) {
            define('PHPWG_ROOT_PATH', __DIR__ . '/albums/');
        }
        include_once $piwigoCommon; // provides $user and $conf
        $piwigoBooted = true;
        if ($debug) error_log('[LAC DEBUG] Root: Piwigo environment bootstrapped');
    }

    // Check plugin availability (on disk) and activation (DB), then run exemption logic if active
    $pluginBootstrap = __DIR__ . '/albums/plugins/lac/include/bootstrap.inc.php';
    $lacPluginAvailableOnDisk = file_exists($pluginBootstrap);
    $lacPluginActive = false;
    if (!empty($piwigoBooted) && function_exists('pwg_query')) {
        // Determine if plugin is active in DB
        try {
            $prefix = isset($conf['db_prefix']) ? $conf['db_prefix'] : 'piwigo_';
            $pluginsTable = lac_safe_table($prefix, 'plugins');
            $q = "SELECT state FROM `{$pluginsTable}` WHERE id='lac' LIMIT 1";
            $res = pwg_query($q);
            if ($res) {
                $row = pwg_db_fetch_assoc($res);
                if ($row && isset($row['state']) && $row['state'] === 'active') {
                    $lacPluginActive = true;
                }
            }
        } catch (Throwable $e) {
            if ($debug) error_log('[LAC DEBUG] Root: error checking plugin active state: ' . $e->getMessage());
        }
    }
    $lacUsePlugin = $lacPluginAvailableOnDisk && $lacPluginActive && !empty($piwigoBooted);
    if ($lacUsePlugin) {
        if (!defined('LAC_PATH')) { define('LAC_PATH', __DIR__ . '/albums/plugins/lac/'); }
        include_once $pluginBootstrap; // guarded by LAC_PATH in file
        if ($debug) error_log('[LAC DEBUG] Root: LAC plugin is active, using full exemption logic');
        if (function_exists('lac_is_user_exempt')) {
            $exempt = false;
            try { $exempt = lac_is_user_exempt(); } catch (Throwable $e) { $exempt = false; }
            if ($exempt) {
                // Redirect exempt users to gallery index and exit early
                $target = '/' . trim($galleryDir, './') . '/index.php';
                header('Location: ' . $target);
                exit;
            }
        }
    } else if ($debug) {
        $why = [];
        if (empty($piwigoBooted)) $why[] = 'Piwigo not booted';
        if (!$lacPluginAvailableOnDisk) $why[] = 'plugin files missing';
        if (!$lacPluginActive) $why[] = 'plugin inactive';
        error_log('[LAC DEBUG] Root: session-only fallback mode (' . implode(', ', $why) . ')');
    }

    // Configure secure session parameters BEFORE starting root LAC session (only if no session is active)
    if (session_status() === PHP_SESSION_NONE) {
        if ($debug) error_log('[LAC DEBUG] Root: configuring secure session');
        
        // Set secure session cookie parameters
        session_set_cookie_params([
            'lifetime' => 0,                    // Session cookies (expire on browser close)
            'path' => '/',                      // Available site-wide
            'domain' => '',                     // Current domain only
            'secure' => $secure,                // HTTPS only if available
            'httponly' => true,                 // Prevent XSS access via JavaScript
            'samesite' => 'Lax'                // CSRF protection
        ]);
        
        // Set session name to something less predictable
        session_name('LAC_SESSION');
        
        if ($debug) error_log('[LAC DEBUG] Root: start session');
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['lac_session_regenerated']) || 
            (time() - $_SESSION['lac_session_regenerated']) > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['lac_session_regenerated'] = time();
            if ($debug) error_log('[LAC DEBUG] Root: session ID regenerated');
        }
    } else {
        if ($debug) error_log('[LAC DEBUG] Root: using existing Piwigo session');
    }

    // Accept triggers: legacy field name or our consent buttons; also allow lac_accept=1 for diagnostics
    $accept = false;
    if (isset($_POST['consent']) && $_POST['consent']==='18+') { $accept = true; }
    if (isset($_POST['legal_age'])) { $accept = true; }
    if (isset($_GET['lac_accept']) && $_GET['lac_accept'] == '1') { $accept = true; }
    if ($accept) {
        $_SESSION['lac_consent_granted'] = true; // legacy
        $_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => time()];
        $cookieName = defined('LAC_COOKIE_NAME') ? LAC_COOKIE_NAME : 'LAC';
        $cookieWindow = defined('LAC_COOKIE_MAX_WINDOW') ? LAC_COOKIE_MAX_WINDOW : 86400;
        
        // Set secure LAC timestamp cookie with standardized helper (if available)
        if (function_exists('lac_set_consent_cookie')) {
            lac_set_consent_cookie(time(), $secure);
        } else {
            setcookie($cookieName, (string)time(), [
                'expires' => time() + $cookieWindow,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        
        // Determine target: prefer saved intended destination (Phase 6)
        $target = '';
        if (!empty($_SESSION['LAC_REDIRECT'])) {
            $candidate = $_SESSION['LAC_REDIRECT'];
            // Basic validation: same-origin and under gallery path if relative
            $isValid = false;
            $parsed = @parse_url($candidate);
            if ($parsed !== false) {
                $candidateHost = $parsed['host'] ?? '';
                $candidateScheme = $parsed['scheme'] ?? '';
                $candidatePath = $parsed['path'] ?? '';
                $currentHost = $_SERVER['HTTP_HOST'] ?? '';
                $isSameOrigin = ($candidateHost === '' || strcasecmp($candidateHost, $currentHost) === 0);
                // Accept relative or same-origin absolute URLs; also ensure it points to the gallery subtree
                $galleryPrefix = '/' . trim($galleryDir, './') . '/';
                $pointsToGallery = (strpos($candidatePath, $galleryPrefix) === 0) || ($candidatePath === '/');
                if ($isSameOrigin && ($candidateHost === '' || in_array(strtolower($candidateScheme), ['http','https',''], true)) && $pointsToGallery) {
                    $isValid = true;
                }
            }
            if ($isValid) {
                $target = $candidate;
            }
            unset($_SESSION['LAC_REDIRECT']); // one-time use
        }

        // Regenerate session ID on consent for additional security
        session_regenerate_id(true);
        $_SESSION['lac_session_regenerated'] = time();
        
        if ($debug) error_log('[LAC DEBUG] Root: consent accepted, session regenerated');
        
        // If no valid saved target, fall back to existing behavior
        if ($target === '') {
            $target = '/' . trim($galleryDir, './') . '/index.php';
        }
        header('Location: ' . $target);
        exit;
    }

// Configure session cookie parameters with HttpOnly, Secure, and SameSite attributes
// Session parameters are now configured before session_start() for better security
if ($debug) error_log('[LAC DEBUG] Root: session cookie name=' . session_name());

// (galleryDir determined above before accept handling)

// 1) Handle incoming redirect parameter on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['redirect'])) {
    $raw = $_GET['redirect'];
    $url = urldecode($raw);
    $parts = parse_url($url);
    $path  = $parts['path'] ?? '';
    $query = $parts['query'] ?? '';
    // Same-origin check when an absolute URL is provided
    if (isset($parts['host']) && $parts['host'] !== '' && isset($_SERVER['HTTP_HOST']) && strcasecmp($parts['host'], $_SERVER['HTTP_HOST']) !== 0) {
        // Cross-origin not allowed; fallback to gallery index
        $_SESSION['LAC_REDIRECT'] = '/' . trim($galleryDir, './') . '/index.php';
    } else {
        // Preserve full query string, including potential "sid" parameter used by Piwigo
        // Previous implementation stripped "sid", which can cause redirect loops if Piwigo
        // relies on it for session continuity (especially with cookie path differences).
        // We keep the query intact and rely on same-origin + gallery-subtree checks below.
        $clean = $path;
        if ($query !== '') {
            $clean .= '?' . $query;
        }
        // Allow any path under /albums (or custom galleryDir), fallback to gallery index if not matching
        if (preg_match('#^/' . preg_quote(trim($galleryDir, './'), '#') . '(/|$)#', $clean)) {
            $_SESSION['LAC_REDIRECT'] = $clean;
        } else {
            // If not a gallery path, fallback to gallery index
            $_SESSION['LAC_REDIRECT'] = '/' . trim($galleryDir, './') . '/index.php';
        }
    }
}
// Helper: validate table prefix (allow only alnum + underscore) and append suffix
if (!function_exists('lac_safe_table')) {
    function lac_safe_table(string $prefix, string $name): string {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) { $prefix = 'piwigo_'; }
        return $prefix . $name;
    }
}

// 2) Load configuration needed for consent duration
// minutes; 0 = session-only when KNOWN. If unknown, we won't auto-redirect based on session.
$consentDuration = 0;
$consentDurationKnown = false;
try {
    if (!empty($piwigoBooted) && function_exists('pwg_query')) {
        // Prefer using existing Piwigo DB connection
        $prefix = isset($conf['db_prefix']) ? $conf['db_prefix'] : 'piwigo_';
        $configTable = lac_safe_table($prefix, 'config');
        $q = "SELECT value FROM `{$configTable}` WHERE param='lac_consent_duration' LIMIT 1";
        $res = pwg_query($q);
        if ($res) {
            $row = pwg_db_fetch_assoc($res);
            if ($row && isset($row['value'])) {
                $consentDuration = (int)$row['value'];
                $consentDurationKnown = true;
            }
        }
    } else {
        // Fallback to direct mysqli (even when plugin not booted) to honor configured duration in root flows
        if (!isset($conf['db_host']) && file_exists(__DIR__ . '/albums/local/config/database.inc.php')) {
            include_once __DIR__ . '/albums/local/config/database.inc.php';
        }
        if (!empty($conf['db_host'])) {
            $lac_mysqli = @mysqli_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
            if ($lac_mysqli) {
                $lac_prefix = isset($conf['db_prefix']) ? $conf['db_prefix'] : 'piwigo_';
                $configTable = lac_safe_table($lac_prefix, 'config');
                $stmt = @mysqli_prepare($lac_mysqli, "SELECT value FROM `{$configTable}` WHERE param=? LIMIT 1");
                if ($stmt) {
                    $param = 'lac_consent_duration';
                    if (mysqli_stmt_bind_param($stmt, 's', $param) && mysqli_stmt_execute($stmt)) {
                        $res = mysqli_stmt_get_result($stmt);
                        if ($res && ($row = mysqli_fetch_assoc($res))) {
                            $consentDuration = (int)$row['value'];
                            $consentDurationKnown = true;
                        }
                    }
                    mysqli_stmt_close($stmt);
                } else if ($debug) {
                    error_log('[LAC DEBUG] Failed to prepare duration query: ' . mysqli_error($lac_mysqli));
                }
                mysqli_close($lac_mysqli);
            } else if ($debug) {
                error_log('[LAC DEBUG] Database connection failed for consent duration lookup');
            }
        } else if ($debug) {
            error_log('[LAC DEBUG] Database config not available; consent duration unknown');
        }
    }
} catch (Throwable $e) { 
    if ($debug) { error_log('[LAC DEBUG] Error loading consent duration: ' . $e->getMessage()); }
}

// Helper to mark structured consent
function lac_mark_consent_structured(?int $ts = null) {
    if ($ts === null || $ts <= 0) { $ts = time(); }
    $_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => $ts];
    $_SESSION['lac_consent_granted'] = true; // legacy flag
}

// 2.7) If consent already present in session, redirect immediately (only if still valid)
try {
    $hasStructured = isset($_SESSION['lac_consent']) && is_array($_SESSION['lac_consent']) && !empty($_SESSION['lac_consent']['granted']);
    $ts = $hasStructured ? (int)($_SESSION['lac_consent']['timestamp'] ?? 0) : 0;
    $hasLegacy = isset($_SESSION['lac_consent_granted']) && $_SESSION['lac_consent_granted'] === true;

    $shouldRedirect = false;
    // Respect configured duration consistently. If duration is unknown, be conservative and do NOT auto-redirect.
    if ($hasStructured) {
        if ($consentDurationKnown) {
            if ($consentDuration === 0) {
                $shouldRedirect = true; // session-only mode explicitly configured
            } else if ($ts > 0 && (time() - $ts) < ($consentDuration * 60)) {
                $shouldRedirect = true; // within configured duration
            }
            // else: structured consent exists but is expired -> do not redirect; show UI to reconfirm
        } else if ($debug) {
            error_log('[LAC DEBUG] Root: consent duration unknown; will not auto-redirect based on session');
        }
    } elseif ($hasLegacy && $consentDurationKnown && $consentDuration === 0) {
        // Legacy flag only counts in session-only mode when that mode is explicitly configured
        $shouldRedirect = true;
    }

    if ($shouldRedirect) {
        if ($debug) error_log('[LAC DEBUG] Root: session already has consent; redirecting without prompting');
        $target = $_SESSION['LAC_REDIRECT'] ?? ('/' . trim($galleryDir, './') . '/index.php');
        unset($_SESSION['LAC_REDIRECT']);
        header('Location: ' . $target);
        exit;
    }
} catch (Throwable $e) {
    if ($debug) error_log('[LAC DEBUG] Root: error during session-consent check: ' . $e->getMessage());
}

// 3) If plugin is active and available, allow cookie-based auto-recognition; otherwise skip (session-only fallback)
$cookie_lifetime = defined('LAC_COOKIE_MAX_WINDOW') ? LAC_COOKIE_MAX_WINDOW : 86400; // absolute hard cap for cookie storage
$cookieName = defined('LAC_COOKIE_NAME') ? LAC_COOKIE_NAME : 'LAC';
// Allow restoration based on LAC cookie when plugin is truly in use OR duration is known (so we can validate age)
if (!empty($lacUsePlugin) || $consentDurationKnown) {
    if (isset($_COOKIE[$cookieName]) && ctype_digit($_COOKIE[$cookieName])) {
        $cookieTs = (int)$_COOKIE[$cookieName];
        $cookieAge = time() - $cookieTs;
        $withinCookieWindow = $cookieAge < $cookie_lifetime;
        $withinConfiguredWindow = $consentDurationKnown ? (($consentDuration === 0) || ($cookieAge < ($consentDuration * 60))) : false;
        if ($withinCookieWindow && $withinConfiguredWindow) {
            // Reuse original cookie timestamp so plugin expiration matches root logic
            lac_mark_consent_structured($cookieTs);
            $target = $_SESSION['LAC_REDIRECT'] ?? ('/' . trim($galleryDir, './') . '/index.php');
            unset($_SESSION['LAC_REDIRECT']);
            header("Location: " . $target);
            exit;
        }
    }
} else if ($debug) {
    error_log('[LAC DEBUG] Root: skipping cookie reconstruction (duration unknown and plugin not active)');
}

// 3) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consent'])) {
    if ($_POST['consent'] === '18+') {
        // set new LAC timestamp cookie
        $cookieName = defined('LAC_COOKIE_NAME') ? LAC_COOKIE_NAME : 'LAC';
        if (function_exists('lac_set_consent_cookie')) {
            lac_set_consent_cookie(time(), $secure);
        } else {
            setcookie($cookieName, (string)time(), [
                'expires'  => time() + $cookie_lifetime,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        // store structured session consent
        lac_mark_consent_structured();

    // choose the stored redirect or the fallback
    $target = $_SESSION['LAC_REDIRECT'] ?? ('/' . trim($galleryDir, './') . '/index.php');
    unset($_SESSION['LAC_REDIRECT']);
    header("Location: " . $target);
    exit;

    } elseif ($_POST['consent'] === 'under18') {
        // Decline: use configured fallback if present, else referrer, else Google
        $configuredFallback = '';
        // Attempt to read value directly from Piwigo config table to avoid filesystem permission issues.
        try {
            if (!empty($piwigoBooted) && function_exists('pwg_query')) {
                $prefix = isset($conf['db_prefix']) ? $conf['db_prefix'] : 'piwigo_';
                $configTable = lac_safe_table($prefix, 'config');
                $q = "SELECT value FROM `{$configTable}` WHERE param='lac_fallback_url' LIMIT 1";
                $res = pwg_query($q);
                if ($res) {
                    $row = pwg_db_fetch_assoc($res);
                    if ($row && isset($row['value'])) {
                        $val = $row['value'];
                        if ($val === 'false') { $val = ''; }
                        if ($val !== 'true' && $val !== 'false') { $configuredFallback = trim($val); }
                    }
                }
            } else {
                // Direct mysqli fallback
                if (!isset($conf['db_host']) && file_exists(__DIR__ . '/albums/local/config/database.inc.php')) {
                    include_once __DIR__ . '/albums/local/config/database.inc.php';
                }
                if (!empty($conf['db_host'])) {
                    $lac_mysqli = mysqli_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
                    if ($lac_mysqli) {
                        $lac_prefix = isset($conf['db_prefix']) ? $conf['db_prefix'] : 'piwigo_';
                        $configTable = lac_safe_table($lac_prefix, 'config');
                        $stmt = mysqli_prepare($lac_mysqli, "SELECT value FROM `{$configTable}` WHERE param=? LIMIT 1");
                        if ($stmt) {
                            $param = 'lac_fallback_url';
                            if (mysqli_stmt_bind_param($stmt, 's', $param) && mysqli_stmt_execute($stmt)) {
                                $res = mysqli_stmt_get_result($stmt);
                                if ($res && ($row = mysqli_fetch_assoc($res))) {
                                    $val = $row['value'];
                                    if ($val === 'false') { $val = ''; }
                                    if ($val !== 'true' && $val !== 'false') { $configuredFallback = trim($val); }
                                }
                            }
                            mysqli_stmt_close($stmt);
                        } else if ($debug) {
                            error_log('[LAC DEBUG] Failed to prepare fallback URL query: ' . mysqli_error($lac_mysqli));
                        }
                        mysqli_close($lac_mysqli);
                    } else if ($debug) {
                        error_log('[LAC DEBUG] Database connection failed for fallback URL lookup');
                    }
                }
            }
        } catch (Throwable $e) {
            // Swallow; will fall back to legacy file based approach
        }
        // Legacy file-based fallbacks removed: DB value only now.
        $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $currentUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        $target = $configuredFallback !== '' ? $configuredFallback : (!empty($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== $currentUrl ? $_SERVER['HTTP_REFERER'] : LAC_DEFAULT_FALLBACK_URL);
        header('Location: ' . $target);
        exit;
    }
}

// Add security headers to protect against session hijacking and other attacks
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
if ($secure) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

?>
<!DOCTYPE html>
<html>
<head>
	<!-- Google tag (gtag.js) -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=G-8H5H5EJ5ZD"></script>
	<script>
	  window.dataLayer = window.dataLayer || [];
	  function gtag(){dataLayer.push(arguments);}
	  gtag('js', new Date());
	  gtag('config', 'G-8H5H5EJ5ZD');
	</script>
    <title>Overenie veku</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="ageconsent.css">
</head>
<body class="age-consent-page"> <div class="age-consent-container">
    <h1>Stránky tohto fóra sú určené výhradne pre osoby staršie ako 18 rokov.</h1>
    <h2>Prosím, potvrďte svoj vek</h2>
    <p>Vstupom na fórum a kliknutím na tlačidlo "Súhlasím" potvrdzujem, že mám viac ako 18 rokov a súhlasím so <a href="./legal_clause.html" target="_blank" rel="noopener noreferrer"><strong>Zásadami tohto fóra</strong></a> a že obsah fóra nikdy nebudem sprístupňovať deťom a maloletým.</p>
    <form method="post">
        <button type="submit" name="consent" value="18+">Súhlasím, že som <br> starší ako 18 rokov</button>
        <br><br>
        <button type="submit" name="consent" value="under18">Som mladší ako 18 rokov<br> a chcem odísť</button>
    </form>
</div> </body>
</html>
