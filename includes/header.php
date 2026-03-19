<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'HRMS') ?> — HRMS</title>

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // Apply saved theme before paint to avoid flash
    (function () {
      const t = localStorage.getItem('hrms_theme');
      if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
      }
    })();
  </script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            indigo: {
              50:  '#edf7f6',
              100: '#cceeed',
              200: '#99deda',
              300: '#66cec6',
              400: '#40c2ba',
              500: '#2EC4B6',
              600: '#0B2545',
              700: '#1a3a6b',
              800: '#0B2545',
              900: '#071629',
            },
            purple: {
              800: '#0B2545',
              900: '#071629',
            }
          }
        }
      }
    }
  </script>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

  <!-- Alpine.js -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js"></script>

  <!-- Custom Styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='26'>👥</text></svg>">
</head>
<body class="bg-gray-50 text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
