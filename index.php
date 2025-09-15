<?php
    // Define consent root constant early so plugin guard can reliably detect this page
    if (!defined('LAC_CONSENT_ROOT')) {
        define('LAC_CONSENT_ROOT', '/index.php');
    }
    $debug = (defined('LAC_DEBUG') && LAC_DEBUG) || isset($_GET['lac_debug']);
    
    // Determine if HTTPS is enabled (moved up for session configuration)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    
    // Configure secure session parameters BEFORE starting session
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
        if ($debug) error_log('[LAC DEBUG] Root: session already active');
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
        
        // Set secure LAC timestamp cookie with proper security attributes
        setcookie($cookieName, (string)time(), [
            'expires' => time() + $cookieWindow,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        
        // Regenerate session ID on consent for additional security
        session_regenerate_id(true);
        $_SESSION['lac_session_regenerated'] = time();
        
        if ($debug) error_log('[LAC DEBUG] Root: consent accepted, session regenerated');
    }

// Configure session cookie parameters with HttpOnly, Secure, and SameSite attributes
// Session parameters are now configured before session_start() for better security
if ($debug) error_log('[LAC DEBUG] Root: session cookie name=' . session_name());

// Determine gallery (Piwigo) directory (hardcoded to ./albums with optional override via .gallerydir)
$gallerydir_path = __DIR__ . '/.gallerydir';
$galleryDir = './albums'; // default fallback
if (file_exists($gallerydir_path)) {
    $custom = trim(file_get_contents($gallerydir_path));
    if ($custom !== '') {
        $galleryDir = $custom;
    }
}

// 1) Handle incoming redirect parameter on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['redirect'])) {
    $raw = $_GET['redirect'];
    // Decode, parse, strip sid, rebuild
    $url = urldecode($raw);
    $parts = parse_url($url);
    $path  = $parts['path'] ?? '';
    parse_str($parts['query'] ?? '', $qs);
    unset($qs['sid']);              // REMOVE the session id!
    $clean = $path;
    if (!empty($qs)) {
        $clean .= '?' . http_build_query($qs);
    }
    // Allow any path under /albums (or custom galleryDir), fallback to gallery index if not matching
    if (preg_match('#^/' . preg_quote(trim($galleryDir, './'), '#') . '(/|$)#', $clean)) {
        $_SESSION['LAC_REDIRECT'] = $clean;
    } else {
        // If not a gallery path, fallback to gallery index
        $_SESSION['LAC_REDIRECT'] = '/' . trim($galleryDir, './') . '/index.php';
    }
}
// Helper: validate table prefix (allow only alnum + underscore) and append suffix
if (!function_exists('lac_safe_table')) {
    function lac_safe_table(string $prefix, string $name): string {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) { $prefix = 'piwigo_'; }
        return $prefix . $name;
    }
}

// 2) Load configuration needed for consent duration (DB direct lightweight)
$consentDuration = 0; // minutes; 0 = session-only
try {
    $conf = [];
    $prefixeTable = 'piwigo_';
    @include __DIR__ . '/albums/local/config/database.inc.php';
    if (!empty($conf['db_host'])) {
        $mysqli = @mysqli_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
        if ($mysqli) {
            $configTable = lac_safe_table($prefixeTable, 'config');
            // Use prepared statement even though param list is static (defensive consistency)
            $stmt = @mysqli_prepare($mysqli, "SELECT value FROM `{$configTable}` WHERE param=? LIMIT 1");
            if ($stmt) {
                $param = 'lac_consent_duration';
                @mysqli_stmt_bind_param($stmt, 's', $param);
                if (@mysqli_stmt_execute($stmt)) {
                    $res = @mysqli_stmt_get_result($stmt);
                    if ($res && ($row = mysqli_fetch_assoc($res))) {
                        $consentDuration = (int)$row['value'];
                    }
                }
                @mysqli_stmt_close($stmt);
            }
            @mysqli_close($mysqli);
        }
    }
} catch (Throwable $e) { /* swallow; defaults remain */ }

// Helper to mark structured consent
function lac_mark_consent_structured(?int $ts = null) {
    if ($ts === null || $ts <= 0) { $ts = time(); }
    $_SESSION['lac_consent'] = ['granted' => true, 'timestamp' => $ts];
    $_SESSION['lac_consent_granted'] = true; // legacy flag
}

// 3) If user already has valid cookie+session id, validate duration (Option A strategy)
$cookie_lifetime = defined('LAC_COOKIE_MAX_WINDOW') ? LAC_COOKIE_MAX_WINDOW : 86400; // absolute hard cap for cookie storage
$cookieName = defined('LAC_COOKIE_NAME') ? LAC_COOKIE_NAME : 'LAC';
if (isset($_COOKIE[$cookieName], $_COOKIE['PHPSESSID']) && ctype_digit($_COOKIE[$cookieName])) {
    $cookieTs = (int)$_COOKIE[$cookieName];
    $cookieAge = time() - $cookieTs;
    $withinCookieWindow = $cookieAge < $cookie_lifetime;
    $withinConfiguredWindow = ($consentDuration === 0) || ($cookieAge < ($consentDuration * 60));
    if ($withinCookieWindow && $withinConfiguredWindow) {
        // Reuse original cookie timestamp so plugin expiration matches root logic
        lac_mark_consent_structured($cookieTs);
        $target = $_SESSION['LAC_REDIRECT'] ?? ('/' . trim($galleryDir, './') . '/index.php');
        unset($_SESSION['LAC_REDIRECT']);
        header("Location: " . $target);
        exit;
    }
}

// 3) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consent'])) {
    if ($_POST['consent'] === '18+') {
        // set new LAC timestamp cookie
        $cookieName = defined('LAC_COOKIE_NAME') ? LAC_COOKIE_NAME : 'LAC';
        setcookie($cookieName, (string)time(), [
            'expires'  => time() + $cookie_lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
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
            // Minimal bootstrap: load database credentials and perform a direct query.
            $conf = [];
            $prefixeTable = 'piwigo_'; // default fallback; will be overridden by include
            @include __DIR__ . '/albums/local/config/database.inc.php';
            if (!empty($conf['db_host'])) {
                $mysqli = @mysqli_connect($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_base']);
                if ($mysqli) {
                    $configTable = lac_safe_table($prefixeTable, 'config');
                    $stmt = @mysqli_prepare($mysqli, "SELECT value FROM `{$configTable}` WHERE param=? LIMIT 1");
                    if ($stmt) {
                        $param = 'lac_fallback_url';
                        @mysqli_stmt_bind_param($stmt, 's', $param);
                        if (@mysqli_stmt_execute($stmt)) {
                            $res = @mysqli_stmt_get_result($stmt);
                            if ($res && ($row = mysqli_fetch_assoc($res))) {
                                $val = $row['value'];
                                if ($val === 'false') { $val = ''; }
                                if ($val !== 'true' && $val !== 'false') { $configuredFallback = trim($val); }
                            }
                        }
                        @mysqli_stmt_close($stmt);
                    }
                    @mysqli_close($mysqli);
                }
            }
        } catch (Throwable $e) {
            // Swallow; will fall back to legacy file based approach
        }
        // Legacy file-based fallbacks removed: DB value only now.
        $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $currentUrl = "{$protocol}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
        $target = $configuredFallback !== '' ? $configuredFallback : (!empty($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] !== $currentUrl ? $_SERVER['HTTP_REFERER'] : 'https://www.google.com');
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
