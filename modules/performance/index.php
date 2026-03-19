<?php
/**
 * HRMS - Performance Reviews List
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Performance Reviews';
$user      = currentUser();
$role      = $user['role'] ?? '';
$isHRAdmin = hasRole('Admin', 'HR Manager');
$isDM      = ($role === 'Head of Department');

// ── Filters ────────────────────────────────────────────────────────────────────
$filterStatus = sanitize($_GET['status'] ?? '');
$filterPeriod = sanitize($_GET['period'] ?? '');
$search       = sanitize($_GET['q'] ?? '');

// ── Pagination ─────────────────────────────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ── Build WHERE based on role ──────────────────────────────────────────────────
$where  = '1=1';
$types  = '';
$params = [];

if ($isHRAdmin) {
    // Admin / HR Manager: see all reviews
} elseif ($isDM) {
    // Head of Department: see reviews for employees in their department
    $deptId = myDeptId();
    if ($deptId) {
        $where   .= ' AND e.department_id = ?';
        $types   .= 'i';
        $params[] = $deptId;
    }
} else {
    // Employee: see only their own reviews (matched via users.employee_id)
    $empId = (int)($user['employee_id'] ?? 0);
    if (!$empId) {
        setFlash('error', 'No employee record found for your account.');
        header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
        exit;
    }
    $where   .= ' AND pr.employee_id = ?';
    $types   .= 'i';
    $params[] = $empId;
}

if ($filterStatus !== '') {
    $where   .= ' AND pr.status = ?';
    $types   .= 's';
    $params[] = $filterStatus;
}
if ($filterPeriod !== '') {
    $where   .= ' AND pr.review_period LIKE ?';
    $types   .= 's';
    $params[] = '%' . $filterPeriod . '%';
}
if ($search !== '') {
    $where   .= ' AND CONCAT(e.first_name," ",e.last_name) LIKE ?';
    $types   .= 's';
    $params[] = '%' . $search . '%';
}

$countSql  = "SELECT COUNT(*) AS c
              FROM performance_reviews pr
              JOIN employees e ON pr.employee_id = e.id
              WHERE {$where}";
$totalRows = (int)(fetchOne($countSql, $types, ...$params)['c'] ?? 0);
$pag       = paginate($totalRows, $page, $perPage);

$typesFull  = $types . 'ii';
$paramsFull = array_merge($params, [$pag['perPage'], $pag['offset']]);

$reviews = fetchAll(
    "SELECT pr.*,
            CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.photo AS emp_photo,
            e.employee_id AS emp_code,
            d.name AS dept_name,
            CONCAT(rv.first_name,' ',rv.last_name) AS reviewer_name
     FROM performance_reviews pr
     JOIN employees e  ON pr.employee_id = e.id
     LEFT JOIN departments d   ON e.department_id = d.id
     LEFT JOIN employees rv ON pr.reviewer_id = rv.id
     WHERE {$where}
     ORDER BY pr.review_date DESC, pr.id DESC
     LIMIT ? OFFSET ?",
    $typesFull, ...$paramsFull
);

$baseUrl = BASE_URL . '/modules/performance/index.php?status=' . urlencode($filterStatus)
         . '&period=' . urlencode($filterPeriod) . '&q=' . urlencode($search);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

function scoreColor(float $score): string {
    if ($score >= 8) return 'text-green-700 bg-green-100';
    if ($score >= 6) return 'text-blue-700 bg-blue-100';
    if ($score >= 4) return 'text-yellow-700 bg-yellow-100';
    return 'text-red-700 bg-red-100';
}
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
        <h1 class="text-2xl font-bold text-gray-900">Performance Reviews</h1>
        <p class="text-sm text-gray-500 mt-0.5">
          <?php if ($isHRAdmin): ?>
            All employee reviews
          <?php elseif ($isDM): ?>
            Your department's performance reviews
          <?php else: ?>
            Your performance reviews
          <?php endif; ?>
        </p>
      </div>
      <?php if (can('create', 'performance')): ?>
        <a href="<?= BASE_URL ?>/modules/performance/add_review.php"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          New Review
        </a>
      <?php endif; ?>
    </div>

    <!-- Filters -->
    <form method="GET" class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4">
      <div class="flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
          <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">All Statuses</option>
            <?php foreach (['Draft','Submitted','Acknowledged'] as $s): ?>
              <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Period</label>
          <input type="text" name="period" value="<?= htmlspecialchars($filterPeriod) ?>"
                 placeholder="e.g. Q1 2026"
                 class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-32">
        </div>
        <?php if ($isHRAdmin || $isDM): ?>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Search Employee</label>
          <div class="relative">
            <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Employee name…"
                   class="pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-48">
          </div>
        </div>
        <?php endif; ?>
        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Filter</button>
        <a href="<?= BASE_URL ?>/modules/performance/index.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Reset</a>
      </div>
    </form>

    <!-- Reviews Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">Reviews</h2>
        <span class="text-xs text-gray-400"><?= $totalRows ?> record(s)</span>
      </div>

      <?php if (empty($reviews)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-gray-400">
          <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
          </svg>
          <p class="text-sm font-medium">No performance reviews found</p>
          <?php if (can('create', 'performance')): ?>
            <a href="<?= BASE_URL ?>/modules/performance/add_review.php" class="mt-3 text-xs text-indigo-600 hover:underline">Create first review →</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Employee</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Reviewer</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Period</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Overall Score</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Review Date</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <?php foreach ($reviews as $rev):
                $score      = (float)($rev['overall_score'] ?? 0);
                $scoreClass = scoreColor($score);
              ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                      <img src="<?= avatarUrl($rev['emp_photo']) ?>" alt=""
                           class="w-8 h-8 rounded-full object-cover shrink-0">
                      <div>
                        <p class="font-medium text-gray-900"><?= sanitize($rev['emp_name']) ?></p>
                        <p class="text-xs text-gray-400"><?= sanitize($rev['emp_code']) ?> · <?= sanitize($rev['dept_name'] ?? '—') ?></p>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 text-gray-600"><?= sanitize($rev['reviewer_name'] ?? '—') ?></td>
                  <td class="px-4 py-3">
                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded text-xs font-medium">
                      <?= sanitize($rev['review_period']) ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <?php if ($score > 0): ?>
                      <span class="inline-flex items-center justify-center w-12 h-8 rounded-lg text-sm font-bold <?= $scoreClass ?>">
                        <?= number_format($score, 1) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400 text-xs">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-center"><?= statusBadge($rev['status']) ?></td>
                  <td class="px-4 py-3 text-gray-600"><?= formatDate($rev['review_date']) ?></td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-1">
                      <a href="<?= BASE_URL ?>/modules/performance/view_review.php?id=<?= $rev['id'] ?>"
                         title="View" class="p-1.5 rounded-lg text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                      </a>
                      <?php if (can('edit', 'performance')): ?>
                        <a href="<?= BASE_URL ?>/modules/performance/add_review.php?edit=<?= $rev['id'] ?>"
                           title="Edit" class="p-1.5 rounded-lg text-gray-500 hover:text-yellow-600 hover:bg-yellow-50 transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                          </svg>
                        </a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?= renderPagination($pag, $baseUrl) ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
