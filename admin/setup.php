<?php
require_once __DIR__ . '/../includes/bootstrap.php';

require_once __DIR__ . '/../includes/db.php';

$count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($count > 0) {
    
    http_response_code(404);
    exit('Not found.');
}

$message = '';

if (empty($_SESSION['admin_setup_csrf'])) {
    $_SESSION['admin_setup_csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $count === 0) {
    
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if ($submittedToken === '' || !hash_equals((string) $_SESSION['admin_setup_csrf'], $submittedToken)) {
        http_response_code(403);
        exit('Invalid security token. Reload the page and try again.');
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!preg_match('/^[A-Za-z0-9_.-]{4,80}$/', $username) || strlen($password) < 12) {
        $message = 'Username must be 4–80 characters (letters, numbers, dots, underscores, or hyphens). Password must be at least 12 characters.';
    } else {
        try {
            
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)');
            
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
            
            $message = 'Admin account created. Setup is now disabled. Remove admin/setup.php from the server after setup.';
            $count = 1;
            unset($_SESSION['admin_setup_csrf']);
        } catch (PDOException $e) {
            
            $message = 'Could not create admin account. Check whether the username is already used.';
        }
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Academie Admin Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:520px"><div class="card shadow-sm"><div class="card-body p-4">
<h1 class="h3 mb-3">Create the first Academie admin account</h1>
<?php if ($message): ?><div class="alert alert-info"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($count > 0): ?><p>An admin account already exists. For security, this setup page is disabled. Go to <a href="login">Admin Login</a>.</p>
<?php else: ?><p class="text-muted">Make sure the Academie database is configured and the <code>admin_users</code> table exists. Choose a strong password and keep it private.</p>
<form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['admin_setup_csrf'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><label class="form-label" for="username">Admin username</label><input class="form-control mb-3" id="username" name="username" minlength="4" required>
<label class="form-label" for="password">Admin password</label><input class="form-control mb-3" type="password" id="password" name="password" minlength="12" required>
<button class="btn btn-primary w-100">Create Admin Account</button></form><?php endif; ?>
</div></div></main></body></html>
