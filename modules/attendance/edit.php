<?php
/**
 * HRMS - Edit Attendance Record
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('edit', 'attendance');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid attendance record.');
    header('Location: ' . BASE_URL . '/modules/attendance/index.php');
    exit;
}

// Load record with employee info
$record = fetchOne(
    "SELECT a.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.employee_id AS emp_code, e.photo
     FROM attendance a
     JOIN employees e ON a.employee_id = e.id
     WHERE a.id = ?",
    'i', $id
);

if (!$record) {
    setFlash('error', 'Attendance record not found.');
    header('Location: ' . BASE_URL . '/modules/attendance/index.php');
    exit;
}

$pageTitle = 'Edit Attendance';
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $date       = sanitize($_POST['date']       ?? '');
        $checkIn    = sanitize($_POST['check_in']   ?? '');
        $checkOut   = sanitize($_POST['check_out']  ?? '');
        $status     = sanitize($_POST['status']     ?? '');
        $notes      = sanitize($_POST['notes']      ?? '');

        $validStatuses = ['Present', 'Absent', 'Late', 'Half Day', 'On Leave'];

        if (empty($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = 'A valid date is required.';
        }
        if (!in_array($status, $validStatuses)) {
            $errors[] = 'Invalid status selected.';
        }

        // Calculate total_hours if both check_in and check_out provided
        $totalHours = null;
        if ($checkIn !== '' && $checkOut !== '') {
            $in  = strtotime($date . ' ' . $checkIn);
            $out = strtotime($date . ' ' . $checkOut);
            if ($out > $in) {
                $totalHours = round(($out - $in) / 3600, 2);
            }
        }

        if (empty($errors)) {
            $ok = execute(
                "UPDATE attendance
                 SET date = ?, check_in = ?, check_out = ?, total_hours = ?, status = ?, notes = ?
                 WHERE id = ?",
                'sssdssi',
                $date,
                $checkIn  !== '' ? $checkIn  : null,
                $checkOut !== '' ? $checkOut : null,
                $totalHours,
                $status,
                $notes !== '' ? $notes : null,
                $id
            );

            if ($ok !== false) {
                setFlash('success', 'Attendance record updated successfully.');
                header('Location: ' . BASE_URL . '/modules/attendance/index.php');
                exit;
            } else {
                $errors[] = 'Failed to update attendance record. Please try again.';
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
    <nav class="text-sm text-gray-500 mb-5 flex items-center gap-2">
      <a href="<?= BASE_URL ?>/modules/attendance/index.php" class="hover:text-indigo-600 transition-colors">Attendance</a>
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
      <span class="text-gray-900 font-medium">Edit Record</span>
    </nav>

    <!-- Errors -->
    <?php if (!empty($errors)): ?>
      <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4">
        <ul class="list-disc list-inside space-y-1">
          <?php foreach ($errors as $e): ?>
            <li class="text-sm text-red-700"><?= sanitize($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="max-w-xl">
      <!-- Employee info header -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 mb-5 flex items-center gap-4">
        <img src="<?= avatarUrl($record['photo'] ?? null) ?>" alt="Photo"
             class="w-12 h-12 rounded-full object-cover border border-gray-200">
        <div>
          <p class="font-semibold text-gray-900"><?= sanitize($record['emp_name']) ?></p>
          <p class="text-xs text-gray-400"><?= sanitize($record['emp_code']) ?></p>
        </div>
      </div>

      <form method="POST" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
        <?= csrfField() ?>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
          <input type="date" name="date" required
                 value="<?= htmlspecialchars($_POST['date'] ?? $record['date']) ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Check In</label>
            <input type="time" name="check_in"
                   value="<?= htmlspecialchars($_POST['check_in'] ?? $record['check_in'] ?? '') ?>"
                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Check Out</label>
            <input type="time" name="check_out"
                   value="<?= htmlspecialchars($_POST['check_out'] ?? $record['check_out'] ?? '') ?>"
                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
          <select name="status" required
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php foreach (['Present', 'Absent', 'Late', 'Half Day', 'On Leave'] as $s): ?>
              <option value="<?= $s ?>"
                <?= (($_POST['status'] ?? $record['status']) === $s) ? 'selected' : '' ?>>
                <?= $s ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea name="notes" rows="3"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"><?= htmlspecialchars($_POST['notes'] ?? $record['notes'] ?? '') ?></textarea>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button type="submit"
                  class="px-5 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors">
            Save Changes
          </button>
          <a href="<?= BASE_URL ?>/modules/attendance/index.php"
             class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
            Cancel
          </a>
        </div>
      </form>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
