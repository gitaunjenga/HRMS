<?php
/**
 * HRMS - Role Permission Settings (DB-driven)
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireCan('manage', 'settings');

$pageTitle = 'Role Permissions';

// All roles & modules — loaded from DB so custom roles appear automatically
$rolesDb = fetchAll("SELECT name FROM roles ORDER BY is_system DESC, name ASC");
$roles   = array_column($rolesDb, 'name');
$modules = [
    'dashboard'   => ['view'],
    'employees'   => ['view', 'create', 'edit', 'delete', 'view_own'],
    'departments' => ['view', 'create', 'edit', 'delete'],
    'attendance'  => ['view', 'edit', 'view_own'],
    'leaves'      => ['view', 'create', 'approve', 'reject', 'view_own'],
    'payroll'     => ['view', 'create', 'edit', 'process', 'view_own'],
    'recruitment' => ['view', 'create', 'edit'],
    'performance' => ['view', 'create', 'edit', 'view_own'],
    'documents'   => ['view', 'upload', 'manage', 'view_own'],
    'reports'     => ['view', 'export'],
    'notifications'=> ['view'],
    'users'       => ['view', 'create', 'edit', 'delete'],
    'settings'    => ['view', 'manage'],
    'audit'       => ['view'],
    'tickets'     => ['view', 'create', 'manage'],
    'shifts'      => ['view', 'create', 'edit', 'delete', 'view_own'],
    'overtime'    => ['view', 'create', 'approve', 'reject', 'view_own'],
    'holidays'    => ['view', 'create', 'edit', 'delete'],
];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
    } else {
        $role   = sanitize($_POST['role'] ?? '');
        $module = sanitize($_POST['module'] ?? '');
        $action = sanitize($_POST['action_name'] ?? '');
        $grant  = isset($_POST['grant']) ? 1 : 0;

        if (in_array($role, $roles, true) && isset($modules[$module]) && in_array($action, $modules[$module], true)) {
            // Upsert
            $existing = fetchOne(
                "SELECT id FROM role_permissions WHERE role=? AND module=? AND action=?",
                'sss', $role, $module, $action
            );
            if ($existing) {
                execute("UPDATE role_permissions SET is_granted=? WHERE id=?", 'ii', $grant, $existing['id']);
            } else {
                execute("INSERT INTO role_permissions (role, module, action, is_granted) VALUES (?,?,?,?)",
                    'sssi', $role, $module, $action, $grant);
            }
            logAudit('update', 'settings', "Permission {$role}/{$module}/{$action} set to " . ($grant ? 'granted' : 'denied'));
            // Clear static can() cache on next request (it's per-request anyway)
            setFlash('success', 'Permission updated.');
        } else {
            setFlash('error', 'Invalid permission data.');
        }
    }
    header('Location: ' . BASE_URL . '/modules/settings/permissions.php');
    exit;
}

// Load current permissions into a lookup map
$currentPerms = fetchAll("SELECT role, module, action, is_granted FROM role_permissions");
$permMap = [];
foreach ($currentPerms as $p) {
    $permMap[$p['role']][$p['module']][$p['action']] = (int)$p['is_granted'];
}

function isGranted(array $map, string $role, string $module, string $action): bool {
    return ($map[$role][$module][$action] ?? 0) === 1;
}

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

  <div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Role Permissions</h1>
    <p class="text-sm text-gray-500 mt-0.5">Click a toggle to grant or deny a permission. Changes take effect on next login.</p>
  </div>

  <?php foreach ($roles as $role): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6 overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 bg-gray-50">
        <h2 class="font-semibold text-gray-800"><?= htmlspecialchars($role) ?></h2>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100">
              <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide w-40">Module</th>
              <?php
                $allActions = [];
                foreach ($modules as $m => $acts) {
                    foreach ($acts as $a) {
                        if (!in_array($a, $allActions)) $allActions[] = $a;
                    }
                }
              ?>
              <?php foreach ($allActions as $act): ?>
                <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= $act ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($modules as $module => $actions): ?>
              <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium text-gray-700 capitalize"><?= $module ?></td>
                <?php foreach ($allActions as $act): ?>
                  <td class="px-3 py-3 text-center">
                    <?php if (in_array($act, $actions, true)): ?>
                      <?php $granted = isGranted($permMap, $role, $module, $act); ?>
                      <form method="POST" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                        <input type="hidden" name="module" value="<?= htmlspecialchars($module) ?>">
                        <input type="hidden" name="action_name" value="<?= htmlspecialchars($act) ?>">
                        <?php if ($granted): ?>
                          <button type="submit" title="Click to revoke" class="w-6 h-6 rounded-full bg-green-500 hover:bg-red-400 transition-colors mx-auto block" title="Granted — click to revoke"></button>
                        <?php else: ?>
                          <input type="hidden" name="grant" value="1">
                          <button type="submit" title="Click to grant" class="w-6 h-6 rounded-full border-2 border-gray-300 hover:border-green-400 hover:bg-green-50 transition-colors mx-auto block"></button>
                        <?php endif; ?>
                      </form>
                    <?php else: ?>
                      <span class="block w-6 h-6 mx-auto"></span>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
