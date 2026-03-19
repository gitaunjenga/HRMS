<?php
/**
 * HRMS - Attendance Records
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('view', 'attendance');

$pageTitle  = 'Attendance';
$user       = currentUser();
$isHR       = hasRole('Admin', 'HR Manager');

// ── Filters ──────────────────────────────────────────────────────────────────
$filterEmp    = (int)($_GET['employee_id'] ?? 0);
$filterMonth  = sanitize($_GET['month'] ?? date('Y-m'));
$filterStatus = sanitize($_GET['status'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

// If employee role, force their own employee_id
if (hasRole('Employee')) {
    $filterEmp = (int)($user['employee_id'] ?? 0);
}

// Parse month filter into year/month parts
$monthParts = explode('-', $filterMonth);
$filterYear  = isset($monthParts[0]) && is_numeric($monthParts[0]) ? (int)$monthParts[0] : (int)date('Y');
$filterMon   = isset($monthParts[1]) && is_numeric($monthParts[1]) ? (int)$monthParts[1] : (int)date('m');

// Build WHERE conditions
$whereClauses = ["YEAR(a.date) = ? AND MONTH(a.date) = ?"];
$whereTypes   = "ii";
$whereParams  = [$filterYear, $filterMon];

// Employee role: filter to own records only
if (hasRole('Employee')) {
    $empId = (int)($user['employee_id'] ?? 0);
    $whereClauses[] = "a.employee_id = ?";
    $whereTypes    .= "i";
    $whereParams[]  = $empId;
} elseif (hasRole('Head of Department')) {
    // Head of Department: filter to their department only
    $dmDeptId = myDeptId();
    if ($dmDeptId !== null) {
        $whereClauses[] = "e.department_id = ?";
        $whereTypes    .= "i";
        $whereParams[]  = $dmDeptId;
    }
} elseif ($filterEmp > 0) {
    $whereClauses[] = "a.employee_id = ?";
    $whereTypes    .= "i";
    $whereParams[]  = $filterEmp;
}

if ($filterStatus !== '') {
    $whereClauses[] = "a.status = ?";
    $whereTypes    .= "s";
    $whereParams[]  = $filterStatus;
}

$where = implode(' AND ', $whereClauses);

// Count for pagination
$totalRow = fetchOne(
    "SELECT COUNT(*) AS c FROM attendance a
     JOIN employees e ON a.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE $where",
    $whereTypes, ...$whereParams
);
$total = (int)($totalRow['c'] ?? 0);
$pag   = paginate($total, $page, $perPage);

// Fetch records
$records = fetchAll(
    "SELECT a.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.employee_id AS emp_code, e.photo, d.name AS dept_name
     FROM attendance a
     JOIN employees e ON a.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE $where
     ORDER BY a.date DESC, e.first_name
     LIMIT ? OFFSET ?",
    $whereTypes . "ii", ...[...$whereParams, $perPage, $pag['offset']]
);

// Employee list for filter (HR/Admin only)
$employees = $isHR ? fetchAll(
    "SELECT id, employee_id, first_name, last_name FROM employees
     WHERE employment_status = 'Active' ORDER BY first_name, last_name"
) : [];

$statuses = ['Present', 'Absent', 'Late', 'Half Day', 'On Leave'];

// Build base URL for pagination
$baseUrl = BASE_URL . '/modules/attendance/index.php?month=' . urlencode($filterMonth)
    . ($filterEmp ? '&employee_id=' . $filterEmp : '')
    . ($filterStatus ? '&status=' . urlencode($filterStatus) : '');

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Flash -->
    <?php if ($flash): ?>
      <div class="rounded-lg px-4 py-3 text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Attendance Records</h2>
        <p class="text-sm text-gray-500 mt-0.5">
          <?= $total ?> record<?= $total !== 1 ? 's' : '' ?> for
          <?= date('F Y', mktime(0, 0, 0, $filterMon, 1, $filterYear)) ?>
        </p>
      </div>
      <div class="flex items-center gap-2">
        <?php if (!$isHR): ?>
          <a href="<?= BASE_URL ?>/modules/attendance/checkin.php"
             class="inline-flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Check In/Out
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/attendance/report.php"
           class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Reports
        </a>
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

        <!-- Month picker -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">Month</label>
          <input type="month" name="month"
                 value="<?= htmlspecialchars($filterMonth) ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <!-- Employee filter (HR/Admin only) -->
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

        <!-- Status filter -->
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">Status</label>
          <select name="status"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <?php foreach ($statuses as $s): ?>
              <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Submit -->
        <div class="flex items-end">
          <div class="flex gap-2 w-full">
            <button type="submit"
                    class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
              Filter
            </button>
            <a href="<?= BASE_URL ?>/modules/attendance/index.php"
               class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">
              Reset
            </a>
          </div>
        </div>
      </div>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
              <?php if ($isHR): ?>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee</th>
              <?php endif; ?>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Check In</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Check Out</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Hours</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <?php if ($isHR): ?>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Notes</th>
              <?php endif; ?>
              <?php if (can('edit', 'attendance')): ?>
                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-50">
            <?php if (empty($records)): ?>
              <tr>
                <td colspan="<?= ($isHR ? 7 : 5) + (can('edit', 'attendance') ? 1 : 0) ?>" class="px-5 py-10 text-center text-sm text-gray-400">
                  No attendance records found for the selected period.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($records as $row): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="text-sm font-medium text-gray-900"><?= formatDate($row['date']) ?></p>
                    <p class="text-xs text-gray-400"><?= date('l', strtotime($row['date'])) ?></p>
                  </td>
                  <?php if ($isHR): ?>
                    <td class="px-5 py-3">
                      <div class="flex items-center gap-2.5">
                        <img src="<?= avatarUrl($row['photo']) ?>" alt="" class="w-8 h-8 rounded-full object-cover">
                        <div>
                          <p class="text-sm font-medium text-gray-900"><?= sanitize($row['emp_name']) ?></p>
                          <p class="text-xs text-gray-400"><?= sanitize($row['emp_code']) ?><?= $row['dept_name'] ? ' · ' . sanitize($row['dept_name']) : '' ?></p>
                        </div>
                      </div>
                    </td>
                  <?php endif; ?>
                  <td class="px-5 py-3">
                    <span class="text-sm text-gray-700 font-mono">
                      <?= $row['check_in'] ? date('h:i A', strtotime($row['check_in'])) : '—' ?>
                    </span>
                  </td>
                  <td class="px-5 py-3">
                    <span class="text-sm text-gray-700 font-mono">
                      <?= $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : '—' ?>
                    </span>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <?php if ($row['total_hours'] !== null): ?>
                      <span class="text-sm font-semibold text-gray-900">
                        <?= number_format((float)$row['total_hours'], 1) ?>h
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <?= statusBadge($row['status']) ?>
                  </td>
                  <?php if ($isHR): ?>
                    <td class="px-5 py-3 max-w-xs">
                      <span class="text-xs text-gray-500 truncate block">
                        <?= $row['notes'] ? sanitize($row['notes']) : '—' ?>
                      </span>
                    </td>
                  <?php endif; ?>
                  <?php if (can('edit', 'attendance')): ?>
                    <td class="px-5 py-3 text-center">
                      <a href="<?= BASE_URL ?>/modules/attendance/edit.php?id=<?= $row['id'] ?>"
                         title="Edit Attendance"
                         class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors inline-flex">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                      </a>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?= renderPagination($pag, $baseUrl) ?>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
