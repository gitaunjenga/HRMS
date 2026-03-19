<?php
/**
 * HRMS - Edit Department
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('edit', 'departments');

$pageTitle = 'Edit Department';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    setFlash('error', 'Invalid department ID.');
    header('Location: ' . BASE_URL . '/modules/departments/index.php');
    exit;
}

// Fetch existing department
$dept = fetchOne("SELECT * FROM departments WHERE id = ?", 'i', $id);
if (!$dept) {
    setFlash('error', 'Department not found.');
    header('Location: ' . BASE_URL . '/modules/departments/index.php');
    exit;
}

// Fetch active employees for head dropdown
$employees = fetchAll(
    "SELECT id, employee_id, first_name, last_name, position
     FROM employees
     WHERE employment_status = 'Active'
     ORDER BY first_name, last_name"
);

$errors = [];
$values = [
    'name'        => $dept['name'],
    'code'        => $dept['code'] ?? '',
    'description' => $dept['description'] ?? '',
    'head_id'     => $dept['head_id'] ?? '',
];

// ── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $values['name']        = sanitize($_POST['name'] ?? '');
        $values['code']        = strtoupper(sanitize($_POST['code'] ?? ''));
        $values['description'] = sanitize($_POST['description'] ?? '');
        $values['head_id']     = (int)($_POST['head_id'] ?? 0);

        // Validation
        if ($values['name'] === '') {
            $errors[] = 'Department name is required.';
        } elseif (strlen($values['name']) > 100) {
            $errors[] = 'Department name must be 100 characters or fewer.';
        }

        if ($values['code'] !== '' && strlen($values['code']) > 20) {
            $errors[] = 'Department code must be 20 characters or fewer.';
        }

        // Check duplicate name (excluding self)
        if ($values['name'] !== '') {
            $existing = fetchOne(
                "SELECT id FROM departments WHERE name = ? AND id != ?",
                'si', $values['name'], $id
            );
            if ($existing) {
                $errors[] = 'Another department with this name already exists.';
            }
        }

        // Check duplicate code (excluding self)
        if ($values['code'] !== '') {
            $existingCode = fetchOne(
                "SELECT id FROM departments WHERE code = ? AND id != ?",
                'si', $values['code'], $id
            );
            if ($existingCode) {
                $errors[] = 'Another department with this code already exists.';
            }
        }

        if (empty($errors)) {
            $headId = $values['head_id'] > 0 ? $values['head_id'] : null;
            $result = execute(
                "UPDATE departments SET name = ?, code = ?, description = ?, head_id = ? WHERE id = ?",
                'sssii',
                $values['name'],
                $values['code'] !== '' ? $values['code'] : null,
                $values['description'] !== '' ? $values['description'] : null,
                $headId,
                $id
            );

            // execute() returns false only on real failure; 0 affected rows when nothing changed is OK
            setFlash('success', 'Department "' . $values['name'] . '" updated successfully.');
            header('Location: ' . BASE_URL . '/modules/departments/index.php');
            exit;
        }
    }
}

// Employee count for info
$empCount = (int)(fetchOne(
    "SELECT COUNT(*) AS c FROM employees WHERE department_id = ? AND employment_status = 'Active'",
    'i', $id
)['c'] ?? 0);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
      <a href="<?= BASE_URL ?>/modules/departments/index.php" class="hover:text-indigo-600 transition-colors">Departments</a>
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
      <span class="text-gray-900 font-medium">Edit Department</span>
    </nav>

    <div class="max-w-2xl mx-auto space-y-5">

      <!-- Info Banner -->
      <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
          </svg>
          <div>
            <p class="text-sm font-medium text-indigo-900">Editing: <?= sanitize($dept['name']) ?></p>
            <p class="text-xs text-indigo-600"><?= $empCount ?> active employee<?= $empCount !== 1 ? 's' : '' ?> in this department</p>
          </div>
        </div>
        <?php if ($dept['code']): ?>
          <span class="bg-indigo-100 text-indigo-700 text-xs font-mono px-2.5 py-1 rounded-lg">
            <?= sanitize($dept['code']) ?>
          </span>
        <?php endif; ?>
      </div>

      <!-- Card -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

        <!-- Card Header -->
        <div class="bg-indigo-600 px-6 py-5">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-indigo-500 rounded-lg flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-lg font-semibold text-white">Edit Department</h2>
              <p class="text-indigo-200 text-sm">Update the department details below.</p>
            </div>
          </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
          <div class="mx-6 mt-5 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
            <div class="flex items-start gap-2">
              <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <ul class="text-sm text-red-700 space-y-0.5">
                <?php foreach ($errors as $err): ?>
                  <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="" class="p-6 space-y-5">
          <?= csrfField() ?>

          <!-- Name -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">
              Department Name <span class="text-red-500">*</span>
            </label>
            <input type="text" id="name" name="name"
                   value="<?= htmlspecialchars($values['name']) ?>"
                   placeholder="e.g. Human Resources"
                   maxlength="100"
                   class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400
                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                   required>
          </div>

          <!-- Code -->
          <div>
            <label for="code" class="block text-sm font-medium text-gray-700 mb-1.5">
              Department Code
              <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <input type="text" id="code" name="code"
                   value="<?= htmlspecialchars($values['code']) ?>"
                   placeholder="e.g. HR, ENG, FIN"
                   maxlength="20"
                   style="text-transform:uppercase"
                   class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 font-mono
                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
            <p class="text-xs text-gray-400 mt-1">Short alphanumeric identifier. Will be stored in uppercase.</p>
          </div>

          <!-- Department Head -->
          <div>
            <label for="head_id" class="block text-sm font-medium text-gray-700 mb-1.5">
              Department Head
              <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <select id="head_id" name="head_id"
                    class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition bg-white">
              <option value="">— Select Employee —</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"
                  <?= (string)$values['head_id'] === (string)$emp['id'] ? 'selected' : '' ?>>
                  <?= sanitize($emp['first_name'] . ' ' . $emp['last_name']) ?>
                  <?php if ($emp['position']): ?>
                    (<?= sanitize($emp['position']) ?>)
                  <?php endif; ?>
                  — <?= sanitize($emp['employee_id']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Description -->
          <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">
              Description
              <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <textarea id="description" name="description" rows="4"
                      placeholder="Brief description of the department's role and responsibilities..."
                      class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 resize-none
                             focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"><?= htmlspecialchars($values['description']) ?></textarea>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
            <a href="<?= BASE_URL ?>/modules/departments/index.php"
               class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
              Cancel
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
              </svg>
              Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
  // Auto-uppercase code field
  document.getElementById('code').addEventListener('input', function () {
    this.value = this.value.toUpperCase();
  });
</script>
