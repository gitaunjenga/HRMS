<?php
/**
 * HRMS - Reset Password (via token)
 */
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$token = sanitize(trim($_GET['token'] ?? ''));
$error = '';
$done  = false;

// Validate token
if (empty($token)) {
    header('Location: ' . BASE_URL . '/auth/forgot-password.php');
    exit;
}

$user = fetchOne(
    "SELECT id, username, email, reset_token_expires
     FROM users
     WHERE reset_token = ? AND is_active = 1",
    's', $token
);

if (!$user) {
    $error = 'This reset link is invalid or has already been used.';
} elseif (strtotime($user['reset_token_expires']) < time()) {
    // Expired — clear the token
    execute("UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE id = ?", 'i', (int)$user['id']);
    $error = 'This reset link has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $newPassword     = $_POST['password']         ?? '';
        $confirmPassword = $_POST['password_confirm'] ?? '';

        if (strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPassword)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPassword)) {
            $error = 'Password must contain at least one number.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            execute(
                "UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?",
                'si', $hashed, (int)$user['id']
            );
            setFlash('success', 'Your password has been reset. You can now sign in with your new password.');
            header('Location: ' . BASE_URL . '/auth/login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — HRMS</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            indigo: {
              50:  '#edf7f6', 100: '#cceeed', 200: '#99deda', 300: '#66cec6',
              400: '#40c2ba', 500: '#2EC4B6', 600: '#0B2545', 700: '#1a3a6b',
              800: '#0B2545', 900: '#071629',
            },
            purple: { 800: '#0B2545', 900: '#071629' }
          }
        }
      }
    }
  </script>
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='26'>👥</text></svg>">
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-900 via-indigo-800 to-purple-900 flex items-center justify-center p-4">

  <div class="w-full max-w-md">

    <!-- Logo -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-2xl shadow-lg mb-4">
        <span class="text-3xl font-black text-indigo-600">HR</span>
      </div>
      <h1 class="text-3xl font-bold text-white">Reset Password</h1>
      <p class="text-indigo-300 mt-1">Choose a strong new password</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-2xl p-8">

      <?php if ($error && !$user): ?>
        <!-- Invalid / expired token — no form shown -->
        <div class="flex flex-col items-center text-center gap-4">
          <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center">
            <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="text-gray-700 text-sm"><?= htmlspecialchars($error) ?></p>
          <a href="<?= BASE_URL ?>/auth/forgot-password.php"
             class="inline-block mt-2 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">
            Request a New Link
          </a>
        </div>

      <?php elseif ($error): ?>
        <div class="mb-4 flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
          <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
          <?= htmlspecialchars($error) ?>
        </div>
        <?php // Fall through to show form again ?>
      <?php endif; ?>

      <?php if ($user && !$done): ?>
        <p class="text-sm text-gray-600 mb-5">
          Resetting password for <span class="font-semibold text-gray-900"><?= sanitize($user['username']) ?></span>
        </p>

        <form method="POST" action="" novalidate id="reset-form">
          <?= csrfField() ?>

          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
            <div class="relative">
              <input type="password" name="password" id="new-password" required
                     class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                     placeholder="Min. 8 chars, 1 uppercase, 1 number" autocomplete="new-password">
              <button type="button" onclick="toggleField('new-password','eye1')"
                      class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                <svg id="eye1" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
            <!-- Strength bar -->
            <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
              <div id="strength-bar" class="h-full rounded-full transition-all duration-300" style="width:0"></div>
            </div>
            <p id="strength-label" class="text-xs text-gray-400 mt-1"></p>
          </div>

          <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm New Password</label>
            <div class="relative">
              <input type="password" name="password_confirm" id="confirm-password" required
                     class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                     placeholder="Repeat your new password" autocomplete="new-password">
              <button type="button" onclick="toggleField('confirm-password','eye2')"
                      class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                <svg id="eye2" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
            <p id="match-msg" class="text-xs mt-1 hidden"></p>
          </div>

          <button type="submit"
                  class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Set New Password
          </button>
        </form>
      <?php endif; ?>

    </div>

    <p class="text-center text-indigo-400 text-xs mt-6">&copy; <?= date('Y') ?> HRMS — Human Resource Management System</p>
  </div>

  <script>
    function toggleField(id, eyeId) {
      const inp = document.getElementById(id);
      inp.type = inp.type === 'password' ? 'text' : 'password';
    }

    const pwdInput  = document.getElementById('new-password');
    const confInput = document.getElementById('confirm-password');
    const bar       = document.getElementById('strength-bar');
    const label     = document.getElementById('strength-label');
    const matchMsg  = document.getElementById('match-msg');

    if (pwdInput) {
      pwdInput.addEventListener('input', function () {
        const v = this.value;
        let score = 0;
        if (v.length >= 8)           score++;
        if (/[A-Z]/.test(v))         score++;
        if (/[0-9]/.test(v))         score++;
        if (/[^A-Za-z0-9]/.test(v))  score++;

        const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        bar.style.width   = (score * 25) + '%';
        bar.style.background = colors[score] || '';
        label.textContent = score > 0 ? labels[score] : '';
        label.style.color = colors[score] || '';
        checkMatch();
      });
    }

    if (confInput) {
      confInput.addEventListener('input', checkMatch);
    }

    function checkMatch() {
      if (!pwdInput || !confInput || !confInput.value) return;
      matchMsg.classList.remove('hidden');
      if (pwdInput.value === confInput.value) {
        matchMsg.textContent = '✓ Passwords match';
        matchMsg.className = 'text-xs mt-1 text-green-600';
      } else {
        matchMsg.textContent = '✗ Passwords do not match';
        matchMsg.className = 'text-xs mt-1 text-red-600';
      }
    }
  </script>
</body>
</html>
