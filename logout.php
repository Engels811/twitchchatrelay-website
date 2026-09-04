<?php
/**
 * Logout Handler
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/AuthManager.php';

$auth = new AuthManager();
$auth->logout();

// Redirect zur Login Seite
header('Location: ' . BASE_URL . '/auth/login.php?logged_out=1');
exit;

?>
