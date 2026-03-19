<?php
/**
 * HRMS - Payslip (Kenyan Format)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('view', 'payroll');

$id   = (int)($_GET['id'] ?? 0);
$user = currentUser();

// Build query: Admin/HR Manager can see all; Employee can only see their own
if (hasRole('Admin', 'HR Manager', 'Head of Department')) {
    $payroll = fetchOne(
        "SELECT p.*,
                CONCAT(e.first_name,' ',e.last_name) AS emp_name,
                e.employee_id AS emp_code,
                e.email, e.phone, e.position, e.photo,
                d.name AS dept_name,
                CONCAT(proc.first_name,' ',proc.last_name) AS processor_name
         FROM payroll p
         JOIN employees e ON p.employee_id = e.id
         LEFT JOIN departments d ON e.department_id = d.id
         LEFT JOIN employees proc ON p.processed_by = proc.id
         WHERE p.id = ?",
        'i', $id
    );
} else {
    // Employee: can only view their own payslip
    $empId = (int)($user['employee_id'] ?? 0);
    if (!$empId) {
        setFlash('error', 'No employee record found for your account.');
        header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
        exit;
    }
    $payroll = fetchOne(
        "SELECT p.*,
                CONCAT(e.first_name,' ',e.last_name) AS emp_name,
                e.employee_id AS emp_code,
                e.email, e.phone, e.position, e.photo,
                d.name AS dept_name,
                CONCAT(proc.first_name,' ',proc.last_name) AS processor_name
         FROM payroll p
         JOIN employees e ON p.employee_id = e.id
         LEFT JOIN departments d ON e.department_id = d.id
         LEFT JOIN employees proc ON p.processed_by = proc.id
         WHERE p.id = ? AND p.employee_id = ?",
        'ii', $id, $empId
    );
}

if (!$payroll) {
    setFlash('error', 'Payslip not found or access denied.');
    header('Location: ' . BASE_URL . '/modules/payroll/index.php');
    exit;
}

$monthName = date('F', mktime(0, 0, 0, (int)$payroll['month'], 1));
$pageTitle = 'Payslip — ' . $payroll['emp_name'] . ' — ' . $monthName . ' ' . $payroll['year'];

// Salary structure for allowance/deduction breakdown
$structure = fetchOne(
    "SELECT * FROM salary_structures WHERE employee_id = ? ORDER BY effective_date DESC LIMIT 1",
    'i', (int)$payroll['employee_id']
);

// Helper: safely get float from structure or payroll
function amt(array $src = null, string $key): float {
    return $src ? (float)($src[$key] ?? 0) : 0;
}

// Earnings
$basic      = (float)$payroll['basic_salary'];
$house      = amt($structure, 'house_allowance');
$transport  = amt($structure, 'transport_allowance');
$medical    = amt($structure, 'medical_allowance');
$other_all  = amt($structure, 'other_allowances');
$overtime   = (float)$payroll['overtime_pay'];
$gross      = (float)$payroll['gross_salary'];

// Deductions (Kenyan labels)
$paye       = amt($structure, 'tax_deduction');      // PAYE
$nssf       = amt($structure, 'provident_fund');      // NSSF
$nhif       = amt($structure, 'insurance');           // NHIF
$other_ded  = amt($structure, 'other_deductions');    // Loans / Other
$total_ded  = (float)$payroll['total_deductions'];
$net        = (float)$payroll['net_salary'];

// If no structure, fall back to aggregate columns
if (!$structure) {
    $total_ded = (float)$payroll['total_deductions'];
}

$payPeriod     = $monthName . ' ' . $payroll['year'];
$payPeriodEnd  = date('d/m/Y', mktime(0, 0, 0, (int)$payroll['month'] + 1, 0, (int)$payroll['year']));
$generatedDate = date('d M Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
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
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Arial:wght@400;700&display=swap');

    * { box-sizing: border-box; }

    body { font-family: Arial, Helvetica, sans-serif; }

    @media print {
      .no-print { display: none !important; }
      body { background: white !important; margin: 0; padding: 0; }
      .payslip-wrap { max-width: 100% !important; margin: 0 !important; box-shadow: none !important; border: none !important; }
      @page { size: A4; margin: 10mm 12mm; }
    }

    /* Table borders */
    .slip-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .slip-table th, .slip-table td { border: 1px solid #374151; padding: 4px 8px; }
    .slip-table thead th { background: #1e3a5f; color: #ffffff; font-weight: 600; text-align: left; }
    .slip-table tfoot td { background: #f3f4f6; font-weight: 700; }

    .info-table { width: 100%; border-collapse: collapse; font-size: 12px; }
    .info-table td { border: 1px solid #d1d5db; padding: 4px 10px; vertical-align: top; }
    .info-table .label { background: #f0f4ff; font-weight: 600; color: #1e3a5f; width: 150px; }

    .outer-border { border: 2px solid #1e3a5f; }

    .section-heading {
      background: #1e3a5f;
      color: white;
      font-weight: 700;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 4px 8px;
    }
  </style>
</head>
<body class="bg-gray-200 min-h-screen py-6 px-4">

  <!-- Action Bar (hidden on print) -->
  <div class="no-print max-w-3xl mx-auto mb-4 flex items-center justify-between">
    <a href="<?= BASE_URL ?>/modules/payroll/index.php?month=<?= $payroll['month'] ?>&year=<?= $payroll['year'] ?>"
       class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors shadow-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
      </svg>
      Back to Payroll
    </a>
    <button onclick="window.print()"
            class="inline-flex items-center gap-2 px-5 py-2 bg-blue-900 hover:bg-blue-800 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
      </svg>
      Print / Save PDF
    </button>
  </div>

  <!-- ═══════════════════ PAYSLIP DOCUMENT ═══════════════════ -->
  <div class="payslip-wrap max-w-3xl mx-auto bg-white shadow-xl outer-border">

    <!-- ── COMPANY HEADER ────────────────────────────────────── -->
    <div style="background:#1e3a5f;" class="px-6 py-4 text-white">
      <div class="flex items-start justify-between">
        <div>
          <div class="flex items-center gap-3 mb-1">
            <div style="background:#ffffff;color:#1e3a5f;" class="w-10 h-10 rounded flex items-center justify-center font-extrabold text-sm">HR</div>
            <div>
              <p style="font-size:18px;font-weight:800;letter-spacing:0.04em;">HRMS COMPANY LIMITED</p>
              <p style="font-size:11px;opacity:0.8;">Incorporated in Kenya | Registration No. CPR/2020/12345</p>
            </div>
          </div>
          <p style="font-size:11px;opacity:0.75;margin-top:4px;">
            P.O. Box 00200 – Nairobi, Kenya &nbsp;|&nbsp; Tel: +254 700 000 000 &nbsp;|&nbsp; info@hrmscompany.co.ke
          </p>
          <p style="font-size:11px;opacity:0.75;">PIN: A001234567X &nbsp;|&nbsp; NHIF Employer Code: 1234567 &nbsp;|&nbsp; NSSF Employer No: 234567</p>
        </div>
        <div class="text-right" style="min-width:140px;">
          <p style="font-size:20px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;">PAYSLIP</p>
          <p style="font-size:13px;font-weight:600;opacity:0.9;"><?= $payPeriod ?></p>
          <p style="font-size:11px;opacity:0.75;">Period End: <?= $payPeriodEnd ?></p>
          <?php if ($payroll['payment_status'] === 'Paid'): ?>
            <span style="display:inline-block;margin-top:6px;padding:2px 10px;background:#22c55e;border-radius:20px;font-size:11px;font-weight:700;">&#10003; PAID</span>
          <?php else: ?>
            <span style="display:inline-block;margin-top:6px;padding:2px 10px;background:#f59e0b;border-radius:20px;font-size:11px;font-weight:700;">PENDING</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── EMPLOYEE DETAILS ──────────────────────────────────── -->
    <div class="px-0">
      <div class="section-heading px-4">Employee Details</div>
      <table class="info-table" style="margin:0;">
        <tr>
          <td class="label">Employee Name</td>
          <td><strong><?= sanitize($payroll['emp_name']) ?></strong></td>
          <td class="label">Employee No.</td>
          <td><?= sanitize($payroll['emp_code']) ?></td>
        </tr>
        <tr>
          <td class="label">Department</td>
          <td><?= sanitize($payroll['dept_name'] ?? '—') ?></td>
          <td class="label">Designation / Job Title</td>
          <td><?= sanitize($payroll['position'] ?? '—') ?></td>
        </tr>
        <tr>
          <td class="label">KRA PIN</td>
          <td><?= sanitize($payroll['kra_pin'] ?? 'A000000000X') ?></td>
          <td class="label">NHIF No.</td>
          <td><?= sanitize($payroll['nhif_no'] ?? '—') ?></td>
        </tr>
        <tr>
          <td class="label">NSSF No.</td>
          <td><?= sanitize($payroll['nssf_no'] ?? '—') ?></td>
          <td class="label">ID / Passport No.</td>
          <td><?= sanitize($payroll['national_id'] ?? '—') ?></td>
        </tr>
        <tr>
          <td class="label">Bank Name</td>
          <td><?= sanitize($payroll['bank_name'] ?? '—') ?></td>
          <td class="label">Account No.</td>
          <td><?= sanitize($payroll['bank_account'] ?? '—') ?></td>
        </tr>
        <tr>
          <td class="label">Pay Period</td>
          <td><?= $payPeriod ?></td>
          <td class="label">Days Worked / Absent</td>
          <td><?= (int)$payroll['days_worked'] ?> days worked &nbsp;/&nbsp; <?= (int)$payroll['days_absent'] ?> days absent</td>
        </tr>
      </table>
    </div>

    <!-- ── EARNINGS & DEDUCTIONS ─────────────────────────────── -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-top:1px solid #1e3a5f;">

      <!-- Earnings -->
      <div style="border-right:1px solid #1e3a5f;">
        <div class="section-heading">Earnings</div>
        <table class="slip-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style="text-align:right;">Amount (KES)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Basic Salary</td>
              <td style="text-align:right;font-family:monospace;"><?= number_format($basic, 2) ?></td>
            </tr>
            <?php if ($house > 0): ?>
            <tr>
              <td>House Allowance</td>
              <td style="text-align:right;font-family:monospace;"><?= number_format($house, 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($transport > 0): ?>
            <tr>
              <td>Transport Allowance</td>
              <td style="text-align:right;font-family:monospace;"><?= number_format($transport, 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($medical > 0): ?>
            <tr>
              <td>Medical Allowance</td>
              <td style="text-align:right;font-family:monospace;"><?= number_format($medical, 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($other_all > 0): ?>
            <tr>
              <td>Other Allowances</td>
              <td style="text-align:right;font-family:monospace;"><?= number_format($other_all, 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($overtime > 0): ?>
            <tr>
              <td>
                Overtime Pay
                <?php if ((float)$payroll['overtime_hours'] > 0): ?>
                  <span style="font-size:10px;color:#6b7280;">(<?= number_format((float)$payroll['overtime_hours'], 1) ?> hrs)</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;font-family:monospace;"><?= number_format($overtime, 2) ?></td>
            </tr>
            <?php endif; ?>
            <!-- Filler rows so tables look even -->
            <tr><td style="border-color:#e5e7eb;color:#9ca3af;font-size:11px;">&nbsp;</td><td style="border-color:#e5e7eb;"></td></tr>
          </tbody>
          <tfoot>
            <tr style="background:#dbeafe;">
              <td style="font-weight:700;color:#1e3a5f;">GROSS EARNINGS</td>
              <td style="text-align:right;font-family:monospace;font-weight:700;color:#1e3a5f;"><?= number_format($gross, 2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Deductions -->
      <div>
        <div class="section-heading">Deductions</div>
        <table class="slip-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style="text-align:right;">Amount (KES)</th>
            </tr>
          </thead>
          <tbody>
            <!-- Statutory -->
            <tr style="background:#fff7ed;">
              <td style="font-size:10px;font-weight:600;color:#92400e;font-style:italic;" colspan="2">Statutory Deductions</td>
            </tr>
            <tr>
              <td>PAYE <span style="font-size:10px;color:#6b7280;">(Pay As You Earn)</span></td>
              <td style="text-align:right;font-family:monospace;color:#dc2626;"><?= $paye > 0 ? number_format($paye, 2) : '—' ?></td>
            </tr>
            <tr>
              <td>NSSF <span style="font-size:10px;color:#6b7280;">(National Social Security Fund)</span></td>
              <td style="text-align:right;font-family:monospace;color:#dc2626;"><?= $nssf > 0 ? number_format($nssf, 2) : '—' ?></td>
            </tr>
            <tr>
              <td>NHIF <span style="font-size:10px;color:#6b7280;">(National Hospital Insurance Fund)</span></td>
              <td style="text-align:right;font-family:monospace;color:#dc2626;"><?= $nhif > 0 ? number_format($nhif, 2) : '—' ?></td>
            </tr>
            <!-- Non-statutory -->
            <tr style="background:#fff7ed;">
              <td style="font-size:10px;font-weight:600;color:#92400e;font-style:italic;" colspan="2">Other Deductions</td>
            </tr>
            <?php if ($other_ded > 0): ?>
            <tr>
              <td>Loan / Advance Repayment</td>
              <td style="text-align:right;font-family:monospace;color:#dc2626;"><?= number_format($other_ded, 2) ?></td>
            </tr>
            <?php else: ?>
            <tr>
              <td style="color:#9ca3af;font-size:11px;">No other deductions</td>
              <td style="text-align:right;font-family:monospace;color:#9ca3af;">—</td>
            </tr>
            <?php endif; ?>
          </tbody>
          <tfoot>
            <tr style="background:#fee2e2;">
              <td style="font-weight:700;color:#991b1b;">TOTAL DEDUCTIONS</td>
              <td style="text-align:right;font-family:monospace;font-weight:700;color:#991b1b;"><?= number_format($total_ded, 2) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- ── NET PAY BOX ────────────────────────────────────────── -->
    <div style="background:#1e3a5f;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;border-top:2px solid #1e3a5f;">
      <div>
        <p style="color:#93c5fd;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;">Net Pay</p>
        <p style="color:#ffffff;font-size:26px;font-weight:800;font-family:monospace;letter-spacing:0.02em;">KES <?= number_format($net, 2) ?></p>
        <p style="color:#93c5fd;font-size:10px;">
          <?php
          $netWords = '';
          // Simple number to words for display
          $netInt = (int)round($net);
          if ($netInt >= 1000000) $netWords = 'Kenya Shillings ' . number_format($net, 2) . ' Only';
          else $netWords = 'Kenya Shillings ' . number_format($net, 2) . ' Only';
          echo htmlspecialchars($netWords);
          ?>
        </p>
      </div>
      <div style="text-align:right;">
        <?php if ($payroll['payment_status'] === 'Paid'): ?>
          <p style="color:#93c5fd;font-size:10px;">Payment Method</p>
          <p style="color:#ffffff;font-size:12px;font-weight:600;"><?= sanitize($payroll['payment_method'] ?? '—') ?></p>
          <p style="color:#93c5fd;font-size:10px;margin-top:4px;">Payment Date</p>
          <p style="color:#ffffff;font-size:12px;font-weight:600;"><?= formatDate($payroll['payment_date']) ?></p>
        <?php else: ?>
          <span style="display:inline-block;padding:4px 14px;background:#f59e0b;border-radius:20px;font-size:11px;font-weight:700;color:#1f2937;">PAYMENT PENDING</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── LEAVE SUMMARY (inline) ─────────────────────────────── -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;font-size:11px;border-top:1px solid #d1d5db;">
      <div style="padding:6px 12px;border-right:1px solid #d1d5db;text-align:center;">
        <p style="color:#6b7280;font-weight:600;text-transform:uppercase;font-size:10px;">Days Worked</p>
        <p style="font-size:16px;font-weight:700;color:#1e3a5f;"><?= (int)$payroll['days_worked'] ?></p>
      </div>
      <div style="padding:6px 12px;border-right:1px solid #d1d5db;text-align:center;">
        <p style="color:#6b7280;font-weight:600;text-transform:uppercase;font-size:10px;">Days Absent</p>
        <p style="font-size:16px;font-weight:700;color:#dc2626;"><?= (int)$payroll['days_absent'] ?></p>
      </div>
      <div style="padding:6px 12px;text-align:center;">
        <p style="color:#6b7280;font-weight:600;text-transform:uppercase;font-size:10px;">Overtime Hours</p>
        <p style="font-size:16px;font-weight:700;color:#1e3a5f;"><?= number_format((float)$payroll['overtime_hours'], 1) ?></p>
      </div>
    </div>

    <!-- ── NOTES ─────────────────────────────────────────────── -->
    <?php if (!empty($payroll['notes'])): ?>
    <div style="padding:6px 16px;background:#fffbeb;border-top:1px solid #fde68a;font-size:11px;color:#78350f;">
      <strong>Remarks / Notes:</strong> <?= sanitize($payroll['notes']) ?>
    </div>
    <?php endif; ?>

    <!-- ── DECLARATION & SIGNATURES ─────────────────────────── -->
    <div style="padding:12px 16px;border-top:1px solid #d1d5db;background:#f9fafb;">
      <p style="font-size:10px;color:#374151;margin-bottom:12px;line-height:1.5;">
        This pay advice is issued for the pay period ending <strong><?= $payPeriodEnd ?></strong>.
        The net amount of <strong>KES <?= number_format($net, 2) ?></strong> has been
        <?= $payroll['payment_status'] === 'Paid' ? 'credited to your account.' : 'processed and is pending payment.' ?>
        Deductions include all statutory obligations as required under the Laws of Kenya
        (Income Tax Act Cap 470, NSSF Act, NHIF Act).
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:10px;">
        <div>
          <div style="border-bottom:1px solid #374151;height:32px;margin-bottom:4px;"></div>
          <p style="font-size:10px;color:#6b7280;">Authorised Signatory / HR Manager</p>
          <p style="font-size:10px;color:#6b7280;">Name: ____________________________</p>
          <p style="font-size:10px;color:#6b7280;">Date: <?= $generatedDate ?></p>
        </div>
        <div>
          <div style="border-bottom:1px solid #374151;height:32px;margin-bottom:4px;"></div>
          <p style="font-size:10px;color:#6b7280;">Employee Acknowledgement</p>
          <p style="font-size:10px;color:#6b7280;">Name: <?= sanitize($payroll['emp_name']) ?></p>
          <p style="font-size:10px;color:#6b7280;">Date: ____________________________</p>
        </div>
      </div>
    </div>

    <!-- ── FOOTER ────────────────────────────────────────────── -->
    <div style="background:#1e3a5f;padding:6px 16px;display:flex;justify-content:space-between;align-items:center;">
      <p style="color:#93c5fd;font-size:9px;">Generated: <?= $generatedDate ?> at <?= date('H:i') ?> EAT</p>
      <?php if ($payroll['processor_name']): ?>
        <p style="color:#93c5fd;font-size:9px;">Processed by: <?= sanitize($payroll['processor_name']) ?></p>
      <?php endif; ?>
      <p style="color:#93c5fd;font-size:9px;font-style:italic;">*** This is a computer-generated payslip — no signature required ***</p>
    </div>

  </div><!-- /.payslip-wrap -->

</body>
</html>
