<?php
/**
 * HRMS - Apply for Leave
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('create', 'leaves');

$pageTitle = 'Apply for Leave';
$user      = currentUser();

// Resolve employee record
$employeeId = (int)($user['employee_id'] ?? 0);
if ($employeeId <= 0) {
    setFlash('error', 'No employee profile linked to your account. Please contact HR.');
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$employee = fetchOne(
    "SELECT e.*, d.name AS dept_name FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE e.id = ?",
    'i', $employeeId
);
if (!$employee) {
    setFlash('error', 'Employee record not found.');
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

// Fetch active leave types
$leaveTypes = fetchAll(
    "SELECT * FROM leave_types WHERE is_active = 1 ORDER BY name"
);

// Fetch employee's leave balances for current year
$currentYear = (int)date('Y');
$balances = fetchAll(
    "SELECT lb.*, lt.name AS leave_type_name
     FROM leave_balances lb
     JOIN leave_types lt ON lb.leave_type_id = lt.id
     WHERE lb.employee_id = ? AND lb.year = ?",
    'ii', $employeeId, $currentYear
);
$balanceMap = [];
foreach ($balances as $b) {
    $balanceMap[$b['leave_type_id']] = $b;
}

$errors = [];
$values = [
    'leave_type_id' => '',
    'start_date'    => '',
    'end_date'      => '',
    'reason'        => '',
];

// ── POST Handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $values['leave_type_id'] = (int)($_POST['leave_type_id'] ?? 0);
        $values['start_date']    = sanitize($_POST['start_date'] ?? '');
        $values['end_date']      = sanitize($_POST['end_date']   ?? '');
        $values['reason']        = sanitize($_POST['reason']     ?? '');

        // Validation
        if ($values['leave_type_id'] <= 0) $errors[] = 'Please select a leave type.';
        if ($values['start_date'] === '')   $errors[] = 'Start date is required.';
        if ($values['end_date']   === '')   $errors[] = 'End date is required.';
        if ($values['start_date'] !== '' && $values['end_date'] !== '') {
            if ($values['end_date'] < $values['start_date'])
                $errors[] = 'End date cannot be before start date.';
            if ($values['start_date'] < date('Y-m-d'))
                $errors[] = 'Start date cannot be in the past.';
        }
        if ($values['reason'] === '') $errors[] = 'Reason for leave is required.';

        // Validate leave type
        $leaveType = null;
        if ($values['leave_type_id'] > 0) {
            $leaveType = fetchOne(
                "SELECT * FROM leave_types WHERE id = ? AND is_active = 1",
                'i', $values['leave_type_id']
            );
            if (!$leaveType) $errors[] = 'Invalid or inactive leave type selected.';
        }

        // Calculate working days (Mon–Fri)
        $totalDays = 0;
        if (empty($errors) && $values['start_date'] !== '' && $values['end_date'] !== '') {
            $start = new DateTime($values['start_date']);
            $end   = new DateTime($values['end_date']);
            $end->modify('+1 day');
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);
            foreach ($period as $day) {
                if ((int)$day->format('N') < 6) $totalDays++;
            }
            if ($totalDays <= 0)
                $errors[] = 'The selected date range contains no working days (Mon–Fri).';
        }

        // Check leave balance
        if (empty($errors) && $leaveType) {
            $balance = $balanceMap[$values['leave_type_id']] ?? null;
            if ($balance !== null && $totalDays > (int)$balance['remaining']) {
                $errors[] = 'Insufficient leave balance. You have ' . $balance['remaining'] .
                            ' day(s) remaining for ' . $leaveType['name'] .
                            ', but requested ' . $totalDays . ' day(s).';
            }
        }

        // Check for overlapping leave requests
        if (empty($errors)) {
            $overlap = fetchOne(
                "SELECT id FROM leave_requests
                 WHERE employee_id = ?
                   AND status IN ('Pending Department Approval','Pending HR Approval','Approved')
                   AND start_date <= ? AND end_date >= ?",
                'iss', $employeeId, $values['end_date'], $values['start_date']
            );
            if ($overlap)
                $errors[] = 'You already have a pending or approved leave request overlapping with the selected dates.';
        }

        // Handle file attachment
        $attachment = null;
        if (!empty($errors) === false && isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Only process if no other errors so far, but attachment errors are standalone
        }
        // Process attachment independently
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['attachment'];
            $allowed = ['application/pdf','image/jpeg','image/png',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload error. Please try again.';
            } elseif (!in_array($file['type'], $allowed)) {
                $errors[] = 'Attachment must be PDF, JPG, PNG, DOC, or DOCX.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = 'Attachment must not exceed 5 MB.';
            } else {
                $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $attachment = 'leave_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest       = __DIR__ . '/../../uploads/leave_attachments/' . $attachment;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $errors[] = 'Failed to save attachment. Please try again.';
                    $attachment = null;
                }
            }
        }

        if (empty($errors)) {
            $newLeaveId = execute(
                "INSERT INTO leave_requests
                    (employee_id, leave_type_id, start_date, end_date, total_days, reason, attachment, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending Department Approval')",
                'iississ',
                $employeeId,
                $values['leave_type_id'],
                $values['start_date'],
                $values['end_date'],
                $totalDays,
                $values['reason'],
                $attachment
            );

            if ($newLeaveId) {
                $empFullName    = $employee['first_name'] . ' ' . $employee['last_name'];
                $leaveTypeName  = $leaveType['name'] ?? 'leave';
                $startFormatted = date('M j, Y', strtotime($values['start_date']));
                $viewLink       = BASE_URL . '/modules/leaves/view.php?id=' . (int)$newLeaveId;

                // Notify the Head of Department of the employee's department
                if (!empty($employee['department_id'])) {
                    $dmUser = fetchOne(
                        "SELECT u.id FROM users u
                         JOIN employees e ON u.employee_id = e.id
                         WHERE e.department_id = ? AND u.role = 'Head of Department' AND u.is_active = 1
                         LIMIT 1",
                        'i', (int)$employee['department_id']
                    );
                    if ($dmUser) {
                        createNotification(
                            (int)$dmUser['id'],
                            'New Leave Request Awaiting Your Approval',
                            $empFullName . ' has submitted a ' . $leaveTypeName .
                            ' request for ' . $totalDays . ' day(s) starting ' . $startFormatted .
                            '. Please review and take action.',
                            'leave', $viewLink
                        );
                    }
                }

                setFlash('success', 'Your leave request has been submitted and is pending Head of Department approval.');
                header('Location: ' . BASE_URL . '/modules/leaves/index.php');
                exit;
            } else {
                $errors[] = 'Failed to submit leave request. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6">

    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-6">
      <a href="<?= BASE_URL ?>/modules/leaves/index.php" class="hover:text-indigo-600 transition-colors">Leave Management</a>
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
      <span class="text-gray-900 font-medium">Apply for Leave</span>
    </nav>

    <div class="max-w-3xl mx-auto space-y-6">

      <!-- Employee Info Banner -->
      <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-center gap-4">
        <img src="<?= avatarUrl($employee['photo']) ?>" alt="Avatar"
             class="w-12 h-12 rounded-full object-cover ring-2 ring-indigo-200">
        <div class="flex-1">
          <p class="font-semibold text-indigo-900">
            <?= sanitize($employee['first_name'] . ' ' . $employee['last_name']) ?>
          </p>
          <p class="text-sm text-indigo-700">
            <?= sanitize($employee['employee_id']) ?>
            <?php if ($employee['position']): ?> · <?= sanitize($employee['position']) ?><?php endif; ?>
            <?php if ($employee['dept_name']): ?> · <?= sanitize($employee['dept_name']) ?><?php endif; ?>
          </p>
        </div>
        <div class="text-right">
          <p class="text-xs text-indigo-500">Year <?= $currentYear ?></p>
        </div>
      </div>

      <!-- Leave Balance Cards -->
      <?php if (!empty($balances)): ?>
        <div>
          <h3 class="text-sm font-semibold text-gray-700 mb-3">Your Leave Balances (<?= $currentYear ?>)</h3>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php foreach ($balances as $bal): ?>
              <div class="bg-white border border-gray-200 rounded-lg p-3 text-center shadow-sm"
                   id="balance-card-<?= $bal['leave_type_id'] ?>">
                <p class="text-xs text-gray-500 truncate"><?= sanitize($bal['leave_type_name']) ?></p>
                <p class="text-2xl font-bold mt-1 <?= (int)$bal['remaining'] <= 0 ? 'text-red-600' : 'text-indigo-600' ?>">
                  <?= (int)$bal['remaining'] ?>
                </p>
                <p class="text-xs text-gray-400 mt-0.5">of <?= (int)$bal['allocated'] ?> remaining</p>
                <div class="mt-2 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                  <?php
                  $pct      = $bal['allocated'] > 0 ? min(100, round(($bal['remaining'] / $bal['allocated']) * 100)) : 0;
                  $barColor = $pct > 50 ? 'bg-green-500' : ($pct > 20 ? 'bg-yellow-500' : 'bg-red-500');
                  ?>
                  <div class="<?= $barColor ?> h-full rounded-full" style="width:<?= $pct ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Workflow Info -->
      <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-blue-800">
          Leave requests require <strong>two approvals</strong>: first your Head of Department, then HR. You will be notified at each stage.
        </p>
      </div>

      <!-- Form Card -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

        <!-- Card Header -->
        <div class="px-6 py-5" style="background:#0B2545;">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:rgba(255,255,255,0.15);">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-lg font-semibold text-white">Leave Application</h2>
              <p class="text-sm" style="color:rgba(255,255,255,0.65);">Fill in the form to submit your leave request.</p>
            </div>
          </div>
        </div>

        <!-- Errors -->
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
        <form method="POST" action="" enctype="multipart/form-data" class="p-6 space-y-5" id="leave-form">
          <?= csrfField() ?>

          <!-- Leave Type -->
          <div>
            <label for="leave_type_id" class="block text-sm font-medium text-gray-700 mb-1.5">
              Leave Type <span class="text-red-500">*</span>
            </label>
            <select id="leave_type_id" name="leave_type_id" required
                    class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900 bg-white
                           focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
              <option value="">— Select Leave Type —</option>
              <?php foreach ($leaveTypes as $lt): ?>
                <?php
                $bal = $balanceMap[$lt['id']] ?? null;
                $rem = $bal ? (int)$bal['remaining'] : null;
                ?>
                <option value="<?= $lt['id'] ?>"
                        data-remaining="<?= $rem !== null ? $rem : 'N/A' ?>"
                        data-name="<?= sanitize($lt['name']) ?>"
                        <?= $values['leave_type_id'] == $lt['id'] ? 'selected' : '' ?>
                        <?= ($rem !== null && $rem <= 0) ? 'class="text-red-500"' : '' ?>>
                  <?= sanitize($lt['name']) ?>
                  <?php if ($rem !== null): ?>
                    (<?= $rem ?> day<?= $rem !== 1 ? 's' : '' ?> remaining)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div id="balance-display" class="mt-2 hidden">
              <p class="text-sm text-indigo-700 font-medium" id="balance-text"></p>
            </div>
          </div>

          <!-- Dates Row -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1.5">
                Start Date <span class="text-red-500">*</span>
              </label>
              <input type="date" id="start_date" name="start_date" required
                     value="<?= htmlspecialchars($values['start_date']) ?>"
                     min="<?= date('Y-m-d') ?>"
                     class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
            </div>
            <div>
              <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1.5">
                End Date <span class="text-red-500">*</span>
              </label>
              <input type="date" id="end_date" name="end_date" required
                     value="<?= htmlspecialchars($values['end_date']) ?>"
                     min="<?= date('Y-m-d') ?>"
                     class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900
                            focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
            </div>
          </div>

          <!-- Working Days Preview -->
          <div id="days-preview" class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 hidden">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <p class="text-sm font-medium text-indigo-800">
                Working days requested: <span id="working-days-count" class="font-bold text-indigo-900">0</span>
              </p>
            </div>
          </div>

          <!-- Reason -->
          <div>
            <label for="reason" class="block text-sm font-medium text-gray-700 mb-1.5">
              Reason <span class="text-red-500">*</span>
            </label>
            <textarea id="reason" name="reason" rows="4" required
                      placeholder="Please describe the reason for your leave request..."
                      class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm text-gray-900 placeholder-gray-400 resize-none
                             focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"><?= htmlspecialchars($values['reason']) ?></textarea>
          </div>

          <!-- Attachment -->
          <div>
            <label for="attachment" class="block text-sm font-medium text-gray-700 mb-1.5">
              Supporting Document <span class="text-gray-400 font-normal">(optional)</span>
            </label>
            <input type="file" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                   class="w-full border border-gray-300 rounded-lg px-3.5 py-2 text-sm text-gray-700 bg-white
                          file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium
                          file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100
                          focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            <p class="mt-1 text-xs text-gray-400">PDF, JPG, PNG, DOC, DOCX — max 5 MB</p>
          </div>

          <!-- Actions -->
          <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
            <a href="<?= BASE_URL ?>/modules/leaves/index.php"
               class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
              Cancel
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-white text-sm font-medium rounded-lg transition-colors shadow-sm"
                    style="background:#0B2545;" onmouseover="this.style.background='#1a3a6b'" onmouseout="this.style.background='#0B2545'">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
              </svg>
              Submit Leave Request
            </button>
          </div>
        </form>
      </div>

    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
(function () {
  const leaveTypeSelect = document.getElementById('leave_type_id');
  const startInput      = document.getElementById('start_date');
  const endInput        = document.getElementById('end_date');
  const daysPreview     = document.getElementById('days-preview');
  const daysCount       = document.getElementById('working-days-count');
  const balanceDisplay  = document.getElementById('balance-display');
  const balanceText     = document.getElementById('balance-text');

  function calcWorkingDays(startStr, endStr) {
    if (!startStr || !endStr) return 0;
    const start = new Date(startStr + 'T00:00:00');
    const end   = new Date(endStr   + 'T00:00:00');
    if (end < start) return 0;
    let count = 0;
    const cur = new Date(start);
    while (cur <= end) {
      const dow = cur.getDay();
      if (dow !== 0 && dow !== 6) count++;
      cur.setDate(cur.getDate() + 1);
    }
    return count;
  }

  function updateDaysPreview() {
    const days = calcWorkingDays(startInput.value, endInput.value);
    if (days > 0) {
      daysCount.textContent = days;
      daysPreview.classList.remove('hidden');
    } else {
      daysPreview.classList.add('hidden');
    }
  }

  function updateBalanceDisplay() {
    const opt = leaveTypeSelect.options[leaveTypeSelect.selectedIndex];
    if (!opt || !opt.value) { balanceDisplay.classList.add('hidden'); return; }
    const remaining = opt.dataset.remaining;
    const name      = opt.dataset.name;
    if (remaining !== undefined && remaining !== 'N/A') {
      const rem = parseInt(remaining);
      balanceText.textContent = 'Available balance for ' + name + ': ' + rem + ' day' + (rem !== 1 ? 's' : '');
      balanceText.className = rem <= 0 ? 'text-sm text-red-700 font-medium' : 'text-sm text-indigo-700 font-medium';
      balanceDisplay.classList.remove('hidden');
    } else {
      balanceDisplay.classList.add('hidden');
    }
  }

  startInput.addEventListener('change', function () {
    if (endInput.value && endInput.value < this.value) endInput.value = this.value;
    endInput.min = this.value;
    updateDaysPreview();
  });
  endInput.addEventListener('change', updateDaysPreview);
  leaveTypeSelect.addEventListener('change', updateBalanceDisplay);

  updateDaysPreview();
  updateBalanceDisplay();
})();
</script>
