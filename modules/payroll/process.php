<?php
/**
 * HRMS - Process / Add Payroll
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('process', 'payroll');

$pageTitle = 'Process Payroll';

// ── Load employees for dropdown ────────────────────────────────────────────────
$employees = fetchAll(
    "SELECT e.id, e.employee_id AS emp_code,
            CONCAT(e.first_name,' ',e.last_name) AS full_name,
            d.name AS dept_name
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE e.employment_status = 'Active'
     ORDER BY e.first_name, e.last_name"
);

$errors  = [];
$success = false;

// ── POST Handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $employeeId    = (int)($_POST['employee_id'] ?? 0);
        $month         = (int)($_POST['month'] ?? 0);
        $year          = (int)($_POST['year'] ?? 0);
        $basicSalary   = (float)($_POST['basic_salary'] ?? 0);
        $houseAllow    = (float)($_POST['house_allowance'] ?? 0);
        $transportAllow= (float)($_POST['transport_allowance'] ?? 0);
        $medicalAllow  = (float)($_POST['medical_allowance'] ?? 0);
        $otherAllow    = (float)($_POST['other_allowances'] ?? 0);
        $taxDed        = (float)($_POST['tax_deduction'] ?? 0);
        $pfDed         = (float)($_POST['provident_fund'] ?? 0);
        $insuranceDed  = (float)($_POST['insurance'] ?? 0);
        $otherDed      = (float)($_POST['other_deductions'] ?? 0);
        $daysWorked    = (int)($_POST['days_worked'] ?? 0);
        $daysAbsent    = (int)($_POST['days_absent'] ?? 0);
        $overtimeHours = (float)($_POST['overtime_hours'] ?? 0);
        $overtimePay   = (float)($_POST['overtime_pay'] ?? 0);
        $notes         = sanitize($_POST['notes'] ?? '');
        $processedBy   = (int)(currentUser()['id'] ?? 0);

        // Validations
        if ($employeeId <= 0) $errors[] = 'Please select an employee.';
        if ($month < 1 || $month > 12) $errors[] = 'Invalid month selected.';
        if ($year < 2000 || $year > 2100) $errors[] = 'Invalid year selected.';
        if ($basicSalary <= 0) $errors[] = 'Basic salary must be greater than zero.';

        // Duplicate check
        if (empty($errors)) {
            $existing = fetchOne(
                "SELECT id FROM payroll WHERE employee_id = ? AND month = ? AND year = ?",
                'iii', $employeeId, $month, $year
            );
            if ($existing) {
                $errors[] = 'A payroll record already exists for this employee for the selected month/year.';
            }
        }

        if (empty($errors)) {
            $totalAllowances  = $houseAllow + $transportAllow + $medicalAllow + $otherAllow;
            $grossSalary      = $basicSalary + $totalAllowances + $overtimePay;
            $totalDeductions  = $taxDed + $pfDed + $insuranceDed + $otherDed;
            $netSalary        = $grossSalary - $totalDeductions;

            $result = execute(
                "INSERT INTO payroll
                    (employee_id, month, year, basic_salary, total_allowances, gross_salary,
                     total_deductions, net_salary, days_worked, days_absent, overtime_hours,
                     overtime_pay, payment_status, notes, processed_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)",
                'iiidddddiiddsi',
                $employeeId, $month, $year,
                $basicSalary, $totalAllowances, $grossSalary,
                $totalDeductions, $netSalary,
                $daysWorked, $daysAbsent, $overtimeHours, $overtimePay,
                $notes, $processedBy
            );

            if ($result) {
                // Get employee's linked user for notification
                $empUser = fetchOne(
                    "SELECT u.id AS user_id, CONCAT(e.first_name,' ',e.last_name) AS full_name
                     FROM employees e JOIN users u ON u.employee_id = e.id WHERE e.id = ?",
                    'i', $employeeId
                );
                if ($empUser && $empUser['user_id']) {
                    createNotification(
                        (int)$empUser['user_id'],
                        'Payroll Processed',
                        'Your payroll for ' . date('F', mktime(0,0,0,$month,1)) . ' ' . $year . ' has been processed. Net Pay: ' . formatMoney($netSalary),
                        'payroll',
                        BASE_URL . '/modules/payroll/payslip.php?id=' . $result
                    );
                }
                setFlash('success', 'Payroll processed successfully.');
                header('Location: ' . BASE_URL . '/modules/payroll/index.php?month=' . $month . '&year=' . $year);
                exit;
            } else {
                $errors[] = 'Failed to save payroll record. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Process Payroll</h1>
        <p class="text-sm text-gray-500 mt-0.5">Add a new payroll record for an employee</p>
      </div>
      <a href="<?= BASE_URL ?>/modules/payroll/index.php"
         class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Payroll
      </a>
    </div>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <ul class="list-disc list-inside space-y-1">
          <?php foreach ($errors as $e): ?>
            <li class="text-sm text-red-700"><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" id="payrollForm" class="space-y-6">
      <?= csrfField() ?>

      <!-- Step 1: Select Employee & Period -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">
          1. Employee & Pay Period
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
          <div class="md:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Employee <span class="text-red-500">*</span>
            </label>
            <select name="employee_id" id="employeeSelect" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">— Select Employee —</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"
                        data-id="<?= $emp['id'] ?>"
                        <?= (int)($_POST['employee_id'] ?? 0) === (int)$emp['id'] ? 'selected' : '' ?>>
                  <?= sanitize($emp['full_name']) ?> (<?= sanitize($emp['emp_code']) ?>) — <?= sanitize($emp['dept_name'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Month <span class="text-red-500">*</span>
            </label>
            <select name="month" id="monthSelect" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= ((int)($_POST['month'] ?? (int)date('m'))) === $m ? 'selected' : '' ?>>
                  <?= date('F', mktime(0,0,0,$m,1)) ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Year <span class="text-red-500">*</span>
            </label>
            <select name="year" id="yearSelect" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <?php for ($y = (int)date('Y') + 1; $y >= 2020; $y--): ?>
                <option value="<?= $y ?>" <?= ((int)($_POST['year'] ?? (int)date('Y'))) === $y ? 'selected' : '' ?>><?= $y ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="mt-4">
          <button type="button" id="loadStructureBtn"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Load Salary Structure
          </button>
          <span id="loadStatus" class="ml-3 text-xs text-gray-400"></span>
        </div>
      </div>

      <!-- Step 2: Earnings -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">
          2. Earnings
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Basic Salary <span class="text-red-500">*</span></label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="basic_salary" id="basicSalary" step="0.01" min="0" required
                     value="<?= htmlspecialchars($_POST['basic_salary'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">House Allowance</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="house_allowance" id="houseAllowance" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['house_allowance'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Transport Allowance</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="transport_allowance" id="transportAllowance" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['transport_allowance'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Medical Allowance</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="medical_allowance" id="medicalAllowance" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['medical_allowance'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Other Allowances</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="other_allowances" id="otherAllowances" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['other_allowances'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Overtime Pay</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="overtime_pay" id="overtimePay" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['overtime_pay'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
        </div>
      </div>

      <!-- Step 3: Deductions -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">
          3. Deductions
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tax Deduction</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="tax_deduction" id="taxDeduction" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['tax_deduction'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Provident Fund</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="provident_fund" id="providentFund" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['provident_fund'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Insurance</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="insurance" id="insurance" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['insurance'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Other Deductions</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="other_deductions" id="otherDeductions" step="0.01" min="0"
                     value="<?= htmlspecialchars($_POST['other_deductions'] ?? '0') ?>"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 calc-input">
            </div>
          </div>
        </div>
      </div>

      <!-- Step 4: Attendance & Other -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">
          4. Attendance &amp; Notes
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Days Worked</label>
            <input type="number" name="days_worked" id="daysWorked" min="0" max="31"
                   value="<?= htmlspecialchars($_POST['days_worked'] ?? '0') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Days Absent</label>
            <input type="number" name="days_absent" id="daysAbsent" min="0" max="31"
                   value="<?= htmlspecialchars($_POST['days_absent'] ?? '0') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Overtime Hours</label>
            <input type="number" name="overtime_hours" id="overtimeHours" step="0.5" min="0"
                   value="<?= htmlspecialchars($_POST['overtime_hours'] ?? '0') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
          <div class="sm:col-span-2 lg:col-span-1">
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
            <textarea name="notes" rows="1"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Live Summary -->
      <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-6">
        <h2 class="text-base font-semibold text-indigo-800 mb-4">Salary Summary (Live Preview)</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div class="bg-white rounded-lg p-4 text-center shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Basic Salary</p>
            <p class="text-lg font-bold text-gray-800" id="displayBasic">$0.00</p>
          </div>
          <div class="bg-white rounded-lg p-4 text-center shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Total Allowances</p>
            <p class="text-lg font-bold text-blue-700" id="displayAllowances">$0.00</p>
          </div>
          <div class="bg-white rounded-lg p-4 text-center shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Gross Salary</p>
            <p class="text-lg font-bold text-indigo-700" id="displayGross">$0.00</p>
          </div>
          <div class="bg-white rounded-lg p-4 text-center shadow-sm">
            <p class="text-xs text-gray-500 mb-1">Total Deductions</p>
            <p class="text-lg font-bold text-red-600" id="displayDeductions">$0.00</p>
          </div>
        </div>
        <div class="mt-4 bg-indigo-600 text-white rounded-xl p-4 text-center">
          <p class="text-sm opacity-80">Net Pay</p>
          <p class="text-3xl font-extrabold mt-1" id="displayNet">$0.00</p>
        </div>
      </div>

      <!-- Submit -->
      <div class="flex gap-3">
        <button type="submit"
                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
          Process Payroll
        </button>
        <a href="<?= BASE_URL ?>/modules/payroll/index.php"
           class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition-colors">
          Cancel
        </a>
      </div>

    </form>
  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// ── Live salary calculator ──────────────────────────────────────────────────────
function fmt(n) {
  return 'KES ' + parseFloat(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function recalculate() {
  var basic     = parseFloat(document.getElementById('basicSalary').value) || 0;
  var house     = parseFloat(document.getElementById('houseAllowance').value) || 0;
  var transport = parseFloat(document.getElementById('transportAllowance').value) || 0;
  var medical   = parseFloat(document.getElementById('medicalAllowance').value) || 0;
  var other     = parseFloat(document.getElementById('otherAllowances').value) || 0;
  var overtime  = parseFloat(document.getElementById('overtimePay').value) || 0;
  var tax       = parseFloat(document.getElementById('taxDeduction').value) || 0;
  var pf        = parseFloat(document.getElementById('providentFund').value) || 0;
  var ins       = parseFloat(document.getElementById('insurance').value) || 0;
  var otherDed  = parseFloat(document.getElementById('otherDeductions').value) || 0;

  var totalAllowances = house + transport + medical + other;
  var gross = basic + totalAllowances + overtime;
  var totalDed = tax + pf + ins + otherDed;
  var net = gross - totalDed;

  document.getElementById('displayBasic').textContent       = fmt(basic);
  document.getElementById('displayAllowances').textContent  = fmt(totalAllowances);
  document.getElementById('displayGross').textContent       = fmt(gross);
  document.getElementById('displayDeductions').textContent  = fmt(totalDed);
  document.getElementById('displayNet').textContent         = fmt(net);
}

document.querySelectorAll('.calc-input').forEach(function(el) {
  el.addEventListener('input', recalculate);
});
recalculate();

// ── Load salary structure via AJAX ─────────────────────────────────────────────
document.getElementById('loadStructureBtn').addEventListener('click', function() {
  var empId = document.getElementById('employeeSelect').value;
  if (!empId) {
    alert('Please select an employee first.');
    return;
  }
  var status = document.getElementById('loadStatus');
  status.textContent = 'Loading...';
  status.className = 'ml-3 text-xs text-gray-400';

  fetch('<?= BASE_URL ?>/modules/payroll/get_salary_structure.php?employee_id=' + empId)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data.success) {
        document.getElementById('basicSalary').value        = data.basic_salary || 0;
        document.getElementById('houseAllowance').value     = data.house_allowance || 0;
        document.getElementById('transportAllowance').value = data.transport_allowance || 0;
        document.getElementById('medicalAllowance').value   = data.medical_allowance || 0;
        document.getElementById('otherAllowances').value    = data.other_allowances || 0;
        document.getElementById('taxDeduction').value       = data.tax_deduction || 0;
        document.getElementById('providentFund').value      = data.provident_fund || 0;
        document.getElementById('insurance').value          = data.insurance || 0;
        document.getElementById('otherDeductions').value    = data.other_deductions || 0;
        recalculate();
        status.textContent = 'Salary structure loaded.';
        status.className = 'ml-3 text-xs text-green-600';
      } else {
        status.textContent = data.message || 'No salary structure found for this employee.';
        status.className = 'ml-3 text-xs text-yellow-600';
      }
    })
    .catch(function() {
      status.textContent = 'Error loading salary structure.';
      status.className = 'ml-3 text-xs text-red-600';
    });
});
</script>
