<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
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
    <title>Create account - Acadamie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">
<header class="auth-topbar">
    <a class="auth-brand" href="index.php" aria-label="Acadamie home"><span class="auth-brand-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span><span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">amie</span></span></a>
    <a href="login.php" class="btn btn-outline-primary">Log in</a>
</header>
<main class="auth-main"><div class="auth-card wide">
    <span class="auth-eyebrow">GET STARTED</span>
    <h1>Create your account</h1>
    <p class="auth-sub">Register to get your personalized learning path.</p>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <form method="post" action="actions/register.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="row">
            <div class="col-sm-6 mb-3"><label for="first_name" class="form-label">First name</label><div class="auth-field no-icon"><input class="form-control" id="first_name" name="first_name" required maxlength="50" autocomplete="given-name"></div></div>
            <div class="col-sm-6 mb-3"><label for="last_name" class="form-label">Last name</label><div class="auth-field no-icon"><input class="form-control" id="last_name" name="last_name" required maxlength="50" autocomplete="family-name"></div></div>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <div class="auth-field"><i class="bi bi-envelope field-icon" aria-hidden="true"></i><input class="form-control" type="email" id="email" name="email" required maxlength="120" autocomplete="email" placeholder="you@example.com"></div>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="auth-field">
                <i class="bi bi-lock field-icon" aria-hidden="true"></i>
                <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                <button class="toggle-password" type="button" data-target="password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button>
            </div>
            <div class="auth-hint">Use at least 8 characters.</div>
        </div>
        <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm password</label>
            <div class="auth-field">
                <i class="bi bi-lock field-icon" aria-hidden="true"></i>
                <input class="form-control" type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
                <button class="toggle-password" type="button" data-target="confirm_password" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye-slash" aria-hidden="true"></i></button>
            </div>
        </div>
        <button class="btn btn-primary w-100 auth-submit" type="submit">Create account</button>
    </form>
    <p class="auth-alt">Already registered? <a href="login.php">Log in</a></p>
</div></main>
<footer class="site-footer">
    <div class="site-footer-inner">
        <aside class="site-disclaimer" aria-label="Academic project disclaimer">
            <strong>Disclaimer:</strong>
            StudyPath AI is developed for demonstration and academic purposes as part of a student project.
            It is not an official educational institution or professional advisory service.
            AI-generated learning paths and study materials are provided for educational guidance only and may require verification.
        </aside>
        <nav class="site-footer-links" aria-label="Footer navigation">
            <a href="privacy-policy.php">Privacy Policy</a>
            <a href="terms.php">Terms</a>
            <a href="moderation.php">Moderation</a>
            <a href="copyright.php">Copyright</a>
        </nav>
        <p class="site-copyright">&copy; <?= date('Y') ?> StudyPath AI</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Eye icon toggles between showing and hiding the password.
document.querySelectorAll('.toggle-password').forEach(function (button) {
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
