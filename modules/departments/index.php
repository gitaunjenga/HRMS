<?php
/**
 * HRMS - Departments List
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('view', 'departments');

$pageTitle = 'Departments';

// ── Fetch departments with head name and employee count ──────────────────────
$departments = fetchAll(
    "SELECT d.id, d.name, d.code, d.description,
            CONCAT(e.first_name, ' ', e.last_name) AS head_name,
            e.photo AS head_photo,
            (SELECT COUNT(*) FROM employees emp
             WHERE emp.department_id = d.id
               AND emp.employment_status = 'Active') AS employee_count
     FROM departments d
     LEFT JOIN employees e ON d.head_id = e.id
     ORDER BY d.name ASC"
);

$flash = getFlash();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Flash Message -->
    <?php if ($flash): ?>
      <div class="rounded-lg px-4 py-3 text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Header Row -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">Departments</h2>
        <p class="text-sm text-gray-500 mt-0.5"><?= count($departments) ?> department<?= count($departments) !== 1 ? 's' : '' ?> total</p>
      </div>
      <?php if (can('create', 'departments')): ?>
      <a href="<?= BASE_URL ?>/modules/departments/add.php"
         class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Department
      </a>
      <?php endif; ?>
    </div>

    <!-- Department Cards Grid -->
    <?php if (empty($departments)): ?>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
        <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
        </div>
        <p class="text-gray-500 font-medium">No departments found.</p>
        <p class="text-sm text-gray-400 mt-1">Create your first department to get started.</p>
        <a href="<?= BASE_URL ?>/modules/departments/add.php"
           class="inline-flex items-center gap-2 mt-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
          Add First Department
        </a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
        <?php foreach ($departments as $dept): ?>
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow flex flex-col">

            <!-- Card Header -->
            <div class="bg-indigo-600 rounded-t-xl px-5 py-4 flex items-start justify-between">
              <div>
                <h3 class="text-white font-semibold text-base leading-tight">
                  <?= sanitize($dept['name']) ?>
                </h3>
                <?php if ($dept['code']): ?>
                  <span class="inline-block mt-1 bg-indigo-500 text-indigo-100 text-xs font-mono px-2 py-0.5 rounded">
                    <?= sanitize($dept['code']) ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="bg-indigo-500 rounded-lg px-3 py-1.5 text-center min-w-[44px]">
                <p class="text-white text-xl font-bold leading-tight"><?= (int)$dept['employee_count'] ?></p>
                <p class="text-indigo-200 text-xs leading-tight">staff</p>
              </div>
            </div>

            <!-- Card Body -->
            <div class="px-5 py-4 flex-1 space-y-3">

              <!-- Description -->
              <?php if ($dept['description']): ?>
                <p class="text-sm text-gray-500 line-clamp-2">
                  <?= sanitize($dept['description']) ?>
                </p>
              <?php else: ?>
                <p class="text-sm text-gray-400 italic">No description provided.</p>
              <?php endif; ?>

              <!-- Department Head -->
              <div class="flex items-center gap-2.5 pt-1">
                <?php if ($dept['head_name']): ?>
                  <img src="<?= avatarUrl($dept['head_photo'] ?? null) ?>" alt="Head"
                       class="w-7 h-7 rounded-full object-cover ring-2 ring-indigo-100">
                  <div>
                    <p class="text-xs text-gray-400">Department Head</p>
                    <p class="text-sm font-medium text-gray-700"><?= sanitize($dept['head_name']) ?></p>
                  </div>
                <?php else: ?>
                  <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center">
                    <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                  </div>
                  <p class="text-sm text-gray-400 italic">No head assigned</p>
                <?php endif; ?>
              </div>
            </div>

            <!-- Card Footer -->
            <div class="px-5 py-3 border-t border-gray-100 flex items-center gap-2">
              <?php if (can('edit', 'departments')): ?>
              <a href="<?= BASE_URL ?>/modules/departments/edit.php?id=<?= $dept['id'] ?>"
                 class="flex-1 inline-flex items-center justify-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 font-medium px-3 py-1.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
              </a>
              <?php endif; ?>
              <?php if (can('delete', 'departments')): ?>
              <form method="POST" action="<?= BASE_URL ?>/modules/departments/delete.php"
                    onsubmit="return confirm('Delete department \'<?= sanitize(addslashes($dept['name'])) ?>\'? This cannot be undone.');"
                    class="flex-1">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int)$dept['id'] ?>">
                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-1.5 text-sm text-red-600 hover:text-red-800 hover:bg-red-50 font-medium px-3 py-1.5 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                  Delete
                </button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Also show as table on wider screens if needed -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800">All Departments — Summary Table</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Department</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Code</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Head</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Employees</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-50">
              <?php foreach ($departments as $dept): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="text-sm font-medium text-gray-900"><?= sanitize($dept['name']) ?></p>
                    <?php if ($dept['description']): ?>
                      <p class="text-xs text-gray-400 mt-0.5 max-w-xs truncate"><?= sanitize($dept['description']) ?></p>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3">
                    <?php if ($dept['code']): ?>
                      <span class="inline-block bg-indigo-100 text-indigo-700 text-xs font-mono px-2 py-0.5 rounded">
                        <?= sanitize($dept['code']) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-gray-400">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3">
                    <?php if ($dept['head_name']): ?>
                      <div class="flex items-center gap-2">
                        <img src="<?= avatarUrl($dept['head_photo'] ?? null) ?>" alt=""
                             class="w-6 h-6 rounded-full object-cover">
                        <span class="text-sm text-gray-700"><?= sanitize($dept['head_name']) ?></span>
                      </div>
                    <?php else: ?>
                      <span class="text-gray-400 text-sm">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3 text-center">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 text-sm font-semibold">
                      <?= (int)$dept['employee_count'] ?>
                    </span>
                  </td>
                  <td class="px-5 py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                      <?php if (can('edit', 'departments')): ?>
                      <a href="<?= BASE_URL ?>/modules/departments/edit.php?id=<?= $dept['id'] ?>"
                         class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium px-2.5 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                      </a>
                      <?php endif; ?>
                      <?php if (can('delete', 'departments')): ?>
                      <form method="POST" action="<?= BASE_URL ?>/modules/departments/delete.php"
                            onsubmit="return confirm('Delete \'<?= sanitize(addslashes($dept['name'])) ?>\'?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int)$dept['id'] ?>">
                        <button type="submit"
                                class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-800 font-medium px-2.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 transition-colors">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                          </svg>
                          Delete
                        </button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
