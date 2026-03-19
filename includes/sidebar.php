<?php
$currentUser = currentUser();
$role        = $currentUser['role'] ?? 'Employee';
$currentPath = $_SERVER['PHP_SELF'];

function navItem(string $href, string $svg, string $label, string $current, array $match = []): string {
    $match[] = $href;
    $active  = false;
    foreach ($match as $p) {
        if (strpos($current, $p) !== false) { $active = true; break; }
    }
    $cls = $active
        ? 'flex items-center gap-3 px-4 py-2.5 rounded-lg bg-indigo-700 text-white font-medium text-sm'
        : 'flex items-center gap-3 px-4 py-2.5 rounded-lg text-indigo-100 hover:bg-indigo-700 hover:text-white transition-colors text-sm';
    return "<a href=\"{$href}\" class=\"{$cls}\">{$svg}<span>{$label}</span></a>";
}

function sectionLabel(string $text): string {
    return "<div class=\"pt-3 pb-1 px-4 text-xs font-semibold text-indigo-400 uppercase tracking-wider\">{$text}</div>";
}

// SVG Icons (mini)
$icons = [
    'dashboard'    => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
    'employees'    => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
    'departments'  => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>',
    'attendance'   => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'leaves'       => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
    'payroll'      => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'recruitment'  => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
    'performance'  => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
    'documents'    => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'reports'      => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'notifications'=> '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
    'profile'      => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
    'payslip'      => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>',
    'users'        => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
    'overtime'     => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    'tickets'      => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>',
    'audit'        => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>',
    'holidays'     => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>',
    'qr'           => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>',
    'settings'     => '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
];

$B = BASE_URL;
?>

