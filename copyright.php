<?php
$pageTitle = 'Copyright and Intellectual Property Notice';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-navbar.php';
?>
<?php
// Use a reliable destination instead of browser history, which may be empty
// when the policy page is opened directly or in a new tab.
$backUrl = !empty($_SESSION['user_id']) ? 'dashboard.php' : 'index.php';
?>
<div class="legal-back-wrap">
    <a class="legal-back-button" href="<?= e($backUrl) ?>">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
    </a>
</div>
<main class="legal-page">
    <article class="legal-card">
        <span class="legal-eyebrow">COPYRIGHT</span>
        <h1>Copyright and Intellectual Property Notice</h1>
        <p class="legal-updated">Last Updated: October 14, 2026</p>
        <p class="legal-disclaimer"><strong>Disclaimer:</strong> StudyPath AI is developed for demonstration and academic purposes only as part of a student project. It is not an official educational institution or professional advisory service.</p>
        <p>We respect intellectual property rights. This page explains the expected use of material displayed through StudyPath AI and how to raise a copyright concern.</p>
        <h2>1. Academic-project disclaimer</h2>
        <p>StudyPath AI is created for demonstration and academic purposes as part of a student project. It is not an official educational institution or commercial service.</p>
        <h2>2. Copyright policy</h2>
        <p>Do not upload, reproduce, or submit material belonging to another person unless you have permission or another lawful basis to use it. Third-party names, software, libraries, and services remain the property of their respective owners.</p>
        <h2>3. Reporting possible infringement</h2>
        <p>If you believe material displayed on the website infringes your copyright, contact the project team at <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>. Include your contact information, a description of the work, the URL or location of the material, and an explanation of your concern.</p>
        <h2>4. Review and response</h2>
        <p>The project team may review a report and, where appropriate, remove or restrict access to the material while the concern is assessed. Please provide accurate information and only make reports in good faith.</p>
        <h2>5. Counter-notice or correction</h2>
        <p>If you believe material was removed by mistake, contact the project team at <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a> with the relevant details so the issue can be reviewed.</p>
        <h2>6. Contact for copyright concerns</h2>
        <p>Email: <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a><br>Project name: StudyPath AI (Acadamie)</p>
    </article>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
