<?php
/**
 * HRMS - 2FA Disable
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';
requireLogin();

$user   = currentUser();
$userId = (int)$user['id'];
$dbUser = fetchOne("SELECT totp_secret, totp_enabled, totp_confirmed FROM users WHERE id = ?", 'i', $userId);

if (!$dbUser['totp_enabled']) {
    header('Location: ' . BASE_URL . '/auth/2fa-setup.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $code     = preg_replace('/\s+/', '', $_POST['totp_code'] ?? '');
        $verified = totpVerify($dbUser['totp_secret'], $code);
        if (!$verified) {
            $verified = totpUseBackupCode($userId, $code);
        }
        if ($verified) {
            execute(
                "UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_confirmed = 0 WHERE id = ?",
                'i', $userId
            );
            execute("DELETE FROM totp_backup_codes WHERE user_id = ?", 'i', $userId);
            setFlash('success', '2FA has been disabled on your account.');
            header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
            exit;
        } else {
            $error = 'Invalid code. Please try again.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">
  <div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Disable Two-Factor Authentication</h1>
    <p class="text-sm text-gray-500 mb-6">Enter your current authenticator code or a backup code to disable 2FA.</p>

    <?php if ($error): ?>
      <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-100 text-red-800 border border-red-200"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
      <form method="POST" action="" class="space-y-4">
        <?= csrfField() ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Verification Code</label>
          <input type="text" name="totp_code" maxlength="8" placeholder="6-digit or backup code" required autofocus
                 class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-mono tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
        </div>
        <button type="submit"
                class="w-full py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-colors">
          Disable 2FA
        </button>
        <a href="<?= BASE_URL ?>/modules/dashboard/index.php"
           class="block text-center text-sm text-gray-500 hover:text-gray-700">Cancel</a>
      </form>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
