<?php
/**
 * HRMS - Performance: Add / Edit Review
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('create', 'performance');

$pageTitle = 'New Performance Review';
$editId    = (int)($_GET['edit'] ?? 0);
$isEdit    = $editId > 0;
$errors    = [];
$review    = [];

// ── Load employees for dropdown ────────────────────────────────────────────────
$user      = currentUser();
$role      = $user['role'] ?? '';
$isHRAdmin = hasRole('Admin', 'HR Manager');
$isDM      = ($role === 'Head of Department');

// Head of Department: only list employees in their department
if ($isDM && myDeptId() !== null) {
    $deptId    = myDeptId();
    $employees = fetchAll(
        "SELECT e.id, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                e.employee_id AS emp_code, d.name AS dept_name
         FROM employees e
         LEFT JOIN departments d ON e.department_id = d.id
         WHERE e.employment_status = 'Active' AND e.department_id = ?
         ORDER BY e.first_name, e.last_name",
        'i', $deptId
    );
} else {
    $employees = fetchAll(
        "SELECT e.id, CONCAT(e.first_name,' ',e.last_name) AS full_name,
                e.employee_id AS emp_code, d.name AS dept_name
         FROM employees e
         LEFT JOIN departments d ON e.department_id = d.id
         WHERE e.employment_status = 'Active'
         ORDER BY e.first_name, e.last_name"
    );
}

// ── Reviewer list (HR/Admin sees all employees, Manager just themselves) ───────
$reviewers = fetchAll(
    "SELECT e.id, CONCAT(e.first_name,' ',e.last_name) AS full_name
     FROM employees e
     WHERE e.employment_status = 'Active'
     ORDER BY e.first_name, e.last_name"
);

// ── Load existing review if editing ───────────────────────────────────────────
if ($isEdit) {
    $review = fetchOne("SELECT * FROM performance_reviews WHERE id = ?", 'i', $editId);
    if (!$review) {
        setFlash('error', 'Review not found.');
        header('Location: ' . BASE_URL . '/modules/performance/index.php');
        exit;
    }
    $pageTitle = 'Edit Performance Review';
}

// ── Default reviewer = current user's employee record ─────────────────────────
$defaultReviewerId = (int)($user['employee_id'] ?? 0);

// ── POST Handler ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $employeeId        = (int)($_POST['employee_id'] ?? 0);
        $reviewerId        = (int)($_POST['reviewer_id'] ?? $defaultReviewerId);
        $reviewPeriod      = sanitize($_POST['review_period'] ?? '');
        $reviewDate        = $_POST['review_date'] ?? date('Y-m-d');
        $kpiScore          = max(1, min(10, (float)($_POST['kpi_score'] ?? 0)));
        $communicationScore= max(1, min(10, (float)($_POST['communication_score'] ?? 0)));
        $teamworkScore     = max(1, min(10, (float)($_POST['teamwork_score'] ?? 0)));
        $leadershipScore   = max(1, min(10, (float)($_POST['leadership_score'] ?? 0)));
        $productivityScore = max(1, min(10, (float)($_POST['productivity_score'] ?? 0)));
        $overallScore      = ($kpiScore + $communicationScore + $teamworkScore + $leadershipScore + $productivityScore) / 5;
        $strengths         = trim($_POST['strengths'] ?? '');
        $improvements      = trim($_POST['improvements'] ?? '');
        $goals             = trim($_POST['goals'] ?? '');
        $managerComments   = trim($_POST['manager_comments'] ?? '');
        $status            = $isEdit ? sanitize($_POST['status'] ?? 'Draft') : 'Draft';

        // Validate
        if ($employeeId <= 0) $errors[] = 'Please select an employee.';
        if ($reviewPeriod === '') $errors[] = 'Review period is required.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reviewDate)) $errors[] = 'Invalid review date.';
        foreach (['kpi_score','communication_score','teamwork_score','leadership_score','productivity_score'] as $f) {
            $v = (float)($_POST[$f] ?? 0);
            if ($v < 1 || $v > 10) {
                $errors[] = 'All scores must be between 1 and 10.';
                break;
            }
        }

        if (empty($errors)) {
            if ($isEdit) {
                $ok = execute(
                    "UPDATE performance_reviews SET
                        employee_id=?, reviewer_id=?, review_period=?, review_date=?,
                        kpi_score=?, communication_score=?, teamwork_score=?,
                        leadership_score=?, productivity_score=?, overall_score=?,
                        strengths=?, improvements=?, goals=?, manager_comments=?, status=?
                     WHERE id=?",
                    'iissddddddsssssi',
                    $employeeId, $reviewerId, $reviewPeriod, $reviewDate,
                    $kpiScore, $communicationScore, $teamworkScore,
                    $leadershipScore, $productivityScore, $overallScore,
                    $strengths, $improvements, $goals, $managerComments, $status,
                    $editId
                );
                if ($ok !== false) {
                    setFlash('success', 'Review updated successfully.');
                    header('Location: ' . BASE_URL . '/modules/performance/view_review.php?id=' . $editId);
                    exit;
                }
                $errors[] = 'Failed to update review. Please try again.';
            } else {
                $ok = execute(
                    "INSERT INTO performance_reviews
                        (employee_id, reviewer_id, review_period, review_date,
                         kpi_score, communication_score, teamwork_score,
                         leadership_score, productivity_score, overall_score,
                         strengths, improvements, goals, manager_comments, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Draft')",
                    'iissddddddssss',
                    $employeeId, $reviewerId, $reviewPeriod, $reviewDate,
                    $kpiScore, $communicationScore, $teamworkScore,
                    $leadershipScore, $productivityScore, $overallScore,
                    $strengths, $improvements, $goals, $managerComments
                );
                if ($ok) {
                    // Notify employee
                    $empUser = fetchOne("SELECT u.id AS user_id, CONCAT(e.first_name,' ',e.last_name) AS name FROM employees e JOIN users u ON u.employee_id = e.id WHERE e.id=?", 'i', $employeeId);
                    if ($empUser && $empUser['user_id']) {
                        createNotification(
                            (int)$empUser['user_id'],
                            'Performance Review',
                            'A performance review for ' . $reviewPeriod . ' has been created. Please review and acknowledge.',
                            'performance',
                            BASE_URL . '/modules/performance/view_review.php?id=' . $ok
                        );
                    }
                    setFlash('success', 'Performance review created successfully.');
                    header('Location: ' . BASE_URL . '/modules/performance/view_review.php?id=' . $ok);
                    exit;
                }
                $errors[] = 'Failed to create review. Please try again.';
            }
        }

        // Re-populate
        $review = [
            'employee_id'         => $employeeId,
            'reviewer_id'         => $reviewerId,
            'review_period'       => $_POST['review_period'] ?? '',
            'review_date'         => $reviewDate,
            'kpi_score'           => $kpiScore,
            'communication_score' => $communicationScore,
            'teamwork_score'      => $teamworkScore,
            'leadership_score'    => $leadershipScore,
            'productivity_score'  => $productivityScore,
            'overall_score'       => $overallScore,
            'strengths'           => $_POST['strengths'] ?? '',
            'improvements'        => $_POST['improvements'] ?? '',
            'goals'               => $_POST['goals'] ?? '',
            'manager_comments'    => $_POST['manager_comments'] ?? '',
            'status'              => $_POST['status'] ?? 'Draft',
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
        <h1 class="text-2xl font-bold text-gray-900"><?= $isEdit ? 'Edit Performance Review' : 'New Performance Review' ?></h1>
        <p class="text-sm text-gray-500 mt-0.5">Fill in the scores and comments for this review period</p>
      </div>
      <a href="<?= BASE_URL ?>/modules/performance/index.php"
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

    <form method="POST" id="reviewForm" class="space-y-6">
      <?= csrfField() ?>

      <!-- Basic Info -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">Review Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Employee <span class="text-red-500">*</span></label>
            <select name="employee_id" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">— Select Employee —</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= $emp['id'] ?>"
                        <?= (int)($review['employee_id'] ?? 0) === (int)$emp['id'] ? 'selected' : '' ?>>
                  <?= sanitize($emp['full_name']) ?> (<?= sanitize($emp['emp_code']) ?>) — <?= sanitize($emp['dept_name'] ?? '') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700 mb-1">Reviewer <span class="text-red-500">*</span></label>
            <select name="reviewer_id" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="">— Select Reviewer —</option>
              <?php foreach ($reviewers as $rev): ?>
                <option value="<?= $rev['id'] ?>"
                        <?= (int)($review['reviewer_id'] ?? $defaultReviewerId) === (int)$rev['id'] ? 'selected' : '' ?>>
                  <?= sanitize($rev['full_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Review Period <span class="text-red-500">*</span></label>
            <input type="text" name="review_period" required
                   value="<?= htmlspecialchars($review['review_period'] ?? '') ?>"
                   placeholder="e.g. Q1 2026, Annual 2025"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Review Date <span class="text-red-500">*</span></label>
            <input type="date" name="review_date" required
                   value="<?= htmlspecialchars($review['review_date'] ?? date('Y-m-d')) ?>"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
          <?php if ($isEdit): ?>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="status"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
              <option value="Draft"        <?= ($review['status'] ?? '') === 'Draft'        ? 'selected' : '' ?>>Draft</option>
              <option value="Submitted"    <?= ($review['status'] ?? '') === 'Submitted'    ? 'selected' : '' ?>>Submitted</option>
              <option value="Acknowledged" <?= ($review['status'] ?? '') === 'Acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
            </select>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Scores -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">
          Performance Scores
          <span class="text-xs font-normal text-gray-400 ml-2">Rate each criterion from 1 (Poor) to 10 (Excellent)</span>
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5">
          <?php
          $scoreFields = [
            ['id' => 'kpiScore',           'name' => 'kpi_score',           'label' => 'KPI Score',           'icon' => '🎯'],
            ['id' => 'communicationScore', 'name' => 'communication_score', 'label' => 'Communication',        'icon' => '💬'],
            ['id' => 'teamworkScore',      'name' => 'teamwork_score',      'label' => 'Teamwork',             'icon' => '🤝'],
            ['id' => 'leadershipScore',    'name' => 'leadership_score',    'label' => 'Leadership',           'icon' => '🌟'],
            ['id' => 'productivityScore',  'name' => 'productivity_score',  'label' => 'Productivity',         'icon' => '⚡'],
          ];
          foreach ($scoreFields as $sf):
          ?>
            <div class="score-card bg-gray-50 rounded-xl p-4 text-center border-2 border-transparent hover:border-indigo-200 transition-colors">
              <div class="text-2xl mb-2"><?= $sf['icon'] ?></div>
              <label class="block text-xs font-semibold text-gray-600 mb-3"><?= $sf['label'] ?></label>
              <input type="number" name="<?= $sf['name'] ?>" id="<?= $sf['id'] ?>"
                     step="0.5" min="1" max="10" required
                     value="<?= htmlspecialchars((string)($review[$sf['name']] ?? '7')) ?>"
                     class="score-input w-full text-center text-2xl font-bold text-indigo-700 bg-white border-2 border-indigo-200 rounded-xl px-2 py-3 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
              <div class="mt-2">
                <div class="w-full bg-gray-200 rounded-full h-1.5">
                  <div class="score-bar bg-indigo-500 h-1.5 rounded-full transition-all"
                       id="bar_<?= $sf['id'] ?>"
                       style="width: <?= ((float)($review[$sf['name']] ?? 7) / 10 * 100) ?>%"></div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Overall Score Display -->
        <div class="mt-6 bg-indigo-50 border border-indigo-200 rounded-xl p-5 text-center">
          <p class="text-sm font-medium text-indigo-600 mb-1">Overall Score (Average)</p>
          <p class="text-4xl font-extrabold text-indigo-700" id="overallDisplay">
            <?= number_format((float)($review['overall_score'] ?? 7), 1) ?>
          </p>
          <div class="w-48 mx-auto mt-3 bg-indigo-200 rounded-full h-2.5">
            <div class="bg-indigo-600 h-2.5 rounded-full transition-all" id="overallBar"
                 style="width: <?= ((float)($review['overall_score'] ?? 7) / 10 * 100) ?>%"></div>
          </div>
          <p class="text-xs text-indigo-400 mt-2">Out of 10.0</p>
        </div>
      </div>

      <!-- Comments & Goals -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-4 pb-3 border-b border-gray-100">Comments &amp; Goals</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Strengths</label>
            <textarea name="strengths" rows="4"
                      placeholder="Key strengths and positive contributions…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"><?= htmlspecialchars($review['strengths'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Areas for Improvement</label>
            <textarea name="improvements" rows="4"
                      placeholder="Areas where growth and improvement are needed…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"><?= htmlspecialchars($review['improvements'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Goals for Next Period</label>
            <textarea name="goals" rows="4"
                      placeholder="Objectives and targets for the upcoming period…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"><?= htmlspecialchars($review['goals'] ?? '') ?></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Manager Comments</label>
            <textarea name="manager_comments" rows="4"
                      placeholder="Additional comments from the manager…"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y"><?= htmlspecialchars($review['manager_comments'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="flex gap-3">
        <button type="submit"
                class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
          <?= $isEdit ? 'Update Review' : 'Save Review' ?>
        </button>
        <a href="<?= BASE_URL ?>/modules/performance/index.php"
           class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition-colors">
          Cancel
        </a>
      </div>

    </form>
  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
var scoreIds = ['kpiScore','communicationScore','teamworkScore','leadershipScore','productivityScore'];

function recalcOverall() {
  var total = 0, count = 0;
  scoreIds.forEach(function(id) {
    var val = parseFloat(document.getElementById(id).value) || 0;
    total += val;
    count++;
    var pct = Math.min(100, Math.max(0, (val / 10) * 100));
    document.getElementById('bar_' + id).style.width = pct + '%';
  });
  var avg = count > 0 ? total / count : 0;
  document.getElementById('overallDisplay').textContent = avg.toFixed(1);
  document.getElementById('overallBar').style.width = Math.min(100, (avg / 10) * 100) + '%';
}

scoreIds.forEach(function(id) {
  document.getElementById(id).addEventListener('input', recalcOverall);
});

recalcOverall();
</script>