<aside class="fixed inset-y-0 left-0 w-64 bg-indigo-800 flex flex-col z-30 overflow-y-auto">

  <!-- Logo + Role Badge -->
  <div class="flex items-center gap-3 px-5 py-4 border-b border-indigo-700">
    <div class="w-9 h-9 bg-white rounded-lg flex items-center justify-center text-indigo-600 font-black text-base shrink-0">HR</div>
    <div class="min-w-0">
      <div class="text-white font-bold text-sm leading-tight">HRMS</div>
      <div class="text-xs mt-0.5">
        <?php
        $roleColors = [
            'Admin'              => 'bg-red-500 text-white',
            'HR Manager'         => 'bg-emerald-500 text-white',
            'Head of Department' => 'bg-blue-500 text-white',
            'Employee'           => 'bg-indigo-400 text-white',
        ];
        $rc = $roleColors[$role] ?? 'bg-indigo-400 text-white';
        ?>
        <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium <?= $rc ?>"><?= htmlspecialchars($role) ?></span>
      </div>
    </div>
  </div>

  <!-- Navigation -->
  <nav class="flex-1 px-3 py-3 space-y-0.5 overflow-y-auto">

    <?php if ($role === 'Admin'): ?>
    <!-- ═══════════ ADMIN NAVIGATION ═══════════ -->
    <?= navItem($B.'/modules/dashboard/index.php', $icons['dashboard'], 'Dashboard', $currentPath) ?>

    <?= sectionLabel('People & Structure') ?>
    <?= navItem($B.'/modules/employees/index.php',   $icons['employees'],   'Employees',   $currentPath, ['/modules/employees/']) ?>
    <?= navItem($B.'/modules/departments/index.php', $icons['departments'], 'Departments', $currentPath, ['/modules/departments/']) ?>

    <?= sectionLabel('Operations') ?>
    <?= navItem($B.'/modules/recruitment/index.php',$icons['recruitment'],'Recruitment', $currentPath, ['/modules/recruitment/']) ?>
    <?= navItem($B.'/modules/performance/index.php',$icons['performance'],'Performance', $currentPath, ['/modules/performance/']) ?>

    <?= sectionLabel('Time & Leave') ?>
    <?= navItem($B.'/modules/attendance/index.php', $icons['attendance'], 'Attendance',    $currentPath, ['/modules/attendance/']) ?>
    <?= navItem($B.'/modules/leaves/index.php',     $icons['leaves'],     'Leave Mgmt',    $currentPath, ['/modules/leaves/']) ?>
    <?= navItem($B.'/modules/overtime/index.php',   $icons['overtime'],   'Overtime',      $currentPath, ['/modules/overtime/']) ?>
    <?= navItem($B.'/modules/tickets/index.php',    $icons['tickets'],    'HR Help Desk',  $currentPath, ['/modules/tickets/']) ?>

    <?= sectionLabel('Reports & System') ?>
    <?= navItem($B.'/modules/reports/index.php',            $icons['reports'],   'Reports',       $currentPath, ['/modules/reports/']) ?>
    <?= navItem($B.'/modules/documents/index.php',          $icons['documents'], 'Documents',     $currentPath, ['/modules/documents/']) ?>
    <?= navItem($B.'/modules/users/index.php',              $icons['users'],     'User Roles',    $currentPath, ['/modules/users/']) ?>
    <?= navItem($B.'/modules/audit/index.php',              $icons['audit'],     'Audit Log',     $currentPath, ['/modules/audit/']) ?>
    <?= navItem($B.'/modules/settings/holidays.php',        $icons['holidays'],  'Holidays',      $currentPath, ['/modules/settings/holidays']) ?>
    <?= navItem($B.'/modules/settings/permissions.php',     $icons['settings'],  'Permissions',   $currentPath, ['/modules/settings/permissions']) ?>
    <?= navItem($B.'/modules/notifications/index.php',      $icons['notifications'],'Notifications',$currentPath,['/modules/notifications/']) ?>

    <?php elseif ($role === 'HR Manager'): ?>
    <!-- ═══════════ HR MANAGER NAVIGATION ═══════════ -->
    <?= navItem($B.'/modules/dashboard/index.php', $icons['dashboard'], 'Dashboard', $currentPath) ?>

    <?= sectionLabel('People') ?>
    <?= navItem($B.'/modules/employees/index.php',   $icons['employees'],   'Employees',   $currentPath, ['/modules/employees/']) ?>
    <?= navItem($B.'/modules/departments/index.php', $icons['departments'], 'Departments', $currentPath, ['/modules/departments/']) ?>
    <?= navItem($B.'/modules/recruitment/index.php', $icons['recruitment'], 'Recruitment', $currentPath, ['/modules/recruitment/']) ?>

    <?= sectionLabel('Time & Leave') ?>
    <?= navItem($B.'/modules/attendance/index.php', $icons['attendance'], 'Attendance',       $currentPath, ['/modules/attendance/']) ?>
    <?= navItem($B.'/modules/leaves/index.php',     $icons['leaves'],     'Leave Management', $currentPath, ['/modules/leaves/']) ?>

    <?= sectionLabel('Finance & HR') ?>
    <?= navItem($B.'/modules/payroll/index.php',     $icons['payroll'],     'Payroll',     $currentPath, ['/modules/payroll/']) ?>
    <?= navItem($B.'/modules/performance/index.php', $icons['performance'], 'Performance', $currentPath, ['/modules/performance/']) ?>
    <?= navItem($B.'/modules/overtime/index.php',    $icons['overtime'],    'Overtime',    $currentPath, ['/modules/overtime/']) ?>
    <?= navItem($B.'/modules/tickets/index.php',     $icons['tickets'],     'HR Help Desk',$currentPath, ['/modules/tickets/']) ?>

    <?= sectionLabel('Files & Reports') ?>
    <?= navItem($B.'/modules/documents/index.php',       $icons['documents'],    'Documents',     $currentPath, ['/modules/documents/']) ?>
    <?= navItem($B.'/modules/reports/index.php',         $icons['reports'],      'Reports',       $currentPath, ['/modules/reports/']) ?>
    <?= navItem($B.'/modules/settings/holidays.php',     $icons['holidays'],     'Holidays',      $currentPath, ['/modules/settings/holidays']) ?>
    <?= navItem($B.'/modules/notifications/index.php',   $icons['notifications'],'Notifications', $currentPath, ['/modules/notifications/']) ?>

    <?php elseif ($role === 'Head of Department'): ?>
    <!-- ═══════════ DEPARTMENT MANAGER NAVIGATION ═══════════ -->
    <?= navItem($B.'/modules/dashboard/index.php', $icons['dashboard'], 'Dashboard', $currentPath) ?>

    <?= sectionLabel('My Department') ?>
    <?= navItem($B.'/modules/employees/index.php',  $icons['employees'],  'Team Members', $currentPath, ['/modules/employees/']) ?>
    <?= navItem($B.'/modules/attendance/index.php', $icons['attendance'], 'Attendance',   $currentPath, ['/modules/attendance/']) ?>
    <?= navItem($B.'/modules/leaves/index.php',     $icons['leaves'],     'Leave Requests', $currentPath, ['/modules/leaves/']) ?>

    <?= sectionLabel('Reviews & Reports') ?>
    <?= navItem($B.'/modules/performance/index.php', $icons['performance'], 'Performance',   $currentPath, ['/modules/performance/']) ?>
    <?= navItem($B.'/modules/overtime/index.php',    $icons['overtime'],    'Overtime',      $currentPath, ['/modules/overtime/']) ?>
    <?= navItem($B.'/modules/tickets/index.php',     $icons['tickets'],     'HR Help Desk',  $currentPath, ['/modules/tickets/']) ?>
    <?= navItem($B.'/modules/documents/index.php',   $icons['documents'],   'Documents',     $currentPath, ['/modules/documents/']) ?>
    <?= navItem($B.'/modules/reports/index.php',     $icons['reports'],     'Reports',       $currentPath, ['/modules/reports/']) ?>
    <?= navItem($B.'/modules/notifications/index.php',$icons['notifications'],'Notifications', $currentPath, ['/modules/notifications/']) ?>

    <?php else: ?>
    <!-- ═══════════ EMPLOYEE NAVIGATION ═══════════ -->
    <?= navItem($B.'/modules/dashboard/index.php', $icons['dashboard'], 'Dashboard', $currentPath) ?>

    <?= sectionLabel('My Workspace') ?>
    <?php if ($currentUser['employee_id']): ?>
      <?= navItem($B.'/modules/employees/view.php?id='.$currentUser['employee_id'], $icons['profile'], 'My Profile', $currentPath) ?>
    <?php endif; ?>
    <?= navItem($B.'/modules/attendance/checkin.php', $icons['attendance'], 'Check In / Out', $currentPath, ['/modules/attendance/checkin']) ?>
    <?= navItem($B.'/modules/attendance/index.php',   $icons['attendance'], 'My Attendance',  $currentPath, ['/modules/attendance/index']) ?>

    <?= sectionLabel('Requests') ?>
    <?= navItem($B.'/modules/leaves/apply.php',   $icons['leaves'],   'Apply for Leave',  $currentPath, ['/modules/leaves/apply']) ?>
    <?= navItem($B.'/modules/leaves/index.php',   $icons['leaves'],   'My Leave History', $currentPath, ['/modules/leaves/index']) ?>
    <?= navItem($B.'/modules/overtime/apply.php', $icons['overtime'], 'Request Overtime', $currentPath, ['/modules/overtime/apply']) ?>
    <?= navItem($B.'/modules/tickets/create.php', $icons['tickets'],  'HR Help Desk',     $currentPath, ['/modules/tickets/']) ?>

    <?= sectionLabel('My Records') ?>
    <?= navItem($B.'/modules/payroll/index.php',        $icons['payslip'],      'My Payslips',    $currentPath, ['/modules/payroll/']) ?>
    <?= navItem($B.'/modules/performance/index.php',    $icons['performance'],  'My Performance', $currentPath, ['/modules/performance/']) ?>
    <?= navItem($B.'/modules/attendance/my-qr.php',     $icons['qr'],           'My QR Code',     $currentPath, ['/modules/attendance/my-qr']) ?>
    <?= navItem($B.'/modules/documents/index.php',      $icons['documents'],    'Documents',      $currentPath, ['/modules/documents/']) ?>
    <?= navItem($B.'/modules/notifications/index.php',  $icons['notifications'],'Notifications',  $currentPath, ['/modules/notifications/']) ?>
    <?php endif; ?>

  </nav>

  <!-- User Footer -->
  <div class="border-t border-indigo-700 px-4 py-3">
    <div class="flex items-center gap-3">
      <img src="<?= avatarUrl($currentUser['photo'] ?? null) ?>" alt=""
           class="w-8 h-8 rounded-full object-cover ring-2 ring-indigo-500 shrink-0">
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-white truncate"><?= sanitize($currentUser['username'] ?? '') ?></p>
        <p class="text-xs text-indigo-300 truncate"><?= sanitize($role) ?></p>
      </div>
      <a href="<?= BASE_URL ?>/auth/logout.php" title="Logout"
         class="text-indigo-300 hover:text-white transition-colors shrink-0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
      </a>
    </div>
  </div>
</aside>
