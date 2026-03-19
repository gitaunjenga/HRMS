<?php
/**
 * HRMS - Forgot Password
 */
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
    exit;
}

$success  = '';
$error    = '';
$devLink  = ''; // shown if mail() not available (local dev)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = sanitize(trim($_POST['email'] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Look up user by email
            $user = fetchOne(
                "SELECT id, username, email FROM users WHERE email = ? AND is_active = 1",
                's', $email
            );

            // Always show generic success to avoid user enumeration
            if ($user) {
                $token   = bin2hex(random_bytes(32)); // 64-char secure token
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

                execute(
                    "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?",
                    'ssi', $token, $expires, (int)$user['id']
                );

                $resetUrl = BASE_URL . '/auth/reset-password.php?token=' . $token;
                $siteName = 'HRMS';
                $subject  = $siteName . ' — Password Reset Request';
                $body     = "Hello " . $user['username'] . ",\r\n\r\n"
                          . "A password reset was requested for your account.\r\n"
                          . "Click the link below to reset your password (expires in 1 hour):\r\n\r\n"
                          . $resetUrl . "\r\n\r\n"
                          . "If you did not request this, ignore this email.\r\n\r\n"
                          . "— " . $siteName . " Team";

                $headers  = "From: noreply@hrms.local\r\n"
                          . "Reply-To: noreply@hrms.local\r\n"
                          . "X-Mailer: PHP/" . phpversion();

                $mailSent = @mail($user['email'], $subject, $body, $headers);

                if ($mailSent) {
                    $success = 'A password reset link has been sent to your email address.';
                } else {
                    // Mail not configured (local/dev) — show link directly
                    $devLink = $resetUrl;
                    $success = 'Reset link generated. Copy the link below (mail server not configured).';
                }
            } else {
                // Still show success to prevent user enumeration
                $success = 'If that email is registered, a reset link has been sent.';
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
  <title>Forgot Password — HRMS</title>
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
      <h1 class="text-3xl font-bold text-white">Forgot Password?</h1>
      <p class="text-indigo-300 mt-1">Enter your email to receive a reset link</p>
    </div>

    <!-- Card -->
    <div class="bg-white rounded-2xl shadow-2xl p-8">

      <?php if ($error): ?>
        <div class="mb-4 flex items-center gap-2 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
          <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
          </svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-green-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-green-800"><?= htmlspecialchars($success) ?></p>
          </div>

          <?php if ($devLink): ?>
            <div class="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
              <p class="text-xs font-semibold text-amber-700 mb-1">Development Mode — Reset Link:</p>
              <a href="<?= htmlspecialchars($devLink) ?>"
                 class="text-xs text-indigo-600 hover:underline break-all">
                <?= htmlspecialchars($devLink) ?>
              </a>
            </div>
          <?php else: ?>
            <div class="mt-4 text-center">
              <a href="<?= BASE_URL ?>/auth/login.php"
                 class="text-sm text-indigo-600 hover:underline font-medium">
                ← Back to Sign In
              </a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!$success): ?>
        <form method="POST" action="" novalidate>
          <?= csrfField() ?>

          <div class="mb-5">
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email Address</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                   placeholder="you@company.com" autocomplete="email" required autofocus>
          </div>

          <button type="submit"
                  class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-4 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Send Reset Link
          </button>

          <div class="mt-5 text-center">
            <a href="<?= BASE_URL ?>/auth/login.php"
               class="text-sm text-indigo-600 hover:underline font-medium">
              ← Back to Sign In
            </a>
          </div>
        </form>
      <?php endif; ?>

    </div>

    <p class="text-center text-indigo-400 text-xs mt-6">&copy; <?= date('Y') ?> HRMS — Human Resource Management System</p>
  </div>

</body>
</html>
