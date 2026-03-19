<?php
/**
 * HRMS - Leave Request Detail & Approval Page
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('view', 'leaves');

$pageTitle = 'Leave Request Detail';
$user      = currentUser();
$role      = $user['role'] ?? '';
$isDM      = ($role === 'Head of Department');
$isHRMgr   = ($role === 'HR Manager');
$isAdmin   = ($role === 'Admin');
$isEmp     = ($role === 'Employee');

$leaveId = (int)($_GET['id'] ?? 0);
if ($leaveId <= 0) {
    setFlash('error', 'Invalid leave request.');
    header('Location: ' . BASE_URL . '/modules/leaves/index.php');
    exit;
}

// Fetch leave with all reviewer details
$leave = fetchOne(
    "SELECT lr.*,
            lt.name AS leave_type_name,
            CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.employee_id AS emp_code,
            e.photo AS emp_photo,
            e.position AS emp_position,
            e.department_id AS emp_dept_id,
            d.name AS dept_name,
            eu.id AS emp_user_id,
            dm.username AS dm_reviewer_name,
            dmr.first_name AS dm_first, dmr.last_name AS dm_last,
            hr.username AS hr_reviewer_name,
            hrr.first_name AS hr_first, hrr.last_name AS hr_last
     FROM leave_requests lr
     JOIN employees   e   ON lr.employee_id   = e.id
     JOIN leave_types lt  ON lr.leave_type_id = lt.id
     LEFT JOIN departments d   ON e.department_id  = d.id
     LEFT JOIN users eu        ON eu.employee_id   = e.id
     LEFT JOIN users dm        ON lr.dm_action_by  = dm.id
     LEFT JOIN employees dmr   ON dm.employee_id   = dmr.id
     LEFT JOIN users hr        ON lr.hr_action_by  = hr.id
     LEFT JOIN employees hrr   ON hr.employee_id   = hrr.id
     WHERE lr.id = ?",
    'i', $leaveId
);

if (!$leave) {
    setFlash('error', 'Leave request not found.');
    header('Location: ' . BASE_URL . '/modules/leaves/index.php');
    exit;
}

// Access control
if ($isEmp) {
    // Employee can only view their own
    if ((int)($user['employee_id'] ?? 0) !== (int)$leave['employee_id']) {
        setFlash('error', 'Access denied.');
        header('Location: ' . BASE_URL . '/modules/leaves/index.php');
        exit;
    }
} elseif ($isDM) {
    // DM can only view their department's employees
    $myDept = myDeptId();
    if ($myDept === null || (int)$leave['emp_dept_id'] !== $myDept) {
        setFlash('error', 'Access denied. This leave request is outside your department.');
        header('Location: ' . BASE_URL . '/modules/leaves/index.php');
        exit;
    }
}

// Can this user take action?
$canActDM = $isDM && $leave['status'] === 'Pending Department Approval';
$canActHR = $isHRMgr && $leave['status'] === 'Pending HR Approval';
$canAct   = $canActDM || $canActHR;

$flash = getFlash();

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
      <span class="text-gray-900 font-medium">Request #<?= $leaveId ?></span>
    </nav>

    <!-- Flash -->
    <?php if ($flash): ?>
      <div class="mb-6 rounded-lg px-4 py-3 text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <div class="max-w-3xl mx-auto space-y-6">

      <!-- Status Header Card -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div class="flex items-center gap-4">
            <img src="<?= avatarUrl($leave['emp_photo']) ?>" alt=""
                 class="w-14 h-14 rounded-full object-cover ring-2 ring-gray-200">
            <div>
              <p class="text-lg font-bold text-gray-900"><?= sanitize($leave['emp_name']) ?></p>
              <p class="text-sm text-gray-500">
                <?= sanitize($leave['emp_code']) ?>
                <?php if ($leave['emp_position']): ?> · <?= sanitize($leave['emp_position']) ?><?php endif; ?>
                <?php if ($leave['dept_name']): ?> · <?= sanitize($leave['dept_name']) ?><?php endif; ?>
              </p>
              <p class="text-xs text-gray-400 mt-1">Submitted <?= formatDate($leave['created_at']) ?></p>
            </div>
          </div>
          <div class="text-right">
            <?= statusBadge($leave['status']) ?>
            <p class="text-xs text-gray-400 mt-1">Request #<?= $leaveId ?></p>
          </div>
        </div>
      </div>

      <!-- Leave Details Card -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100" style="background:#f8fafc;">
          <h3 class="font-semibold text-gray-800">Leave Details</h3>
        </div>
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
          <div>
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Leave Type</p>
            <p class="text-sm font-semibold text-gray-900 mt-0.5"><?= sanitize($leave['leave_type_name']) ?></p>
          </div>
          <div>
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Duration</p>
            <p class="text-sm font-semibold text-gray-900 mt-0.5">
              <?= (int)$leave['total_days'] ?> working day<?= (int)$leave['total_days'] !== 1 ? 's' : '' ?>
            </p>
          </div>
          <div>
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Start Date</p>
            <p class="text-sm font-semibold text-gray-900 mt-0.5"><?= formatDate($leave['start_date']) ?></p>
          </div>
          <div>
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">End Date</p>
            <p class="text-sm font-semibold text-gray-900 mt-0.5"><?= formatDate($leave['end_date']) ?></p>
          </div>
          <div class="sm:col-span-2">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Reason</p>
            <p class="text-sm text-gray-700 mt-0.5 leading-relaxed"><?= nl2br(htmlspecialchars($leave['reason'] ?? '')) ?></p>
          </div>
          <?php if ($leave['attachment']): ?>
            <div class="sm:col-span-2">
              <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Attachment</p>
              <a href="<?= BASE_URL ?>/uploads/leave_attachments/<?= rawurlencode($leave['attachment']) ?>"
                 target="_blank"
                 class="inline-flex items-center gap-2 mt-1 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                View Supporting Document
              </a>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Approval Timeline -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100" style="background:#f8fafc;">
          <h3 class="font-semibold text-gray-800">Approval Timeline</h3>
        </div>
        <div class="p-6">
          <div class="relative">
            <!-- Connecting line -->
            <div class="absolute left-5 top-10 bottom-10 w-0.5 bg-gray-200"></div>

            <div class="space-y-8">

              <!-- Stage 1: Head of Department -->
              <div class="flex gap-4">
                <?php
                $dmDone     = !empty($leave['dm_action_by']);
                $dmApproved = $dmDone && $leave['status'] !== 'Rejected by Head of Department';
                $dmRejected = $leave['status'] === 'Rejected by Head of Department';
                ?>
                <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center z-10
                  <?= $dmRejected ? 'bg-red-100' : ($dmApproved ? 'bg-green-100' : 'bg-gray-100') ?>">
                  <?php if ($dmRejected): ?>
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  <?php elseif ($dmApproved): ?>
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                  <?php else: ?>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  <?php endif; ?>
                </div>
                <div class="flex-1 pt-1">
                  <p class="text-sm font-semibold text-gray-900">Stage 1 — Head of Department Approval</p>
                  <?php if ($dmDone): ?>
                    <p class="text-sm mt-0.5 <?= $dmRejected ? 'text-red-600' : 'text-green-700' ?>">
                      <?= $dmRejected ? 'Rejected' : 'Approved' ?> by
                      <?php
                      $dmFullName = trim(($leave['dm_first'] ?? '') . ' ' . ($leave['dm_last'] ?? ''));
                      echo sanitize($dmFullName ?: $leave['dm_reviewer_name'] ?? 'Head of Department');
                      ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5"><?= formatDate($leave['dm_action_at']) ?></p>
                    <?php if ($leave['dm_comment']): ?>
                      <div class="mt-2 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                        <p class="text-xs font-medium text-gray-500">Comment</p>
                        <p class="text-sm text-gray-700 mt-0.5"><?= htmlspecialchars($leave['dm_comment']) ?></p>
                      </div>
                    <?php endif; ?>
                  <?php elseif ($leave['status'] === 'Pending Department Approval'): ?>
                    <p class="text-sm text-amber-600 mt-0.5">Awaiting department manager review</p>
                  <?php else: ?>
                    <p class="text-sm text-gray-400 mt-0.5">Not yet reached</p>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Stage 2: HR Manager -->
              <div class="flex gap-4">
                <?php
                $hrDone     = !empty($leave['hr_action_by']);
                $hrApproved = $leave['status'] === 'Approved';
                $hrRejected = $leave['status'] === 'Rejected by HR';
                $hrPending  = $leave['status'] === 'Pending HR Approval';
                $hrBlocked  = in_array($leave['status'], ['Pending Department Approval', 'Rejected by Head of Department', 'Cancelled']);
                ?>
                <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center z-10
                  <?= $hrRejected ? 'bg-red-100' : ($hrApproved ? 'bg-green-100' : ($hrPending ? 'bg-blue-50' : 'bg-gray-100')) ?>">
                  <?php if ($hrRejected): ?>
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  <?php elseif ($hrApproved): ?>
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                  <?php elseif ($hrPending): ?>
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  <?php else: ?>
                    <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  <?php endif; ?>
                </div>
                <div class="flex-1 pt-1">
                  <p class="text-sm font-semibold text-gray-900">Stage 2 — HR Manager Final Approval</p>
                  <?php if ($hrDone): ?>
                    <p class="text-sm mt-0.5 <?= $hrRejected ? 'text-red-600' : 'text-green-700' ?>">
                      <?= $hrRejected ? 'Rejected' : 'Approved' ?> by
                      <?php
                      $hrFullName = trim(($leave['hr_first'] ?? '') . ' ' . ($leave['hr_last'] ?? ''));
                      echo sanitize($hrFullName ?: $leave['hr_reviewer_name'] ?? 'HR Manager');
                      ?>
                    </p>
                    <p class="text-xs text-gray-400 mt-0.5"><?= formatDate($leave['hr_action_at']) ?></p>
                    <?php if ($leave['hr_comment']): ?>
                      <div class="mt-2 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                        <p class="text-xs font-medium text-gray-500">Comment</p>
                        <p class="text-sm text-gray-700 mt-0.5"><?= htmlspecialchars($leave['hr_comment']) ?></p>
                      </div>
                    <?php endif; ?>
                  <?php elseif ($hrPending): ?>
                    <p class="text-sm text-blue-600 mt-0.5">Awaiting HR review — Head of Department has approved</p>
                  <?php elseif ($hrBlocked): ?>
                    <p class="text-sm text-gray-400 mt-0.5">Requires department approval first</p>
                  <?php else: ?>
                    <p class="text-sm text-gray-400 mt-0.5">Not applicable</p>
                  <?php endif; ?>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

      <!-- Action Form (shown only to authorized reviewers) -->
      <?php if ($canAct): ?>
        <div class="bg-white rounded-xl border-2 shadow-sm overflow-hidden" style="border-color:#0B2545;">
          <div class="px-6 py-4 border-b border-gray-100" style="background:#0B2545;">
            <h3 class="font-semibold text-white">
              <?= $canActDM ? 'Head of Department Decision' : 'HR Manager Decision' ?>
            </h3>
            <p class="text-sm mt-0.5" style="color:rgba(255,255,255,0.65);">
              Add an optional comment and choose to approve or reject this request.
            </p>
          </div>
          <form method="POST" action="<?= BASE_URL ?>/modules/leaves/approve.php" class="p-6 space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="leave_id" value="<?= $leaveId ?>">

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Comment <span class="text-gray-400 font-normal">(required for rejection)</span>
              </label>
              <textarea name="comment" rows="3" id="dm-comment"
                        placeholder="Add a comment or reason..."
                        class="w-full border border-gray-300 rounded-lg px-3.5 py-2.5 text-sm placeholder-gray-400 resize-none
                               focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"></textarea>
            </div>

            <div class="flex items-center gap-3 pt-2">
              <!-- Approve -->
              <button type="submit" name="action" value="approve"
                      class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
                      onclick="return confirmAction('approve', this.form)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <?= $canActDM ? 'Approve & Forward to HR' : 'Approve — Final' ?>
              </button>

              <!-- Reject -->
              <button type="submit" name="action" value="reject"
                      class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm"
                      onclick="return confirmAction('reject', this.form)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Reject
              </button>

              <a href="<?= BASE_URL ?>/modules/leaves/index.php"
                 class="ml-auto text-sm text-gray-500 hover:text-gray-700">← Back to list</a>
            </div>
          </form>
        </div>
      <?php else: ?>
        <div class="text-center">
          <a href="<?= BASE_URL ?>/modules/leaves/index.php"
             class="text-sm text-indigo-600 hover:underline">← Back to Leave Requests</a>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
function confirmAction(action, form) {
  if (action === 'reject') {
    const comment = form.querySelector('[name="comment"]').value.trim();
    if (!comment) {
      alert('Please enter a reason for rejection before proceeding.');
      form.querySelector('[name="comment"]').focus();
      return false;
    }
    return confirm('Are you sure you want to REJECT this leave request?');
  }
  return confirm('Are you sure you want to APPROVE this leave request?');
}
</script>
