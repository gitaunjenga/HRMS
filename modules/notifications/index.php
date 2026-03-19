<?php
/**
 * HRMS - Notifications
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('view', 'notifications');

$pageTitle = 'Notifications';
$user      = currentUser();
$userId    = (int)$user['id'];

// ── Fetch all notifications for the current user ──────────────────────────────
$notifications = fetchAll(
    "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC",
    'i', $userId
);

// ── Group by date label ───────────────────────────────────────────────────────
$grouped = [];
foreach ($notifications as $n) {
    $ts    = strtotime($n['created_at']);
    $today = date('Y-m-d');
    $yest  = date('Y-m-d', strtotime('-1 day'));
    $nDate = date('Y-m-d', $ts);

    if ($nDate === $today) {
        $label = 'Today';
    } elseif ($nDate === $yest) {
        $label = 'Yesterday';
    } else {
        $label = date('l, F j, Y', $ts);
    }

    $grouped[$label][] = $n;
}

$unreadCount = array_sum(array_map(fn($n) => $n['is_read'] ? 0 : 1, $notifications));

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <!-- Flash message -->
  <?php $flash = getFlash(); if ($flash): ?>
    <div class="mb-4 px-4 py-3 rounded-lg text-sm font-medium
        <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
      <?= sanitize($flash['message']) ?>
    </div>
  <?php endif; ?>

  <!-- Page header -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h2 class="text-2xl font-bold text-gray-900">Notifications</h2>
      <p class="text-sm text-gray-500 mt-1">
        <?= count($notifications) ?> total
        <?php if ($unreadCount > 0): ?>
          &mdash; <span class="text-indigo-600 font-medium"><?= $unreadCount ?> unread</span>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($unreadCount > 0): ?>
      <a href="<?= BASE_URL ?>/modules/notifications/mark_read.php?action=all"
         class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        Mark All as Read
      </a>
    <?php endif; ?>
  </div>

  <?php if (empty($notifications)): ?>
    <!-- Empty state -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-16 text-center">
      <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
      </div>
      <p class="text-lg font-semibold text-gray-700">You're all caught up!</p>
      <p class="text-sm text-gray-400 mt-1">No notifications at the moment.</p>
    </div>

  <?php else: ?>

    <div class="space-y-6">
      <?php foreach ($grouped as $dateLabel => $items): ?>
        <!-- Date group -->
        <div>
          <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-1">
            <?= htmlspecialchars($dateLabel) ?>
          </h3>

          <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden divide-y divide-gray-100">
            <?php foreach ($items as $notif): ?>
              <?php
                // Icon & colour based on type
                $type = $notif['type'] ?? 'general';
                $iconMap = [
                    'leave' => [
                        'color' => 'bg-blue-100 text-blue-600',
                        'svg'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                    ],
                    'payroll' => [
                        'color' => 'bg-green-100 text-green-600',
                        'svg'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                    ],
                    'announcement' => [
                        'color' => 'bg-yellow-100 text-yellow-600',
                        'svg'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>',
                    ],
                    'general' => [
                        'color' => 'bg-indigo-100 text-indigo-600',
                        'svg'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>',
                    ],
                ];
                $icon      = $iconMap[$type] ?? $iconMap['general'];
                $isUnread  = !(bool)$notif['is_read'];
                $markUrl   = BASE_URL . '/modules/notifications/mark_read.php?id=' . (int)$notif['id'];
                $targetUrl = $notif['link'] ? $notif['link'] : $markUrl;
                $rowBg     = $isUnread ? 'bg-blue-50 hover:bg-blue-100' : 'hover:bg-gray-50';
              ?>
              <div class="flex items-start gap-4 px-5 py-4 <?= $rowBg ?> transition-colors group">

                <!-- Icon -->
                <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center <?= $icon['color'] ?>">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <?= $icon['svg'] ?>
                  </svg>
                </div>

                <!-- Content (click to mark as read + navigate) -->
                <a href="<?= htmlspecialchars($notif['link'] ? $notif['link'] : $markUrl) ?>"
                   onclick="<?= $isUnread ? "fetch('" . $markUrl . "'); " : '' ?>"
                   class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <div>
                      <p class="text-sm font-semibold text-gray-900 leading-tight">
                        <?= sanitize($notif['title']) ?>
                        <?php if ($isUnread): ?>
                          <span class="inline-block w-2 h-2 bg-indigo-500 rounded-full ml-1 align-middle"></span>
                        <?php endif; ?>
                      </p>
                      <p class="text-sm text-gray-600 mt-0.5 leading-snug">
                        <?= sanitize($notif['message']) ?>
                      </p>
                    </div>
                    <span class="shrink-0 text-xs text-gray-400 whitespace-nowrap mt-0.5">
                      <?= timeAgo($notif['created_at']) ?>
                    </span>
                  </div>
                  <p class="text-xs text-gray-400 mt-1"><?= date('M d, Y \a\t g:i A', strtotime($notif['created_at'])) ?></p>
                </a>

                <!-- Actions: mark read + delete -->
                <div class="shrink-0 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  <?php if ($isUnread): ?>
                    <a href="<?= $markUrl ?>" title="Mark as read"
                       class="p-1.5 text-indigo-500 hover:bg-indigo-100 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                      </svg>
                    </a>
                  <?php endif; ?>

                  <!-- Delete -->
                  <form method="POST" action="<?= BASE_URL ?>/modules/notifications/mark_read.php"
                        onsubmit="return confirm('Delete this notification?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$notif['id'] ?>">
                    <button type="submit" title="Delete"
                            class="p-1.5 text-red-400 hover:bg-red-50 rounded-lg transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
