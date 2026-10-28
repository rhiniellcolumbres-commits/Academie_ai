<?php

require_once __DIR__ . '/functions.php';

if (empty($_SESSION['csrf_token'])) {
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentPage = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');

$navbarUser = (isset($user) && is_array($user)) ? $user : [];

if (!$navbarUser && isset($pdo, $_SESSION['user_id'])) {
    $navbarUser = getUserById($pdo, (int) $_SESSION['user_id']) ?: [];
}

$navbarName = trim((string) ($navbarUser['first_name'] ?? '') . ' ' . (string) ($navbarUser['last_name'] ?? ''));

$navbarTrack = trim((string) ($navbarUser['career_goal'] ?? ''));
$navbarInitials = strtoupper(substr((string) ($navbarUser['first_name'] ?? 'S'), 0, 1) . substr((string) ($navbarUser['last_name'] ?? ''), 0, 1));

?>
<nav class="main-navbar">
    <a href="dashboard" class="main-logo">
        <img class="dashboard-brand-mark" src="academie-icon.png" alt="">
        <span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">emie</span></span>
    </a>

    <div class="main-nav-links">
        <a href="dashboard" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span>Dashboard</span></a>
        <a href="roadmap" class="<?= $currentPage === 'study-path.php' ? 'active' : '' ?>"><i class="bi bi-signpost-split" aria-hidden="true"></i><span>Roadmap</span></a>
        <a href="profile" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>"><i class="bi bi-person-circle" aria-hidden="true"></i><span>Profile</span></a>
    </div>

    <div class="navbar-account">
        <a class="navbar-user-info" href="profile" aria-label="View profile for <?= e($navbarName !== '' ? $navbarName : 'Student') ?>">
            <span class="navbar-user-avatar" aria-hidden="true"><?= e($navbarInitials !== '' ? $navbarInitials : 'S') ?></span>
            <span class="navbar-user-details">
            <span class="navbar-user-name"><?= e($navbarName !== '' ? $navbarName : 'Student') ?></span>
            <?php if ($navbarTrack !== ''): ?><span class="navbar-user-track"><?= e($navbarTrack) ?></span><?php endif; ?>
            </span>
        </a>
        <form action="actions/logout.php" method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="navbar-logout">Log out</button>
        </form>
    </div>
</nav>
