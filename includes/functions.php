<?php
/**
 * HRMS - Global Helper Functions
 */

require_once __DIR__ . '/../config/database.php';

// ─── Session ─────────────────────────────────────────────────────────────────

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    // Force password change if flagged
    if (!empty($_SESSION['must_change_password'])) {
        $forceUrl = BASE_URL . '/modules/settings/force-change-password.php';
        $currentUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($currentUri, 'force-change-password.php') === false) {
            header('Location: ' . $forceUrl);
            exit;
        }
    }
}

function currentUser(): array {
    startSession();
    return $_SESSION['user'] ?? [];
}

function hasRole(string ...$roles): bool {
    $user = currentUser();
    return in_array($user['role'] ?? '', $roles, true);
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!hasRole(...$roles)) {
        setFlash('error', 'You do not have permission to access that page.');
        header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
        exit;
    }
}

// ─── Permission Matrix ────────────────────────────────────────────────────────

/**
 * Check if current user can perform an action on a module.
 *
 * Actions: view, create, edit, delete, approve, reject, process, export,
 *          view_own, upload, manage
 */
function can(string $action, string $module): bool {
    static $cache = null;
    if ($cache === null) {
        $rows  = fetchAll("SELECT role, module, action FROM role_permissions WHERE is_granted = 1");
        $cache = [];
        foreach ($rows as $r) {
            $cache[$r['role']][$r['module']][] = $r['action'];
        }
    }
    $role = currentUser()['role'] ?? '';
    return in_array($action, $cache[$role][$module] ?? [], true);
}

function requireCan(string $action, string $module): void {
    requireLogin();
    if (!can($action, $module)) {
        setFlash('error', 'You do not have permission to perform that action.');
        header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
        exit;
    }
}

// ─── Head of Department Scope ─────────────────────────────────────────────────

/** Returns the department ID for the current Head of Department, or null. */
function myDeptId(): ?int {
    $user = currentUser();
    if ($user['role'] !== 'Head of Department') return null;
    return isset($user['dept_id']) ? (int)$user['dept_id'] : null;
}

/** Restrict a query's employee scope to current DM's department if applicable. */
function deptScope(): array {
    $deptId = myDeptId();
    if ($deptId !== null) {
        return ['AND e.department_id = ?', 'i', $deptId];
    }
    return ['', '', null];
}

// ─── Flash Messages ───────────────────────────────────────────────────────────

