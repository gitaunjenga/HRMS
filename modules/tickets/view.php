<?php
/**
 * HRMS - View & Reply to Ticket
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireLogin();

$pageTitle = 'Ticket';
$user      = currentUser();
$isHRAdmin = hasRole('Admin', 'HR Manager');
$empId     = (int)($user['employee_id'] ?? 0);
$id        = (int)($_GET['id'] ?? 0);

$ticket = fetchOne(
    "SELECT t.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name, e.photo AS emp_photo, e.employee_id AS emp_code,
            d.name AS dept_name
     FROM hr_tickets t
     JOIN employees e ON t.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE t.id = ?",
    'i', $id
);

if (!$ticket || (!$isHRAdmin && $ticket['employee_id'] != $empId)) {
    setFlash('error', 'Ticket not found.');
    header('Location: ' . BASE_URL . '/modules/tickets/index.php');
    exit;
}

$error = '';

// Handle reply / status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        // Status change (HR only)
        if ($isHRAdmin && isset($_POST['new_status'])) {
            $newStatus = sanitize($_POST['new_status']);
            if (in_array($newStatus, ['Open','In Progress','Resolved','Closed'], true)) {
                $resolvedAt = in_array($newStatus, ['Resolved','Closed']) ? 'NOW()' : 'NULL';
                execute(
                    "UPDATE hr_tickets SET status=?, resolved_at={$resolvedAt}, updated_at=NOW() WHERE id=?",
                    'si', $newStatus, $id
                );
                logAudit('update', 'tickets', "Changed ticket {$ticket['ticket_number']} status to {$newStatus}");
                // Notify employee
                $empUser = fetchOne("SELECT u.id FROM users u WHERE u.employee_id = ?", 'i', $ticket['employee_id']);
                if ($empUser) {
                    createNotification($empUser['id'], 'Ticket Status Updated',
                        "Your ticket {$ticket['ticket_number']} status changed to {$newStatus}.",
                        'ticket', BASE_URL . '/modules/tickets/view.php?id=' . $id);
                }
            }
        }

        // Add reply
        $message    = sanitize($_POST['message'] ?? '');
        $isInternal = $isHRAdmin && isset($_POST['is_internal']) ? 1 : 0;
        if ($message !== '') {
            execute(
                "INSERT INTO hr_ticket_replies (ticket_id, user_id, message, is_internal) VALUES (?,?,?,?)",
                'iisi', $id, (int)$user['id'], $message, $isInternal
            );
            execute("UPDATE hr_tickets SET updated_at=NOW() WHERE id=?", 'i', $id);
            logAudit('reply', 'tickets', "Replied to ticket {$ticket['ticket_number']}");

            // Notify other party
            if ($isHRAdmin) {
                $empUser = fetchOne("SELECT u.id FROM users u WHERE u.employee_id = ?", 'i', $ticket['employee_id']);
                if ($empUser && !$isInternal) {
                    createNotification($empUser['id'], 'Ticket Reply',
                        "HR replied to your ticket {$ticket['ticket_number']}.",
                        'ticket', BASE_URL . '/modules/tickets/view.php?id=' . $id);
                }
            } else {
                $hrUsers = fetchAll("SELECT id FROM users WHERE role IN ('Admin','HR Manager') AND is_active = 1");
                foreach ($hrUsers as $hr) {
                    createNotification($hr['id'], 'Ticket Reply',
                        "{$user['username']} replied to ticket {$ticket['ticket_number']}.",
                        'ticket', BASE_URL . '/modules/tickets/view.php?id=' . $id);
                }
            }
        }

        header('Location: ' . BASE_URL . '/modules/tickets/view.php?id=' . $id . '#replies');
        exit;
    }
}

// Refresh ticket
$ticket  = fetchOne("SELECT t.*, CONCAT(e.first_name,' ',e.last_name) AS emp_name FROM hr_tickets t JOIN employees e ON t.employee_id = e.id WHERE t.id = ?", 'i', $id);
$replies = fetchAll(
    "SELECT r.*, u.username, u.role,
            CONCAT(e.first_name,' ',e.last_name) AS full_name, e.photo
     FROM hr_ticket_replies r
     JOIN users u ON r.user_id = u.id
     LEFT JOIN employees e ON u.employee_id = e.id
     WHERE r.ticket_id = ?
     ORDER BY r.created_at ASC",
    'i', $id
);

$priorityColors = ['Low'=>'bg-gray-100 text-gray-600','Medium'=>'bg-blue-100 text-blue-700','High'=>'bg-orange-100 text-orange-700','Urgent'=>'bg-red-100 text-red-700'];
$statusColors   = ['Open'=>'bg-blue-100 text-blue-700','In Progress'=>'bg-yellow-100 text-yellow-700','Resolved'=>'bg-green-100 text-green-700','Closed'=>'bg-gray-100 text-gray-600'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">
  <div class="max-w-3xl mx-auto">

    <div class="flex items-center gap-4 mb-6">
      <a href="<?= BASE_URL ?>/modules/tickets/index.php"
         class="p-2 rounded-lg text-gray-500 hover:bg-gray-100 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
      </a>
      <div>
        <h1 class="text-2xl font-bold text-gray-900"><?= sanitize($ticket['subject']) ?></h1>
        <div class="flex items-center gap-2 mt-1">
          <span class="text-xs font-mono text-gray-400"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
          <span class="px-2 py-0.5 rounded text-xs font-medium <?= $statusColors[$ticket['status']] ?? '' ?>"><?= $ticket['status'] ?></span>
          <span class="px-2 py-0.5 rounded text-xs font-medium <?= $priorityColors[$ticket['priority']] ?? '' ?>"><?= $ticket['priority'] ?></span>
        </div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-100 text-red-800 border border-red-200"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Ticket details -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
      <div class="grid grid-cols-2 gap-4 text-sm mb-4">
        <div><span class="text-gray-500">Raised by:</span> <strong><?= sanitize($ticket['emp_name']) ?></strong></div>
        <div><span class="text-gray-500">Category:</span> <strong><?= htmlspecialchars($ticket['category']) ?></strong></div>
        <div><span class="text-gray-500">Created:</span> <strong><?= date('M d, Y H:i', strtotime($ticket['created_at'])) ?></strong></div>
        <?php if ($ticket['resolved_at']): ?>
          <div><span class="text-gray-500">Resolved:</span> <strong><?= date('M d, Y H:i', strtotime($ticket['resolved_at'])) ?></strong></div>
        <?php endif; ?>
      </div>
      <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 leading-relaxed">
        <?= nl2br(htmlspecialchars($ticket['description'])) ?>
      </div>
    </div>

    <!-- HR status change -->
    <?php if ($isHRAdmin && !in_array($ticket['status'], ['Closed'], true)): ?>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-5">
        <form method="POST" class="flex items-center gap-3">
          <?= csrfField() ?>
          <label class="text-sm font-medium text-gray-700">Change status:</label>
          <select name="new_status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
              <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Update</button>
        </form>
      </div>
    <?php endif; ?>

    <!-- Replies -->
    <div id="replies" class="space-y-3 mb-5">
      <h2 class="font-semibold text-gray-800">Replies (<?= count($replies) ?>)</h2>
      <?php foreach ($replies as $reply): ?>
        <?php $isOwn = (int)$reply['user_id'] === (int)$user['id']; ?>
        <?php if ($reply['is_internal'] && !$isHRAdmin) continue; ?>
        <div class="bg-white rounded-xl border <?= $reply['is_internal'] ? 'border-amber-200 bg-amber-50' : 'border-gray-200' ?> shadow-sm p-4">
          <div class="flex items-center gap-2 mb-2">
            <img src="<?= avatarUrl($reply['photo']) ?>" alt="" class="w-7 h-7 rounded-full object-cover">
            <span class="text-sm font-medium text-gray-900">
              <?= $reply['full_name'] ? sanitize($reply['full_name']) : htmlspecialchars($reply['username']) ?>
            </span>
            <span class="text-xs px-1.5 py-0.5 bg-gray-100 text-gray-600 rounded"><?= htmlspecialchars($reply['role']) ?></span>
            <?php if ($reply['is_internal']): ?>
              <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">Internal note</span>
            <?php endif; ?>
            <span class="ml-auto text-xs text-gray-400"><?= timeAgo($reply['created_at']) ?></span>
          </div>
          <p class="text-sm text-gray-700 leading-relaxed"><?= nl2br(htmlspecialchars($reply['message'])) ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Reply form -->
    <?php if (!in_array($ticket['status'], ['Closed'], true)): ?>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="font-semibold text-gray-800 mb-4">Add Reply</h2>
        <form method="POST">
          <?= csrfField() ?>
          <textarea name="message" rows="4" required
                    placeholder="Type your reply…"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none mb-3"></textarea>
          <div class="flex items-center gap-3">
            <?php if ($isHRAdmin): ?>
              <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="is_internal" value="1" class="rounded border-gray-300 text-amber-500">
                Internal note (not visible to employee)
              </label>
            <?php endif; ?>
            <button type="submit"
                    class="ml-auto px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
              Send Reply
            </button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="text-center text-sm text-gray-400 py-4">This ticket is closed. <a href="<?= BASE_URL ?>/modules/tickets/create.php" class="text-indigo-600 hover:underline">Open a new ticket</a> if needed.</div>
    <?php endif; ?>

  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
