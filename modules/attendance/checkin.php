<?php
/**
 * HRMS - Employee Check In / Check Out
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
if (!hasRole('Employee')) {
    setFlash('error', 'Check-in is for employees only.');
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$pageTitle = 'Check In / Check Out';
$user      = currentUser();

// Resolve employee record for the logged-in user
$employeeId = (int)($user['employee_id'] ?? 0);
if ($employeeId <= 0) {
    setFlash('error', 'No employee profile is linked to your account. Please contact HR.');
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$employee = fetchOne(
    "SELECT e.*, d.name AS dept_name
     FROM employees e
     LEFT JOIN departments d ON e.department_id = d.id
     WHERE e.id = ?",
    'i', $employeeId
);

if (!$employee) {
    setFlash('error', 'Employee record not found.');
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$today = date('Y-m-d');

// Fetch today's attendance record
$todayRecord = fetchOne(
    "SELECT * FROM attendance WHERE employee_id = ? AND date = ?",
    'is', $employeeId, $today
);

// ── POST Handler ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token. Please try again.');
        header('Location: ' . BASE_URL . '/modules/attendance/checkin.php');
        exit;
    }

    $action = sanitize($_POST['action'] ?? '');

    // Resolve employee's current shift
    $empShift = fetchOne(
        "SELECT s.* FROM employee_shifts es
         JOIN shifts s ON es.shift_id = s.id
         WHERE es.employee_id = ? AND es.effective_from <= CURDATE()
           AND (es.effective_to IS NULL OR es.effective_to >= CURDATE())
         ORDER BY es.effective_from DESC LIMIT 1",
        'i', $employeeId
    );

    if ($action === 'checkin') {
        // Must not already be checked in today
        if ($todayRecord) {
            setFlash('error', 'You have already checked in today.');
        } else {
            $now         = date('H:i:s');
            $checkInSec  = strtotime($now);
            $workType    = sanitize($_POST['work_type'] ?? 'Office');
            if (!in_array($workType, ['Office','WFH','Remote','Field'])) $workType = 'Office';

            // Shift-aware late detection
            $isLate      = 0;
            $lateMinutes = 0;
            if ($empShift) {
                $shiftStart    = strtotime($empShift['start_time']);
                $graceEnd      = $shiftStart + ($empShift['grace_minutes'] * 60);
                $shiftStartSec = $shiftStart; // Time-of-day comparison
                $nowTod        = $checkInSec - strtotime('today');
                if ($nowTod > ($shiftStart - strtotime('today')) + ($empShift['grace_minutes'] * 60)) {
                    $isLate      = 1;
                    $lateMinutes = (int)floor(($nowTod - ($shiftStart - strtotime('today'))) / 60);
                }
            } else {
                // Fallback: late after 09:15
                $nowTod = $checkInSec - strtotime('today');
                if ($nowTod > (9 * 3600 + 15 * 60)) {
                    $isLate = 1;
                    $lateMinutes = (int)floor(($nowTod - (9 * 3600 + 15 * 60)) / 60);
                }
            }
            $status = $isLate ? 'Late' : 'Present';
            $shiftId = $empShift ? (int)$empShift['id'] : null;

            $result = execute(
                "INSERT INTO attendance (employee_id, date, check_in, status, work_type, is_late, late_minutes, shift_id, check_in_method)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Manual')",
                'issssiis', $employeeId, $today, $now, $status, $workType, $isLate, $lateMinutes, $shiftId
            );

            if ($result) {
                $msg = 'Checked in at ' . date('h:i A', $checkInSec) . ' (' . $workType . ')';
                if ($isLate) $msg .= ' — Late by ' . $lateMinutes . ' min';
                setFlash($isLate ? 'error' : 'success', $msg);
            } else {
                setFlash('error', 'Failed to record check-in. Please try again.');
            }
        }

    } elseif ($action === 'break_start') {
        if (!$todayRecord || $todayRecord['check_out'] !== null) {
            setFlash('error', 'Cannot start break — not checked in.');
        } elseif (!empty($todayRecord['break_start']) && empty($todayRecord['break_end'])) {
            setFlash('error', 'Break already started.');
        } else {
            execute("UPDATE attendance SET break_start = ?, break_end = NULL WHERE id = ?", 'si', date('H:i:s'), $todayRecord['id']);
            setFlash('success', 'Break started at ' . date('h:i A') . '.');
        }

    } elseif ($action === 'break_end') {
        if (!$todayRecord || empty($todayRecord['break_start'])) {
            setFlash('error', 'No break in progress.');
        } elseif (!empty($todayRecord['break_end'])) {
            setFlash('error', 'Break already ended.');
        } else {
            $breakMins = max(0, (int)floor((strtotime('now') - strtotime($todayRecord['break_start'])) / 60));
            execute(
                "UPDATE attendance SET break_end = ?, break_minutes = break_minutes + ? WHERE id = ?",
                'sii', date('H:i:s'), $breakMins, $todayRecord['id']
            );
            setFlash('success', "Break ended. Duration: {$breakMins} min.");
        }

    } elseif ($action === 'checkout') {
        // Must have a check-in record and not yet checked out
        if (!$todayRecord) {
            setFlash('error', 'No check-in record found for today. Please check in first.');
        } elseif ($todayRecord['check_out'] !== null) {
            setFlash('error', 'You have already checked out today.');
        } else {
            $now          = date('H:i:s');
            $checkInSec   = strtotime($todayRecord['check_in']);
            $checkOutSec  = strtotime($now);
            $totalSeconds = max(0, $checkOutSec - $checkInSec);
            $breakSec     = (int)$todayRecord['break_minutes'] * 60;
            $totalHours   = round(($totalSeconds - $breakSec) / 3600, 2);

            // Shift-aware early departure
            $isEarly       = 0;
            $earlyMinutes  = 0;
            if ($empShift) {
                $shiftEndTod = strtotime($empShift['end_time']) - strtotime('today');
                $nowTod      = $checkOutSec - strtotime('today');
                $earlyGrace  = $empShift['early_departure_minutes'] * 60;
                if ($nowTod < ($shiftEndTod - $earlyGrace)) {
                    $isEarly      = 1;
                    $earlyMinutes = (int)floor(($shiftEndTod - $nowTod) / 60);
                }
            }

            // Update status
            $status = $todayRecord['status'];
            if ($totalHours < 4 && in_array($status, ['Present', 'Late'])) {
                $status = 'Half Day';
            }

            $result = execute(
                "UPDATE attendance SET check_out=?, total_hours=?, status=?, is_early_departure=?, early_departure_minutes=? WHERE id=?",
                'sdsiii', $now, $totalHours, $status, $isEarly, $earlyMinutes, $todayRecord['id']
            );

            if ($result) {
                $msg = 'Checked out at ' . date('h:i A', $checkOutSec) . '. Total: ' . number_format($totalHours, 2) . ' hours.';
                if ($isEarly) $msg .= ' Early departure by ' . $earlyMinutes . ' min.';
                setFlash($isEarly ? 'error' : 'success', $msg);
            } else {
                setFlash('error', 'Failed to record check-out. Please try again.');
            }
        }
    }

    header('Location: ' . BASE_URL . '/modules/attendance/checkin.php');
    exit;
}

// Re-fetch after any POST
$todayRecord = fetchOne(
    "SELECT * FROM attendance WHERE employee_id = ? AND date = ?",
    'is', $employeeId, $today
);

// Recent attendance (last 7 days)
$recentAttendance = fetchAll(
    "SELECT * FROM attendance WHERE employee_id = ? AND date < ? ORDER BY date DESC LIMIT 7",
    'is', $employeeId, $today
);

// Current month summary
$monthlySummary = fetchOne(
    "SELECT
        SUM(status = 'Present') AS present,
        SUM(status = 'Absent')  AS absent,
        SUM(status = 'Late')    AS late,
        SUM(status = 'Half Day') AS half_day,
        ROUND(SUM(IFNULL(total_hours, 0)), 1) AS total_hours
     FROM attendance
     WHERE employee_id = ? AND YEAR(date) = ? AND MONTH(date) = ?",
    'iii', $employeeId, (int)date('Y'), (int)date('m')
);

$flash = getFlash();

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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left: Check In/Out Card -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Time & Action Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="bg-indigo-600 px-6 py-5">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-lg font-semibold text-white">Attendance Clock</h2>
                <p class="text-indigo-200 text-sm mt-0.5"><?= date('l, F j, Y') ?></p>
              </div>
              <div class="text-right">
                <div id="live-clock" class="text-3xl font-bold text-white font-mono"></div>
                <p class="text-indigo-200 text-xs mt-1" id="live-date"></p>
              </div>
            </div>
          </div>

          <div class="p-6">

            <!-- Employee Info -->
            <div class="flex items-center gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
              <img src="<?= avatarUrl($employee['photo']) ?>" alt="Avatar"
                   class="w-14 h-14 rounded-full object-cover ring-2 ring-indigo-200">
              <div>
                <p class="font-semibold text-gray-900 text-base">
                  <?= sanitize($employee['first_name'] . ' ' . $employee['last_name']) ?>
                </p>
                <p class="text-sm text-gray-500">
                  <?= sanitize($employee['employee_id']) ?>
                  <?php if ($employee['position']): ?>
                    · <?= sanitize($employee['position']) ?>
                  <?php endif; ?>
                </p>
                <?php if ($employee['dept_name']): ?>
                  <p class="text-xs text-indigo-600 mt-0.5"><?= sanitize($employee['dept_name']) ?></p>
                <?php endif; ?>
              </div>
            </div>

            <!-- Today Status -->
            <div class="grid grid-cols-3 gap-4 mb-6">
              <div class="bg-gray-50 rounded-lg p-4 text-center">
                <p class="text-xs text-gray-500 mb-1">Check In</p>
                <p class="text-lg font-bold font-mono text-gray-900">
                  <?= $todayRecord && $todayRecord['check_in']
                      ? date('h:i A', strtotime($todayRecord['check_in']))
                      : '—' ?>
                </p>
              </div>
              <div class="bg-gray-50 rounded-lg p-4 text-center">
                <p class="text-xs text-gray-500 mb-1">Check Out</p>
                <p class="text-lg font-bold font-mono text-gray-900">
                  <?= $todayRecord && $todayRecord['check_out']
                      ? date('h:i A', strtotime($todayRecord['check_out']))
                      : '—' ?>
                </p>
              </div>
              <div class="bg-gray-50 rounded-lg p-4 text-center">
                <p class="text-xs text-gray-500 mb-1">Hours Today</p>
                <p class="text-lg font-bold text-gray-900">
                  <?php if ($todayRecord && $todayRecord['check_out']): ?>
                    <?= number_format((float)$todayRecord['total_hours'], 1) ?>h
                  <?php elseif ($todayRecord && $todayRecord['check_in']): ?>
                    <span class="text-indigo-600" id="elapsed-hours">—</span>
                  <?php else: ?>
                    —
                  <?php endif; ?>
                </p>
              </div>
            </div>

            <!-- Today's Status Badge -->
            <?php if ($todayRecord): ?>
              <div class="flex items-center gap-3 mb-6 p-3 bg-gray-50 rounded-lg">
                <span class="text-sm text-gray-600">Today's Status:</span>
                <?= statusBadge($todayRecord['status']) ?>
              </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <?php if (!$todayRecord): ?>
              <!-- Not checked in yet -->
              <form method="POST" action="" class="space-y-3">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="checkin">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1.5">Work Location</label>
                  <div class="grid grid-cols-4 gap-2">
                    <?php foreach (['Office','WFH','Remote','Field'] as $wt): ?>
                      <label class="cursor-pointer">
                        <input type="radio" name="work_type" value="<?= $wt ?>" class="sr-only peer" <?= $wt === 'Office' ? 'checked' : '' ?>>
                        <span class="block text-center py-2 border border-gray-200 rounded-lg text-xs font-medium text-gray-600
                                     peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:text-indigo-700 transition-colors">
                          <?= $wt ?>
                        </span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
                <button type="submit"
                        class="w-full flex items-center justify-center gap-3 bg-green-600 hover:bg-green-700
                               text-white text-base font-semibold px-6 py-4 rounded-xl transition-colors shadow-sm">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                  </svg>
                  Check In Now
                </button>
              </form>

            <?php elseif ($todayRecord['check_out'] === null): ?>
              <?php
                $onBreak   = !empty($todayRecord['break_start']) && empty($todayRecord['break_end']);
                $hadBreak  = !empty($todayRecord['break_start']) && !empty($todayRecord['break_end']);
              ?>
              <!-- Work type badge -->
              <?php if (!empty($todayRecord['work_type']) && $todayRecord['work_type'] !== 'Office'): ?>
                <div class="mb-3 flex items-center gap-2 text-sm text-indigo-700">
                  <span class="px-2 py-0.5 bg-indigo-50 border border-indigo-200 rounded text-xs font-medium"><?= htmlspecialchars($todayRecord['work_type']) ?></span>
                  <span class="text-gray-400 text-xs">work mode</span>
                </div>
              <?php endif; ?>

              <!-- Break controls -->
              <div class="flex gap-2 mb-3">
                <?php if (!$onBreak): ?>
                  <form method="POST" class="flex-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="break_start">
                    <button type="submit" class="w-full py-2.5 border border-orange-300 text-orange-600 hover:bg-orange-50 text-sm font-medium rounded-lg transition-colors">
                      ☕ Start Break
                    </button>
                  </form>
                <?php else: ?>
                  <div class="flex-1 py-2.5 bg-orange-50 border border-orange-200 text-orange-700 text-sm font-medium rounded-lg text-center">
                    On Break since <?= date('h:i A', strtotime($todayRecord['break_start'])) ?>
                  </div>
                  <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="break_end">
                    <button type="submit" class="px-4 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                      End Break
                    </button>
                  </form>
                <?php endif; ?>
              </div>

              <!-- Check out -->
              <?php if (!$onBreak): ?>
                <form method="POST" action="" onsubmit="return confirm('Confirm check-out now?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="checkout">
                  <button type="submit"
                          class="w-full flex items-center justify-center gap-3 bg-red-600 hover:bg-red-700
                                 text-white text-base font-semibold px-6 py-4 rounded-xl transition-colors shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Check Out Now
                  </button>
                </form>
              <?php else: ?>
                <p class="text-center text-sm text-gray-400 mt-2">End your break before checking out.</p>
              <?php endif; ?>

            <?php else: ?>
              <!-- Fully completed for today -->
              <div class="w-full flex items-center justify-center gap-3 bg-gray-100 text-gray-500
                          text-base font-semibold px-6 py-4 rounded-xl cursor-not-allowed">
                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Attendance Completed for Today
              </div>
              <?php if ($todayRecord['break_minutes'] > 0): ?>
                <p class="text-center text-xs text-gray-400 mt-2">Break: <?= $todayRecord['break_minutes'] ?> min</p>
              <?php endif; ?>
            <?php endif; ?>

          </div>
        </div>

        <!-- Recent 7 Days -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Recent Attendance</h3>
            <a href="<?= BASE_URL ?>/modules/attendance/index.php"
               class="text-xs text-indigo-600 hover:underline">View all →</a>
          </div>
          <?php if (empty($recentAttendance)): ?>
            <p class="px-5 py-6 text-sm text-gray-400 text-center">No recent records.</p>
          <?php else: ?>
            <div class="divide-y divide-gray-50">
              <?php foreach ($recentAttendance as $rec): ?>
                <div class="flex items-center justify-between px-5 py-3">
                  <div>
                    <p class="text-sm font-medium text-gray-900"><?= formatDate($rec['date']) ?></p>
                    <p class="text-xs text-gray-400">
                      <?= $rec['check_in'] ? date('h:i A', strtotime($rec['check_in'])) : '—' ?>
                      →
                      <?= $rec['check_out'] ? date('h:i A', strtotime($rec['check_out'])) : '—' ?>
                      <?php if ($rec['total_hours']): ?>
                        · <?= number_format((float)$rec['total_hours'], 1) ?>h
                      <?php endif; ?>
                    </p>
                  </div>
                  <?= statusBadge($rec['status']) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: Monthly Summary -->
      <div class="space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">This Month — <?= date('F Y') ?></h3>
          </div>
          <div class="p-5 space-y-4">
            <?php
            $summaryItems = [
              ['label' => 'Present',  'value' => (int)($monthlySummary['present']   ?? 0), 'color' => 'bg-green-100 text-green-700'],
              ['label' => 'Late',     'value' => (int)($monthlySummary['late']      ?? 0), 'color' => 'bg-orange-100 text-orange-700'],
              ['label' => 'Half Day', 'value' => (int)($monthlySummary['half_day']  ?? 0), 'color' => 'bg-yellow-100 text-yellow-700'],
              ['label' => 'Absent',   'value' => (int)($monthlySummary['absent']    ?? 0), 'color' => 'bg-red-100 text-red-700'],
            ];
            foreach ($summaryItems as $item): ?>
              <div class="flex items-center justify-between">
                <span class="text-sm text-gray-600"><?= $item['label'] ?></span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $item['color'] ?>">
                  <?= $item['value'] ?> day<?= $item['value'] !== 1 ? 's' : '' ?>
                </span>
              </div>
            <?php endforeach; ?>

            <div class="pt-3 border-t border-gray-100">
              <div class="flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700">Total Hours</span>
                <span class="text-sm font-bold text-indigo-600">
                  <?= number_format((float)($monthlySummary['total_hours'] ?? 0), 1) ?>h
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-5 space-y-3">
          <h4 class="text-sm font-semibold text-indigo-900">Quick Links</h4>
          <a href="<?= BASE_URL ?>/modules/attendance/index.php"
             class="flex items-center gap-2 text-sm text-indigo-700 hover:text-indigo-900 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            View Full Attendance History
          </a>
          <a href="<?= BASE_URL ?>/modules/attendance/report.php"
             class="flex items-center gap-2 text-sm text-indigo-700 hover:text-indigo-900 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Monthly Report
          </a>
          <a href="<?= BASE_URL ?>/modules/leaves/apply.php"
             class="flex items-center gap-2 text-sm text-indigo-700 hover:text-indigo-900 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Apply for Leave
          </a>
        </div>
      </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
  // Live clock
  function updateClock() {
    const now = new Date();
    const hh  = String(now.getHours()).padStart(2, '0');
    const mm  = String(now.getMinutes()).padStart(2, '0');
    const ss  = String(now.getSeconds()).padStart(2, '0');
    const ampm = now.getHours() >= 12 ? 'PM' : 'AM';
    const h12  = now.getHours() % 12 || 12;
    document.getElementById('live-clock').textContent =
      String(h12).padStart(2, '0') + ':' + mm + ':' + ss + ' ' + ampm;
    document.getElementById('live-date').textContent =
      now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }
  updateClock();
  setInterval(updateClock, 1000);

  <?php if ($todayRecord && !$todayRecord['check_out'] && $todayRecord['check_in']): ?>
  // Show elapsed hours for in-progress attendance
  const checkInTime = new Date();
  const parts = '<?= $todayRecord['check_in'] ?>'.split(':');
  checkInTime.setHours(parseInt(parts[0]), parseInt(parts[1]), parseInt(parts[2]), 0);

  function updateElapsed() {
    const diff = Math.max(0, (new Date() - checkInTime) / 3600000);
    const el = document.getElementById('elapsed-hours');
    if (el) el.textContent = diff.toFixed(1) + 'h';
  }
  updateElapsed();
  setInterval(updateElapsed, 10000);
  <?php endif; ?>
</script>
