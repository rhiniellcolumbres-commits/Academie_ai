<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals((string) $_SESSION['admin_login_csrf'], $submittedToken)) {
        http_response_code(403);
        exit('Invalid security token. Reload the page and try again.');
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_last_activity'] = time();
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        unset($_SESSION['admin_login_csrf']);
        header('Location: dashboard.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin Login | StudyPath</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:460px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h3 mb-1">StudyPath Admin</h1><p class="text-muted mb-4">Sign in to monitor activity and AI usage.</p>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['admin_login_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><label class="form-label" for="username">Username</label><input class="form-control mb-3" id="username" name="username" autocomplete="username" required>
<label class="form-label" for="password">Password</label><input class="form-control mb-4" type="password" id="password" name="password" autocomplete="current-password" required>
<button class="btn btn-primary w-100">Log In</button></form><a class="d-inline-block mt-3" href="../index.php">Back to home</a>
</div></div></main></body></html>
