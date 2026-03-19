<?php
/**
 * HRMS - Approve/Reject Overtime
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireCan('approve', 'overtime');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRF($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . '/modules/overtime/index.php');
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$action = sanitize($_POST['action'] ?? '');
$user   = currentUser();

$req = fetchOne(
    "SELECT ot.*, e.department_id FROM overtime_requests ot
     JOIN employees e ON ot.employee_id = e.id
     WHERE ot.id = ?",
    'i', $id
);

if (!$req || $req['status'] !== 'Pending') {
    setFlash('error', 'Request not found or already processed.');
    header('Location: ' . BASE_URL . '/modules/overtime/index.php');
    exit;
}

// HoD can only approve their dept's requests
if (hasRole('Head of Department')) {
    $myDept = myDeptId();
    if ($myDept !== (int)$req['department_id']) {
        setFlash('error', 'Not authorised.');
        header('Location: ' . BASE_URL . '/modules/overtime/index.php');
        exit;
    }
}

if ($action === 'approve') {
    execute(
        "UPDATE overtime_requests SET status='Approved', approved_by=?, approved_at=NOW() WHERE id=?",
        'ii', (int)$user['id'], $id
    );
    logAudit('approve', 'overtime', "Approved overtime request ID: {$id}");
    // Notify employee
    $empUser = fetchOne("SELECT u.id FROM users u WHERE u.employee_id = ?", 'i', $req['employee_id']);
    if ($empUser) {
        createNotification($empUser['id'], 'Overtime Approved',
            "Your overtime request for {$req['request_date']} has been approved.",
            'overtime', BASE_URL . '/modules/overtime/index.php');
    }
    setFlash('success', 'Overtime request approved.');
} elseif ($action === 'reject') {
    $reason = sanitize($_POST['rejection_reason'] ?? 'No reason provided.');
    execute(
        "UPDATE overtime_requests SET status='Rejected', approved_by=?, approved_at=NOW(), rejection_reason=? WHERE id=?",
        'isi', (int)$user['id'], $reason, $id
    );
    logAudit('reject', 'overtime', "Rejected overtime request ID: {$id}");
    $empUser = fetchOne("SELECT u.id FROM users u WHERE u.employee_id = ?", 'i', $req['employee_id']);
    if ($empUser) {
        createNotification($empUser['id'], 'Overtime Rejected',
            "Your overtime request for {$req['request_date']} was rejected.",
            'overtime', BASE_URL . '/modules/overtime/index.php');
    }
    setFlash('success', 'Overtime request rejected.');
} else {
    setFlash('error', 'Invalid action.');
}

header('Location: ' . BASE_URL . '/modules/overtime/index.php');
exit;
