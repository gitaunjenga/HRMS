<?php
/**
 * HRMS - HR Help Desk Tickets
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'HR Help Desk';
$user      = currentUser();
$isHRAdmin = hasRole('Admin', 'HR Manager');
$isDM      = hasRole('Head of Department');

// Filters
$filterStatus = sanitize($_GET['status'] ?? '');
$filterCat    = sanitize($_GET['category'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

$where  = ['1=1'];
$types  = '';
$params = [];

if (!$isHRAdmin) {
    // Non-HR: see only own tickets (or dept tickets for HoD)
    $empId = (int)($user['employee_id'] ?? 0);
    $where[]  = 't.employee_id = ?';
    $types   .= 'i';
    $params[] = $empId;
}

if ($filterStatus !== '') {
    $where[]  = 't.status = ?';
    $types   .= 's';
    $params[] = $filterStatus;
}
if ($filterCat !== '') {
    $where[]  = 't.category = ?';
    $types   .= 's';
    $params[] = $filterCat;
}

$whereSQL = implode(' AND ', $where);

$total = (int)(fetchOne(
    "SELECT COUNT(*) AS c FROM hr_tickets t WHERE {$whereSQL}",
    $types, ...$params
)['c'] ?? 0);

$pag     = paginate($total, $page, $perPage);
$tickets = fetchAll(
    "SELECT t.*,
            CONCAT(e.first_name,' ',e.last_name) AS emp_name,
            e.photo AS emp_photo,
            e.department_id,
            d.name AS dept_name
     FROM hr_tickets t
     JOIN employees e ON t.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE {$whereSQL}
     ORDER BY FIELD(t.status,'Open','In Progress','Resolved','Closed'),
              FIELD(t.priority,'Urgent','High','Medium','Low'),
              t.created_at DESC
     LIMIT ? OFFSET ?",
    $types . 'ii', ...[...$params, $pag['perPage'], $pag['offset']]
);

$baseUrl = BASE_URL . '/modules/tickets/index.php?' . http_build_query(array_filter([
    'status' => $filterStatus, 'category' => $filterCat,
]));

$categories = ['General','Leave','Payroll','Attendance','Benefits','Training','IT Support','Other'];

$priorityColors = [
    'Low'    => 'bg-gray-100 text-gray-600',
    'Medium' => 'bg-blue-100 text-blue-700',
    'High'   => 'bg-orange-100 text-orange-700',
    'Urgent' => 'bg-red-100 text-red-700',
];
$statusColors = [
    'Open'        => 'bg-blue-100 text-blue-700',
    'In Progress' => 'bg-yellow-100 text-yellow-700',
    'Resolved'    => 'bg-green-100 text-green-700',
    'Closed'      => 'bg-gray-100 text-gray-600',
];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <?php $flash = getFlash(); if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">HR Help Desk</h1>
      <p class="text-sm text-gray-500 mt-0.5"><?= $isHRAdmin ? 'All tickets' : 'My tickets' ?> — <?= $total ?> found</p>
    </div>
    <?php if (can('create', 'tickets')): ?>
      <a href="<?= BASE_URL ?>/modules/tickets/create.php"
         class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        New Ticket
      </a>
    <?php endif; ?>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All</option>
          <?php foreach (['Open','In Progress','Resolved','Closed'] as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
        <select name="category" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat ?>" <?= $filterCat === $cat ? 'selected' : '' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="self-end px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg">Filter</button>
      <a href="<?= BASE_URL ?>/modules/tickets/index.php" class="self-end px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg">Reset</a>
    </form>
  </div>

  <!-- Ticket list -->
  <div class="space-y-3">
    <?php if (empty($tickets)): ?>
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm flex flex-col items-center justify-center py-16 text-gray-400">
        <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
        </svg>
        <p class="font-medium text-sm">No tickets found</p>
        <?php if (can('create', 'tickets')): ?>
          <a href="<?= BASE_URL ?>/modules/tickets/create.php" class="mt-3 text-xs text-indigo-600 hover:underline">Raise a ticket →</a>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <?php foreach ($tickets as $t): ?>
        <a href="<?= BASE_URL ?>/modules/tickets/view.php?id=<?= $t['id'] ?>"
           class="block bg-white rounded-xl border border-gray-200 shadow-sm p-5 hover:border-indigo-300 transition-colors">
          <div class="flex items-start gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-xs font-mono text-gray-400"><?= htmlspecialchars($t['ticket_number']) ?></span>
                <span class="px-2 py-0.5 rounded text-xs font-medium <?= $statusColors[$t['status']] ?? 'bg-gray-100 text-gray-600' ?>"><?= htmlspecialchars($t['status']) ?></span>
                <span class="px-2 py-0.5 rounded text-xs font-medium <?= $priorityColors[$t['priority']] ?? '' ?>"><?= htmlspecialchars($t['priority']) ?></span>
                <span class="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs"><?= htmlspecialchars($t['category']) ?></span>
              </div>
              <p class="font-semibold text-gray-900"><?= sanitize($t['subject']) ?></p>
              <?php if ($isHRAdmin): ?>
                <p class="text-xs text-gray-500 mt-0.5">
                  By <?= sanitize($t['emp_name']) ?> · <?= sanitize($t['dept_name'] ?? '—') ?>
                </p>
              <?php endif; ?>
            </div>
            <div class="shrink-0 text-right">
              <p class="text-xs text-gray-400"><?= timeAgo($t['created_at']) ?></p>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
      <?= renderPagination($pag, $baseUrl) ?>
    <?php endif; ?>
  </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
