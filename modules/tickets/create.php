<?php
/**
 * HRMS - Create HR Ticket
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireCan('create', 'tickets');

$pageTitle = 'New Ticket';
$user      = currentUser();
$empId     = (int)($user['employee_id'] ?? 0);
$error     = '';

if (!$empId) {
    setFlash('error', 'No employee record linked to your account.');
    header('Location: ' . BASE_URL . '/modules/tickets/index.php');
    exit;
}

$categories = ['General','Leave','Payroll','Attendance','Benefits','Training','IT Support','Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $subject     = sanitize($_POST['subject'] ?? '');
        $category    = sanitize($_POST['category'] ?? 'General');
        $priority    = sanitize($_POST['priority'] ?? 'Medium');
        $description = sanitize($_POST['description'] ?? '');

        if (!$subject || !$description) {
            $error = 'Subject and description are required.';
        } elseif (!in_array($category, $categories, true)) {
            $error = 'Invalid category.';
        } elseif (!in_array($priority, ['Low','Medium','High','Urgent'], true)) {
            $error = 'Invalid priority.';
        } else {
            // Generate ticket number
            $ticketNo = 'TKT-' . strtoupper(substr(uniqid(), -6));

            execute(
                "INSERT INTO hr_tickets (ticket_number, employee_id, subject, category, priority, description) VALUES (?,?,?,?,?,?)",
                'sissss', $ticketNo, $empId, $subject, $category, $priority, $description
            );
            logAudit('create', 'tickets', "Raised ticket: {$ticketNo} — {$subject}");

            // Notify HR
            $hrUsers = fetchAll("SELECT id FROM users WHERE role IN ('Admin','HR Manager') AND is_active = 1");
            foreach ($hrUsers as $hr) {
                createNotification(
                    $hr['id'],
                    'New HR Ticket',
                    "{$user['username']} raised a ticket: {$subject}",
                    'ticket',
                    BASE_URL . '/modules/tickets/index.php'
                );
            }

            setFlash('success', "Ticket {$ticketNo} submitted successfully.");
            header('Location: ' . BASE_URL . '/modules/tickets/index.php');
            exit;
        }
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">
  <div class="max-w-2xl mx-auto">

    <div class="flex items-center gap-4 mb-6">
      <a href="<?= BASE_URL ?>/modules/tickets/index.php"
         class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-900">New HR Ticket</h1>
        <p class="text-sm text-gray-500 mt-0.5">Raise a query or request with HR</p>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-100 text-red-800 border border-red-200"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <form method="POST" class="space-y-5">
        <?= csrfField() ?>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Subject *</label>
          <input type="text" name="subject" required value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                 placeholder="Brief description of your issue"
                 class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Category *</label>
            <select name="category" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat ?>" <?= ($_POST['category'] ?? 'General') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Priority *</label>
            <select name="priority" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
                <option value="<?= $p ?>" <?= ($_POST['priority'] ?? 'Medium') === $p ? 'selected' : '' ?>><?= $p ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Description *</label>
          <textarea name="description" rows="6" required
                    placeholder="Describe your issue in detail. Include any relevant dates, amounts, or reference numbers."
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="flex gap-3 pt-1">
          <button type="submit"
                  class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            Submit Ticket
          </button>
          <a href="<?= BASE_URL ?>/modules/tickets/index.php"
             class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">
            Cancel
          </a>
        </div>
      </form>
    </div>

  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
