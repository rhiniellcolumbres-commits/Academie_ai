<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_once __DIR__ . '/../includes/db.php';

if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals((string) $_SESSION['admin_login_csrf'], $submittedToken)) {
        http_response_code(403);
        exit('Invalid security token. Reload the page and try again.');
    }
    
    $username = substr(trim((string) ($_POST['username'] ?? '')), 0, 80);
    
    $password = (string) ($_POST['password'] ?? '');

$clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $limiterKey = hash('sha256', 'admin|' . strtolower($username) . '|' . $clientIp);
    
    $limitStmt = $pdo->prepare(
        'SELECT attempts, window_started_at, blocked_until
         FROM security_login_attempts WHERE limiter_key = ? LIMIT 1'
    );
    
    $limitStmt->execute([$limiterKey]);
    $limitRow = $limitStmt->fetch(PDO::FETCH_ASSOC);
    
    $now = time();

if ($limitRow && !empty($limitRow['blocked_until']) && strtotime($limitRow['blocked_until']) > $now) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes before trying again.';
    } else {
        
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password_hash'])) {
            
            $clearLimit = $pdo->prepare('DELETE FROM security_login_attempts WHERE limiter_key = ?');
            $clearLimit->execute([$limiterKey]);
            
            session_regenerate_id(true);
            
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_last_activity'] = time();
            
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            
            unset($_SESSION['admin_login_csrf']);
            
            header('Location: dashboard');
            exit;
        }
        
        $error = 'Invalid username or password.';

$withinWindow = $limitRow && strtotime((string) $limitRow['window_started_at']) > ($now - 900);
        
        $attempts = $withinWindow ? ((int) $limitRow['attempts'] + 1) : 1;
        
        $windowStartedAt = $withinWindow ? $limitRow['window_started_at'] : date('Y-m-d H:i:s');
        
        $blockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', $now + 900) : null;
        
        $saveLimit = $pdo->prepare(
            'INSERT INTO security_login_attempts (limiter_key, attempts, window_started_at, blocked_until)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attempts = VALUES(attempts),
                 window_started_at = VALUES(window_started_at), blocked_until = VALUES(blocked_until)'
        );
        
        $saveLimit->execute([$limiterKey, $attempts, $windowStartedAt, $blockedUntil]);
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Academie Admin Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>.admin-password-field{position:relative}.admin-password-field .form-control{height:48px;padding-right:48px;border:1px solid #d5e0f1;border-radius:12px;background:#fbfdff}.admin-password-field .form-control:focus{border-color:#2463c5;box-shadow:0 0 0 4px #2463c51f;background:#fff}.toggle-password{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:38px;height:38px;border:0;background:transparent;color:#6b7f9f;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;font-size:1.15rem;cursor:pointer}.toggle-password:hover,.toggle-password:focus-visible{background:#eef4ff;color:#2463c5;outline:none}.toggle-password:focus-visible{box-shadow:0 0 0 3px #2463c51f}</style></head>
<body class="bg-light"><main class="container py-5" style="max-width:460px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h3 mb-1">Academie Admin</h1><p class="text-muted mb-4">Sign in to monitor activity and AI usage.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['admin_login_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><label class="form-label" for="username">Username</label><input class="form-control mb-3" id="username" name="username" maxlength="80" autocomplete="username" required>
<label class="form-label" for="password">Password</label><div class="admin-password-field mb-4"><input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required><button class="toggle-password" type="button" id="toggle-password" aria-controls="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button></div>
<button class="btn btn-primary w-100">Log In</button></form><a class="d-inline-block mt-3" href="../">Back to home</a>
</div></div></main>
<script>
const passwordField = document.getElementById('password');
const passwordToggle = document.getElementById('toggle-password');

// Toggles the admin password field between hidden and visible text.
passwordToggle.addEventListener('click', function () {
    const showPassword = passwordField.type === 'password';
    passwordField.type = showPassword ? 'text' : 'password';
    passwordToggle.firstElementChild.className = showPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
    passwordToggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
    passwordToggle.setAttribute('aria-pressed', String(showPassword));
});
</script>
</body></html>