function setFlash(string $type, string $message): void {
    startSession();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Security ────────────────────────────────────────────────────────────────

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateCSRF(): string {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    startSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRF() . '">';
}

// ─── Database Helpers ─────────────────────────────────────────────────────────

function fetchAll(string $sql, string $types = '', ...$params): array {
    $db = getDB();
    $stmt = $db->prepare($sql);
    if (!$stmt) return [];
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function fetchOne(string $sql, string $types = '', ...$params): ?array {
    $rows = fetchAll($sql, $types, ...$params);
    return $rows[0] ?? null;
}

function execute(string $sql, string $types = '', ...$params): bool|int {
    $db = getDB();
    $stmt = $db->prepare($sql);
    if (!$stmt) return false;
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $ok = $stmt->execute();
    return $ok ? ($stmt->affected_rows > 0 ? $db->insert_id ?: true : false) : false;
}

// ─── Formatting ───────────────────────────────────────────────────────────────

function formatDate(?string $date): string {
    if (!$date) return '—';
    return date('M d, Y', strtotime($date));
}

function formatMoney(float $amount): string {
    return 'KES ' . number_format($amount, 2);
}

function timeAgo(string $datetime): string {
    $time = time() - strtotime($datetime);
    $units = [
        31536000 => 'year', 2592000 => 'month', 604800 => 'week',
        86400 => 'day',     3600 => 'hour',     60 => 'minute',
    ];
    foreach ($units as $seconds => $label) {
        if ($time >= $seconds) {
            $count = floor($time / $seconds);
            return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function statusBadge(string $status): string {
    $map = [
        'Active'    => 'bg-green-100 text-green-800',
        'Inactive'  => 'bg-gray-100 text-gray-800',
        'Terminated'=> 'bg-red-100 text-red-800',
        'On Leave'  => 'bg-yellow-100 text-yellow-800',
        'Pending'                        => 'bg-yellow-100 text-yellow-800',
        'Pending Department Approval'    => 'bg-amber-100 text-amber-800',
        'Pending HR Approval'            => 'bg-blue-100 text-blue-800',
        'Approved'                       => 'bg-green-100 text-green-800',
        'Rejected'                       => 'bg-red-100 text-red-800',
        'Rejected by Head of Department' => 'bg-orange-100 text-orange-800',
        'Rejected by HR'                 => 'bg-red-100 text-red-800',
        'Cancelled'                      => 'bg-gray-100 text-gray-800',
        'Paid'      => 'bg-green-100 text-green-800',
        'Open'      => 'bg-blue-100 text-blue-800',
        'Closed'    => 'bg-gray-100 text-gray-800',
        'Present'   => 'bg-green-100 text-green-800',
        'Absent'    => 'bg-red-100 text-red-800',
        'Late'      => 'bg-orange-100 text-orange-800',
        'Half Day'  => 'bg-yellow-100 text-yellow-800',
        'Hired'     => 'bg-green-100 text-green-800',
        'Applied'   => 'bg-blue-100 text-blue-800',
        'Interview' => 'bg-purple-100 text-purple-800',
    ];
    $class = $map[$status] ?? 'bg-gray-100 text-gray-800';
    return "<span class=\"inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$class}\">{$status}</span>";
}

function avatarUrl(?string $photo): string {
    if ($photo && file_exists(PHOTO_DIR . $photo)) {
        return BASE_URL . '/uploads/photos/' . rawurlencode($photo);
    }
    return BASE_URL . '/assets/images/default-avatar.svg';
}

// ─── Notifications ────────────────────────────────────────────────────────────

function createNotification(int $userId, string $title, string $message, string $type = 'general', string $link = ''): void {
    execute(
        "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)",
        'issss', $userId, $title, $message, $type, $link
    );
}

function unreadNotificationCount(): int {
    $user = currentUser();
    if (!$user) return 0;
    $row = fetchOne("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0", 'i', $user['id']);
    return (int)($row['cnt'] ?? 0);
}

// ─── File Upload ──────────────────────────────────────────────────────────────

function uploadFile(array $file, string $dir, array $allowedTypes): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload_max_filesize limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form MAX_FILE_SIZE limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by a PHP extension.',
        ];
        $msg = $uploadErrors[$file['error']] ?? 'Upload error code: ' . $file['error'];
        return ['success' => false, 'message' => $msg];
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large (max 5MB)'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes, true)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }
    // Trim both Unix and Windows trailing slashes — safe cross-platform
    $dir = rtrim($dir, '/\\');
    // Auto-create directory if missing (e.g. fresh Windows install)
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['success' => false, 'message' => 'Upload directory does not exist and could not be created.'];
    }
    $filename = uniqid('', true) . '.' . $ext;
    $dest     = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'message' => 'Failed to save uploaded file. Check directory permissions.'];
    }
    return ['success' => true, 'filename' => $filename];
}

// ─── Pagination ───────────────────────────────────────────────────────────────

function paginate(int $total, int $page, int $perPage = 15): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => $totalPages, 'offset' => $offset];
}

function renderPagination(array $pag, string $baseUrl): string {
    if ($pag['totalPages'] <= 1) return '';
    $html = '<div class="flex items-center justify-between px-4 py-3 bg-white border-t border-gray-200 sm:px-6">';
    $html .= '<div class="text-sm text-gray-700">Showing <span class="font-medium">' .
        (($pag['page'] - 1) * $pag['perPage'] + 1) . '</span> to <span class="font-medium">' .
        min($pag['page'] * $pag['perPage'], $pag['total']) .
        '</span> of <span class="font-medium">' . $pag['total'] . '</span> results</div>';
    $html .= '<div class="flex space-x-1">';
    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
    for ($i = 1; $i <= $pag['totalPages']; $i++) {
        $active = $i === $pag['page'];
        $cls = $active
            ? 'px-3 py-1 rounded text-sm font-medium bg-indigo-600 text-white'
            : 'px-3 py-1 rounded text-sm font-medium bg-white text-gray-700 border hover:bg-gray-50';
        $html .= "<a href=\"{$baseUrl}{$sep}page={$i}\" class=\"{$cls}\">{$i}</a>";
        $sep = '&';
    }
    $html .= '</div></div>';
    return $html;
}

// ─── Employee ID Generator ────────────────────────────────────────────────────

function generateEmployeeId(): string {
    $row = fetchOne("SELECT employee_id FROM employees ORDER BY id DESC LIMIT 1");
    if (!$row) return 'EMP001';
    $num = (int)substr($row['employee_id'], 3) + 1;
    return 'EMP' . str_pad($num, 3, '0', STR_PAD_LEFT);
}
