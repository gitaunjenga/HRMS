<?php
/**
 * HRMS - Recruitment: Candidates / Applications for a Job
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
requireCan('view', 'recruitment');

$pageTitle = 'Applications';
$user      = currentUser();
$role      = $user['role'] ?? '';
$isHRMgr   = ($role === 'HR Manager');
$isAdmin   = ($role === 'Admin');

$jobId = (int)($_GET['job_id'] ?? 0);
if ($jobId <= 0) {
    setFlash('error', 'Invalid job ID.');
    header('Location: ' . BASE_URL . '/modules/recruitment/index.php');
    exit;
}

// ── Load Job ───────────────────────────────────────────────────────────────────
$job = fetchOne(
    "SELECT jp.*, d.name AS dept_name
     FROM job_postings jp
     LEFT JOIN departments d ON jp.department_id = d.id
     WHERE jp.id = ?",
    'i', $jobId
);
if (!$job) {
    setFlash('error', 'Job posting not found.');
    header('Location: ' . BASE_URL . '/modules/recruitment/index.php');
    exit;
}

// ── Filters ────────────────────────────────────────────────────────────────────
$filterStatus = sanitize($_GET['status'] ?? '');
$search       = sanitize($_GET['q'] ?? '');

// ── Pagination ─────────────────────────────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where  = 'c.job_id = ?';
$types  = 'i';
$params = [$jobId];

if ($filterStatus !== '') {
    $where   .= ' AND c.status = ?';
    $types   .= 's';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where   .= ' AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)';
    $types   .= 'sss';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$totalRows = (int)(fetchOne("SELECT COUNT(*) AS c FROM candidates c WHERE {$where}", $types, ...$params)['c'] ?? 0);
$pag       = paginate($totalRows, $page, $perPage);

$typesFull  = $types . 'ii';
$paramsFull = array_merge($params, [$pag['perPage'], $pag['offset']]);

$candidates = fetchAll(
    "SELECT c.* FROM candidates c
     WHERE {$where}
     ORDER BY c.id DESC
     LIMIT ? OFFSET ?",
    $typesFull, ...$paramsFull
);

// ── Status breakdown ───────────────────────────────────────────────────────────
$statusBreakdown = fetchAll(
    "SELECT status, COUNT(*) AS cnt FROM candidates WHERE job_id = ? GROUP BY status",
    'i', $jobId
);
$statusCounts = [];
foreach ($statusBreakdown as $s) {
    $statusCounts[$s['status']] = (int)$s['cnt'];
}

$baseUrl = BASE_URL . '/modules/recruitment/applications.php?job_id=' . $jobId
         . '&status=' . urlencode($filterStatus) . '&q=' . urlencode($search);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<div class="ml-64 min-h-screen flex flex-col">
  <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

  <main class="flex-1 p-6 space-y-6">

    <!-- Flash -->
    <?php $flash = getFlash(); if ($flash): ?>
      <div class="rounded-lg px-4 py-3 text-sm font-medium <?= $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
      </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-1">
          <a href="<?= BASE_URL ?>/modules/recruitment/index.php" class="hover:text-indigo-600">Recruitment</a>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          <span class="text-gray-700 font-medium"><?= sanitize($job['title']) ?></span>
        </div>
        <h1 class="text-2xl font-bold text-gray-900">Applications</h1>
        <div class="flex flex-wrap items-center gap-3 mt-1">
          <span class="text-sm text-gray-500"><?= sanitize($job['dept_name'] ?? '—') ?></span>
          <span class="text-gray-300">•</span>
          <span class="text-sm text-gray-500"><?= sanitize($job['employment_type'] ?? '—') ?></span>
          <span class="text-gray-300">•</span>
          <?= statusBadge($job['status']) ?>
          <?php if ($job['deadline']): ?>
            <span class="text-gray-300">•</span>
            <span class="text-sm text-gray-500">Deadline: <?= formatDate($job['deadline']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/modules/recruitment/index.php"
         class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Back to Jobs
      </a>
    </div>

    <!-- Status Filter Pills -->
    <div class="flex flex-wrap gap-2">
      <?php
      $allStatuses = ['', 'Applied', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected'];
      foreach ($allStatuses as $s):
        $label = $s === '' ? 'All' : $s;
        $cnt   = $s === '' ? $totalRows : ($statusCounts[$s] ?? 0);
        $active = $filterStatus === $s;
      ?>
        <a href="<?= BASE_URL ?>/modules/recruitment/applications.php?job_id=<?= $jobId ?>&status=<?= urlencode($s) ?>&q=<?= urlencode($search) ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-colors
                  <?= $active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-indigo-50 hover:text-indigo-700' ?>">
          <?= $label ?>
          <span class="<?= $active ? 'bg-indigo-500 text-white' : 'bg-gray-100 text-gray-600' ?> rounded-full px-1.5 py-0.5 text-xs"><?= $cnt ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Search -->
    <form method="GET" class="flex gap-3">
      <input type="hidden" name="job_id" value="<?= $jobId ?>">
      <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
      <div class="flex-1 max-w-sm relative">
        <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or email…"
               class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
      </div>
      <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg transition-colors">Search</button>
    </form>

    <!-- Candidates Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="font-semibold text-gray-800">Candidates — <?= sanitize($job['title']) ?></h2>
        <span class="text-xs text-gray-400"><?= $totalRows ?> applicant(s)</span>
      </div>

      <?php if (empty($candidates)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-gray-400">
          <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          <p class="text-sm font-medium">No candidates found</p>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Candidate</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Contact</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Interview Date</th>
                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Rating</th>
                <?php if ($isHRMgr): ?>
                  <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <?php foreach ($candidates as $c): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                  <td class="px-5 py-3">
                    <p class="font-medium text-gray-900"><?= sanitize($c['first_name'] . ' ' . $c['last_name']) ?></p>
                    <?php if ($c['resume']): ?>
                      <a href="<?= BASE_URL ?>/uploads/resumes/<?= urlencode($c['resume']) ?>"
                         target="_blank"
                         class="text-xs text-indigo-600 hover:underline">View Resume</a>
                    <?php endif; ?>
                  </td>
                  <td class="px-4 py-3">
                    <p class="text-gray-700"><?= sanitize($c['email']) ?></p>
                    <p class="text-xs text-gray-400"><?= sanitize($c['phone'] ?? '—') ?></p>
                  </td>
                  <td class="px-4 py-3 text-center"><?= statusBadge($c['status']) ?></td>
                  <td class="px-4 py-3 text-gray-600">
                    <?= $c['interview_date'] ? formatDate($c['interview_date']) : '<span class="text-gray-400">—</span>' ?>
                  </td>
                  <td class="px-4 py-3 text-center">
                    <?php
                    $rating = (int)($c['rating'] ?? 0);
                    if ($rating > 0):
                      for ($i = 1; $i <= 5; $i++):
                    ?>
                      <span class="<?= $i <= $rating ? 'text-yellow-400' : 'text-gray-300' ?>">★</span>
                    <?php
                      endfor;
                    else: ?>
                      <span class="text-gray-400 text-xs">Not rated</span>
                    <?php endif; ?>
                  </td>
                  <?php if ($isHRMgr): ?>
                    <td class="px-4 py-3">
                      <div class="flex items-center justify-center gap-1">
                        <button type="button"
                                onclick="openUpdateModal(<?= htmlspecialchars(json_encode([
                                  'id'             => $c['id'],
                                  'name'           => $c['first_name'] . ' ' . $c['last_name'],
                                  'status'         => $c['status'],
                                  'interview_date' => $c['interview_date'] ?? '',
                                  'interview_notes'=> $c['interview_notes'] ?? '',
                                  'rating'         => (int)($c['rating'] ?? 0),
                                ])) ?>)"
                                title="Update Status"
                                class="p-1.5 rounded-lg text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                          </svg>
                        </button>
                      </div>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?= renderPagination($pag, $baseUrl) ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<?php if ($isHRMgr): ?>
<!-- Update Candidate Modal — HR Manager only -->
<div id="updateModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-40 px-4">
  <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-screen overflow-y-auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-bold text-gray-900">Update Candidate</h3>
      <button onclick="closeUpdateModal()" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <p class="text-sm text-gray-500 mb-5" id="modalCandidateName"></p>

    <form method="POST" action="<?= BASE_URL ?>/modules/recruitment/update_candidate.php">
      <?= csrfField() ?>
      <input type="hidden" name="candidate_id" id="modalCandidateId">
      <input type="hidden" name="job_id" value="<?= $jobId ?>">

      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
          <select name="status" id="modalStatus" required
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <?php foreach (['Applied','Screening','Interview','Offer','Hired','Rejected'] as $st): ?>
              <option value="<?= $st ?>"><?= $st ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Interview Date</label>
          <input type="date" name="interview_date" id="modalInterviewDate"
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Rating (1–5)</label>
          <div class="flex gap-2" id="starRating">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <button type="button" data-star="<?= $i ?>"
                      onclick="setRating(<?= $i ?>)"
                      class="star-btn text-3xl text-gray-300 hover:text-yellow-400 transition-colors focus:outline-none">★</button>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" id="modalRatingInput" value="0">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Interview Notes</label>
          <textarea name="interview_notes" id="modalInterviewNotes" rows="4"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                    placeholder="Notes about the interview, observations, etc."></textarea>
        </div>
      </div>

      <div class="flex gap-3 mt-6">
        <button type="submit"
                class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg transition-colors">
          Save Changes
        </button>
        <button type="button" onclick="closeUpdateModal()"
                class="flex-1 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition-colors">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
<?php if ($isHRMgr): ?>
function openUpdateModal(data) {
  document.getElementById('modalCandidateId').value     = data.id;
  document.getElementById('modalCandidateName').textContent = 'Candidate: ' + data.name;
  document.getElementById('modalStatus').value          = data.status;
  document.getElementById('modalInterviewDate').value   = data.interview_date || '';
  document.getElementById('modalInterviewNotes').value  = data.interview_notes || '';
  setRating(parseInt(data.rating) || 0);
  document.getElementById('updateModal').classList.remove('hidden');
}
function closeUpdateModal() {
  document.getElementById('updateModal').classList.add('hidden');
}
function setRating(val) {
  document.getElementById('modalRatingInput').value = val;
  document.querySelectorAll('.star-btn').forEach(function(btn) {
    btn.classList.toggle('text-yellow-400', parseInt(btn.dataset.star) <= val);
    btn.classList.toggle('text-gray-300', parseInt(btn.dataset.star) > val);
  });
}
document.getElementById('updateModal').addEventListener('click', function(e) {
  if (e.target === this) closeUpdateModal();
});
<?php endif; ?>
</script>
