<?php
/**
 * HRMS - Audit Log Viewer (Admin only)
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireCan('view', 'audit');

$pageTitle = 'Audit Log';

// Filters
$filterUser   = (int)($_GET['user_id'] ?? 0);
$filterModule = sanitize($_GET['module'] ?? '');
$filterAction = sanitize($_GET['action'] ?? '');
$filterFrom   = sanitize($_GET['from'] ?? '');
$filterTo     = sanitize($_GET['to'] ?? '');
$search       = sanitize($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 30;

// Build query
$where  = ['1=1'];
$types  = '';
$params = [];

if ($filterUser > 0) {
    $where[]  = 'al.user_id = ?';
    $types   .= 'i';
    $params[] = $filterUser;
}
if ($filterModule !== '') {
    $where[]  = 'al.module = ?';
    $types   .= 's';
    $params[] = $filterModule;
}
if ($filterAction !== '') {
    $where[]  = 'al.action = ?';
    $types   .= 's';
    $params[] = $filterAction;
}
if ($filterFrom !== '') {
    $where[]  = 'al.created_at >= ?';
    $types   .= 's';
    $params[] = $filterFrom . ' 00:00:00';
}
if ($filterTo !== '') {
    $where[]  = 'al.created_at <= ?';
    $types   .= 's';
    $params[] = $filterTo . ' 23:59:59';
}
if ($search !== '') {
    $where[]  = '(al.description LIKE ? OR u.username LIKE ?)';
    $types   .= 'ss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereSQL = implode(' AND ', $where);

$total = (int)(fetchOne(
    "SELECT COUNT(*) AS c FROM audit_log al LEFT JOIN users u ON al.user_id = u.id WHERE {$whereSQL}",
    $types, ...$params
)['c'] ?? 0);

$pag = paginate($total, $page, $perPage);

$logs = fetchAll(
    "SELECT al.*, u.username FROM audit_log al
     LEFT JOIN users u ON al.user_id = u.id
     WHERE {$whereSQL}
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?",
    $types . 'ii', ...[...$params, $pag['perPage'], $pag['offset']]
);

// For filters
$modules = fetchAll("SELECT DISTINCT module FROM audit_log ORDER BY module");
$actions = fetchAll("SELECT DISTINCT action FROM audit_log ORDER BY action");
$allUsers = fetchAll("SELECT id, username FROM users WHERE is_active = 1 ORDER BY username");

$baseUrl = BASE_URL . '/modules/audit/index.php?' . http_build_query(array_filter([
    'user_id' => $filterUser ?: '',
    'module'  => $filterModule,
    'action'  => $filterAction,
    'from'    => $filterFrom,
    'to'      => $filterTo,
    'q'       => $search,
]));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';

$moduleColors = [
    'auth'       => 'bg-purple-100 text-purple-700',
    'employees'  => 'bg-blue-100 text-blue-700',
    'leaves'     => 'bg-yellow-100 text-yellow-700',
    'attendance' => 'bg-green-100 text-green-700',
    'payroll'    => 'bg-indigo-100 text-indigo-700',
    'tickets'    => 'bg-orange-100 text-orange-700',
    'overtime'   => 'bg-red-100 text-red-700',
    'shifts'     => 'bg-teal-100 text-teal-700',
    'settings'   => 'bg-gray-100 text-gray-700',
    'documents'  => 'bg-pink-100 text-pink-700',
];
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <?php $flash = getFlash(); if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Header -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Audit Log</h1>
      <p class="text-sm text-gray-500 mt-0.5">All system activity — <?= $total ?> record(s)</p>
    </div>
  </div>

  <!-- Filters -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">User</label>
        <select name="user_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All Users</option>
          <?php foreach ($allUsers as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $filterUser === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Module</label>
        <select name="module" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All Modules</option>
          <?php foreach ($modules as $m): ?>
            <option value="<?= $m['module'] ?>" <?= $filterModule === $m['module'] ? 'selected' : '' ?>><?= htmlspecialchars($m['module']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Action</label>
        <select name="action" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <option value="">All Actions</option>
          <?php foreach ($actions as $a): ?>
            <option value="<?= $a['action'] ?>" <?= $filterAction === $a['action'] ? 'selected' : '' ?>><?= htmlspecialchars($a['action']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">From</label>
        <input type="date" name="from" value="<?= htmlspecialchars($filterFrom) ?>"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">To</label>
        <input type="date" name="to" value="<?= htmlspecialchars($filterTo) ?>"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Description or user…"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-44">
      </div>
      <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Filter</button>
      <a href="<?= BASE_URL ?>/modules/audit/index.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Reset</a>
    </form>
  </div>

  <!-- Table -->
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Time</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">User</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Role</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Module</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Description</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="7" class="px-5 py-16 text-center text-gray-400">
                <p class="font-medium">No audit log entries found</p>
                <p class="text-xs mt-1">System activity will appear here as users perform actions</p>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <?php $modColor = $moduleColors[$log['module']] ?? 'bg-gray-100 text-gray-700'; ?>
              <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 whitespace-nowrap text-gray-500 text-xs">
                  <?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?>
                </td>
                <td class="px-4 py-3 font-medium text-gray-900">
                  <?= $log['username'] ? htmlspecialchars($log['username']) : '<span class="text-gray-400">System</span>' ?>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs"><?= htmlspecialchars($log['role'] ?? '—') ?></td>
                <td class="px-4 py-3">
                  <span class="px-2 py-0.5 rounded text-xs font-medium <?= $modColor ?>"><?= htmlspecialchars($log['module']) ?></span>
                </td>
                <td class="px-4 py-3">
                  <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars($log['action']) ?></code>
                </td>
                <td class="px-4 py-3 text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($log['description'] ?? '') ?>">
                  <?= htmlspecialchars($log['description'] ?? '—') ?>
                </td>
                <td class="px-4 py-3 text-gray-400 font-mono text-xs"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?= renderPagination($pag, $baseUrl) ?>
  </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
