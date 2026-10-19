<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
$submittedToken = (string) ($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    exit('Invalid security token. Refresh the page and try again.');
}

if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/audit.php';
    log_user_activity($pdo, (int) $_SESSION['user_id'], 'logout');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ../');
exit;
