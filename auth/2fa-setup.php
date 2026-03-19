<?php
/**
 * HRMS - 2FA Setup (generate secret + QR)
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';
requireLogin();

$user   = currentUser();
$userId = (int)$user['id'];
$dbUser = fetchOne("SELECT totp_secret, totp_enabled, totp_confirmed FROM users WHERE id = ?", 'i', $userId);

// If 2FA already active, redirect to disable page
if ($dbUser['totp_enabled'] && $dbUser['totp_confirmed']) {
    header('Location: ' . BASE_URL . '/auth/2fa-disable.php');
    exit;
}

$error   = '';
$success = '';
$backupCodes = [];

// Generate a pending secret if not yet created
if (empty($dbUser['totp_secret'])) {
    $secret = totpGenerateSecret();
    execute("UPDATE users SET totp_secret = ?, totp_enabled = 0, totp_confirmed = 0 WHERE id = ?", 'si', $secret, $userId);
    $dbUser['totp_secret'] = $secret;
}
$secret = $dbUser['totp_secret'];

// Handle verification form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $code = preg_replace('/\s+/', '', $_POST['totp_code'] ?? '');
        if (totpVerify($secret, $code)) {
            execute("UPDATE users SET totp_enabled = 1, totp_confirmed = 1 WHERE id = ?", 'i', $userId);
            $backupCodes = totpGenerateBackupCodes($userId);
            $success = '2FA enabled successfully. Save your backup codes below.';
        } else {
            $error = 'Invalid code. Please try again.';
        }
    }
}

$qrUrl      = totpQrUrl($secret, $user['email'] ?? $user['username']);
$manualUri  = totpGetUri($secret, $user['email'] ?? $user['username']);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">
  <div class="max-w-xl mx-auto">

    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Set Up Two-Factor Authentication</h1>
      <p class="text-sm text-gray-500 mt-1">Secure your account with an authenticator app (Google Authenticator, Authy, etc.)</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-100 text-red-800 border border-red-200"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success && !empty($backupCodes)): ?>
      <div class="mb-6 p-5 bg-green-50 border border-green-200 rounded-xl">
        <div class="flex items-center gap-2 mb-3">
          <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p class="font-semibold text-green-800">2FA Enabled! Save these backup codes.</p>
        </div>
        <p class="text-xs text-green-700 mb-3">Each code can be used once if you lose access to your authenticator app.</p>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach ($backupCodes as $bc): ?>
            <code class="block bg-white border border-green-200 rounded px-3 py-1.5 text-sm font-mono text-center tracking-widest"><?= htmlspecialchars($bc) ?></code>
          <?php endforeach; ?>
        </div>
        <a href="<?= BASE_URL ?>/modules/dashboard/index.php"
           class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
          Continue to Dashboard
        </a>
      </div>
    <?php else: ?>

      <!-- Step 1: Scan QR -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-5">
        <h2 class="font-semibold text-gray-800 mb-1">Step 1 — Scan QR Code</h2>
        <p class="text-sm text-gray-500 mb-4">Open your authenticator app and scan this QR code.</p>
        <div class="flex justify-center mb-4">
          <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" class="w-48 h-48 border border-gray-200 rounded-lg">
        </div>
        <details class="text-xs text-gray-500">
          <summary class="cursor-pointer hover:text-gray-700">Can't scan? Enter manually</summary>
          <code class="block mt-2 bg-gray-50 border border-gray-200 rounded p-2 break-all font-mono"><?= htmlspecialchars($secret) ?></code>
        </details>
      </div>

      <!-- Step 2: Verify -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <h2 class="font-semibold text-gray-800 mb-1">Step 2 — Verify Code</h2>
        <p class="text-sm text-gray-500 mb-4">Enter the 6-digit code from your authenticator app to confirm setup.</p>
        <form method="POST" action="" class="flex gap-3">
          <?= csrfField() ?>
          <input type="text" name="totp_code" maxlength="6" placeholder="000000" required autofocus
                 class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-mono tracking-widest focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          <button type="submit"
                  class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">
            Verify &amp; Enable
          </button>
        </form>
      </div>

    <?php endif; ?>

  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
