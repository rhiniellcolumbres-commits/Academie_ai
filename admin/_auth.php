<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminId = filter_var($_SESSION['admin_id'] ?? null, FILTER_VALIDATE_INT);
$lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
$sessionExpired = $lastActivity > 0 && (time() - $lastActivity) > 1800;

if (!$adminId || $sessionExpired) {
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_last_activity'], $_SESSION['admin_csrf_token']);
    header('Location: login?expired=1');
    exit;
}

require_once __DIR__ . '/../includes/db.php';
$check = $pdo->prepare('SELECT id, username FROM admin_users WHERE id = ? LIMIT 1');
$check->execute([(int) $adminId]);
$admin = $check->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_last_activity'], $_SESSION['admin_csrf_token']);
    header('Location: login');
    exit;
}

$_SESSION['admin_username'] = (string) $admin['username'];
$_SESSION['admin_last_activity'] = time();
if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
