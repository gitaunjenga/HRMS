<?php
/**
 * HRMS - Reports Hub
 * Four report types: Employee, Attendance, Leave, Payroll
 * Each supports HTML view + CSV export (?report=X&export=csv)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('view', 'reports');

$user = currentUser();
$role = $user['role'] ?? '';

// ── Active tab / report type ──────────────────────────────────────────────────
$reportType = sanitize($_GET['report'] ?? 'employees');
$validTypes = ['employees', 'attendance', 'leave', 'payroll'];
if (!in_array($reportType, $validTypes, true)) {
    $reportType = 'employees';
}

$export = ($_GET['export'] ?? '') === 'csv';

// Block CSV export for roles without export permission
if ($export && !can('export', 'reports')) {
    setFlash('error', 'You do not have permission to export reports.');
    header('Location: ' . BASE_URL . '/modules/reports/index.php?report=' . $reportType);
    exit;
}

$year   = (int)($_GET['year']  ?? date('Y'));
$month  = (int)($_GET['month'] ?? date('n'));

// ── Helper: output CSV and exit ───────────────────────────────────────────────
function outputCSV(string $filename, array $headers, array $rows): void
{
    if (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $fp = fopen('php://output', 'wb');
    // UTF-8 BOM for Excel compatibility
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers);
    foreach ($rows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// REPORT A: EMPLOYEES
// ─────────────────────────────────────────────────────────────────────────────
$empDeptFilter    = (int)($_GET['dept']   ?? 0);
$empStatusFilter  = sanitize($_GET['status'] ?? '');
$empTypeFilter    = sanitize($_GET['emp_type'] ?? '');

$empWhere  = ['1=1'];
$empTypes  = '';
$empParams = [];

if ($empDeptFilter > 0) {
    $empWhere[]  = 'e.department_id = ?';
    $empTypes   .= 'i';
    $empParams[] = $empDeptFilter;
}
if ($empStatusFilter !== '') {
    $empWhere[]  = 'e.employment_status = ?';
    $empTypes   .= 's';
    $empParams[] = $empStatusFilter;
}
if ($empTypeFilter !== '') {
    $empWhere[]  = 'e.employment_type = ?';
    $empTypes   .= 's';
    $empParams[] = $empTypeFilter;
}

$empWhereSQL = implode(' AND ', $empWhere);

$employeeRows = [];
if ($reportType === 'employees') {
    $employeeRows = fetchAll(
        "SELECT e.employee_id, e.first_name, e.last_name, e.email, e.phone,
                e.position, e.employment_type, e.employment_status, e.hire_date, e.salary,
                d.name AS department_name
         FROM employees e
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE {$empWhereSQL}
         ORDER BY e.first_name, e.last_name",
        $empTypes,
        ...$empParams
    );

    if ($export) {
        $csvHeaders = ['Employee ID', 'First Name', 'Last Name', 'Email', 'Phone',
                       'Department', 'Position', 'Type', 'Status', 'Hire Date', 'Salary'];
        $csvRows = array_map(fn($r) => [
            $r['employee_id'], $r['first_name'], $r['last_name'], $r['email'], $r['phone'] ?? '',
            $r['department_name'] ?? 'N/A', $r['position'] ?? 'N/A',
            $r['employment_type'], $r['employment_status'],
            $r['hire_date'] ?? '', number_format((float)$r['salary'], 2),
        ], $employeeRows);
        outputCSV('employees_report_' . date('Ymd') . '.csv', $csvHeaders, $csvRows);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// REPORT B: ATTENDANCE
// ─────────────────────────────────────────────────────────────────────────────
$attEmpFilter = (int)($_GET['att_emp'] ?? 0);
$attMonth     = (int)($_GET['att_month'] ?? date('n'));
$attYear      = (int)($_GET['att_year']  ?? date('Y'));

$attRows = [];
if ($reportType === 'attendance') {
    $attWhere  = ['MONTH(a.date) = ? AND YEAR(a.date) = ?'];
    $attTypes  = 'ii';
    $attParams = [$attMonth, $attYear];

    if ($attEmpFilter > 0) {
        $attWhere[]  = 'e.id = ?';
        $attTypes   .= 'i';
        $attParams[] = $attEmpFilter;
    }

    $attWhereSQL = implode(' AND ', $attWhere);

    $attRows = fetchAll(
        "SELECT e.employee_id, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                d.name AS department,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END)  AS present_days,
                SUM(CASE WHEN a.status = 'Absent'  THEN 1 ELSE 0 END)  AS absent_days,
                SUM(CASE WHEN a.status = 'Late'    THEN 1 ELSE 0 END)  AS late_days,
                SUM(CASE WHEN a.status = 'Half Day' THEN 1 ELSE 0 END) AS half_days,
                ROUND(SUM(COALESCE(a.total_hours, 0)), 2)              AS total_hours
         FROM attendance a
         JOIN employees e ON e.id = a.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE {$attWhereSQL}
         GROUP BY e.id, e.employee_id, full_name, d.name
         ORDER BY full_name",
        $attTypes,
        ...$attParams
    );

    if ($export) {
        $csvHeaders = ['Employee ID', 'Name', 'Department', 'Present', 'Absent', 'Late', 'Half Day', 'Total Hours'];
        $csvRows = array_map(fn($r) => [
            $r['employee_id'], $r['full_name'], $r['department'] ?? 'N/A',
            $r['present_days'], $r['absent_days'], $r['late_days'], $r['half_days'], $r['total_hours'],
        ], $attRows);
        $monthName = date('F', mktime(0, 0, 0, $attMonth, 1));
        outputCSV("attendance_{$monthName}_{$attYear}.csv", $csvHeaders, $csvRows);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// REPORT C: LEAVE
// ─────────────────────────────────────────────────────────────────────────────
$leaveStatusFilter = sanitize($_GET['leave_status'] ?? '');
$leaveTypeFilter   = (int)($_GET['leave_type'] ?? 0);
$leaveYear         = (int)($_GET['leave_year'] ?? date('Y'));

$leaveTypes = fetchAll("SELECT id, name FROM leave_types WHERE is_active = 1 ORDER BY name");

$leaveRows = [];
if ($reportType === 'leave') {
    $leaveWhere  = ['YEAR(lr.start_date) = ?'];
    $leaveTypes2 = 'i';
    $leaveParams = [$leaveYear];

    if ($leaveStatusFilter !== '') {
        $leaveWhere[]  = 'lr.status = ?';
        $leaveTypes2  .= 's';
        $leaveParams[] = $leaveStatusFilter;
    }
    if ($leaveTypeFilter > 0) {
        $leaveWhere[]  = 'lr.leave_type_id = ?';
        $leaveTypes2  .= 'i';
        $leaveParams[] = $leaveTypeFilter;
    }

    $leaveWhereSQL = implode(' AND ', $leaveWhere);

    $leaveRows = fetchAll(
        "SELECT e.employee_id, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                d.name AS department,
                COUNT(lr.id)                                            AS total_requests,
                SUM(CASE WHEN lr.status = 'Approved'  THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN lr.status = 'Rejected'  THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN lr.status = 'Pending'   THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN lr.status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN lr.status = 'Approved' THEN lr.total_days ELSE 0 END) AS total_days_taken
         FROM leave_requests lr
         JOIN employees e ON e.id = lr.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE {$leaveWhereSQL}
         GROUP BY e.id, e.employee_id, full_name, d.name
         ORDER BY full_name",
        $leaveTypes2,
        ...$leaveParams
    );

    if ($export) {
        $csvHeaders = ['Employee ID', 'Name', 'Department', 'Total Requests',
                       'Approved', 'Rejected', 'Pending', 'Cancelled', 'Days Taken'];
        $csvRows = array_map(fn($r) => [
            $r['employee_id'], $r['full_name'], $r['department'] ?? 'N/A',
            $r['total_requests'], $r['approved'], $r['rejected'],
            $r['pending'], $r['cancelled'], $r['total_days_taken'],
        ], $leaveRows);
        outputCSV("leave_report_{$leaveYear}.csv", $csvHeaders, $csvRows);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// REPORT D: PAYROLL
// ─────────────────────────────────────────────────────────────────────────────
$payMonth = (int)($_GET['pay_month'] ?? date('n'));
$payYear  = (int)($_GET['pay_year']  ?? date('Y'));

$payRows     = [];
$payTotals   = [];
if ($reportType === 'payroll') {
    $payRows = fetchAll(
        "SELECT e.employee_id, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                d.name AS department, e.position,
                p.basic_salary, p.total_allowances, p.gross_salary,
                p.total_deductions, p.net_salary, p.payment_status,
                p.days_worked, p.days_absent
         FROM payroll p
         JOIN employees e ON e.id = p.employee_id
         LEFT JOIN departments d ON d.id = e.department_id
         WHERE p.month = ? AND p.year = ?
         ORDER BY full_name",
        'ii', $payMonth, $payYear
    );

    // Column totals
    $payTotals = [
        'basic'       => array_sum(array_column($payRows, 'basic_salary')),
        'allowances'  => array_sum(array_column($payRows, 'total_allowances')),
        'gross'       => array_sum(array_column($payRows, 'gross_salary')),
        'deductions'  => array_sum(array_column($payRows, 'total_deductions')),
        'net'         => array_sum(array_column($payRows, 'net_salary')),
    ];

    if ($export) {
        $csvHeaders = ['Employee ID', 'Name', 'Department', 'Position',
                       'Basic Salary', 'Allowances', 'Gross Salary',
                       'Deductions', 'Net Salary', 'Days Worked', 'Days Absent', 'Status'];
        $csvRows = array_map(fn($r) => [
            $r['employee_id'], $r['full_name'], $r['department'] ?? 'N/A', $r['position'] ?? 'N/A',
            number_format((float)$r['basic_salary'], 2),
            number_format((float)$r['total_allowances'], 2),
            number_format((float)$r['gross_salary'], 2),
            number_format((float)$r['total_deductions'], 2),
            number_format((float)$r['net_salary'], 2),
            $r['days_worked'] ?? 0, $r['days_absent'] ?? 0, $r['payment_status'],
        ], $payRows);
        // Append totals row
        $csvRows[] = ['', 'TOTALS', '', '',
            number_format($payTotals['basic'], 2),
            number_format($payTotals['allowances'], 2),
            number_format($payTotals['gross'], 2),
            number_format($payTotals['deductions'], 2),
            number_format($payTotals['net'], 2),
            '', '', ''];
        $monthName = date('F', mktime(0, 0, 0, $payMonth, 1));
        outputCSV("payroll_{$monthName}_{$payYear}.csv", $csvHeaders, $csvRows);
    }
}

// ── Shared data for filters ────────────────────────────────────────────────────
$departments   = fetchAll("SELECT id, name FROM departments ORDER BY name");
$allEmployees  = fetchAll("SELECT id, employee_id, CONCAT(first_name,' ',last_name) AS full_name FROM employees ORDER BY first_name");

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
$yearRange = range(date('Y') - 3, date('Y') + 1);

$pageTitle = 'Reports';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <!-- Page header -->
  <div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900">Reports</h2>
    <p class="text-sm text-gray-500 mt-1">
      Generate and review reports across the HRMS system
      <?php if (!can('export', 'reports')): ?>
        <span class="ml-2 text-xs text-amber-600 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full">View only — CSV export not available for your role</span>
      <?php endif; ?>
    </p>
  </div>

  <!-- Tab bar -->
  <div class="flex flex-wrap gap-1 bg-white rounded-xl shadow-sm border border-gray-200 p-1 mb-6 w-fit">
    <?php
    $tabs = [
        'employees'  => ['label' => 'Employees',  'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
        'attendance' => ['label' => 'Attendance', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
        'leave'      => ['label' => 'Leave',      'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'],
        'payroll'    => ['label' => 'Payroll',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
    ];
    foreach ($tabs as $key => $tab): ?>
      <a href="?report=<?= $key ?>"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
                <?= $reportType === $key ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' ?>">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <?= $tab['icon'] ?>
        </svg>
        <?= $tab['label'] ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       REPORT A: EMPLOYEES
  ══════════════════════════════════════════════════════════════════════════════ -->
  <?php if ($reportType === 'employees'): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5">
      <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="report" value="employees">

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Department</label>
          <select name="dept"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[170px]">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>" <?= $empDeptFilter === (int)$d['id'] ? 'selected' : '' ?>>
                <?= sanitize($d['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
          <select name="status"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[140px]">
            <option value="">All Statuses</option>
            <?php foreach (['Active', 'Inactive', 'On Leave', 'Terminated'] as $s): ?>
              <option value="<?= $s ?>" <?= $empStatusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Employment Type</label>
          <select name="emp_type"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[150px]">
            <option value="">All Types</option>
            <?php foreach (['Full-Time', 'Part-Time', 'Contract', 'Internship'] as $t): ?>
              <option value="<?= $t ?>" <?= $empTypeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Filter
        </button>
        <a href="?report=employees"
           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Reset
        </a>

        <!-- CSV Export (HR Manager only) -->
        <?php if (can('export', 'reports')): ?>
          <a href="?report=employees&export=csv&dept=<?= $empDeptFilter ?>&status=<?= urlencode($empStatusFilter) ?>&emp_type=<?= urlencode($empTypeFilter) ?>"
             class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors ml-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Employee Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
        <p class="text-sm font-semibold text-gray-700">
          <?= count($employeeRows) ?> employee<?= count($employeeRows) !== 1 ? 's' : '' ?> found
        </p>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">ID</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Name</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Department</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Position</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Type</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Status</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Hire Date</th>
              <th class="text-right px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Salary</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($employeeRows)): ?>
              <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">No employees match the selected filters.</td></tr>
            <?php else: ?>
              <?php foreach ($employeeRows as $r): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <span class="font-mono text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded">
                      <?= sanitize($r['employee_id']) ?>
                    </span>
                  </td>
                  <td class="px-5 py-3 font-medium text-gray-900">
                    <?= sanitize($r['first_name']) ?> <?= sanitize($r['last_name']) ?>
                    <div class="text-xs text-gray-400"><?= sanitize($r['email']) ?></div>
                  </td>
                  <td class="px-5 py-3 text-gray-600"><?= $r['department_name'] ? sanitize($r['department_name']) : '—' ?></td>
                  <td class="px-5 py-3 text-gray-600"><?= $r['position'] ? sanitize($r['position']) : '—' ?></td>
                  <td class="px-5 py-3 text-gray-600"><?= sanitize($r['employment_type']) ?></td>
                  <td class="px-5 py-3"><?= statusBadge($r['employment_status']) ?></td>
                  <td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?= formatDate($r['hire_date']) ?></td>
                  <td class="px-5 py-3 text-right font-medium text-gray-800"><?= formatMoney((float)$r['salary']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       REPORT B: ATTENDANCE
  ══════════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($reportType === 'attendance'): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5">
      <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="report" value="attendance">

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Employee</label>
          <select name="att_emp"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[200px]">
            <option value="">All Employees</option>
            <?php foreach ($allEmployees as $emp): ?>
              <option value="<?= $emp['id'] ?>" <?= $attEmpFilter === (int)$emp['id'] ? 'selected' : '' ?>>
                <?= sanitize($emp['full_name']) ?> (<?= sanitize($emp['employee_id']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Month</label>
          <select name="att_month"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[130px]">
            <?php foreach ($months as $num => $name): ?>
              <option value="<?= $num ?>" <?= $attMonth === $num ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Year</label>
          <select name="att_year"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[100px]">
            <?php foreach ($yearRange as $y): ?>
              <option value="<?= $y ?>" <?= $attYear === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Filter
        </button>
        <a href="?report=attendance"
           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Reset
        </a>

        <?php if (can('export', 'reports')): ?>
          <a href="?report=attendance&export=csv&att_emp=<?= $attEmpFilter ?>&att_month=<?= $attMonth ?>&att_year=<?= $attYear ?>"
             class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors ml-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
          </a>
        <?php endif; ?>
      </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100">
        <p class="text-sm font-semibold text-gray-700">
          Attendance summary — <?= $months[$attMonth] ?> <?= $attYear ?>
        </p>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Employee</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Department</th>
              <th class="text-center px-5 py-3 font-semibold text-green-600 uppercase text-xs tracking-wider">Present</th>
              <th class="text-center px-5 py-3 font-semibold text-red-600 uppercase text-xs tracking-wider">Absent</th>
              <th class="text-center px-5 py-3 font-semibold text-orange-500 uppercase text-xs tracking-wider">Late</th>
              <th class="text-center px-5 py-3 font-semibold text-yellow-600 uppercase text-xs tracking-wider">Half Day</th>
              <th class="text-right px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Total Hours</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($attRows)): ?>
              <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">No attendance records found for the selected period.</td></tr>
            <?php else: ?>
              <?php foreach ($attRows as $r): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="font-medium text-gray-900"><?= sanitize($r['full_name']) ?></p>
                    <p class="text-xs text-gray-400 font-mono"><?= sanitize($r['employee_id']) ?></p>
                  </td>
                  <td class="px-5 py-3 text-gray-600"><?= $r['department'] ? sanitize($r['department']) : '—' ?></td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-green-100 text-green-700 font-semibold px-2.5 py-0.5 rounded-full text-xs">
                      <?= (int)$r['present_days'] ?>
                    </span>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-red-100 text-red-700 font-semibold px-2.5 py-0.5 rounded-full text-xs">
                      <?= (int)$r['absent_days'] ?>
                    </span>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-orange-100 text-orange-700 font-semibold px-2.5 py-0.5 rounded-full text-xs">
                      <?= (int)$r['late_days'] ?>
                    </span>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-yellow-100 text-yellow-700 font-semibold px-2.5 py-0.5 rounded-full text-xs">
                      <?= (int)$r['half_days'] ?>
                    </span>
                  </td>
                  <td class="px-5 py-3 text-right font-medium text-gray-800"><?= number_format((float)$r['total_hours'], 1) ?>h</td>
                </tr>
              <?php endforeach; ?>
              <!-- Totals row -->
              <tr class="bg-gray-50 font-semibold border-t-2 border-gray-300">
                <td class="px-5 py-3 text-gray-800" colspan="2">Totals</td>
                <td class="px-5 py-3 text-center text-green-700"><?= array_sum(array_column($attRows, 'present_days')) ?></td>
                <td class="px-5 py-3 text-center text-red-700"><?= array_sum(array_column($attRows, 'absent_days')) ?></td>
                <td class="px-5 py-3 text-center text-orange-700"><?= array_sum(array_column($attRows, 'late_days')) ?></td>
                <td class="px-5 py-3 text-center text-yellow-700"><?= array_sum(array_column($attRows, 'half_days')) ?></td>
                <td class="px-5 py-3 text-right text-gray-800">
                  <?= number_format(array_sum(array_column($attRows, 'total_hours')), 1) ?>h
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       REPORT C: LEAVE
  ══════════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($reportType === 'leave'): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5">
      <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="report" value="leave">

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
          <select name="leave_status"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[140px]">
            <option value="">All Statuses</option>
            <?php foreach (['Pending', 'Approved', 'Rejected', 'Cancelled'] as $s): ?>
              <option value="<?= $s ?>" <?= $leaveStatusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Leave Type</label>
          <select name="leave_type"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[160px]">
            <option value="">All Types</option>
            <?php foreach ($leaveTypes as $lt): ?>
              <option value="<?= $lt['id'] ?>" <?= $leaveTypeFilter === (int)$lt['id'] ? 'selected' : '' ?>>
                <?= sanitize($lt['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Year</label>
          <select name="leave_year"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[100px]">
            <?php foreach ($yearRange as $y): ?>
              <option value="<?= $y ?>" <?= $leaveYear === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Filter
        </button>
        <a href="?report=leave"
           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Reset
        </a>

        <?php if (can('export', 'reports')): ?>
          <a href="?report=leave&export=csv&leave_status=<?= urlencode($leaveStatusFilter) ?>&leave_type=<?= $leaveTypeFilter ?>&leave_year=<?= $leaveYear ?>"
             class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors ml-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
          </a>
        <?php endif; ?>
      </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100">
        <p class="text-sm font-semibold text-gray-700">Leave summary — <?= $leaveYear ?></p>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Employee</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Department</th>
              <th class="text-center px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Total</th>
              <th class="text-center px-5 py-3 font-semibold text-green-600 uppercase text-xs tracking-wider">Approved</th>
              <th class="text-center px-5 py-3 font-semibold text-red-600 uppercase text-xs tracking-wider">Rejected</th>
              <th class="text-center px-5 py-3 font-semibold text-yellow-600 uppercase text-xs tracking-wider">Pending</th>
              <th class="text-center px-5 py-3 font-semibold text-gray-500 uppercase text-xs tracking-wider">Cancelled</th>
              <th class="text-center px-5 py-3 font-semibold text-indigo-600 uppercase text-xs tracking-wider">Days Taken</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($leaveRows)): ?>
              <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400">No leave records found for the selected filters.</td></tr>
            <?php else: ?>
              <?php foreach ($leaveRows as $r): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="font-medium text-gray-900"><?= sanitize($r['full_name']) ?></p>
                    <p class="text-xs text-gray-400 font-mono"><?= sanitize($r['employee_id']) ?></p>
                  </td>
                  <td class="px-5 py-3 text-gray-600"><?= $r['department'] ? sanitize($r['department']) : '—' ?></td>
                  <td class="px-5 py-3 text-center font-medium text-gray-800"><?= (int)$r['total_requests'] ?></td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-green-100 text-green-700 font-semibold px-2.5 py-0.5 rounded-full text-xs"><?= (int)$r['approved'] ?></span>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-red-100 text-red-700 font-semibold px-2.5 py-0.5 rounded-full text-xs"><?= (int)$r['rejected'] ?></span>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-yellow-100 text-yellow-700 font-semibold px-2.5 py-0.5 rounded-full text-xs"><?= (int)$r['pending'] ?></span>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-gray-100 text-gray-600 font-semibold px-2.5 py-0.5 rounded-full text-xs"><?= (int)$r['cancelled'] ?></span>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-block bg-indigo-100 text-indigo-700 font-semibold px-2.5 py-0.5 rounded-full text-xs"><?= (int)$r['total_days_taken'] ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
              <!-- Totals row -->
              <tr class="bg-gray-50 font-semibold border-t-2 border-gray-300">
                <td class="px-5 py-3 text-gray-800" colspan="2">Totals</td>
                <td class="px-5 py-3 text-center text-gray-800"><?= array_sum(array_column($leaveRows, 'total_requests')) ?></td>
                <td class="px-5 py-3 text-center text-green-700"><?= array_sum(array_column($leaveRows, 'approved')) ?></td>
                <td class="px-5 py-3 text-center text-red-700"><?= array_sum(array_column($leaveRows, 'rejected')) ?></td>
                <td class="px-5 py-3 text-center text-yellow-700"><?= array_sum(array_column($leaveRows, 'pending')) ?></td>
                <td class="px-5 py-3 text-center text-gray-600"><?= array_sum(array_column($leaveRows, 'cancelled')) ?></td>
                <td class="px-5 py-3 text-center text-indigo-700"><?= array_sum(array_column($leaveRows, 'total_days_taken')) ?></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- ═══════════════════════════════════════════════════════════════════════════
       REPORT D: PAYROLL
  ══════════════════════════════════════════════════════════════════════════════ -->
  <?php elseif ($reportType === 'payroll'): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-5">
      <form method="GET" class="flex flex-wrap gap-3 items-end">
        <input type="hidden" name="report" value="payroll">

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Month</label>
          <select name="pay_month"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[130px]">
            <?php foreach ($months as $num => $name): ?>
              <option value="<?= $num ?>" <?= $payMonth === $num ? 'selected' : '' ?>><?= $name ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Year</label>
          <select name="pay_year"
                  class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[100px]">
            <?php foreach ($yearRange as $y): ?>
              <option value="<?= $y ?>" <?= $payYear === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Filter
        </button>
        <a href="?report=payroll"
           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
          Reset
        </a>

        <?php if (can('export', 'reports')): ?>
          <a href="?report=payroll&export=csv&pay_month=<?= $payMonth ?>&pay_year=<?= $payYear ?>"
             class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors ml-auto">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Payroll summary cards -->
    <?php if (!empty($payRows)): ?>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
        <?php
          $cards = [
              ['label' => 'Total Employees',  'value' => count($payRows),                    'color' => 'bg-indigo-50 text-indigo-600', 'border' => 'border-indigo-200'],
              ['label' => 'Total Gross',       'value' => formatMoney($payTotals['gross']),   'color' => 'bg-blue-50 text-blue-600',    'border' => 'border-blue-200'],
              ['label' => 'Total Deductions',  'value' => formatMoney($payTotals['deductions']), 'color' => 'bg-red-50 text-red-600',   'border' => 'border-red-200'],
              ['label' => 'Total Net Payroll', 'value' => formatMoney($payTotals['net']),     'color' => 'bg-green-50 text-green-700', 'border' => 'border-green-200'],
          ];
          foreach ($cards as $card): ?>
          <div class="bg-white rounded-xl shadow-sm border <?= $card['border'] ?> p-4">
            <p class="text-xs font-medium text-gray-500 mb-1"><?= $card['label'] ?></p>
            <p class="text-xl font-bold <?= $card['color'] ?>"><?= $card['value'] ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100">
        <p class="text-sm font-semibold text-gray-700">
          Payroll — <?= $months[$payMonth] ?> <?= $payYear ?>
        </p>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Employee</th>
              <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Department</th>
              <th class="text-right px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Basic</th>
              <th class="text-right px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Allowances</th>
              <th class="text-right px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Gross</th>
              <th class="text-right px-5 py-3 font-semibold text-red-600 uppercase text-xs tracking-wider">Deductions</th>
              <th class="text-right px-5 py-3 font-semibold text-green-600 uppercase text-xs tracking-wider">Net</th>
              <th class="text-center px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Days</th>
              <th class="text-center px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <?php if (empty($payRows)): ?>
              <tr><td colspan="9" class="px-5 py-12 text-center text-gray-400">No payroll records found for <?= $months[$payMonth] ?> <?= $payYear ?>.</td></tr>
            <?php else: ?>
              <?php foreach ($payRows as $r): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="font-medium text-gray-900"><?= sanitize($r['full_name']) ?></p>
                    <p class="text-xs text-gray-400"><?= $r['position'] ? sanitize($r['position']) : '—' ?></p>
                  </td>
                  <td class="px-5 py-3 text-gray-600"><?= $r['department'] ? sanitize($r['department']) : '—' ?></td>
                  <td class="px-5 py-3 text-right text-gray-700"><?= formatMoney((float)$r['basic_salary']) ?></td>
                  <td class="px-5 py-3 text-right text-gray-700"><?= formatMoney((float)$r['total_allowances']) ?></td>
                  <td class="px-5 py-3 text-right text-gray-800 font-medium"><?= formatMoney((float)$r['gross_salary']) ?></td>
                  <td class="px-5 py-3 text-right text-red-700"><?= formatMoney((float)$r['total_deductions']) ?></td>
                  <td class="px-5 py-3 text-right text-green-700 font-semibold"><?= formatMoney((float)$r['net_salary']) ?></td>
                  <td class="px-5 py-3 text-center text-gray-600">
                    <span title="Days worked / absent">
                      <?= (int)$r['days_worked'] ?>/<span class="text-red-500"><?= (int)$r['days_absent'] ?></span>
                    </span>
                  </td>
                  <td class="px-5 py-3 text-center"><?= statusBadge($r['payment_status']) ?></td>
                </tr>
              <?php endforeach; ?>
              <!-- Totals row -->
              <tr class="bg-indigo-50 font-semibold border-t-2 border-indigo-200">
                <td class="px-5 py-3 text-gray-800 font-bold" colspan="2">Totals (<?= count($payRows) ?> employees)</td>
                <td class="px-5 py-3 text-right text-gray-800"><?= formatMoney($payTotals['basic']) ?></td>
                <td class="px-5 py-3 text-right text-gray-800"><?= formatMoney($payTotals['allowances']) ?></td>
                <td class="px-5 py-3 text-right text-gray-900 font-bold"><?= formatMoney($payTotals['gross']) ?></td>
                <td class="px-5 py-3 text-right text-red-700 font-bold"><?= formatMoney($payTotals['deductions']) ?></td>
                <td class="px-5 py-3 text-right text-green-700 font-bold text-base"><?= formatMoney($payTotals['net']) ?></td>
                <td class="px-5 py-3" colspan="2"></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
