<?php
$notifCount = unreadNotificationCount();
$currentUser = currentUser();
?>
<!-- Top Navbar -->
<header class="ml-64 sticky top-0 z-20 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-3 flex items-center justify-between shadow-sm">
  <div class="flex items-center gap-4">
    <!-- Mobile menu toggle (shown on small screens) -->
    <button class="lg:hidden text-gray-500 hover:text-gray-700" onclick="document.querySelector('aside').classList.toggle('-translate-x-full')">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
    <h1 class="text-lg font-semibold text-gray-800 dark:text-gray-100"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
  </div>

  <div class="flex items-center gap-4">
    <!-- Date -->
    <span class="hidden md:block text-sm text-gray-500 dark:text-gray-400"><?= date('l, F j, Y') ?></span>

    <!-- Dark Mode Toggle -->
    <button onclick="toggleDarkMode()" id="dm-toggle"
            class="text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
            title="Toggle dark mode">
      <!-- Moon (shown in light mode) -->
      <svg id="dm-moon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
      </svg>
      <!-- Sun (shown in dark mode) -->
      <svg id="dm-sun" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/>
      </svg>
    </button>

    <!-- Notifications -->
    <a href="<?= BASE_URL ?>/modules/notifications/index.php" class="relative text-gray-500 hover:text-indigo-600 transition-colors">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
      </svg>
      <?php if ($notifCount > 0): ?>
        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center font-bold"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
      <?php endif; ?>
    </a>

    <!-- User Menu (Alpine.js) -->
    <script>
      function toggleDarkMode() {
        const html = document.documentElement;
        const isDark = html.classList.toggle('dark');
        localStorage.setItem('hrms_theme', isDark ? 'dark' : 'light');
        document.getElementById('dm-sun').classList.toggle('hidden', !isDark);
        document.getElementById('dm-moon').classList.toggle('hidden', isDark);
      }
      // Sync icon on load
      (function () {
        const isDark = document.documentElement.classList.contains('dark');
        const sun = document.getElementById('dm-sun');
        const moon = document.getElementById('dm-moon');
        if (sun && moon) {
          sun.classList.toggle('hidden', !isDark);
          moon.classList.toggle('hidden', isDark);
        }
      })();
    </script>
    <div x-data="{ open: false }" class="relative">
      <button @click="open = !open" class="flex items-center gap-2 text-sm text-gray-700 hover:text-indigo-600 focus:outline-none">
        <img src="<?= avatarUrl($currentUser['photo'] ?? null) ?>" alt="Avatar"
             class="w-8 h-8 rounded-full object-cover ring-2 ring-gray-200">
        <span class="hidden md:block font-medium"><?= sanitize($currentUser['username'] ?? '') ?></span>
        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>

      <div x-show="open" @click.away="open = false" x-transition
           class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-100 dark:border-gray-700 py-1 z-50">
        <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-700">
          <p class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= sanitize($currentUser['username'] ?? '') ?></p>
          <p class="text-xs text-gray-500 dark:text-gray-400"><?= sanitize($currentUser['role'] ?? '') ?></p>
        </div>
        <?php if ($currentUser['employee_id'] ?? null): ?>
          <a href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= $currentUser['employee_id'] ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            My Profile
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/modules/settings/change-password.php" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
          Change Password
        </a>
        <?php
          $navDbUser = fetchOne("SELECT totp_enabled, totp_confirmed FROM users WHERE id = ?", 'i', (int)($currentUser['id'] ?? 0));
          $navTwoFAOn = !empty($navDbUser['totp_enabled']) && !empty($navDbUser['totp_confirmed']);
        ?>
        <a href="<?= BASE_URL ?>/auth/<?= $navTwoFAOn ? '2fa-disable' : '2fa-setup' ?>.php"
           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
          <?= $navTwoFAOn ? '2FA Enabled' : 'Enable 2FA' ?>
        </a>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
          Sign Out
        </a>
      </div>
    </div>
  </div>
</header>
