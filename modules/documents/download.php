<?php
/**
 * HRMS - Secure Document Download
 * Validates permission, streams the file, prevents direct URL access.
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user  = currentUser();
$isHR  = hasRole('Admin', 'HR Manager');
$docId = (int)($_GET['id'] ?? 0);

if ($docId <= 0) {
    setFlash('error', 'Invalid document ID.');
    header('Location: ' . BASE_URL . '/modules/documents/index.php');
    exit;
}

// ── Fetch document record ─────────────────────────────────────────────────────
$doc = fetchOne("SELECT * FROM documents WHERE id = ?", 'i', $docId);

if (!$doc) {
    setFlash('error', 'Document not found.');
    header('Location: ' . BASE_URL . '/modules/documents/index.php');
    exit;
}

// ── Permission check ──────────────────────────────────────────────────────────
if (!$isHR) {
    // Resolve the logged-in user's employee record
    $myEmpId = 0;
    if ($user['employee_id'] ?? null) {
        $myEmpId = (int)$user['employee_id'];
    }

    $isCompanyDoc = ($doc['employee_id'] === null || $doc['employee_id'] == 0);
    $isOwn        = ($myEmpId > 0 && (int)$doc['employee_id'] === $myEmpId);

    if (!$isCompanyDoc && !$isOwn) {
        setFlash('error', 'You do not have permission to download this document.');
        header('Location: ' . BASE_URL . '/modules/documents/index.php');
        exit;
    }
}

// ── Resolve physical file path ────────────────────────────────────────────────
$filePath = rtrim(DOC_DIR, '/') . '/' . basename($doc['file_path']);

if (!file_exists($filePath) || !is_file($filePath)) {
    setFlash('error', 'The requested file could not be found on the server.');
    header('Location: ' . BASE_URL . '/modules/documents/index.php');
    exit;
}

// ── Determine MIME type ───────────────────────────────────────────────────────
$ext      = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeMap  = [
    'pdf'  => 'application/pdf',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
];
$mimeType = $mimeMap[$ext] ?? 'application/octet-stream';

// Build a clean download filename from the document title
$safeTitle    = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $doc['title']);
$downloadName = $safeTitle . '.' . $ext;

// ── Clear output buffer and send headers ─────────────────────────────────────
if (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: '        . $mimeType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: '      . filesize($filePath));
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
// Prevent clickjacking / direct embedding
header('X-Content-Type-Options: nosniff');

// ── Stream file ───────────────────────────────────────────────────────────────
$fp = fopen($filePath, 'rb');
if ($fp === false) {
    // Headers already sent — redirect won't work cleanly, just die with a message.
    http_response_code(500);
    die('Failed to open file for reading.');
}

fpassthru($fp);
fclose($fp);
exit;
