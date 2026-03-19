<?php
/**
 * HRMS - Performance: View Review
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('view', 'performance');

$id        = (int)($_GET['id'] ?? 0);
$user      = currentUser();
$role      = $user['role'] ?? '';
$isHRAdmin = hasRole('Admin', 'HR Manager');
$isDM      = ($role === 'Head of Department');
$isAdmin   = ($role === 'Admin');

// Shared SELECT — joins users table to get emp_user_id correctly
$selectSql = "SELECT pr.*,
                     CONCAT(e.first_name,' ',e.last_name) AS emp_name,
                     e.employee_id AS emp_code, e.photo AS emp_photo, e.email,
                     e.position, d.name AS dept_name,
                     e.department_id AS emp_dept_id,
                     CONCAT(rv.first_name,' ',rv.last_name) AS reviewer_name,
                     u.id AS emp_user_id
              FROM performance_reviews pr
              JOIN employees e   ON pr.employee_id = e.id
              LEFT JOIN departments d  ON e.department_id = d.id
              LEFT JOIN employees rv  ON pr.reviewer_id  = rv.id
              LEFT JOIN users u       ON u.employee_id   = e.id";

if ($isHRAdmin) {
    $review = fetchOne($selectSql . " WHERE pr.id = ?", 'i', $id);
} elseif ($isDM) {
    // Head of Department: can view reviews for employees in their department
    $deptId = myDeptId();
    if ($deptId) {
        $review = fetchOne($selectSql . " WHERE pr.id = ? AND e.department_id = ?", 'ii', $id, $deptId);
    } else {
        $review = null;
    }
} else {
    // Employee: can only view their own review
    $empId = (int)($user['employee_id'] ?? 0);
    if (!$empId) {
        setFlash('error', 'No employee record linked to your account.');
        header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
        exit;
    }
    $review = fetchOne($selectSql . " WHERE pr.id = ? AND pr.employee_id = ?", 'ii', $id, $empId);
}

if (!$review) {
    setFlash('error', 'Performance review not found or access denied.');
    header('Location: ' . BASE_URL . '/modules/performance/index.php');
    exit;
}

$pageTitle = 'Review — ' . $review['emp_name'] . ' — ' . $review['review_period'];

// ── Determine if current user IS the reviewed employee ────────────────────────
$isOwnReview = ((int)$review['emp_user_id'] === (int)$user['id']);

// ── Handle employee acknowledgement / comment submission ──────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_comment_submit'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } elseif (!$isOwnReview) {
        $errors[] = 'Only the reviewed employee can submit comments.';
    } else {
        $employeeComment = trim($_POST['employee_comments'] ?? '');
        $ok = execute(
            "UPDATE performance_reviews
             SET employee_comments = ?, status = 'Acknowledged'
             WHERE id = ?",
            'si', $employeeComment, $id
        );
        if ($ok !== false) {
            // Reload review
            $review['employee_comments'] = $employeeComment;
            $review['status']            = 'Acknowledged';
            setFlash('success', 'Your comments have been submitted and the review is now Acknowledged.');
            header('Location: ' . BASE_URL . '/modules/performance/view_review.php?id=' . $id);
            exit;
        }
        $errors[] = 'Failed to submit comments. Please try again.';
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Helper: score color class
function scoreColorClass(float $score): string {
    if ($score >= 8) return 'text-green-700 bg-green-100 border-green-200';
    if ($score >= 6) return 'text-blue-700 bg-blue-100 border-blue-200';
    if ($score >= 4) return 'text-yellow-700 bg-yellow-100 border-yellow-200';
    return 'text-red-700 bg-red-100 border-red-200';
}

$scores = [
    'KPI'           => (float)$review['kpi_score'],
    'Communication' => (float)$review['communication_score'],
    'Teamwork'      => (float)$review['teamwork_score'],
    'Leadership'    => (float)$review['leadership_score'],
    'Productivity'  => (float)$review['productivity_score'],
];
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

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
      <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <?php foreach ($errors as $e): ?>
          <p class="text-sm text-red-700"><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
          <a href="<?= BASE_URL ?>/modules/performance/index.php" class="hover:text-indigo-600">Performance</a>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          <span class="text-gray-700">Review</span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Performance Review</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= sanitize($review['review_period']) ?> · <?= formatDate($review['review_date']) ?></p>
      </div>
      <div class="flex gap-2 shrink-0">
        <?php if (can('edit', 'performance')): ?>
          <a href="<?= BASE_URL ?>/modules/performance/add_review.php?edit=<?= $id ?>"
             class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit Review
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/performance/index.php"
           class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
          Back
        </a>
      </div>
    </div>

    <!-- Employee Card + Status -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <div class="flex flex-col sm:flex-row sm:items-center gap-6">
        <img src="<?= avatarUrl($review['emp_photo']) ?>" alt=""
             class="w-20 h-20 rounded-2xl object-cover border-2 border-indigo-100 shrink-0">
        <div class="flex-1 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
          <div>
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Employee</p>
            <p class="font-bold text-gray-900 mt-0.5"><?= sanitize($review['emp_name']) ?></p>
            <p class="text-xs text-gray-500"><?= sanitize($review['emp_code']) ?></p>
          </div>
          <div>
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Department</p>
            <p class="font-medium text-gray-700 mt-0.5"><?= sanitize($review['dept_name'] ?? '—') ?></p>
          </div>
          <div>
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Reviewer</p>
            <p class="font-medium text-gray-700 mt-0.5"><?= sanitize($review['reviewer_name'] ?? '—') ?></p>
          </div>
          <div>
            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Status</p>
            <div class="mt-0.5"><?= statusBadge($review['status']) ?></div>
          </div>
        </div>
        <!-- Overall Score -->
        <div class="text-center shrink-0">
          <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Overall</p>
          <?php
          $overall = (float)$review['overall_score'];
          $oc = scoreColorClass($overall);
          ?>
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl border-2 text-3xl font-extrabold <?= $oc ?>">
            <?= number_format($overall, 1) ?>
          </div>
          <p class="text-xs text-gray-400 mt-1">/ 10.0</p>
        </div>
      </div>
    </div>

    <!-- Charts & Scores -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Radar Chart -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Score Overview</h3>
        <div class="h-64">
          <canvas id="radarChart"></canvas>
        </div>
      </div>

      <!-- Bar Chart + Score Cards -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Individual Scores</h3>
        <div class="space-y-3">
          <?php foreach ($scores as $label => $score):
            $pct = ($score / 10) * 100;
            $barColor = $score >= 8 ? 'bg-green-500' : ($score >= 6 ? 'bg-blue-500' : ($score >= 4 ? 'bg-yellow-500' : 'bg-red-500'));
          ?>
            <div>
              <div class="flex items-center justify-between text-sm mb-1">
                <span class="font-medium text-gray-700"><?= $label ?></span>
                <span class="font-bold text-gray-900"><?= number_format($score, 1) ?> / 10</span>
              </div>
              <div class="w-full bg-gray-100 rounded-full h-2.5">
                <div class="<?= $barColor ?> h-2.5 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
          <!-- Overall -->
          <div class="pt-2 border-t border-gray-100">
            <div class="flex items-center justify-between text-sm mb-1">
              <span class="font-bold text-indigo-700">Overall Average</span>
              <span class="font-extrabold text-indigo-700"><?= number_format($overall, 1) ?> / 10</span>
            </div>
            <div class="w-full bg-indigo-100 rounded-full h-3">
              <div class="bg-indigo-600 h-3 rounded-full" style="width: <?= ($overall / 10) * 100 ?>%"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Comments Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

      <?php
      $commentFields = [
        ['label' => 'Strengths',                  'value' => $review['strengths']],
        ['label' => 'Areas for Improvement',       'value' => $review['improvements']],
        ['label' => 'Goals for Next Period',        'value' => $review['goals']],
        ['label' => 'Manager Comments',             'value' => $review['manager_comments']],
      ];
      foreach ($commentFields as $cf): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
          <h3 class="font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-100"><?= $cf['label'] ?></h3>
          <?php if (!empty($cf['value'])): ?>
            <p class="text-sm text-gray-700 whitespace-pre-wrap leading-relaxed"><?= sanitize($cf['value']) ?></p>
          <?php else: ?>
            <p class="text-sm text-gray-400 italic">No content provided.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Employee Comments -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <h3 class="font-semibold text-gray-800 mb-3 pb-2 border-b border-gray-100">Employee Comments</h3>

      <?php if (!empty($review['employee_comments'])): ?>
        <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-100 mb-4">
          <p class="text-sm text-indigo-900 whitespace-pre-wrap leading-relaxed"><?= sanitize($review['employee_comments']) ?></p>
        </div>
        <?php if ($review['status'] === 'Acknowledged'): ?>
          <div class="flex items-center gap-2 text-sm text-green-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            This review has been acknowledged by the employee.
          </div>
        <?php endif; ?>
      <?php elseif ($isOwnReview && $review['status'] !== 'Acknowledged'): ?>
        <!-- Employee can submit their comments -->
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="employee_comment_submit" value="1">
          <textarea name="employee_comments" rows="5" required
                    placeholder="Share your perspective on this review, any feedback, or comments…"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-y mb-4"></textarea>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Submit Comments &amp; Acknowledge Review
          </button>
          <p class="text-xs text-gray-400 mt-2">Submitting will change the review status to Acknowledged.</p>
        </form>
      <?php else: ?>
        <p class="text-sm text-gray-400 italic">No employee comments yet.</p>
      <?php endif; ?>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// ── Radar Chart ────────────────────────────────────────────────────────────────
(function() {
  var ctx = document.getElementById('radarChart').getContext('2d');
  new Chart(ctx, {
    type: 'radar',
    data: {
      labels: <?= json_encode(array_keys($scores)) ?>,
      datasets: [{
        label: '<?= sanitize($review['emp_name']) ?>',
        data: <?= json_encode(array_values($scores)) ?>,
        backgroundColor: 'rgba(99, 102, 241, 0.2)',
        borderColor: 'rgba(99, 102, 241, 1)',
        borderWidth: 2,
        pointBackgroundColor: 'rgba(99, 102, 241, 1)',
        pointBorderColor: '#fff',
        pointHoverBackgroundColor: '#fff',
        pointHoverBorderColor: 'rgba(99, 102, 241, 1)',
        pointRadius: 5,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        r: {
          min: 0,
          max: 10,
          ticks: {
            stepSize: 2,
            font: { size: 10 },
            color: '#9ca3af',
          },
          grid: { color: 'rgba(0,0,0,0.07)' },
          pointLabels: {
            font: { size: 12, weight: '600' },
            color: '#374151'
          }
        }
      },
      plugins: {
        legend: { display: false }
      }
    }
  });
})();
</script>
