<?php
/**
 * HRMS - Delete Document (POST handler — HR/Admin only)
 */
require_once __DIR__ . '/../../includes/functions.php';
requireCan('manage', 'documents');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/modules/documents/index.php');
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
    setFlash('error', 'Invalid security token. Please try again.');
    header('Location: ' . BASE_URL . '/modules/documents/index.php');
    exit;
}

$docId = (int)($_POST['id'] ?? 0);

if ($docId <= 0) {
    setFlash('error', 'Invalid document ID.');
    header('Location: ' . BASE_URL . '/modules/documents/index.php');
    exit;
}

// ── Fetch document ────────────────────────────────────────────────────────────
$doc = fetchOne("SELECT * FROM documents WHERE id = ?", 'i', $docId);

if (!$doc) {
    setFlash('error', 'Document not found.');
    header('Location: ' . BASE_URL . '/modules/documents/index.php');
    exit;
}

// ── Delete physical file ──────────────────────────────────────────────────────
$filePath = rtrim(DOC_DIR, '/') . '/' . basename($doc['file_path']);
if (file_exists($filePath) && is_file($filePath)) {
    @unlink($filePath);
}

// ── Delete database record ────────────────────────────────────────────────────
$deleted = execute("DELETE FROM documents WHERE id = ?", 'i', $docId);

if ($deleted !== false) {
    setFlash('success', 'Document "' . $doc['title'] . '" has been deleted.');
} else {
    setFlash('error', 'Failed to delete the document record. Please try again.');
}

header('Location: ' . BASE_URL . '/modules/documents/index.php');
exit;
