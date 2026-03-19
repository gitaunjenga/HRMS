<?php
/**
 * HRMS - Document Management — List View
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('view', 'documents');

$pageTitle = 'Documents';
$user      = currentUser();
$role      = $user['role'] ?? '';
$isHR      = hasRole('Admin', 'HR Manager');
$isDM      = ($role === 'Head of Department');

// ── Filters ───────────────────────────────────────────────────────────────────
$categoryFilter  = sanitize($_GET['category']    ?? '');
$employeeFilter  = (int)($_GET['employee_id']    ?? 0);
$page            = max(1, (int)($_GET['page']    ?? 1));
$perPage         = 15;

$categories = ['HR Policy', 'Contract', 'Certificate', 'ID', 'Other'];

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where  = ['1=1'];
$types  = '';
$params = [];

if ($isHR) {
    // Admin / HR Manager: see all documents
} elseif ($isDM) {
    // Head of Department: see company docs + their department's employee docs
    $deptId = myDeptId();
    if ($deptId) {
        $where[]  = '(d.employee_id IS NULL OR e_doc.department_id = ?)';
        $types   .= 'i';
        $params[] = $deptId;
    } else {
        $where[] = 'd.employee_id IS NULL';
    }
} else {
    // Employee: see company docs (employee_id IS NULL) + their own docs
    $empRow = fetchOne(
        "SELECT id FROM employees WHERE id = (SELECT employee_id FROM users WHERE id = ?)",
        'i', $user['id']
    );
    $empId = $empRow ? (int)$empRow['id'] : 0;

    if ($empId > 0) {
        $where[]  = '(d.employee_id IS NULL OR d.employee_id = ?)';
        $types   .= 'i';
        $params[] = $empId;
    } else {
        $where[] = 'd.employee_id IS NULL';
    }
}

if ($categoryFilter !== '') {
    $where[]  = 'd.category = ?';
    $types   .= 's';
    $params[] = $categoryFilter;
}

if ($isHR && $employeeFilter > 0) {
    $where[]  = 'd.employee_id = ?';
    $types   .= 'i';
    $params[] = $employeeFilter;
}

$whereSQL = implode(' AND ', $where);

// ── Total count ───────────────────────────────────────────────────────────────
// For DM we need the JOIN with employees for department filtering
$joinClause = ($isDM && myDeptId())
    ? "LEFT JOIN employees e_doc ON e_doc.id = d.employee_id"
    : "";

$countRow = fetchOne(
    "SELECT COUNT(*) AS cnt FROM documents d {$joinClause} WHERE {$whereSQL}",
    $types,
    ...$params
);
$total = (int)($countRow['cnt'] ?? 0);
$pag   = paginate($total, $page, $perPage);

// ── Fetch rows ────────────────────────────────────────────────────────────────
$documents = fetchAll(
    "SELECT d.*,
            CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            u.username AS uploader_name
     FROM documents d
     LEFT JOIN employees e ON e.id = d.employee_id
     " . (($isDM && myDeptId()) ? "LEFT JOIN employees e_doc ON e_doc.id = d.employee_id" : "") . "
     LEFT JOIN users u     ON u.id = d.uploaded_by
     WHERE {$whereSQL}
     ORDER BY d.created_at DESC
     LIMIT ? OFFSET ?",
    $types . 'ii',
    ...[...$params, $pag['perPage'], $pag['offset']]
);

// ── Employees list for filter dropdown (HR/Admin only) ────────────────────────
$employees = $isHR
    ? fetchAll("SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM employees ORDER BY first_name ASC")
    : [];

// ── Base URL for pagination ───────────────────────────────────────────────────
$baseUrl = BASE_URL . '/modules/documents/index.php?' . http_build_query(array_filter([
    'category'    => $categoryFilter,
    'employee_id' => $employeeFilter ?: '',
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
      <h2 class="text-2xl font-bold text-gray-900">Documents</h2>
      <p class="text-sm text-gray-500 mt-1"><?= $total ?> document<?= $total !== 1 ? 's' : '' ?> found</p>
    </div>
    <?php if (can('upload', 'documents')): ?>
      <a href="<?= BASE_URL ?>/modules/documents/upload.php"
         class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
        </svg>
        Upload Document
      </a>
    <?php endif; ?>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 flex-wrap">

      <!-- Category -->
      <select name="category"
              class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-w-[160px]">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= $cat ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= $cat ?></option>
        <?php endforeach; ?>
      </select>

      <!-- Employee filter (HR/Admin only) -->
      <?php if ($isHR): ?>
        <select name="employee_id"
                class="text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-w-[200px]">
          <option value="">All Employees</option>
          <option value="">— Company Documents —</option>
          <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>" <?= $employeeFilter === (int)$emp['id'] ? 'selected' : '' ?>>
              <?= sanitize($emp['full_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <button type="submit"
              class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">
        Filter
      </button>
      <a href="<?= BASE_URL ?>/modules/documents/index.php"
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
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Title</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Employee</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Category</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Type</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Size</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Uploaded By</th>
            <th class="text-left px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Date</th>
            <th class="text-center px-5 py-3 font-semibold text-gray-600 uppercase text-xs tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($documents)): ?>
            <tr>
              <td colspan="8" class="px-5 py-16 text-center text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="font-medium text-gray-500">No documents found</p>
                <p class="text-xs mt-1">Try adjusting your filters or upload a new document</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($documents as $doc): ?>
              <?php
                // File-type badge colour
                $ext   = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                $extColors = [
                    'pdf'  => 'bg-red-100 text-red-700',
                    'doc'  => 'bg-blue-100 text-blue-700',
                    'docx' => 'bg-blue-100 text-blue-700',
                    'jpg'  => 'bg-green-100 text-green-700',
                    'jpeg' => 'bg-green-100 text-green-700',
                    'png'  => 'bg-green-100 text-green-700',
                ];
                $extColor = $extColors[$ext] ?? 'bg-gray-100 text-gray-700';

                // Category badge colour
                $catColors = [
                    'HR Policy'   => 'bg-purple-100 text-purple-700',
                    'Contract'    => 'bg-indigo-100 text-indigo-700',
                    'Certificate' => 'bg-yellow-100 text-yellow-700',
                    'ID'          => 'bg-teal-100 text-teal-700',
                    'Other'       => 'bg-gray-100 text-gray-700',
                ];
                $catColor = $catColors[$doc['category']] ?? 'bg-gray-100 text-gray-700';

                // Human-readable file size
                $bytes = (int)$doc['file_size'];
                $size  = $bytes >= 1048576
                    ? number_format($bytes / 1048576, 2) . ' MB'
                    : number_format($bytes / 1024, 1) . ' KB';
              ?>
              <tr class="hover:bg-gray-50 transition-colors">
                <!-- Title -->
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <div>
                      <p class="font-medium text-gray-900 leading-tight"><?= sanitize($doc['title']) ?></p>
                      <?php if ($doc['description']): ?>
                        <p class="text-xs text-gray-400 mt-0.5 max-w-xs truncate"><?= sanitize($doc['description']) ?></p>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>

                <!-- Employee -->
                <td class="px-5 py-3 text-gray-600">
                  <?php if ($doc['employee_name']): ?>
                    <?= sanitize($doc['employee_name']) ?>
                  <?php else: ?>
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                      </svg>
                      Company
                    </span>
                  <?php endif; ?>
                </td>

                <!-- Category -->
                <td class="px-5 py-3">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $catColor ?>">
                    <?= sanitize($doc['category']) ?>
                  </span>
                </td>

                <!-- File type -->
                <td class="px-5 py-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium uppercase <?= $extColor ?>">
                    <?= htmlspecialchars($ext) ?>
                  </span>
                </td>

                <!-- Size -->
                <td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?= $size ?></td>

                <!-- Uploaded by -->
                <td class="px-5 py-3 text-gray-500"><?= sanitize($doc['uploader_name'] ?? '—') ?></td>

                <!-- Date -->
                <td class="px-5 py-3 text-gray-500 whitespace-nowrap"><?= formatDate($doc['created_at']) ?></td>

                <!-- Actions -->
                <td class="px-5 py-3">
                  <div class="flex items-center justify-center gap-2">
                    <!-- Download -->
                    <a href="<?= BASE_URL ?>/modules/documents/download.php?id=<?= (int)$doc['id'] ?>"
                       title="Download"
                       class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                      </svg>
                    </a>

                    <!-- Delete (HR Manager only via can('manage','documents')) -->
                    <?php if (can('manage', 'documents')): ?>
                      <form method="POST" action="<?= BASE_URL ?>/modules/documents/delete.php"
                            onsubmit="return confirm('Delete this document? This action cannot be undone.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
                        <button type="submit" title="Delete"
                                class="p-1.5 text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
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
