<?php
// config/app.php - RACIC Church Management System Configuration

// Enable output buffering so headers can always be modified/redirected cleanly
if (!ob_get_level()) {
    ob_start();
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_only_cookies', 1);
    session_start();
}

// Church Information
if (!defined('CHURCH_NAME')) define('CHURCH_NAME', 'ROYAL AMBASSADORS OF CHRIST INTERNATIONAL CHURCH');
if (!defined('CHURCH_SHORT_NAME')) define('CHURCH_SHORT_NAME', 'RACIC');
if (!defined('CHURCH_SLOGAN')) define('CHURCH_SLOGAN', 'Raising Ambassadors of Christ to Impact Nations');
if (!defined('CHURCH_EMAIL')) define('CHURCH_EMAIL', 'info@racic.org');
if (!defined('CHURCH_PHONE')) define('CHURCH_PHONE', '+233 24 111 2233');
if (!defined('CHURCH_ADDRESS')) define('CHURCH_ADDRESS', 'Ambassador Cathedral, Airport Residential Area, Accra, Ghana');
if (!defined('CURRENCY_SYMBOL')) define('CURRENCY_SYMBOL', 'GHS ');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'Africa/Accra');
date_default_timezone_set(APP_TIMEZONE);

// Base URL calculation (Supports Local XAMPP subdirectories and Vercel root deployments)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443 || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$subDir = '';
if (!getenv('VERCEL')) {
    $scriptPath = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

    if (!empty($docRoot) && strpos($scriptPath, $docRoot) === 0) {
        $subDir = substr($scriptPath, strlen($docRoot));
    } else {
        $parts = explode('/', trim($_SERVER['SCRIPT_NAME'] ?? '', '/'));
        if (!empty($parts) && strtolower($parts[0]) === 'racic') {
            $subDir = '/' . $parts[0];
        }
    }
}
$subDir = rtrim($subDir, '/');

if (!defined('BASE_URL')) define('BASE_URL', $protocol . $host . $subDir . '/');
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// On Vercel, filesystem outside /tmp is read-only
$uploadBase = getenv('VERCEL') ? '/tmp/uploads/' : (BASE_PATH . 'uploads' . DIRECTORY_SEPARATOR);
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', $uploadBase);
if (!defined('UPLOAD_URL')) define('UPLOAD_URL', BASE_URL . 'uploads/');

// Session timeout duration (2 hours = 7200 seconds)
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 7200);

require_once __DIR__ . '/database.php';
require_once BASE_PATH . 'includes/functions.php';
require_once BASE_PATH . 'includes/auth.php';
