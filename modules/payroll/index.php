<?php
/**
 * HRMS - Payroll List
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('view', 'payroll');

$pageTitle = 'Payroll';
$user      = currentUser();
$role      = $user['role'] ?? '';
$isEmp     = ($role === 'Employee');
$isAdmin   = ($role === 'Admin');
$isHRMgr   = ($role === 'HR Manager');

// ── Filters ────────────────────────────────────────────────────────────────────
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filterYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$filterMonth = max(1, min(12, $filterMonth));
$filterYear  = max(2000, min(2100, $filterYear));

// ── Pagination ─────────────────────────────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ── Build WHERE based on role ──────────────────────────────────────────────────
$extraWhere = '';
$extraTypes = '';
$extraParams = [];

if ($isEmp) {
    // Employee: only their own payroll records
    $empId = (int)($user['employee_id'] ?? 0);
    $extraWhere  = ' AND p.employee_id = ?';
    $extraTypes  = 'i';
    $extraParams = [$empId];
}
// Admin and HR Manager see all records (no extra filter)

$totalRows = fetchOne(
    "SELECT COUNT(*) AS c FROM payroll p WHERE p.month = ? AND p.year = ?{$extraWhere}",
    'ii' . $extraTypes, $filterMonth, $filterYear, ...$extraParams
)['c'] ?? 0;

$pag = paginate((int)$totalRows, $page, $perPage);

// ── Data ───────────────────────────────────────────────────────────────────────
$payrolls = fetchAll(
    "SELECT p.*,
            CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.employee_id AS emp_code,
            e.photo,
            d.name AS dept_name
     FROM payroll p
     JOIN employees e ON p.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE p.month = ? AND p.year = ?{$extraWhere}
     ORDER BY e.first_name, e.last_name
     LIMIT ? OFFSET ?",
    'ii' . $extraTypes . 'ii', $filterMonth, $filterYear, ...[...$extraParams, $pag['perPage'], $pag['offset']]
);

// ── Summary totals ─────────────────────────────────────────────────────────────
$summary = fetchOne(
    "SELECT
        COUNT(*) AS total_records,
        SUM(gross_salary)  AS total_gross,
        SUM(total_deductions) AS total_deductions,
        SUM(net_salary)    AS total_net,
        SUM(payment_status = 'Paid')    AS paid_count,
        SUM(payment_status = 'Pending') AS pending_count
     FROM payroll p WHERE p.month = ? AND p.year = ?{$extraWhere}",
    'ii' . $extraTypes, $filterMonth, $filterYear, ...$extraParams
);

// ── Month name ─────────────────────────────────────────────────────────────────
$monthName = date('F', mktime(0, 0, 0, $filterMonth, 1));

// ── Build base URL for pagination ─────────────────────────────────────────────
$baseUrl = BASE_URL . '/modules/payroll/index.php?month=' . $filterMonth . '&year=' . $filterYear;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Flash -->
    <?php $flash = getFlash(); if ($flash): ?>
      <div class="rounded-lg px-4 py-3 text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <?php if ($isEmp): ?>
          <h1 class="text-2xl font-bold text-gray-900">My Payslips</h1>
        <?php else: ?>
          <h1 class="text-2xl font-bold text-gray-900">Payroll</h1>
        <?php endif; ?>
        <p class="text-sm text-gray-500 mt-0.5"><?= $monthName . ' ' . $filterYear ?> — <?= number_format((int)$totalRows) ?> record(s)</p>
      </div>
      <?php if ($isHRMgr): ?>
        <a href="<?= BASE_URL ?>/modules/payroll/process.php"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Process Payroll
        </a>
      <?php endif; ?>
    </div>

    <!-- Filter Bar -->
    <form method="GET" class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Month</label>
          <select name="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php for ($m = 1; $m <= 12; $m++): ?>
              <option value="<?= $m ?>" <?= $m === $filterMonth ? 'selected' : '' ?>>
                <?= date('F', mktime(0,0,0,$m,1)) ?>
              </option>
            <?php endfor; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Year</label>
          <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php for ($y = (int)date('Y') + 1; $y >= 2020; $y--): ?>
              <option value="<?= $y ?>" <?= $y === $filterYear ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <button type="submit"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
          Filter
        </button>
        <a href="<?= BASE_URL ?>/modules/payroll/index.php"
           class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
          Reset
        </a>
      </div>
    </form>

    <!-- Summary Cards -->
    <?php if ($summary && (int)$summary['total_records'] > 0): ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
      <?php
      $cards = [
        ['label' => 'Total Records',   'value' => number_format((int)$summary['total_records']), 'color' => 'text-indigo-700',  'bg' => 'bg-indigo-50'],
        ['label' => 'Total Gross',     'value' => formatMoney((float)($summary['total_gross'] ?? 0)),      'color' => 'text-blue-700',    'bg' => 'bg-blue-50'],
        ['label' => 'Total Deductions','value' => formatMoney((float)($summary['total_deductions'] ?? 0)), 'color' => 'text-red-700',     'bg' => 'bg-red-50'],
        ['label' => 'Total Net Pay',   'value' => formatMoney((float)($summary['total_net'] ?? 0)),        'color' => 'text-green-700',   'bg' => 'bg-green-50'],
        ['label' => 'Paid / Pending',  'value' => (int)$summary['paid_count'] . ' / ' . (int)$summary['pending_count'], 'color' => 'text-yellow-700',  'bg' => 'bg-yellow-50'],
      ];
      foreach ($cards as $card): ?>
        <div class="<?= $card['bg'] ?> rounded-xl border border-opacity-50 border-gray-200 px-4 py-3">
          <p class="text-xs font-medium text-gray-500"><?= $card['label'] ?></p>
          <p class="text-lg font-bold <?= $card['color'] ?> mt-0.5"><?= $card['value'] ?></p>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Payroll Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">
          <?= $isEmp ? 'My Payslips' : 'Payroll Records' ?> — <?= $monthName . ' ' . $filterYear ?>
        </h2>
        <?php if (!empty($payrolls)): ?>
          <span class="text-xs text-gray-400"><?= count($payrolls) ?> of <?= $totalRows ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($payrolls)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-gray-400">
          <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p class="text-sm font-medium">No payroll records for <?= $monthName . ' ' . $filterYear ?></p>
          <?php if ($isHRMgr): ?>
            <a href="<?= BASE_URL ?>/modules/payroll/process.php"
               class="mt-3 text-xs text-indigo-600 hover:underline">Process payroll now →</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Department</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Basic Salary</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Gross</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Deductions</th>
                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Net Pay</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <?php foreach ($payrolls as $row): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                      <img src="<?= avatarUrl($row['photo']) ?>" alt=""
                           class="w-8 h-8 rounded-full object-cover shrink-0">
                      <div>
                        <p class="font-medium text-gray-900"><?= sanitize($row['emp_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= sanitize($row['emp_code']) ?></p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-gray-600"><?= sanitize($row['dept_name'] ?? '—') ?></td>
                  <td class="px-4 py-3 text-right font-mono text-gray-700"><?= formatMoney((float)$row['basic_salary']) ?></td>
                  <td class="px-4 py-3 text-right font-mono text-gray-700"><?= formatMoney((float)$row['gross_salary']) ?></td>
                  <td class="px-4 py-3 text-right font-mono text-red-600"><?= formatMoney((float)$row['total_deductions']) ?></td>
                  <td class="px-4 py-3 text-right font-mono font-semibold text-green-700"><?= formatMoney((float)$row['net_salary']) ?></td>
                  <td class="px-4 py-3 text-center"><?= statusBadge($row['payment_status']) ?></td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-1">
                      <!-- View Payslip -->
                      <a href="<?= BASE_URL ?>/modules/payroll/payslip.php?id=<?= $row['id'] ?>"
                         title="View Payslip"
                         class="p-1.5 rounded-lg text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                      </a>
                      <?php if ($isHRMgr && $row['payment_status'] === 'Pending'): ?>
                        <!-- Mark Paid — HR Manager only -->
                        <button type="button"
                                onclick="openMarkPaid(<?= $row['id'] ?>, '<?= sanitize($row['emp_name']) ?>')"
                                title="Mark as Paid"
                                class="p-1.5 rounded-lg text-gray-500 hover:text-green-600 hover:bg-green-50 transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                          </svg>
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?= renderPagination($pag, $baseUrl) ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<!-- Mark Paid Modal (HR Manager only) -->
<?php if ($isHRMgr): ?>
<div id="markPaidModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-40 px-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-1">Mark as Paid</h3>
    <p class="text-sm text-gray-500 mb-5" id="markPaidName"></p>
    <form method="POST" action="<?= BASE_URL ?>/modules/payroll/mark_paid.php">
      <?= csrfField() ?>
      <input type="hidden" name="payroll_id" id="markPaidId">
      <input type="hidden" name="redirect_month" value="<?= $filterMonth ?>">
      <input type="hidden" name="redirect_year" value="<?= $filterYear ?>">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method <span class="text-red-500">*</span></label>
          <select name="payment_method" required
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="Bank Transfer">Bank Transfer</option>
            <option value="Cash">Cash</option>
            <option value="Cheque">Cheque</option>
            <option value="Mobile Money">Mobile Money</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
          <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
      </div>
      <div class="flex gap-3 mt-6">
        <button type="submit"
                class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
          Confirm Payment
        </button>
        <button type="button" onclick="closeMarkPaid()"
                class="flex-1 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
function openMarkPaid(id, name) {
  document.getElementById('markPaidId').value = id;
  document.getElementById('markPaidName').textContent = 'Employee: ' + name;
  document.getElementById('markPaidModal').classList.remove('hidden');
}
function closeMarkPaid() {
  document.getElementById('markPaidModal').classList.add('hidden');
}
var modal = document.getElementById('markPaidModal');
if (modal) {
  modal.addEventListener('click', function(e) {
    if (e.target === this) closeMarkPaid();
  });
}
</script>
