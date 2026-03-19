<?php
/**
 * HRMS - My QR Code (for employee self-check-in)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user   = currentUser();
$empId  = (int)($user['employee_id'] ?? 0);

if (!$empId) {
    setFlash('error', 'No employee record linked to your account.');
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$employee = fetchOne("SELECT * FROM employees WHERE id = ?", 'i', $empId);

// Generate QR token if not set
if (empty($employee['qr_token'])) {
    $token = bin2hex(random_bytes(16));
    execute("UPDATE employees SET qr_token = ? WHERE id = ?", 'si', $token, $empId);
    $employee['qr_token'] = $token;
}

$qrData  = BASE_URL . '/modules/attendance/qr-checkin.php?token=' . $employee['qr_token'];
$qrUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . rawurlencode($qrData);

$pageTitle = 'My QR Code';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">
  <div class="max-w-sm mx-auto text-center">

    <h1 class="text-2xl font-bold text-gray-900 mb-2">My Check-in QR Code</h1>
    <p class="text-sm text-gray-500 mb-6">Present this code at a QR scanner station to check in or out.</p>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-8 mb-5">
      <img src="<?= htmlspecialchars($qrUrl) ?>" alt="My QR Code"
           class="w-56 h-56 mx-auto border border-gray-100 rounded-xl mb-4">
      <p class="font-semibold text-gray-900">
        <?= sanitize($employee['first_name'] . ' ' . $employee['last_name']) ?>
      </p>
      <p class="text-sm text-gray-400"><?= sanitize($employee['employee_id']) ?></p>
      <code class="block mt-2 text-xs text-gray-400 font-mono truncate"><?= htmlspecialchars($employee['qr_token']) ?></code>
    </div>

    <!-- Regenerate token -->
    <form method="POST" action="<?= BASE_URL ?>/modules/attendance/regenerate-qr.php"
          onsubmit="return confirm('Regenerate QR code? Your old code will stop working.')">
      <?= csrfField() ?>
      <button type="submit"
              class="text-sm text-red-600 hover:underline">
        Regenerate QR code
      </button>
    </form>

  </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
