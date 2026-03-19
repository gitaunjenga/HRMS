<?php
/**
 * HRMS - Terminate Employee (soft delete)
 * Accepts POST only. Sets employment_status = 'Terminated'.
 * Admin role required.
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('delete', 'employees');

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'Invalid request method.');
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
    exit;
}

// CSRF check
if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid security token. Please try again.');
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid employee ID.');
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
    exit;
}

// Verify employee exists
$employee = fetchOne("SELECT id, first_name, last_name, employment_status FROM employees WHERE id = ?", 'i', $id);
if (!$employee) {
    setFlash('error', 'Employee not found.');
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
    exit;
}

// Prevent terminating an already-terminated employee
if ($employee['employment_status'] === 'Terminated') {
    setFlash('error', 'This employee has already been terminated.');
    header('Location: ' . BASE_URL . '/modules/employees/view.php?id=' . $id);
    exit;
}

// Soft-delete: update employment_status to Terminated
$ok = execute(
    "UPDATE employees SET employment_status = 'Terminated', termination_date = CURDATE() WHERE id = ?",
    'i', $id
);

// Deactivate the associated user account
execute("UPDATE users SET is_active = 0 WHERE employee_id = ?", 'i', $id);

if ($ok !== false) {
    $name = sanitize($employee['first_name']) . ' ' . sanitize($employee['last_name']);
    setFlash('success', "Employee \"{$name}\" has been terminated successfully.");
} else {
    setFlash('error', 'Failed to terminate the employee. Please try again.');
}

// Determine redirect: if the action came from the view page, go back there; otherwise list
$referer = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($referer, 'view.php') !== false) {
    header('Location: ' . BASE_URL . '/modules/employees/view.php?id=' . $id);
} else {
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
}
exit;
