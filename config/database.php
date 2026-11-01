<?php
// config/database.php - RACIC Church Management System Database Connection
// Supports Local XAMPP, Cloud MySQL (TiDB, Aiven, PlanetScale, Railway, AWS RDS), and DATABASE_URL connection strings

// 1. Check for standard connection string (e.g. DATABASE_URL or MYSQL_URL)
$dbUrl = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL') ?: ($_ENV['MYSQL_URL'] ?? $_SERVER['MYSQL_URL'] ?? getenv('MYSQL_URL') ?: null);

$dbHost = 'localhost';
$dbPort = '3306';
$dbName = 'racic_church_management';
$dbUser = 'root';
$dbPass = '';

if (!empty($dbUrl)) {
    $parsed = parse_url($dbUrl);
    if (!empty($parsed['host'])) {
        $dbHost = $parsed['host'];
        $dbPort = (string)($parsed['port'] ?? '3306');
        $dbUser = $parsed['user'] ?? 'root';
        $dbPass = $parsed['pass'] ?? '';
        $dbName = ltrim($parsed['path'] ?? '', '/') ?: 'racic_church_management';
    }
} else {
    // 2. Individual environment variables (Vercel, Railway, Render, Docker)
    $dbHost = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? getenv('DB_HOST') ?: ($_ENV['MYSQLHOST'] ?? $_SERVER['MYSQLHOST'] ?? getenv('MYSQLHOST') ?: 'localhost');
    $dbPort = (string)($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? getenv('DB_PORT') ?: ($_ENV['MYSQLPORT'] ?? $_SERVER['MYSQLPORT'] ?? getenv('MYSQLPORT') ?: '3306'));
    $dbName = $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? getenv('DB_NAME') ?: ($_ENV['MYSQLDATABASE'] ?? $_SERVER['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?: 'racic_church_management');
    $dbUser = $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? getenv('DB_USER') ?: ($_ENV['MYSQLUSER'] ?? $_SERVER['MYSQLUSER'] ?? getenv('MYSQLUSER') ?: 'root');
    $dbPass = $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? getenv('DB_PASS') ?: ($_ENV['MYSQLPASSWORD'] ?? $_SERVER['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?: '');
}

if (!defined('DB_HOST')) define('DB_HOST', $dbHost);
if (!defined('DB_PORT')) define('DB_PORT', $dbPort);
if (!defined('DB_NAME')) define('DB_NAME', $dbName);
if (!defined('DB_USER')) define('DB_USER', $dbUser);
if (!defined('DB_PASS')) define('DB_PASS', $dbPass);
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $isVercel = (bool)(getenv('VERCEL') || isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']));
            
            // Build PDO DSN
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_TIMEOUT            => 5
            ];

            // If SSL CA certificate provided for cloud MySQL
            $sslCa = $_ENV['MYSQL_SSL_CA'] ?? $_SERVER['MYSQL_SSL_CA'] ?? getenv('MYSQL_SSL_CA');
            if ($sslCa) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // If running on Vercel without cloud DB configured yet, render helpful setup dashboard
                if ($isVercel) {
                    self::renderVercelDbSetupGuide($e->getMessage());
                    exit;
                }

                die("<div style='font-family:sans-serif;padding:30px;text-align:center;'>
                        <h2 style='color:#dc3545;'>Database Connection Failed</h2>
                        <p>Unable to connect to the RACIC Church Management database: " . htmlspecialchars($e->getMessage()) . "</p>
                        <p><small>Ensure MySQL is running in XAMPP and the database <code>racic_church_management</code> exists.</small></p>
                     </div>");
            }
        }
        return self::$instance;
    }

    private static function renderVercelDbSetupGuide(string $errorMessage): void {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Cloud Database Setup Required | RACIC CMS</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
            <style>
                body {
                    background: linear-gradient(135deg, #07172b 0%, #0f2c59 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: system-ui, -apple-system, sans-serif;
                    padding: 1.5rem;
                }
                .setup-card {
                    background: #ffffff;
                    border-radius: 16px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.4);
                    max-width: 680px;
                    width: 100%;
                    overflow: hidden;
                }
                .step-num {
                    width: 32px;
                    height: 32px;
                    background: #0f2c59;
                    color: #fff;
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    font-size: 0.9rem;
                    flex-shrink: 0;
                }
            </style>
        </head>
        <body>
            <div class="setup-card">
                <div class="p-4 bg-primary text-white text-center">
                    <div class="d-inline-flex p-3 rounded-circle bg-warning text-dark mb-2 shadow">
                        <i class="fas fa-database fs-3"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Connect Your Cloud MySQL Database</h4>
                    <p class="small text-white-50 mb-0">Royal Ambassadors of Christ International Church (RACIC)</p>
                </div>
                <div class="p-4 p-md-5">
                    <div class="alert alert-warning small mb-4">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Why you see this:</strong> Your application code successfully deployed to Vercel! However, Vercel runs on cloud serverless functions where <code>localhost</code> has no local MySQL service. You must link a free cloud MySQL database.
                    </div>

                    <h6 class="fw-bold text-dark mb-3">Quick 3-Minute Setup:</h6>
                    
                    <div class="d-flex gap-3 mb-3">
                        <div class="step-num">1</div>
                        <div>
                            <strong>Get a Free Cloud MySQL Database</strong>
                            <p class="small text-muted mb-1">Sign up at any free MySQL provider (takes 1 minute):</p>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="https://tidbcloud.com/" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-external-link-alt me-1"></i>TiDB Cloud (Free Serverless MySQL)</a>
                                <a href="https://aiven.io/" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-external-link-alt me-1"></i>Aiven Free MySQL</a>
                                <a href="https://railway.app/" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fas fa-external-link-alt me-1"></i>Railway MySQL</a>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-3">
                        <div class="step-num">2</div>
                        <div>
                            <strong>Import the RACIC Database Schema</strong>
                            <p class="small text-muted mb-0">Import the SQL file from your repository: <code>database/racic_church_management.sql</code> into your cloud database.</p>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="step-num">3</div>
                        <div>
                            <strong>Add Variables in Vercel Dashboard</strong>
                            <p class="small text-muted mb-2">Go to <strong>Vercel Dashboard &rarr; Project Settings &rarr; Environment Variables</strong>, then add either <code>DATABASE_URL</code> or these variables:</p>
                            <table class="table table-sm table-bordered font-monospace small mb-0">
                                <thead class="table-light"><tr><th>Variable Name</th><th>Value</th></tr></thead>
                                <tbody>
                                    <tr><td>DB_HOST</td><td>Your cloud database host</td></tr>
                                    <tr><td>DB_PORT</td><td>3306 (or cloud port)</td></tr>
                                    <tr><td>DB_NAME</td><td>racic_church_management</td></tr>
                                    <tr><td>DB_USER</td><td>Your database username</td></tr>
                                    <tr><td>DB_PASS</td><td>Your database password</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="border-top pt-3 text-center">
                        <small class="text-muted d-block mb-2">After adding the variables, click <strong>Redeploy</strong> in Vercel.</small>
                        <a href="/" class="btn btn-primary px-4 shadow-sm"><i class="fas fa-redo me-1"></i>Test Connection / Reload</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

if (!function_exists('getDB')) {
    function getDB(): PDO {
        return Database::getConnection();
    }
}
