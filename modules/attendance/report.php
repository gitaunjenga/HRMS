<?php
/**
 * HRMS - Monthly Attendance Report
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Attendance Report';
$user      = currentUser();
$isHR      = hasRole('Admin', 'HR Manager');

// ── Filters ──────────────────────────────────────────────────────────────────
$filterYear  = (int)($_GET['year']  ?? date('Y'));
$filterMonth = (int)($_GET['month'] ?? date('m'));
$filterEmp   = (int)($_GET['employee_id'] ?? 0);

// Employees force their own record
if (!$isHR) {
    $filterEmp = (int)($user['employee_id'] ?? 0);
}

// Clamp values
$filterYear  = max(2020, min((int)date('Y') + 1, $filterYear));
$filterMonth = max(1, min(12, $filterMonth));

// ── Export CSV ────────────────────────────────────────────────────────────────
$isExport = isset($_GET['export']) && $_GET['export'] === 'csv';

// ── Build employee filter ────────────────────────────────────────────────────
$empWhere  = '';
$empTypes  = 'ii';
$empParams = [$filterYear, $filterMonth];

if ($filterEmp > 0) {
    $empWhere   = ' AND a.employee_id = ?';
    $empTypes  .= 'i';
    $empParams[] = $filterEmp;
}

// ── Summary ──────────────────────────────────────────────────────────────────
$summary = fetchOne(
    "SELECT
        COUNT(*)                   AS total_records,
        SUM(status = 'Present')    AS present,
        SUM(status = 'Absent')     AS absent,
        SUM(status = 'Late')       AS late,
        SUM(status = 'Half Day')   AS half_day,
        SUM(status = 'On Leave')   AS on_leave,
        ROUND(SUM(IFNULL(total_hours, 0)), 2) AS total_hours,
        ROUND(AVG(CASE WHEN total_hours IS NOT NULL THEN total_hours END), 2) AS avg_hours
     FROM attendance a
     WHERE YEAR(a.date) = ? AND MONTH(a.date) = ?$empWhere",
    $empTypes, ...$empParams
);

// ── Daily Records ─────────────────────────────────────────────────────────────
$records = fetchAll(
    "SELECT a.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.employee_id AS emp_code, e.photo, d.name AS dept_name
     FROM attendance a
     JOIN employees e ON a.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE YEAR(a.date) = ? AND MONTH(a.date) = ?$empWhere
     ORDER BY a.date ASC, e.first_name ASC",
    $empTypes, ...$empParams
);

// ── Employee list for filter ──────────────────────────────────────────────────
$employees = $isHR ? fetchAll(
    "SELECT id, employee_id, first_name, last_name FROM employees
     WHERE employment_status = 'Active' ORDER BY first_name, last_name"
) : [];

// Resolve employee name for display
$empName = 'All Employees';
if ($filterEmp > 0) {
    $empRow   = fetchOne("SELECT first_name, last_name FROM employees WHERE id = ?", 'i', $filterEmp);
    $empName  = $empRow ? $empRow['first_name'] . ' ' . $empRow['last_name'] : 'Unknown';
}
$monthLabel = date('F Y', mktime(0, 0, 0, $filterMonth, 1, $filterYear));

// ── CSV Export ────────────────────────────────────────────────────────────────
if ($isExport) {
    $filename = 'attendance_report_' . $filterYear . '_' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT) . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Attendance Report — ' . $monthLabel . ' — ' . $empName]);
    fputcsv($out, []);
    fputcsv($out, ['Date', 'Day', 'Employee', 'Employee ID', 'Department', 'Check In', 'Check Out', 'Total Hours', 'Status', 'Notes']);

    foreach ($records as $row) {
        fputcsv($out, [
            $row['date'],
            date('l', strtotime($row['date'])),
            $row['emp_name'],
            $row['emp_code'],
            $row['dept_name'] ?? '',
            $row['check_in']  ? date('h:i A', strtotime($row['check_in']))  : '',
            $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '',
            $row['total_hours'] ? number_format((float)$row['total_hours'], 2) : '',
            $row['status'],
            $row['notes'] ?? '',
        ]);
    }

    fputcsv($out, []);
    fputcsv($out, ['Summary']);
    fputcsv($out, ['Present',  $summary['present']    ?? 0]);
    fputcsv($out, ['Absent',   $summary['absent']     ?? 0]);
    fputcsv($out, ['Late',     $summary['late']       ?? 0]);
    fputcsv($out, ['Half Day', $summary['half_day']   ?? 0]);
    fputcsv($out, ['On Leave', $summary['on_leave']   ?? 0]);
    fputcsv($out, ['Total Hours', number_format((float)($summary['total_hours'] ?? 0), 2)]);
    fputcsv($out, ['Average Hours/Day', number_format((float)($summary['avg_hours'] ?? 0), 2)]);

    fclose($out);
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Build export URL
$exportUrl = BASE_URL . '/modules/attendance/report.php?export=csv'
    . '&year=' . $filterYear . '&month=' . $filterMonth
    . ($filterEmp ? '&employee_id=' . $filterEmp : '');
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Attendance Report</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= $monthLabel ?> · <?= htmlspecialchars($empName) ?></p>
      </div>
      <a href="<?= $exportUrl ?>"
         class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
        </svg>
        Export CSV
      </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">Year</label>
          <select name="year"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <?php for ($y = (int)date('Y'); $y >= 2020; $y--): ?>
              <option value="<?= $y ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">Month</label>
          <select name="month"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $filterMonth === $m ? 'selected' : '' ?>>
                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <?php if ($isHR): ?>
          <div>
            <label class="block text-xs font-medium text-gray-600 mb-1.5">Employee</label>
            <select name="employee_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">All Employees</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>" <?= $filterEmp == $emp['id'] ? 'selected' : '' ?>>
                  <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                  (<?= sanitize($emp['employee_id']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <div class="flex items-end">
          <button type="submit"
                  class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
            Generate Report
          </button>
        </div>
      </div>
    </form>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-4">
      <?php
      $summaryCards = [
        ['label' => 'Present',    'value' => (int)($summary['present']    ?? 0), 'color' => 'bg-green-100  text-green-700',  'border' => 'border-green-200'],
        ['label' => 'Absent',     'value' => (int)($summary['absent']     ?? 0), 'color' => 'bg-red-100    text-red-700',    'border' => 'border-red-200'],
        ['label' => 'Late',       'value' => (int)($summary['late']       ?? 0), 'color' => 'bg-orange-100 text-orange-700', 'border' => 'border-orange-200'],
        ['label' => 'Half Day',   'value' => (int)($summary['half_day']   ?? 0), 'color' => 'bg-yellow-100 text-yellow-700', 'border' => 'border-yellow-200'],
        ['label' => 'On Leave',   'value' => (int)($summary['on_leave']   ?? 0), 'color' => 'bg-blue-100   text-blue-700',   'border' => 'border-blue-200'],
        ['label' => 'Total Hours','value' => number_format((float)($summary['total_hours'] ?? 0), 1) . 'h',
                                              'color' => 'bg-indigo-100  text-indigo-700', 'border' => 'border-indigo-200'],
        ['label' => 'Avg Hrs/Day','value' => number_format((float)($summary['avg_hours']   ?? 0), 1) . 'h',
                                              'color' => 'bg-purple-100  text-purple-700', 'border' => 'border-purple-200'],
      ];
      foreach ($summaryCards as $card): ?>
        <div class="bg-white rounded-xl border <?= $card['border'] ?> shadow-sm p-4 text-center">
          <p class="text-xl font-bold <?= explode(' ', $card['color'])[1] ?>"><?= $card['value'] ?></p>
          <p class="text-xs text-gray-500 mt-0.5"><?= $card['label'] ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Records Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800">Daily Records</h3>
        <span class="text-xs text-gray-400"><?= count($records) ?> record<?= count($records) !== 1 ? 's' : '' ?></span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
              <?php if ($isHR && !$filterEmp): ?>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee</th>
              <?php endif; ?>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Check In</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Check Out</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Hours</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Notes</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-50">
            <?php if (empty($records)): ?>
              <tr>
                <td colspan="7" class="px-5 py-10 text-center text-sm text-gray-400">
                  No attendance records for <?= $monthLabel ?>.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($records as $row): ?>
                <?php $isWeekend = in_array(date('N', strtotime($row['date'])), [6, 7]); ?>
                <tr class="hover:bg-gray-50 transition-colors <?= $isWeekend ? 'bg-gray-50/50' : '' ?>">
                  <td class="px-5 py-3">
                    <p class="text-sm font-medium text-gray-900"><?= formatDate($row['date']) ?></p>
                    <p class="text-xs <?= $isWeekend ? 'text-orange-400' : 'text-gray-400' ?>">
                      <?= date('l', strtotime($row['date'])) ?>
                    </p>
                  </td>
                  <?php if ($isHR && !$filterEmp): ?>
                    <td class="px-5 py-3">
                      <div class="flex items-center gap-2">
                        <img src="<?= avatarUrl($row['photo']) ?>" alt="" class="w-7 h-7 rounded-full object-cover">
                        <div>
                          <p class="text-sm font-medium text-gray-900"><?= sanitize($row['emp_name']) ?></p>
                          <p class="text-xs text-gray-400"><?= sanitize($row['emp_code']) ?></p>
                        </div>
                      </div>
                    </td>
                  <?php endif; ?>
                  <td class="px-5 py-3 font-mono text-sm text-gray-700">
                    <?= $row['check_in']  ? date('h:i A', strtotime($row['check_in']))  : '—' ?>
                  </td>
                  <td class="px-5 py-3 font-mono text-sm text-gray-700">
                    <?= $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '—' ?>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <?php if ($row['total_hours'] !== null): ?>
                      <span class="font-semibold text-sm text-gray-900">
                        <?= number_format((float)$row['total_hours'], 1) ?>h
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3 text-center"><?= statusBadge($row['status']) ?></td>
                  <td class="px-5 py-3 text-xs text-gray-500 max-w-xs truncate">
                    <?= $row['notes'] ? sanitize($row['notes']) : '—' ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
