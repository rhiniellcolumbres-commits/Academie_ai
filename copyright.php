<?php
$pageTitle = 'Copyright and Intellectual Property Notice';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/public-navbar.php';

$backUrl = !empty($_SESSION['user_id']) ? 'dashboard' : './';
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
        <p class="legal-disclaimer"><strong>Disclaimer:</strong> Academie is developed for demonstration and academic purposes as part of a student project. It is not an official educational institution or professional advisory service.</p>
        <p>Academie aims to respect copyright and other intellectual property rights. This page explains the expectations for material shared through the website and how to report a possible infringement.</p>
        <h2>1. Academic project</h2>
        <p>Academie is a student project for demonstration and academic use. It is not an official educational institution or commercial service.</p>
        <h2>2. Using copyrighted material</h2>
        <p>Only upload, reproduce, or submit material that you created, have permission to use, or are otherwise legally allowed to use. Product names, software, libraries, and services owned by others remain the property of their respective owners.</p>
        <h2>3. Reporting a copyright concern</h2>
        <p>If you believe content on the website uses your copyrighted work without authorization, email <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>. Include your name and contact details, identify the copyrighted work, provide the page URL or location of the content, and explain why you believe its use is unauthorized. Include only information needed to assess the report.</p>
        <h2>4. Review of reports</h2>
        <p>The project team may review the information provided and take appropriate action, which may include temporarily restricting or removing the identified content. A report does not guarantee that content will be removed. Please submit accurate information in good faith.</p>
        <h2>5. Requesting a correction</h2>
        <p>If you submitted content that was restricted or removed and believe this happened by mistake, email <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>. Identify the content and explain why you believe it should be restored. The project team will review the information when possible.</p>
        <h2>6. Contact</h2>
        <p>For copyright concerns, email <a href="mailto:rhiniellcolumbres@gmail.com">rhiniellcolumbres@gmail.com</a>.<br>Project name: Academie</p>
    </article>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
