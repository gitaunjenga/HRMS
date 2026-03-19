<?php
/**
 * HRMS - Forced Password Change (first login)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

// If not required to change, go to dashboard
if (empty($_SESSION['must_change_password'])) {
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$errors = [];
$user   = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (strlen($newPassword) < 8)             $errors[] = 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $newPassword))  $errors[] = 'Password must contain at least one uppercase letter.';
        if (!preg_match('/[0-9]/', $newPassword))  $errors[] = 'Password must contain at least one number.';
        if ($newPassword !== $confirmPassword)     $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            execute(
                "UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?",
                'si', $hashed, (int)$user['id']
            );
            $_SESSION['must_change_password'] = false;

            setFlash('success', 'Password updated successfully. Welcome to HRMS!');
            header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
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
  <title>Set Your Password — HRMS</title>
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
      <h1 class="text-2xl font-bold text-white">Set Your Password</h1>
      <p class="text-indigo-300 mt-1">You must set a new password before continuing.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-8">

      <!-- Notice banner -->
      <div class="mb-6 flex items-start gap-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
        <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-amber-800">For your security, please choose a new password. Your temporary password will no longer work after this.</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4">
          <ul class="list-disc list-inside space-y-1">
            <?php foreach ($errors as $e): ?>
              <li class="text-sm text-red-700"><?= sanitize($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <!-- New Password -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
          <div class="relative">
            <input type="password" name="new_password" id="new-pwd" required
                   class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                   placeholder="Min. 8 chars, 1 uppercase, 1 number" autocomplete="new-password">
            <button type="button" onclick="toggleField('new-pwd')"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
          <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
            <div id="strength-bar" class="h-full rounded-full transition-all duration-300" style="width:0"></div>
          </div>
          <p id="strength-label" class="text-xs text-gray-400 mt-1 h-4"></p>
        </div>

        <!-- Confirm Password -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm New Password</label>
          <div class="relative">
            <input type="password" name="confirm_password" id="confirm-pwd" required
                   class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                   placeholder="Repeat new password" autocomplete="new-password">
            <button type="button" onclick="toggleField('confirm-pwd')"
                    class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
          <p id="match-msg" class="text-xs mt-1 h-4"></p>
        </div>

        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
          Set Password &amp; Continue
        </button>
      </form>

      <div class="mt-5 p-4 bg-indigo-50 rounded-xl border border-indigo-100">
        <p class="text-xs font-semibold text-indigo-700 mb-2">Password Requirements</p>
        <ul class="space-y-1 text-xs text-indigo-600">
          <li class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
            At least 8 characters
          </li>
          <li class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
            At least one uppercase letter (A–Z)
          </li>
          <li class="flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>
            At least one number (0–9)
          </li>
        </ul>
      </div>
    </div>

    <p class="text-center text-indigo-400 text-xs mt-6">&copy; <?= date('Y') ?> HRMS — Human Resource Management System</p>
  </div>

<script>
function toggleField(id) {
  const inp = document.getElementById(id);
  if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
}
const newPwd = document.getElementById('new-pwd');
const confPwd = document.getElementById('confirm-pwd');
const bar = document.getElementById('strength-bar');
const strLabel = document.getElementById('strength-label');
const matchMsg = document.getElementById('match-msg');
if (newPwd) {
  newPwd.addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    bar.style.width = (score * 25) + '%';
    bar.style.background = colors[score] || '';
    strLabel.textContent = score > 0 ? labels[score] : '';
    strLabel.style.color = colors[score] || '';
    updateMatch();
  });
}
if (confPwd) confPwd.addEventListener('input', updateMatch);
function updateMatch() {
  if (!newPwd || !confPwd || !confPwd.value) { matchMsg.textContent = ''; return; }
  if (newPwd.value === confPwd.value) {
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
