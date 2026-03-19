<?php
/**
 * HRMS - Leave Approval / Rejection Handler (POST only)
 * Two-level workflow: Head of Department → HR Manager
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('approve', 'leaves');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/modules/leaves/index.php');
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid security token. Please try again.');
    header('Location: ' . BASE_URL . '/modules/leaves/index.php');
    exit;
}

$currentUser = currentUser();
$actorId     = (int)($currentUser['id'] ?? 0);
$role        = $currentUser['role'] ?? '';
$leaveId     = (int)($_POST['leave_id']  ?? 0);
$action      = sanitize($_POST['action'] ?? '');
$comment     = sanitize($_POST['comment'] ?? '');

if ($leaveId <= 0 || !in_array($action, ['approve', 'reject'])) {
    setFlash('error', 'Invalid request parameters.');
    header('Location: ' . BASE_URL . '/modules/leaves/index.php');
    exit;
}

// Fetch leave request with related data
$leave = fetchOne(
    "SELECT lr.*, lt.name AS leave_type_name,
            CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.department_id AS emp_dept_id,
            u.id AS emp_user_id
     FROM leave_requests lr
     JOIN employees   e  ON lr.employee_id   = e.id
     JOIN leave_types lt ON lr.leave_type_id = lt.id
     LEFT JOIN users  u  ON u.employee_id    = e.id
     WHERE lr.id = ?",
    'i', $leaveId
);

if (!$leave) {
    setFlash('error', 'Leave request not found.');
    header('Location: ' . BASE_URL . '/modules/leaves/index.php');
    exit;
}

$empName       = sanitize($leave['emp_name']);
$leaveTypeName = $leave['leave_type_name'] ?? 'Leave';
$startFmt      = date('M j, Y', strtotime($leave['start_date']));
$empUserId     = (int)($leave['emp_user_id'] ?? 0);
$viewLink      = BASE_URL . '/modules/leaves/view.php?id=' . $leaveId;
$now           = date('Y-m-d H:i:s');

// ── Head of Department Stage ──────────────────────────────────────────────────
if ($role === 'Head of Department') {

    if ($leave['status'] !== 'Pending Department Approval') {
        setFlash('error', 'This request is not awaiting department approval.');
        header('Location: ' . BASE_URL . '/modules/leaves/view.php?id=' . $leaveId);
        exit;
    }

    // Verify the request belongs to this DM's department
    $myDept = myDeptId();
    if ($myDept === null || (int)$leave['emp_dept_id'] !== $myDept) {
        setFlash('error', 'Access denied. This leave request does not belong to your department.');
        header('Location: ' . BASE_URL . '/modules/leaves/index.php');
        exit;
    }

    if ($action === 'approve') {
        // Forward to HR
        execute(
            "UPDATE leave_requests
             SET status = 'Pending HR Approval',
                 dm_action_by = ?, dm_action_at = ?, dm_comment = ?
             WHERE id = ? AND status = 'Pending Department Approval'",
            'issi',
            $actorId, $now, $comment ?: null, $leaveId
        );

        // Notify all HR Managers
        $hrManagers = fetchAll(
            "SELECT id FROM users WHERE role = 'HR Manager' AND is_active = 1"
        );
        foreach ($hrManagers as $hr) {
            createNotification(
                (int)$hr['id'],
                'Leave Request Awaiting HR Approval',
                $empName . '\'s ' . $leaveTypeName . ' request for ' . (int)$leave['total_days'] .
                ' day(s) starting ' . $startFmt . ' has been approved by the Head of Department and requires your review.',
                'leave', $viewLink
            );
        }

        setFlash('success', 'Leave request for ' . $empName . ' approved and forwarded to HR for final approval.');

    } else {
        // Reject — stop the process
        execute(
            "UPDATE leave_requests
             SET status = 'Rejected by Head of Department',
                 dm_action_by = ?, dm_action_at = ?, dm_comment = ?
             WHERE id = ? AND status = 'Pending Department Approval'",
            'issi',
            $actorId, $now, $comment ?: null, $leaveId
        );

        // Notify employee
        if ($empUserId > 0) {
            createNotification(
                $empUserId,
                'Leave Request Rejected by Head of Department',
                'Your ' . $leaveTypeName . ' request for ' . (int)$leave['total_days'] .
                ' day(s) starting ' . $startFmt . ' has been rejected by your Head of Department.' .
                ($comment ? ' Reason: ' . $comment : ''),
                'leave', $viewLink
            );
        }

        setFlash('success', 'Leave request for ' . $empName . ' has been rejected.');
    }

// ── HR Manager Stage ──────────────────────────────────────────────────────────
} elseif ($role === 'HR Manager') {

    if ($leave['status'] !== 'Pending HR Approval') {
        setFlash('error', 'This request is not awaiting HR approval.');
        header('Location: ' . BASE_URL . '/modules/leaves/view.php?id=' . $leaveId);
        exit;
    }

    if ($action === 'approve') {
        execute(
            "UPDATE leave_requests
             SET status = 'Approved',
                 hr_action_by = ?, hr_action_at = ?, hr_comment = ?
             WHERE id = ? AND status = 'Pending HR Approval'",
            'issi',
            $actorId, $now, $comment ?: null, $leaveId
        );

        // Deduct from leave_balances
        $leaveYear = (int)date('Y', strtotime($leave['start_date']));
        $balance   = fetchOne(
            "SELECT id, remaining, used FROM leave_balances
             WHERE employee_id = ? AND leave_type_id = ? AND year = ?",
            'iii', (int)$leave['employee_id'], (int)$leave['leave_type_id'], $leaveYear
        );
        if ($balance) {
            execute(
                "UPDATE leave_balances SET used = ?, remaining = ? WHERE id = ?",
                'iii',
                (int)$balance['used'] + (int)$leave['total_days'],
                max(0, (int)$balance['remaining'] - (int)$leave['total_days']),
                (int)$balance['id']
            );
        }

        // Notify employee
        if ($empUserId > 0) {
            createNotification(
                $empUserId,
                'Leave Request Approved',
                'Your ' . $leaveTypeName . ' request for ' . (int)$leave['total_days'] .
                ' day(s) starting ' . $startFmt . ' has been fully approved.' .
                ($comment ? ' Note: ' . $comment : ''),
                'leave', $viewLink
            );
        }

        setFlash('success', 'Leave request for ' . $empName . ' has been fully approved.');

    } else {
        execute(
            "UPDATE leave_requests
             SET status = 'Rejected by HR',
                 hr_action_by = ?, hr_action_at = ?, hr_comment = ?
             WHERE id = ? AND status = 'Pending HR Approval'",
            'issi',
            $actorId, $now, $comment ?: null, $leaveId
        );

        // Notify employee
        if ($empUserId > 0) {
            createNotification(
                $empUserId,
                'Leave Request Rejected by HR',
                'Your ' . $leaveTypeName . ' request for ' . (int)$leave['total_days'] .
                ' day(s) starting ' . $startFmt . ' has been rejected by HR.' .
                ($comment ? ' Reason: ' . $comment : ''),
                'leave', $viewLink
            );
        }

        setFlash('success', 'Leave request for ' . $empName . ' has been rejected by HR.');
    }

} else {
    setFlash('error', 'You do not have permission to approve or reject leave requests.');
}

header('Location: ' . BASE_URL . '/modules/leaves/index.php');
exit;
