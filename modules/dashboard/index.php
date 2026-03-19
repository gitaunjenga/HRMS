<?php
/**
 * HRMS - Dashboard
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Dashboard';
$user      = currentUser();
$role      = $user['role'] ?? '';
$isAdmin   = ($role === 'Admin');
$isHR      = ($role === 'HR Manager');
$isHRAdmin = hasRole('Admin', 'HR Manager');
$isDM      = ($role === 'Head of Department');
$isEmp     = ($role === 'Employee');

// ── Role-specific data ────────────────────────────────────────────────────────

if ($isHRAdmin) {
    // ── Global stats for Admin / HR Manager ──────────────────────────────────
    $totalEmployees   = fetchOne("SELECT COUNT(*) AS c FROM employees WHERE employment_status = 'Active'")['c'];
    $totalDepartments = fetchOne("SELECT COUNT(*) AS c FROM departments")['c'];
    $pendingLeaves    = fetchOne("SELECT COUNT(*) AS c FROM leave_requests WHERE status = 'Pending'")['c'];
    $todayAttendance  = fetchOne("SELECT COUNT(*) AS c FROM attendance WHERE date = CURDATE() AND status = 'Present'")['c'];
    $monthPayroll     = fetchOne("SELECT SUM(net_salary) AS s FROM payroll WHERE month = MONTH(NOW()) AND year = YEAR(NOW()) AND payment_status = 'Paid'")['s'] ?? 0;
    $openJobs         = fetchOne("SELECT COUNT(*) AS c FROM job_postings WHERE status = 'Open'")['c'];

    // Recent Employees
    $recentEmployees = fetchAll(
        "SELECT e.*, d.name AS dept_name FROM employees e
         LEFT JOIN departments d ON e.department_id = d.id
         ORDER BY e.created_at DESC LIMIT 5"
    );

    // Recent Leave Requests
    $recentLeaves = fetchAll(
        "SELECT lr.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, lt.name AS leave_type
         FROM leave_requests lr
         JOIN employees e ON lr.employee_id = e.id
         JOIN leave_types lt ON lr.leave_type_id = lt.id
         ORDER BY lr.created_at DESC LIMIT 5"
    );

    // Department Distribution
    $deptDist = fetchAll(
        "SELECT d.name, COUNT(e.id) AS cnt FROM departments d
         LEFT JOIN employees e ON d.id = e.department_id AND e.employment_status = 'Active'
         GROUP BY d.id, d.name ORDER BY cnt DESC"
    );

    // Monthly Attendance (last 6 months)
    $attendance6m = fetchAll(
        "SELECT DATE_FORMAT(date, '%b %Y') AS month,
                SUM(status = 'Present') AS present,
                SUM(status = 'Absent') AS absent
         FROM attendance
         WHERE date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY YEAR(date), MONTH(date)
         ORDER BY MIN(date)"
    );

} elseif ($isDM) {
    // ── Head of Department stats ──────────────────────────────────────────────
    $deptId = myDeptId();

    $deptHeadcount   = $deptId ? (int)(fetchOne("SELECT COUNT(*) AS c FROM employees WHERE department_id = ? AND employment_status = 'Active'", 'i', $deptId)['c'] ?? 0) : 0;
    $deptPendLeaves  = $deptId ? (int)(fetchOne("SELECT COUNT(*) AS c FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE e.department_id = ? AND lr.status = 'Pending'", 'i', $deptId)['c'] ?? 0) : 0;
    $deptAttToday    = $deptId ? (int)(fetchOne("SELECT COUNT(*) AS c FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE e.department_id = ? AND a.date = CURDATE() AND a.status = 'Present'", 'i', $deptId)['c'] ?? 0) : 0;

    // Department name
    $deptName = $deptId ? (fetchOne("SELECT name FROM departments WHERE id = ?", 'i', $deptId)['name'] ?? 'Your Department') : 'Your Department';

    // Recent leave requests for dept
    $recentLeaves = $deptId ? fetchAll(
        "SELECT lr.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, lt.name AS leave_type
         FROM leave_requests lr
         JOIN employees e ON lr.employee_id = e.id
         JOIN leave_types lt ON lr.leave_type_id = lt.id
         WHERE e.department_id = ?
         ORDER BY lr.created_at DESC LIMIT 5",
        'i', $deptId
    ) : [];

    // Dept employees
    $recentEmployees = $deptId ? fetchAll(
        "SELECT e.*, d.name AS dept_name FROM employees e
         LEFT JOIN departments d ON e.department_id = d.id
         WHERE e.department_id = ? AND e.employment_status = 'Active'
         ORDER BY e.first_name LIMIT 5",
        'i', $deptId
    ) : [];

    // Attendance chart for dept (last 6 months)
    $attendance6m = $deptId ? fetchAll(
        "SELECT DATE_FORMAT(a.date, '%b %Y') AS month,
                SUM(a.status = 'Present') AS present,
                SUM(a.status = 'Absent') AS absent
         FROM attendance a
         JOIN employees e ON a.employee_id = e.id
         WHERE e.department_id = ? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY YEAR(a.date), MONTH(a.date)
         ORDER BY MIN(a.date)",
        'i', $deptId
    ) : [];

    $deptDist = [];

} else {
    // ── Employee stats ────────────────────────────────────────────────────────
    $empId = (int)($user['employee_id'] ?? 0);

    // My attendance this month
    $myAttThisMonth = $empId ? (int)(fetchOne(
        "SELECT COUNT(*) AS c FROM attendance WHERE employee_id = ? AND MONTH(date) = MONTH(NOW()) AND YEAR(date) = YEAR(NOW()) AND status = 'Present'",
        'i', $empId
    )['c'] ?? 0) : 0;

    // My leave balance (approved leaves this year)
    $myLeaveDaysTaken = $empId ? (int)(fetchOne(
        "SELECT COALESCE(SUM(total_days), 0) AS s FROM leave_requests WHERE employee_id = ? AND status = 'Approved' AND YEAR(start_date) = YEAR(NOW())",
        'i', $empId
    )['s'] ?? 0) : 0;

    // My last payslip
    $myLastPayslip = $empId ? fetchOne(
        "SELECT net_salary, month, year, payment_status FROM payroll WHERE employee_id = ? ORDER BY year DESC, month DESC LIMIT 1",
        'i', $empId
    ) : null;

    // My pending leave requests
    $myPendingLeaves = $empId ? (int)(fetchOne(
        "SELECT COUNT(*) AS c FROM leave_requests WHERE employee_id = ? AND status = 'Pending'",
        'i', $empId
    )['c'] ?? 0) : 0;

    $attendance6m    = [];
    $deptDist        = [];
    $recentEmployees = [];
    $recentLeaves    = [];
}

// ── Announcements (all roles) ──────────────────────────────────────────────────
$announcements = fetchAll(
    "SELECT a.*, u.username AS author FROM announcements a
     JOIN users u ON a.author_id = u.id
     WHERE a.is_active = 1 ORDER BY a.created_at DESC LIMIT 3"
);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Flash -->
    <?php $flash = getFlash(); if ($flash): ?>
      <div class="flash-message alert alert-<?= $flash['type'] ?>">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- ── Role Welcome Banner ───────────────────────────────────────────────── -->
    <?php
    $bannerConfig = [
        'Admin'              => ['bg' => 'from-slate-700 to-slate-900',   'label' => 'Administrator',        'desc' => 'Full system access — manage employees, departments, settings and all reports.'],
        'HR Manager'         => ['bg' => 'from-indigo-600 to-indigo-800', 'label' => 'HR Manager',           'desc' => 'Manage the workforce — process payroll, handle leave requests, upload documents and run reports.'],
        'Head of Department' => ['bg' => 'from-teal-600 to-teal-800',     'label' => 'Head of Department',   'desc' => 'Oversee your team — review attendance, manage leave requests and conduct performance reviews.'],
        'Employee'           => ['bg' => 'from-blue-500 to-blue-700',     'label' => 'Employee',             'desc' => 'Track your attendance, apply for leave, view payslips and check your performance reviews.'],
    ];
    $bc = $bannerConfig[$role] ?? ['bg' => 'from-gray-600 to-gray-800', 'label' => $role, 'desc' => ''];
    ?>
    <div class="bg-gradient-to-r <?= $bc['bg'] ?> rounded-xl p-5 text-white shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <p class="text-xs font-semibold uppercase tracking-widest opacity-75 mb-0.5"><?= htmlspecialchars($bc['label']) ?></p>
          <h2 class="text-xl font-bold">Welcome back, <?= htmlspecialchars($user['username'] ?? 'User') ?>!</h2>
          <p class="text-sm opacity-80 mt-1"><?= htmlspecialchars($bc['desc']) ?></p>
        </div>
        <div class="text-sm opacity-70 whitespace-nowrap"><?= date('l, F j, Y') ?></div>
      </div>
    </div>

    <!-- ── Stats Row ─────────────────────────────────────────────────────────── -->
    <?php if ($isHRAdmin): ?>
      <!-- Global stats for Admin / HR Manager -->
      <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <?php
        $stats = [
          ['label'=>'Active Employees','value'=>$totalEmployees,'icon'=>'👥','color'=>'bg-blue-500','link'=>'/modules/employees/index.php'],
          ['label'=>'Departments','value'=>$totalDepartments,'icon'=>'🏢','color'=>'bg-purple-500','link'=>'/modules/departments/index.php'],
          ['label'=>'Pending Leaves','value'=>$pendingLeaves,'icon'=>'📅','color'=>'bg-yellow-500','link'=>'/modules/leaves/index.php'],
          ['label'=>'Present Today','value'=>$todayAttendance,'icon'=>'⏰','color'=>'bg-green-500','link'=>'/modules/attendance/index.php'],
          ['label'=>'Monthly Payroll','value'=>formatMoney((float)$monthPayroll),'icon'=>'💵','color'=>'bg-emerald-500','link'=>'/modules/payroll/index.php'],
          ['label'=>'Open Positions','value'=>$openJobs,'icon'=>'💼','color'=>'bg-orange-500','link'=>'/modules/recruitment/index.php'],
        ];
        foreach ($stats as $s): ?>
          <a href="<?= BASE_URL . $s['link'] ?>"
             class="bg-white rounded-xl p-4 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between mb-2">
              <span class="text-2xl"><?= $s['icon'] ?></span>
              <span class="w-2 h-2 rounded-full <?= $s['color'] ?>"></span>
            </div>
            <p class="text-2xl font-bold text-gray-900"><?= $s['value'] ?></p>
            <p class="text-xs text-gray-500 mt-0.5"><?= $s['label'] ?></p>
          </a>
        <?php endforeach; ?>
      </div>

    <?php elseif ($isDM): ?>
      <!-- Head of Department stats -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <?php
        $dmStats = [
          ['label' => 'Team Members', 'value' => $deptHeadcount, 'icon' => '👥', 'color' => 'bg-teal-500', 'desc' => 'Active in ' . htmlspecialchars($deptName)],
          ['label' => 'Pending Leave Requests', 'value' => $deptPendLeaves, 'icon' => '📅', 'color' => 'bg-yellow-500', 'desc' => 'Awaiting review'],
          ['label' => 'Present Today', 'value' => $deptAttToday, 'icon' => '⏰', 'color' => 'bg-green-500', 'desc' => 'In your department'],
        ];
        foreach ($dmStats as $s): ?>
          <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
            <div class="flex items-center justify-between mb-3">
              <span class="text-3xl"><?= $s['icon'] ?></span>
              <span class="w-2.5 h-2.5 rounded-full <?= $s['color'] ?>"></span>
            </div>
            <p class="text-3xl font-bold text-gray-900"><?= $s['value'] ?></p>
            <p class="text-sm font-medium text-gray-700 mt-0.5"><?= $s['label'] ?></p>
            <p class="text-xs text-gray-400 mt-0.5"><?= $s['desc'] ?></p>
          </div>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <!-- Employee personal stats -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
          <div class="flex items-center justify-between mb-3">
            <span class="text-3xl">⏰</span>
            <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
          </div>
          <p class="text-3xl font-bold text-gray-900"><?= $myAttThisMonth ?></p>
          <p class="text-sm font-medium text-gray-700 mt-0.5">Days Present</p>
          <p class="text-xs text-gray-400 mt-0.5">This month</p>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
          <div class="flex items-center justify-between mb-3">
            <span class="text-3xl">📅</span>
            <span class="w-2.5 h-2.5 rounded-full bg-yellow-500"></span>
          </div>
          <p class="text-3xl font-bold text-gray-900"><?= $myLeaveDaysTaken ?></p>
          <p class="text-sm font-medium text-gray-700 mt-0.5">Leave Days Taken</p>
          <p class="text-xs text-gray-400 mt-0.5">This year (approved) · <?= $myPendingLeaves ?> pending</p>
        </div>

        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-200">
          <div class="flex items-center justify-between mb-3">
            <span class="text-3xl">💵</span>
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
          </div>
          <?php if ($myLastPayslip): ?>
            <p class="text-2xl font-bold text-gray-900"><?= formatMoney((float)$myLastPayslip['net_salary']) ?></p>
            <p class="text-sm font-medium text-gray-700 mt-0.5">Last Payslip</p>
            <p class="text-xs text-gray-400 mt-0.5">
              <?= date('F', mktime(0,0,0,(int)$myLastPayslip['month'],1)) ?> <?= $myLastPayslip['year'] ?>
              · <?= statusBadge($myLastPayslip['payment_status']) ?>
            </p>
          <?php else: ?>
            <p class="text-2xl font-bold text-gray-400">—</p>
            <p class="text-sm font-medium text-gray-700 mt-0.5">Last Payslip</p>
            <p class="text-xs text-gray-400 mt-0.5">No payslip found</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- ── Quick Actions ─────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
      <h3 class="font-semibold text-gray-800 mb-4 text-sm uppercase tracking-wide">Quick Actions</h3>
      <div class="flex flex-wrap gap-3">

        <?php if ($isAdmin): ?>
          <a href="<?= BASE_URL ?>/modules/employees/add.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>👤</span> Add Employee
          </a>
          <a href="<?= BASE_URL ?>/modules/departments/index.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>🏢</span> Manage Departments
          </a>
          <a href="<?= BASE_URL ?>/modules/reports/index.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>📊</span> View Reports
          </a>

        <?php elseif ($isHR): ?>
          <a href="<?= BASE_URL ?>/modules/payroll/index.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>💵</span> Process Payroll
          </a>
          <a href="<?= BASE_URL ?>/modules/leaves/index.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>📅</span> Review Leave Requests
          </a>
          <a href="<?= BASE_URL ?>/modules/recruitment/index.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>💼</span> Post Job
          </a>
          <a href="<?= BASE_URL ?>/modules/performance/add_review.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>⭐</span> Add Review
          </a>

        <?php elseif ($isDM): ?>
          <a href="<?= BASE_URL ?>/modules/employees/index.php<?= $deptId ? '?dept=' . $deptId : '' ?>"
             class="inline-flex items-center gap-2 px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>👥</span> View My Team
          </a>
          <a href="<?= BASE_URL ?>/modules/leaves/index.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>📅</span> Review Leave Requests
          </a>
          <a href="<?= BASE_URL ?>/modules/performance/add_review.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>⭐</span> Add Performance Review
          </a>

        <?php else: ?>
          <a href="<?= BASE_URL ?>/modules/leaves/apply.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>📅</span> Apply for Leave
          </a>
          <a href="<?= BASE_URL ?>/modules/attendance/checkin.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>⏰</span> Check In
          </a>
          <a href="<?= BASE_URL ?>/modules/payroll/index.php"
             class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
            <span>💵</span> View Payslip
          </a>
        <?php endif; ?>

      </div>
    </div>

    <!-- ── Charts Row (Admin/HR Manager/DM) ──────────────────────────────────── -->
    <?php if ($isHRAdmin || $isDM): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Attendance Chart -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-4">
          Attendance Overview (6 Months)<?= $isDM ? ' — ' . htmlspecialchars($deptName) : '' ?>
        </h3>
        <div class="h-56">
          <canvas id="attendanceChart"></canvas>
        </div>
      </div>

      <?php if ($isHRAdmin): ?>
      <!-- Department Distribution -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Employees by Department</h3>
        <div class="h-56">
          <canvas id="deptChart"></canvas>
        </div>
      </div>
      <?php else: ?>
      <!-- DM: Recent dept leave requests -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800">Team Leave Requests</h3>
          <a href="<?= BASE_URL ?>/modules/leaves/index.php" class="text-xs text-indigo-600 hover:underline">View all →</a>
        </div>
        <div class="divide-y divide-gray-50">
          <?php foreach ($recentLeaves as $lr): ?>
            <div class="flex items-center justify-between px-5 py-3">
              <div>
                <p class="text-sm font-medium text-gray-900"><?= sanitize($lr['emp_name']) ?></p>
                <p class="text-xs text-gray-500"><?= sanitize($lr['leave_type']) ?> · <?= $lr['total_days'] ?> day(s)</p>
              </div>
              <?= statusBadge($lr['status']) ?>
            </div>
          <?php endforeach; ?>
          <?php if (empty($recentLeaves)): ?>
            <p class="px-5 py-4 text-sm text-gray-400 text-center">No leave requests from your team.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Tables Row (Admin / HR Manager) ───────────────────────────────────── -->
    <?php if ($isHRAdmin): ?>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

      <!-- Recent Employees -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800">Recent Employees</h3>
          <a href="<?= BASE_URL ?>/modules/employees/index.php" class="text-xs text-indigo-600 hover:underline">View all →</a>
        </div>
        <div class="divide-y divide-gray-50">
          <?php foreach ($recentEmployees as $emp): ?>
            <div class="flex items-center gap-3 px-5 py-3">
              <img src="<?= avatarUrl($emp['photo']) ?>" alt="" class="w-9 h-9 rounded-full object-cover">
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">
                  <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                </p>
                <p class="text-xs text-gray-500 truncate"><?= sanitize($emp['position'] ?? '—') ?> · <?= sanitize($emp['dept_name'] ?? '—') ?></p>
              </div>
              <?= statusBadge($emp['employment_status']) ?>
            </div>
          <?php endforeach; ?>
          <?php if (empty($recentEmployees)): ?>
            <p class="px-5 py-4 text-sm text-gray-400 text-center">No employees yet.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Recent Leave Requests -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800">Recent Leave Requests</h3>
          <a href="<?= BASE_URL ?>/modules/leaves/index.php" class="text-xs text-indigo-600 hover:underline">View all →</a>
        </div>
        <div class="divide-y divide-gray-50">
          <?php foreach ($recentLeaves as $lr): ?>
            <div class="flex items-center justify-between px-5 py-3">
              <div>
                <p class="text-sm font-medium text-gray-900"><?= sanitize($lr['emp_name']) ?></p>
                <p class="text-xs text-gray-500"><?= sanitize($lr['leave_type']) ?> · <?= $lr['total_days'] ?> day(s)</p>
              </div>
              <?= statusBadge($lr['status']) ?>
            </div>
          <?php endforeach; ?>
          <?php if (empty($recentLeaves)): ?>
            <p class="px-5 py-4 text-sm text-gray-400 text-center">No leave requests.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── Head of Department: team members list ──────────────────────────── -->
    <?php elseif ($isDM): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Team Members — <?= htmlspecialchars($deptName) ?></h3>
        <a href="<?= BASE_URL ?>/modules/employees/index.php<?= $deptId ? '?dept=' . $deptId : '' ?>" class="text-xs text-indigo-600 hover:underline">View all →</a>
      </div>
      <div class="divide-y divide-gray-50">
        <?php foreach ($recentEmployees as $emp): ?>
          <div class="flex items-center gap-3 px-5 py-3">
            <img src="<?= avatarUrl($emp['photo']) ?>" alt="" class="w-9 h-9 rounded-full object-cover">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-gray-900 truncate">
                <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
              </p>
              <p class="text-xs text-gray-500 truncate"><?= sanitize($emp['position'] ?? '—') ?></p>
            </div>
            <?= statusBadge($emp['employment_status']) ?>
          </div>
        <?php endforeach; ?>
        <?php if (empty($recentEmployees)): ?>
          <p class="px-5 py-4 text-sm text-gray-400 text-center">No team members found.</p>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Announcements (all roles) ─────────────────────────────────────────── -->
    <?php if ($announcements): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
      <h3 class="font-semibold text-gray-800 mb-4">Announcements</h3>
      <div class="space-y-3">
        <?php foreach ($announcements as $ann): ?>
          <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-100">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="font-medium text-indigo-900 text-sm"><?= sanitize($ann['title']) ?></p>
                <p class="text-sm text-indigo-700 mt-1"><?= sanitize($ann['content']) ?></p>
              </div>
              <span class="text-xs text-indigo-400 whitespace-nowrap"><?= timeAgo($ann['created_at']) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if ($isHRAdmin || $isDM): ?>
<script>
// Attendance bar chart
hrmsCharts.bar('attendanceChart',
  <?= json_encode(array_column($attendance6m, 'month')) ?>,
  [
    { label: 'Present', data: <?= json_encode(array_map('intval', array_column($attendance6m, 'present'))) ?>, backgroundColor: '#6366f1' },
    { label: 'Absent',  data: <?= json_encode(array_map('intval', array_column($attendance6m, 'absent'))) ?>,  backgroundColor: '#f87171' }
  ]
);

<?php if ($isHRAdmin): ?>
// Department bar chart
hrmsCharts.bar('deptChart',
  <?= json_encode(array_column($deptDist, 'name')) ?>,
  [{
    label: 'Employees',
    data: <?= json_encode(array_map('intval', array_column($deptDist, 'cnt'))) ?>,
    backgroundColor: '#2EC4B6',
    borderColor: '#0B2545',
    borderWidth: 1,
    borderRadius: 4,
  }],
  { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false } } }
);
<?php endif; ?>
</script>
<?php endif; ?>
