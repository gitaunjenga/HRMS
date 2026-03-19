<?php
/**
 * HRMS - Document Upload
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('upload', 'documents');

$pageTitle = 'Upload Document';
$user      = currentUser();
$isHR      = hasRole('Admin', 'HR Manager');

// ── Determine the current user's linked employee record ───────────────────────
$myEmployee = null;
if ($user['employee_id'] ?? null) {
    $myEmployee = fetchOne("SELECT id, CONCAT(first_name,' ',last_name) AS full_name FROM employees WHERE id = ?",
        'i', (int)$user['employee_id']);
}

// Employees list for HR/Admin dropdown
$employees = $isHR
    ? fetchAll("SELECT id, employee_id, CONCAT(first_name,' ',last_name) AS full_name FROM employees ORDER BY first_name ASC")
    : [];

$errors = [];
$old    = [];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
    } else {
        $title       = sanitize($_POST['title']       ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category    = sanitize($_POST['category']    ?? '');
        $employeeId  = (int)($_POST['employee_id']    ?? 0);

        $old = compact('title', 'description', 'category', 'employeeId');

        $validCategories = ['HR Policy', 'Contract', 'Certificate', 'ID', 'Other'];

        // ── Validate ──────────────────────────────────────────────────────────
        if ($title === '') {
            $errors[] = 'Document title is required.';
        }
        if (!in_array($category, $validCategories, true)) {
            $errors[] = 'Please select a valid category.';
        }
        if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Please select a file to upload.';
        }

        // ── Permissions ───────────────────────────────────────────────────────
        if (!$isHR) {
            // Employees may only upload documents for themselves
            if ($myEmployee && $employeeId !== 0 && $employeeId !== (int)$myEmployee['id']) {
                $errors[] = 'You can only upload documents for your own employee record.';
            }
            // Force their own employee_id (or 0/null for company — restrict)
            if ($myEmployee) {
                $employeeId = (int)$myEmployee['id'];
            } else {
                $errors[] = 'No employee record is linked to your account.';
            }
        }

        // ── File Upload ───────────────────────────────────────────────────────
        if (empty($errors) && isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            $result = uploadFile($_FILES['document'], DOC_DIR, $allowedTypes);

            if (!$result['success']) {
                $errors[] = $result['message'];
            } else {
                $filename  = $result['filename'];
                $fileSize  = (int)$_FILES['document']['size'];
                $fileType  = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
                $finalEmpId = $employeeId > 0 ? $employeeId : null;

                $inserted = execute(
                    "INSERT INTO documents (title, description, file_path, file_type, file_size, employee_id, category, uploaded_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    'ssssisis',
                    $title,
                    $description,
                    $filename,
                    $fileType,
                    $fileSize,
                    $finalEmpId,
                    $category,
                    (int)$user['id']
                );

                if ($inserted) {
                    setFlash('success', 'Document "' . $title . '" uploaded successfully.');
                    header('Location: ' . BASE_URL . '/modules/documents/index.php');
                    exit;
                } else {
                    // Roll back uploaded file on DB failure
                    @unlink(DOC_DIR . $filename);
                    $errors[] = 'Failed to save document record. Please try again.';
                }
            }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <!-- Breadcrumb -->
  <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
    <a href="<?= BASE_URL ?>/modules/documents/index.php" class="hover:text-indigo-600 transition-colors">Documents</a>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
    </svg>
    <span class="text-gray-800 font-medium">Upload</span>
  </nav>

  <div class="max-w-2xl">
    <div class="mb-6">
      <h2 class="text-2xl font-bold text-gray-900">Upload Document</h2>
      <p class="text-sm text-gray-500 mt-1">Accepted: PDF, DOC, DOCX, JPG, PNG — max 5 MB</p>
    </div>

    <!-- Errors -->
    <?php if ($errors): ?>
      <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <ul class="text-sm text-red-700 space-y-1 list-disc list-inside">
            <?php foreach ($errors as $e): ?>
              <li><?= sanitize($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <form method="POST" enctype="multipart/form-data" class="space-y-5">
        <?= csrfField() ?>

        <!-- Title -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1" for="title">
            Document Title <span class="text-red-500">*</span>
          </label>
          <input type="text" id="title" name="title"
                 value="<?= htmlspecialchars($old['title'] ?? '') ?>"
                 placeholder="e.g. Employment Contract 2024"
                 required
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1" for="description">
            Description <span class="text-gray-400 font-normal">(optional)</span>
          </label>
          <textarea id="description" name="description" rows="3"
                    placeholder="Brief description of this document…"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
        </div>

        <!-- Category -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1" for="category">
            Category <span class="text-red-500">*</span>
          </label>
          <select id="category" name="category" required
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">— Select category —</option>
            <?php foreach (['HR Policy', 'Contract', 'Certificate', 'ID', 'Other'] as $cat): ?>
              <option value="<?= $cat ?>" <?= ($old['category'] ?? '') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Employee (HR/Admin picks any; regular employee sees their own or disabled) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1" for="employee_id">
            Employee
            <span class="text-gray-400 font-normal">(leave blank for a company-wide document)</span>
          </label>
          <?php if ($isHR): ?>
            <select id="employee_id" name="employee_id"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">— Company Document —</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>"
                  <?= ($old['employeeId'] ?? 0) === (int)$emp['id'] ? 'selected' : '' ?>>
                  <?= sanitize($emp['full_name']) ?> (<?= sanitize($emp['employee_id']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          <?php elseif ($myEmployee): ?>
            <input type="text"
                   value="<?= sanitize($myEmployee['full_name']) ?>"
                   disabled
                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
            <input type="hidden" name="employee_id" value="<?= (int)$myEmployee['id'] ?>">
          <?php else: ?>
            <p class="text-sm text-red-600">No employee record is linked to your account.</p>
          <?php endif; ?>
        </div>

        <!-- File -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1" for="document">
            File <span class="text-red-500">*</span>
          </label>
          <div x-data="{ fileName: '' }"
               class="relative border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-indigo-400 transition-colors cursor-pointer"
               onclick="document.getElementById('document').click()">
            <input type="file" id="document" name="document" required
                   accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                   class="hidden"
                   onchange="document.getElementById('file-name').textContent = this.files[0] ? this.files[0].name : 'No file chosen'">
            <svg class="w-10 h-10 mx-auto mb-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            <p class="text-sm text-gray-500">Click to browse or drag & drop</p>
            <p id="file-name" class="mt-2 text-xs text-indigo-600 font-medium">No file chosen</p>
          </div>
          <p class="mt-1.5 text-xs text-gray-400">PDF, DOC, DOCX, JPG, PNG — maximum 5 MB</p>
        </div>

        <!-- Buttons -->
        <div class="flex items-center gap-3 pt-2">
          <button type="submit"
                  class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            Upload Document
          </button>
          <a href="<?= BASE_URL ?>/modules/documents/index.php"
             class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2 rounded-lg text-sm font-medium transition-colors">
            Cancel
          </a>
        </div>
      </form>
    </div>
  </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
