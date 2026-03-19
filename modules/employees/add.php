<?php
/**
 * HRMS - Add Employee
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('create', 'employees');

$pageTitle = 'Add Employee';

$errors = [];
$input  = [];

// Pre-generate employee ID
$nextEmpId = generateEmployeeId();

// Departments
$departments = fetchAll("SELECT id, name FROM departments ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CSRF ──────────────────────────────────────────────────────────────────
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    // ── Collect & sanitize ────────────────────────────────────────────────────
    $tempPassword = $_POST['temp_password'] ?? '';
    $selectedRole = sanitize($_POST['role'] ?? 'Employee');
    $validRoles   = ['Employee', 'Department Manager', 'HR Manager', 'Admin'];
    if (!in_array($selectedRole, $validRoles)) $selectedRole = 'Employee';

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
        'hire_date'                => sanitize($_POST['hire_date']                ?? ''),
        'salary'                   => (float)($_POST['salary']                    ?? 0),
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
    if (empty($tempPassword))          $errors[] = 'Temporary password is required.';
    elseif (strlen($tempPassword) < 8) $errors[] = 'Temporary password must be at least 8 characters.';

    // Unique checks
    if (empty($errors)) {
        $existEmail = fetchOne("SELECT id FROM employees WHERE email = ?", 's', $input['email']);
        if ($existEmail) $errors[] = 'An employee with this email already exists.';

        $existId = fetchOne("SELECT id FROM employees WHERE employee_id = ?", 's', $input['employee_id']);
        if ($existId) $errors[] = 'This Employee ID is already in use.';
    }

    // ── Photo upload ──────────────────────────────────────────────────────────
    $photoFilename = null;
    if (!empty($_FILES['photo']['name'])) {
        $upload = uploadFile($_FILES['photo'], PHOTO_DIR, ['jpg', 'jpeg', 'png', 'webp']);
        if ($upload['success']) {
            $photoFilename = $upload['filename'];
        } else {
            $errors[] = 'Photo: ' . $upload['message'];
        }
    }

    // ── Insert ────────────────────────────────────────────────────────────────
    if (empty($errors)) {
        $newId = execute(
            "INSERT INTO employees
                (employee_id, first_name, last_name, email, phone, date_of_birth, gender,
                 department_id, position, employment_type, hire_date, salary, photo,
                 address, city, state, country, postal_code,
                 emergency_contact_name, emergency_contact_phone,
                 bank_name, bank_account, tax_id, employment_status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Active')",
            'sssssssisssdsssssssssss',
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
            $input['hire_date'],
            $input['salary'],
            $photoFilename,
            $input['address'],
            $input['city'],
            $input['state'],
            $input['country'],
            $input['postal_code'],
            $input['emergency_contact_name'],
            $input['emergency_contact_phone'],
            $input['bank_name'],
            $input['bank_account'],
            $input['tax_id']
        );

        if ($newId) {
            // Create user account
            $username = strtolower(explode('@', $input['email'])[0]);
            // Ensure username is unique
            $base = $username;
            $suffix = 1;
            while (fetchOne("SELECT id FROM users WHERE username = ?", 's', $username)) {
                $username = $base . $suffix++;
            }
            $hashedPass = password_hash($tempPassword, PASSWORD_DEFAULT);
            execute(
                "INSERT INTO users (employee_id, username, email, password, role, is_active, must_change_password)
                 VALUES (?, ?, ?, ?, ?, 1, 1)",
                'issss',
                (int)$newId,
                $username,
                $input['email'],
                $hashedPass,
                $selectedRole
            );

            setFlash('success', 'Employee ' . $input['first_name'] . ' ' . $input['last_name'] . ' added successfully. Temporary password: ' . htmlspecialchars($tempPassword));
            header('Location: ' . BASE_URL . '/modules/employees/index.php');
            exit;
        } else {
            $errors[] = 'Failed to save employee. Please try again.';
        }
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
    <span class="text-gray-900 font-medium">Add Employee</span>
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
        <div x-data="{ preview: null }" class="flex items-center gap-4">
          <div class="w-20 h-20 rounded-full bg-gray-100 border-2 border-dashed border-gray-300 overflow-hidden flex items-center justify-center" id="photo-preview-wrap">
            <img id="photo-preview" src="<?= BASE_URL ?>/assets/images/default-avatar.png" alt="Preview"
                 class="w-full h-full object-cover hidden">
            <svg id="photo-placeholder" class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0M12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
          </div>
          <div>
            <label class="cursor-pointer inline-flex items-center gap-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
              Upload Photo
              <input type="file" name="photo" accept="image/*" class="sr-only"
                     onchange="previewPhoto(this)">
            </label>
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP — max 5MB</p>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <!-- First name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
          <input type="text" name="first_name" value="<?= htmlspecialchars($input['first_name'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 required>
        </div>
        <!-- Last name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
          <input type="text" name="last_name" value="<?= htmlspecialchars($input['last_name'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 required>
        </div>
        <!-- Employee ID -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Employee ID <span class="text-red-500">*</span></label>
          <input type="text" name="employee_id" value="<?= htmlspecialchars($input['employee_id'] ?? $nextEmpId) ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                 required>
        </div>
        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
          <input type="email" name="email" value="<?= htmlspecialchars($input['email'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 required>
        </div>
        <!-- Phone -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($input['phone'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <!-- Date of birth -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
          <input type="date" name="date_of_birth" value="<?= htmlspecialchars($input['date_of_birth'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <!-- Gender -->
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
        <!-- Department -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
          <select name="department_id"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  required>
            <option value="">Select department</option>
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['id'] ?>" <?= ($input['department_id'] ?? 0) == $d['id'] ? 'selected' : '' ?>>
                <?= sanitize($d['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Position -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Position / Job Title <span class="text-red-500">*</span></label>
          <input type="text" name="position" value="<?= htmlspecialchars($input['position'] ?? '') ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 required>
        </div>
        <!-- Employment type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Employment Type</label>
          <select name="employment_type"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php foreach (['Full-Time', 'Part-Time', 'Contract', 'Internship'] as $et): ?>
              <option value="<?= $et ?>" <?= ($input['employment_type'] ?? 'Full-Time') === $et ? 'selected' : '' ?>><?= $et ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Hire date -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Hire Date <span class="text-red-500">*</span></label>
          <input type="date" name="hire_date" value="<?= htmlspecialchars($input['hire_date'] ?? date('Y-m-d')) ?>"
                 class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                 required>
        </div>
        <!-- Salary -->
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

    <!-- ── Account Access ───────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
      <h3 class="text-base font-semibold text-gray-900 mb-5 flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center text-xs font-bold">6</span>
        Account Access
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-5 max-w-2xl">

        <!-- Role -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            System Role <span class="text-red-500">*</span>
          </label>
          <select name="role"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  onchange="updateRoleHint(this.value)">
            <?php
            $roleOptions = [
                'Employee'           => 'Employee — standard access to own records',
                'Department Manager' => 'Department Manager — manages a department',
                'HR Manager'         => 'HR Manager — full HR & payroll access',
                'Admin'              => 'Admin — system-wide administration',
            ];
            foreach ($roleOptions as $val => $label):
            ?>
              <option value="<?= $val ?>" <?= ($selectedRole ?? 'Employee') === $val ? 'selected' : '' ?>>
                <?= $val ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p id="role-hint" class="text-xs text-gray-400 mt-1.5"></p>
        </div>

        <!-- Temporary Password -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Temporary Password <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <input type="text" name="temp_password" id="temp_password"
                   value="<?= htmlspecialchars($tempPassword ?? '') ?>"
                   class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                   placeholder="Min. 8 characters" autocomplete="off">
            <button type="button" onclick="generateTempPass()"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-indigo-600 hover:text-indigo-800 font-medium px-2 py-1 rounded hover:bg-indigo-50 transition-colors">
              Generate
            </button>
          </div>
          <p class="text-xs text-gray-400 mt-1.5">User must change this on first login.</p>
        </div>

      </div>

      <!-- Role description pills -->
      <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
        <?php
        $roleInfo = [
            'Employee'           => ['bg-slate-100 text-slate-700',  'View own records, apply for leave, check in/out'],
            'Department Manager' => ['bg-blue-100 text-blue-700',    'Approve dept leave, view team attendance & performance'],
            'HR Manager'         => ['bg-emerald-100 text-emerald-700','Full HR access: payroll, recruitment, final leave approval'],
            'Admin'              => ['bg-red-100 text-red-700',      'System-wide admin: manage users, departments, reports'],
        ];
        foreach ($roleInfo as $r => [$cls, $desc]):
        ?>
          <div class="rounded-lg p-3 <?= $cls ?>" id="role-card-<?= str_replace(' ', '-', $r) ?>">
            <p class="text-xs font-semibold"><?= $r ?></p>
            <p class="text-xs mt-0.5 opacity-80"><?= $desc ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Actions ────────────────────────────────────────────────────────── -->
    <div class="flex items-center justify-end gap-3 mb-8">
      <a href="<?= BASE_URL ?>/modules/employees/index.php"
         class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
        Cancel
      </a>
      <button type="submit"
              class="px-6 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors">
        Add Employee
      </button>
    </div>
  </form>
</main>

<script>
function updateRoleHint(role) {
    const hints = {
        'Employee':           'Standard access — can view own records, apply leave, check attendance.',
        'Department Manager': 'Can approve leave for their department and review team performance.',
        'HR Manager':         'Full HR access including payroll, recruitment, and final leave approval.',
        'Admin':              'Full system administration — cannot access payroll/salary data.',
    };
    const el = document.getElementById('role-hint');
    if (el) el.textContent = hints[role] || '';
    // Highlight active card
    document.querySelectorAll('[id^="role-card-"]').forEach(c => c.style.opacity = '0.45');
    const active = document.getElementById('role-card-' + role.replace(/ /g, '-'));
    if (active) active.style.opacity = '1';
}
// Run on page load
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.querySelector('[name="role"]');
    if (sel) updateRoleHint(sel.value);
});

function generateTempPass() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
    let pass = '';
    for (let i = 0; i < 10; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('temp_password').value = pass;
}

function previewPhoto(input) {
    const preview = document.getElementById('photo-preview');
    const placeholder = document.getElementById('photo-placeholder');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
