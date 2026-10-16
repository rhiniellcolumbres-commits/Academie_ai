<?php
// Admin entry point. The admin area has its own login (admin_users table), separate from student accounts.
session_start();
header('Location: ' . (!empty($_SESSION['admin_id']) ? 'dashboard.php' : 'login.php'));
exit;
