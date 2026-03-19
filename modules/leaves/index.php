<?php
/**
 * HRMS - Leave Requests List
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('view', 'leaves');

$pageTitle = 'Leave Management';
$user      = currentUser();
$role      = $user['role'] ?? '';
$isDM      = ($role === 'Head of Department');
$isHRMgr   = ($role === 'HR Manager');
$isAdmin   = ($role === 'Admin');
$isEmp     = ($role === 'Employee');
$isHR      = hasRole('Admin', 'HR Manager');

// ── Filters ───────────────────────────────────────────────────────────────────
$filterStatus = sanitize($_GET['status']    ?? '');
$filterEmp    = (int)($_GET['employee_id']  ?? 0);
$filterFrom   = sanitize($_GET['date_from'] ?? '');
$filterTo     = sanitize($_GET['date_to']   ?? '');
$page         = max(1, (int)($_GET['page']  ?? 1));
$perPage      = 20;

$whereClauses = ['1=1'];
$whereTypes   = '';
$whereParams  = [];

// Role-based data scoping
if ($isEmp) {
    $empId = (int)($user['employee_id'] ?? 0);
    $whereClauses[] = 'lr.employee_id = ?';
    $whereTypes    .= 'i';
    $whereParams[]  = $empId;
} elseif ($isDM) {
    // Department join handles the scoping; HR sees only post-DM-approval by default
} elseif ($isHRMgr) {
    // HR sees all requests that have passed (or bypassed) the DM stage
    // No extra WHERE needed — they see everything except 'Pending Department Approval'
    // unless they specifically filter for it
    if ($filterEmp > 0) {
        $whereClauses[] = 'lr.employee_id = ?';
        $whereTypes    .= 'i';
        $whereParams[]  = $filterEmp;
    }
} elseif ($isAdmin) {
    if ($filterEmp > 0) {
        $whereClauses[] = 'lr.employee_id = ?';
        $whereTypes    .= 'i';
        $whereParams[]  = $filterEmp;
    }
}

if ($filterStatus !== '') {
    $whereClauses[] = 'lr.status = ?';
    $whereTypes    .= 's';
    $whereParams[]  = $filterStatus;
}
if ($filterFrom !== '') {
    $whereClauses[] = 'lr.start_date >= ?';
    $whereTypes    .= 's';
    $whereParams[]  = $filterFrom;
}
if ($filterTo !== '') {
    $whereClauses[] = 'lr.end_date <= ?';
    $whereTypes    .= 's';
    $whereParams[]  = $filterTo;
}

$where = implode(' AND ', $whereClauses);

// Head of Department scoped JOIN
$deptJoin = '';
if ($isDM) {
    $deptId   = myDeptId();
    $deptJoin = ' AND e.department_id = ' . (int)$deptId;
}

// Count
$totalRow = fetchOne(
    "SELECT COUNT(*) AS c FROM leave_requests lr
     JOIN employees e ON lr.employee_id = e.id{$deptJoin}
     WHERE $where",
    $whereTypes, ...$whereParams
);
$total = (int)($totalRow['c'] ?? 0);
$pag   = paginate($total, $page, $perPage);

// Records — join both DM and HR reviewer usernames
$leaves = fetchAll(
    "SELECT lr.*, lt.name AS leave_type_name,
            CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.employee_id AS emp_code, e.photo,
            dm.username AS dm_reviewer_name,
            hr.username AS hr_reviewer_name
     FROM leave_requests lr
     JOIN employees  e   ON lr.employee_id   = e.id{$deptJoin}
     JOIN leave_types lt ON lr.leave_type_id = lt.id
     LEFT JOIN users dm  ON lr.dm_action_by  = dm.id
     LEFT JOIN users hr  ON lr.hr_action_by  = hr.id
     WHERE $where
     ORDER BY lr.created_at DESC
     LIMIT ? OFFSET ?",
    $whereTypes . 'ii', ...[...$whereParams, $perPage, $pag['offset']]
);

// Employees for filter dropdown (HR/Admin only)
$employees = $isHR ? fetchAll(
    "SELECT id, employee_id, first_name, last_name FROM employees
     WHERE employment_status = 'Active' ORDER BY first_name, last_name"
) : [];

$allStatuses = [
    'Pending Department Approval',
    'Pending HR Approval',
    'Approved',
    'Rejected by Head of Department',
    'Rejected by HR',
    'Cancelled',
];

$baseUrl = BASE_URL . '/modules/leaves/index.php?' . http_build_query(array_filter([
    'status'      => $filterStatus,
    'employee_id' => $filterEmp ?: null,
    'date_from'   => $filterFrom,
    'date_to'     => $filterTo,
]));

// Role-specific pending counts for the banner
$pendingCount = 0;
if ($isDM) {
    $myDept = myDeptId();
    if ($myDept) {
        $row = fetchOne(
            "SELECT COUNT(*) AS c FROM leave_requests lr
             JOIN employees e ON lr.employee_id = e.id
             WHERE lr.status = 'Pending Department Approval' AND e.department_id = ?",
            'i', $myDept
        );
        $pendingCount = (int)($row['c'] ?? 0);
    }
} elseif ($isHRMgr) {
    $row = fetchOne(
        "SELECT COUNT(*) AS c FROM leave_requests WHERE status = 'Pending HR Approval'"
    );
    $pendingCount = (int)($row['c'] ?? 0);
}

$canApprove = can('approve', 'leaves');
$flash      = getFlash();

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

    <!-- Pending Banner -->
    <?php if ($pendingCount > 0): ?>
      <?php $pendingStatus = $isDM ? 'Pending Department Approval' : 'Pending HR Approval'; ?>
      <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <p class="font-semibold text-amber-900">
              <?= $pendingCount ?> leave request<?= $pendingCount !== 1 ? 's' : '' ?> awaiting your review
            </p>
            <p class="text-sm text-amber-700">
              <?= $isDM ? 'Pending department approval.' : 'Pending HR approval — already approved by Head of Department.' ?>
            </p>
          </div>
        </div>
        <a href="?status=<?= urlencode($pendingStatus) ?>"
           class="text-sm font-semibold text-amber-800 underline hover:no-underline whitespace-nowrap">
          Review Now →
        </a>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Leave Requests</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= $total ?> record<?= $total !== 1 ? 's' : '' ?></p>
      </div>
      <?php if (can('create', 'leaves')): ?>
        <a href="<?= BASE_URL ?>/modules/leaves/apply.php"
           class="inline-flex items-center gap-2 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors shadow-sm"
           style="background:#0B2545;" onmouseover="this.style.background='#1a3a6b'" onmouseout="this.style.background='#0B2545'">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Apply for Leave
        </a>
      <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="GET" action="" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">Status</label>
          <select name="status"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <?php foreach ($allStatuses as $s): ?>
              <option value="<?= htmlspecialchars($s) ?>" <?= $filterStatus === $s ? 'selected' : '' ?>>
                <?= htmlspecialchars($s) ?>
              </option>
            <?php endforeach; ?>
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
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">From Date</label>
          <input type="date" name="date_from" value="<?= htmlspecialchars($filterFrom) ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">To Date</label>
          <input type="date" name="date_to" value="<?= htmlspecialchars($filterTo) ?>"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="flex items-end">
          <div class="flex gap-2 w-full">
            <button type="submit"
                    class="flex-1 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
                    style="background:#0B2545;">
              Filter
            </button>
            <a href="<?= BASE_URL ?>/modules/leaves/index.php"
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
              <?php if ($isHR || $isDM): ?>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employee</th>
              <?php endif; ?>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Leave Type</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Dates</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Days</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Progress</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Action</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-50">
            <?php if (empty($leaves)): ?>
              <tr>
                <td colspan="<?= ($isHR || $isDM) ? 7 : 6 ?>" class="px-5 py-10 text-center text-sm text-gray-400">
                  No leave requests found.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($leaves as $lr): ?>
                <tr class="hover:bg-gray-50 transition-colors">

                  <?php if ($isHR || $isDM): ?>
                    <td class="px-5 py-3">
                      <div class="flex items-center gap-2.5">
                        <img src="<?= avatarUrl($lr['photo']) ?>" alt="" class="w-8 h-8 rounded-full object-cover">
                        <div>
                          <p class="text-sm font-medium text-gray-900"><?= sanitize($lr['emp_name']) ?></p>
                          <p class="text-xs text-gray-400"><?= sanitize($lr['emp_code']) ?></p>
                        </div>
                      </div>
                    </td>
                  <?php endif; ?>

                  <td class="px-5 py-3">
                    <span class="text-sm font-medium text-gray-900"><?= sanitize($lr['leave_type_name']) ?></span>
                    <?php if ($lr['attachment']): ?>
                      <br><a href="<?= BASE_URL ?>/uploads/leave_attachments/<?= rawurlencode($lr['attachment']) ?>"
                             target="_blank" class="text-xs text-indigo-600 hover:underline">📎 Attachment</a>
                    <?php endif; ?>
                  </td>

                  <td class="px-5 py-3">
                    <p class="text-sm text-gray-900"><?= formatDate($lr['start_date']) ?></p>
                    <?php if ($lr['start_date'] !== $lr['end_date']): ?>
                      <p class="text-xs text-gray-400">to <?= formatDate($lr['end_date']) ?></p>
                    <?php endif; ?>
                  </td>

                  <td class="px-5 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-sm font-semibold">
                      <?= (int)$lr['total_days'] ?>
                    </span>
                  </td>

                  <td class="px-5 py-3 text-center"><?= statusBadge($lr['status']) ?></td>

                  <!-- Progress: show who has acted -->
                  <td class="px-5 py-3">
                    <div class="space-y-0.5">
                      <?php if ($lr['dm_action_by']): ?>
                        <p class="text-xs text-gray-500">
                          <span class="font-medium">DM:</span>
                          <?= in_array($lr['status'], ['Rejected by Head of Department']) ? '✗' : '✓' ?>
                          <?= sanitize($lr['dm_reviewer_name'] ?? '') ?>
                        </p>
                      <?php else: ?>
                        <p class="text-xs text-gray-400">DM: Pending</p>
                      <?php endif; ?>
                      <?php if ($lr['hr_action_by']): ?>
                        <p class="text-xs text-gray-500">
                          <span class="font-medium">HR:</span>
                          <?= $lr['status'] === 'Rejected by HR' ? '✗' : '✓' ?>
                          <?= sanitize($lr['hr_reviewer_name'] ?? '') ?>
                        </p>
                      <?php elseif (!in_array($lr['status'], ['Rejected by Head of Department','Cancelled'])): ?>
                        <p class="text-xs text-gray-400">HR: Pending</p>
                      <?php endif; ?>
                    </div>
                  </td>

                  <!-- Action column -->
                  <td class="px-5 py-3 text-center">
                    <?php
                    $canActDM = $isDM && $lr['status'] === 'Pending Department Approval';
                    $canActHR = $isHRMgr && $lr['status'] === 'Pending HR Approval';
                    ?>
                    <?php if ($canActDM || $canActHR): ?>
                      <a href="<?= BASE_URL ?>/modules/leaves/view.php?id=<?= (int)$lr['id'] ?>"
                         class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors"
                         style="background:#0B2545; color:#fff;">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        Review
                      </a>
                    <?php else: ?>
                      <a href="<?= BASE_URL ?>/modules/leaves/view.php?id=<?= (int)$lr['id'] ?>"
                         class="text-xs text-indigo-600 hover:underline">View</a>
                    <?php endif; ?>
                  </td>

                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?= renderPagination($pag, $baseUrl) ?>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
