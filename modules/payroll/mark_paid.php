<?php
/**
 * HRMS - Mark Payroll as Paid (POST handler)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('process', 'payroll');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/modules/payroll/index.php');
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid CSRF token. Please try again.');
    header('Location: ' . BASE_URL . '/modules/payroll/index.php');
    exit;
}

$payrollId     = (int)($_POST['payroll_id'] ?? 0);
$paymentMethod = sanitize($_POST['payment_method'] ?? 'Bank Transfer');
$paymentDate   = $_POST['payment_date'] ?? date('Y-m-d');
$redirectMonth = (int)($_POST['redirect_month'] ?? (int)date('m'));
$redirectYear  = (int)($_POST['redirect_year']  ?? (int)date('Y'));

// Validate
if ($payrollId <= 0) {
    setFlash('error', 'Invalid payroll record.');
    header('Location: ' . BASE_URL . '/modules/payroll/index.php');
    exit;
}

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
    $paymentDate = date('Y-m-d');
}

// Ensure record exists and is Pending
$payroll = fetchOne(
    "SELECT p.*, u.id AS user_id,
            CONCAT(e.first_name,' ',e.last_name) AS emp_name
     FROM payroll p
     JOIN employees e ON p.employee_id = e.id
     LEFT JOIN users u ON u.employee_id = e.id
     WHERE p.id = ? AND p.payment_status = 'Pending'",
    'i', $payrollId
);

if (!$payroll) {
    setFlash('error', 'Payroll record not found or already processed.');
    header('Location: ' . BASE_URL . '/modules/payroll/index.php?month=' . $redirectMonth . '&year=' . $redirectYear);
    exit;
}

// Update payment status
$updated = execute(
    "UPDATE payroll
     SET payment_status = 'Paid',
         payment_date   = ?,
         payment_method = ?
     WHERE id = ? AND payment_status = 'Pending'",
    'ssi', $paymentDate, $paymentMethod, $payrollId
);

if ($updated) {
    $monthName = date('F', mktime(0, 0, 0, (int)$payroll['month'], 1));

    // Notify employee
    if (!empty($payroll['user_id'])) {
        createNotification(
            (int)$payroll['user_id'],
            'Salary Paid',
            'Your salary for ' . $monthName . ' ' . $payroll['year'] . ' has been paid via ' . $paymentMethod . '. Net Pay: ' . formatMoney((float)$payroll['net_salary']),
            'payroll',
            BASE_URL . '/modules/payroll/payslip.php?id=' . $payrollId
        );
    }

    setFlash('success', 'Payroll marked as Paid for ' . sanitize($payroll['emp_name']) . '.');
} else {
    setFlash('error', 'Failed to update payroll status. Please try again.');
}

header('Location: ' . BASE_URL . '/modules/payroll/index.php?month=' . $redirectMonth . '&year=' . $redirectYear);
exit;
