/**
 * HRMS Main JavaScript
 */

// ─── Flash Message Auto-dismiss ───────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
  const flashes = document.querySelectorAll('.flash-message');
  flashes.forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.5s ease';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });
});

// ─── Confirm Dialogs ──────────────────────────────────────────────────────────
function confirmAction(message, formOrUrl) {
  if (confirm(message || 'Are you sure?')) {
    if (typeof formOrUrl === 'string') {
      window.location.href = formOrUrl;
    } else if (formOrUrl instanceof HTMLFormElement) {
      formOrUrl.submit();
    }
    return true;
  }
  return false;
}

// ─── Image Preview ────────────────────────────────────────────────────────────
function previewImage(input, previewId) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => {
      const img = document.getElementById(previewId);
      if (img) img.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}

// ─── Date Range Validation ────────────────────────────────────────────────────
function validateDateRange(startId, endId) {
  const start = document.getElementById(startId);
  const end = document.getElementById(endId);
  if (start && end) {
    end.min = start.value;
    start.addEventListener('change', () => {
      if (end.value && end.value < start.value) end.value = start.value;
      end.min = start.value;
    });
  }
}

// ─── Business-day Counter ─────────────────────────────────────────────────────
function countBusinessDays(start, end) {
  let count = 0;
  const s = new Date(start);
  const e = new Date(end);
  const cur = new Date(s);
  while (cur <= e) {
    const day = cur.getDay();
    if (day !== 0 && day !== 6) count++;
    cur.setDate(cur.getDate() + 1);
  }
  return count;
}

// ─── Leave Days Calculator ────────────────────────────────────────────────────
function setupLeaveCalc(startId, endId, outputId) {
  const start = document.getElementById(startId);
  const end = document.getElementById(endId);
  const out = document.getElementById(outputId);
  if (!start || !end || !out) return;
  function update() {
    if (start.value && end.value && end.value >= start.value) {
      out.textContent = countBusinessDays(start.value, end.value) + ' working day(s)';
    } else {
      out.textContent = '';
    }
  }
  start.addEventListener('change', update);
  end.addEventListener('change', update);
}

// ─── Search/Filter Table ──────────────────────────────────────────────────────
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function () {
    const q = this.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ─── Salary Calculator ────────────────────────────────────────────────────────
function calcSalary() {
  const ids = ['basic_salary','house_allowance','transport_allowance','medical_allowance','other_allowances',
                'tax_deduction','provident_fund','insurance','other_deductions'];
  const vals = {};
  ids.forEach(id => {
    const el = document.getElementById(id);
    vals[id] = el ? parseFloat(el.value) || 0 : 0;
  });

  const gross = vals.basic_salary + vals.house_allowance + vals.transport_allowance +
                vals.medical_allowance + vals.other_allowances;
  const deductions = vals.tax_deduction + vals.provident_fund + vals.insurance + vals.other_deductions;
  const net = gross - deductions;

  const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = 'KES ' + val.toFixed(2); };
  setEl('gross_display', gross);
  setEl('deductions_display', deductions);
  setEl('net_display', net);
}

document.addEventListener('DOMContentLoaded', () => {
  const salaryFields = ['basic_salary','house_allowance','transport_allowance','medical_allowance',
    'other_allowances','tax_deduction','provident_fund','insurance','other_deductions'];
  salaryFields.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', calcSalary);
  });
  calcSalary();
});

// ─── Attendance Check-in/out Toggle ──────────────────────────────────────────
function updateAttendanceTime() {
  const now = new Date();
  const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  const el = document.getElementById('current-time');
  if (el) el.textContent = timeStr;
}
setInterval(updateAttendanceTime, 1000);
updateAttendanceTime();

// ─── Charts Helper ────────────────────────────────────────────────────────────
const hrmsCharts = {
  donut(id, labels, data, colors) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    return new Chart(ctx, {
      type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
  },
  bar(id, labels, datasets, options = {}) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    return new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, ...options }
    });
  },
  line(id, labels, datasets) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    return new Chart(ctx, {
      type: 'line',
      data: { labels, datasets },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }
};
