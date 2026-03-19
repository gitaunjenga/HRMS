<?php
/**
 * HRMS - Database Configuration
 * Modify the constants below to match your server settings
 */

// ── Database ──────────────────────────────────────────────────────────────────
// Use 127.0.0.1 instead of 'localhost' — works on both Windows XAMPP and Linux/macOS.
// On Windows, 'localhost' may try a named pipe; 127.0.0.1 forces TCP and always works.
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');        // Change to your MySQL username
define('DB_PASS', '');            // Change to your MySQL password (XAMPP default: empty)
define('DB_NAME', 'hrms_db');
define('DB_CHARSET', 'utf8mb4');

// ── Application URL ───────────────────────────────────────────────────────────
// Set this to where HRMS lives on your server.
// Windows XAMPP example : http://localhost/HRMS
// Custom domain example  : http://hrms.local
define('BASE_URL', 'http://localhost/HRMS');

// ── Upload Paths (cross-platform: PHP handles forward slashes on Windows too) ─
define('UPLOAD_DIR',    __DIR__ . '/../uploads/');
define('PHOTO_DIR',     __DIR__ . '/../uploads/photos/');
define('DOC_DIR',       __DIR__ . '/../uploads/documents/');
define('LEAVE_DIR',     __DIR__ . '/../uploads/leave_attachments/');
define('PAYSLIP_DIR',   __DIR__ . '/../uploads/payslips/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB

// ── Auto-create upload directories (needed on a fresh Windows install) ────────
foreach ([UPLOAD_DIR, PHOTO_DIR, DOC_DIR, LEAVE_DIR, PAYSLIP_DIR] as $_dir) {
    if (!is_dir($_dir)) {
        mkdir($_dir, 0755, true);
    }
}
unset($_dir);

// ── Database Connection ───────────────────────────────────────────────────────
function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_OFF); // handle errors manually
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3306);
        if ($conn->connect_error) {
            $msg = 'Database connection failed: ' . $conn->connect_error
                 . ' — Check DB_HOST/DB_USER/DB_PASS in config/database.php';
            error_log($msg);
            die('<div style="font-family:sans-serif;padding:2rem;color:#b91c1c">'
                . '<h2>Database Connection Error</h2><p>' . htmlspecialchars($msg) . '</p></div>');
        }
        $conn->set_charset(DB_CHARSET);
    }
    return $conn;
}
