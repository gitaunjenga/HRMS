<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRF($_POST['csrf_token'] ?? '')) {
    header('Location: ' . BASE_URL . '/modules/attendance/my-qr.php');
    exit;
}

$empId = (int)(currentUser()['employee_id'] ?? 0);
if ($empId > 0) {
    $token = bin2hex(random_bytes(16));
    execute("UPDATE employees SET qr_token = ? WHERE id = ?", 'si', $token, $empId);
    setFlash('success', 'QR code regenerated. Your old code is now invalid.');
}

header('Location: ' . BASE_URL . '/modules/attendance/my-qr.php');
exit;
