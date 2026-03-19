<?php
/**
 * HRMS - Employee List
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('view', 'employees');

$pageTitle = 'Employees';

// ── Head of Department scope ───────────────────────────────────────────────────
$deptId = myDeptId();
if ($deptId !== null) {
    $pageTitle = 'My Department — Team Members';
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search     = sanitize($_GET['search']    ?? '');
$deptFilter = (int)($_GET['department']   ?? 0);
$statusFilter = sanitize($_GET['status'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 15;

// ── Build query ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$types  = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where[]  = '(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_id LIKE ? OR e.email LIKE ? OR e.position LIKE ?)';
    $types   .= 'sssss';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}

if ($deptId !== null) {
    // Head of Department: restrict to their own department only
    $where[]  = 'e.department_id = ?';
    $types   .= 'i';
    $params[] = $deptId;
} elseif ($deptFilter > 0) {
    $where[]  = 'e.department_id = ?';
    $types   .= 'i';
    $params[] = $deptFilter;
}

if ($statusFilter !== '') {
    $where[]  = 'e.employment_status = ?';
    $types   .= 's';
    $params[] = $statusFilter;
}

$whereSQL = implode(' AND ', $where);

// Count total
$countRow = fetchOne(
    "SELECT COUNT(*) AS cnt FROM employees e WHERE {$whereSQL}",
    $types,
    ...$params
);
$total = (int)($countRow['cnt'] ?? 0);
$pag   = paginate($total, $page, $perPage);

// Fetch page
$employees = fetchAll(
    "SELECT e.*, d.name AS department_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE {$whereSQL}
     ORDER BY e.created_at DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    ...[...$params, $pag['perPage'], $pag['offset']]
);

// Departments for filter dropdown
$departments = fetchAll("SELECT id, name FROM departments ORDER BY name ASC");

// Build base URL for pagination (preserve filters)
$baseUrl = BASE_URL . '/modules/employees/index.php?' . http_build_query(array_filter([
    'search'     => $search,
    'department' => $deptFilter ?: '',
    'status'     => $statusFilter,
]));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <!-- Flash message -->
  <?php $flash = getFlash(); if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
      <?= sanitize($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Page header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($pageTitle) ?></h2>
      <p class="text-sm text-gray-500 mt-1"><?= $total ?> employee<?= $total !== 1 ? 's' : '' ?> found</p>
    </div>
    <?php if (can('create', 'employees')): ?>
      <a href="<?= BASE_URL ?>/modules/employees/add.php"
         class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Employee
      </a>
    <?php endif; ?>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 flex-wrap">
      <!-- Search -->
      <div class="flex-1 min-w-[200px]">
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                 placeholder="Search by name, ID, email, position…"
                 class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
      </div>

      <!-- Department filter -->
      <select name="department"
              class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-w-[160px]">
        <option value="">All Departments</option>
        <?php foreach ($departments as $dept): ?>
          <option value="<?= $dept['id'] ?>" <?= $deptFilter === (int)$dept['id'] ? 'selected' : '' ?>>
            <?= sanitize($dept['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <!-- Status filter -->
      <select name="status"
              class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-w-[140px]">
        <option value="">All Statuses</option>
        <?php foreach (['Active', 'Inactive', 'On Leave', 'Terminated'] as $s): ?>
          <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
        Filter
      </button>
      <a href="<?= BASE_URL ?>/modules/employees/index.php"
         class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition-colors">
        Reset
      </a>
    </form>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Employee</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">ID</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Department</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Position</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Status</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Hire Date</th>
            <th class="text-center px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($employees)): ?>
            <tr>
              <td colspan="7" class="px-5 py-16 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <p class="font-medium text-gray-500">No employees found</p>
                <p class="text-xs mt-1">Try adjusting your search or filters</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($employees as $emp): ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <!-- Employee name + photo -->
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <img src="<?= avatarUrl($emp['photo']) ?>" alt="Photo"
                         class="w-9 h-9 rounded-full object-cover ring-1 ring-gray-200 shrink-0">
                    <div>
                      <p class="font-medium text-gray-900">
                        <?= sanitize($emp['first_name']) ?> <?= sanitize($emp['last_name']) ?>
                      </p>
                      <p class="text-xs text-gray-400"><?= sanitize($emp['email']) ?></p>
                    </div>
                  </div>
                </td>
                <!-- Employee ID -->
                <td class="px-5 py-3">
                  <span class="font-mono text-xs bg-indigo-50 text-indigo-700 px-2 py-1 rounded">
                    <?= sanitize($emp['employee_id']) ?>
                  </span>
                </td>
                <!-- Department -->
                <td class="px-5 py-3 text-gray-600">
                  <?= $emp['department_name'] ? sanitize($emp['department_name']) : '<span class="text-gray-400">—</span>' ?>
                </td>
                <!-- Position -->
                <td class="px-5 py-3 text-gray-600">
                  <?= $emp['position'] ? sanitize($emp['position']) : '<span class="text-gray-400">—</span>' ?>
                </td>
                <!-- Status -->
                <td class="px-5 py-3">
                  <?= statusBadge($emp['employment_status']) ?>
                </td>
                <!-- Hire date -->
                <td class="px-5 py-3 text-gray-500 whitespace-nowrap">
                  <?= formatDate($emp['hire_date']) ?>
                </td>
                <!-- Actions -->
                <td class="px-5 py-3">
                  <div class="flex items-center justify-center gap-2">
                    <!-- View -->
                    <a href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= $emp['id'] ?>"
                       title="View"
                       class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </a>
                    <?php if (can('edit', 'employees')): ?>
                      <!-- Edit -->
                      <a href="<?= BASE_URL ?>/modules/employees/edit.php?id=<?= $emp['id'] ?>"
                         title="Edit"
                         class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                      </a>
                    <?php endif; ?>
                    <?php if (can('delete', 'employees') && $emp['employment_status'] !== 'Terminated'): ?>
                      <!-- Delete / Terminate -->
                      <form method="POST" action="<?= BASE_URL ?>/modules/employees/delete.php"
                            onsubmit="return confirm('Terminate this employee? This cannot be undone.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= $emp['id'] ?>">
                        <button type="submit" title="Terminate"
                                class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                          </svg>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
