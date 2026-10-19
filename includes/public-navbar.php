<?php
// Shared public navbar. Show account actions according to the current login session.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user_id']);
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$baseDir = preg_replace('#/(admin|api|actions)$#', '', rtrim($scriptDir, '/'));
$basePath = rtrim((string) $baseDir, '/');
if ($basePath === '/') {
    $basePath = '';
}

$dashboardUrl = $basePath . '/dashboard';
$loginUrl = $basePath . '/login';
$registerUrl = $basePath . '/register';
$logoutUrl = $basePath . '/actions/logout.php';
?>
<nav class="public-navbar" aria-label="Main navigation">
    <div class="public-navbar-inner">
        <a href="<?= htmlspecialchars($basePath . '/', ENT_QUOTES, 'UTF-8') ?>" class="public-brand" aria-label="StudyPath AI home">
            <span class="public-brand-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
            <span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">emie</span></span>
        </a>
        <button class="public-nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="publicNavLinks">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="public-nav-links" id="publicNavLinks">
            <span class="public-nav-auth">
                <?php if ($isLoggedIn): ?>
                    <a class="public-login" href="<?= htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard</a>
                    <a class="public-register" href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Log out</a>
                <?php else: ?>
                    <a class="public-login" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Log in</a>
                    <a class="public-register" href="<?= htmlspecialchars($registerUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-person-plus" aria-hidden="true"></i> Create account</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
</nav>
<script>
// Find the mobile navigation controls after the navbar markup has loaded.
document.addEventListener('DOMContentLoaded', function () {
    var toggle = document.querySelector('.public-nav-toggle');
    var links = document.getElementById('publicNavLinks');
    if (toggle && links) {
        // Toggle the expanded mobile navigation when the menu button is clicked.
        toggle.addEventListener('click', function () {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            links.classList.toggle('is-open', !expanded);
        });
    }
});
</script>
