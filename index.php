<?php
require_once __DIR__ . '/includes/functions.php';
startSession();
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/index.php');
} else {
    header('Location: ' . BASE_URL . '/auth/login.php');
}
exit;
