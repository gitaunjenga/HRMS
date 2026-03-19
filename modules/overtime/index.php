<?php
/**
 * HRMS - Overtime Requests
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Overtime';
$user      = currentUser();
$isHRAdmin = hasRole('Admin', 'HR Manager');
$isDM      = hasRole('Head of Department');

// Filters
$filterStatus = sanitize($_GET['status'] ?? '');
$filterEmp    = (int)($_GET['emp'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

$where  = ['1=1'];
$types  = '';
$params = [];

if ($isHRAdmin) {
    // See all
} elseif ($isDM) {
    $deptId = myDeptId();
    if ($deptId) {
        $where[]  = 'e.department_id = ?';
        $types   .= 'i';
        $params[] = $deptId;
    }
} else {
    // Employee: own only
    $empId = (int)($user['employee_id'] ?? 0);
    $where[]  = 'ot.employee_id = ?';
    $types   .= 'i';
    $params[] = $empId;
}

if ($filterStatus !== '') {
    $where[]  = 'ot.status = ?';
    $types   .= 's';
    $params[] = $filterStatus;
}
if ($filterEmp > 0 && ($isHRAdmin || $isDM)) {
    $where[]  = 'ot.employee_id = ?';
    $types   .= 'i';
    $params[] = $filterEmp;
}

$whereSQL = implode(' AND ', $where);

$total = (int)(fetchOne(
    "SELECT COUNT(*) AS c FROM overtime_requests ot
     JOIN employees e ON ot.employee_id = e.id
     WHERE {$whereSQL}",
    $types, ...$params
)['c'] ?? 0);

$pag  = paginate($total, $page, $perPage);
$rows = fetchAll(
    "SELECT ot.*,
            CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.photo AS emp_photo, e.employee_id AS emp_code,
            d.name AS dept_name
     FROM overtime_requests ot
     JOIN employees e ON ot.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE {$whereSQL}
     ORDER BY ot.created_at DESC
     LIMIT ? OFFSET ?",
    $types . 'ii', ...[...$params, $pag['perPage'], $pag['offset']]
);

$baseUrl = BASE_URL . '/modules/overtime/index.php?' . http_build_query(array_filter([
    'status' => $filterStatus, 'emp' => $filterEmp ?: '',
]));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <?php $flash = getFlash(); if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Overtime Requests</h1>
      <p class="text-sm text-gray-500 mt-0.5"><?= $total ?> request(s)</p>
    </div>
    <?php if (can('create', 'overtime')): ?>
      <a href="<?= BASE_URL ?>/modules/overtime/apply.php"
         class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Request Overtime
      </a>
    <?php endif; ?>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All Statuses</option>
          <?php foreach (['Pending','Approved','Rejected','Cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="self-end px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Filter</button>
      <a href="<?= BASE_URL ?>/modules/overtime/index.php" class="self-end px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Reset</a>
    </form>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <?php if ($isHRAdmin || $isDM): ?><th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee</th><?php endif; ?>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Date</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Time</th>
            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Hours</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Reason</th>
            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
            <?php if ($isHRAdmin || $isDM): ?><th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th><?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="7" class="px-5 py-16 text-center text-gray-400">
                <p class="font-medium">No overtime requests found</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <tr class="hover:bg-gray-50">
                <?php if ($isHRAdmin || $isDM): ?>
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                      <img src="<?= avatarUrl($row['emp_photo']) ?>" alt="" class="w-8 h-8 rounded-full object-cover shrink-0">
                      <div>
                        <p class="font-medium text-gray-900"><?= sanitize($row['emp_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= sanitize($row['dept_name'] ?? '—') ?></p>
                      </div>
                    </div>
                  </td>
                <?php endif; ?>
                <td class="px-4 py-3 text-gray-600"><?= formatDate($row['request_date']) ?></td>
                <td class="px-4 py-3 text-gray-600 font-mono text-xs">
                  <?= substr($row['start_time'],0,5) ?> – <?= substr($row['end_time'],0,5) ?>
                </td>
                <td class="px-4 py-3 text-center">
                  <span class="px-2 py-0.5 bg-orange-50 text-orange-700 rounded text-xs font-medium"><?= $row['hours_requested'] ?>h</span>
                </td>
                <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($row['reason']) ?>">
                  <?= htmlspecialchars(mb_strimwidth($row['reason'], 0, 60, '…')) ?>
                </td>
                <td class="px-4 py-3 text-center"><?= statusBadge($row['status']) ?></td>
                <?php if ($isHRAdmin || $isDM): ?>
                  <td class="px-4 py-3 text-center">
                    <?php if ($row['status'] === 'Pending'): ?>
                      <div class="flex items-center justify-center gap-1">
                        <form method="POST" action="<?= BASE_URL ?>/modules/overtime/approve.php">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= $row['id'] ?>">
                          <input type="hidden" name="action" value="approve">
                          <button type="submit" title="Approve"
                                  class="px-2.5 py-1 bg-green-100 hover:bg-green-200 text-green-700 text-xs font-medium rounded-lg transition-colors">
                            Approve
                          </button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/modules/overtime/approve.php">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= $row['id'] ?>">
                          <input type="hidden" name="action" value="reject">
                          <button type="submit" title="Reject"
                                  class="px-2.5 py-1 bg-red-100 hover:bg-red-200 text-red-700 text-xs font-medium rounded-lg transition-colors">
                            Reject
                          </button>
                        </form>
                      </div>
                    <?php else: ?>
                      <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPagination($pag, $baseUrl) ?>
  </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
