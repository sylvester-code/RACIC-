<?php
// config/database.php - RACIC Church Management System Database Connection

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'racic_church_management');
define('DB_USER', 'root');
define('DB_PASS', '');
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

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Friendly error message in production
                die("<div style='font-family:sans-serif;padding:30px;text-align:center;'>
                        <h2 style='color:#dc3545;'>Database Connection Failed</h2>
                        <p>Unable to connect to the RACIC Church Management database: " . htmlspecialchars($e->getMessage()) . "</p>
                        <p><small>Ensure MySQL is running in XAMPP and the database <code>racic_church_management</code> exists.</small></p>
                     </div>");
            }
        }
        return self::$instance;
    }
}

function getDB(): PDO {
    return Database::getConnection();
}

