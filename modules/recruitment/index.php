<?php
/**
 * HRMS - Recruitment: Job Postings List
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('view', 'recruitment');

$pageTitle = 'Recruitment';
$user      = currentUser();
$role      = $user['role'] ?? '';
$isHRMgr   = ($role === 'HR Manager');
$isAdmin   = ($role === 'Admin');

// ── Filters ────────────────────────────────────────────────────────────────────
$filterStatus = sanitize($_GET['status'] ?? '');
$search       = sanitize($_GET['q'] ?? '');

// ── Pagination ─────────────────────────────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

// Build WHERE clause
$where  = '1=1';
$types  = '';
$params = [];

if ($filterStatus !== '') {
    $where   .= ' AND jp.status = ?';
    $types   .= 's';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where   .= ' AND (jp.title LIKE ? OR jp.location LIKE ? OR d.name LIKE ?)';
    $types   .= 'sss';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$countSql  = "SELECT COUNT(*) AS c
              FROM job_postings jp
              LEFT JOIN departments d ON jp.department_id = d.id
              WHERE {$where}";
$totalRows = (int)(fetchOne($countSql, $types, ...$params)['c'] ?? 0);
$pag       = paginate($totalRows, $page, $perPage);

$dataSql = "SELECT jp.*,
                   d.name AS dept_name,
                   u.username AS posted_by_name,
                   (SELECT COUNT(*) FROM candidates c WHERE c.job_id = jp.id) AS applicant_count
            FROM job_postings jp
            LEFT JOIN departments d ON jp.department_id = d.id
            LEFT JOIN users u ON jp.posted_by = u.id
            WHERE {$where}
            ORDER BY jp.id DESC
            LIMIT ? OFFSET ?";

$typesFull    = $types . 'ii';
$paramsFull   = array_merge($params, [$pag['perPage'], $pag['offset']]);
$jobs = fetchAll($dataSql, $typesFull, ...$paramsFull);

// ── Summary ────────────────────────────────────────────────────────────────────
$summary = fetchAll(
    "SELECT status, COUNT(*) AS cnt FROM job_postings GROUP BY status"
);
$statusCount = [];
foreach ($summary as $s) {
    $statusCount[$s['status']] = (int)$s['cnt'];
}

// ── Base URL for pagination ────────────────────────────────────────────────────
$baseUrl = BASE_URL . '/modules/recruitment/index.php?status=' . urlencode($filterStatus) . '&q=' . urlencode($search);

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
        <h1 class="text-2xl font-bold text-gray-900">Recruitment</h1>
        <p class="text-sm text-gray-500 mt-0.5">Manage job postings and track applicants</p>
      </div>
      <?php if ($isHRMgr): ?>
        <a href="<?= BASE_URL ?>/modules/recruitment/add_job.php"
           class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          Post a Job
        </a>
      <?php endif; ?>
    </div>

    <!-- Status Pills -->
    <div class="flex flex-wrap gap-2">
      <?php
      $allCount  = array_sum($statusCount);
      $statuses  = ['' => 'All', 'Open' => 'Open', 'Closed' => 'Closed', 'On Hold' => 'On Hold'];
      foreach ($statuses as $val => $label):
        $cnt    = $val === '' ? $allCount : ($statusCount[$val] ?? 0);
        $active = $filterStatus === $val;
      ?>
        <a href="<?= BASE_URL ?>/modules/recruitment/index.php?status=<?= urlencode($val) ?>&q=<?= urlencode($search) ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-colors
                  <?= $active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-indigo-50 hover:text-indigo-700' ?>">
          <?= $label ?>
          <span class="<?= $active ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-600' ?> rounded-full px-1.5 py-0.5 text-xs"><?= $cnt ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Search Bar -->
    <form method="GET" class="flex gap-3">
      <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
      <div class="flex-1 max-w-md relative">
        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search jobs, department, location…"
               class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
      </div>
      <button type="submit"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
        Search
      </button>
      <?php if ($search): ?>
        <a href="<?= BASE_URL ?>/modules/recruitment/index.php?status=<?= urlencode($filterStatus) ?>"
           class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
          Clear
        </a>
      <?php endif; ?>
    </form>

    <!-- Jobs Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">Job Postings</h2>
        <span class="text-xs text-gray-400"><?= $totalRows ?> posting(s)</span>
      </div>

      <?php if (empty($jobs)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-gray-400">
          <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
          <p class="text-sm font-medium">No job postings found</p>
          <?php if ($isHRMgr): ?>
            <a href="<?= BASE_URL ?>/modules/recruitment/add_job.php"
               class="mt-3 text-xs text-indigo-600 hover:underline">Post your first job →</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Job Title</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Department</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Type</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Vacancies</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Deadline</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Applicants</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <?php foreach ($jobs as $job):
                $isPastDeadline = !empty($job['deadline']) && strtotime($job['deadline']) < strtotime('today');
              ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="font-medium text-gray-900"><?= sanitize($job['title']) ?></p>
                    <p class="text-xs text-gray-400"><?= sanitize($job['location'] ?? '—') ?></p>
                  </td>
                  <td class="px-4 py-3 text-gray-600"><?= sanitize($job['dept_name'] ?? '—') ?></td>
                  <td class="px-4 py-3">
                    <span class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded text-xs font-medium">
                      <?= sanitize($job['employment_type'] ?? '—') ?>
                    </span>
                  </td>
                  <td class="px-4 py-3 text-center font-medium text-gray-800"><?= (int)$job['vacancies'] ?></td>
                  <td class="px-4 py-3">
                    <span class="<?= $isPastDeadline ? 'text-red-600 font-medium' : 'text-gray-600' ?>">
                      <?= formatDate($job['deadline']) ?>
                    </span>
                    <?php if ($isPastDeadline): ?>
                      <span class="block text-xs text-red-400">Expired</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3 text-center"><?= statusBadge($job['status']) ?></td>
                  <td class="px-4 py-3 text-center">
                    <?php if ((int)$job['applicant_count'] > 0): ?>
                      <a href="<?= BASE_URL ?>/modules/recruitment/applications.php?job_id=<?= $job['id'] ?>"
                         class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-100 hover:bg-indigo-200 text-indigo-700 text-xs font-semibold rounded-full transition-colors">
                        <?= (int)$job['applicant_count'] ?> applicant<?= (int)$job['applicant_count'] !== 1 ? 's' : '' ?>
                      </a>
                    <?php else: ?>
                      <span class="text-xs text-gray-400">None</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3">
                    <div class="flex items-center justify-center gap-1">
                      <!-- View Applicants — all who can access this page -->
                      <a href="<?= BASE_URL ?>/modules/recruitment/applications.php?job_id=<?= $job['id'] ?>"
                         title="View Applicants"
                         class="p-1.5 rounded-lg text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                      </a>
                      <?php if ($isHRMgr): ?>
                        <!-- Edit Job — HR Manager only -->
                        <a href="<?= BASE_URL ?>/modules/recruitment/add_job.php?edit=<?= $job['id'] ?>"
                           title="Edit Job"
                           class="p-1.5 rounded-lg text-gray-500 hover:text-yellow-600 hover:bg-yellow-50 transition-colors">
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
