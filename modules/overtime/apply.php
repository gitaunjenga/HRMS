<?php
/**
 * HRMS - Apply for Overtime
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireCan('create', 'overtime');

$pageTitle = 'Request Overtime';
$user      = currentUser();
$empId     = (int)($user['employee_id'] ?? 0);
$error     = '';

if (!$empId) {
    setFlash('error', 'No employee record linked to your account.');
    header('Location: ' . BASE_URL . '/modules/overtime/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $date      = sanitize($_POST['request_date'] ?? '');
        $startTime = sanitize($_POST['start_time'] ?? '');
        $endTime   = sanitize($_POST['end_time'] ?? '');
        $reason    = sanitize($_POST['reason'] ?? '');

        if (!$date || !$startTime || !$endTime || !$reason) {
            $error = 'All fields are required.';
        } elseif ($endTime <= $startTime) {
            $error = 'End time must be after start time.';
        } else {
            $s    = strtotime('1970-01-01 ' . $startTime);
            $e    = strtotime('1970-01-01 ' . $endTime);
            $hrs  = round(($e - $s) / 3600, 2);

            execute(
                "INSERT INTO overtime_requests (employee_id, request_date, start_time, end_time, hours_requested, reason)
                 VALUES (?,?,?,?,?,?)",
                'isssds', $empId, $date, $startTime, $endTime, $hrs, $reason
            );
            logAudit('create', 'overtime', "Requested {$hrs}h overtime on {$date}");

            // Notify HR
            $hrUsers = fetchAll("SELECT id FROM users WHERE role IN ('Admin','HR Manager') AND is_active = 1");
            foreach ($hrUsers as $hr) {
                createNotification(
                    $hr['id'],
                    'Overtime Request',
                    "{$user['username']} requested {$hrs}h overtime on {$date}.",
                    'overtime',
                    BASE_URL . '/modules/overtime/index.php'
                );
            }

            setFlash('success', 'Overtime request submitted successfully.');
            header('Location: ' . BASE_URL . '/modules/overtime/index.php');
            exit;
        }
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">
  <div class="max-w-lg mx-auto">

    <div class="flex items-center gap-4 mb-6">
      <a href="<?= BASE_URL ?>/modules/overtime/index.php"
         class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Request Overtime</h1>
        <p class="text-sm text-gray-500 mt-0.5">Submit an overtime work request for approval</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-100 text-red-800 border border-red-200"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <form method="POST" action="" class="space-y-5">
        <?= csrfField() ?>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Date *</label>
          <input type="date" name="request_date" required
                 value="<?= htmlspecialchars($_POST['request_date'] ?? date('Y-m-d')) ?>"
                 min="<?= date('Y-m-d', strtotime('-30 days')) ?>"
                 max="<?= date('Y-m-d', strtotime('+7 days')) ?>"
                 class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Start Time *</label>
            <input type="time" name="start_time" required
                   value="<?= htmlspecialchars($_POST['start_time'] ?? '') ?>"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">End Time *</label>
            <input type="time" name="end_time" required
                   value="<?= htmlspecialchars($_POST['end_time'] ?? '') ?>"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Reason *</label>
          <textarea name="reason" rows="4" required
                    placeholder="Describe the work to be done during overtime…"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
        </div>

        <div class="flex gap-3 pt-1">
          <button type="submit"
                  class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            Submit Request
          </button>
          <a href="<?= BASE_URL ?>/modules/overtime/index.php"
             class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
            Cancel
          </a>
        </div>
      </form>
    </div>

  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
