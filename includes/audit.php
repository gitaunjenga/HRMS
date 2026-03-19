<?php
/**
 * HRMS - Audit / Activity Log helper
 */

function logAudit(string $action, string $module, string $description = ''): void {
    // Avoid recursive inclusion issues — functions.php should already be loaded
    $user   = currentUser();
    $userId = $user['id'] ?? null;
    $role   = $user['role'] ?? null;
    $ip     = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua     = isset($_SERVER['HTTP_USER_AGENT'])
              ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500)
              : null;

    execute(
        "INSERT INTO audit_log (user_id, role, action, module, description, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        'issssss',
        $userId, $role, $action, $module, $description, $ip, $ua
    );
}
