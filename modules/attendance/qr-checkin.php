<?php
/**
 * HRMS - QR Code Check-in endpoint
 * Scanned by a kiosk / browser — logs attendance via token
 */
require_once __DIR__ . '/../../includes/functions.php';
startSession();

$token    = sanitize($_GET['token'] ?? '');
$response = ['success' => false, 'message' => ''];

if (!$token) {
    $response['message'] = 'Invalid QR code.';
} else {
    $employee = fetchOne(
        "SELECT e.*, u.id AS user_id FROM employees e
         JOIN users u ON u.employee_id = e.id
         WHERE e.qr_token = ? AND e.employment_status = 'Active' AND u.is_active = 1",
        's', $token
    );

    if (!$employee) {
        $response['message'] = 'Employee not found or inactive.';
    } else {
        $empId = (int)$employee['id'];
        $today = date('Y-m-d');
        $now   = date('H:i:s');

        $todayRecord = fetchOne(
            "SELECT * FROM attendance WHERE employee_id = ? AND date = ?",
            'is', $empId, $today
        );

        if (!$todayRecord) {
            // Check in
            $empShift = fetchOne(
                "SELECT s.* FROM employee_shifts es JOIN shifts s ON es.shift_id = s.id
                 WHERE es.employee_id = ? AND es.effective_from <= CURDATE()
                   AND (es.effective_to IS NULL OR es.effective_to >= CURDATE())
                 ORDER BY es.effective_from DESC LIMIT 1",
                'i', $empId
            );
            $isLate      = 0;
            $lateMinutes = 0;
            $status      = 'Present';
            $shiftId     = null;
            $nowTod      = strtotime($now) - strtotime('today');
            if ($empShift) {
                $shiftId  = (int)$empShift['id'];
                $shiftTod = strtotime($empShift['start_time']) - strtotime('today');
                $graceEnd = $shiftTod + $empShift['grace_minutes'] * 60;
                if ($nowTod > $graceEnd) {
                    $isLate = 1;
                    $lateMinutes = (int)floor(($nowTod - $shiftTod) / 60);
                    $status = 'Late';
                }
            } else {
                if ($nowTod > 9 * 3600 + 15 * 60) {
                    $isLate = 1;
                    $lateMinutes = (int)floor(($nowTod - (9 * 3600 + 15 * 60)) / 60);
                    $status = 'Late';
                }
            }
            execute(
                "INSERT INTO attendance (employee_id, date, check_in, status, is_late, late_minutes, shift_id, check_in_method, work_type)
                 VALUES (?,?,?,?,?,?,?,'QR','Office')",
                'isssiiis', $empId, $today, $now, $status, $isLate, $lateMinutes, $shiftId
            );
            $response['success'] = true;
            $response['message'] = 'Checked in at ' . date('h:i A') . ($isLate ? " (Late by {$lateMinutes} min)" : '');
            $response['action']  = 'checkin';
            $response['name']    = $employee['first_name'] . ' ' . $employee['last_name'];

        } elseif ($todayRecord['check_out'] === null) {
            // Check out
            $checkInSec  = strtotime($todayRecord['check_in']);
            $checkOutSec = strtotime($now);
            $breakSec    = (int)$todayRecord['break_minutes'] * 60;
            $totalHours  = round(($checkOutSec - $checkInSec - $breakSec) / 3600, 2);
            $status      = $todayRecord['status'];
            if ($totalHours < 4 && in_array($status, ['Present','Late'])) $status = 'Half Day';

            execute(
                "UPDATE attendance SET check_out=?, total_hours=?, status=? WHERE id=?",
                'sdsi', $now, $totalHours, $status, $todayRecord['id']
            );
            $response['success'] = true;
            $response['message'] = 'Checked out at ' . date('h:i A') . '. Total: ' . number_format($totalHours, 2) . 'h';
            $response['action']  = 'checkout';
            $response['name']    = $employee['first_name'] . ' ' . $employee['last_name'];
        } else {
            $response['message'] = 'Attendance already completed for today.';
        }
    }
}

// If accessed via browser (not AJAX), show a nice page
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])):
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QR Check-in — HRMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-900 flex items-center justify-center p-6">
  <div class="max-w-sm w-full text-center">
    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4
                <?= $response['success'] ? 'bg-green-500' : 'bg-red-500' ?>">
      <?php if ($response['success']): ?>
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
      <?php else: ?>
        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      <?php endif; ?>
    </div>
    <?php if (!empty($response['name'])): ?>
      <p class="text-white text-xl font-bold mb-1"><?= htmlspecialchars($response['name']) ?></p>
    <?php endif; ?>
    <p class="text-<?= $response['success'] ? 'green' : 'red' ?>-400 text-lg mb-6">
      <?= htmlspecialchars($response['message']) ?>
    </p>
    <p class="text-gray-500 text-sm"><?= date('l, F j, Y · h:i A') ?></p>
    <script>setTimeout(() => window.close(), 5000);</script>
  </div>
</body>
</html>
<?php else:
    header('Content-Type: application/json');
    echo json_encode($response);
endif;
