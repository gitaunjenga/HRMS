<?php
/**
 * HRMS - Mark Notification(s) as Read / Delete a Notification
 *
 * Accepts:
 *   GET  ?id=N                        → mark single notification as read
 *   GET  ?action=all                  → mark all as read for current user
 *   POST action=delete & id=N (CSRF)  → delete a single notification
 */
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();

$user   = currentUser();
$userId = (int)$user['id'];

$redirectUrl = BASE_URL . '/modules/notifications/index.php';

// ── DELETE action (POST with CSRF) ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        // Only delete if it belongs to the current user
        execute("DELETE FROM notifications WHERE id = ? AND user_id = ?", 'ii', $id, $userId);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

// ── MARK ALL as read ──────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'all') {
    execute(
        "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
        'i', $userId
    );
    header('Location: ' . $redirectUrl);
    exit;
}

// ── MARK SINGLE as read ───────────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id > 0) {
    // Verify ownership before marking
    $notif = fetchOne("SELECT id, link FROM notifications WHERE id = ? AND user_id = ?", 'ii', $id, $userId);
    if ($notif) {
        execute("UPDATE notifications SET is_read = 1 WHERE id = ?", 'i', $id);

        // If the notification has a link, redirect there; otherwise back to list
        if (!empty($notif['link'])) {
            header('Location: ' . $notif['link']);
            exit;
        }
    }
}

header('Location: ' . $redirectUrl);
exit;
