<?php
/**
 * HRMS — One-Click Setup / Installer
 * Run this ONCE to create the database and seed data.
 * DELETE or rename this file after setup is complete.
 */

// ── Config ─────────────────────────────────────────────────────────────────────
$config = [
    'host'   => 'localhost',
    'user'   => 'root',
    'pass'   => '',       // ← change if needed
    'dbname' => 'hrms_db',
];

$errors   = [];
$messages = [];
$done     = false;

// ── Run Setup ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['host']   = trim($_POST['host']   ?? 'localhost');
    $config['user']   = trim($_POST['db_user'] ?? 'root');
    $config['pass']   = $_POST['db_pass']      ?? '';
    $config['dbname'] = trim($_POST['dbname']  ?? 'hrms_db');

    // 1. Connect without selecting a DB
    $conn = @new mysqli($config['host'], $config['user'], $config['pass']);
    if ($conn->connect_error) {
        $errors[] = 'Cannot connect to MySQL: ' . $conn->connect_error;
    } else {
        // 2. Create database
        if (!$conn->query("CREATE DATABASE IF NOT EXISTS `{$config['dbname']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
            $errors[] = 'Failed to create database: ' . $conn->error;
        } else {
            $messages[] = "✅ Database `{$config['dbname']}` created (or already exists).";

            // 3. Select database
            $conn->select_db($config['dbname']);

            // 4. Run SQL file
            $sqlFile = __DIR__ . '/hrms.sql';
            if (!file_exists($sqlFile)) {
                $errors[] = 'hrms.sql not found. Make sure it is in the HRMS root directory.';
            } else {
                $sql = file_get_contents($sqlFile);
                // Remove the CREATE DATABASE and USE lines (already handled above)
                $sql = preg_replace('/^CREATE DATABASE.*?;$/im', '', $sql);
                $sql = preg_replace('/^USE.*?;$/im', '', $sql);

                // Split into individual statements
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                $sqlErrors = 0;
                foreach ($statements as $stmt) {
                    if (empty($stmt) || substr($stmt, 0, 2) === '--') continue;
                    if (!$conn->query($stmt)) {
                        // Ignore duplicate entry errors for seeding
                        if ($conn->errno !== 1062) {
                            $errors[] = "SQL Error [{$conn->errno}]: " . $conn->error . "<br><small>" . htmlspecialchars(substr($stmt, 0, 100)) . "...</small>";
                            $sqlErrors++;
                        }
                    }
                }

                if ($sqlErrors === 0) {
                    $messages[] = '✅ All tables and seed data imported successfully.';
                } else {
                    $messages[] = "⚠️ Import completed with {$sqlErrors} error(s). Check above.";
                }
            }

            // 5. Update config/database.php
            $configFile = __DIR__ . '/config/database.php';
            if (file_exists($configFile)) {
                $configContent = file_get_contents($configFile);
                $configContent = preg_replace("/define\('DB_HOST',\s*'[^']*'\)/", "define('DB_HOST', '{$config['host']}')", $configContent);
                $configContent = preg_replace("/define\('DB_USER',\s*'[^']*'\)/", "define('DB_USER', '{$config['user']}')", $configContent);
                $configContent = preg_replace("/define\('DB_PASS',\s*'[^']*'\)/", "define('DB_PASS', '{$config['pass']}')", $configContent);
                $configContent = preg_replace("/define\('DB_NAME',\s*'[^']*'\)/", "define('DB_NAME', '{$config['dbname']}')", $configContent);
                file_put_contents($configFile, $configContent);
                $messages[] = '✅ config/database.php updated.';
            }

            // 6. Ensure upload directories exist
            $dirs = ['uploads', 'uploads/photos', 'uploads/documents', 'uploads/payslips'];
            foreach ($dirs as $dir) {
                $path = __DIR__ . '/' . $dir;
                if (!is_dir($path)) {
                    mkdir($path, 0755, true);
                }
            }
            $messages[] = '✅ Upload directories verified.';

            if (empty($errors)) {
                $done = true;
            }
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HRMS Setup</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-900 to-purple-900 flex items-center justify-center p-4">
  <div class="w-full max-w-lg">

    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-2xl shadow-lg mb-4">
        <span class="text-3xl font-black text-indigo-600">HR</span>
      </div>
      <h1 class="text-3xl font-bold text-white">HRMS Setup</h1>
      <p class="text-indigo-300 mt-1">One-click database installer</p>
    </div>

    <div class="bg-white rounded-2xl shadow-2xl p-8">

      <?php if ($done): ?>
        <!-- Success -->
        <div class="text-center py-4">
          <div class="text-6xl mb-4">🎉</div>
          <h2 class="text-2xl font-bold text-green-700 mb-2">Setup Complete!</h2>
          <p class="text-gray-600 mb-6">Your HRMS is ready to use.</p>

          <div class="mb-6 text-left space-y-2">
            <?php foreach ($messages as $msg): ?>
              <div class="flex items-start gap-2 p-2 bg-green-50 rounded text-sm text-green-800"><?= $msg ?></div>
            <?php endforeach; ?>
          </div>

          <div class="p-4 bg-indigo-50 rounded-xl text-sm text-indigo-800 mb-6 text-left">
            <p class="font-semibold mb-2">Default Login Credentials:</p>
            <div class="space-y-1">
              <div class="flex justify-between"><span>Admin:</span><span class="font-mono">admin / Admin@1234</span></div>
              <div class="flex justify-between"><span>HR Manager:</span><span class="font-mono">sarah.hr / Admin@1234</span></div>
              <div class="flex justify-between"><span>Employee:</span><span class="font-mono">john.emp / Admin@1234</span></div>
            </div>
          </div>

          <div class="p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 mb-6">
            ⚠️ <strong>Security:</strong> Delete <code>setup.php</code> after setup!
          </div>

          <a href="index.php" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
            Open HRMS →
          </a>
        </div>

      <?php else: ?>

        <?php if ($errors): ?>
          <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="font-semibold text-red-700 mb-2">Setup Errors:</p>
            <?php foreach ($errors as $e): ?>
              <p class="text-sm text-red-600">• <?= $e ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($messages && !$done): ?>
          <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
            <?php foreach ($messages as $m): ?>
              <p class="text-sm text-green-700"><?= $m ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <h2 class="text-xl font-bold text-gray-800 mb-1">Database Configuration</h2>
        <p class="text-sm text-gray-500 mb-6">Enter your MySQL credentials to set up the HRMS database.</p>

        <form method="POST">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">MySQL Host</label>
              <input type="text" name="host" value="<?= htmlspecialchars($_POST['host'] ?? 'localhost') ?>"
                     class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">MySQL Username</label>
              <input type="text" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>"
                     class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">MySQL Password</label>
              <input type="password" name="db_pass" placeholder="Leave blank if no password"
                     class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Database Name</label>
              <input type="text" name="dbname" value="<?= htmlspecialchars($_POST['dbname'] ?? 'hrms_db') ?>"
                     class="w-full rounded-xl border border-gray-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>
          </div>

          <button type="submit"
                  class="w-full mt-6 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 px-4 rounded-xl transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            Install HRMS Database
          </button>
        </form>

        <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs text-yellow-700">
          <strong>Tip:</strong> For XAMPP on Windows, default user is <code>root</code> with no password.
          For MAMP/Linux, check your MySQL settings.
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
