<?php
// config/database.php - RACIC Church Management System Database Connection
// Supports both Local XAMPP and Cloud Serverless Deployments (Vercel, Railway, Render, etc.)

$dbHost = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$dbPort = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306');
$dbName = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'racic_church_management');
$dbUser = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$dbPass = getenv('DB_PASS') ?: (getenv('MYSQLPASSWORD') ?: '');

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];

            // If running on cloud with SSL requirement
            if (getenv('MYSQL_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = getenv('MYSQL_SSL_CA');
            }

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                die("<div style='font-family:sans-serif;padding:30px;text-align:center;'>
                        <h2 style='color:#dc3545;'>Database Connection Failed</h2>
                        <p>Unable to connect to the RACIC Church Management database: " . htmlspecialchars($e->getMessage()) . "</p>
                        <p><small>Ensure MySQL is running and connection variables (<code>DB_HOST, DB_NAME, DB_USER, DB_PASS</code>) are properly configured.</small></p>
                     </div>");
            }
        }
        return self::$instance;
    }
}

function getDB(): PDO {
    return Database::getConnection();
}
