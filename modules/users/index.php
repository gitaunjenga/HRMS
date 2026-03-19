<?php
/**
 * HRMS - User Role Management (Admin only)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireRole('Admin');

$pageTitle   = 'User & Role Management';
$currentUser = currentUser();
$flash       = null;

// Load roles from DB (used throughout the page)
$allRoles   = fetchAll("SELECT * FROM roles ORDER BY is_system DESC, name ASC");
$validRoles = array_column($allRoles, 'name');

// ── POST: update role / toggle status / create/delete role ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $action   = sanitize($_POST['action'] ?? '');

        // ── Create Role ───────────────────────────────────────────────────────
        if ($action === 'create_role') {
            $roleName  = trim(sanitize($_POST['role_name']        ?? ''));
            $roleDesc  = trim(sanitize($_POST['role_description'] ?? ''));
            $roleColor = sanitize($_POST['role_color'] ?? 'gray');
            $allowedColors = ['red','emerald','blue','slate','amber','purple','pink','indigo','teal','orange'];

            if (strlen($roleName) < 2) {
                setFlash('error', 'Role name must be at least 2 characters.');
            } elseif (!in_array($roleColor, $allowedColors, true)) {
                setFlash('error', 'Invalid colour selected.');
            } else {
                $dup = fetchOne("SELECT id FROM roles WHERE name = ?", 's', $roleName);
                if ($dup) {
                    setFlash('error', "A role named '{$roleName}' already exists.");
                } else {
                    execute(
                        "INSERT INTO roles (name, description, color, is_system) VALUES (?, ?, ?, 0)",
                        'sss', $roleName, $roleDesc, $roleColor
                    );
                    // Seed blank permissions row set for this role from existing modules
                    setFlash('success', "Role '{$roleName}' created. Set its permissions in Role Permissions.");
                }
            }
            header('Location: ' . BASE_URL . '/modules/users/index.php?tab=roles');
            exit;
        }

        // ── Delete Role ───────────────────────────────────────────────────────
        if ($action === 'delete_role') {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $role   = fetchOne("SELECT * FROM roles WHERE id = ?", 'i', $roleId);
            if (!$role) {
                setFlash('error', 'Role not found.');
            } elseif ($role['is_system']) {
                setFlash('error', 'System roles cannot be deleted.');
            } else {
                $inUse = fetchOne("SELECT COUNT(*) AS c FROM users WHERE role = ?", 's', $role['name']);
                if ((int)$inUse['c'] > 0) {
                    setFlash('error', "Cannot delete '{$role['name']}' — it is assigned to {$inUse['c']} user(s). Reassign them first.");
                } else {
                    execute("DELETE FROM roles WHERE id = ?", 'i', $roleId);
                    execute("DELETE FROM role_permissions WHERE role = ?", 's', $role['name']);
                    setFlash('success', "Role '{$role['name']}' deleted.");
                }
            }
            header('Location: ' . BASE_URL . '/modules/users/index.php?tab=roles');
            exit;
        }

        if ($action === 'create_user') {
            $newUsername = sanitize($_POST['username']    ?? '');
            $newEmail    = sanitize($_POST['email']       ?? '');
            $newRole     = sanitize($_POST['role']        ?? 'Employee');
            $newPass     = $_POST['password']             ?? '';
            $newEmpId    = (int)($_POST['employee_id']   ?? 0) ?: null;

            $createErrors = [];
            if (strlen($newUsername) < 3)         $createErrors[] = 'Username must be at least 3 characters.';
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $createErrors[] = 'Enter a valid email address.';
            if (strlen($newPass) < 8)             $createErrors[] = 'Password must be at least 8 characters.';
            if (!in_array($newRole, $validRoles)) $createErrors[] = 'Invalid role selected.';

            if (empty($createErrors)) {
                $dupUser  = fetchOne("SELECT id FROM users WHERE username = ?", 's', $newUsername);
                $dupEmail = fetchOne("SELECT id FROM users WHERE email = ?",    's', $newEmail);
                if ($dupUser)  $createErrors[] = 'Username already exists.';
                if ($dupEmail) $createErrors[] = 'Email already in use.';
            }

            if (empty($createErrors)) {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                execute(
                    "INSERT INTO users (username, email, password, role, employee_id, is_active, must_change_password)
                     VALUES (?, ?, ?, ?, ?, 1, 1)",
                    'ssssi', $newUsername, $newEmail, $hashed, $newRole, $newEmpId
                );
                setFlash('success', "User '{$newUsername}' created successfully. They must change their password on first login.");
                header('Location: ' . BASE_URL . '/modules/users/index.php');
                exit;
            } else {
                setFlash('error', implode(' ', $createErrors));
                header('Location: ' . BASE_URL . '/modules/users/index.php?tab=create');
                exit;
            }
        }

        $target = fetchOne("SELECT id, role, is_active, username FROM users WHERE id = ?", 'i', $targetId);

        if (!$target) {
            setFlash('error', 'User not found.');
        } elseif ($action === 'change_role') {
            $newRole = sanitize($_POST['role'] ?? '');
            if (!in_array($newRole, $validRoles)) {
                setFlash('error', 'Invalid role selected.');
            } elseif ((int)$targetId === (int)$currentUser['id'] && $newRole !== 'Admin') {
                setFlash('error', 'You cannot change your own role away from Admin.');
            } else {
                execute("UPDATE users SET role = ? WHERE id = ?", 'si', $newRole, $targetId);
                setFlash('success', sanitize($target['username']) . '\'s role changed to ' . $newRole . '.');
            }
        } elseif ($action === 'toggle_active') {
            if ((int)$targetId === (int)$currentUser['id']) {
                setFlash('error', 'You cannot deactivate your own account.');
            } else {
                $newActive = $target['is_active'] ? 0 : 1;
                execute("UPDATE users SET is_active = ? WHERE id = ?", 'ii', $newActive, $targetId);
                setFlash('success', sanitize($target['username']) . ' has been ' . ($newActive ? 'activated' : 'deactivated') . '.');
            }
        } elseif ($action === 'reset_password') {
            $newPass = sanitize($_POST['new_password'] ?? '');
            if (strlen($newPass) < 8) {
                setFlash('error', 'Password must be at least 8 characters.');
            } else {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                execute("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?", 'si', $hashed, $targetId);
                setFlash('success', 'Password reset for ' . sanitize($target['username']) . '. They must change it on next login.');
            }
        }
    }
    header('Location: ' . BASE_URL . '/modules/users/index.php');
    exit;
}

$flash = getFlash();

// ── Filters ───────────────────────────────────────────────────────────────────
$filterRole   = sanitize($_GET['role']   ?? '');
$filterStatus = sanitize($_GET['status'] ?? '');
$filterSearch = sanitize($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

$where  = ['1=1'];
$types  = '';
$params = [];

if ($filterRole !== '') {
    $where[]  = 'u.role = ?';
    $types   .= 's';
    $params[] = $filterRole;
}
if ($filterStatus === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($filterStatus === 'inactive') {
    $where[] = 'u.is_active = 0';
}
if ($filterSearch !== '') {
    $where[]  = '(u.username LIKE ? OR u.email LIKE ? OR CONCAT(e.first_name," ",e.last_name) LIKE ?)';
    $types   .= 'sss';
    $like     = '%' . $filterSearch . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereStr = implode(' AND ', $where);

$totalRow = fetchOne(
    "SELECT COUNT(*) AS c FROM users u
     LEFT JOIN employees e ON u.employee_id = e.id
     WHERE $whereStr",
    $types, ...$params
);
$total = (int)($totalRow['c'] ?? 0);
$pag   = paginate($total, $page, $perPage);

$users = fetchAll(
    "SELECT u.id, u.username, u.email, u.role, u.is_active, u.last_login, u.must_change_password,
            e.id AS emp_id, e.first_name, e.last_name, e.photo, e.position,
            d.name AS dept_name
     FROM users u
     LEFT JOIN employees e   ON u.employee_id = e.id
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE $whereStr
     ORDER BY u.role, u.username
     LIMIT ? OFFSET ?",
    $types . 'ii', ...[...$params, $perPage, $pag['offset']]
);

// Role stats
$roleStats = fetchAll(
    "SELECT role, COUNT(*) AS cnt, SUM(is_active) AS active_cnt FROM users GROUP BY role ORDER BY FIELD(role,'Admin','HR Manager','Head of Department','Employee')"
);

$baseUrl = BASE_URL . '/modules/users/index.php?' . http_build_query(array_filter([
    'role'   => $filterRole,
    'status' => $filterStatus,
    'search' => $filterSearch,
]));

$activeTab = in_array($_GET['tab'] ?? '', ['create', 'roles']) ? $_GET['tab'] : 'users';

// Employees not yet linked to a user account (for Create User form)
$unlinkedEmployees = fetchAll(
    "SELECT e.id, e.employee_id, e.first_name, e.last_name, e.position
     FROM employees e
     LEFT JOIN users u ON u.employee_id = e.id
     WHERE u.id IS NULL
     ORDER BY e.first_name, e.last_name"
);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Flash -->
    <?php if ($flash): ?>
      <div class="rounded-lg px-4 py-3 text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-900">User &amp; Role Management</h2>
        <p class="text-sm text-gray-500 mt-0.5">Assign roles, manage access levels, and control account status.</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
      <nav class="-mb-px flex gap-6">
        <a href="<?= BASE_URL ?>/modules/users/index.php"
           class="py-3 px-1 text-sm font-medium border-b-2 transition-colors
                  <?= $activeTab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
          All Users
        </a>
        <a href="<?= BASE_URL ?>/modules/users/index.php?tab=roles"
           class="py-3 px-1 text-sm font-medium border-b-2 transition-colors
                  <?= $activeTab === 'roles' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
          Roles
        </a>
        <a href="<?= BASE_URL ?>/modules/users/index.php?tab=create"
           class="py-3 px-1 text-sm font-medium border-b-2 transition-colors
                  <?= $activeTab === 'create' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
          + Create User
        </a>
      </nav>
    </div>

    <?php if ($activeTab === 'roles'): ?>

    <!-- ── Roles Tab ────────────────────────────────────────────────────── -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

      <!-- Create Role Form -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
          <div>
            <h3 class="text-base font-semibold text-gray-900">Create New Role</h3>
            <p class="text-sm text-gray-500 mt-0.5">Add a custom role and set its permissions separately.</p>
          </div>
          <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_role">

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Role Name <span class="text-red-500">*</span></label>
              <input type="text" name="role_name" required minlength="2" maxlength="50"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                     placeholder="e.g. Finance Manager">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
              <input type="text" name="role_description" maxlength="255"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                     placeholder="Brief description of this role">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Badge Colour</label>
              <div class="grid grid-cols-5 gap-2" id="color-picker">
                <?php
                $colorOpts = [
                    'red'    => 'bg-red-500',
                    'emerald'=> 'bg-emerald-500',
                    'blue'   => 'bg-blue-500',
                    'slate'  => 'bg-slate-500',
                    'amber'  => 'bg-amber-500',
                    'purple' => 'bg-purple-500',
                    'pink'   => 'bg-pink-500',
                    'indigo' => 'bg-indigo-500',
                    'teal'   => 'bg-teal-500',
                    'orange' => 'bg-orange-500',
                ];
                foreach ($colorOpts as $val => $cls): ?>
                  <label class="cursor-pointer">
                    <input type="radio" name="role_color" value="<?= $val ?>"
                           class="sr-only peer" <?= $val === 'indigo' ? 'checked' : '' ?>>
                    <span class="block w-8 h-8 rounded-full <?= $cls ?> ring-2 ring-transparent peer-checked:ring-offset-2 peer-checked:ring-gray-900 transition-all mx-auto"></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <button type="submit"
                    class="w-full text-white text-sm font-semibold py-2.5 rounded-lg transition-colors"
                    style="background:#0B2545;">
              Create Role
            </button>
          </form>
        </div>
      </div>

      <!-- Existing Roles List -->
      <div class="lg:col-span-3 space-y-3">
        <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">All Roles</h3>
        <?php
        $colorBadge = [
            'red'    => 'bg-red-100 text-red-800',
            'emerald'=> 'bg-emerald-100 text-emerald-800',
            'blue'   => 'bg-blue-100 text-blue-800',
            'slate'  => 'bg-slate-100 text-slate-700',
            'amber'  => 'bg-amber-100 text-amber-800',
            'purple' => 'bg-purple-100 text-purple-800',
            'pink'   => 'bg-pink-100 text-pink-800',
            'indigo' => 'bg-indigo-100 text-indigo-800',
            'teal'   => 'bg-teal-100 text-teal-800',
            'orange' => 'bg-orange-100 text-orange-800',
        ];
        // User counts per role
        $roleCounts = fetchAll("SELECT role, COUNT(*) AS cnt FROM users GROUP BY role");
        $roleCountMap = array_column($roleCounts, 'cnt', 'role');
        foreach ($allRoles as $role):
            $badge  = $colorBadge[$role['color']] ?? 'bg-gray-100 text-gray-700';
            $usrCnt = (int)($roleCountMap[$role['name']] ?? 0);
        ?>
          <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $badge ?>">
                  <?= htmlspecialchars($role['name']) ?>
                </span>
                <?php if ($role['is_system']): ?>
                  <span class="text-xs text-gray-400 font-medium">System</span>
                <?php endif; ?>
              </div>
              <?php if ($role['description']): ?>
                <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($role['description']) ?></p>
              <?php endif; ?>
              <p class="text-xs text-gray-400 mt-1"><?= $usrCnt ?> user<?= $usrCnt !== 1 ? 's' : '' ?></p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <a href="<?= BASE_URL ?>/modules/settings/permissions.php"
                 title="Edit permissions"
                 class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-600 hover:bg-indigo-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Permissions
              </a>
              <?php if (!$role['is_system']): ?>
                <form method="POST" action="" class="inline"
                      onsubmit="return confirm('Delete role \'<?= addslashes(htmlspecialchars($role['name'])) ?>\'? This cannot be undone.')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="delete_role">
                  <input type="hidden" name="role_id" value="<?= $role['id'] ?>">
                  <button type="submit"
                          class="inline-flex items-center justify-center p-1.5 rounded-lg border border-red-200 text-red-500 hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </form>
              <?php else: ?>
                <span class="inline-flex items-center justify-center p-1.5 rounded-lg border border-gray-100 text-gray-300 cursor-not-allowed" title="System roles cannot be deleted">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-5a2 2 0 100-4 2 2 0 000 4z"/>
                  </svg>
                </span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

    </div>

    <?php elseif ($activeTab === 'create'): ?>

    <!-- Create User Form -->
    <div class="max-w-xl">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
        <div>
          <h3 class="text-base font-semibold text-gray-900">New User Account</h3>
          <p class="text-sm text-gray-500 mt-0.5">The user will be required to change their password on first login.</p>
        </div>

        <form method="POST" action="" class="space-y-4">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="create_user">

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Username <span class="text-red-500">*</span></label>
              <input type="text" name="username" required minlength="3"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                     placeholder="e.g. jdoe">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
              <input type="email" name="email" required
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                     placeholder="user@company.com">
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Role <span class="text-red-500">*</span></label>
            <select name="role" required
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <?php foreach ($validRoles as $r): ?>
                <option value="<?= $r ?>"><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Temporary Password <span class="text-red-500">*</span></label>
            <div class="relative">
              <input type="text" name="password" id="new-pass" required minlength="8"
                     class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                     placeholder="Min. 8 characters">
              <button type="button" onclick="genNewPass()"
                      class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-indigo-600 hover:text-indigo-800 font-medium px-2 py-1 rounded hover:bg-indigo-50">
                Generate
              </button>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Link to Employee <span class="text-gray-400 font-normal">(optional)</span></label>
            <select name="employee_id"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <option value="">— No employee link —</option>
              <?php foreach ($unlinkedEmployees as $emp): ?>
                <option value="<?= $emp['id'] ?>">
                  <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                  (<?= htmlspecialchars($emp['employee_id']) ?>)
                  <?= $emp['position'] ? '— ' . htmlspecialchars($emp['position']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Only employees without an existing account are shown.</p>
          </div>

          <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="flex-1 text-white text-sm font-semibold py-2.5 rounded-lg transition-colors"
                    style="background:#0B2545;">
              Create User
            </button>
            <a href="<?= BASE_URL ?>/modules/users/index.php"
               class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold py-2.5 rounded-lg transition-colors">
              Cancel
            </a>
          </div>
        </form>
      </div>
    </div>

    <?php else: ?>

    <!-- Role Stats Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <?php
      $roleCardCfg = [
          'Admin'              => ['bg-red-50 border-red-200',         'text-red-700',   'text-red-900'],
          'HR Manager'         => ['bg-emerald-50 border-emerald-200', 'text-emerald-700','text-emerald-900'],
          'Head of Department' => ['bg-blue-50 border-blue-200',       'text-blue-700',  'text-blue-900'],
          'Employee'           => ['bg-slate-50 border-slate-200',     'text-slate-600', 'text-slate-900'],
      ];
      $statsMap = [];
      foreach ($roleStats as $rs) $statsMap[$rs['role']] = $rs;
      foreach ($roleCardCfg as $r => [$bg, $sub, $main]):
          $cnt    = (int)($statsMap[$r]['cnt']        ?? 0);
          $active = (int)($statsMap[$r]['active_cnt'] ?? 0);
      ?>
        <a href="?role=<?= urlencode($r) ?>"
           class="rounded-xl border p-4 <?= $bg ?> hover:shadow-md transition-shadow block">
          <p class="text-xs font-semibold <?= $sub ?> uppercase tracking-wide"><?= $r ?></p>
          <p class="text-3xl font-bold <?= $main ?> mt-1"><?= $cnt ?></p>
          <p class="text-xs <?= $sub ?> mt-0.5"><?= $active ?> active</p>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <form method="GET" action="" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">Search</label>
          <input type="text" name="search" value="<?= htmlspecialchars($filterSearch) ?>"
                 placeholder="Name, username or email…"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">Role</label>
          <select name="role" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Roles</option>
            <?php foreach ($validRoles as $r): ?>
              <option value="<?= $r ?>" <?= $filterRole === $r ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1.5">Status</label>
          <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">All Statuses</option>
            <option value="active"   <?= $filterStatus === 'active'   ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div class="flex items-end gap-2">
          <button type="submit" class="flex-1 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
                  style="background:#0B2545;">Filter</button>
          <a href="<?= BASE_URL ?>/modules/users/index.php"
             class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors">Reset</a>
        </div>
      </div>
    </form>

    <!-- Users Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <p class="text-sm font-medium text-gray-700"><?= $total ?> user<?= $total !== 1 ? 's' : '' ?></p>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">User</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Current Role</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Department</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Login</th>
              <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php if (empty($users)): ?>
              <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">No users found.</td></tr>
            <?php else: ?>
              <?php foreach ($users as $u):
                $isSelf    = (int)$u['id'] === (int)$currentUser['id'];
                $roleCfg   = [
                    'Admin'              => 'bg-red-100 text-red-800',
                    'HR Manager'         => 'bg-emerald-100 text-emerald-800',
                    'Head of Department' => 'bg-blue-100 text-blue-800',
                    'Employee'           => 'bg-slate-100 text-slate-700',
                ];
                $badgeCls = $roleCfg[$u['role']] ?? 'bg-gray-100 text-gray-700';
              ?>
                <tr class="hover:bg-gray-50 transition-colors <?= !$u['is_active'] ? 'opacity-60' : '' ?>">

                  <!-- User -->
                  <td class="px-5 py-3">
                    <div class="flex items-center gap-3">
                      <img src="<?= avatarUrl($u['photo'] ?? null) ?>" alt=""
                           class="w-9 h-9 rounded-full object-cover shrink-0 <?= !$u['is_active'] ? 'grayscale' : '' ?>">
                      <div>
                        <p class="text-sm font-semibold text-gray-900">
                          <?= sanitize(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?: sanitize($u['username']) ?>
                          <?php if ($isSelf): ?>
                            <span class="ml-1 text-xs text-indigo-500 font-normal">(you)</span>
                          <?php endif; ?>
                        </p>
                        <p class="text-xs text-gray-400">@<?= sanitize($u['username']) ?></p>
                        <p class="text-xs text-gray-400"><?= sanitize($u['email']) ?></p>
                      </div>
                    </div>
                  </td>

                  <!-- Role -->
                  <td class="px-5 py-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $badgeCls ?>">
                      <?= htmlspecialchars($u['role']) ?>
                    </span>
                    <?php if (!empty($u['must_change_password'])): ?>
                      <p class="text-xs text-amber-600 mt-0.5">Must change password</p>
                    <?php endif; ?>
                  </td>

                  <!-- Department / Position -->
                  <td class="px-5 py-3">
                    <?php if ($u['dept_name']): ?>
                      <p class="text-sm text-gray-700"><?= sanitize($u['dept_name']) ?></p>
                    <?php endif; ?>
                    <?php if ($u['position']): ?>
                      <p class="text-xs text-gray-400"><?= sanitize($u['position']) ?></p>
                    <?php endif; ?>
                    <?php if (!$u['dept_name'] && !$u['position']): ?>
                      <span class="text-gray-300 text-sm">—</span>
                    <?php endif; ?>
                  </td>

                  <!-- Status -->
                  <td class="px-5 py-3 text-center">
                    <?php if ($u['is_active']): ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                      </span>
                    <?php else: ?>
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                      </span>
                    <?php endif; ?>
                  </td>

                  <!-- Last Login -->
                  <td class="px-5 py-3 text-sm text-gray-500">
                    <?= $u['last_login'] ? formatDate($u['last_login']) : '<span class="text-gray-300">Never</span>' ?>
                  </td>

                  <!-- Actions -->
                  <td class="px-5 py-3 text-center">
                    <div class="flex items-center justify-center gap-1" x-data="{ open: false }" @click.away="open = false">

                      <!-- Role change dropdown -->
                      <div class="relative">
                        <button @click="open = !open"
                                class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1.5 rounded-lg transition-colors border"
                                style="background:#0B2545; color:#fff; border-color:#0B2545;">
                          Change Role
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                          </svg>
                        </button>
                        <div x-show="open" x-transition
                             class="absolute right-0 mt-1 w-52 bg-white rounded-xl shadow-xl border border-gray-200 py-1 z-50">
                          <p class="px-3 py-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100">Assign Role</p>
                          <?php foreach ($validRoles as $r):
                            $isCurrentRole = $u['role'] === $r;
                          ?>
                            <form method="POST" action="">
                              <?= csrfField() ?>
                              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                              <input type="hidden" name="action"  value="change_role">
                              <input type="hidden" name="role"    value="<?= $r ?>">
                              <button type="submit"
                                      <?= ($isSelf && $r !== 'Admin') || $isCurrentRole ? 'disabled' : '' ?>
                                      onclick="return confirm('Change role to <?= $r ?>?')"
                                      class="w-full text-left px-3 py-2 text-sm flex items-center gap-2 transition-colors
                                             <?= $isCurrentRole ? 'text-indigo-600 font-semibold bg-indigo-50' : 'text-gray-700 hover:bg-gray-50' ?>
                                             disabled:opacity-40 disabled:cursor-not-allowed">
                                <?php if ($isCurrentRole): ?>
                                  <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                  </svg>
                                <?php else: ?>
                                  <span class="w-3.5 h-3.5"></span>
                                <?php endif; ?>
                                <?= $r ?>
                              </button>
                            </form>
                          <?php endforeach; ?>
                        </div>
                      </div>

                      <!-- Toggle active -->
                      <form method="POST" action="" class="inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <input type="hidden" name="action"  value="toggle_active">
                        <button type="submit"
                                <?= $isSelf ? 'disabled' : '' ?>
                                onclick="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?')"
                                title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>"
                                class="inline-flex items-center justify-center p-1.5 rounded-lg border transition-colors disabled:opacity-40 disabled:cursor-not-allowed
                                       <?= $u['is_active']
                                           ? 'border-red-200 text-red-600 hover:bg-red-50'
                                           : 'border-green-200 text-green-600 hover:bg-green-50' ?>">
                          <?php if ($u['is_active']): ?>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                            </svg>
                          <?php else: ?>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                          <?php endif; ?>
                        </button>
                      </form>

                      <!-- Reset password -->
                      <button onclick="openResetModal(<?= $u['id'] ?>, '<?= addslashes(sanitize($u['username'])) ?>')"
                              title="Reset password"
                              class="inline-flex items-center justify-center p-1.5 rounded-lg border border-amber-200 text-amber-600 hover:bg-amber-50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                      </button>

                      <!-- View employee profile -->
                      <?php if ($u['emp_id']): ?>
                        <a href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= $u['emp_id'] ?>"
                           title="View profile"
                           class="inline-flex items-center justify-center p-1.5 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                          </svg>
                        </a>
                      <?php endif; ?>

                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?= renderPagination($pag, $baseUrl) ?>
    </div>

    <?php endif; // end tab else ?>

  </main>
</div>

<!-- Reset Password Modal -->
<div id="reset-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
    <h3 class="text-lg font-bold text-gray-900 mb-1">Reset Password</h3>
    <p class="text-sm text-gray-500 mb-4">Set a new temporary password for <strong id="reset-username"></strong>. They will be required to change it on next login.</p>
    <form method="POST" action="">
      <?= csrfField() ?>
      <input type="hidden" name="action"  value="reset_password">
      <input type="hidden" name="user_id" id="reset-user-id">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
        <div class="relative">
          <input type="text" name="new_password" id="reset-pass-input"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500"
                 placeholder="Min. 8 characters" required minlength="8">
          <button type="button" onclick="genResetPass()"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-indigo-600 hover:text-indigo-800 font-medium px-2 py-1 rounded hover:bg-indigo-50">
            Generate
          </button>
        </div>
      </div>
      <div class="flex gap-3">
        <button type="submit"
                class="flex-1 text-white text-sm font-semibold py-2.5 rounded-lg transition-colors"
                style="background:#0B2545;">Reset Password</button>
        <button type="button" onclick="closeResetModal()"
                class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold py-2.5 rounded-lg transition-colors">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
function openResetModal(userId, username) {
  document.getElementById('reset-user-id').value  = userId;
  document.getElementById('reset-username').textContent = username;
  document.getElementById('reset-pass-input').value = '';
  document.getElementById('reset-modal').classList.remove('hidden');
  document.getElementById('reset-modal').classList.add('flex');
}
function closeResetModal() {
  document.getElementById('reset-modal').classList.add('hidden');
  document.getElementById('reset-modal').classList.remove('flex');
}
function genResetPass() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
  let p = '';
  for (let i = 0; i < 12; i++) p += chars[Math.floor(Math.random() * chars.length)];
  document.getElementById('reset-pass-input').value = p;
}
function genNewPass() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
  let p = '';
  for (let i = 0; i < 12; i++) p += chars[Math.floor(Math.random() * chars.length)];
  document.getElementById('new-pass').value = p;
}
// Close modal on backdrop click
document.getElementById('reset-modal').addEventListener('click', function(e) {
  if (e.target === this) closeResetModal();
});
</script>
