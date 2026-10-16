<?php
// Shared navbar for every logged-in page (dashboard, roadmap, profile, assessment).
// Pages load the student with getUserById(), which already includes career_goal and
// course, so we reuse $user. If a page did not load it, we fetch it once here.

require_once __DIR__ . '/functions.php';

// Ensure the session has a token for the POST-only logout form.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$navbarUser = (isset($user) && is_array($user)) ? $user : [];

if (!$navbarUser && isset($pdo, $_SESSION['user_id'])) {
    $navbarUser = getUserById($pdo, (int) $_SESSION['user_id']) ?: [];
}

$navbarName = trim((string) ($navbarUser['first_name'] ?? '') . ' ' . (string) ($navbarUser['last_name'] ?? ''));

// Show the career goal; fall back to the course if no career goal was saved.
$navbarTrack = trim((string) ($navbarUser['career_goal'] ?? ''));
if ($navbarTrack === '') {
    $navbarTrack = trim((string) ($navbarUser['course'] ?? ''));
}
if ($navbarTrack === '') {
    $navbarTrack = 'Course not specified';
}

?>
<nav class="main-navbar">
    <a href="dashboard.php" class="main-logo">
        <span class="brand-name"><span class="brand-acad">Acad</span><span class="brand-amie">amie</span></span>
    </a>

    <div class="main-nav-links">
        <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"><i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span>Dashboard</span></a>
        <a href="study-path.php" class="<?= $currentPage === 'study-path.php' ? 'active' : '' ?>"><i class="bi bi-signpost-split" aria-hidden="true"></i><span>Roadmap</span></a>
        <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>"><i class="bi bi-person-circle" aria-hidden="true"></i><span>Profile</span></a>
    </div>

    <div class="navbar-account">
        <div class="navbar-user-info">
            <span class="navbar-user-name"><?= e($navbarName !== '' ? $navbarName : 'Student') ?></span>
            <span class="navbar-user-course"><?= e($navbarTrack) ?></span>
        </div>
        <form action="actions/logout.php" method="post" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>">
            <button type="submit" class="navbar-logout">Logout</button>
        </form>
    </div>
</nav>
