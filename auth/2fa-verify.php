<?php
/**
 * HRMS - 2FA Verification during login
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';
startSession();

// Must have a pending 2FA session
if (empty($_SESSION['2fa_pending_user_id'])) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

$pendingId = (int)$_SESSION['2fa_pending_user_id'];
$error     = '';
$useBackup = isset($_GET['backup']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $dbUser = fetchOne("SELECT * FROM users WHERE id = ? AND is_active = 1", 'i', $pendingId);
        if (!$dbUser) {
            session_destroy();
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }

        $code      = preg_replace('/\s+/', '', $_POST['totp_code'] ?? '');
        $verified  = false;

        if ($useBackup || !empty($_POST['is_backup'])) {
            $verified = totpUseBackupCode($pendingId, $code);
        } else {
            $verified = totpVerify($dbUser['totp_secret'], $code);
        }

        if ($verified) {
            // Complete login — same logic as login.php after password_verify
            $deptId = null;
            if ($dbUser['role'] === 'Head of Department' && $dbUser['employee_id']) {
                $emp    = fetchOne("SELECT department_id FROM employees WHERE id = ?", 'i', (int)$dbUser['employee_id']);
                $deptId = ($emp && $emp['department_id']) ? (int)$emp['department_id'] : null;
            }
            $photo = fetchOne("SELECT photo FROM employees WHERE id = ?", 'i', (int)$dbUser['employee_id'])['photo'] ?? null;

            $_SESSION['user_id'] = $dbUser['id'];
            $_SESSION['user']    = [
                'id'          => $dbUser['id'],
                'username'    => $dbUser['username'],
                'email'       => $dbUser['email'],
                'role'        => $dbUser['role'],
                'employee_id' => $dbUser['employee_id'],
                'photo'       => $photo,
                'dept_id'     => $deptId,
            ];
            $_SESSION['must_change_password'] = !empty($dbUser['must_change_password']);
            unset($_SESSION['2fa_pending_user_id']);

            execute("UPDATE users SET last_login = NOW() WHERE id = ?", 'i', $dbUser['id']);

            if (!empty($dbUser['must_change_password'])) {
                header('Location: ' . BASE_URL . '/modules/settings/force-change-password.php');
            } else {
                header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
            }
            exit;
        } else {
            $error = $useBackup ? 'Invalid or already-used backup code.' : 'Invalid code. Try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Two-Factor Authentication — HRMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: { colors: {
        indigo: { 500: '#2EC4B6', 600: '#0B2545', 700: '#1a3a6b', 800: '#0B2545' }
      }}}
    }
  </script>
</head>
<body class="min-h-screen bg-gray-900 flex items-center justify-center p-6">
  <div class="w-full max-w-sm">
    <div class="flex items-center justify-center gap-3 mb-8">
      <div class="w-11 h-11 bg-white rounded-xl flex items-center justify-center shadow-lg">
        <span class="text-lg font-black" style="color:#0B2545;">HR</span>
      </div>
      <span class="text-white text-2xl font-bold tracking-wide">HRMS</span>
    </div>

    <div class="bg-white rounded-2xl shadow-xl p-8">
      <div class="text-center mb-6">
        <div class="w-14 h-14 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-3">
          <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900">Two-Factor Authentication</h1>
        <p class="text-sm text-gray-500 mt-1">
          <?= $useBackup ? 'Enter one of your backup codes.' : 'Enter the 6-digit code from your authenticator app.' ?>
        </p>
      </div>

      <?php if ($error): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-100 text-red-800 border border-red-200">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrfField() ?>
        <?php if ($useBackup): ?>
          <input type="hidden" name="is_backup" value="1">
        <?php endif; ?>
        <div class="mb-5">
          <input type="text" name="totp_code"
                 maxlength="<?= $useBackup ? 8 : 6 ?>"
                 placeholder="<?= $useBackup ? 'XXXXXXXX' : '000 000' ?>"
                 class="w-full rounded-lg border border-gray-300 px-4 py-3 text-center text-xl font-mono tracking-widest focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 autofocus required>
        </div>
        <button type="submit"
                class="w-full py-2.5 rounded-lg font-semibold text-white text-sm transition-colors"
                style="background:#2EC4B6"
                onmouseover="this.style.background='#26a99d'" onmouseout="this.style.background='#2EC4B6'">
          Verify
        </button>
      </form>

      <div class="mt-5 text-center text-sm">
        <?php if (!$useBackup): ?>
          <a href="?backup=1" class="text-indigo-600 hover:underline">Use a backup code instead</a>
        <?php else: ?>
          <a href="?" class="text-indigo-600 hover:underline">Use authenticator app instead</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
