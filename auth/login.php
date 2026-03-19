<?php
/**
 * HRMS - Login Page
 */
require_once __DIR__ . '/../includes/functions.php';
startSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter your username and password.';
        } else {
            $user = fetchOne(
                "SELECT u.*, e.photo FROM users u LEFT JOIN employees e ON u.employee_id = e.id
                 WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1",
                'ss', $username, $username
            );
            // Note: totp_enabled and totp_confirmed come from u.* above

            if ($user && password_verify($password, $user['password'])) {
                // Check 2FA before completing login
                if (!empty($user['totp_enabled']) && !empty($user['totp_confirmed'])) {
                    $_SESSION['2fa_pending_user_id'] = $user['id'];
                    header('Location: ' . BASE_URL . '/auth/2fa-verify.php');
                    exit;
                }

                // Set session
                // Resolve department for Head of Department — use their own employee's department_id
                $deptId = null;
                if ($user['role'] === 'Head of Department' && $user['employee_id']) {
                    $emp = fetchOne(
                        "SELECT department_id FROM employees WHERE id = ?",
                        'i', (int)$user['employee_id']
                    );
                    $deptId = ($emp && $emp['department_id']) ? (int)$emp['department_id'] : null;
                }

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'id'          => $user['id'],
                    'username'    => $user['username'],
                    'email'       => $user['email'],
                    'role'        => $user['role'],
                    'employee_id' => $user['employee_id'],
                    'photo'       => $user['photo'],
                    'dept_id'     => $deptId,
                ];
                $_SESSION['must_change_password'] = !empty($user['must_change_password']);

                // Update last login
                execute("UPDATE users SET last_login = NOW() WHERE id = ?", 'i', $user['id']);

                if (!empty($user['must_change_password'])) {
                    header('Location: ' . BASE_URL . '/modules/settings/force-change-password.php');
                } else {
                    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
                }
                exit;
            } else {
                $error = 'Invalid username/email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — HRMS</title>
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
<body class="min-h-screen relative flex items-center justify-center p-6">

  <!-- ── Full-page background image ───────────────────────────────────────── -->
  <img src="https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1920&q=80"
       alt="Corporate office"
       class="absolute inset-0 w-full h-full object-cover z-0">
  <!-- Navy gradient overlay -->
  <div class="absolute inset-0 z-0" style="background: linear-gradient(135deg, rgba(11,37,69,0.92) 0%, rgba(11,37,69,0.78) 60%, rgba(46,196,182,0.22) 100%);"></div>

  <!-- ── Centred login card ────────────────────────────────────────────────── -->
  <div class="relative z-10 w-full max-w-md">

    <!-- Logo -->
    <div class="flex items-center justify-center gap-3 mb-6">
      <div class="w-11 h-11 bg-white rounded-xl flex items-center justify-center shadow-lg">
        <span class="text-lg font-black" style="color:#0B2545;">HR</span>
      </div>
      <span class="text-white text-2xl font-bold tracking-wide">HRMS</span>
    </div>

    <!-- Heading -->
    <div class="text-center mb-6">
      <h2 class="text-3xl font-bold text-white">Welcome back</h2>
      <p class="text-white/60 text-sm mt-1">Sign in to your account to continue</p>
    </div>

    <!-- Glassmorphism card -->
    <div class="rounded-2xl border border-white/15 p-8" style="background: rgba(11,37,69,0.55); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);">

      <?php $flash = getFlash(); if ($flash && $flash['type'] === 'success'): ?>
        <div class="mb-5 flex items-center gap-2 p-3 rounded-lg text-sm" style="background:rgba(4,120,87,0.4); border:1px solid rgba(52,211,153,0.3); color:#6ee7b7;">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="mb-5 flex items-center gap-2 p-3 rounded-lg text-sm" style="background:rgba(127,29,29,0.4); border:1px solid rgba(252,165,165,0.3); color:#fca5a5;">
          <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <?= csrfField() ?>

        <div class="mb-5">
          <label class="block text-sm font-medium text-white/80 mb-1.5">Username or Email</label>
          <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 class="w-full rounded-lg px-4 py-2.5 text-sm text-white placeholder-white/40 focus:outline-none focus:ring-2 transition"
                 style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); focus:ring-color:#2EC4B6;"
                 placeholder="Enter your username or email" autocomplete="username" required
                 onfocus="this.style.borderColor='#2EC4B6'" onblur="this.style.borderColor='rgba(255,255,255,0.2)'">
        </div>

        <div class="mb-7">
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-sm font-medium text-white/80">Password</label>
            <a href="<?= BASE_URL ?>/auth/forgot-password.php"
               class="text-xs font-medium hover:underline" style="color:#2EC4B6;">
              Forgot password?
            </a>
          </div>
          <div class="relative">
            <input type="password" name="password" id="password"
                   class="w-full rounded-lg px-4 py-2.5 text-sm text-white placeholder-white/40 focus:outline-none transition pr-10"
                   style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2);"
                   placeholder="••••••••" autocomplete="current-password" required
                   onfocus="this.style.borderColor='#2EC4B6'" onblur="this.style.borderColor='rgba(255,255,255,0.2)'">
            <button type="button" onclick="togglePwd()" class="absolute inset-y-0 right-3 flex items-center text-white/40 hover:text-white/80">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>

        <button type="submit"
                class="w-full font-semibold py-2.5 px-4 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 text-white"
                style="background:#2EC4B6;"
                onmouseover="this.style.background='#26a99d'" onmouseout="this.style.background='#2EC4B6'">
          Sign In
        </button>
      </form>

    </div>

    <p class="text-center text-white/30 text-xs mt-6">&copy; <?= date('Y') ?> HRMS — Human Resource Management System</p>
  </div>

  <script>
    function togglePwd() {
      const inp = document.getElementById('password');
      inp.type = inp.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>
