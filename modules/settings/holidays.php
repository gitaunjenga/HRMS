<?php
/**
 * HRMS - Public / Statutory Holidays (Kenya)
 */
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/audit.php';
requireCan('view', 'holidays');

$pageTitle = 'Public Holidays';
$canManage = can('create', 'holidays');
$error     = '';
$edit      = null;

// Handle create / update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } elseif (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        execute("DELETE FROM public_holidays WHERE id = ?", 'i', $delId);
        logAudit('delete', 'holidays', "Deleted holiday ID: {$delId}");
        setFlash('success', 'Holiday deleted.');
        header('Location: ' . BASE_URL . '/modules/settings/holidays.php');
        exit;
    } else {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = sanitize($_POST['name'] ?? '');
        $date        = sanitize($_POST['holiday_date'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || !$date) {
            $error = 'Name and date are required.';
        } else {
            if ($id > 0) {
                execute(
                    "UPDATE public_holidays SET name=?, holiday_date=?, description=?, is_active=? WHERE id=?",
                    'sssii', $name, $date, $description, $isActive, $id
                );
                logAudit('update', 'holidays', "Updated holiday: {$name} ({$date})");
                setFlash('success', 'Holiday updated.');
            } else {
                execute(
                    "INSERT INTO public_holidays (name, holiday_date, description, is_active) VALUES (?,?,?,?)",
                    'sssi', $name, $date, $description, $isActive
                );
                logAudit('create', 'holidays', "Added holiday: {$name} ({$date})");
                setFlash('success', 'Holiday added.');
            }
            header('Location: ' . BASE_URL . '/modules/settings/holidays.php');
            exit;
        }
    }
}

if (isset($_GET['edit']) && $canManage) {
    $edit = fetchOne("SELECT * FROM public_holidays WHERE id = ?", 'i', (int)$_GET['edit']);
}

$year      = max(date('Y'), (int)($_GET['year'] ?? date('Y')));
$holidays  = fetchAll(
    "SELECT * FROM public_holidays WHERE YEAR(holiday_date) = ? ORDER BY holiday_date",
    'i', $year
);

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

  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Public Holidays — Kenya</h1>
      <p class="text-sm text-gray-500 mt-0.5">Statutory and public holidays configuration</p>
    </div>
    <!-- Year nav -->
    <div class="flex items-center gap-2">
      <a href="?year=<?= $year - 1 ?>" class="p-2 rounded-lg bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </a>
      <span class="text-sm font-semibold text-gray-800 px-2"><?= $year ?></span>
      <a href="?year=<?= $year + 1 ?>" class="p-2 rounded-lg bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <?php if ($canManage): ?>
    <!-- Form -->
    <div class="lg:col-span-1">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sticky top-6">
        <h2 class="font-semibold text-gray-800 mb-4"><?= $edit ? 'Edit Holiday' : 'Add Holiday' ?></h2>
        <?php if ($error): ?>
          <div class="mb-3 px-3 py-2 rounded-lg text-sm bg-red-100 text-red-800 border border-red-200"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
          <?= csrfField() ?>
          <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?= $edit['id'] ?>">
          <?php endif; ?>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Holiday Name *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>"
                   placeholder="e.g. Madaraka Day"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
            <input type="date" name="holiday_date" required value="<?= htmlspecialchars($edit['holiday_date'] ?? '') ?>"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <input type="text" name="description" value="<?= htmlspecialchars($edit['description'] ?? '') ?>"
                   placeholder="Optional note"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
          </div>
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1"
                   <?= ($edit ? !empty($edit['is_active']) : true) ? 'checked' : '' ?>
                   class="rounded border-gray-300 text-indigo-600">
            Active
          </label>
          <div class="flex gap-2">
            <button type="submit"
                    class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
              <?= $edit ? 'Update' : 'Add Holiday' ?>
            </button>
            <?php if ($edit): ?>
              <a href="?" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- List -->
    <div class="<?= $canManage ? 'lg:col-span-2' : 'lg:col-span-3' ?>">
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="font-semibold text-gray-800">Holidays in <?= $year ?></h2>
          <span class="text-xs text-gray-400"><?= count($holidays) ?> holiday(s)</span>
        </div>

        <?php if (empty($holidays)): ?>
          <div class="flex flex-col items-center justify-center py-16 text-gray-400">
            <svg class="w-10 h-10 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <p class="text-sm font-medium">No holidays for <?= $year ?></p>
            <?php if ($canManage): ?>
              <p class="text-xs mt-1">Use the form to add holidays.</p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="divide-y divide-gray-100">
            <?php foreach ($holidays as $h): ?>
              <div class="px-5 py-3 flex items-center gap-4 hover:bg-gray-50 transition-colors">
                <div class="w-14 h-14 rounded-xl flex flex-col items-center justify-center shrink-0
                            <?= $h['is_active'] ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-400' ?>">
                  <span class="text-xs font-medium uppercase"><?= date('M', strtotime($h['holiday_date'])) ?></span>
                  <span class="text-xl font-bold leading-tight"><?= date('d', strtotime($h['holiday_date'])) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <p class="font-medium text-gray-900"><?= sanitize($h['name']) ?></p>
                    <?php if (!$h['is_active']): ?>
                      <span class="px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded text-xs">Inactive</span>
                    <?php endif; ?>
                  </div>
                  <p class="text-xs text-gray-400 mt-0.5">
                    <?= date('l', strtotime($h['holiday_date'])) ?>
                    <?php if ($h['description']): ?>
                      &nbsp;·&nbsp;<?= sanitize($h['description']) ?>
                    <?php endif; ?>
                  </p>
                </div>
                <?php if ($canManage): ?>
                  <div class="flex items-center gap-1 shrink-0">
                    <a href="?edit=<?= $h['id'] ?>&year=<?= $year ?>"
                       class="p-1.5 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors" title="Edit">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </a>
                    <form method="POST" onsubmit="return confirm('Delete this holiday?')">
                      <?= csrfField() ?>
                      <input type="hidden" name="delete_id" value="<?= $h['id'] ?>">
                      <button type="submit" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                      </button>
                    </form>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
