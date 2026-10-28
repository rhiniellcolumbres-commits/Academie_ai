<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard');
    exit;
}

$error = $_SESSION['auth_error'] ?? '';
unset($_SESSION['auth_error']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="academie-icon.png">
    <title>Log in - Academie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
<aside class="auth-side">
    <a class="auth-brand" href="./" aria-label="Academie home"><img class="auth-brand-icon" src="academie-icon.png" alt=""><span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">emie</span></span></a>
    <section class="auth-pitch"><h2>Pick up where you left off.</h2><ul><li><span class="auth-step">✓</span>Your roadmap and stage progress are saved</li><li><span class="auth-step">✓</span>Badges you earned stay on your profile</li><li><span class="auth-step">✓</span>Continue the lesson you were reading</li></ul></section>
    <p class="auth-side-note">Student project for academic purposes.</p>
</aside>
<main class="auth-main"><div class="auth-card">

    <h1>Log in</h1>
    <p class="auth-sub">Welcome back. Enter your details to continue.</p>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="actions/login.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <div class="auth-field"><i class="bi bi-envelope field-icon" aria-hidden="true"></i><input class="form-control" type="email" id="email" name="email" autocomplete="email" required maxlength="120" placeholder="you@example.com"></div>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="auth-field">
                <i class="bi bi-lock field-icon" aria-hidden="true"></i>
                <input class="form-control" type="password" id="password" name="password" required autocomplete="current-password">
                <button class="toggle-password" type="button" data-target="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button>
            </div>
        </div>
        <button class="btn btn-primary w-100 auth-submit" type="submit">Log in</button>
    </form>
    <p class="auth-alt">New to Academie? <a href="register">Create an account</a></p>
    <nav class="auth-footer-links" aria-label="Footer navigation"><a href="privacy">Privacy</a><a href="terms">Terms</a><a href="moderation">Moderation</a><a href="copyright">Copyright</a></nav>
</div></main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Adds show/hide behavior to each password visibility button.
document.querySelectorAll('.toggle-password').forEach(function (button) {
    // Toggles the associated password input and updates the button icon.
    button.addEventListener('click', function () {
        var input = document.getElementById(button.dataset.target);
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(show));
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        button.firstElementChild.className = show ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});
</script>
</body></html>
