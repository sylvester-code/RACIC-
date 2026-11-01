<?php
// config/app.php - RACIC Church Management System Configuration

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Church Information
define('CHURCH_NAME', 'ROYAL AMBASSADORS OF CHRIST INTERNATIONAL CHURCH');
define('CHURCH_SHORT_NAME', 'RACIC');
define('CHURCH_SLOGAN', 'Raising Ambassadors of Christ to Impact Nations');
define('CHURCH_EMAIL', 'info@racic.org');
define('CHURCH_PHONE', '+233 24 111 2233');
define('CHURCH_ADDRESS', 'Ambassador Cathedral, Airport Residential Area, Accra, Ghana');
define('CURRENCY_SYMBOL', 'GHS ');
define('APP_TIMEZONE', 'Africa/Accra');
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

define('BASE_URL', $protocol . $host . $subDir . '/');
define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// On Vercel, filesystem outside /tmp is read-only
$uploadBase = getenv('VERCEL') ? '/tmp/uploads/' : (BASE_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOAD_PATH', $uploadBase);
define('UPLOAD_URL', BASE_URL . 'uploads/');

// Session timeout duration (2 hours = 7200 seconds)
define('SESSION_TIMEOUT', 7200);

require_once __DIR__ . '/database.php';
require_once BASE_PATH . 'includes/functions.php';
require_once BASE_PATH . 'includes/auth.php';
