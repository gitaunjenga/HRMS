<?php
/**
 * HRMS - Recruitment: Add / Edit Job Posting
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('create', 'recruitment');

$pageTitle = 'Post a Job';
$editId    = (int)($_GET['edit'] ?? 0);
$isEdit    = $editId > 0;
$errors    = [];
$job       = [];

// ── Load departments ───────────────────────────────────────────────────────────
$departments = fetchAll("SELECT id, name FROM departments ORDER BY name");

// ── Load existing job if editing ───────────────────────────────────────────────
if ($isEdit) {
    $job = fetchOne("SELECT * FROM job_postings WHERE id = ?", 'i', $editId);
    if (!$job) {
        setFlash('error', 'Job posting not found.');
        header('Location: ' . BASE_URL . '/modules/recruitment/index.php');
        exit;
    }
    $pageTitle = 'Edit Job Posting';
}

// ── POST Handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $title          = sanitize($_POST['title'] ?? '');
        $departmentId   = (int)($_POST['department_id'] ?? 0);
        $description    = trim($_POST['description'] ?? '');
        $requirements   = trim($_POST['requirements'] ?? '');
        $employmentType = sanitize($_POST['employment_type'] ?? '');
        $location       = sanitize($_POST['location'] ?? '');
        $salaryMin      = $_POST['salary_range_min'] !== '' ? (float)$_POST['salary_range_min'] : null;
        $salaryMax      = $_POST['salary_range_max'] !== '' ? (float)$_POST['salary_range_max'] : null;
        $vacancies      = (int)($_POST['vacancies'] ?? 1);
        $deadline       = $_POST['deadline'] ?? '';
        $status         = sanitize($_POST['status'] ?? 'Open');
        $postedBy       = (int)(currentUser()['id'] ?? 0);

        // Validate
        if ($title === '') $errors[] = 'Job title is required.';
        if ($departmentId <= 0) $errors[] = 'Please select a department.';
        if ($description === '') $errors[] = 'Job description is required.';
        if ($employmentType === '') $errors[] = 'Employment type is required.';
        if ($vacancies < 1) $errors[] = 'Number of vacancies must be at least 1.';
        if (!in_array($status, ['Open', 'Closed', 'On Hold'])) $errors[] = 'Invalid status.';
        if ($salaryMin !== null && $salaryMax !== null && $salaryMin > $salaryMax) {
            $errors[] = 'Minimum salary cannot exceed maximum salary.';
        }
        if ($deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $errors[] = 'Invalid deadline date format.';
        }

        if (empty($errors)) {
            if ($isEdit) {
                $ok = execute(
                    "UPDATE job_postings SET
                        title=?, department_id=?, description=?, requirements=?,
                        employment_type=?, location=?, salary_range_min=?, salary_range_max=?,
                        vacancies=?, deadline=?, status=?
                     WHERE id=?",
                    'sissssddiisi',
                    $title, $departmentId, $description, $requirements,
                    $employmentType, $location, $salaryMin, $salaryMax,
                    $vacancies, ($deadline ?: null), $status, $editId
                );
                if ($ok !== false) {
                    setFlash('success', 'Job posting updated successfully.');
                    header('Location: ' . BASE_URL . '/modules/recruitment/index.php');
                    exit;
                } else {
                    $errors[] = 'Failed to update job posting. Please try again.';
                }
            } else {
                $ok = execute(
                    "INSERT INTO job_postings
                        (title, department_id, description, requirements, employment_type,
                         location, salary_range_min, salary_range_max, vacancies, deadline,
                         status, posted_by)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
                    'sissssddiisi',
                    $title, $departmentId, $description, $requirements, $employmentType,
                    $location, $salaryMin, $salaryMax, $vacancies,
                    ($deadline ?: null), $status, $postedBy
                );
                if ($ok) {
                    setFlash('success', 'Job posted successfully.');
                    header('Location: ' . BASE_URL . '/modules/recruitment/index.php');
                    exit;
                } else {
                    $errors[] = 'Failed to create job posting. Please try again.';
                }
            }
        }

        // Re-populate on error
        $job = [
            'title'           => $_POST['title'] ?? '',
            'department_id'   => $departmentId,
            'description'     => $_POST['description'] ?? '',
            'requirements'    => $_POST['requirements'] ?? '',
            'employment_type' => $_POST['employment_type'] ?? '',
            'location'        => $_POST['location'] ?? '',
            'salary_range_min'=> $_POST['salary_range_min'] ?? '',
            'salary_range_max'=> $_POST['salary_range_max'] ?? '',
            'vacancies'       => $vacancies,
            'deadline'        => $deadline,
            'status'          => $_POST['status'] ?? 'Open',
        ];
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900"><?= $isEdit ? 'Edit Job Posting' : 'Post a Job' ?></h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= $isEdit ? 'Update the job posting details' : 'Create a new job posting to attract candidates' ?></p>
      </div>
      <a href="<?= BASE_URL ?>/modules/recruitment/index.php"
         class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back
      </a>
    </div>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <ul class="list-disc list-inside space-y-1">
          <?php foreach ($errors as $e): ?>
            <li class="text-sm text-red-700"><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" class="space-y-6">
      <?= csrfField() ?>

      <!-- Basic Info -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">Job Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Job Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" required maxlength="200"
                   value="<?= htmlspecialchars($job['title'] ?? '') ?>"
                   placeholder="e.g. Senior Software Engineer"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
            <select name="department_id" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">— Select Department —</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?= $dept['id'] ?>"
                        <?= (int)($job['department_id'] ?? 0) === (int)$dept['id'] ? 'selected' : '' ?>>
                  <?= sanitize($dept['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Employment Type <span class="text-red-500">*</span></label>
            <select name="employment_type" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">— Select Type —</option>
              <?php
              $types = ['Full-Time', 'Part-Time', 'Contract', 'Internship', 'Freelance', 'Temporary'];
              foreach ($types as $t): ?>
                <option value="<?= $t ?>" <?= ($job['employment_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
            <input type="text" name="location" maxlength="200"
                   value="<?= htmlspecialchars($job['location'] ?? '') ?>"
                   placeholder="e.g. Remote, New York, NY"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Number of Vacancies <span class="text-red-500">*</span></label>
            <input type="number" name="vacancies" required min="1"
                   value="<?= htmlspecialchars($job['vacancies'] ?? '1') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Application Deadline</label>
            <input type="date" name="deadline"
                   value="<?= htmlspecialchars($job['deadline'] ?? '') ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Salary Range — Min</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="salary_range_min" step="0.01" min="0"
                     value="<?= htmlspecialchars($job['salary_range_min'] ?? '') ?>"
                     placeholder="0.00"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Salary Range — Max</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 text-gray-400 text-sm">KES</span>
              <input type="number" name="salary_range_max" step="0.01" min="0"
                     value="<?= htmlspecialchars($job['salary_range_max'] ?? '') ?>"
                     placeholder="0.00"
                     class="w-full border border-gray-300 rounded-lg pl-12 pr-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
            <select name="status" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="Open"    <?= ($job['status'] ?? 'Open') === 'Open'    ? 'selected' : '' ?>>Open</option>
              <option value="On Hold" <?= ($job['status'] ?? '') === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
              <option value="Closed"  <?= ($job['status'] ?? '') === 'Closed'  ? 'selected' : '' ?>>Closed</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Description & Requirements -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">Description &amp; Requirements</h2>
        <div class="space-y-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Job Description <span class="text-red-500">*</span>
            </label>
            <textarea name="description" required rows="7"
                      placeholder="Describe the role, responsibilities, and what a typical day looks like…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"><?= htmlspecialchars($job['description'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
            <textarea name="requirements" rows="6"
                      placeholder="List qualifications, skills, and experience required…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"><?= htmlspecialchars($job['requirements'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="flex gap-3">
        <button type="submit"
                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
          <?= $isEdit ? 'Update Job Posting' : 'Post Job' ?>
        </button>
        <a href="<?= BASE_URL ?>/modules/recruitment/index.php"
           class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition-colors">
          Cancel
        </a>
      </div>

    </form>
  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
