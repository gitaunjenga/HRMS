<?php
/**
 * HRMS - View Employee Profile
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('view', 'employees');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid employee ID.');
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
    exit;
}

// Employee role: may only view their own profile
if (hasRole('Employee') && $id !== (int)(currentUser()['employee_id'] ?? 0)) {
    setFlash('error', 'You are not authorised to view another employee\'s profile.');
    header('Location: ' . BASE_URL . '/modules/employees/view.php?id=' . (int)(currentUser()['employee_id'] ?? 0));
    exit;
}

$employee = fetchOne(
    "SELECT e.*, d.name AS department_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.id = ?",
    'i', $id
);

if (!$employee) {
    setFlash('error', 'Employee not found.');
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
    exit;
}

$pageTitle = $employee['first_name'] . ' ' . $employee['last_name'];

// ── Related data ──────────────────────────────────────────────────────────────
$currentYear = date('Y');

// Leave balances for current year
$leaveBalances = fetchAll(
    "SELECT lb.*, lt.name AS leave_type_name, lt.days_allowed
     FROM leave_balances lb
     JOIN leave_types lt ON lt.id = lb.leave_type_id
     WHERE lb.employee_id = ? AND lb.year = ?
     ORDER BY lt.name ASC",
    'ii', $id, $currentYear
);

// Recent attendance (last 10 records)
$recentAttendance = fetchAll(
    "SELECT * FROM attendance WHERE employee_id = ? ORDER BY date DESC LIMIT 10",
    'i', $id
);

// Recent payroll (last 3 months)
$recentPayroll = fetchAll(
    "SELECT * FROM payroll WHERE employee_id = ? ORDER BY year DESC, month DESC LIMIT 3",
    'i', $id
);

// Documents
$documents = fetchAll(
    "SELECT * FROM documents WHERE employee_id = ? ORDER BY created_at DESC",
    'i', $id
);

// Active tab
$activeTab = sanitize($_GET['tab'] ?? 'personal');
$validTabs = ['personal', 'employment', 'contact', 'documents'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'personal';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <!-- Flash -->
  <?php $flash = getFlash(); if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
      <?= sanitize($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Breadcrumb -->
  <nav class="text-sm text-gray-500 mb-4 flex items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/employees/index.php" class="hover:text-indigo-600 transition-colors">Employees</a>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-900 font-medium"><?= sanitize($employee['first_name']) ?> <?= sanitize($employee['last_name']) ?></span>
  </nav>

  <!-- Profile hero card -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-6">
      <!-- Photo -->
      <div class="relative shrink-0">
        <img src="<?= avatarUrl($employee['photo']) ?>" alt="Photo"
             class="w-24 h-24 rounded-full object-cover ring-4 ring-indigo-100">
        <span class="absolute bottom-0 right-0 block w-4 h-4 rounded-full border-2 border-white
            <?= $employee['employment_status'] === 'Active' ? 'bg-green-400' : ($employee['employment_status'] === 'Terminated' ? 'bg-red-400' : 'bg-yellow-400') ?>">
        </span>
      </div>

      <!-- Basic info -->
      <div class="flex-1 min-w-0">
        <div class="flex flex-wrap items-center gap-3 mb-1">
          <h2 class="text-xl font-bold text-gray-900">
            <?= sanitize($employee['first_name']) ?> <?= sanitize($employee['last_name']) ?>
          </h2>
          <?= statusBadge($employee['employment_status']) ?>
        </div>
        <p class="text-sm text-gray-500 mb-2">
          <?= $employee['position'] ? sanitize($employee['position']) : 'No position set' ?>
          <?php if ($employee['department_name']): ?>
            &bull; <?= sanitize($employee['department_name']) ?>
          <?php endif; ?>
        </p>
        <div class="flex flex-wrap gap-4 text-sm text-gray-500">
          <span class="flex items-center gap-1">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            <?= sanitize($employee['email']) ?>
          </span>
          <?php if ($employee['phone']): ?>
            <span class="flex items-center gap-1">
              <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              <?= sanitize($employee['phone']) ?>
            </span>
          <?php endif; ?>
          <span class="flex items-center gap-1">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
            <?= sanitize($employee['employee_id']) ?>
          </span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="flex items-center gap-2 shrink-0">
        <a href="<?= BASE_URL ?>/modules/employees/index.php"
           class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
          Back
        </a>
        <?php if (can('edit', 'employees')): ?>
          <a href="<?= BASE_URL ?>/modules/employees/edit.php?id=<?= $id ?>"
             class="inline-flex items-center gap-1.5 px-3 py-2 text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Edit
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Stat cards row -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 shadow-sm">
      <p class="text-xs text-gray-500 uppercase font-semibold tracking-wider mb-1">Hire Date</p>
      <p class="text-base font-semibold text-gray-800"><?= formatDate($employee['hire_date']) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 shadow-sm">
      <p class="text-xs text-gray-500 uppercase font-semibold tracking-wider mb-1">Employment Type</p>
      <p class="text-base font-semibold text-gray-800"><?= sanitize($employee['employment_type']) ?></p>
    </div>
    <?php if (!hasRole('Admin')): ?>
    <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 shadow-sm">
      <p class="text-xs text-gray-500 uppercase font-semibold tracking-wider mb-1">Monthly Salary</p>
      <p class="text-base font-semibold text-indigo-600"><?= formatMoney((float)$employee['salary']) ?></p>
    </div>
    <?php endif; ?>
    <div class="bg-white rounded-xl border border-gray-200 px-5 py-4 shadow-sm">
      <p class="text-xs text-gray-500 uppercase font-semibold tracking-wider mb-1">Department</p>
      <p class="text-base font-semibold text-gray-800"><?= $employee['department_name'] ? sanitize($employee['department_name']) : '—' ?></p>
    </div>
  </div>

  <!-- Tab navigation -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
    <div class="border-b border-gray-200 overflow-x-auto">
      <nav class="flex -mb-px px-4">
        <?php
        $tabs = [
            'personal'   => ['label' => 'Personal Info',   'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
            'employment' => ['label' => 'Employment',       'icon' => 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            'contact'    => ['label' => 'Contact Details',  'icon' => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'],
            'documents'  => ['label' => 'Documents',        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ];
        foreach ($tabs as $key => $tab):
            $isActive = $activeTab === $key;
        ?>
          <a href="?id=<?= $id ?>&tab=<?= $key ?>"
             class="flex items-center gap-2 py-3.5 px-4 text-sm font-medium border-b-2 transition-colors whitespace-nowrap
                    <?= $isActive ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $tab['icon'] ?>"/>
            </svg>
            <?= $tab['label'] ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>

    <!-- Tab content -->
    <div class="p-6">

      <!-- ── Personal Info ─────────────────────────────────────────────────── -->
      <?php if ($activeTab === 'personal'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php
          $fields = [
              'Full Name'    => sanitize($employee['first_name']) . ' ' . sanitize($employee['last_name']),
              'Employee ID'  => '<span class="font-mono text-indigo-700">' . sanitize($employee['employee_id']) . '</span>',
              'Gender'       => $employee['gender'] ? sanitize($employee['gender']) : '—',
              'Date of Birth'=> formatDate($employee['date_of_birth']),
              'Email'        => sanitize($employee['email']),
              'Phone'        => $employee['phone'] ? sanitize($employee['phone']) : '—',
          ];
          foreach ($fields as $label => $value):
          ?>
            <div>
              <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1"><?= $label ?></dt>
              <dd class="text-sm text-gray-800"><?= $value ?></dd>
            </div>
          <?php endforeach; ?>
        </div>

      <!-- ── Employment ────────────────────────────────────────────────────── -->
      <?php elseif ($activeTab === 'employment'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
          <?php
          $fields = [
              'Department'       => $employee['department_name'] ? sanitize($employee['department_name']) : '—',
              'Position'         => $employee['position'] ? sanitize($employee['position']) : '—',
              'Employment Type'  => sanitize($employee['employment_type']),
              'Employment Status'=> statusBadge($employee['employment_status']),
              'Hire Date'        => formatDate($employee['hire_date']),
              'Tax ID'           => $employee['tax_id'] ? sanitize($employee['tax_id']) : '—',
          ];
          if (!hasRole('Admin')) {
              $fields['Monthly Salary'] = '<span class="text-indigo-600 font-semibold">' . formatMoney((float)$employee['salary']) . '</span>';
          }
          foreach ($fields as $label => $value):
          ?>
            <div>
              <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1"><?= $label ?></dt>
              <dd class="text-sm text-gray-800"><?= $value ?></dd>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Leave balances -->
        <?php if (!empty($leaveBalances)): ?>
          <div class="mb-8">
            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
              Leave Balances — <?= $currentYear ?>
            </h4>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
              <?php foreach ($leaveBalances as $lb): ?>
                <div class="bg-indigo-50 rounded-lg p-3">
                  <p class="text-xs font-semibold text-indigo-700 mb-2"><?= sanitize($lb['leave_type_name']) ?></p>
                  <div class="flex items-center justify-between text-xs text-gray-600 mb-1.5">
                    <span>Used: <strong class="text-gray-800"><?= $lb['used'] ?></strong></span>
                    <span>Remaining: <strong class="text-green-700"><?= $lb['remaining'] ?></strong></span>
                    <span>Total: <strong><?= $lb['allocated'] ?></strong></span>
                  </div>
                  <div class="w-full bg-gray-200 rounded-full h-1.5">
                    <?php $pct = $lb['allocated'] > 0 ? round(($lb['used'] / $lb['allocated']) * 100) : 0; ?>
                    <div class="bg-indigo-500 h-1.5 rounded-full" style="width: <?= $pct ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Recent attendance -->
        <?php if (!empty($recentAttendance)): ?>
          <div class="mb-8">
            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Recent Attendance (Last 10 Records)
            </h4>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
              <table class="w-full text-sm">
                <thead>
                  <tr class="bg-gray-50 border-b border-gray-200">
                    <th class="text-left px-4 py-2.5 font-semibold text-gray-500 text-xs uppercase">Date</th>
                    <th class="text-left px-4 py-2.5 font-semibold text-gray-500 text-xs uppercase">Check In</th>
                    <th class="text-left px-4 py-2.5 font-semibold text-gray-500 text-xs uppercase">Check Out</th>
                    <th class="text-left px-4 py-2.5 font-semibold text-gray-500 text-xs uppercase">Hours</th>
                    <th class="text-left px-4 py-2.5 font-semibold text-gray-500 text-xs uppercase">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php foreach ($recentAttendance as $att): ?>
                    <tr class="hover:bg-gray-50">
                      <td class="px-4 py-2.5 text-gray-700"><?= formatDate($att['date']) ?></td>
                      <td class="px-4 py-2.5 text-gray-600"><?= $att['check_in'] ? date('h:i A', strtotime($att['check_in'])) : '—' ?></td>
                      <td class="px-4 py-2.5 text-gray-600"><?= $att['check_out'] ? date('h:i A', strtotime($att['check_out'])) : '—' ?></td>
                      <td class="px-4 py-2.5 text-gray-600"><?= $att['total_hours'] ? $att['total_hours'] . 'h' : '—' ?></td>
                      <td class="px-4 py-2.5"><?= statusBadge($att['status']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <!-- Recent payroll -->
        <?php if (!hasRole('Admin') && !empty($recentPayroll)): ?>
          <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
              <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Recent Payroll (Last 3 Months)
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
              <?php foreach ($recentPayroll as $pay):
                $monthName = date('F', mktime(0, 0, 0, $pay['month'], 1));
              ?>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                  <p class="text-xs font-semibold text-gray-500 uppercase mb-2"><?= $monthName ?> <?= $pay['year'] ?></p>
                  <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                      <span class="text-gray-500">Gross:</span>
                      <span class="font-medium"><?= formatMoney((float)$pay['gross_salary']) ?></span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-500">Deductions:</span>
                      <span class="text-red-600">-<?= formatMoney((float)$pay['total_deductions']) ?></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-100 pt-1 mt-1">
                      <span class="font-semibold text-gray-700">Net Pay:</span>
                      <span class="font-bold text-indigo-600"><?= formatMoney((float)$pay['net_salary']) ?></span>
                    </div>
                  </div>
                  <div class="mt-2"><?= statusBadge($pay['payment_status']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

      <!-- ── Contact Details ───────────────────────────────────────────────── -->
      <?php elseif ($activeTab === 'contact'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wider">Address</h4>
            <div class="space-y-3">
              <?php
              $addrFields = [
                  'Street Address' => $employee['address']     ? sanitize($employee['address'])     : '—',
                  'City'           => $employee['city']        ? sanitize($employee['city'])        : '—',
                  'State'          => $employee['state']       ? sanitize($employee['state'])       : '—',
                  'Country'        => $employee['country']     ? sanitize($employee['country'])     : '—',
                  'Postal Code'    => $employee['postal_code'] ? sanitize($employee['postal_code']) : '—',
              ];
              foreach ($addrFields as $label => $val):
              ?>
                <div>
                  <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-0.5"><?= $label ?></dt>
                  <dd class="text-sm text-gray-800"><?= $val ?></dd>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div>
            <h4 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wider">Emergency Contact</h4>
            <div class="space-y-3">
              <div>
                <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Contact Name</dt>
                <dd class="text-sm text-gray-800"><?= $employee['emergency_contact_name'] ? sanitize($employee['emergency_contact_name']) : '—' ?></dd>
              </div>
              <div>
                <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Contact Phone</dt>
                <dd class="text-sm text-gray-800"><?= $employee['emergency_contact_phone'] ? sanitize($employee['emergency_contact_phone']) : '—' ?></dd>
              </div>
            </div>

            <?php if (hasRole('Admin', 'HR Manager') && !hasRole('Employee', 'Head of Department')): ?>
              <h4 class="text-sm font-semibold text-gray-700 mt-6 mb-4 uppercase tracking-wider">Banking Information</h4>
              <div class="space-y-3">
                <div>
                  <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Bank Name</dt>
                  <dd class="text-sm text-gray-800"><?= $employee['bank_name'] ? sanitize($employee['bank_name']) : '—' ?></dd>
                </div>
                <div>
                  <dt class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Account Number</dt>
                  <dd class="text-sm text-gray-800 font-mono">
                    <?php if ($employee['bank_account']): ?>
                      <?= str_repeat('*', max(0, strlen($employee['bank_account']) - 4)) . substr($employee['bank_account'], -4) ?>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </dd>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <!-- ── Documents ─────────────────────────────────────────────────────── -->
      <?php elseif ($activeTab === 'documents'): ?>
        <?php if (empty($documents)): ?>
          <div class="text-center py-12 text-gray-400">
            <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="font-medium text-gray-500">No documents uploaded</p>
            <a href="<?= BASE_URL ?>/modules/documents/index.php?employee_id=<?= $id ?>"
               class="mt-3 inline-block text-sm text-indigo-600 hover:underline">Upload Documents</a>
          </div>
        <?php else: ?>
          <div class="space-y-2">
            <?php foreach ($documents as $doc): ?>
              <div class="flex items-center justify-between py-3 px-4 bg-gray-50 hover:bg-gray-100 rounded-lg transition-colors">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 bg-indigo-100 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <div>
                    <p class="text-sm font-medium text-gray-800"><?= sanitize($doc['title'] ?? $doc['file_name'] ?? 'Document') ?></p>
                    <p class="text-xs text-gray-400"><?= formatDate($doc['created_at']) ?></p>
                  </div>
                </div>
                <a href="<?= BASE_URL ?>/uploads/documents/<?= rawurlencode($doc['file_name'] ?? '') ?>"
                   target="_blank"
                   class="text-indigo-600 hover:text-indigo-800 text-xs font-medium flex items-center gap-1">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                  Download
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
