<?php
// Shared navigation for every admin page. Admin is a separate area from the student site.
$adminPage = basename($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['PHP_SELF'] ?? '');
// Build one admin navigation link and mark it active when it matches this page.
$adminLink = function (string $file, string $label) use ($adminPage): string {
    $active = $adminPage === $file ? 'btn-primary' : 'btn-outline-secondary';
    $route = pathinfo($file, PATHINFO_FILENAME);
    return '<a class="btn ' . $active . ' btn-sm" href="' . $route . '">' . $label . '</a>';
};
?>
<nav class="navbar bg-white border-bottom"><div class="container-fluid px-4">
  <a class="navbar-brand fw-bold" href="dashboard">StudyPath <span class="text-primary">Admin</span></a>
  <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
    <span class="text-muted small d-none d-md-inline">Signed in as <?= htmlspecialchars((string)($_SESSION['admin_username'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></span>
    <?= $adminLink('dashboard.php', 'Dashboard') ?>
    <?= $adminLink('students.php', 'Students') ?>
    <form method="post" action="logout.php" class="d-inline">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string)($_SESSION['admin_csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      <button class="btn btn-outline-secondary btn-sm" type="submit">Log out</button>
    </form>
  </div>
</div></nav>
