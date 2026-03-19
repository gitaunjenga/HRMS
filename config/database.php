<?php
/**
 * HRMS - Database Configuration
 * Modify the constants below to match your server settings
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change to your MySQL username
define('DB_PASS', '');            // Change to your MySQL password
define('DB_NAME', 'hrms_db');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', 'http://localhost/HRMS');   // Change to your server URL
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('PHOTO_DIR', __DIR__ . '/../uploads/photos/');
define('DOC_DIR', __DIR__ . '/../uploads/documents/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

/**
 * Create and return a MySQLi connection
 */
function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            error_log("Database connection failed: " . $conn->connect_error);
            die(json_encode(['error' => 'Database connection failed. Please check config/database.php']));
        }
        $conn->set_charset(DB_CHARSET);
    }
    return $conn;
}
