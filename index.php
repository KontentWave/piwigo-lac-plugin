    <?php
// Determine if HTTPS is enabled
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

// Configure session cookie parameters with HttpOnly, Secure, and SameSite attributes
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

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
// 2) If user already has a valid LAC cookie + PHPSESSID, go straight there
$cookie_lifetime = 86400;
if (
    isset($_COOKIE['LAC'], $_COOKIE['PHPSESSID']) &&
    is_numeric($_COOKIE['LAC']) &&
    (time() - (int)$_COOKIE['LAC']) < $cookie_lifetime
) {
    // mark current session as having granted consent for the gallery/plugin
    $_SESSION['lac_consent_granted'] = true;
    // choose the stored redirect or the fallback
    $target = $_SESSION['LAC_REDIRECT'] ?? ('/' . trim($galleryDir, './') . '/index.php');
    unset($_SESSION['LAC_REDIRECT']);
    header("Location: " . $target);
    exit;
}

// 3) Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['consent'])) {
    if ($_POST['consent'] === '18+') {
        // set new LAC timestamp cookie
        setcookie("LAC", (string)time(), [
            'expires'  => time() + $cookie_lifetime,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        // also set session flag consumed by the Piwigo plugin age gate
        $_SESSION['lac_consent_granted'] = true;

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
                    $table = $prefixeTable . 'config';
                    $sql = "SELECT value FROM `".$table."` WHERE param='lac_fallback_url' LIMIT 1";
                    $res = @mysqli_query($mysqli, $sql);
                    if ($res && ($row = mysqli_fetch_assoc($res))) {
                        $val = $row['value'];
                        if ($val === 'false') { $val = ''; }
                        if ($val !== 'true' && $val !== 'false') { $configuredFallback = trim($val); }
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
