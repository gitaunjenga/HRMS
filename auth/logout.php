<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
session_destroy();
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
