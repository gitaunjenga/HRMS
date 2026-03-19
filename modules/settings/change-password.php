<?php
/**
 * HRMS - Change Password (logged-in user)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$pageTitle = 'Change Password';
$errors    = [];
$success   = false;
$user      = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Fetch the stored hash
        $dbUser = fetchOne("SELECT password FROM users WHERE id = ?", 'i', (int)$user['id']);

        if (!$dbUser || !password_verify($currentPassword, $dbUser['password'])) {
            $errors[] = 'Your current password is incorrect.';
        }
        if (strlen($newPassword) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'New password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'New password must contain at least one number.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'New password and confirmation do not match.';
        }
        if (empty($errors) && password_verify($newPassword, $dbUser['password'])) {
            $errors[] = 'New password must be different from your current password.';
        }

        if (empty($errors)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            execute(
                "UPDATE users SET password = ? WHERE id = ?",
                'si', $hashed, (int)$user['id']
            );
            $success = true;
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6">

    <!-- Breadcrumb -->
    <nav class="text-sm text-gray-500 mb-5 flex items-center gap-2">
      <a href="<?= BASE_URL ?>/modules/dashboard/index.php" class="hover:text-indigo-600 transition-colors">Dashboard</a>
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
      </svg>
      <span class="text-gray-900 font-medium">Change Password</span>
    </nav>

    <div class="max-w-lg">

      <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-6 flex flex-col items-center text-center gap-4">
          <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center">
            <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-base font-semibold text-green-800 mb-1">Password Changed</h3>
            <p class="text-sm text-green-700">Your password has been updated successfully.</p>
          </div>
          <a href="<?= BASE_URL ?>/modules/dashboard/index.php"
             class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
            Back to Dashboard
          </a>
        </div>

      <?php else: ?>

        <?php if (!empty($errors)): ?>
          <div class="mb-5 bg-red-50 border border-red-200 rounded-xl p-4">
            <ul class="list-disc list-inside space-y-1">
              <?php foreach ($errors as $e): ?>
                <li class="text-sm text-red-700"><?= sanitize($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
          <div class="flex items-center gap-3 mb-6 pb-5 border-b border-gray-100">
            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
              <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
            </div>
            <div>
              <h2 class="text-base font-semibold text-gray-900">Update Password</h2>
              <p class="text-xs text-gray-500">Signed in as <span class="font-medium"><?= sanitize($user['username']) ?></span></p>
            </div>
          </div>

          <form method="POST" action="" novalidate>
            <?= csrfField() ?>

            <!-- Current Password -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Current Password</label>
              <div class="relative">
                <input type="password" name="current_password" id="current-pwd" required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                       placeholder="Your current password" autocomplete="current-password">
                <button type="button" onclick="toggleField('current-pwd')"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- New Password -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">New Password</label>
              <div class="relative">
                <input type="password" name="new_password" id="new-pwd" required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                       placeholder="Min. 8 chars, 1 uppercase, 1 number" autocomplete="new-password">
                <button type="button" onclick="toggleField('new-pwd')"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
              </div>
              <!-- Strength meter -->
              <div class="mt-2 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                <div id="strength-bar" class="h-full rounded-full transition-all duration-300" style="width:0"></div>
              </div>
              <p id="strength-label" class="text-xs text-gray-400 mt-1 h-4"></p>
            </div>

            <!-- Confirm New Password -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm New Password</label>
              <div class="relative">
                <input type="password" name="confirm_password" id="confirm-pwd" required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm pr-10 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                       placeholder="Repeat new password" autocomplete="new-password">
                <button type="button" onclick="toggleField('confirm-pwd')"
                        class="absolute inset-y-0 right-3 flex items-center text-gray-400 hover:text-gray-600">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
              </div>
              <p id="match-msg" class="text-xs mt-1 h-4"></p>
            </div>

            <div class="flex items-center gap-3">
              <button type="submit"
                      class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                Update Password
              </button>
              <a href="<?= BASE_URL ?>/modules/dashboard/index.php"
                 class="px-5 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                Cancel
              </a>
            </div>
          </form>
        </div>

        <!-- Requirements hint -->
        <div class="mt-4 p-4 bg-indigo-50 border border-indigo-100 rounded-xl">
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

      <?php endif; ?>

    </div>
  </main>
</div>

<script>
function toggleField(id) {
  const inp = document.getElementById(id);
  if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
}

const newPwd    = document.getElementById('new-pwd');
const confPwd   = document.getElementById('confirm-pwd');
const bar       = document.getElementById('strength-bar');
const strLabel  = document.getElementById('strength-label');
const matchMsg  = document.getElementById('match-msg');

if (newPwd) {
  newPwd.addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)           score++;
    if (/[A-Z]/.test(v))         score++;
    if (/[0-9]/.test(v))         score++;
    if (/[^A-Za-z0-9]/.test(v))  score++;

    const colors = ['', '#ef4444', '#f97316', '#eab308', '#22c55e'];
    const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
    bar.style.width      = (score * 25) + '%';
    bar.style.background = colors[score] || '';
    strLabel.textContent = score > 0 ? labels[score] : '';
    strLabel.style.color = colors[score] || '';
    updateMatch();
  });
}

if (confPwd) {
  confPwd.addEventListener('input', updateMatch);
}

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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
