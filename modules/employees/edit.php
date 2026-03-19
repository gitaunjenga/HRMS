<?php
/**
 * HRMS - Edit Employee
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('edit', 'employees');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid employee ID.');
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
    exit;
}

$employee = fetchOne(
    "SELECT e.*, d.name AS department_name
     FROM employees e
     LEFT JOIN departments d ON d.id = e.department_id
     WHERE e.id = ?",
    'i', $id
);
if (!$employee) {
    setFlash('error', 'Employee not found.');
    header('Location: ' . BASE_URL . '/modules/employees/index.php');
    exit;
}

$pageTitle = 'Edit Employee — ' . $employee['first_name'] . ' ' . $employee['last_name'];
$errors    = [];
$input     = $employee; // pre-populate from DB

$departments = fetchAll("SELECT id, name FROM departments ORDER BY name ASC");

// Fetch linked user account
$linkedUser = fetchOne("SELECT id, username, role, is_active FROM users WHERE employee_id = ?", 'i', $id);
$validRoles = ['Employee', 'Head of Department', 'HR Manager', 'Admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CSRF ──────────────────────────────────────────────────────────────────
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    // ── Collect & sanitize ────────────────────────────────────────────────────
    $input = [
        'employee_id'              => sanitize($_POST['employee_id']              ?? ''),
        'first_name'               => sanitize($_POST['first_name']               ?? ''),
        'last_name'                => sanitize($_POST['last_name']                ?? ''),
        'email'                    => sanitize($_POST['email']                    ?? ''),
        'phone'                    => sanitize($_POST['phone']                    ?? ''),
        'date_of_birth'            => sanitize($_POST['date_of_birth']            ?? ''),
        'gender'                   => sanitize($_POST['gender']                   ?? ''),
        'department_id'            => (int)($_POST['department_id']               ?? 0),
        'position'                 => sanitize($_POST['position']                 ?? ''),
        'employment_type'          => sanitize($_POST['employment_type']          ?? 'Full-Time'),
        'employment_status'        => sanitize($_POST['employment_status']        ?? 'Active'),
        'hire_date'                => sanitize($_POST['hire_date']                ?? ''),
        'salary'                   => hasRole('Admin') ? (float)$employee['salary'] : (float)($_POST['salary'] ?? 0),
        'address'                  => sanitize($_POST['address']                  ?? ''),
        'city'                     => sanitize($_POST['city']                     ?? ''),
        'state'                    => sanitize($_POST['state']                    ?? ''),
        'country'                  => sanitize($_POST['country']                  ?? ''),
        'postal_code'              => sanitize($_POST['postal_code']              ?? ''),
        'emergency_contact_name'   => sanitize($_POST['emergency_contact_name']   ?? ''),
        'emergency_contact_phone'  => sanitize($_POST['emergency_contact_phone']  ?? ''),
        'bank_name'                => sanitize($_POST['bank_name']                ?? ''),
        'bank_account'             => sanitize($_POST['bank_account']             ?? ''),
        'tax_id'                   => sanitize($_POST['tax_id']                   ?? ''),
        'photo'                    => $employee['photo'], // keep existing unless replaced
    ];

    // ── Validation ────────────────────────────────────────────────────────────
    if (empty($input['first_name']))   $errors[] = 'First name is required.';
    if (empty($input['last_name']))    $errors[] = 'Last name is required.';
    if (empty($input['email']))        $errors[] = 'Email is required.';
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (empty($input['employee_id']))  $errors[] = 'Employee ID is required.';
    if (empty($input['hire_date']))    $errors[] = 'Hire date is required.';
    if ($input['department_id'] === 0) $errors[] = 'Please select a department.';
    if (empty($input['position']))     $errors[] = 'Position is required.';

    // Unique checks (exclude current record)
    if (empty($errors)) {
        $existEmail = fetchOne("SELECT id FROM employees WHERE email = ? AND id != ?", 'si', $input['email'], $id);
        if ($existEmail) $errors[] = 'Another employee already uses this email address.';

        $existEmpId = fetchOne("SELECT id FROM employees WHERE employee_id = ? AND id != ?", 'si', $input['employee_id'], $id);
        if ($existEmpId) $errors[] = 'This Employee ID is already in use.';
    }

    // ── Photo upload ──────────────────────────────────────────────────────────
    if (!empty($_FILES['photo']['name'])) {
        $upload = uploadFile($_FILES['photo'], PHOTO_DIR, ['jpg', 'jpeg', 'png', 'webp']);
        if ($upload['success']) {
            // Remove old photo if exists
            if ($employee['photo'] && file_exists(PHOTO_DIR . $employee['photo'])) {
                @unlink(PHOTO_DIR . $employee['photo']);
            }
            $input['photo'] = $upload['filename'];
        } else {
            $errors[] = 'Photo: ' . $upload['message'];
        }
    }

    // ── Update ────────────────────────────────────────────────────────────────
    if (empty($errors)) {
        $ok = execute(
            "UPDATE employees SET
                employee_id = ?, first_name = ?, last_name = ?, email = ?, phone = ?,
                date_of_birth = ?, gender = ?, department_id = ?, position = ?,
                employment_type = ?, employment_status = ?, hire_date = ?, salary = ?,
                photo = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ?,
                emergency_contact_name = ?, emergency_contact_phone = ?,
                bank_name = ?, bank_account = ?, tax_id = ?
             WHERE id = ?",
            'sssssssissssdsssssssssssi',
            $input['employee_id'],
            $input['first_name'],
            $input['last_name'],
            $input['email'],
            $input['phone'],
            $input['date_of_birth'] ?: null,
            $input['gender'] ?: null,
            $input['department_id'],
            $input['position'],
            $input['employment_type'],
            $input['employment_status'],
            $input['hire_date'],
            $input['salary'],
            $input['photo'],
            $input['address'],
            $input['city'],
            $input['state'],
            $input['country'],
            $input['postal_code'],
            $input['emergency_contact_name'],
            $input['emergency_contact_phone'],
            $input['bank_name'],
            $input['bank_account'],
            $input['tax_id'],
            $id
        );

        // Sync email in users table
        execute("UPDATE users SET email = ? WHERE employee_id = ?", 'si', $input['email'], $id);

        // Admin can also update role and account status
        if (hasRole('Admin') && $linkedUser) {
            $newRole     = sanitize($_POST['user_role']      ?? $linkedUser['role']);
            $newActive   = isset($_POST['user_is_active']) ? 1 : 0;
            if (!in_array($newRole, $validRoles)) $newRole = $linkedUser['role'];
            // Prevent admin from removing their own admin role
            $currentUser = currentUser();
            if ((int)$linkedUser['id'] === (int)$currentUser['id'] && $newRole !== 'Admin') {
                $newRole = 'Admin'; // silent guard
            }
            execute(
                "UPDATE users SET role = ?, is_active = ? WHERE id = ?",
                'sii', $newRole, $newActive, (int)$linkedUser['id']
            );
        }

        setFlash('success', 'Employee updated successfully.');
        header('Location: ' . BASE_URL . '/modules/employees/view.php?id=' . $id);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/navbar.php';
?>

<main class="ml-64 p-6 min-h-screen bg-gray-50">

  <!-- Breadcrumb -->
  <nav class="text-sm text-gray-500 mb-4 flex items-center gap-2">
    <a href="<?= BASE_URL ?>/modules/employees/index.php" class="hover:text-indigo-600 transition-colors">Employees</a>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <a href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= $id ?>" class="hover:text-indigo-600 transition-colors">
      <?= sanitize($employee['first_name']) ?> <?= sanitize($employee['last_name']) ?>
    </a>
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    <span class="text-gray-900 font-medium">Edit</span>
  </nav>

  <!-- Error summary -->
  <?php if (!empty($errors)): ?>
    <div class="mb-5 bg-red-50 border border-red-200 rounded-lg p-4">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
          <p class="text-sm font-semibold text-red-800 mb-1">Please fix the following errors:</p>
          <ul class="list-disc list-inside text-sm text-red-700 space-y-0.5">
            <?php foreach ($errors as $e): ?>
              <li><?= sanitize($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" novalidate>
    <?= csrfField() ?>

    <!-- ── Personal Information ─────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <h3 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold">1</span>
        Personal Information
      </h3>

      <!-- Photo -->
      <div class="mb-6 flex items-center gap-5">
        <div class="flex items-center gap-4">
          <div class="w-20 h-20 rounded-full overflow-hidden border-2 border-gray-200">
            <img id="photo-preview" src="<?= avatarUrl($input['photo'] ?? null) ?>" alt="Photo"
                 class="w-full h-full object-cover">
          </div>
          <div>
            <label class="cursor-pointer inline-flex items-center gap-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
              Change Photo
              <input type="file" name="photo" accept="image/*" class="sr-only"
                     onchange="previewPhoto(this)">
            </label>
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP — max 5MB</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
          <input type="text" name="first_name" value="<?= htmlspecialchars($input['first_name'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($input['last_name'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Employee ID <span class="text-red-500">*</span></label>
          <input type="text" name="employee_id" value="<?= htmlspecialchars($input['employee_id'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
          <input type="email" name="email" value="<?= htmlspecialchars($input['email'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($input['phone'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
          <input type="date" name="date_of_birth" value="<?= htmlspecialchars($input['date_of_birth'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Gender</label>
          <select name="gender"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <option value="">Select gender</option>
            <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
              <option value="<?= $g ?>" <?= ($input['gender'] ?? '') === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- ── Employment Information ───────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <h3 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold">2</span>
        Employment Information
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
          <select name="department_id"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
            <option value="">Select department</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>" <?= ($input['department_id'] ?? 0) == $d['id'] ? 'selected' : '' ?>>
                <?= sanitize($d['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Position / Job Title <span class="text-red-500">*</span></label>
          <input type="text" name="position" value="<?= htmlspecialchars($input['position'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Employment Type</label>
          <select name="employment_type"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php foreach (['Full-Time', 'Part-Time', 'Contract', 'Internship'] as $et): ?>
              <option value="<?= $et ?>" <?= ($input['employment_type'] ?? 'Full-Time') === $et ? 'selected' : '' ?>><?= $et ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
          <select name="employment_status"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php foreach (['Active', 'Inactive', 'On Leave', 'Terminated'] as $es): ?>
              <option value="<?= $es ?>" <?= ($input['employment_status'] ?? 'Active') === $es ? 'selected' : '' ?>><?= $es ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Hire Date <span class="text-red-500">*</span></label>
          <input type="date" name="hire_date" value="<?= htmlspecialchars($input['hire_date'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
        </div>
        <?php if (!hasRole('Admin')): ?>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Salary</label>
          <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">KES</span>
            <input type="number" name="salary" min="0" step="0.01"
                   value="<?= htmlspecialchars($input['salary'] ?? '0') ?>"
                   class="w-full pl-7 pr-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Address ──────────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <h3 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold">3</span>
        Address
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Street Address</label>
          <input type="text" name="address" value="<?= htmlspecialchars($input['address'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
          <input type="text" name="city" value="<?= htmlspecialchars($input['city'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">State / Province</label>
          <input type="text" name="state" value="<?= htmlspecialchars($input['state'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Country</label>
          <input type="text" name="country" value="<?= htmlspecialchars($input['country'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code</label>
          <input type="text" name="postal_code" value="<?= htmlspecialchars($input['postal_code'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
      </div>
    </div>

    <!-- ── Emergency Contact ────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <h3 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold">4</span>
        Emergency Contact
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contact Name</label>
          <input type="text" name="emergency_contact_name"
                 value="<?= htmlspecialchars($input['emergency_contact_name'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contact Phone</label>
          <input type="tel" name="emergency_contact_phone"
                 value="<?= htmlspecialchars($input['emergency_contact_phone'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
      </div>
    </div>

    <!-- ── Financial Information ─────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <h3 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold">5</span>
        Financial Information
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
          <input type="text" name="bank_name" value="<?= htmlspecialchars($input['bank_name'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Bank Account Number</label>
          <input type="text" name="bank_account" value="<?= htmlspecialchars($input['bank_account'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Tax ID</label>
          <input type="text" name="tax_id" value="<?= htmlspecialchars($input['tax_id'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
      </div>
    </div>

    <!-- ── Account Access (Admin only) ─────────────────────────────────────── -->
    <?php if (hasRole('Admin') && $linkedUser): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <h3 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
        </svg>
        Account &amp; Role Management
      </h3>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        <!-- Username (read-only) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <input type="text" value="<?= htmlspecialchars($linkedUser['username']) ?>" disabled
                 class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
          <p class="text-xs text-gray-400 mt-1">Username cannot be changed here.</p>
        </div>

        <!-- Role -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            System Role <span class="text-red-500">*</span>
          </label>
          <?php
          $currentViewedRole = $linkedUser['role'];
          $isSelf = (int)$linkedUser['id'] === (int)(currentUser()['id'] ?? 0);
          ?>
          <?php if ($isSelf): ?>
            <input type="hidden" name="user_role" value="Admin">
            <input type="text" value="Admin" disabled
                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 text-gray-500 cursor-not-allowed">
            <p class="text-xs text-amber-600 mt-1">You cannot change your own role.</p>
          <?php else: ?>
            <select name="user_role"
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
              <?php foreach ($validRoles as $r): ?>
                <option value="<?= $r ?>" <?= $currentViewedRole === $r ? 'selected' : '' ?>><?= $r ?></option>
              <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Changing role takes effect on next login.</p>
          <?php endif; ?>
        </div>

        <!-- Account Status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Account Status</label>
          <div class="flex items-center gap-3 mt-2.5">
            <label class="relative inline-flex items-center cursor-pointer <?= $isSelf ? 'opacity-50 pointer-events-none' : '' ?>">
              <input type="checkbox" name="user_is_active" value="1"
                     <?= $linkedUser['is_active'] ? 'checked' : '' ?>
                     <?= $isSelf ? 'disabled' : '' ?>
                     class="sr-only peer">
              <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer
                          peer-checked:after:translate-x-full peer-checked:after:border-white after:content-['']
                          after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300
                          after:border after:rounded-full after:h-5 after:w-5 after:transition-all
                          peer-checked:bg-green-500"></div>
              <span class="ml-2 text-sm text-gray-700">Active</span>
            </label>
          </div>
          <?php if ($isSelf): ?>
            <p class="text-xs text-amber-600 mt-1">You cannot deactivate your own account.</p>
          <?php else: ?>
            <p class="text-xs text-gray-400 mt-1">Inactive users cannot log in.</p>
          <?php endif; ?>
        </div>

      </div>

      <!-- Role permission summary -->
      <div class="mt-5 grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php
        $roleCards = [
            'Employee'           => ['bg-slate-50 border-slate-200',   'text-slate-700',  'Own records, leave, attendance'],
            'Department Manager' => ['bg-blue-50 border-blue-200',     'text-blue-700',   'Dept leave approval, team views'],
            'HR Manager'         => ['bg-emerald-50 border-emerald-200','text-emerald-700','Payroll, recruitment, HR approval'],
            'Admin'              => ['bg-red-50 border-red-200',        'text-red-700',    'System admin, user management'],
        ];
        foreach ($roleCards as $r => [$bg, $txt, $desc]):
        ?>
          <div class="rounded-lg p-3 border <?= $bg ?> <?= $currentViewedRole === $r ? 'ring-2 ring-offset-1 ring-indigo-400' : 'opacity-50' ?>">
            <p class="text-xs font-semibold <?= $txt ?>"><?= $r ?></p>
            <p class="text-xs mt-0.5 text-gray-500"><?= $desc ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Actions ────────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-end gap-3 mb-8">
      <a href="<?= BASE_URL ?>/modules/employees/view.php?id=<?= $id ?>"
         class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
        Cancel
      </a>
      <button type="submit"
              class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors">
        Save Changes
      </button>
    </div>
  </form>
</main>

<script>
function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('photo-preview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
