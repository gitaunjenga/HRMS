<?php
/**
 * HRMS - Delete Department (POST handler only)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Admin', 'HR Manager');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/modules/departments/index.php');
    exit;
}

// CSRF check
if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid security token. Please try again.');
    header('Location: ' . BASE_URL . '/modules/departments/index.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid department ID.');
    header('Location: ' . BASE_URL . '/modules/departments/index.php');
    exit;
}

// Fetch department
$dept = fetchOne("SELECT id, name FROM departments WHERE id = ?", 'i', $id);
if (!$dept) {
    setFlash('error', 'Department not found.');
    header('Location: ' . BASE_URL . '/modules/departments/index.php');
    exit;
}

// Check if any employees are assigned to this department
$empCount = (int)(fetchOne(
    "SELECT COUNT(*) AS c FROM employees WHERE department_id = ?",
    'i', $id
)['c'] ?? 0);

if ($empCount > 0) {
    setFlash(
        'error',
        'Cannot delete "' . $dept['name'] . '" — ' . $empCount . ' employee' .
        ($empCount !== 1 ? 's are' : ' is') .
        ' currently assigned to this department. Reassign or remove them first.'
    );
    header('Location: ' . BASE_URL . '/modules/departments/index.php');
    exit;
}

// Safe to delete
$result = execute("DELETE FROM departments WHERE id = ?", 'i', $id);

if ($result) {
    setFlash('success', 'Department "' . $dept['name'] . '" has been deleted successfully.');
} else {
    setFlash('error', 'Failed to delete department. Please try again.');
}

header('Location: ' . BASE_URL . '/modules/departments/index.php');
exit;
