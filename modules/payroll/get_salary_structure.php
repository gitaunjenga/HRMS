<?php
/**
 * HRMS - AJAX: Get Salary Structure for Employee
 */
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json');

// AJAX endpoint — return JSON errors instead of redirecting
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']); exit;
}
if (!can('process', 'payroll') && !can('view', 'payroll')) {
    echo json_encode(['success' => false, 'message' => 'Access denied.']); exit;
}

$employeeId = (int)($_GET['employee_id'] ?? 0);
if ($employeeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID.']);
    exit;
}

$structure = fetchOne(
    "SELECT * FROM salary_structures WHERE employee_id = ? ORDER BY effective_date DESC LIMIT 1",
    'i', $employeeId
);

if ($structure) {
    echo json_encode([
        'success'             => true,
        'basic_salary'        => (float)$structure['basic_salary'],
        'house_allowance'     => (float)$structure['house_allowance'],
        'transport_allowance' => (float)$structure['transport_allowance'],
        'medical_allowance'   => (float)$structure['medical_allowance'],
        'other_allowances'    => (float)$structure['other_allowances'],
        'tax_deduction'       => (float)$structure['tax_deduction'],
        'provident_fund'      => (float)$structure['provident_fund'],
        'insurance'           => (float)$structure['insurance'],
        'other_deductions'    => (float)$structure['other_deductions'],
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No salary structure found for this employee.']);
}
