<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';

function login_error(string $message): void {
    $_SESSION['auth_error'] = $message;
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    login_error('Your session expired. Please try logging in again.');
}
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$password = (string)($_POST['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    login_error('Invalid email or password.');
}

// Rate-limit by the combination of normalized email and client IP.
// Store only a SHA-256 key, not the raw email/IP combination.
$clientIp = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$limiterKey = hash('sha256', $email . '|' . $clientIp);
$limitStmt = $pdo->prepare(
    'SELECT attempts, window_started_at, blocked_until
     FROM security_login_attempts WHERE limiter_key = ? LIMIT 1'
);
$limitStmt->execute([$limiterKey]);
$limitRow = $limitStmt->fetch(PDO::FETCH_ASSOC);
$now = time();
if ($limitRow && !empty($limitRow['blocked_until']) && strtotime($limitRow['blocked_until']) > $now) {
    login_error('Too many failed login attempts. Please wait 15 minutes before trying again.');
}

$stmt = $pdo->prepare('SELECT id, email, password FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || !password_verify($password, $user['password'])) {
    // Five failed attempts in a 15-minute window trigger a 15-minute block.
    $withinWindow = $limitRow && (strtotime($limitRow['window_started_at']) > ($now - 900));
    $attempts = $withinWindow ? ((int)$limitRow['attempts'] + 1) : 1;
    $windowStartedAt = $withinWindow ? $limitRow['window_started_at'] : date('Y-m-d H:i:s');
    $blockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', $now + 900) : null;
    $saveLimit = $pdo->prepare(
        'INSERT INTO security_login_attempts (limiter_key, attempts, window_started_at, blocked_until)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE attempts = VALUES(attempts),
             window_started_at = VALUES(window_started_at), blocked_until = VALUES(blocked_until)'
    );
    $saveLimit->execute([$limiterKey, $attempts, $windowStartedAt, $blockedUntil]);
    login_error('Invalid email or password.');
}

// Clear the failed-attempt counter after a successful login.
$clearLimit = $pdo->prepare('DELETE FROM security_login_attempts WHERE limiter_key = ?');
$clearLimit->execute([$limiterKey]);
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['id'];
unset($_SESSION['csrf_token'], $_SESSION['auth_error']);
log_user_activity($pdo, (int)$user['id'], 'login');
header('Location: ../dashboard.php');
exit;
