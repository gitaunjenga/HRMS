<?php
/**
 * HRMS - Recruitment: Update Candidate Status (POST handler)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireRole('Admin', 'HR');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/modules/recruitment/index.php');
    exit;
}

if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid CSRF token. Please try again.');
    header('Location: ' . BASE_URL . '/modules/recruitment/index.php');
    exit;
}

$candidateId    = (int)($_POST['candidate_id'] ?? 0);
$jobId          = (int)($_POST['job_id'] ?? 0);
$newStatus      = sanitize($_POST['status'] ?? '');
$interviewDate  = trim($_POST['interview_date'] ?? '');
$interviewNotes = trim($_POST['interview_notes'] ?? '');
$rating         = max(0, min(5, (int)($_POST['rating'] ?? 0)));

// Validate
$allowedStatuses = ['Applied', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected'];

$errors = [];
if ($candidateId <= 0) $errors[] = 'Invalid candidate.';
if (!in_array($newStatus, $allowedStatuses)) $errors[] = 'Invalid status.';
if ($interviewDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $interviewDate)) {
    $errors[] = 'Invalid interview date format.';
    $interviewDate = '';
}

if (!empty($errors)) {
    setFlash('error', implode(' ', $errors));
    header('Location: ' . BASE_URL . '/modules/recruitment/applications.php?job_id=' . $jobId);
    exit;
}

// Check candidate exists
$candidate = fetchOne("SELECT * FROM candidates WHERE id = ?", 'i', $candidateId);
if (!$candidate) {
    setFlash('error', 'Candidate not found.');
    header('Location: ' . BASE_URL . '/modules/recruitment/applications.php?job_id=' . $jobId);
    exit;
}

// Update
$ok = execute(
    "UPDATE candidates
     SET status          = ?,
         interview_date  = ?,
         interview_notes = ?,
         rating          = ?
     WHERE id = ?",
    'sssii',
    $newStatus,
    ($interviewDate ?: null),
    ($interviewNotes ?: null),
    $rating,
    $candidateId
);

if ($ok !== false) {
    // If hired, create a notification/flag
    if ($newStatus === 'Hired') {
        // Optional: additional logic for onboarding workflow
    }

    setFlash('success', sanitize($candidate['first_name'] . ' ' . $candidate['last_name']) . '\'s application updated to "' . $newStatus . '".');
} else {
    setFlash('error', 'Failed to update candidate. Please try again.');
}

header('Location: ' . BASE_URL . '/modules/recruitment/applications.php?job_id=' . $jobId);
exit;
